<?php
require_once('staging/wp-load.php');
delete_option('rewrite_rules');
flush_rewrite_rules(true);
echo "Flushed rewrite rules!\n";
?>