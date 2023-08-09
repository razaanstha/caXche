<?php

/**
 * ----------------------------------------------------------------
 * Caxche
 * ----------------------------------------------------------------
 */

class Caxche
{
    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Caxche_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Cache prefix used for storing minified HTML output in transients.
     */
    const CAXCHE_CACHE_PREFIX = 'caxche_output';
    const CAXCHE_CACHE_DIRECTORY = WP_CONTENT_DIR . '/caxche_dir/';

    public function __construct()
    {
        if (defined('CAXCHE_VERSION')) {
            $this->version = CAXCHE_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'caxche';
        $this->load_dependencies();
    }

    /**
     * ----------------------------------------------------------------
     * Activates the plugin
     * ----------------------------------------------------------------
     * This method is responsible for activating the plugin functionalities after the plugin is activated.
     * 
     * @since 1.0.0
     */
    public static function activate_caxche()
    {
        error_log('ACTIVATIN CAXCHE....');
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies()
    {
        /**
         * The class responsible for defining all actions that occur in the frontend-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-caxche-logic.php';
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run()
    {
        // Check if the cache file exists and serve and end the process
        add_action('plugins_loaded', function () {
            CaxchedLogic::serve_cache_if_available();
        });

        // Initalize the caching functionality to collect caching 
        add_action('init', [CaxchedLogic::class, 'init']);

        /**
         * Cache Cleanup when
         */
        // Every weekly by using WP CRON 
        CaxchedLogic::schedule_weekly_cache_cleanup();

        // When the post is updated
        add_action('post_updated', [__CLASS__, 'run_cache_cleanup']);

        // When the post is deleted
        add_action('delete_post', [__CLASS__, 'run_cache_cleanup']);

        // When term is updated
        add_action('edited_term', [__CLASS__, 'run_cache_cleanup']);

        // When term is deleted
        add_action('edited_term', [__CLASS__, 'run_cache_cleanup']);

        // When the WP options (WP Core Settings) page is updated
        add_action('admin_init', function () {
            if (
                isset($_POST['_wpnonce'], $_POST['action'], $_POST['_wp_http_referer']) &&
                $_POST['action'] === 'update' &&
                is_string($_POST['_wp_http_referer']) &&
                str_starts_with($_POST['_wp_http_referer'], '/wp-admin/options')
            ) {
                CaxchedLogic::cleanup_cache_directory();
            }
        });

        // When the WP nav menu is updated
        add_action('wp_update_nav_menu', [CaxchedLogic::class, 'cleanup_cache_directory']);

        // Cleanup the cache when the ACF fields are updated
        add_action('acf/save_post', [CaxchedLogic::class, 'cleanup_cache_directory'], 200);

        // Cleanup the cache when the ACF option page are updated
        add_action('acf/options_page/save', [CaxchedLogic::class, 'cleanup_cache_directory'], 200);
    }

    /**
     * Cleanup the cache
     */
    public static function run_cache_cleanup()
    {
        CaxchedLogic::cleanup_cache_directory();
    }
}
