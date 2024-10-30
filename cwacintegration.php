<?php
/*
Plugin Name: Campwire
Plugin URI:  https://www.grit.online/campwire-plugin/
Description: Submits Campwire customer name and email to ActiveCampaign list.
Author:      GRIT Online Inc.
Version:     0.0.2
Author URI:  https://www.grit.online/
License:     GPL2
*/
 
// =================================================
// Allow code only if WordPress is loaded
// =================================================
if ( !defined('ABSPATH') ) {
	header( 'HTTP/1.0 403 Forbidden' );
	exit;
}

// =================================================
// Define Constants
// =================================================
if ( ! defined( 'GRITONL_CWAC_PLUGIN_VERSION' ) ) {
	define( 'GRITONL_CWAC_PLUGIN_VERSION', '0.0.2' );
}

if ( ! defined( 'GRITONL_CWAC_PLUGIN_NAME' ) ) {
	define( 'GRITONL_CWAC_PLUGIN_NAME', 'Campwire' );
}

// =================================================
// Register Hooks
// =================================================
register_activation_hook( __FILE__, 'gritonl_cwac_activate' );
register_deactivation_hook( __FILE__, 'gritonl_cwac_deactivate' );
register_uninstall_hook(__FILE__, 'gritonl_cwac_uninstall');

// =================================================
// Create shortcode
// =================================================
add_shortcode( 'gritonl_cwac', 'gritonl_cwac_handler' );

// =================================================
// Load admin functions only if user is admin
// =================================================
if ( is_admin() ) {
  require_once( dirname( __FILE__ ) . '/admin/cwac_admin.php' );
}

// =================================================
// Add AC Tracking code in head section
// =================================================
if ( get_option('gritonl_cwac_accodeon') ) { add_action ( 'wp_head', 'gritonl_cwac_accode' ); }
function gritonl_cwac_accode() { echo get_option('gritonl_cwac_accode'); }

// =================================================
// Capture Campwire data and submit
// =================================================
function gritonl_cwac_handler( $atts = array(), $content = null, $shortcode ) {
  static $gritonl_cwac_run = false;
  if ( $gritonl_cwac_run !== true ) {
    //cw_order_id        = Order ID from Campwire
    //cw_order_email     = Customers email address
    //cw_order_firstname = Customers firstname
    //cw_order_lastname  = Customers lastname
    //cw_order_price     = Purchase price without taxes
    //cw_order_currency  = Currency used in purchase, defaults to EUR
    //cw_order_pid       = Purchased product ID on Campwire
    $list = get_option( 'gritonl_cwac_aclist' );
    if ( isset($_REQUEST['cw_order_email']) && $list ){
      $email = sanitize_email( $_REQUEST['cw_order_email'] );
      $fname = sanitize_text_field( $_REQUEST['cw_order_firstname'] );
      $lname = sanitize_text_field( $_REQUEST['cw_order_lastname'] );
      $tags = get_option( 'gritonl_cwac_actags' );
      $tagss = ""; foreach ($tags as $tag) { $tagss.=$tag.","; }
      $data = array (
        'email' => $email,
        'first_name' => $fname,
        'last_name' => $lname,
        'tags' => $tagss,
        'p['.$list.']' => $list, 
      );
      $result = gritonl_cwac_api( 'contact_add', $data, 'POST' );
      if( WP_DEBUG ){ error_log(print_r($result,true)); }
    }
  } 
  $gritonl_cwac_run = true;
  return;
}

// =================================================
// ActiveCampaign API
// =================================================
function gritonl_cwac_api( $action, $p = array(), $method = 'GET' ){
  $acurl = get_option( 'gritonl_cwac_acurl' );
  $acapikey = get_option( 'gritonl_cwac_acapikey' );
  $endpoint = '/admin/api.php?';
  
  $args = array (
    'api_action' => $action,
    'api_key' => $acapikey,  
    'api_output' => 'json',
  );
  
  foreach( $p as $key => $value ) $args[$key] = $value;
  
  if ($action == 'list_list'){
    $args['ids'] = 'all';
    $args['full'] = 1;
  }
  
  $get='';
  if ( $method == 'GET' ){
    foreach( $args as $key => $value ) $get .= urlencode($key) . '=' . urlencode($value) . '&';
  }
  
  $data = array(
    'header' => "Content-type: application/x-www-form-urlencoded\r\n",
    'method' => $method,
  );
  
  if ( $method == 'POST' ){
    $get .= 'api_action='.urlencode($action). '&'; 
    foreach( $args as $key => $value ) $data['body'][$key] = $value;
  }
  
  $get = rtrim( $get, '& ' );
  
  if ( $action == 'track_site_code' ){ $endpoint = '/api/2/track/site/code?'; }
  
  $url = $acurl . $endpoint . $get;
  
  $result = wp_remote_request( $url, $data );
  
  if ( $result['response']['code'] == 200 ){ return json_decode( $result['body'], true ); }
  
  return;
}

// =================================================
// Activate plugin function
// =================================================
function gritonl_cwac_activate(){
  # Create custom options
  add_option( 'gritonl_cwac_acgood', '' );
  add_option( 'gritonl_cwac_acurl', '' );
  add_option( 'gritonl_cwac_acapikey', '' );
  add_option( 'gritonl_cwac_actags', array( "Campwire" ) );
  add_option( 'gritonl_cwac_aclists', array() );
  add_option( 'gritonl_cwac_aclist', '' );
  add_option( 'gritonl_cwac_accodeon', '' );
  add_option( 'gritonl_cwac_accode', '' );
}

// =================================================
// Deactivate plugin function, do not delete options
// =================================================
function gritonl_cwac_deactivate(){
  # Nothing yet
}

// =================================================
// Uninstall plugin and delete options
// =================================================  
function gritonl_cwac_uninstall(){
  # Delete plugin options
  delete_option('gritonl_cwac_acgood');
  delete_option('gritonl_cwac_acurl');
  delete_option('gritonl_cwac_acapikey');
  delete_option('gritonl_cwac_actags');
  delete_option('gritonl_cwac_aclists');
  delete_option('gritonl_cwac_aclist');
  delete_option('gritonl_cwac_accodeon');
  delete_option('gritonl_cwac_accode');
}