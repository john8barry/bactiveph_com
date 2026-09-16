<?php
/** Standalone real-file decoder + simulated WordPress/Woo lifecycle regression contracts. */
define('ABSPATH', __DIR__);
class WP_Error { public function __construct(public $code, public $message, public $data=null) {} public function get_error_message(){return $this->message;} }
function is_wp_error($x){return $x instanceof WP_Error;}
function __($s,$domain=''){return $s;}
function absint($n){return abs((int)$n);}
function add_filter($name,$fn,$priority=10,$argc=1){$GLOBALS['hooks'][$name][]=$fn;}
function add_action(...$args){add_filter(...$args);}
function get_post_meta($id,$key,$single=true){return $GLOBALS['meta'][$id][$key]??'';}
function get_post_type($id){return $GLOBALS['types'][$id]??'attachment';}
function get_post_status($id){return $GLOBALS['statuses'][$id]??'draft';}
function get_attached_file($id){return $GLOBALS['files'][$id]??false;}
function wp_convert_hr_to_bytes($s){if($s==='-1')return -1;return (int)$s*(strtolower(substr($s,-1))==='g'?1073741824:1048576);}
function current_user_can(...$args){return $GLOBALS['can']??true;}
function wp_verify_nonce($nonce,$action){return $nonce==='valid' && $action==='bactive_photo_1';}
function update_post_meta($id,$key,$value){$GLOBALS['meta'][$id][$key]=$value;return true;}
function bactive_catalogue_attachment($id){$p=get_attached_file($id); $s=$p?@getimagesize($p):false;return $s?['id'=>$id,'url'=>'https://example.test/'.basename($p),'width'=>$s[0],'height'=>$s[1],'sha256'=>hash_file('sha256',$p)]:null;}
class WC_Admin_Meta_Boxes{public static function add_error($s){$GLOBALS['notices'][]=$s;}}
class PhotoProduct {
    public $id=100; public $image=99; public $gallery=[]; public $settings=[]; public $status='publish'; public $variation=false;
    public function get_id(){return $this->id;} public function get_image_id($c=''){return $this->image;}
    public function set_image_id($n){$this->image=$n;} public function get_gallery_image_ids($c=''){return $this->gallery;}
    public function set_gallery_image_ids($a){$this->gallery=$a;} public function get_meta(...$args){return $this->settings;}
    public function update_meta_data($k,$v){$this->settings=$v;} public function get_status($c=''){return $this->status;}
    public function set_status($s){$this->status=$s;} public function is_type($s){return $this->variation && $s==='variation';}
}
require __DIR__.'/../wp-content/themes/blocksy-child/inc/catalogue-photo-policy.php';
function check($ok,$message){$GLOBALS['checks']=1+($GLOBALS['checks']??0);if(!$ok)throw new RuntimeException($message);}
$dir=sys_get_temp_dir().'/bactive-photo-test-'.bin2hex(random_bytes(6));mkdir($dir);
register_shutdown_function(function()use($dir){foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);});
function fixture($id,$w,$h,$type='jpeg',$alpha=false){global $dir; $p="$dir/$id.$type";$im=imagecreatetruecolor($w,$h);if($alpha){imagealphablending($im,false);imagesavealpha($im,true);$bg=imagecolorallocatealpha($im,255,255,255,50);}else{$bg=imagecolorallocate($im,240,240,240);}imagefill($im,0,0,$bg);if($type==='png')imagepng($im,$p);else imagejpeg($im,$p,88);imagedestroy($im);$GLOBALS['files'][$id]=$p;}
function approve($id,$role='portrait'){$GLOBALS['meta'][$id]['_bactive_photo_role']=$role;$GLOBALS['meta'][$id]['_bactive_photo_review']=['schema_version'=>1,'role'=>$role,'sha256'=>hash_file('sha256',get_attached_file($id))];}
fixture(1,800,1200);fixture(2,799,1200);fixture(3,1200,1200);fixture(4,800,1200,'png',true);fixture(5,804,1200);fixture(6,805,1200);fixture(7,800,1200,'png');
check(is_wp_error(bactive_catalogue_photo_validate(1)),'Review required even for correct dimensions');approve(1);
check(!is_wp_error(bactive_catalogue_photo_validate(1)),'Reviewed exact minimum portrait passes full decoder');
foreach([2,3,4,6] as $id){approve($id);check(is_wp_error(bactive_catalogue_photo_validate($id)),'Reject wrong dimensions/transparent file '.$id);}
approve(5);check(!is_wp_error(bactive_catalogue_photo_validate(5)),'Half-percent rounding tolerance allowed');
approve(7);check(!is_wp_error(bactive_catalogue_photo_validate(7)),'Opaque PNG accepted');
approve(3,'comparison');check(!is_wp_error(bactive_catalogue_photo_validate(3,'gallery')),'Explicit gallery comparison accepted');
check(is_wp_error(bactive_catalogue_photo_validate(3)),'Comparison cannot become main/variation photo');
$meta[1]['_bactive_photo_review']['sha256']=str_repeat('0',64);check(is_wp_error(bactive_catalogue_photo_validate(1)),'Changed file hash invalidates review');approve(1);
fixture(8,800,1200);file_put_contents($files[8],substr(file_get_contents($files[8]),0,200));check(is_wp_error(bactive_catalogue_photo_validate(8,'portrait',false)),'Truncated file rejected');
check(is_wp_error(bactive_catalogue_photo_validate(999)),'Missing file rejected');
$types[100]='product';$statuses[100]='publish';$meta[100]=['_thumbnail_id'=>99,'_product_image_gallery'=>'3','_bactive_colour_settings'=>['colours'=>['blue'=>['preview_image_id'=>99]]]];
check(null===bactive_catalogue_photo_meta_guard(null,100,'_thumbnail_id',99),'Legacy same-image maintenance allowed');
check(false===bactive_catalogue_photo_meta_guard(null,100,'_thumbnail_id',2),'Native metadata backstop blocks invalid main');
check(null===bactive_catalogue_photo_meta_guard(null,100,'_thumbnail_id',1),'Backstop allows valid new photo');
check(null===bactive_catalogue_photo_meta_guard(null,100,'_price','123'),'Price maintenance unaffected');
check(false===bactive_catalogue_photo_meta_guard(null,100,'_product_image_gallery','3,2'),'Gallery additions validated');
check(null===bactive_catalogue_photo_meta_guard(null,100,'_product_image_gallery','3'),'Unchanged gallery allowed');
check(false===bactive_catalogue_photo_meta_guard(null,100,'_bactive_colour_settings',['colours'=>['red'=>['preview_image_id'=>99]]]),'Moving invalid preview to another colour is a new assignment');
check(null===bactive_catalogue_photo_meta_guard(null,1,'_thumbnail_id',2),'Non-product metadata unaffected');
$p=new PhotoProduct();$p->image=2;$p->gallery=[3,2];$p->settings=['colours'=>['red'=>['preview_image_id'=>2]]];
check(is_wp_error(bactive_catalogue_photo_rest_guard($p)),'REST rejects invalid pending assignments before save');
bactive_catalogue_photo_object_guard($p);
check($p->image===99&&$p->gallery===[3]&&$p->settings===$meta[100]['_bactive_colour_settings'],'Native save restores previous invalid-assignment fields');
check($p->status==='publish','Native edit does not unpublish existing catalogue');
$p->image=1;check(!is_wp_error(bactive_catalogue_photo_rest_guard($p)),'REST accepts approved replacement while retaining old unchanged choices');
$p->variation=true;$p->image=3;check(is_wp_error(bactive_catalogue_photo_rest_guard($p)),'Variation REST enforces portrait role');
$p->image=2;$csv=$hooks['woocommerce_product_import_pre_insert_product_object'][0];$threw=false;try{$csv($p);}catch(Exception $e){$threw=true;}check($threw,'CSV invalid row reports caught import error');
$p->image=1;check($csv($p)===$p,'CSV approved replacement allowed');
$p->variation=false;$p->id=0;$p->image=0;$p->gallery=[];$p->settings=[];bactive_catalogue_photo_object_guard($p);check($p->status==='draft','New publication without a valid main photo retained as draft');
$p->status='publish';$p->image=1;bactive_catalogue_photo_object_guard($p);check($p->status==='publish','New publication with reviewed main photo allowed');
$guard=bactive_catalogue_photo_publish_guard(['post_type'=>'product','post_status'=>'publish'],['ID'=>0]);check($guard['post_status']==='publish','New Woo publication validates pending object before metadata is written');unset($GLOBALS['bactive_photo_saving_product']);
$guard=bactive_catalogue_photo_publish_guard(['post_type'=>'product','post_status'=>'publish'],['ID'=>102]);check($guard['post_status']==='draft','Direct WordPress publication cannot bypass main-photo requirement');
$guard=bactive_catalogue_photo_publish_guard(['post_type'=>'product','post_status'=>'publish'],['ID'=>100]);check($guard['post_status']==='publish','Direct maintenance of existing published product remains possible');
$old=$meta[1];$can=false;bactive_catalogue_photo_fields_save(['ID'=>1],['bactive_photo_nonce'=>'valid','bactive_photo_role'=>'comparison','bactive_photo_confirm'=>'1']);check($old===$meta[1],'Review capability enforced');$can=true;
bactive_catalogue_photo_fields_save(['ID'=>1],['bactive_photo_nonce'=>'invalid','bactive_photo_role'=>'comparison','bactive_photo_confirm'=>'1']);check($old===$meta[1],'Review nonce enforced');
bactive_catalogue_photo_fields_save(['ID'=>1],['bactive_photo_nonce'=>'valid','bactive_photo_role'=>'portrait','bactive_photo_confirm'=>'1']);check($meta[1]['_bactive_photo_review']['sha256']===hash_file('sha256',$files[1]),'Review binds exact current bytes');
check(isset($hooks['update_post_metadata'],$hooks['add_post_metadata'],$hooks['woocommerce_before_product_object_save'],$hooks['woocommerce_rest_pre_insert_product_variation_object']),'Native metadata and variation hooks installed');
echo "Photo policy real decoders, exact reviews, native/REST/CSV/metadata guards, publication and review auth PASS\n";
// New publication must inspect unchanged gallery, previews and variation images too.
$p=new PhotoProduct();$p->id=103;$p->image=1;$p->gallery=[2];$meta[103]['_product_image_gallery']='2';
check(isset(bactive_catalogue_photo_object_errors($p)['publication']),'Invalid unchanged draft gallery blocks publication');
$p->gallery=[];$p->settings=['colours'=>['red'=>['preview_image_id'=>2]]];$meta[103]['_bactive_colour_settings']=$p->settings;
check(isset(bactive_catalogue_photo_object_errors($p)['publication']),'Invalid unchanged draft preview blocks publication');
class PhotoRequest {public function __construct(public $params){} public function get_params(){return $this->params;}public function get_method(){return 'POST';}public function get_route(){return '/wc/v3/products/100';}}
check(is_wp_error(bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'images'=>[['id'=>2]]]))),'Raw REST invalid image rejects before callback/other writes');
check(is_wp_error(bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'images'=>[['id'=>'bogus']]]))),'Raw REST nonnumeric ID rejected');
check(is_wp_error(bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'images'=>[['id'=>999]]]))),'Raw REST missing image rejected');
check(is_wp_error(bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'images'=>[['src'=>'https://external.test/image.jpg']]]))),'Remote REST upload requires separate media review');
check(null===bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'regular_price'=>'90'])),'Unrelated REST fields unaffected');
check(null===bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'images'=>[['id'=>99]]])),'Unchanged legacy REST image permitted');
check(is_wp_error(bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'variations'=>[['id'=>101,'image'=>['id'=>2]]]]))),'Embedded v1 variation checked before parent mutation');
check(false===bactive_catalogue_photo_meta_guard(null,100,'_thumbnail_id',[1]),'Malformed native image ID rejected');
echo "Photo policy publication and raw REST atomic rejection regressions PASS\n";

check(null===bactive_catalogue_photo_request_guard(null,null,new PhotoRequest(['id'=>100,'images'=>[['id'=>1,'src'=>'https://example.test/photo.jpg']]])),'REST may echo a URL when assigning an existing reviewed ID');
echo $GLOBALS['checks']." assertions PASS\n";
