<?php
if (!file_exists('wp-cli.phar')) {
    file_put_contents('wp-cli.phar', file_get_contents('https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar'));
}
echo "=== WP POST LIST ===\n";
echo shell_exec('php wp-cli.phar post list --post_type=product --post_status=publish --fields=ID,post_title,post_status --format=table 2>&1');
?>