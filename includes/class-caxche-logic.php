<?php

/**
 * ----------------------------------------------------------------
 * Caxche HTML Minification
 * ----------------------------------------------------------------
 */

class CaxchedLogic
{
    /**
     * Cache prefix used for storing minified HTML output in transients.
     */
    const CAXCHED_PREFIX = 'caxched_data_';
    const CAXCHED_DIRECTORY = WP_CONTENT_DIR . '/caxched_dir/';

    /**
     * Registers the action hooks for minifying HTML output.
     */
    public static function init()
    {
        // Schedules the cache cleaner schedule
        self::schedule_weekly_cache_cleanup();

        // Check if the current request should be served from the cache or should be cached
        if (!self::is_caxcheable()) return;

        // Check if the REQUEST_URI starts with the upload direcyory
        if (str_starts_with($_SERVER['REQUEST_URI'], (str_replace(ABSPATH, '', (wp_upload_dir()['basedir'] ?? ''))))) return;

        // Reset cache when the posts are updated
        add_action('acf/save_post', [__CLASS__, 'cleanup_cache_directory'], 200);
        // Reset cache when the ACF option page is updated
        add_action('acf/options_page/save', [__CLASS__, 'cleanup_cache_directory'], 200);

        // Check and serve
        return self::serve_cache_if_available();
    }

    /**
     * Checks and serves the requested content from the cached directory
     */
    public static function serve_cache_if_available()
    {
        // Check if the current request should be served from the cache or should be cached
        if (!self::is_caxcheable()) return self::send_minified_html_to_client();

        // Checks and serve the pages with cached content
        $cache_key = self::get_cache_key();
        $cache_path = self::get_cache_path($cache_key);

        if (file_exists($cache_path)) {
            try {
                // If is post request due to form submission do not serve with cached content
                if ($_SERVER['REQUEST_METHOD'] === 'POST') return self::send_minified_html_to_client();

                // Send appropriate headers
                header('X-Cache: HIT (cached) ' . $cache_key);
                $cache_contents = file_get_contents($cache_path);
                echo $cache_contents . "\n" . '<!-- CAXCHED: ' . $cache_key . ' -->';
                exit;
            } catch (\Exception $e) {
                error_log('Error serving from cache: ' .  $e->getMessage());
            }
        }

        return self::send_minified_html_to_client();
    }

