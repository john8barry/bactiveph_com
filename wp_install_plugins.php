<?php
putenv('WP_CLI_CACHE_DIR=/.wp-cli/cache');
$output = shell_exec('php wp-cli.phar plugin install host-webfonts-local shortpixel-image-optimiser --activate 2>&1');
echo "Plugin Installation:\n$output\n";

$output2 = shell_exec('php wp-cli.phar plugin list 2>&1');
echo "Plugins:\n$output2\n";
?>
