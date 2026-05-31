import ftplib
import requests

php_script = """<?php
require_once('wp-load.php');
global $wpdb;
$count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
echo "Total products in DB: $count\\n";

$results = $wpdb->get_results("SELECT post_title, post_status, post_name FROM {$wpdb->prefix}posts WHERE post_type = 'product'");
foreach($results as $row) {
    echo $row->post_title . ' - ' . $row->post_name . ' (' . $row->post_status . ')\\n';
}
?>"""

with open('get_db_products.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('get_db_products.php', 'rb') as f:
        ftp.storbinary('STOR staging/get_db_products.php', f)
    
    response = requests.get('https://bactiveph.com/staging/get_db_products.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('staging/get_db_products.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
