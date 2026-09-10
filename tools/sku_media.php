<?php
/**
 * Private WP-CLI only: plan | apply <private-manifest.json> | rollback <manifest>.
 * Never upload this script or its manifest under a public document root.
 * Requires a qualified full backup and review of the generated per-ID mapping.
 */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { exit( 1 ); }

function sku_media_assert( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
}
function sku_media_path( $base, $relative ) {
    sku_media_assert( is_string($relative) && preg_match('#^[A-Za-z0-9_./-]+$#D',$relative)
        && !str_starts_with($relative,'/') && !in_array('..',explode('/',$relative),true), 'Unsafe media path' );
    $path=$base.'/'.$relative;
    sku_media_assert(!is_link($path) && realpath(dirname($path))!==false
        && str_starts_with(realpath(dirname($path)).'/',realpath($base).'/'), 'Media path leaves uploads' );
    return $path;
}
function sku_media_replace( $value, $map ) {
    if (is_array($value)) {
        foreach($value as $key=>$child) {$value[$key]=sku_media_replace($child,$map);}
        return $value;
    }
    return is_string($value) && isset($map[$value]) ? $map[$value] : $value;
}
function sku_media_atomic( $path, $content ) {
    $tmp=tempnam(dirname($path),'.bactive-media-');
    sku_media_assert(false!==$tmp,'Cannot create temporary file');
    sku_media_assert(file_put_contents($tmp,$content)===strlen($content),'Incomplete file write');
    chmod($tmp,fileperms($path)&0777);
    sku_media_assert(rename($tmp,$path),'Atomic replacement failed');
}
function sku_media_restore_attachment( $a, $state ) {
    update_attached_file($a['id'],wp_get_upload_dir()['basedir'].'/'.$a[$state.'_file']);
    wp_update_attachment_metadata($a['id'],$a[$state.'_metadata']);
    clean_post_cache($a['id']);
    sku_media_assert(get_post_meta($a['id'],'_wp_attached_file',true)===$a[$state.'_file']
        && wp_get_attachment_metadata($a['id'])===$a[$state.'_metadata'],'Attachment readback failed for ID '.$a['id']);
}
function sku_media_copy( $source, $dest, $expected ) {
    $tmp=tempnam(dirname($dest),'.bactive-image-');
    sku_media_assert(false!==$tmp,'Cannot create image temporary file');
    try {
        sku_media_assert(copy($source,$tmp)&&hash_file('sha256',$tmp)===$expected,'Asset copy failed');
        chmod($tmp,0644);sku_media_assert(rename($tmp,$dest),'Atomic image replacement failed');
    } finally {if(is_file($tmp)) {unlink($tmp);}}
}
// Offline filesystem regressions load only the helpers, never a site/migration.
if(defined('BACTIVE_SKU_MEDIA_LIBRARY_ONLY') && BACTIVE_SKU_MEDIA_LIBRARY_ONLY) {return;}

$mode=$args[0]??'plan';
$site=rtrim(home_url(),'/');
$roots=array('https://bactiveph.com'=>'/home/waypmvhk/bactiveph.com','https://staging.bactiveph.com'=>'/home/waypmvhk/staging.bactiveph.com');
sku_media_assert(isset($roots[$site]) && realpath(ABSPATH)===$roots[$site],'Unexpected site/root');
$upload=wp_get_upload_dir();
sku_media_assert(empty($upload['error']) && $upload['basedir']===rtrim(ABSPATH,'/').'/wp-content/uploads','Unexpected uploads directory');
$htaccess=ABSPATH.'.htaccess';
sku_media_assert(is_file($htaccess) && !is_link($htaccess),'Missing or linked server routing file');
$marker='# BEGIN B Active SKU media privacy';

