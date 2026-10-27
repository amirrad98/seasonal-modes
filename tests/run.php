<?php
/** Fast behavior tests. These WordPress substitutes are not integration proof. */
error_reporting(E_ALL);
set_error_handler(function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
define('ABSPATH', __DIR__ . '/');
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/seasonal-modes-tests-' . getmypid());
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
$GLOBALS['transients'] = $GLOBALS['expiries'] = $GLOBALS['responses'] = $GLOBALS['calls'] = $GLOBALS['options'] = $GLOBALS['hooks'] = array();
$GLOBALS['allowed'] = true;
$GLOBALS['nonce'] = true;
$GLOBALS['today'] = '2026-10-26';
class WP_Error {}
function add_filter($hook, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$hook][] = $callback; }
function add_action($hook, $callback, $priority = 10, $args = 1) { add_filter($hook, $callback, $priority, $args); }
function plugin_basename($file) { return 'seasonal-modes/' . basename($file); }
function get_site_transient($key) { return $GLOBALS['transients'][$key] ?? false; }
function set_site_transient($key, $value, $expiry) { $GLOBALS['transients'][$key] = $value; $GLOBALS['expiries'][$key] = $expiry; }
function delete_site_transient($key) { unset($GLOBALS['transients'][$key]); }
function wp_safe_remote_get($url, $args) { $GLOBALS['calls'][] = $url; return $GLOBALS['responses'][$url] ?? new WP_Error(); }
function is_wp_error($value) { return $value instanceof WP_Error; }
function wp_remote_retrieve_response_code($response) { return $response['response']['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
function esc_html($value) { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function esc_html__($value, $domain = '') { return esc_html($value); }
function esc_url($value) { return $value; }
function current_user_can($capability) { return $GLOBALS['allowed']; }
function wp_nonce_url($url, $action) { return $url . '&_wpnonce=valid'; }
function admin_url($path) { return 'https://example.test/wp-admin/' . $path; }
function self_admin_url($path) { return admin_url($path); }
function wp_die($message, $title, $args) { throw new RuntimeException('denied:' . $args['response']); }
function check_admin_referer($action) { if (!$GLOBALS['nonce']) { throw new RuntimeException('invalid nonce'); } }
function wp_update_plugins() { $GLOBALS['update_called'] = true; }
function add_query_arg($key, $value, $url) { return $url . '?' . $key . '=' . $value; }
function wp_safe_redirect($url) { throw new RuntimeException('redirect:' . $url); }
function wp_parse_args($value, $defaults) { return array_merge($defaults, $value); }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function apply_filters($hook, $value) { return $value; }
function plugin_dir_path($file) { return dirname($file) . '/'; }
function plugins_url($path, $file) { return 'https://example.test/wp-content/plugins/seasonal-modes/' . $path; }
function content_url($path) { return 'https://example.test/wp-content/' . $path; }
function trailingslashit($value) { return rtrim($value, '/') . '/'; }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)); }
function current_datetime() { return new DateTimeImmutable($GLOBALS['today'], new DateTimeZone('America/Vancouver')); }
function wp_unslash($value) { return stripslashes($value); }
function is_admin() { return false; }
require dirname(__DIR__) . '/seasonal-modes.php';
$updater = new Seasonal_Modes_GitHub_Updater(dirname(__DIR__) . '/seasonal-modes.php');
$count = 0;
$parts = explode('.', Seasonal_Modes::VERSION);
define('TEST_NEXT_VERSION', $parts[0] . '.' . $parts[1] . '.' . ((int) $parts[2] + 1));
function expect($value, $message) { global $count; $count++; if (!$value) { throw new RuntimeException('FAIL: ' . $message); } }
function reset_pack_state() { foreach (array('packs', 'active') as $name) { $p = new ReflectionProperty('Seasonal_Modes', $name); $p->setAccessible(true); $p->setValue(null, null); } }
function fixture($version = null) {
    $version = $version ?? TEST_NEXT_VERSION;
    $GLOBALS['transients'] = $GLOBALS['calls'] = array();
    $base = Seasonal_Modes_GitHub_Updater::URL . '/releases/download/v' . $version . '/';
    $data = array('draft' => false, 'prerelease' => false, 'tag_name' => 'v' . $version, 'body' => '<script>bad</script>', 'assets' => array());
    foreach (array('seasonal-modes.zip', 'update.json') as $name) {
        $data['assets'][] = array('name' => $name, 'browser_download_url' => $base . $name, 'state' => 'uploaded', 'size' => 200);
    }
    $manifest = array('version' => $version, 'download_url' => $base . 'seasonal-modes.zip', 'requires' => '6.3', 'requires_php' => '7.4');
    $GLOBALS['responses'] = array(
        'https://api.github.com/repos/amirrad98/seasonal-modes/releases/latest' => array('response' => array('code' => 200), 'body' => json_encode($data)),
        $base . 'update.json' => array('response' => array('code' => 200), 'body' => json_encode($manifest)),
    );
    return $data;
}
function change_release($data) { $GLOBALS['responses']['https://api.github.com/repos/amirrad98/seasonal-modes/releases/latest']['body'] = json_encode($data); }

