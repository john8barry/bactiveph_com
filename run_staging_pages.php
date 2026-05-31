<?php
echo shell_exec("cd staging && php create_pages.php 2>&1");
unlink(__FILE__);
?>
