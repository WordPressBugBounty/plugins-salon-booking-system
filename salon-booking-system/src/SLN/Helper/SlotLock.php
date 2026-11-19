<?php

/**
 * MySQL advisory lock on a booking start time, shared by every flow that checks
 * availability and then saves a booking (front-end checkout, back-end editor, PWA).
 *
 * While one request holds the lock for a slot, a concurrent request for the same
 * slot waits, then runs its availability check against the booking the first one
 * saved, so two bookings can't both pass the check on the same free spot.
 *
 * GET_LOCK is atomic at the database level and the lock is released when the
 * MySQL connection closes, so it is never orphaned. Locks taken through acquire()
 * are also released on shutdown.
 */
class SLN_Helper_SlotLock
{
    /** @var string[] lock names held by this request */
    private static $held = array();

    /**
     * Lock name for a booking start time (at most 64 chars, as MySQL requires).
     *
     * @param DateTimeInterface|null $startsAt
     *
     * @return string|null null when there is no valid start time
     */
    public static function key($startsAt)
    {
        if (!($startsAt instanceof DateTimeInterface)) {
            return null;
        }

        return 'sln_'.md5($startsAt->format('Y-m-d H:i'));
    }

    /**
     * @param string|null $key     from key(); null always succeeds
     * @param int         $timeout seconds to wait for a concurrent request
     *
     * @return bool true when the lock is held (or there is nothing to lock)
     */
    public static function acquire($key, $timeout = 5)
    {
        if ($key === null) {
            return true;
        }
        if (isset(self::$held[$key])) {
            return true;
        }
        global $wpdb;
        $result = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $key, $timeout));
        if ($result !== '1') {
            SLN_Plugin::addLog(sprintf('[SlotLock] GET_LOCK(%s, %d) failed: %s', $key, $timeout, var_export($result, true)));

            return false;
        }
        if (empty(self::$held)) {
            add_action('shutdown', array(__CLASS__, 'releaseAll'));
        }
        self::$held[$key] = true;

        return true;
    }

    /**
     * @param string|null $key
     */
    public static function release($key)
    {
        if ($key === null || !isset(self::$held[$key])) {
            return;
        }
        global $wpdb;
        $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
        unset(self::$held[$key]);
    }

    public static function releaseAll()
    {
        foreach (array_keys(self::$held) as $key) {
            self::release($key);
        }
    }
}
