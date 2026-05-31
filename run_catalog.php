<?php
echo shell_exec('cd staging && php setup_catalog.php 2>&1');
echo "Done running setup_catalog via CLI";
?>