<?php
require_once('staging/wp-load.php');
update_option('siteurl', 'https://bactiveph.com/staging');
update_option('home', 'https://bactiveph.com/staging');
flush_rewrite_rules();
echo "Updated siteurl to https://bactiveph.com/staging and flushed rules!\n";
?>