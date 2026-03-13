<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

class SLN_Action_Ajax_InstallPlugin extends SLN_Action_Ajax_Abstract {

    /** @var array|null Per-request cache of get_plugins() results. */
    private static $pluginsCache = null;

    /** @var array|null Per-request cache of the installed_plugin_map option. */
    private static $pluginMapCache = null;

    public function execute() {
        global $sln_license;

        if (!isset($sln_license))
            return ['success' => false, 'message' => 'License manager not initialized.'];

        if (!current_user_can('install_plugins'))
            return ['success' => false, 'message' => 'Insufficient permissions for this action.'];

        $productID = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

        if (!$productID)
            return ['success' => false, 'message' => 'Invalid download ID.'];

        $res = $sln_license->getEddProducts($productID);
        if (!count($res))
            return ['success' => false, 'message' => 'An error occurred while fetching the data.'];

        $product = reset($res);
        $files = isset($product->files) ? (array)$product->files : [];
        if (!count($files))
            return ['success' => false, 'message' => 'No downloadable files found for this product.'];

        $action = isset($_POST['plugin_action']) ? $_POST['plugin_action'] : '';

        switch ($action) {
            case 'install':
                $res = $this->plugin_install($files, $productID);
                break;

            case 'activate':
                $res = $this->plugin_toggle_state($files, 'activate');
                break;

            case 'deactivate':
                $res = $this->plugin_toggle_state($files, 'deactivate');
                break;

            default:
                $res = ['success' => false, 'message' => 'Invalid or missing action.'];
        }

        return $res;
    }

    protected static function plugin_toggle_state($files, $action) {
        $hasArchive = false;

        foreach ($files as $file) {
            $zipUrl = esc_url_raw($file->file);

            if (strpos($zipUrl, '.zip') === false)
                continue;

            $hasArchive = true;

            $res = self::get_plugin($file->name);
            if (!$res['success'])
                return ['success' => false, 'message' => 'Plugin not found among installed plugins.'];

            if ($action == 'activate') {
                activate_plugin($res['plugin']);
                if (!is_plugin_active($res['plugin']))
                    return ['success' => false, 'message' => 'Plugin activating failed, something went wrong.'];
            } else {
                deactivate_plugins($res['plugin']);
                if (is_plugin_active($res['plugin']))
                    return ['success' => false, 'message' => 'Plugin deactivation failed, something went wrong.'];
            }
        }

        if (!$hasArchive)
            return ['success' => false, 'message' => 'No valid ZIP archive found among plugin files.'];

        return [
            'success' => true,
            'text' => $action == 'activate' ? 'Deactivate' : 'Activate',
            'message' => 'The plugin has been successfully ' . ($action == 'activate' ? 'activated' : 'deactivated') . '.',
            'action' => $action == 'activate' ? 'deactivate' : 'activate'
        ];
    }

    protected static function plugin_install($files, $productID = 0) {
        global $sln_license;

        $installed = false;

        foreach ($files as $file) {
            $zipUrl = esc_url_raw($file->file);

            if (strpos($zipUrl, '.zip') === false)
                continue;

            $res = self::get_plugin($file->name);
            if ($res['success']) {
                add_filter('upgrader_package_options', function($options) {
                    $options['clear_destination'] = true;
                    $options['overwrite_package'] = true;
                    return $options;
                });
            } else {
                if (!$sln_license->isValid())
                    return ['success' => false, 'message' => 'License is not valid. Please activate or check your license.'];
            }

            $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
            $result = $upgrader->install($zipUrl);

            if (is_wp_error($result))
                return ['success' => false, 'message' => 'Error installing plugin: ' . esc_html($result->get_error_message())];

            if (!$result)
                return ['success' => false, 'message' => 'Plugin installation failed, something went wrong.'];

            $map        = get_option('installed_plugin_map', []);
            $pluginSlug = self::get_plugin_slug($file->name);
            $pluginPath = $upgrader->plugin_info();
            $map[$pluginSlug] = $pluginPath;
            // Also index by EDD product ID for reliable detection on subsequent loads
            if ($productID) {
                $map['edd_product_' . $productID] = $pluginPath;
            }
            update_option('installed_plugin_map', $map);

            // Bust the static cache so subsequent get_plugin() calls in this
            // request reflect the newly installed plugin.
            self::clear_plugin_cache();

            $installed = true;
        }

        if (!$installed)
            return ['success' => false, 'message' => 'No valid ZIP file found for installation.'];

        return ['success' => true, 'text' => 'Activate', 'message' => 'Plugin installed successfully.', 'action' => 'activate'];
    }

