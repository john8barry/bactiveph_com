<?php
$output = shell_exec('php wp-cli.phar updraftplus backup 2>&1');
echo $output;
?>