    /**
     * Check if the requested source is cacheable
     *
     * @return boolean
     */
    public static function is_caxcheable()
    {
        // If is not client request or any of the conditions are met, do nothing
        if (
            is_admin() ||
            is_user_logged_in() ||
            empty($_SERVER['HTTP_HOST']) ||
            defined('DOING_AJAX') && DOING_AJAX ||
            defined('DOING_CRON') && DOING_CRON ||
            defined('WP_CLI') && WP_CLI ||
            str_starts_with($_SERVER['REQUEST_URI'], str_replace(ABSPATH, '', wp_upload_dir()['basedir'] ?? ''))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Sends the minified html to the client while trying to set caching
     */
    private static function send_minified_html_to_client()
    {
        add_action('send_headers', [__CLASS__, 'start_minification'], -1);
        add_action('send_footers', [__CLASS__, 'end_minification'], 9999);

        // Enqueue the minified caxce to the browser for preloading pages
        add_action('wp_footer', function () {
            $caxche_js = file_get_contents(CAXCHE_PLUGIN_PATH . 'build/caxche.js');
            // Add the caxche JS inline
            echo <<<HTML
                <script id="caxched-js">{$caxche_js}</script>
            HTML;
        });
    }

    /**
     * Starts the minification process by buffering the HTML output.
     */
    public static function start_minification()
    {
        return ob_start([__CLASS__, 'get_minified_html_output_with_caching']);
    }

    /**
     * Retrieves the minified HTML output from cache or generates it if not cached.
     *
     * @param string $html The HTML string to be minified.
     * @return string The minified HTML string.
     */
    public static function get_minified_html_output_with_caching($html)
    {
        $cache_key = self::get_cache_key();
        $cache_path = self::get_cache_path($cache_key);

        // Minify the HTML
        $minified_html = self::minify_html_output($html);

        // Set custom header indicating the page is served from cache
        header('X-Cache: HIT (REQUESTED)');

        // Create a new cache entry in the cache directory with the html content
        file_put_contents($cache_path, $minified_html);

        // Ensure the <html> tag is closed in the minified HTML output
        if (str_contains($minified_html, '<html>') && !str_contains($minified_html, '</html>')) {
            $minified_html .= '</html>';
        }

        $minified_html .= "\n" . "<!-- SETTING_CAXCHED: {$cache_key} -->";

        return $minified_html;
    }

    /**
     * Generates the cache path for a given cache key.
     *
     * @param string $cache_key The cache key.
     * @return string The cache path.
     */
    public static function get_cache_path($cache_key)
    {
        if (!is_dir(self::CAXCHED_DIRECTORY)) {
            try {
                mkdir(self::CAXCHED_DIRECTORY, 0755, true);
                // Hiding the directory structure, making it harder for potential attackers to gather information.
                $indexContent = "<?php // Silence is golden ?>";
                file_put_contents(self::CAXCHED_DIRECTORY . "index.php", $indexContent);
            } catch (\Exception $e) {
                error_log('CAXCHED DIRECTORY CANNOT BE CREATED: ' . $e->getMessage());
            }
        }

        return self::CAXCHED_DIRECTORY . $cache_key . '.html';
    }

    /**
     * Get the key for cache data.
     *
     * @return string The cache key.
     */
    public static function get_cache_key()
    {
        $url = $_SERVER['REQUEST_URI'] . (!empty($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '');
        return self::CAXCHED_PREFIX . md5($url) . '_' . self::get_last_updated_post_date() . '_' . filectime(get_template_directory());
    }

    /**
     * Minifies the HTML output by removing unnecessary white spaces, line breaks, and carriage returns.
     *
     * @param string $html The HTML string to be minified.
     * @return string The minified HTML string.
     */
    private static function minify_html_output($html)
    {
        // Normalize the HTML to Unicode Normalization Form C
        if (extension_loaded('mbstring') && class_exists('Normalizer')) {
            $html = \Normalizer::normalize($html, \Normalizer::FORM_C);
        } else {
            error_log('Normalizer extension not available. Skipping normalization to Form C.');
        }

        // Replace CSS links with inline styles (corrected regex)
        $html = self::html_inline_styles_and_manipulate($html);

        // Remove HTML comments
        $html = preg_replace('/<!--(?!<!)[^\[>].*?-->/', '', $html);

        // Remove spaces between tags
        $html = preg_replace('/\s+/', ' ', $html);

        // Remove spaces before and after tags
        $html = preg_replace('/\s*(<[^>]+>)\s*/', '$1', $html);

        // Remove spaces between attributes
        $html = preg_replace('/\s*=\s*/', '=', $html);

        // Remove `/>` from self-closing tags
        $html = preg_replace('/\s+\/>/', '>', $html);

        return $html;
    }

    /**
     * Helper method to fetch and replace CSS links with their contents
     *
     * @param array $matches An array containing the matched groups from the regex.
     * @return string The inline CSS content wrapped in <style> tags, or the original link tag if fetching fails.
     */
    public static function html_inline_styles_and_manipulate($html)
    {
        if (empty($html)) return $html;
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);

        // DOM Elements to be removed
        $dom_elements_to_be_removed = [];

        // Set some options to format the output
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        // Theme directory
        $site_url = get_site_url();
        $theme_directory = get_template_directory_uri();

        // Inline css styles
        $link_elements = $dom->getElementsByTagName('link');
        $link_elements_with_preload = [];

        // Inline each internal stylesheets from plugins and theme directories
        if (!empty($link_elements)) {
            foreach ($link_elements as $link_index => $link) {
                // If is stylesheet check and try to inline the css
                if ($link->getAttribute('rel') == 'stylesheet') {
                    $url = $link->getAttribute('href');
                    $resource_location = $url;

                    if (str_starts_with($url, $theme_directory) || str_starts_with($url, '/wp-') || str_starts_with($url, $site_url)) {
                        // if (str_starts_with($url, $theme_directory) || str_starts_with($url, $site_url)) {
                        //     $parsed_resource_location = parse_url($url);
                        //     $resource_location = ABSPATH . ltrim($parsed_resource_location['path'] ?? false);
                        // } else {
                        $parse_resource_location = parse_url($url);
                        $resource_location = str_replace('//', '/', ABSPATH . ltrim($parse_resource_location['path']));
                        // }
                        // error_log(print_r($resource_location, true));

                        // URL Parts
                        $url_path = explode('/', $url);
                        // Remove last path segment
                        array_pop($url_path);
                        // Rejoin path
                        $url_path = implode('/', $url_path);

                        // Check the css resource location
                        if (!empty($resource_location) && file_exists($resource_location)) {
                            try {
                                $customized_inlined_style = file_get_contents($resource_location);

                                // CSS URL rewriting
                                if ($customized_inlined_style) {
                                    $customized_inlined_style = preg_replace_callback('/url\((.*?)\)/i', function ($match) use ($url_path) {
                                        $url = $match[1];

                                        if (
                                            preg_match('/^https?|\'https?|\"https?/', $url)
                                            || preg_match('/^data:?|\'data:?|\"data:?/', $url)
                                        ) {
                                            return "url({$url})";
                                        }

                                        // Get the css directory path
                                        return "url('{$url_path}/{$url}')";
                                    }, $customized_inlined_style);

                                    $inline_style = $dom->createElement('style');
                                    $inline_style->setAttribute('data-caxched-inlined', ($link->getAttribute('id') ?? 'true'));
                                    $inline_style->appendChild($dom->createTextNode($customized_inlined_style));
                                    $link->parentNode->appendChild($inline_style);
                                    $dom_elements_to_be_removed[] = $link;
                                }
                            } catch (\Exception $e) {
                                error_log($e->getMessage());
                            }
                        }
                    }
                }
            }
        }

        // Remove elements collected to be removed from the DOM
        if (!empty($dom_elements_to_be_removed)) {
            foreach ($dom_elements_to_be_removed as $dom_item) {
                try {
                    $dom_item_url = $dom_item->hasAttribute('href') ? $dom_item->getAttribute('href') : false;

                    if ($dom_item_url) {
                        foreach ($link_elements as $link_tag) {
                            if (($link_tag->hasAttribute('rel') && $link_tag->getAttribute('rel') == 'preload')
                                && ($link_tag->hasAttribute('href') && ($link_tag->getAttribute('href') == $dom_item_url))
                            ) {
                                $dom_item->parentNode->removeChild($link_tag);
                            }
                        }
                    }

                    // Remove the dom item
                    $dom_item->parentNode->removeChild($dom_item);
                } catch (\Exception $e) {
                    error_log($e->getMessage());
                }
            }
        }

        // Remove 'type="text/javascript"' attribute from all script tags
        $script_tags = $dom->getElementsByTagName('script');
        if (!empty($script_tags)) {
            foreach ($script_tags as $script_tag) {
                if ($script_tag->hasAttribute('type') && $script_tag->getAttribute('type') === 'text/javascript') {
                    $script_tag->removeAttribute('type');
                }
            }
        }

        // Remove 'type="text/css" attribute from all styles
        $style_tags = $dom->getElementsByTagName('script');
        if (!empty($style_tags)) {
            foreach ($style_tags as $style_tag) {
                if ($style_tag->hasAttribute('type') && $style_tag->getAttribute('type') === 'text/css') {
                    $style_tag->removeAttribute('type');
                }
            }
        }

        // Return the libxml_use_internal_errors to the default value
        libxml_use_internal_errors(false);

        $html = $dom->saveHTML();
        return $html;
    }

    /**
     * Gets the last updated date for all post types.
     * @return string The formatted last updated date.
     */
    private static function get_last_updated_post_date()
    {
        try {
            global $wpdb;

            $query = "
            SELECT post_modified
            FROM {$wpdb->posts}
            ORDER BY post_modified DESC
            LIMIT 1
        ";

            $mostRecentPostModified = $wpdb->get_var($query);
        } catch (\Exception $e) {
            error_log('Error getting the most recent post: ' . $e->getMessage());
            $mostRecentPostModified = strtotime("now");
        }
        return strtotime($mostRecentPostModified);
    }

    /**
     * Cleanup the cache directory
     */
    public static function cleanup_cache_directory()
    {
        try {
            $files = glob(self::CAXCHED_DIRECTORY . '/*');
            if ($files === false) {
                return;
            }

            // Check and clear each file
            foreach ($files as $file) {
                if (is_file($file) && basename($file) !== 'index.php' && strpos(basename($file), self::CAXCHED_PREFIX) === 0) {
                    unlink($file);
                }
            }
            error_log('CAXCHED CLEANUP SUCCESSFUL');
        } catch (\Exception $e) {
            // Handle any exceptions that occur during cache cleanup
            error_log('CAXCHED DIRECTORY CLEANUP FAILED: ' . $e->getMessage());
        }
    }

    /**
     * Ends the minification process by flushing the minified HTML output to the browser.
     */
    private static function end_minification()
    {
        ob_end_flush();
    }

    /**
     * Schedules a cron event to cleanup the cache every 7 days
     */
    public static function schedule_weekly_cache_cleanup()
    {
        if (!wp_next_scheduled('weekly_cache_cleanup_event')) {
            wp_schedule_event(time(), 'weekly', 'weekly_cache_cleanup_event');
        }
        add_action('weekly_cache_cleanup_event', [__CLASS__, 'cleanup_cache_directory']);
    }
}
