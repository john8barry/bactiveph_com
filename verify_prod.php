<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== WP CORE VERIFY CHECKSUMS ===\n";
echo shell_exec('php wp-cli.phar core verify-checksums 2>&1');
?>