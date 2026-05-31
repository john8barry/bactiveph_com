import ftplib
import requests

php_script = """<?php
require_once('wp-load.php');
$args = array(
    'post_type'      => 'product',
    'posts_per_page' => -1,
);
$loop = new WP_Query( $args );
while ( $loop->have_posts() ) : $loop->the_post();
    global $product;
    echo $product->get_slug() . " -> " . $product->get_permalink() . "\\n";
endwhile;
wp_reset_query();
?>"""

with open('get_products.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', 'bActive_FTP_9284!')
    with open('get_products.php', 'rb') as f:
        ftp.storbinary('STOR staging/get_products.php', f)
    
    response = requests.get('https://bactiveph.com/staging/get_products.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('staging/get_products.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
