<?php

// =================================================
// Allow code only if plugin is active
// =================================================
if ( ! defined( 'GRITONL_CWAC_PLUGIN_VERSION' ) ) {
	header( 'HTTP/1.0 403 Forbidden' );
  exit;
}

// =================================================
// Allow code only for admins
// =================================================
if ( !is_admin() ) {
  wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}
 
// =================================================
// Register options page in admin menu
// =================================================
add_action( 'admin_menu', 'gritonl_cwac_plugin_menu' );

function gritonl_cwac_plugin_menu() {
  add_options_page( GRITONL_CWAC_PLUGIN_NAME.' Settings', GRITONL_CWAC_PLUGIN_NAME, 'manage_options', 'gritonl_cwac', 'gritonl_cwac_plugin_options' );
}

// =================================================
// Plugin Admin Menu Options
// =================================================
function gritonl_cwac_plugin_options() {
	// Allow only admins
  if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
  
  // Show settings options
	echo '<div class="wrap">';
	echo '<h1>'.GRITONL_CWAC_PLUGIN_NAME.' Settings</h1>';
  
  $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : "integrations" ;
  
  ?>
  <h2 class="nav-tab-wrapper">
    <button class="nav-tab <?php echo $tab == 'integrations' ? 'nav-tab-active' : ''; ?>" onclick="openTab(event, 'integrations')">Integrations</button>
  </h2>
  <br>
  <?php
    
  // Save integration tab settings
  if(isset($_POST['integrations-saved']) && wp_verify_nonce($_POST['nonce'], 'integrations-saved') ){
    // Get and save AC settings
    $acurl = sanitize_textarea_field($_POST['acurl']);
    $acapikey = sanitize_textarea_field($_POST['acapikey']);
    $aclist = isset($_POST['aclist']) ? sanitize_textarea_field($_POST['aclist']) : null ;
    $accodeon = isset($_POST['accodeon']) ? sanitize_textarea_field($_POST['accodeon']) : null ;
    update_option( 'gritonl_cwac_acurl', $acurl );
    update_option( 'gritonl_cwac_acapikey', $acapikey );
    
    
    // Get AC lists and check the connection
    if ( !empty($acurl) && !empty($acapikey) ){
      $result = gritonl_cwac_api('list_list');
      $aclists = array();      
      if ($result['result_code']){
        update_option( 'gritonl_cwac_acgood', '1' );
        update_option( 'gritonl_cwac_aclist', $aclist );
        if ($accodeon){
          $ac_tracking = gritonl_cwac_api('track_site_code');
          update_option( 'gritonl_cwac_accode', $ac_tracking['code'] );
        }
        update_option( 'gritonl_cwac_accodeon', $accodeon );
        foreach($result as $k => $v){
          if (is_numeric($k)){
            $aclists[sanitize_text_field($v['id'])] = sanitize_text_field($v['name']); 
          }          
        }
        update_option( 'gritonl_cwac_aclists', $aclists );
        $actags = isset($_POST['actags']) ? explode (',',($_POST['actags'])) : array();
        foreach ($actags as $k => $v){
          $actags[$k] = sanitize_text_field( $v );
        }
        $actags = array_filter($actags); # Remove potential null values
        update_option( 'gritonl_cwac_actags', $actags ); #insert tags into options
      } else {
          update_option( 'gritonl_cwac_acgood', '' );
          update_option( 'gritonl_cwac_accodeon', '' );
          update_option( 'gritonl_cwac_aclist', '' );
        }
    } else {
        update_option( 'gritonl_cwac_acgood', '' );
        update_option( 'gritonl_cwac_accodeon', '' );
        update_option( 'gritonl_cwac_aclist', '' );
    }
  }  
  ?>

  <div id="integrations" class="tabcontent" style="display:<?php echo $tab == 'integrations' ? 'block' : 'none'; ?>">
    <table class="form-table">
      <tr>
        <th scope="row">Shortcode</th>
        <td><input type="text" size="20" name="shortcode" value="[gritonl_cwac]"><p class="description" id="shortcode-description">Place this shortcode on the 'thank you' page</p></td>     
      </tr>
    </table>
    <h3><?php if (!get_option( 'gritonl_cwac_acgood' ) ){ echo '<i style="color:red">&#10008;</i>'; } else echo '<i style="color:green">&#10004;</i>'; ?> ActiveCampaign</h3>
    <form action="<?php menu_page_url( "gritonl_cwac", true ); ?>" method="post">
      <table class="form-table">
        <tr>
          <th scope="row">API URL</th>
          <td><input type="text" size="86" name="acurl" placeholder="https://name.api-us1.com" value="<?php echo get_option( 'gritonl_cwac_acurl' ); ?>"><p class="description" id="acurl-description">ActiveCampaign -> Settings -> Developer</p></td>
        </tr>
        <tr>
          <th scope="row">API Key</th>
          <td><input type="text" size="86" name="acapikey" placeholder="1a23b4..." value="<?php echo get_option( 'gritonl_cwac_acapikey' ); ?>"><p class="description" id="acapikey-description">ActiveCampaign -> Settings -> Developer</p></td>
        </tr>
       <?php if (get_option( 'gritonl_cwac_acgood' ) == '1' ){ ?> 
        <tr>
          <th scope="row">Tags</th>
          <td><input type="text" size="86" name="actags" value="<?php
            $c=0;
            if (get_option( 'gritonl_cwac_actags' )){
              foreach ( get_option( 'gritonl_cwac_actags' ) as $k => $v ) {
                if ( $c ) { echo ", ".$v; }
                  else { echo $v; }
                $c++;
              }
            }
            ?>"><p class="description" id="actags-description">Comma separated list of tags</p>
          </td>
        </tr>
        <?php $aclists = get_option( 'gritonl_cwac_aclists' ); ?>
        <tr>
          <th scope="row">List</th>
          <td>
          <select name="aclist" id="aclist">
            <?php
              if (count($aclists)){
                $l = get_option( 'gritonl_cwac_aclist' );
                if (empty($l)){echo '<option value="-1">Select list</option>';}
                foreach ( $aclists as $k => $v){
                  if ($k == $l){$s="selected ";} else {$s="";}
                  echo '<option '.$s.'value="'.$k.'">'.$v.'</option>';
                }
              }
              else { echo '<option value="-1">Provide valid URL and API Key</option>'; }
            ?>
          </select>
          <p class="description" id="aclist-description">List must be selected and saved</p>
          </td>
        </tr>
        <tr>
          <th scope="row">Tracking Code</th>
          <td><input type="checkbox" name="accodeon" value="1" <?php if ( get_option( 'gritonl_cwac_accodeon' ) ) { echo "checked"; } ?>><p class="description" id="accodeon-description">Insert tracking code on all pages</p></td>          
        </tr>
       <?php } ?> 
      </table>
      <br />
      <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('integrations-saved'); ?>">
      <input type="hidden" name="tab" value="integrations">
      <input class="button button-primary" name="integrations-saved" type="submit" value="Save Changes">
    </form>
  </div>
  <?php
  echo '<br /><br />This WordPress Plugin is provided by <a href="https://www.grit.online/">GRIT Online Inc.</a>';
  echo '</div>';  
}