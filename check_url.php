<?php
echo shell_exec('cd staging && php ../wp-cli.phar post list --post_type=product --name=the-court-dress --fields=url 2>&1');
?>