<?php
require 'wp-load.php';

$colors = [
    24 => '#FAF8F4', // Court Ivory
    26 => '#1C1B19', // Onyx
    27 => '#D8A7A0', // Sakura (Dusty Rose)
    28 => '#B4C6D3', // Powder (approx)
    29 => '#9CAE92', // Sagewood
    30 => '#C9A0DC', // Wisteria (approx)
    31 => '#6E675F', // Stone
    33 => '#EADDD7', // Almond
    32 => '#F2D3B8', // Apricot
];

foreach($colors as $term_id => $hex) {
    update_term_meta($term_id, 'product_attribute_color', $hex);
}
echo 'Colour terms updated with hex values!';