expect(isset($GLOBALS['hooks']['update_plugins_github.com']), 'updater registers independently of seasonal mode');
fixture();
expect($updater->check('keep', array(), 'other/plugin.php') === 'keep' && count($GLOBALS['calls']) === 0, 'other plugins untouched');
$update = $updater->check(false, array(), 'seasonal-modes/seasonal-modes.php');
expect($update['version'] === TEST_NEXT_VERSION && version_compare($update['version'], Seasonal_Modes::VERSION, '>'), 'newer version offered');
expect($update['requires_php'] === '7.4' && $update['requires'] === '6.3', 'compatibility metadata preserved');
expect(!isset($update['autoupdate']), 'site auto-update choice respected');
expect(count($GLOBALS['calls']) === 2, 'release and exact metadata fetched');
$updater->release();
expect(count($GLOBALS['calls']) === 2 && $GLOBALS['expiries'][Seasonal_Modes_GitHub_Updater::CACHE] === 21600, 'six-hour successful cache');
$info = $updater->information(false, 'plugin_information', (object) array('slug' => 'seasonal-modes'));
expect(strpos($info->sections['changelog'], '&lt;script&gt;') !== false, 'release notes escaped');
expect($updater->information('keep', 'plugin_information', (object) array('slug' => 'other')) === 'keep', 'other plugin information untouched');
foreach (array(Seasonal_Modes::VERSION, '0.0.0') as $version) {
    fixture($version);
    $offer = $updater->check(false, array(), 'seasonal-modes/seasonal-modes.php');
    expect(!version_compare($offer['version'], Seasonal_Modes::VERSION, '>'), 'core comparison must not upgrade equal/older version');
}
foreach (array('draft', 'prerelease', 'tag', 'assets', 'zip', 'url', 'metadata_url', 'empty', 'state', 'types') as $case) {
    $data = fixture();
    if ($case === 'draft' || $case === 'prerelease') { $data[$case] = true; }
    if ($case === 'tag') { $data['tag_name'] = 'v1.3.0-beta'; }
    if ($case === 'assets') { $data['assets'] = 'invalid'; }
    if ($case === 'zip') { array_shift($data['assets']); }
    if ($case === 'url') { $data['assets'][0]['browser_download_url'] = 'https://attacker.test/plugin.zip'; }
    if ($case === 'metadata_url') { $data['assets'][1]['browser_download_url'] = 'https://attacker.test/update.json'; }
    if ($case === 'empty') { $data['assets'][0]['size'] = 0; }
    if ($case === 'state') { $data['assets'][0]['state'] = 'new'; }
    if ($case === 'types') { $data['tag_name'] = array(); }
    change_release($data);
    expect($updater->release() === false, 'reject invalid release: ' . $case);
}
foreach (array('version', 'download_url', 'requires', 'requires_php') as $field) {
    fixture();
    $url = Seasonal_Modes_GitHub_Updater::URL . '/releases/download/v' . TEST_NEXT_VERSION . '/update.json';
    $data = json_decode($GLOBALS['responses'][$url]['body'], true);
    $data[$field] = array('invalid');
    $GLOBALS['responses'][$url]['body'] = json_encode($data);
    expect($updater->release() === false, 'reject malformed manifest field: ' . $field);
}
foreach (array(new WP_Error(), array('response' => array('code' => 403), 'body' => '{}'), array('response' => array('code' => 200), 'body' => 'not-json')) as $response) {
    fixture();
    $GLOBALS['responses']['https://api.github.com/repos/amirrad98/seasonal-modes/releases/latest'] = $response;
    expect($updater->release() === false, 'network, rate limit, or malformed JSON fails safely');
    $updater->release();
    expect(count($GLOBALS['calls']) === 1 && $GLOBALS['expiries'][Seasonal_Modes_GitHub_Updater::CACHE] === 300, 'negative cache prevents repeated failures');
}
fixture();
$GLOBALS['allowed'] = false;
expect($updater->links(array(), 'seasonal-modes/seasonal-modes.php') === array(), 'no manual refresh link without capability');
try { $updater->refresh(); expect(false, 'unauthorized refresh must stop'); } catch (RuntimeException $e) { expect($e->getMessage() === 'denied:403', 'unauthorized refresh denied'); }
$GLOBALS['allowed'] = true;
$GLOBALS['nonce'] = false;
try { $updater->refresh(); expect(false, 'invalid nonce must stop'); } catch (RuntimeException $e) { expect($e->getMessage() === 'invalid nonce', 'invalid nonce denied'); }
expect(count($GLOBALS['calls']) === 0, 'denied refresh cannot make requests');
$GLOBALS['nonce'] = true;
try { $updater->refresh(); } catch (RuntimeException $e) { expect(strpos($e->getMessage(), 'seasonal-modes-update=checked') !== false, 'valid refresh redirects with result'); }
expect(!empty($GLOBALS['update_called']), 'manual refresh invokes WordPress update check');
expect(strpos(implode('', $updater->links(array(), 'seasonal-modes/seasonal-modes.php')), '_wpnonce=valid') !== false, 'refresh link includes nonce');

