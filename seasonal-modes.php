<?php
/**
 * Plugin Name:       Seasonal Modes
 * Description:       Theme-independent seasonal decorations (cursors, corner art, accents, a first-visit intro) driven by data packs. Ships with Halloween and Christmas packs.
 * Version:           1.1.0
 * Plugin URI:        https://github.com/amirrad98/seasonal-modes
 * Update URI:        https://github.com/amirrad98/seasonal-modes
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * Text Domain:       seasonal-modes
 *
 * Settings: Appearance > Seasonal Modes, or WP-CLI:
 *   wp option update seasonal_modes '{"mode":"auto","pack":"halloween"}' --format=json
 *   wp option update seasonal_modes_adapter '{"header":"#site-header"}' --format=json
 * Preview as an administrator: ?seasonal_preview=halloween (or =off).
 *
 * Nothing is output unless a pack is active for the request. Pack asset
 * credits live in each pack's CREDITS.md and are shown in the footer.
 */

if (!defined('ABSPATH')) {
    exit;
}

final class Seasonal_Modes {

    const VERSION        = '1.1.0';
    const OPTION         = 'seasonal_modes';
    const ADAPTER_OPTION = 'seasonal_modes_adapter';

    /** @var array|null|false Resolved pack for this request (false = none). */
    private static $active = null;

    /** @var array|null */
    private static $packs = null;