if ($mode==='plan') {
    global $wpdb;
    $skus=$wpdb->get_col("SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID=pm.post_id WHERE pm.meta_key='_sku' AND pm.meta_value<>'' AND p.post_type IN ('product','product_variation')");
    $manifest=array('version'=>1,'site'=>$site,'created_at'=>gmdate('c'),'attachments'=>array(),'files'=>array(),'htaccess_before'=>file_get_contents($htaccess));
    sku_media_assert(!str_contains($manifest['htaccess_before'],$marker),'Media privacy routing already exists; inspect receipt instead of replanning');
    $rules=array();
    foreach(get_posts(array('post_type'=>'attachment','post_status'=>'inherit','numberposts'=>-1,'orderby'=>'ID','order'=>'ASC')) as $post) {
        $file=get_post_meta($post->ID,'_wp_attached_file',true);
        $matched=(bool)preg_match('/(?:1st|2nd)Batch_/i',basename($file));
        foreach($skus as $sku) {$matched=$matched||stripos(basename($file),$sku)!==false;}
        if(!$matched) {continue;}
        $metadata=wp_get_attachment_metadata($post->ID);
        sku_media_assert(is_array($metadata) && ($metadata['file']??null)===$file,'Attachment metadata mismatch for ID '.$post->ID);
        $names=array(basename($file));
        foreach(($metadata['sizes']??array()) as $size) {$names[]=$size['file'];}
        if(!empty($metadata['original_image'])) {$names[]=$metadata['original_image'];}
        $names=array_values(array_unique($names));$map=array();
        foreach($names as $index=>$name) {
            sku_media_assert(basename($name)===$name,'Unexpected nested derivative');
            $old=dirname($file).'/'.$name;
            $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
            sku_media_assert(in_array($ext,array('png','jpg','jpeg','webp','avif','gif'),true),'Unexpected asset type');
            $new_name='bactive-image-'.$post->ID.($index?'-'.$index:'').'.'.$ext;
            $new=dirname($file).'/'.$new_name;
            $source=sku_media_path($upload['basedir'],$old);$dest=sku_media_path($upload['basedir'],$new);
            sku_media_assert(is_file($source),'Missing source image for ID '.$post->ID);
            $hash=hash_file('sha256',$source);
            sku_media_assert(!file_exists($dest)||hash_file('sha256',$dest)===$hash,'Destination collision');
            $manifest['files'][]=array('old'=>$old,'new'=>$new,'sha256'=>$hash);
            $map[$name]=$new_name;$map[$old]=$new;
            $rules[]='RewriteRule ^wp-content/uploads/'.preg_quote($old,'#').'$ /wp-content/uploads/'.$new.' [R=301,L,NE]';
        }
        $manifest['attachments'][]=array('id'=>$post->ID,'before_file'=>$file,'after_file'=>$map[$file],
            'before_metadata'=>$metadata,'after_metadata'=>sku_media_replace($metadata,$map));
    }
    $manifest['htaccess_after']=$marker."\n<IfModule mod_rewrite.c>\nRewriteEngine On\n".implode("\n",$rules)."\n</IfModule>\n# END B Active SKU media privacy\n\n".$manifest['htaccess_before'];
    echo wp_json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
    return;
}

sku_media_assert(in_array($mode,array('apply','rollback'),true),'Unknown mode');
$manifest_path=$args[1]??'';
sku_media_assert(str_starts_with(realpath($manifest_path)?:'', '/home/waypmvhk/.bactive-sku-privacy/'),'Manifest must be in the private release directory');
$m=json_decode(file_get_contents($manifest_path),true,512,JSON_THROW_ON_ERROR);
sku_media_assert(($m['version']??null)===1 && ($m['site']??null)===$site,'Manifest site/version mismatch');
$from=$mode==='apply'?'before':'after';$to=$mode==='apply'?'after':'before';
sku_media_assert(in_array(file_get_contents($htaccess),array($m['htaccess_before'],$m['htaccess_after']),true),'Server routing changed since snapshot');
foreach($m['attachments'] as $a) {
    sku_media_path($upload['basedir'],$a['before_file']);sku_media_path($upload['basedir'],$a['after_file']);
    sku_media_assert(in_array(get_post_meta($a['id'],'_wp_attached_file',true),array($a['before_file'],$a['after_file']),true)
        && in_array(wp_get_attachment_metadata($a['id']),array($a['before_metadata'],$a['after_metadata']),true),'Attachment changed since snapshot: '.$a['id']);
}
foreach($m['files'] as $f) {
    $source=sku_media_path($upload['basedir'],$f['old']);$dest=sku_media_path($upload['basedir'],$f['new']);
    sku_media_assert(is_file($source)&&hash_file('sha256',$source)===$f['sha256'],'Source checksum changed');
    if($mode==='apply') {sku_media_assert(!file_exists($dest)||hash_file('sha256',$dest)===$f['sha256'],'Destination checksum changed');}
}
// Originals remain on disk for rollback; routing redirects old public URLs.
if($mode==='apply') {
    foreach($m['files'] as $f) {
        $source=sku_media_path($upload['basedir'],$f['old']);$dest=sku_media_path($upload['basedir'],$f['new']);
        if(!file_exists($dest)) {
            sku_media_copy($source,$dest,$f['sha256']);
        }
        sku_media_assert(hash_file('sha256',$dest)===$f['sha256'],'Copied asset checksum mismatch');
    }
}
$changed=array();
try {
    foreach($m['attachments'] as $a) {
        $changed[]=array('id'=>$a['id'],'entry_file'=>get_post_meta($a['id'],'_wp_attached_file',true),
            'entry_metadata'=>wp_get_attachment_metadata($a['id']));
        sku_media_restore_attachment($a,$to);
    }
    sku_media_atomic($htaccess,$m['htaccess_'.$to]);
} catch(Throwable $error) {
    $failures=array();
    foreach(array_reverse($changed) as $a) {
        try {sku_media_restore_attachment($a,'entry');} catch(Throwable $recovery) {$failures[]=$a['id'];}
    }
    if($failures) {throw new RuntimeException('Recovery requires intervention for attachment IDs '.implode(',',$failures),0,$error);}
    throw $error;
}
echo wp_json_encode(array('site'=>$site,'mode'=>$mode,'attachments'=>count($m['attachments']),'assets'=>count($m['files']),
    'routing_sha256'=>hash_file('sha256',$htaccess),'completed_at'=>gmdate('c')))."\n";
