import env_loader  # loads .env
import os
import ftplib
import requests

php_script = """<?php
$public_staging = '/home/waypmvhk/public_html/staging';
$addon_staging = '/home/waypmvhk/bactiveph.com/staging';

echo "Starting fix...\\n";

// 1. Remove the current symlink at public_html/staging
if (is_link($public_staging)) {
    unlink($public_staging);
    echo "Removed public_html/staging symlink.\\n";
} else if (is_dir($public_staging)) {
    shell_exec("rm -rf " . escapeshellarg($public_staging));
    echo "Removed public_html/staging directory.\\n";
}

// 2. Move the addon staging directory to public_html/staging
if (is_dir($addon_staging) && !is_link($addon_staging)) {
    rename($addon_staging, $public_staging);
    echo "Moved staging files to public_html/staging.\\n";
} else {
    echo "addon_staging is not a dir or is a symlink.\\n";
}

// 3. Create a symlink in the addon domain so FTP still works
if (!file_exists($addon_staging)) {
    symlink($public_staging, $addon_staging);
    echo "Created symlink at bactiveph.com/staging -> public_html/staging.\\n";
}

// 4. Use WP-CLI to update the URL
$wp_cli = $public_staging . "/wp-cli.phar";
if (!file_exists($wp_cli)) {
    file_put_contents($wp_cli, file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}

$cmd = "cd " . escapeshellarg($public_staging) . " && php wp-cli.phar search-replace 'https://bactiveph.com/staging' 'https://staging.bactiveph.com' --all-tables 2>&1";
echo "Running WP-CLI...\\n";
echo shell_exec($cmd);

$cmd2 = "cd " . escapeshellarg($public_staging) . " && php wp-cli.phar cache flush 2>&1";
echo shell_exec($cmd2);

echo "Fix complete.\\n";
?>"""

with open('fix_staging_full.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('fix_staging_full.php', 'rb') as f:
        ftp.storbinary('STOR fix_staging_full.php', f)
    
    response = requests.get('https://bactiveph.com/fix_staging_full.php', verify=False)
    print("--- SERVER RESPONSE ---")
    print(response.text)
    
    ftp.delete('fix_staging_full.php')
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove('fix_staging_full.php')