expect(Seasonal_Modes::OPTION === 'seasonal_modes' && Seasonal_Modes::ADAPTER_OPTION === 'seasonal_modes_adapter', 'settings names preserved');
expect(Seasonal_Modes::settings()['mode'] === 'off', 'default mode stays off');
expect(isset(Seasonal_Modes::packs()['halloween'], Seasonal_Modes::packs()['christmas']), 'original bundled packs discovered alongside any new packs');
$GLOBALS['options']['seasonal_modes'] = array('mode' => 'auto', 'pack' => 'halloween');
expect(Seasonal_Modes::active_pack()['slug'] === 'halloween', 'October uses Halloween');
$GLOBALS['today'] = '2026-12-15';
$GLOBALS['options']['seasonal_modes']['pack'] = 'christmas'; reset_pack_state();
expect(Seasonal_Modes::active_pack()['slug'] === 'christmas', 'December uses Christmas');
$GLOBALS['today'] = '2027-02-01'; reset_pack_state();
expect(Seasonal_Modes::active_pack() === null, 'outside dates has no pack');
$GLOBALS['today'] = '2027-01-02';
expect(Seasonal_Modes::in_window(array('window' => array('start' => '12-01', 'end' => '01-06'))), 'New Year wrap works');
$GLOBALS['options']['seasonal_modes'] = array('mode' => 'on', 'pack' => 'halloween'); reset_pack_state();
expect(Seasonal_Modes::active_pack()['slug'] === 'halloween', 'forced mode preserves selected pack');
$GLOBALS['options']['seasonal_modes'] = array('mode' => 'off', 'pack' => ''); reset_pack_state();
expect(Seasonal_Modes::active_pack() === null, 'off mode stays inactive');
$external = WP_CONTENT_DIR . '/seasonal-modes-packs/halloween';
mkdir($external, 0777, true);
file_put_contents($external . '/pack.json', json_encode(array('name' => 'Site Halloween', 'window' => array('start' => '10-01', 'end' => '11-01'))));
reset_pack_state();
expect(Seasonal_Modes::packs()['halloween']['name'] === 'Site Halloween', 'external custom pack overrides bundled pack');
unlink($external . '/pack.json'); rmdir($external); rmdir(dirname($external)); rmdir(WP_CONTENT_DIR);
echo "PASS: $count behavior assertions (WordPress substitutes, not a live integration test).\n";
