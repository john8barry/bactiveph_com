<?php
echo shell_exec('cd staging && php wp-cli.phar plugin install woo-variation-swatches --activate 2>&1');
echo shell_exec('cd staging && php wp-cli.phar plugin install filter-everything --activate 2>&1');
echo "Plugins installed on staging.";
?>