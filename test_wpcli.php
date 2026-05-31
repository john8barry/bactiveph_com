<?php
$output = shell_exec('php wp-cli.phar core version 2>&1');
echo $output;
?>
