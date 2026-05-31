<?php
$output = shell_exec('php -v 2>&1');
echo "PHP Version: $output\n";
$output2 = shell_exec('curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar 2>&1');
echo "Download WP-CLI: $output2\n";
$output3 = shell_exec('php wp-cli.phar --info 2>&1');
echo "WP-CLI Info: $output3\n";
?>