    public static function init() {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'));
        add_filter('body_class', array(__CLASS__, 'body_class'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 100);
        add_action('wp_footer', array(__CLASS__, 'footer_markup'), 5);
    }

    /* ---------- Settings ---------- */

    public static function settings() {
        $saved = get_option(self::OPTION, array());
        return wp_parse_args(is_array($saved) ? $saved : array(), array(
            'mode' => 'off',   // off | auto | on
            'pack' => '',      // pack slug; '' in auto mode = any pack in its window
        ));
    }

    /**
     * Per-site differences. Defaults suit most block themes.
     *
     * @return array
     */
    public static function adapter() {
        $saved   = get_option(self::ADAPTER_OPTION, array());
        $adapter = wp_parse_args(is_array($saved) ? $saved : array(), array(
            'header'        => 'header.wp-block-template-part',
            'logo'          => 'header .wp-block-site-logo a, header .custom-logo-link',
            'bottom_nav'    => '',
            'dark'          => '',  // '' none | 'media' prefers-color-scheme | ancestor selector, e.g. html[data-theme="dark"]
            'style_deps'    => '',  // comma-separated stylesheet handles to load after
            'pointer_extra' => '',  // extra selectors that should get the pointer cursor
            'header_stripe' => '1',
            'logo_badge'    => '1',
        ));

        /**
         * Filters the site adapter (selectors and feature switches).
         *
         * @param array $adapter
         */
        $adapter = (array) apply_filters('seasonal_modes_adapter', $adapter);

        // Options set over WP-CLI and filter values skip the settings sanitizer.
        foreach (array('header', 'logo', 'bottom_nav', 'dark', 'pointer_extra') as $key) {
            $adapter[$key] = self::clean_selector($adapter[$key] ?? '');
        }
        $adapter['style_deps']    = (string) ($adapter['style_deps'] ?? '');
        $adapter['header_stripe'] = empty($adapter['header_stripe']) ? '0' : '1';
        $adapter['logo_badge']    = empty($adapter['logo_badge']) ? '0' : '1';
        return $adapter;
    }

    public static function register_settings() {
        register_setting('seasonal_modes', self::OPTION, array(
            'type'              => 'object',
            'sanitize_callback' => array(__CLASS__, 'sanitize_settings'),
            'default'           => array('mode' => 'off', 'pack' => ''),
            'show_in_rest'      => false,
        ));
        register_setting('seasonal_modes', self::ADAPTER_OPTION, array(
            'type'              => 'object',
            'sanitize_callback' => array(__CLASS__, 'sanitize_adapter'),
            'default'           => array(),
            'show_in_rest'      => false,
        ));
    }

    public static function sanitize_settings($value) {
        $value = is_array($value) ? $value : array();
        $mode  = isset($value['mode']) ? sanitize_key($value['mode']) : 'off';
        $pack  = isset($value['pack']) ? sanitize_key($value['pack']) : '';
        return array(
            'mode' => in_array($mode, array('off', 'auto', 'on'), true) ? $mode : 'off',
            'pack' => isset(self::packs()[$pack]) ? $pack : '',
        );
    }

    public static function sanitize_adapter($value) {
        $value = is_array($value) ? $value : array();
        $clean = array();
        foreach (array('header', 'logo', 'bottom_nav', 'dark', 'pointer_extra') as $key) {
            $clean[$key] = isset($value[$key]) ? self::clean_selector($value[$key]) : '';
        }
        $clean['style_deps']    = isset($value['style_deps']) ? implode(',', array_filter(array_map('sanitize_key', explode(',', (string) $value['style_deps'])))) : '';
        $clean['header_stripe'] = empty($value['header_stripe']) ? '0' : '1';
        $clean['logo_badge']    = empty($value['logo_badge']) ? '0' : '1';
        return $clean;
    }

    /** Selectors go into a stylesheet; drop anything that could end a rule. */
    private static function clean_selector($selector) {
        return trim(preg_replace('/\s+/', ' ', str_replace(array('{', '}', ';', '<', '\\', '/*', '*/'), ' ', (string) $selector)));
    }

    /* ---------- Packs ---------- */

    /**
     * All packs keyed by slug. Later directories override earlier ones, so a
     * site can keep its own packs in wp-content/seasonal-modes-packs/.
     *
     * @return array
     */
    public static function packs() {
        if (self::$packs !== null) {
            return self::$packs;
        }

        $dirs = (array) apply_filters('seasonal_modes_pack_dirs', array(
            array('path' => plugin_dir_path(__FILE__) . 'packs', 'url' => plugins_url('packs', __FILE__)),
            array('path' => WP_CONTENT_DIR . '/seasonal-modes-packs', 'url' => content_url('seasonal-modes-packs')),
        ));

        self::$packs = array();
        foreach ($dirs as $dir) {
            if (empty($dir['path']) || !is_dir($dir['path'])) {
                continue;
            }
            foreach ((array) glob(trailingslashit($dir['path']) . '*/pack.json') as $file) {
                $data = json_decode((string) file_get_contents($file), true);
                if (!is_array($data)) {
                    continue;
                }
                $slug = sanitize_key(basename(dirname($file)));
                $data['slug'] = $slug;
                $data['path'] = dirname($file);
                $data['url']  = trailingslashit($dir['url']) . $slug;
                self::$packs[$slug] = $data;
            }
        }
        return self::$packs;
    }

    /** Whether today (site timezone) is inside a pack's MM-DD window; wraps over New Year. */
    public static function in_window(array $pack) {
        $start = isset($pack['window']['start']) ? (string) $pack['window']['start'] : '';
        $end   = isset($pack['window']['end']) ? (string) $pack['window']['end'] : '';
        if (!preg_match('/^\d\d-\d\d$/', $start) || !preg_match('/^\d\d-\d\d$/', $end)) {
            return false;
        }
        $today = current_datetime()->format('m-d');
        return $start <= $end ? ($today >= $start && $today <= $end) : ($today >= $start || $today <= $end);
    }

    /**
     * The pack active for this request, or null.
     *
     * @return array|null
     */
    public static function active_pack() {
        if (self::$active !== null) {
            return self::$active ?: null;
        }

        $packs    = self::packs();
        $settings = self::settings();
        $slug     = '';

        if ($settings['mode'] === 'on') {
            $slug = $settings['pack'];
        } elseif ($settings['mode'] === 'auto') {
            if ($settings['pack'] !== '') {
                $slug = isset($packs[$settings['pack']]) && self::in_window($packs[$settings['pack']]) ? $settings['pack'] : '';
            } else {
                foreach ($packs as $candidate) {
                    if (self::in_window($candidate)) {
                        $slug = $candidate['slug'];
                        break;
                    }
                }
            }
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only preview for administrators.
        if (isset($_GET['seasonal_preview']) && current_user_can('manage_options')) {
            $slug = sanitize_key(wp_unslash($_GET['seasonal_preview']));
        }

        /**
         * Filters the active pack slug for this request ('' for none).
         *
         * @param string $slug
         */
        $slug = (string) apply_filters('seasonal_modes_active_pack', $slug);

        self::$active = ($slug !== '' && isset($packs[$slug])) ? $packs[$slug] : false;
        return self::$active ?: null;
    }

    private static function asset_url(array $pack, $relative) {
        $relative = ltrim((string) $relative, '/');
        if ($relative === '' || strpos($relative, '..') !== false || !file_exists($pack['path'] . '/' . $relative)) {
            return '';
        }
        return $pack['url'] . '/' . $relative;
    }

    /* ---------- Front end ---------- */

    public static function body_class($classes) {
        $pack = is_admin() ? null : self::active_pack();
        if ($pack) {
            $classes[] = 'sm-active';
            $classes[] = 'sm-pack-' . $pack['slug'];
        }
        return $classes;
    }

    public static function enqueue() {
        $pack = self::active_pack();
        if (!$pack) {
            return;
        }
        $adapter = self::adapter();
        $base    = plugin_dir_path(__FILE__);

        // Load after the theme's last stylesheet(s) so the seasonal layer wins ties.
        $deps = array_values(array_filter(array_map('trim', explode(',', (string) $adapter['style_deps'])), 'wp_style_is'));
        wp_enqueue_style('seasonal-modes', plugins_url('assets/css/core.css', __FILE__), $deps, filemtime($base . 'assets/css/core.css'));
        wp_add_inline_style('seasonal-modes', self::inline_css($pack, $adapter));

        // Optional pack-specific extras.
        if (file_exists($pack['path'] . '/style.css')) {
            wp_enqueue_style('seasonal-modes-' . $pack['slug'], $pack['url'] . '/style.css', array('seasonal-modes'), filemtime($pack['path'] . '/style.css'));
        }

        wp_enqueue_script('seasonal-modes', plugins_url('assets/js/core.js', __FILE__), array(), filemtime($base . 'assets/js/core.js'), array('in_footer' => true, 'strategy' => 'defer'));
        wp_localize_script('seasonal-modes', 'seasonalModes', array(
            'pack'              => $pack['slug'],
            'headerSelector'    => $adapter['header'],
            'bottomNavSelector' => $adapter['bottom_nav'],
            'headerStripe'      => !empty($pack['headerStripe']) && $adapter['header_stripe'] === '1',
            'storageKey'        => 'sm_intro_' . $pack['slug'] . '_' . current_datetime()->format('Y'),
            'intro'             => array(
                'type'  => isset($pack['intro']['type']) ? sanitize_key($pack['intro']['type']) : '',
                'image' => isset($pack['intro']['image']) ? self::asset_url($pack, $pack['intro']['image']) : '',
            ),
        ));

        if (file_exists($pack['path'] . '/script.js')) {
            wp_enqueue_script('seasonal-modes-' . $pack['slug'], $pack['url'] . '/script.js', array('seasonal-modes'), filemtime($pack['path'] . '/script.js'), array('in_footer' => true, 'strategy' => 'defer'));
        }
    }

    /**
     * Pack data + adapter selectors as CSS. Values are validated here; the
     * static rules live in assets/css/core.css.
     */
    private static function inline_css(array $pack, array $adapter) {
        $colors = isset($pack['colors']) && is_array($pack['colors']) ? $pack['colors'] : array();
        $dark   = isset($colors['dark']) && is_array($colors['dark']) ? $colors['dark'] : array();
        $color  = function ($set, $key) {
            return isset($set[$key]) ? sanitize_hex_color($set[$key]) : '';
        };
        $url = function ($relative) use ($pack) {
            $u = self::asset_url($pack, $relative);
            return $u ? 'url("' . esc_url_raw($u) . '")' : '';
        };
        $b   = 'body.sm-active';
        $css = array();

        // Custom properties.
        $vars = array();
        foreach (array('accent' => '--sm-accent', 'accentSoft' => '--sm-accent-soft', 'ink' => '--sm-ink') as $key => $prop) {
            if ($color($colors, $key)) {
                $vars[] = $prop . ':' . $color($colors, $key);
            }
        }
        $corners = isset($pack['corners']) && is_array($pack['corners']) ? $pack['corners'] : array();
        if (isset($corners['opacity'])) {
            $vars[] = '--sm-web-opacity:' . (float) $corners['opacity'];
        }
        $webs = isset($pack['topWebs']) && is_array($pack['topWebs']) ? $pack['topWebs'] : array();
        if (isset($webs['opacity'])) {
            $vars[] = '--sm-top-web-opacity:' . (float) $webs['opacity'];
        }
        if ($vars) {
            $css[] = "$b{" . implode(';', $vars) . '}';
        }

        // Dark variant.
        $dark_vars = array();
        foreach (array('accent' => '--sm-accent', 'accentSoft' => '--sm-accent-soft') as $key => $prop) {
            if ($color($dark, $key)) {
                $dark_vars[] = $prop . ':' . $color($dark, $key);
            }
        }
        if (isset($corners['darkOpacity'])) {
            $dark_vars[] = '--sm-web-opacity:' . (float) $corners['darkOpacity'];
        }
        if (!empty($corners['invertInDark'])) {
            $dark_vars[] = '--sm-web-filter:invert(1)';
        }
        if (isset($webs['darkOpacity'])) {
            $dark_vars[] = '--sm-top-web-opacity:' . (float) $webs['darkOpacity'];
        }
        if (!empty($webs['invertInDark'])) {
            $dark_vars[] = '--sm-top-web-filter:invert(1)';
        }
        if ($dark_vars && $adapter['dark'] !== '') {
            $rule = '{' . implode(';', $dark_vars) . '}';
            $css[] = $adapter['dark'] === 'media'
                ? "@media (prefers-color-scheme: dark){{$b}{$rule}}"
                : "{$adapter['dark']} {$b}{$rule}";
        }

        if ($color($colors, 'selectionBackground')) {
            $css[] = "$b ::selection{background:" . $color($colors, 'selectionBackground') . ';color:' . ($color($colors, 'selectionText') ?: 'inherit') . '}';
        }

        // Top-corner webs: each side has its own size, tilt and offset so the pair looks uneven.
        foreach (array('left', 'right') as $side) {
            if (empty($webs[$side]['image']) || !$url($webs[$side]['image'])) {
                continue;
            }
            $w      = $webs[$side];
            $size   = isset($w['size']) ? absint($w['size']) : 200;
            $mobile = isset($w['mobileSize']) ? absint($w['mobileSize']) : (int) round($size / 2);
            $rot    = isset($w['rotate']) ? (float) $w['rotate'] : 0;
            $x      = isset($w['offsetX']) ? (int) $w['offsetX'] : 0;
            $y      = isset($w['offsetY']) ? (int) $w['offsetY'] : 0;
            $flip   = $side === 'right' ? ' scaleX(-1)' : '';
            $css[]  = "$b .sm-top-web--$side{{$side}:{$x}px;top:{$y}px;width:clamp({$mobile}px,16vw,{$size}px);transform:rotate({$rot}deg){$flip}}";
        }

        // Stripe along the bottom of the header.
        if (!empty($pack['headerStripe']) && $adapter['header_stripe'] === '1' && $adapter['header'] !== '') {
            $h = $adapter['header'];
            $css[] = "$b :is($h).sm-header-relative{position:relative}";
            // headerStripe may be true (accent/ink) or { colors: [a, b] }, e.g. candy cane.
            $stripe = is_array($pack['headerStripe']) && !empty($pack['headerStripe']['colors']) ? array_values((array) $pack['headerStripe']['colors']) : array();
            $sa     = isset($stripe[0]) && sanitize_hex_color($stripe[0]) ? sanitize_hex_color($stripe[0]) : 'var(--sm-accent)';
            $sb     = isset($stripe[1]) && sanitize_hex_color($stripe[1]) ? sanitize_hex_color($stripe[1]) : 'var(--sm-ink)';
            $css[] = "$b :is($h)::after{content:\"\";position:absolute;left:0;right:0;bottom:0;height:3px;background:repeating-linear-gradient(-45deg,$sa 0 10px,$sb 10px 20px);pointer-events:none}";
            $css[] = "@media print{{$b} :is($h)::after{display:none}}";
        }

        // Badge beside the logo (decorative, no layout change).
        $badge = isset($pack['logoBadge']) && is_array($pack['logoBadge']) ? $pack['logoBadge'] : array();
        if ($badge && $adapter['logo_badge'] === '1' && $adapter['logo'] !== '' && $url($badge['image'] ?? '')) {
            $l    = $adapter['logo'];
            $size = isset($badge['size']) ? absint($badge['size']) : 22;
            $rot  = isset($badge['rotate']) ? (float) $badge['rotate'] : 0;
            $css[] = "$b :is($l){position:relative}";
            $css[] = "$b :is($l)::after{content:\"\";position:absolute;top:-10px;right:-16px;width:{$size}px;height:{$size}px;background:" . $url($badge['image']) . " center/contain no-repeat;transform:rotate({$rot}deg);pointer-events:none}";
            $css[] = "@media print{{$b} :is($l)::after{display:none}}";
        }

        // Cursors: mouse/trackpad only.
        $cursors = isset($pack['cursors']) && is_array($pack['cursors']) ? $pack['cursors'] : array();
        $rules   = array();
        if (!empty($cursors['default']['image']) && $url($cursors['default']['image'])) {
            $c       = $cursors['default'];
            $rules[] = "$b{cursor:" . $url($c['image']) . ' ' . absint($c['x'] ?? 0) . ' ' . absint($c['y'] ?? 0) . ',auto}';
        }
        if (!empty($cursors['pointer']['image']) && $url($cursors['pointer']['image'])) {
            $c        = $cursors['pointer'];
            $pointers = 'a[href],button:not(:disabled),[role="button"],summary,label[for],select,'
                . 'input:is([type="submit"],[type="button"],[type="reset"],[type="checkbox"],[type="radio"]),'
                . '.wp-block-button__link,.wp-element-button,.wp-block-navigation-submenu__toggle,'
                . '.wp-block-navigation__responsive-container-open,.wp-block-navigation__responsive-container-close';
            if ($adapter['pointer_extra'] !== '') {
                $pointers .= ',' . $adapter['pointer_extra'];
            }
            $rules[] = "$b :is($pointers),$b :is(a[href],button,[role=\"button\"]) *{cursor:" . $url($c['image']) . ' ' . absint($c['x'] ?? 0) . ' ' . absint($c['y'] ?? 0) . ',pointer}';
        }
        if ($rules) {
            // Keep the I-beam where people type.
            $rules[] = "$b :is(input:not([type=\"submit\"],[type=\"button\"],[type=\"reset\"],[type=\"checkbox\"],[type=\"radio\"],[type=\"range\"],[type=\"color\"],[type=\"file\"],[type=\"image\"]),textarea,[contenteditable=\"\"],[contenteditable=\"true\"]){cursor:text}";
            $css[]   = '@media (hover:hover) and (pointer:fine){' . implode('', $rules) . '}';
        }

        return implode("\n", $css);
    }

    /** Decorative corner layer and the pack's attribution line. */
    public static function footer_markup() {
        $pack = is_admin() ? null : self::active_pack();
        if (!$pack) {
            return;
        }

        $corners = isset($pack['corners']) && is_array($pack['corners']) ? $pack['corners'] : array();
        $img     = !empty($corners['image']) ? self::asset_url($pack, $corners['image']) : '';
        $webs    = isset($pack['topWebs']) && is_array($pack['topWebs']) ? $pack['topWebs'] : array();
        $top_web = array();
        foreach (array('left', 'right') as $side) {
            if (!empty($webs[$side]['image']) && self::asset_url($pack, $webs[$side]['image'])) {
                $top_web[$side] = self::asset_url($pack, $webs[$side]['image']);
            }
        }
        if ($top_web) {
            echo '<div class="sm-top-webs" aria-hidden="true">';
            foreach ($top_web as $side => $src) {
                printf('<img class="sm-top-web sm-top-web--%1$s" src="%2$s" alt="" decoding="async">', esc_attr($side), esc_url($src));
            }
            echo '</div>';
        }

        if ($img) {
            $w = absint($corners['width'] ?? 0);
            $h = absint($corners['height'] ?? 0);
            echo '<div class="sm-corners" aria-hidden="true">';
            // With top webs in front, the faint background art only fills the bottom corners.
            foreach ($top_web ? array('bl', 'br') : array('tl', 'tr', 'bl', 'br') as $pos) {
                printf(
                    '<img class="sm-corner sm-corner--%1$s" src="%2$s" alt=""%3$s decoding="async">',
                    esc_attr($pos),
                    esc_url($img),
                    $w && $h ? sprintf(' width="%d" height="%d"', $w, $h) : ''
                );
            }
            echo '</div>';
        }

        if (!empty($pack['credit']['text'])) {
            $links = array();
            foreach ((array) ($pack['credit']['links'] ?? array()) as $link) {
                $links[] = sprintf('<a href="%s">%s</a>', esc_url($link['url'] ?? ''), esc_html($link['label'] ?? ''));
            }
            $text = esc_html($pack['credit']['text']);
            // A pack's credit text must have a %N$s for each link.
            try {
                $line = $links ? vsprintf($text, $links) : $text;
            } catch (\Throwable $e) {
                $line = $text;
            }
            echo '<p class="sm-credit">' . wp_kses($line, array('a' => array('href' => true))) . '</p>';
        }
    }

    /* ---------- Admin ---------- */

    public static function admin_menu() {
        add_theme_page(
            __('Seasonal Modes', 'seasonal-modes'),
            __('Seasonal Modes', 'seasonal-modes'),
            'manage_options',
            'seasonal-modes',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        $settings = self::settings();
        $adapter  = self::adapter();
        $packs    = self::packs();
        $opt      = self::OPTION;
        $aopt     = self::ADAPTER_OPTION;
        $active   = self::active_pack();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Seasonal Modes', 'seasonal-modes'); ?></h1>
            <p>
                <?php
                echo $active
                    /* translators: %s: pack name */
                    ? esc_html(sprintf(__('Active now: %s.', 'seasonal-modes'), $active['name'] ?? $active['slug']))
                    : esc_html__('No pack is active right now; the site looks normal.', 'seasonal-modes');
                ?>
            </p>
            <form method="post" action="options.php">
                <?php settings_fields('seasonal_modes'); ?>
                <h2><?php esc_html_e('Season', 'seasonal-modes'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="sm-mode"><?php esc_html_e('Mode', 'seasonal-modes'); ?></label></th>
                        <td>
                            <select id="sm-mode" name="<?php echo esc_attr($opt); ?>[mode]">
                                <option value="off" <?php selected($settings['mode'], 'off'); ?>><?php esc_html_e('Off', 'seasonal-modes'); ?></option>
                                <option value="auto" <?php selected($settings['mode'], 'auto'); ?>><?php esc_html_e('Auto (by the pack’s date window)', 'seasonal-modes'); ?></option>
                                <option value="on" <?php selected($settings['mode'], 'on'); ?>><?php esc_html_e('Force on', 'seasonal-modes'); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="sm-pack"><?php esc_html_e('Pack', 'seasonal-modes'); ?></label></th>
                        <td>
                            <select id="sm-pack" name="<?php echo esc_attr($opt); ?>[pack]">
                                <option value="" <?php selected($settings['pack'], ''); ?>><?php esc_html_e('Any pack in season (Auto only)', 'seasonal-modes'); ?></option>
                                <?php foreach ($packs as $slug => $pack) : ?>
                                    <option value="<?php echo esc_attr($slug); ?>" <?php selected($settings['pack'], $slug); ?>>
                                        <?php echo esc_html(sprintf('%s (%s – %s)', $pack['name'] ?? $slug, $pack['window']['start'] ?? '?', $pack['window']['end'] ?? '?')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php
                                foreach ($packs as $slug => $pack) {
                                    printf('<a href="%s" target="_blank">%s</a> ', esc_url(add_query_arg('seasonal_preview', $slug, home_url('/'))), esc_html(sprintf(__('Preview %s', 'seasonal-modes'), $pack['name'] ?? $slug)));
                                }
                                ?>
                                <?php esc_html_e('(visible to administrators only)', 'seasonal-modes'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Site adapter', 'seasonal-modes'); ?></h2>
                <p><?php esc_html_e('CSS selectors for this theme. The defaults suit most block themes.', 'seasonal-modes'); ?></p>
                <table class="form-table" role="presentation">
                    <?php
                    $fields = array(
                        'header'        => __('Header', 'seasonal-modes'),
                        'logo'          => __('Logo link', 'seasonal-modes'),
                        'bottom_nav'    => __('Fixed bottom navigation', 'seasonal-modes'),
                        'dark'          => __('Dark mode (ancestor selector, or “media”)', 'seasonal-modes'),
                        'style_deps'    => __('Load after stylesheet handles', 'seasonal-modes'),
                        'pointer_extra' => __('Extra clickable selectors', 'seasonal-modes'),
                    );
                    foreach ($fields as $key => $label) :
                        ?>
                        <tr>
                            <th scope="row"><label for="sm-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label></th>
                            <td><input type="text" class="large-text code" id="sm-<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($aopt . '[' . $key . ']'); ?>" value="<?php echo esc_attr($adapter[$key]); ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr>
                        <th scope="row"><?php esc_html_e('Features', 'seasonal-modes'); ?></th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr($aopt); ?>[header_stripe]" value="1" <?php checked($adapter['header_stripe'], '1'); ?>> <?php esc_html_e('Stripe under the header', 'seasonal-modes'); ?></label><br>
                            <label><input type="checkbox" name="<?php echo esc_attr($aopt); ?>[logo_badge]" value="1" <?php checked($adapter['logo_badge'], '1'); ?>> <?php esc_html_e('Badge beside the logo', 'seasonal-modes'); ?></label>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

Seasonal_Modes::init();

require_once __DIR__ . '/includes/class-github-updater.php';
new Seasonal_Modes_GitHub_Updater(__FILE__);