    public static function get_plugin($filename) {
        // Populate the per-request caches once — avoids repeated filesystem scans and
        // DB reads when this method is called for every product file in the extensions loop.
        if (self::$pluginsCache === null) {
            self::$pluginsCache = get_plugins();
        }
        if (self::$pluginMapCache === null) {
            self::$pluginMapCache = get_option('installed_plugin_map', []);
        }

        $pluginSlug = self::get_plugin_slug($filename);
        $apiVersion = self::get_plugin_version($filename);

        // 1. Fast path: check the stored install map so we skip the full scan
        //    for plugins previously installed through this system.
        if (isset(self::$pluginMapCache[$pluginSlug])) {
            $mappedPath = self::$pluginMapCache[$pluginSlug];
            if (isset(self::$pluginsCache[$mappedPath])) {
                $installedVersion = isset(self::$pluginsCache[$mappedPath]['Version'])
                    ? self::$pluginsCache[$mappedPath]['Version'] : '0';
                return [
                    'success'       => true,
                    'is_activate'   => is_plugin_active($mappedPath),
                    'plugin'        => $mappedPath,
                    'check_version' => $apiVersion === '' || version_compare($installedVersion, $apiVersion, '>='),
                ];
            }
        }

        // 2. Full scan with matching strategies applied in priority order.
        //
        //    (a) Exact prefix match — standard production install where the zip extracts
        //        to a folder whose name equals the EDD file slug.
        //        e.g. slug "sbs-walkin-totem/" matches path "sbs-walkin-totem/sbs-walkin-totem.php"
        //
        //    (b) Normalised folder match — strips LocalWP-style dev suffixes such as
        //        " symlink" before comparing.
        //        e.g. "sbs-walkin-totem symlink/..." → cleaned to "sbs-walkin-totem" ✓
        //
        //    (c) Known slug remapping — handles cases where the EDD upload file name
        //        has historically diverged from the plugin folder name.
        //        Add entries here whenever an EDD file name differs from the folder.
        $slugBase = rtrim($pluginSlug, '/');

        $knownSlugAliases = [
            'multi-shop-add-on' => 'multi-shops',  // EDD file name vs plugin folder name
            'sln-paystack'      => 'slb-paystack',  // legacy sln-* prefix vs slb-* folder
        ];

        // Build the list of slugs to try (primary + alias if known)
        $slugsToCheck = [$slugBase];
        if (isset($knownSlugAliases[$slugBase])) {
            $slugsToCheck[] = $knownSlugAliases[$slugBase];
        }

        foreach (self::$pluginsCache as $pluginPath => $pluginData) {
            // (a) Exact prefix
            foreach ($slugsToCheck as $s) {
                if (strpos($pluginPath, $s . '/') === 0) {
                    $installedVersion = isset($pluginData['Version']) ? $pluginData['Version'] : '0';
                    return [
                        'success'       => true,
                        'is_activate'   => is_plugin_active($pluginPath),
                        'plugin'        => $pluginPath,
                        'check_version' => $apiVersion === '' || version_compare($installedVersion, $apiVersion, '>='),
                    ];
                }
            }

            // (b) Normalised folder name (strips " symlink" and similar dev suffixes)
            $folder      = strstr($pluginPath, '/', true);
            $cleanFolder = sanitize_title(preg_replace('/\s+(symlink|link)$/i', '', (string) $folder));
            foreach ($slugsToCheck as $s) {
                if ($cleanFolder === $s) {
                    $installedVersion = isset($pluginData['Version']) ? $pluginData['Version'] : '0';
                    return [
                        'success'       => true,
                        'is_activate'   => is_plugin_active($pluginPath),
                        'plugin'        => $pluginPath,
                        'check_version' => $apiVersion === '' || version_compare($installedVersion, $apiVersion, '>='),
                    ];
                }
            }
        }

        return ['success' => false];
    }

    /**
     * Look up an installed plugin by EDD product ID (stored at install time).
     * Returns the same structure as get_plugin() for a seamless fallback chain.
     */
    public static function get_plugin_by_product_id($productId, $apiVersion = '') {
        if (self::$pluginMapCache === null) {
            self::$pluginMapCache = get_option('installed_plugin_map', []);
        }
        if (self::$pluginsCache === null) {
            self::$pluginsCache = get_plugins();
        }

        $key        = 'edd_product_' . intval($productId);
        $pluginPath = isset(self::$pluginMapCache[$key]) ? self::$pluginMapCache[$key] : null;

        if (!$pluginPath || !isset(self::$pluginsCache[$pluginPath])) {
            return ['success' => false];
        }

        $installedVersion = isset(self::$pluginsCache[$pluginPath]['Version'])
            ? self::$pluginsCache[$pluginPath]['Version'] : '0';

        return [
            'success'       => true,
            'is_activate'   => is_plugin_active($pluginPath),
            'plugin'        => $pluginPath,
            'check_version' => $apiVersion === '' || version_compare($installedVersion, $apiVersion, '>='),
        ];
    }

    public static function get_plugin_slug($filename) {
        // Remove .zip extension first
        $name = preg_replace('/\.zip$/i', '', trim($filename));
        // Strip version number at end: handles both dash-separated (-1.2.3)
        // and space-separated (1.2.3) forms, e.g. "Communicator 1.2.0" → "Communicator"
        $name = preg_replace('/[\s\-]+\d+(\.\d+)+$/', '', $name);
        return trailingslashit(sanitize_title($name));
    }

    public static function get_plugin_version($filename) {
        return preg_match('/\d+\.\d+(?:\.\d+)?/', $filename, $m) ? $m[0] : '';
    }

    /**
     * Clears the per-request plugin cache.
     * Must be called after installing a plugin so subsequent get_plugin() calls
     * within the same request see the newly installed plugin.
     */
    public static function clear_plugin_cache() {
        wp_clean_plugins_cache();
        self::$pluginsCache   = null;
        self::$pluginMapCache = null;
    }
}