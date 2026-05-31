<?php
echo "Starting cloning process...\n";

// 1. Export production DB
echo "Exporting Production DB...\n";
$export_out = shell_exec('php wp-cli.phar db export production.sql 2>&1');
echo $export_out . "\n";

// 2. Rsync files to staging
echo "Copying files to staging directory...\n";
$rsync_out = shell_exec("rsync -a --exclude='staging' --exclude='production.sql' --exclude='wp_clone.php' . staging/ 2>&1");
echo "Rsync completed. Status: " . ($rsync_out ? $rsync_out : 'OK') . "\n";

// 3. Update wp-config.php in staging
echo "Updating staging wp-config.php...\n";
$config_path = 'staging/wp-config.php';
if(file_exists($config_path)) {
    $config_content = file_get_contents($config_path);
    
    // Regex replace DB_NAME, DB_USER, DB_PASSWORD
    $config_content = preg_replace("/define\(\s*'DB_NAME',\s*'.*?'\s*\);/", "define( 'DB_NAME', 'waypmvhk_stg' );", $config_content);
    $config_content = preg_replace("/define\(\s*'DB_USER',\s*'.*?'\s*\);/", "define( 'DB_USER', 'waypmvhk_stg' );", $config_content);
    $config_content = preg_replace("/define\(\s*'DB_PASSWORD',\s*'.*?'\s*\);/", "define( 'DB_PASSWORD', 'StagingDB_BActive2026!' );", $config_content);
    
    file_put_contents($config_path, $config_content);
    echo "wp-config.php updated successfully.\n";
} else {
    echo "ERROR: wp-config.php not found in staging!\n";
}

// 4. Import DB into staging
echo "Importing DB to staging...\n";
$import_out = shell_exec('cd staging && php wp-cli.phar db import ../production.sql 2>&1');
echo $import_out . "\n";

// 5. Search and Replace URLs
echo "Running Search and Replace...\n";
$sr_out = shell_exec('cd staging && php wp-cli.phar search-replace "https://bactiveph.com" "https://staging.bactiveph.com" --all-tables 2>&1');
echo $sr_out . "\n";

// Clean up production.sql
unlink('production.sql');

// 6. Security & SEO (htpasswd and robots.txt)
echo "Setting up basic auth and robots.txt...\n";

$htpasswd_content = "bactive_team:" . crypt("BActive_Stg_2026!", base64_encode("BActive_Stg_2026!")) . "\n";
file_put_contents('staging/.htpasswd', $htpasswd_content);

$htaccess_path = 'staging/.htaccess';
$htaccess_content = file_get_contents($htaccess_path);
$auth_block = "\n# STAGING AUTH\nAuthType Basic\nAuthName \"Restricted Staging Area\"\nAuthUserFile /home/waypmvhk/bactiveph.com/staging/.htpasswd\nRequire valid-user\n# END STAGING AUTH\n";
file_put_contents($htaccess_path, $auth_block . $htaccess_content);

$robots_content = "User-agent: *\nDisallow: /\n";
file_put_contents('staging/robots.txt', $robots_content);

// Set blog_public to 0
$opt_out = shell_exec('cd staging && php wp-cli.phar option update blog_public 0 2>&1');
echo $opt_out . "\n";

echo "Cloning and Setup Complete!\n";
?>
