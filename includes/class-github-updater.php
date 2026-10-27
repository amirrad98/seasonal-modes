<?php
/** Public GitHub release updates through WordPress core's Update URI hook. */
if (!defined('ABSPATH')) {
    exit;
}

final class Seasonal_Modes_GitHub_Updater {
    const REPOSITORY = 'amirrad98/seasonal-modes';
    const URL = 'https://github.com/amirrad98/seasonal-modes';
    const CACHE = 'seasonal_modes_github_release_v1';

    private $file;

    public function __construct($file) {
        $this->file = plugin_basename($file);
        add_filter('update_plugins_github.com', array($this, 'check'), 10, 4);
        add_filter('plugins_api', array($this, 'information'), 10, 3);
        add_filter('plugin_row_meta', array($this, 'links'), 10, 2);
        add_action('admin_post_seasonal_modes_check_updates', array($this, 'refresh'));
        add_action('admin_notices', array($this, 'notice'));
        add_action('network_admin_notices', array($this, 'notice'));
    }

    public function check($update, $plugin_data, $plugin_file, $locales = array()) {
        if ($plugin_file !== $this->file) {
            return $update;
        }
        $release = $this->release();
        if (!$release) {
            return $update;
        }
        // Core compares versions and puts equal/older versions in no_update.
        return array(
            'id' => self::URL,
            'slug' => 'seasonal-modes',
            'version' => $release['version'],
            'url' => self::URL,
            'package' => $release['download_url'],
            'requires' => $release['requires'],
            'requires_php' => $release['requires_php'],
        );
    }

    private function json($url) {
        $response = wp_safe_remote_get($url, array(
            'timeout' => 10,
            'redirection' => 3,
            'limit_response_size' => 262144,
            'headers' => array('Accept' => 'application/json'),
        ));
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        $data = json_decode(wp_remote_retrieve_body($response), true);
        return is_array($data) ? $data : false;
    }

    public function release() {
        $cached = get_site_transient(self::CACHE);
        if ($cached !== false) {
            return $cached ?: false;
        }
        // Negative caching avoids repeated requests during outages/rate limits.
        set_site_transient(self::CACHE, array(), 5 * MINUTE_IN_SECONDS);
        $release = $this->json('https://api.github.com/repos/' . self::REPOSITORY . '/releases/latest');
        if (!$release || !isset($release['draft'], $release['prerelease'], $release['tag_name'], $release['assets'])
            || $release['draft'] !== false || $release['prerelease'] !== false
            || !is_string($release['tag_name']) || !is_array($release['assets'])
            || !preg_match('/^v(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$/D', $release['tag_name'])) {
            return false;
        }
        $base = self::URL . '/releases/download/' . $release['tag_name'] . '/';
        $assets = array();
        foreach ($release['assets'] as $asset) {
            if (!is_array($asset) || !isset($asset['name'], $asset['browser_download_url'], $asset['state'], $asset['size'])
                || !is_string($asset['name']) || !in_array($asset['name'], array('seasonal-modes.zip', 'update.json'), true)
                || $asset['browser_download_url'] !== $base . $asset['name']
                || $asset['state'] !== 'uploaded' || !is_int($asset['size']) || $asset['size'] <= 0) {
                continue;
            }
            $assets[$asset['name']] = true;
        }
        if (empty($assets['seasonal-modes.zip']) || empty($assets['update.json'])) {
            return false;
        }
        $metadata = $this->json($base . 'update.json');
        if (!$metadata || !isset($metadata['version'], $metadata['download_url'], $metadata['requires'], $metadata['requires_php'])
            || $metadata['version'] !== substr($release['tag_name'], 1)
            || $metadata['download_url'] !== $base . 'seasonal-modes.zip') {
            return false;
        }
        foreach (array('requires', 'requires_php') as $field) {
            if (!is_string($metadata[$field]) || !preg_match('/^[0-9]+\.[0-9]+(?:\.[0-9]+)?$/D', $metadata[$field])) {
                return false;
            }
        }
        $metadata['notes'] = isset($release['body']) && is_string($release['body']) ? $release['body'] : '';
        set_site_transient(self::CACHE, $metadata, 6 * HOUR_IN_SECONDS);
        return $metadata;
    }

    public function information($result, $action, $args) {
        if ($action !== 'plugin_information' || !is_object($args) || ($args->slug ?? '') !== 'seasonal-modes') {
            return $result;
        }
        $release = $this->release();
        if (!$release) {
            return $result;
        }
        return (object) array(
            'name' => 'Seasonal Modes',
            'slug' => 'seasonal-modes',
            'version' => $release['version'],
            'homepage' => self::URL,
            'download_link' => $release['download_url'],
            'requires' => $release['requires'],
            'requires_php' => $release['requires_php'],
            'sections' => array(
                'description' => 'Theme-independent seasonal decorations with reusable event packs.',
                'changelog' => '<pre>' . esc_html($release['notes']) . '</pre>',
            ),
        );
    }

    public function links($links, $file) {
        if ($file === $this->file && current_user_can('update_plugins')) {
            $url = wp_nonce_url(admin_url('admin-post.php?action=seasonal_modes_check_updates'), 'seasonal_modes_check_updates');
            $links[] = '<a href="' . esc_url($url) . '">' . esc_html__('Check for updates', 'seasonal-modes') . '</a>';
        }
        return $links;
    }

    public function refresh() {
        if (!current_user_can('update_plugins')) {
            wp_die(esc_html__('You cannot update plugins.', 'seasonal-modes'), '', array('response' => 403));
        }
        check_admin_referer('seasonal_modes_check_updates');
        delete_site_transient(self::CACHE);
        delete_site_transient('update_plugins');
        wp_update_plugins();
        $status = $this->release() ? 'checked' : 'unavailable';
        wp_safe_redirect(add_query_arg('seasonal-modes-update', $status, self_admin_url('plugins.php')));
        exit;
    }

    public function notice() {
        // Read-only display; the action doing the check verifies capability and nonce.
        if (!current_user_can('update_plugins') || !isset($_GET['seasonal-modes-update']) || !is_string($_GET['seasonal-modes-update'])) {
            return;
        }
        if ($_GET['seasonal-modes-update'] === 'checked') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Seasonal Modes update check finished. Any available update is shown in the plugin list.', 'seasonal-modes') . '</p></div>';
        } elseif ($_GET['seasonal-modes-update'] === 'unavailable') {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('Seasonal Modes could not find a complete release. GitHub may be unavailable or a release may still be publishing. Try again later.', 'seasonal-modes') . '</p></div>';
        }
    }
}
