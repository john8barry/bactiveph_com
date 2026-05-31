<?php
$zip = new ZipArchive;
if ($zip->open('deploy.zip') === TRUE) {
    $zip->extractTo('./');
    $zip->close();
    echo "Extracted successfully.";
} else {
    echo "Failed to extract.";
}
unlink('deploy.zip');
unlink('extract.php');
?>
