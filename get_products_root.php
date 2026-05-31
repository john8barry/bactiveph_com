<?php
echo shell_exec('cd staging && php get_products_internal.php 2>&1');
?>