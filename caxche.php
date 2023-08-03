<?php

/*
 * Plugin Name:       Caxche
 * Plugin URI:        https://github.com/rajanstha/caxche
 * Description:       Lightest Full Page Caching Plugin for WordPress
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Rajan Shrestha
 * Author URI:        https://github.com/rajanstha/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       caxche
 */

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('CAXCHE_VERSION', '1.0.0');
define('CAXCHE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CAXCHE_PLUGIN_PATH', plugin_dir_path(__FILE__));

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * The core plugin class defined for the plugin
 */
require plugin_dir_path(__FILE__) . '/includes/class-caxche.php';
require plugin_dir_path(__FILE__) . '/includes/class-caxche-logic.php';

/**
 * The code that runs during plugin activation.
 * This action is documented in WPCaxche.php
 */
function activate_caxche()
{
    Caxche::activate_caxche();
}

register_activation_hook(__FILE__, 'activate_caxche');

/**
 * The code that clears the caxche cache
 */
function caxche_clear_cache()
{
    CaxchedLogic::cleanup_cache_directory();
}

register_deactivation_hook(__FILE__, 'caxche_clear_cache');


/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_caxche()
{
    $plugin = new Caxche();
    $plugin->run();
}

run_caxche();
