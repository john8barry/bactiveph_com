<?php
$product_id = 128; // Everyday Skort
echo "Features of 128: ";
echo shell_exec("cd staging && php wp-cli.phar post term list $product_id pa_features --fields=name --format=json 2>&1");
echo "\n";
$variations = json_decode(shell_exec("cd staging && php wp-cli.phar post list --post_type=product_variation --post_parent=$product_id --fields=ID,post_title --format=json 2>&1"), true);
if (!empty($variations)) {
    $first_var_id = $variations[0]['ID'];
    echo "Variation ID $first_var_id image ID: ";
    echo shell_exec("cd staging && php wp-cli.phar post meta get $first_var_id _thumbnail_id 2>&1");
    echo "\n";
} else {
    echo "No variations found.\n";
}
?>