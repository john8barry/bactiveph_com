<?php
echo shell_exec('cd staging && php wp-cli.phar wc product create --name="Sample Dress" --type="variable" --status="publish" --regular_price="1500" --user="1" 2>&1');
// Creating global attributes via WP-CLI is tricky, doing a quick hack with wc api or just creating simple product to test tabs
echo shell_exec('cd staging && php wp-cli.phar wc product update 1 --short_description="This is the short description that goes in the Features & Fit tab." 2>&1');
echo "Verification setup done on staging.";
?>