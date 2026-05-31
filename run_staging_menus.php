<?php
echo shell_exec("cd staging && php create_menus.php 2>&1");
unlink(__FILE__);
?>
