<?php
echo shell_exec('cd staging && php wp-cli.phar post list --post_type=product --format=json 2>&1');
?>