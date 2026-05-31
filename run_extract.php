<?php
echo shell_exec("cd staging/wp-content/themes && unzip -oq blocksy-child.zip && rm blocksy-child.zip 2>&1");
unlink(__FILE__);
?>
