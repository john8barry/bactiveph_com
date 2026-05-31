import os
import ftplib
import requests

php_script = """<?php
$staging_dir = '/home/waypmvhk/public_html/staging';
$target_dir = '/home/waypmvhk/bactiveph.com/staging';

echo "Executing fixes...\\n";

// 1. If public_html/staging is a directory and not a symlink, remove it.
if (is_dir($staging_dir) && !is_link($staging_dir)) {
    // recursively delete
    shell_exec("rm -rf " . escapeshellarg($staging_dir));
    echo "Removed empty staging directory.\\n";
}

// 2. Create a symlink from bactiveph.com/staging -> public_html/staging
if (!file_exists($staging_dir)) {
    symlink($target_dir, $staging_dir);
    echo "Symlink created successfully.\\n";
} else {
    echo "Symlink or file already exists.\\n";
}

// verify
echo "\\nListing of public_html/staging:\\n";
echo shell_exec("ls -la " . escapeshellarg($staging_dir) . " 2>&1");
?>"""

with open('fix_docroot_remote.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('fix_docroot_remote.php', 'rb') as f:
        ftp.storbinary('STOR fix_docroot_remote.php', f)
    
    response = requests.get('https://bactiveph.com/fix_docroot_remote.php', verify=False)
    print("--- SERVER RESPONSE ---")
    print(response.text)
    
    ftp.delete('fix_docroot_remote.php')
except Exception as e:
    print("Error:", e)
finally:
    try:
        ftp.quit()
    except:
        pass
    os.remove('fix_docroot_remote.php')
