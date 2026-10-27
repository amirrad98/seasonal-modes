<?php
/** Run with wp eval-file in a disposable WordPress installation only. */
if (!defined('WP_CLI') || !WP_CLI || getenv('SEASONAL_MODES_TEST_SITE') !== '1') {
    throw new RuntimeException('Only run in the disposable CI test site.');
}
function sm_assert($condition, $message) {
    if (!$condition) {
        WP_CLI::error($message);
    }
    WP_CLI::log('PASS: ' . $message);
}
$plugin = 'seasonal-modes/seasonal-modes.php';
$root = getenv('SEASONAL_MODES_SOURCE');
$manifest = json_decode(file_get_contents($root . '/dist/update.json'), true);
$version = $manifest['version'];
$base = Seasonal_Modes_GitHub_Updater::URL . '/releases/download/v' . $version . '/';
$release = array('draft' => false, 'prerelease' => false, 'tag_name' => 'v' . $version, 'body' => 'Integration fixture', 'assets' => array());
foreach (array('seasonal-modes.zip', 'update.json') as $name) {
    $release['assets'][] = array('name' => $name, 'browser_download_url' => $base . $name, 'state' => 'uploaded', 'size' => filesize($root . '/dist/' . $name));
}
add_filter('pre_http_request', function ($pre, $args, $url) use ($release, $manifest, $base) {
    if ($url === 'https://api.github.com/repos/amirrad98/seasonal-modes/releases/latest') {
        $body = $release;
    } elseif ($url === $base . 'update.json') {
        $body = $manifest;
    } elseif (strpos($url, 'api.wordpress.org/plugins/update-check/') !== false) {
        $body = array('plugins' => array(), 'no_update' => array(), 'translations' => array());
    } else {
        return $pre;
    }
    return array('headers' => array(), 'body' => wp_json_encode($body), 'response' => array('code' => 200, 'message' => 'OK'), 'cookies' => array());
}, 10, 3);
// The actual WordPress upgrader installs the exact locally built release ZIP.
add_filter('upgrader_pre_download', function ($reply, $package) use ($manifest, $root) {
    if ($package === $manifest['download_url']) {
        $copy = wp_tempnam('seasonal-modes.zip');
        copy($root . '/dist/seasonal-modes.zip', $copy);
        return $copy;
    }
    return $reply;
}, 10, 2);
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
sm_assert(Seasonal_Modes::VERSION === '1.0.0', 'test begins with older installed version');
$settings = array('mode' => 'on', 'pack' => 'custom-event');
$adapter = array('header' => '#my-header', 'logo' => '.my-logo', 'header_stripe' => '0');
update_option(Seasonal_Modes::OPTION, $settings);
update_option(Seasonal_Modes::ADAPTER_OPTION, $adapter);
$custom_dir = WP_CONTENT_DIR . '/seasonal-modes-packs/custom-event';
wp_mkdir_p($custom_dir);
$custom_json = wp_json_encode(array('name' => 'Custom event', 'version' => '1.0.0', 'window' => array('start' => '01-01', 'end' => '12-31')));
file_put_contents($custom_dir . '/pack.json', $custom_json);
delete_site_transient(Seasonal_Modes_GitHub_Updater::CACHE);
delete_site_transient('update_plugins');
wp_update_plugins();
$updates = get_site_transient('update_plugins');
sm_assert(isset($updates->response[$plugin]), 'WordPress core offers the newer GitHub release');
sm_assert($updates->response[$plugin]->new_version === $version, 'offered version matches release manifest');
sm_assert($updates->response[$plugin]->package === $manifest['download_url'], 'offer points to the installable release ZIP');
sm_assert(!isset($updates->response[$plugin]->autoupdate), 'auto-update remains the site administrator choice');
// Match wp_ajax_update_plugin(), the Plugins screen's Update now path.
$upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
$result = $upgrader->bulk_upgrade(array($plugin), array('clear_update_cache' => true));
sm_assert(is_array($result) && isset($result[$plugin]) && is_array($result[$plugin]), 'WordPress Plugin_Upgrader successfully installs the release ZIP');
$installed = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin, false, false);
sm_assert($installed['Version'] === $version, 'plugin files now contain the new version');
sm_assert(is_plugin_active($plugin), 'plugin activation is preserved');
sm_assert(get_option(Seasonal_Modes::OPTION) === $settings, 'seasonal settings survive replacement');
sm_assert(get_option(Seasonal_Modes::ADAPTER_OPTION) === $adapter, 'theme adapter survives replacement');
sm_assert(file_get_contents($custom_dir . '/pack.json') === $custom_json, 'external custom pack survives replacement');
wp_clean_plugins_cache();
delete_site_transient('update_plugins');
wp_update_plugins();
$updates = get_site_transient('update_plugins');
sm_assert(!isset($updates->response[$plugin]), 'equal version is not offered as another upgrade');
sm_assert(isset($updates->no_update[$plugin]), 'core records current version for normal auto-update UI');
WP_CLI::success('Real WordPress core install/update test passed with fixture HTTP responses and the built ZIP.');
