<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

class SLN_Action_Ajax_InstallPlugin extends SLN_Action_Ajax_Abstract {

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
                $res = $this->plugin_install($files);
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

    protected static function plugin_install($files) {
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
                if ($sln_license->checkLicense()->license != 'valid')
                    return ['success' => false, 'message' => 'License is not valid. Please activate or check your license.'];
            }

            $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());
            $result = $upgrader->install($zipUrl);

            if (is_wp_error($result))
                return ['success' => false, 'message' => 'Error installing plugin: ' . esc_html($result->get_error_message())];

            if (!$result)
                return ['success' => false, 'message' => 'Plugin installation failed, something went wrong.'];

            $map = get_option('installed_plugin_map', []);
            $pluginSlug = self::get_plugin_slug($file->name);
            $map[$pluginSlug] = $upgrader->plugin_info();
            update_option('installed_plugin_map', $map);

            $installed = true;
        }

        if (!$installed)
            return ['success' => false, 'message' => 'No valid ZIP file found for installation.'];

        return ['success' => true, 'text' => 'Activate', 'message' => 'Plugin installed successfully.', 'action' => 'activate'];
    }

    public static function get_plugin($filename) {
        $allPlugins = get_plugins();
        $pluginSlug = self::get_plugin_slug($filename);

        $map = get_option('installed_plugin_map', []);
        $slug = isset($map[$pluginSlug]) ? $map[$pluginSlug] : 'not-found';

        foreach ($allPlugins as $pluginPath => $pluginData) {
            if (strpos($pluginPath, $pluginSlug) === 0 || strpos($pluginPath, $slug) === 0) {
                return [
                    'success' => true,
                    'is_activate' => is_plugin_active($pluginPath),
                    'plugin' => $pluginPath,
                    'check_version' => $pluginData['Version'] == self::get_plugin_version($filename),
                ];
            }
        }

        return ['success' => false];
    }

    public static function get_plugin_slug($filename) {
        return trailingslashit(sanitize_title(preg_replace('/-\d+(\.\d+)*$/', '', $filename)));
    }

    public static function get_plugin_version($filename) {
        return preg_match('/\d+\.\d+(?:\.\d+)?/', $filename, $m) ? $m[0] : '';
    }
}