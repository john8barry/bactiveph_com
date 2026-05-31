<?php
echo shell_exec("cd staging && php wp_setup.php 2>&1");
unlink(__FILE__);
?>
