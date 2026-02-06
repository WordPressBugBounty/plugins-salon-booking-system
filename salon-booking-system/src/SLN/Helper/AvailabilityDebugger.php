<?php

/**
 * Dedicated debugger for availability slot analysis
 * Provides clear, structured output for troubleshooting time slot availability
 */
class SLN_Helper_AvailabilityDebugger
{
    private static $enabled = true;
    private static $sessionId = null;

    public static function init()
    {
        if (self::$sessionId === null) {
            self::$sessionId = substr(md5(microtime()), 0, 8);
        }
    }

    public static function isEnabled()
    {
        return self::$enabled && SLN_Plugin::isDebugEnabled();
    }

    /**
     * Log the start of an availability check session
     */
    public static function logSessionStart($date, $service = null, $context = '')
    {
        if (!self::isEnabled()) {
            return;
        }
        self::init();
        
        $msg = sprintf(
            "\n========================================\n[SESSION %s] AVAILABILITY CHECK START\n========================================\n",
            self::$sessionId
        );
        $msg .= sprintf("Date: %s\n", $date->format('Y-m-d'));
        if ($service) {
            $msg .= sprintf("Service: %s (ID: %d)\n", $service->getName(), $service->getId());
            $msg .= sprintf("Duration: %s\n", $service->getDuration()->format('H:i'));
            $msg .= sprintf("Break: %s\n", $service->getBreakDuration()->format('H:i'));
            $msg .= sprintf("Allow Nested (Global): %s\n", SLN_Plugin::getInstance()->getSettings()->isNestedBookingsEnabled() ? 'YES' : 'NO');
        }
        if ($context) {
            $msg .= sprintf("Context: %s\n", $context);
        }
        $msg .= "========================================\n";
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Log all existing bookings for a date
     */
    public static function logExistingBookings($date, $bookings)
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $msg = sprintf("\n[EXISTING BOOKINGS] Date: %s\n", $date->format('Y-m-d'));
        
        if (empty($bookings)) {
            $msg .= "  No bookings found\n";
        } else {
            foreach ($bookings as $booking) {
                foreach ($booking->getBookingServices()->getItems() as $bs) {
                    $service = $bs->getService();
                    $allowNested = SLN_Plugin::getInstance()->getSettings()->isNestedBookingsEnabled();
                    
                    $msg .= sprintf(
                        "  [#%d] %s - %s | Service: %s | Break: %s-%s | NestedOK: %s\n",
                        $booking->getId(),
                        $bs->getStartsAt()->format('H:i'),
                        $bs->getEndsAt()->format('H:i'),
                        $service ? $service->getName() : 'N/A',
                        $bs->getBreakStartsAt() ? $bs->getBreakStartsAt()->format('H:i') : 'N/A',
                        $bs->getBreakEndsAt() ? $bs->getBreakEndsAt()->format('H:i') : 'N/A',
                        $allowNested ? 'YES' : 'NO'
                    );
                }
            }
        }
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Log all generated timeslots with their status
     */
    public static function logTimeslots($date, $timeslots)
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $msg = sprintf("\n[TIMESLOTS] All slots for %s:\n", $date->format('Y-m-d'));
        $msg .= sprintf("%-8s | %-12s | %-12s | %-12s | %-12s | %-8s\n", 
            'Time', 'Bookings', 'Services', 'Attendants', 'Resources', 'Breaks');
        $msg .= str_repeat('-', 90) . "\n";
        
        ksort($timeslots);
        foreach ($timeslots as $time => $data) {
            $bookingCount = isset($data['booking']) ? count($data['booking']) : 0;
            $serviceCount = isset($data['service']) ? count($data['service']) : 0;
            $attendantCount = isset($data['attendant']) ? count($data['attendant']) : 0;
            $resourceCount = isset($data['resource']) ? count($data['resource']) : 0;
            $breakCount = isset($data['break']) ? count($data['break']) : 0;
            
            $msg .= sprintf("%-8s | %-12d | %-12d | %-12d | %-12d | %-8d\n",
                $time,
                $bookingCount,
                $serviceCount,
                $attendantCount,
                $resourceCount,
                $breakCount
            );
        }
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Log available times after filtering
     */
    public static function logAvailableTimes($times, $context = '')
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $msg = sprintf("\n[AVAILABLE TIMES] %s\n", $context);
        
        if (empty($times)) {
            $msg .= "  No times available\n";
        } else {
            $msg .= sprintf("  Total: %d slots\n", count($times));
            $msg .= "  Times: " . implode(', ', array_keys($times)) . "\n";
        }
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Log filtered times (what was removed and why)
     */
    public static function logFilteredTimes($original, $filtered, $reason = '')
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $removed = array_diff_key($original, $filtered);
        
        if (empty($removed)) {
            return;
        }
        
        $msg = sprintf("\n[FILTERED OUT] %s\n", $reason);
        $msg .= sprintf("  Removed %d slots: %s\n", count($removed), implode(', ', array_keys($removed)));
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Log break slot analysis
     */
    public static function logBreakSlotAnalysis($time, $isBreak, $bookingId = null)
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $msg = sprintf(
            "[BREAK SLOT] %s | isBreak: %s | bookingId: %s\n",
            $time instanceof DateTime ? $time->format('H:i') : $time,
            $isBreak ? 'YES' : 'NO',
            $bookingId ?: 'N/A'
        );
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Log validation result for a specific slot
     */
    public static function logSlotValidation($time, $isValid, $reason = '')
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $status = $isValid ? '✓ VALID' : '✗ INVALID';
        $msg = sprintf(
            "[VALIDATION] %s | %s | %s\n",
            $time instanceof DateTime ? $time->format('H:i') : $time,
            $status,
            $reason
        );
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Log the final frontend response
     */
    public static function logFrontendResponse($intervalsArray)
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $msg = "\n[FRONTEND RESPONSE] What the time picker will receive:\n";
        
        if (isset($intervalsArray['times'])) {
            $times = $intervalsArray['times'];
            if (empty($times)) {
                $msg .= "  No times available\n";
            } else {
                $msg .= sprintf("  Total: %d slots\n", count($times));
                $msg .= "  Times: " . implode(', ', array_keys($times)) . "\n";
            }
        }
        
        $msg .= sprintf("\n[SESSION %s] END\n", self::$sessionId);
        $msg .= str_repeat('=', 80) . "\n";
        
        SLN_Plugin::addLog($msg);
    }

    /**
     * Clear accumulated logs (for development)
     */
    public static function clearLogs()
    {
        if (!self::isEnabled()) {
            return;
        }
        
        $logPath = SLN_Plugin::getLogFilePath('availability-debug.log');
        if ($logPath && file_exists($logPath)) {
            file_put_contents($logPath, '');
        }
    }
}

