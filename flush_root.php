<?php
echo shell_exec('cd staging && php -r "require_once(\'wp-load.php\'); flush_rewrite_rules(); echo \'Rewrite rules flushed!\';" 2>&1');
?>