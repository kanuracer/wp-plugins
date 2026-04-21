<?php
/**
 * Plugin Name: Formulare
 * Plugin URI: https://github.com/kanuracer/wp-plugins/formulare
 * Description: Erstellt Formulare wie Kontakt- oder Widerrufsformulare mit Formular-Baukasten und E-Mail-Einstellungen.
 * Version: 1.1.0
 * Author: kanuracer
 * Author URI: https://kanuracer.eu
 * Text Domain: formulare
 */

if (! defined('ABSPATH')) {
    exit;
}

define('FORMULARE_VERSION', '1.1.0');
define('FORMULARE_PLUGIN_FILE', __FILE__);
define('FORMULARE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FORMULARE_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once FORMULARE_PLUGIN_DIR . 'includes/class-formulare-plugin.php';

register_activation_hook(__FILE__, array('Formulare_Plugin', 'activate'));

Formulare_Plugin::instance();
