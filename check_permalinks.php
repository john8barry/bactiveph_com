<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== PERMALINK STRUCTURE ===\n";
echo shell_exec('cd staging && php ../wp-cli.phar option get permalink_structure 2>&1');
echo "\n=== WOOCOMMERCE PERMALINKS ===\n";
echo shell_exec('cd staging && php ../wp-cli.phar option get woocommerce_permalinks 2>&1');
?>