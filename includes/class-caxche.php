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
            if (!(is_user_logged_in() || (defined('CAXCHE') && CAXCHE == 'none')) && CaxchedLogic::is_caxcheable()) {
                $cache_key = CaxchedLogic::get_cache_key();
                $cache_path = CaxchedLogic::get_cache_path($cache_key);

                // If the cache file exists, serve and exit the process
                if (file_exists($cache_path)) {
                    try {
                        $cache_contents = file_get_contents($cache_path);
                        echo $cache_contents . "\n" . '<!-- CAXCHED: ' . $cache_key . ' -->';
                        exit;
                    } catch (\Exception $e) {
                        error_log($e->getMessage());
                    }
                }
            }
        });

        // Initalize the caching functionality to store cache 
        add_action('init', [CaxchedLogic::class, 'init']);
    }
}
