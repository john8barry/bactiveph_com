<?php
/*
Plugin Name: Disable XML-RPC
Description: Disables XML-RPC to prevent amplification brute-force attacks.
*/

add_filter( 'xmlrpc_enabled', '__return_false' );
