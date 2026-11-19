<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
class SLN_Action_Update
{
    /** @var array DB updates that need to be run */
    private static $dbUpdates = array(
        '2.3'    => 'sln-update-for-2.3.php',
        '2.3.1'  => 'sln-update-for-2.3.1.php',
        '3.0'  => 'sln-update-for-3.0.php',
        '7.6.4' => 'sln-update-for-7.6.4.php',
        '10.30.11' => 'sln-update-for-10.30.11.php',
    );

    /**
     * @var array DB rollback scripts that revert database changes
     * 
     * IMPORTANT: This array is currently empty. To enable database rollback functionality:
     * 1. Create rollback scripts in src/SLN/Action/Rollbacks/ directory
     * 2. Add entries here mapping versions to rollback script filenames
     * 3. Each script should reverse the changes made by its corresponding update script
     * 
     * Example:
     * '3.0' => 'sln-rollback-to-2.3.2.php'
     * 
     * Without rollback scripts, the Tools page "Rollback database" button will not function.
     * Users will need to use the License page "Rollback Options" feature instead,
     * which downloads complete previous plugin versions from the EDD API.
     */
    private static $dbRollbacks = array(
    );

    /** @var  SLN_Plugin */
    private $plugin;

    public static function getDbUpdates()
    {
        $updates = self::$dbUpdates;
        foreach($updates as $k => $update) {
            $updates[$k] = plugin_dir_path(__FILE__) . 'Updates/' . $update;
        }
        return $updates;
    }

    public static function getDbRollbacks()
    {
        $rollbacks = self::$dbRollbacks;
        foreach($rollbacks as $k => $rollback) {
            $rollbacks[$k] = plugin_dir_path(__FILE__) . 'Rollbacks/' . $rollback;
        }
        return $rollbacks;
    }

    const AVAILABILITY_CACHE_VERSION_OPTION = 'sln_availability_cache_version';

    /**
     * Rebuild the saved availability (salon_cache free_slots / full days) once
     * after the plugin is updated.
     *
     * A saved day is otherwise only rebuilt when one of its bookings or a rule
     * changes, so days written by an older version with a cache bug keep offering
     * booked hours forever after the fix ships. Runs on shutdown so the admin page
     * that triggers it is not delayed.
     */
    private function maybeRebuildAvailabilityCache()
    {
        if (!defined('SLN_VERSION') || get_option(self::AVAILABILITY_CACHE_VERSION_OPTION) === SLN_VERSION) {
            return;
        }
        update_option(self::AVAILABILITY_CACHE_VERSION_OPTION, SLN_VERSION, false);

        $plugin = $this->plugin;
        add_action('shutdown', function () use ($plugin) {
            try {
                $plugin->getBookingCache()->refreshAll();
            } catch (Exception $e) {
                SLN_Plugin::addLog('[Cache Invalidation] post-update availability rebuild failed: ' . $e->getMessage());
            }
        });
    }

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        if (defined('DOING_AJAX') && DOING_AJAX) {
            return;
        }

        if (!empty($_GET['do_update_sln'])) {
            $this->update();
        }

        if (!empty($_GET['do_rollback_sln'])) {
            $this->rollback();
        }
        
        $this->maybeRebuildAvailabilityCache();

        $s = $this->plugin->getSettings();
        $max = max(array_keys(self::getDbUpdates()));
        $version = $s->getDbVersion();
        if (version_compare($version, $max, '<')) {
            // Fresh install (0.0.0): Install will set sln_db_version; do not show update notice.
            if ($version !== '0.0.0') {
                add_action('admin_notices', array($this, 'hook_admin_notices'));
            }
        } else {
            $s->setDbVersion()->save();
        }
    }

    public function hook_admin_notices(){
        remove_action('admin_notice', array($this, 'hook_admin_notices'));
        $version = $this->plugin->getSettings()->getDbVersion();
        echo $this->plugin->loadView('notice/html_notice_update', compact('version'));
    }

    private function update()
    {
        $s = $this->plugin->getSettings();
        $current_version = $s->getDbVersion();

        $updates = self::getDbUpdates();
        ksort($updates);
        foreach ($updates as $version => $updater) {
            if (version_compare($current_version, $version, '<')) {
                include($updater);
                $s->setDbVersion($version)->save();
            }
        }

        $s->setDbVersion()->save();
    }

    private function rollback()
    {
        $s = $this->plugin->getSettings();
        $current_version = $s->getDbVersion();

        $rollbacks = self::getDbRollbacks();
        krsort($rollbacks);
        foreach ($rollbacks as $version => $rollback) {
            if (version_compare($current_version, $version, '>=')) {
                if (preg_match('/sln-rollback-to-(\d+[\.\d+]*).php$/', $rollback, $matches)) {
                    $versionToRollback = $matches[1];
                    include($rollback);
                    $s->setDbVersion($versionToRollback)->save();
                    break;
                }
            }
        }
    }
}
