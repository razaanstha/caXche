# caXche

Full Page Caching Plugin for WordPress 

## Description

This is a lightweight full page caching plugin for WordPress that aims to provide efficient and fast caching for your WordPress site. Please note that the "lightest" claim is highly subjective "😅" and may vary based on different setups and configurations.

## Features

- **Static Cache Creation**: The plugin generates static cache files for pages, which includes inlining and minifying CSS. Additionally, it automatically removes the preload links from the inlined CSS to optimize performance.

- **Automatic Cache Clearing**: Whenever a post or the theme is updated, the cache file associated with that content is automatically cleared, ensuring that your visitors always see the latest content.

- **Efficient Caching Process**: The plugin exits the WordPress further process after serving the cache, which helps in reducing server load and enhances the overall page load speed.

- **No Cache for Logged-in Users**: To provide personalized experiences for logged-in users, this plugin prevents caching for users who are logged in to your WordPress site.

- **Scheduled Cache Cleanup**: The plugin utilizes the WordPress cron functionality to clear the cache directory every 7 days. This ensures that the cache stays up-to-date and does not clutter your server space.

- **Custom Cache Cleanup Function**: For advanced users, the plugin provides a cache cleanup function that can be integrated into the theme code. This allows you to trigger cache clearing after specific events or as per your requirements.

- **Quicklink JS Library Integration**: The plugin comes with the Quicklink JS library, which preloads pages after the initial page load. This makes the navigation process smoother and faster for users, as it predicts which links they are likely to click next and preloads those pages in the background.

## Disclaimer

**Try at your own risk**: While the plugin has been designed with care to provide efficient caching and enhance site performance, using any caching plugin carries certain risks. Before installing or activating the plugin, we recommend taking a full backup of your WordPress site to safeguard against any potential data loss or compatibility issues.

## Installation

1. Download the plugin ZIP file.
2. Go to your WordPress admin panel.
3. Navigate to **Plugins** > **Add New**.
4. Click on **Upload Plugin**.
5. Choose the downloaded ZIP file and click **Install Now**.
6. Once installed, click **Activate** to enable the plugin.

## Cache Clearing

Currently the cache is cleared automatically when the following are updated:

- WordPress post is updated/deleted.
- WordPress term is updated/deleted.
- WordPress setting is updated.
- WordPress nav menu is updated.
- Post with Advanced Custom Fields (ACF) fields is updated.
- ACF options page is updated.

## Benchmarks
```
[08-Aug-2023 09:53:46 UTC] caXched: https://caxched.test/ -> shutdown after: 35.644811630249ms 
[08-Aug-2023 09:54:01 UTC] without caXched: https://caxched.test/ -> shutdown after: 130.8650970459ms 
```