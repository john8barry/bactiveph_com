<?php
require 'wp-load.php';
$states = WC()->countries->get_states('PH');
echo json_encode($states);
