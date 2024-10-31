<?php
/*
Plugin Name: qrLogin
Plugin URI:
Description: qrLogin - fast, easy, free, open, secure login system! For login run qrLogin on smartphone and scan the QR code.
Version: 1.3.1
Author: qrLogin team
Author URI: http://qrlogin.info
*/

/*  Copyright 2017  qrLogin  (email: qrlogin.info[@]gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

// вызывается при активации плагина
register_activation_hook( __FILE__, 'qrl_activation' );
// register_deactivation_hook( __FILE__, 'qrl_deactivation' );
// register_uninstall_hook( __FILE__, 'qrl_uninstall' );
// add translates
add_action( 'plugins_loaded', 'qrl_plugins_loaded');
// Plugin Settings Link
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'qrl_plugin_action_links' );

//добавляем qr-код на экран логина
add_action( 'login_enqueue_scripts', 'qrl_login_enqueue_scripts' );
add_action( 'login_footer', 'qrl_login_footer' );
//добавляем код регистрации на страницу настройки пользователя
add_action( 'show_user_profile', 'qrl_show_user_profile' );
//добавляем страницу настроек в админ панель
add_action( 'admin_enqueue_scripts', 'qrl_admin_enqueue_scripts' );
add_action( 'admin_menu', 'qrl_admin_menu' );
//добавляем обработчик запросов от телефона и от ajax 
add_action( 'parse_request', 'qrl_parse_request' );

function qrl_activation() {
    add_option( 'qrlogin_del_http', '1' );
    add_option( 'qrlogin_timeout', '1' );
    add_option( 'qrlogin_poll_lifetime', '30' );
    add_option( 'qrlogin_post_timeout', '10' );
    add_option( 'qrlogin_login_timeout', '5' );
    add_option( 'qrlogin_qrcode_size', '128' );
    add_option( 'qrlogin_qrcode_fore_color', '#000064' );
    add_option( 'qrlogin_qrcode_back_color', '#FFFFFF' );

    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . 'qrlogin';
    $sql = "CREATE TABLE $table_name (
        sid varchar(32) NOT NULL default '',
		uid int unsigned NOT NULL default 0,
		result smallint unsigned NOT NULL default 0,
        PRIMARY KEY  (sid)
    ) $charset_collate;";
    dbDelta( $sql );
}

function qrl_plugins_loaded() {
    //добавляем домены перевода.
    load_plugin_textdomain( 'qrlogin', false, basename( dirname( __FILE__ ) ) . '/languages');
}

// Add link to plugins.php page for the plugin settings.
function qrl_plugin_action_links( $links ) {
    return array_merge( array(
        '<a href="' . admin_url( 'options-general.php?page=qrLogin' ) . '">' . __('Settings', 'qrlogin') . '</a>'
    ), $links );
}

function qrl_site_url() {
    if (get_option('qrlogin_del_http') == '1') {
        return str_replace(array('http://', 'https://', 'www.'), array('', '', ''), get_site_url());
    } else {
        return get_site_url();
    }
}

function qrl_session_id() {
    session_start();
    $id = wp_create_nonce('qrlogin') . '=' . session_id();
    session_abort();
    return $id;
}

function qrl_login_enqueue_scripts() {
    // only for login !!!
    if ( isset( $_GET['action'] ) ) return;

    wp_enqueue_script( 'qrcode', plugins_url( 'qrcode.js', __FILE__ ), array(), '1.0' );
    wp_enqueue_script( 'jquery' );
    wp_add_inline_script( 'jquery-core', '
function qrlogin_wplogin_move_div() {
    // As there is no action hook in the right place, we have to move the div to the right place.
	var qrl_div        = document.getElementById("qrlogin_wplogin_div");
	if (qrl_div == null) return;
	var qrl_parent_div = document.getElementById("loginform");
	if (qrl_div.parentNode != qrl_parent_div ) {
		qrl_div.parentNode.removeChild(qrl_div);
		qrl_parent_div.insertBefore(qrl_div, qrl_parent_div.firstChild);
	}
}
function qrlogin_set_dots() {
    var d = document.getElementById("qrl_login_status");
    d.innerHTML = (d.innerHTML.length > 10) ? "." : d.innerHTML + ".";
}
function qrlogin_if_logged() { 
    if(document.getElementById("qrlogin_qrcode").style["display"] == "none")
        return;
    qrlogin_set_dots();
    jQuery.ajax({
        url: "./qrl_ajax",
        success: function(data) {
            if(data) window.location = "' . (isset($_GET['redirect_to']) ? $_GET['redirect_to'] : (admin_url() . 'profile.php')) . '";
            else setTimeout(qrlogin_if_logged, ' . get_option( 'qrlogin_timeout' ) * 1000 . '); 
        },
        error:  function(jqXHR, textStatus, errorThrown) {
            document.getElementById("qrlogin_login_error").innerHTML = errorThrown;
            qrlogin_stop_scan();
        },
    });
}
function qrlogin_stop_scan() {
    document.getElementById("qrlogin_qrcode").style["display"] = "none";
  	document.getElementById("qrlogin_login_error").style["display"] = "block";
}
jQuery(document).ready(function() {
    qrlogin_wplogin_move_div();

    var qrl_text = "QRLOGIN\nL:V1\n' . qrl_site_url() . '\n' . qrl_session_id() . '";
    var qrl_qrcode = document.getElementById("qrl_qrcode");
    var qrcode = new QRCode( qrl_qrcode, {
        text: qrl_text,
        width: ' . get_option( 'qrlogin_qrcode_size' ) . ',
        height: ' . get_option( 'qrlogin_qrcode_size' ) . ',
        colorDark : "' . get_option( 'qrlogin_qrcode_fore_color' ) . '",
        colorLight : "' . get_option( 'qrlogin_qrcode_back_color' ) . '",
        correctLevel : QRCode.CorrectLevel.M
    });
    qrl_qrcode.title = "' .  __('Scan in', 'qrlogin') . ' qrLogin";
    if( /Android|webOS|iPhone|iPad|iPod|Opera Mini/i.test(navigator.userAgent) )
        qrl_qrcode.href = "qrlogin://" + qrl_text.replace(/\n/g, "%0A");

  	setTimeout(qrlogin_if_logged, ' . get_option( 'qrlogin_timeout' ) * 1000 . ');
	' . ( get_option( 'qrlogin_login_timeout' ) ? ('setTimeout(function(){ qrlogin_stop_scan(); }, ' . get_option( 'qrlogin_login_timeout' ) * 60000 . ');') : '') . '
});
');
}

function qrl_login_footer() {
    // only for login !!!
    if ( isset( $_GET['action'] ) ) return;
?>
<div id="qrlogin_wplogin_div" style="display: table; text-align:left; padding: 0 0 2em 0">
    <div id="qrlogin_qrcode">
	    <a id="qrl_qrcode" href="http://qrlogin.info" target="_blank" style="float: right; padding: 0 0 0 1em"></a>
	    <?php _e( 'Use the <a href="http://qrlogin.info" target="_blank">qrLogin</a> app to scan the QR Code, or just tap the code on your phone.<br/>Wait', 'qrlogin' ) ?>
	    <span id='qrl_login_status'>.</span>
    </div>
    <span id='qrlogin_login_error' style='display: none'><?php _e( 'The timeout for logging from <a href="http://qrlogin.info" target="_blank">qrLogin</a> app has expired.<br/>Refresh page to start waiting again.', 'qrlogin' ) ?></span>
</div>
<?php
}
       
// выводим код на странице настроек пользователя
function qrl_show_user_profile( $user ) {
?>
<hr />
<h1>qrLogin <?php _e( 'Settings', 'qrlogin' ); ?></h1>
<table class="form-table">
    <tbody>
        <tr>
            <th><?php _e( 'Registration QR-code', 'qrlogin' ); ?></th>
            <td>
                <a id="qrl_qrcode" href="http://qrlogin.info" target="_blank" style="float:left; padding: 0 1ex 0 0"></a>
                <p class="description"><?php _e( 'For save account use the <a href="http://qrlogin.info" target="_blank">qrLogin</a> app to scan the QR Code, or just tap the code on your phone.', 'qrlogin' ); ?></p>
                <p class="description" id="qrlogin_description"><?php _e( 'Since we do not know your password, after scanning, you will have to enter the password on the phone.<br/>Or you can enter the current password in the box below and Refresh the QRCODE.', 'qrlogin' ); ?></p>
                <p class="description" id="qrlogin_description_pass" style="display:none"><?php _e( 'The password you entered is included in qrcode. If you make a mistake, the phone app will remember the wrong password and an error will occur when you log in.', 'qrlogin' ); ?></p>
            </td>
        </tr>
        <tr>
            <th><label for="cur_password"><?php _e( 'Current Password', 'qrlogin' ); ?></label><br /></th>
    		<td>
    		    <input type="password" id="cur_password" maxlength="255" value="<?php echo esc_attr($cur_password) ?>" class="regular-text" title="<?php _e( 'Current Password', 'qrlogin' ); ?>" autocomplete="off" />
    		    <button type="button" class="button" onclick="qrlogin_get_qrcode()"><?php _e( 'Refresh QRCODE', 'qrlogin' ); ?></button>
    		    <br />
    		    <p class="description"><?php _e( 'If you do not want to enter the password on the phone, you must specify the current password.', 'qrlogin' ); ?></p>
    		</td>
        </tr>
        <tr>
            <th><?php _e( 'About', 'qrlogin' ); ?> qrLogin</th>
            <td>
                <a style="float:left; margin: 0 1ex 0 0;" href="http://qrlogin.info" target="_blank">
                    <img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/logo.svg'; ?>" width="60" title="Go to site qrlogin.info">
                </a>
                <p class="description"><?php _e( 'qrLogin is an authentication system based on the reading of the qr code by the mobile phone and the transfer of authentication data via the http / https protocol to the application or to a web resource.', 'qrlogin' ); ?></p>
                <br />
                <a style="float:right" href="https://play.google.com/store/apps/details?id=com.qrlogin" target="_blank">
                    <img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/google-button.svg'; ?>" width="120" title="Get it on Google Play">
                </a>
                <a style="float:right" href="https://itunes.apple.com/ua/app/qrlogin/id1240222885" target="_blank">
                    <img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/apple-button.svg'; ?>" width="120" title="Download on the AppStore">
                </a>
            </td>
        </tr>
    </tbody>
</table>
<hr />

<?php
}

function qrl_admin_enqueue_scripts() {
    // only for qrlogin settins page
    if ( $_GET['page'] == 'qrLogin' ) {
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_add_inline_script( 'wp-color-picker', "jQuery('.iris_color').wpColorPicker({ defaultColor: false, change: function(event, ui){}, clear: function(){}, hide: true, palettes: false });" );
    };
    
    // only for user profile
    $break = Explode('/', $_SERVER["SCRIPT_NAME"]);
    if($break[count($break) - 1] != 'profile.php') return;
    
    wp_enqueue_script( 'qrcode', plugins_url( 'qrcode.js', __FILE__ ), array(), '1.0' );
    wp_add_inline_script( 'qrcode', '
var qrcode;
var qrl_qrcode;
function qrlogin_get_qrcode(){
    var cur_pass = document.getElementById("cur_password").value;
    var qrl_text = "QRLOGIN\nNU:V1\n' . qrl_site_url() . '\n/qrlogin\n' . wp_get_current_user()->user_login . '\n" + cur_pass + "\n2";
    qrcode.makeCode(qrl_text);
    if(/Android|webOS|iPhone|iPad|iPod|Opera Mini/i.test(navigator.userAgent))
        qrl_qrcode.href = "qrlogin://" + qrl_text.replace(/\n/g, "%0A");
    qrl_qrcode.title = "' .  __('Scan in', 'qrlogin') . ' qrLogin";
    document.getElementById("qrlogin_description").style["display"] = (cur_pass === "") ? "block" : "none";
    document.getElementById("qrlogin_description_pass").style["display"] = (cur_pass === "") ? "none" : "block";
}
jQuery(document).ready(function() {
    qrl_qrcode = document.getElementById("qrl_qrcode");
    qrcode = new QRCode( qrl_qrcode, {
        width: ' . get_option( 'qrlogin_qrcode_size' ) . ',
        height: ' . get_option( 'qrlogin_qrcode_size' ) . ',
        colorDark : "' . get_option( 'qrlogin_qrcode_fore_color' ) . '",
        colorLight : "' . get_option( 'qrlogin_qrcode_back_color' ) . '",
        correctLevel : QRCode.CorrectLevel.M
    });
    qrlogin_get_qrcode();
});
');
}

function qrl_sanitize_option( $option ) {
    $value = $_POST[$option];
    switch ( $option ) {
        case 'qrlogin_del_http':
            if (!in_array($value, array( 0, 1 ))) {
                return;
            }
            break;
        case 'qrlogin_timeout':
            $value = intval($value);
            if (($value < 0) || ($value > 5)) {
                return; 
            }
            break;
        case 'qrlogin_poll_lifetime':
            $value = intval($value);
            if (($value < 0) || ($value > 300)) {
                return; 
            }
            break;
        case 'qrlogin_post_timeout':
            $value = intval($value);
            if (($value < 5) || ($value > 60)) {
                return; 
            }
            break;
        case 'qrlogin_login_timeout':
            $value = intval($value);
            if (($value < 0) || ($value > 30)) {
                return; 
            }
            break;
        case 'qrlogin_qrcode_size':
            $value = intval($value);
            if (($value < 64) || ($value > 256)) {
                return; 
            }
            break;
        case 'qrlogin_qrcode_fore_color':
        case 'qrlogin_qrcode_back_color':
            $value = hexdec($value);
            if($value > 0xffffff) {
                return; 
            }
            $value = "#".substr("000000".dechex($value),-6);
            break;
    }
    update_option( $option, $value );
}

function qrl_options_page() {
    if (isset($_POST['submit'])) {
        check_admin_referer( 'qrl_options_page' );

        qrl_sanitize_option( 'qrlogin_del_http' );
        qrl_sanitize_option( 'qrlogin_timeout' );
        qrl_sanitize_option( 'qrlogin_poll_lifetime' );
        qrl_sanitize_option( 'qrlogin_post_timeout' );
        qrl_sanitize_option( 'qrlogin_login_timeout' );
        qrl_sanitize_option( 'qrlogin_qrcode_size' );
        qrl_sanitize_option( 'qrlogin_qrcode_fore_color' );
        qrl_sanitize_option( 'qrlogin_qrcode_back_color' );
    }
?>
<div class="wrap">
    <form method="post" action="./options-general.php?page=qrLogin&amp;updated=true">
        <h1 class="wp-heading-inline">
            <a style="float:left; margin: 0 1ex 0 0;" href="http://qrlogin.info" target="_blank">
                <img src="<?php echo plugin_dir_url( __FILE__ ) . 'images/logo.svg'; ?>" width="60" title="Go to site qrlogin.info">
            </a>
            qrLogin <?php _e( 'Settings', 'qrlogin' ); ?>
        </h1>
    <table class="form-table">
    <fieldset>
        <tr>
            <th><label for="qrlogin_del_http"><?php _e( 'Identifier for', 'qrlogin' ); ?> qrLogin</label></th>
            <td>
                <label for="qrlogin_del_http">
                <input type="checkbox" name="qrlogin_del_http" value="1" <?php checked('1', get_option( 'qrlogin_del_http' )); ?> />
                <?php _e( 'Delete http, https & www from identifier', 'qrlogin' ); ?></label>
                <p class="description"><?php _e( 'We recommend using this option. Then, as the identifier for qrLogin, only the domain and path to your forum are used', 'qrlogin' ); ?></p>
            </td>
        </tr>
        <tr>
			<th><label for="qrlogin_qrcode_size"><?php _e( 'Size of qrcode', 'qrlogin' ); ?></label><br /></th>
			<td><input name="qrlogin_qrcode_size" type="number" id="qrlogin_qrcode_size" value="<?php echo get_option( 'qrlogin_qrcode_size' ); ?>" min="64" max="256" /> px
			<p class="description"><?php _e( 'Size of QRCODE in pixel (recommended 128)', 'qrlogin' ); ?></p>
			</td>
		</tr>
        <tr>
		    <th><label for="qrlogin_qrcode_fore_color"><?php _e( 'Foreground color', 'qrlogin' ); ?></label> / 
		    <label for="qrlogin_qrcode_back_color"><?php _e( 'Background color', 'qrlogin' ); ?></label></th>
		    <td>
		        <input class="iris_color" name="qrlogin_qrcode_fore_color" type="text" id="qrlogin_qrcode_fore_color" value="<?php echo get_option( 'qrlogin_qrcode_fore_color' ); ?>"/>  
		        <input class="iris_color" name="qrlogin_qrcode_back_color" type="text" id="qrlogin_qrcode_back_color" value="<?php echo get_option( 'qrlogin_qrcode_back_color' ); ?>"/>
                <p class="description"><?php _e( 'When specifying colors for the code, choose contrast combinations. Preferably dark on a light background.', 'qrlogin' ); ?></p>
            </td>
		</tr>
        <tr>
			<th><label for="qrlogin_login_timeout"><?php _e( 'Timeout for wait logging, in minuts', 'qrlogin' ); ?></label></th>
			<td><input name="qrlogin_login_timeout" type="number" id="qrlogin_login_timeout" value="<?php echo get_option( 'qrlogin_login_timeout' ); ?>" min="0" max="30" /> m
			<p class="description"><?php _e( 'Timeout for logging from qrLogin. The value is set in minuts. Set "0" to not stop waiting.', 'qrlogin' ); ?></p>
			</td>
		</tr>
        <tr>
			<th><label for="qrlogin_timeout"><?php _e( 'Server polling timeout, in seconds', 'qrlogin' ); ?></label></th>
			<td><input name="qrlogin_timeout" type="number" id="qrlogin_timeout" value="<?php echo get_option( 'qrlogin_timeout' ); ?>" min="0" max="5" /> s
			<p class="description"><?php _e( 'Timeout between requests to the server. Set "0" to polling without delay.', 'qrlogin' ); ?></p>
			</td>
		</tr>
         <tr>
			<th><label for="qrlogin_poll_lifetime"><?php _e( 'Server polling duration, in  seconds', 'qrlogin' ); ?></label></th>
			<td><input name="qrlogin_poll_lifetime" type="number" id="qrlogin_poll_lifetime" value="<?php echo get_option( 'qrlogin_poll_lifetime' ); ?>" min="0" max="300" /> s
			<p class="description"><?php _e( 'Duration of polls on the server. Set "0" for short polling.', 'qrlogin' ); ?></p>
			</td>
		</tr>
       <tr>
			<th><label for="qrlogin_post_timeout"><?php _e( 'Timeout for wait answer to phone, in seconds', 'qrlogin' ); ?></label></th>
			<td><input name="qrlogin_post_timeout" type="number" id="qrlogin_post_timeout" value="<?php echo get_option( 'qrlogin_post_timeout' ); ?>" min="5" max="60" /> s
			<p class="description"><?php _e( 'Timeout for answer to phone. The value is set in seconds.', 'qrlogin' ); ?></p>
			</td>
		</tr>
	</fieldset>
    </table>
<?php
    wp_nonce_field( 'qrl_options_page' );
    submit_button();
?>
    </form>
</div>
<?php
}

//регистрируем страницу настроек в админ панели.
function qrl_admin_menu() {
    add_options_page( 'qrLogin', 'qrLogin', 8, 'qrLogin', 'qrl_options_page' );
}

function qrl_exec_ajax() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'qrlogin';
    $sid = sanitize_text_field( md5('qrlogin' . qrl_session_id()) );
    $poll_lifetime = get_option( 'qrlogin_poll_lifetime' );
	
    // waiting for login - max $poll_lifetime s
    while (!$uid = $wpdb->get_var( $wpdb->prepare("SELECT uid FROM " . $table_name . " WHERE sid = %s ", $sid ))) {
        if(--$poll_lifetime < 0) exit;
        sleep(1);
        if(connection_aborted()) exit;
    }

    // received uid for login! - get user data && set current user
    if( $user = get_user_by( 'id', $uid ) ) {
        wp_set_current_user( $user->ID, $user->user_login );
        wp_set_auth_cookie( $user->ID );
        do_action( 'wp_login', $user->user_login );
    }

    // write answer to post ok - 200 or error 403 - Forbidden
    $wpdb->query( $wpdb->prepare("UPDATE " . $table_name . " SET result = %d WHERE sid = %s", array(($user ? 200 : 403), $sid)));

    // answer to ajax with '1' if loged - for reload page
    die( $user ? '1' : '' );
}

function qrl_exec_post() {
    // set error in answer - default !!
    http_response_code(400);
    
	// get JSON from POST
	$postdata = json_decode(file_get_contents('php://input'), true);

	// if data not correct
	if (($postdata['objectName'] != 'qrLogin') || empty($postdata['sessionid']) || empty($postdata['login']) || empty($postdata['password']))
		exit;
	
	// verify WP nonce
	$nonce = preg_split( "/=/", urldecode($postdata['sessionid']))[0];
	if (!wp_verify_nonce( $nonce, 'qrlogin' ))
        exit;
	
	$user = wp_authenticate(urldecode($postdata['login']), urldecode($postdata['password']));

	if ( is_wp_error($user) ) {
	    // if error login - 403 Forbidden
        http_response_code(403);
	    exit;
	}

    global $wpdb;

	$table_name = $wpdb->prefix . 'qrlogin';
	$sid = md5('qrlogin' . urldecode($postdata['sessionid']));

	// remove queue from db
	$wpdb->query( $wpdb->prepare("DELETE FROM " . $table_name . " WHERE sid = %s ", $sid) );

	// insert queue into db
	if (!$wpdb->insert($table_name, array('sid' => $sid, 'uid' => $user->ID)))
	    exit;
	
    // waiting for answer - max qrlogin_post_timeout s
    $post_timeout = get_option( 'qrlogin_post_timeout' );
 	while ((!$ans = $wpdb->get_var( $wpdb->prepare("SELECT result FROM " . $table_name . " WHERE sid = %s ", $sid ))) && ($post_timeout-- > 0)) {
		sleep(1);
	}
	
	// remove queue from db
	$wpdb->query( $wpdb->prepare("DELETE FROM " . $table_name . " WHERE sid = %s ", $sid) );

	// if not exists answer ! 408 Request Timeout
    http_response_code($ans ? $ans : 408);
	exit;
}

function qrl_parse_request( &$wp ) {
    switch ( $wp->query_vars['pagename'] )
    {
        case 'qrl_ajax': qrl_exec_ajax();
        case 'qrlogin' : qrl_exec_post();
    }
}
