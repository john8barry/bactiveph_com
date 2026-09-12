<?php
if(PHP_SAPI!=='cli') {exit(1);}
define('WP_CLI',true);define('BACTIVE_SKU_MEDIA_LIBRARY_ONLY',true);
require __DIR__.'/../tools/sku_media.php';
$dir=sys_get_temp_dir().'/bactive-media-test-'.bin2hex(random_bytes(6));mkdir($dir,0700);mkdir($dir.'/uploads');mkdir($dir.'/outside');
$checks=0;
function media_check($condition,$label) {if(!$condition) {throw new RuntimeException($label);}++$GLOBALS['checks'];}
try {
    file_put_contents($dir.'/uploads/original.png','original bytes');
    $source=$dir.'/uploads/original.png';$dest=$dir.'/uploads/neutral.png';
    foreach(array('../outside/file.png','/tmp/file.png','name with spaces.png') as $bad) {
        try {sku_media_path($dir.'/uploads',$bad);throw new LogicException('Unsafe path accepted');}
        catch(RuntimeException $e) {++$checks;}
    }
    symlink($dir.'/outside',$dir.'/uploads/linked');
    try {sku_media_path($dir.'/uploads','linked/file.png');throw new LogicException('Symlink escape accepted');}
    catch(RuntimeException $e) {++$checks;}
    sku_media_copy($source,$dest,hash_file('sha256',$source));
    media_check(file_get_contents($dest)==='original bytes','Copied image changed');
    try {sku_media_copy($source,$dest,str_repeat('0',64));throw new LogicException('Invalid checksum accepted');}
    catch(RuntimeException $e) {++$checks;}
    media_check(file_get_contents($dest)==='original bytes','Failed copy damaged destination');
    media_check(count(glob($dir.'/uploads/.bactive-image-*'))===0,'Failed copy left temporary file');
    file_put_contents($dir.'/routing','original routing');chmod($dir.'/routing',0640);
    sku_media_atomic($dir.'/routing','new routing');
    media_check(file_get_contents($dir.'/routing')==='new routing','Atomic routing update');
    media_check((fileperms($dir.'/routing')&0777)===0640,'Routing permissions changed');
    echo "SKU media safety: {$checks} checks passed\n";
} finally {
    foreach(array('uploads/original.png','uploads/neutral.png','uploads/linked','routing') as $file) {if(file_exists($dir.'/'.$file)||is_link($dir.'/'.$file)) {unlink($dir.'/'.$file);}}
    rmdir($dir.'/uploads');rmdir($dir.'/outside');rmdir($dir);
}
