<?php
/**
 * Plugin Name: KR-Forms
 * Plugin URI: https://github.com/kanuracer/wp-plugins/tree/main/KR-Forms
 * Description: KR-Forms erstellt Kontakt-, Anfrage- und Widerrufsformulare mit Formular-Baukasten und E-Mail-Einstellungen.
 * Version: 2.0.0
 * Author: kanuracer
 * Author URI: https://kanuracer.eu
 * Text Domain: kr-forms
 */

if (! defined('ABSPATH')) {
    exit;
}

define('KR_FORMS_VERSION', '2.0.0');
define('KR_FORMS_PLUGIN_FILE', __FILE__);
define('KR_FORMS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KR_FORMS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once KR_FORMS_PLUGIN_DIR . 'includes/class-kr-forms-plugin.php';

register_activation_hook(__FILE__, array('KR_Forms_Plugin', 'activate'));

KR_Forms_Plugin::instance();

