import os
import env_loader  # loads .env
import ftplib
import requests

php_script = """<?php
echo shell_exec('cd staging && php get_products_internal.php 2>&1');
?>"""

with open('get_products_internal.php', 'w') as f:
    f.write("""<?php
require_once('wp-load.php');
$args = array('post_type' => 'product', 'posts_per_page' => -1);
$loop = new WP_Query( $args );
while ( $loop->have_posts() ) : $loop->the_post();
    global $product;
    echo $product->get_slug() . " -> " . $product->get_permalink() . "\\n";
endwhile;
wp_reset_query();
?>""")

with open('get_products_root.php', 'w') as f:
    f.write(php_script)

ftp = ftplib.FTP()
try:
    ftp.connect('ftp.bactiveph.com', 21)
    ftp.login('bactive@bactiveph.com', os.environ['FTP_PASSWORD'])
    with open('get_products_internal.php', 'rb') as f:
        ftp.storbinary('STOR staging/get_products_internal.php', f)
    with open('get_products_root.php', 'rb') as f:
        ftp.storbinary('STOR get_products_root.php', f)
    
    response = requests.get('https://bactiveph.com/get_products_root.php')
    print("PHP Output:\n", response.text)
    
    ftp.delete('staging/get_products_internal.php')
    ftp.delete('get_products_root.php')
except Exception as e:
    print("Error:", e)
finally:
    ftp.quit()
