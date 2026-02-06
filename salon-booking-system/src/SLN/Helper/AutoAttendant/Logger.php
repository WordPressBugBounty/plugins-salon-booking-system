<?php

/**
 * Logger for Auto-Attendant feature
 * Provides safe logging with debug mode support
 */
class SLN_Helper_AutoAttendant_Logger
{
    /**
     * Log an event for auto-attendant feature
     * Only logs if debug mode is enabled
     *
     * @param string $event  Event name/type
     * @param array  $data   Event data
     * @param string $level  Log level (info, warning, error)
     */
    public static function log($event, $data = array(), $level = 'info')
    {
        // Only log if debug mode is enabled
        if (!defined('SLN_AUTO_ATTENDANT_DEBUG') || !SLN_AUTO_ATTENDANT_DEBUG) {
            return;
        }

        $logEntry = array(
            'timestamp' => current_time('mysql'),
            'level' => $level,
            'event' => $event,
            'data' => $data,
            'user_id' => get_current_user_id(),
            'session_id' => session_id() ?: 'no-session',
        );

        // Use WordPress debug log if available
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[SLN Auto-Attendant] ' . json_encode($logEntry));
        }

        // Also use plugin's own logging
        SLN_Plugin::addLog('[Auto-Attendant] ' . $event . ': ' . json_encode($data));
    }

    /**
     * Log the start of availability check
     *
     * @param int    $serviceId Service ID
     * @param string $datetime  Date and time
     */
    public static function logCheckStart($serviceId, $datetime)
    {
        self::log('availability_check_start', array(
            'service_id' => $serviceId,
            'datetime' => $datetime,
        ));
    }

    /**
     * Log the result of availability check
     *
     * @param int   $serviceId          Service ID
     * @param array $availableAttendants Array of available attendant IDs
     */
    public static function logCheckResult($serviceId, $availableAttendants)
    {
        self::log('availability_check_result', array(
            'service_id' => $serviceId,
            'available_count' => is_array($availableAttendants) ? count($availableAttendants) : 0,
            'attendant_ids' => $availableAttendants,
        ));
    }

    /**
     * Log an error during availability check
     *
     * @param string    $message   Error message
     * @param Exception $exception Exception object (optional)
     */
    public static function logError($message, $exception = null)
    {
        $data = array('message' => $message);

        if ($exception) {
            $data['exception'] = array(
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            );
        }

        self::log('error', $data, 'error');
    }

    /**
     * Log when feature is skipped/disabled
     *
     * @param string $reason Reason for skipping
     */
    public static function logSkipped($reason)
    {
        self::log('feature_skipped', array('reason' => $reason), 'info');
    }

    /**
     * Log when fallback logic is used
     *
     * @param string $reason Reason for fallback
     */
    public static function logFallback($reason)
    {
        self::log('fallback_triggered', array('reason' => $reason), 'warning');
    }
}



