<?php
/**
 * Plugin Name:       Mettano Form Protection
 * Description:       Silent antispam for Elementor Pro forms (blocked keywords, websites and emails) plus US phone number validation. Settings → Form Protection.
 * Version:           1.0.0
 * Author:            Mettano
 * Author URI:        https://mettano.com
 * Requires at least: 5.7
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       mettano-form-protection
 */

defined( 'ABSPATH' ) || exit;

define( 'MFP_VERSION', '1.0.0' );
define( 'MFP_FILE', __FILE__ );
define( 'MFP_DIR', plugin_dir_path( __FILE__ ) );

require_once MFP_DIR . 'includes/class-mfp-settings.php';
require_once MFP_DIR . 'includes/class-mfp-logger.php';
require_once MFP_DIR . 'includes/class-mfp-filter.php';

register_activation_hook( __FILE__, array( 'MFP_Settings', 'activate' ) );

MFP_Filter::init();

if ( is_admin() ) {
	require_once MFP_DIR . 'includes/class-mfp-admin.php';
	MFP_Admin::init();
}
