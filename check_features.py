import ftplib
import requests

php_script = """<?php
echo "---FEATURES OF POST 83---\\n";
echo shell_exec('cd staging && php wp-cli.phar post term list 83 pa_features --format=json 2>&1');
echo "\\n---VARIATIONS OF POST 83---\\n";
echo shell_exec('cd staging && php wp-cli.phar post list --post_type=product_variation --post_parent=83 --fields=ID,post_title --format=json 2>&1');
echo "\\n---THUMBNAIL OF FIRST VARIATION---\\n";
echo shell_exec('cd staging && php wp-cli.phar post meta get 84 _thumbnail_id 2>&1'); // assuming 84 is a variation ID, let's just get the first variation dynamically
?>"""

php_script2 = """<?php
$product_id = 83; // Match Dress
echo "Features of 83: ";
echo shell_exec("cd staging && php wp-cli.phar post term list $product_id pa_features --fields=name --format=json 2>&1");
echo "\\n";

$variations = json_decode(shell_exec("cd staging && php wp-cli.phar post list --post_type=product_variation --post_parent=$product_id --fields=ID,post_title --format=json 2>&1"), true);
if (!empty($variations)) {
    $first_var_id = $variations[0]['ID'];
    echo "Variation ID $first_var_id image ID: ";
    echo shell_exec("cd staging && php wp-cli.phar post meta get $first_var_id _thumbnail_id 2>&1");
    echo "\\n";
} else {
    echo "No variations found.\\n";
}
?>"""

with open('check_details_root.php', 'w') as f:
    f.write(php_script2)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('check_details_root.php', 'rb') as f:
        ftp.storbinary('STOR check_details_root.php', f)
    
    response = requests.get('https://bactiveph.com/check_details_root.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('check_details_root.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
