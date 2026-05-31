<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== ROOT VERIFY CHECKSUMS ===\n";
echo shell_exec('php wp-cli.phar core verify-checksums 2>&1');

echo "\n=== STAGING REWRITE FLUSH ===\n";
echo shell_exec('cd staging && php ../wp-cli.phar rewrite flush --hard 2>&1');

echo "\n=== STAGING POST LIST ===\n";
echo shell_exec('cd staging && php ../wp-cli.phar post list --post_type=product --post_status=publish --fields=ID,post_title,post_status,post_name --format=table 2>&1');
?>