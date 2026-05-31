<?php
echo shell_exec('cd staging && php ../wp-cli.phar post meta get 36 _visibility 2>&1');
echo shell_exec('cd staging && php ../wp-cli.phar post get 36 --fields=post_name,post_status,post_password 2>&1');
?>