<?php
echo shell_exec('cd staging && php ../wp-cli.phar litespeed-purge all 2>&1');
echo shell_exec('cd staging && php ../wp-cli.phar litespeed-online 2>&1');
?>