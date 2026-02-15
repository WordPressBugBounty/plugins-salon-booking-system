<?php

class SLN_Service_BookingPersistence
{
    const TRANSIENT_PREFIX = 'sln_booking_builder_';
    const TRANSIENT_TTL    = 3 * HOUR_IN_SECONDS; // Extended to 3 hours to prevent premature expiration

    /** @var string|null */
    private $clientId;

    /** @var bool */
    private $useTransient = false;

    /** @var string */
    private $sessionKeyData;

    /** @var string */
    private $sessionKeyLastId;

    public function __construct($sessionKeyData, $sessionKeyLastId, $clientId = null)
    {
        $this->sessionKeyData   = $sessionKeyData;
        $this->sessionKeyLastId = $sessionKeyLastId;

        $this->initialiseStorage($clientId);
    }

    /**
     * Determine whether we should rely on PHP sessions or fall back to transients.
     *
     * @param string|null $clientId
     * @return void
     */
    private function initialiseStorage($clientId)
    {
        // CRITICAL FIX: Always ensure client_id is generated for maximum reliability
        // Modern best practice: Fail-safe defaults with multiple fallback mechanisms
        //
        // Why this is essential:
        // 1. Safari ITP blocks/restricts localStorage, making client_id critical for transient fallback
        // 2. PHP sessions can fail mid-booking due to timeout, load balancers, or server issues
        // 3. Having a client_id enables seamless migration from session to transient storage
        // 4. Provides a consistent identifier for AJAX requests regardless of storage mode
        // 5. Enables graceful degradation when primary storage mechanism fails
        if (empty($clientId)) {
            $clientId = $this->generateClientId();
        }
        
        // If we already have a client id with stored transient data, continue to use it
        if (!empty($clientId)) {
            $payload = get_transient($this->buildTransientKey($clientId));
            if ($payload !== false) {
                $this->useTransient = true;
                $this->clientId     = $clientId;
                SLN_Plugin::addLog(sprintf('[Storage] Using transient storage (existing data found), client_id=%s', $clientId));
                return;
            }
        }

        // CRITICAL: Detect Safari browser and force transient mode
        // Safari ITP blocks third-party cookies and aggressively limits session cookies
        // Better to use transients from the start than fight with Safari's restrictions
        if ($this->isSafariBrowser()) {
            $this->useTransient = true;
            $this->clientId     = $clientId;
            SLN_Plugin::addLog(sprintf('[Storage] Safari browser detected, using transient storage, client_id=%s', $clientId));
            return;
        }

        // CRITICAL: Detect Safari ITP blocking session cookies
        // If we receive a client_id from request but session is empty/new,
        // it means the session cookie was blocked and we should use transients
        if (!empty($clientId) && $this->isSessionCookieBlocked()) {
            $this->useTransient = true;
            $this->clientId     = $clientId;
            SLN_Plugin::addLog(sprintf('[Storage] Session cookie blocked detected, using transient storage, client_id=%s', $clientId));
            return;
        }

        $sessionWorking = $this->checkSessionWorking($clientId);

        if ($sessionWorking) {
            $this->useTransient = false;
            $this->clientId     = $clientId; // Now guaranteed to have a value
            SLN_Plugin::addLog(sprintf('[Storage] Using session storage, client_id=%s, session_id=%s', $clientId, session_id()));
            return;
        }

        // Fallback to transients if sessions don't work
        $this->useTransient = true;
        $this->clientId     = $clientId; // Already generated above
        SLN_Plugin::addLog(sprintf('[Storage] Fallback to transient storage (session test failed), client_id=%s', $clientId));
    }

    /**
     * Load stored data.
     *
     * @param array $defaultData
     * @return array{data: array, last_id: int|null}
     */
    public function load(array $defaultData)
    {
        if ($this->useTransient) {
            $transientKey = $this->buildTransientKey($this->clientId);
            $payload = get_transient($transientKey);
            
            // CRITICAL DEBUG: Log exact transient key and what WordPress returns
            SLN_Plugin::addLog(sprintf('[BookingPersistence] LOAD TRANSIENT - key=%s, payload_type=%s, has_data=%s', 
                $transientKey,
                gettype($payload),
                (is_array($payload) && isset($payload['data'])) ? 'YES' : 'NO'
            ));
            
            if (is_array($payload) && isset($payload['data'])) {
                $loadedData = is_array($payload['data']) ? $payload['data'] : $defaultData;
                $loadedLastId = isset($payload['last_id']) ? $payload['last_id'] : null;
                
                // DEBUG: Log loaded transient data
                SLN_Plugin::addLog('[BookingPersistence] LOAD FROM TRANSIENT - client_id=' . $this->clientId . ', last_id=' . ($loadedLastId ? $loadedLastId : 'NULL') . ', has_services=' . (isset($loadedData['services']) && !empty($loadedData['services']) ? 'YES' : 'NO') . ', keys=' . implode(',', array_keys($loadedData)));
                
                return array(
                    'data'    => $loadedData,
                    'last_id' => $loadedLastId,
                );
            }

            SLN_Plugin::addLog('[BookingPersistence] LOAD FROM TRANSIENT - NO DATA FOUND (transient key=' . $transientKey . '), returning defaults');
            return array('data' => $defaultData, 'last_id' => null);
        }

        $data   = isset($_SESSION[$this->sessionKeyData]) ? $_SESSION[$this->sessionKeyData] : $defaultData;
        $lastId = isset($_SESSION[$this->sessionKeyLastId]) ? $_SESSION[$this->sessionKeyLastId] : null;

        // DEBUG: Log loaded session data
        $found = isset($_SESSION[$this->sessionKeyData]) ? 'FOUND' : 'NOT_FOUND';
        $hasServices = (isset($data['services']) && !empty($data['services'])) ? 'YES' : 'NO';
        $keys = is_array($data) && !empty($data) ? implode(',', array_keys($data)) : 'EMPTY';
        SLN_Plugin::addLog(sprintf('[BookingPersistence] LOAD FROM SESSION - session_id=%s, key=%s, found=%s, has_services=%s, keys=%s', session_id(), $this->sessionKeyData, $found, $hasServices, $keys));

        return array('data' => $data, 'last_id' => $lastId);
    }

    /**
     * Persist booking builder data.
     *
     * @param array $data
     * @param int|null $lastId
     * @return void
     */
    public function save(array $data, $lastId)
    {
        // DEBUG: Log what's being saved
        $hasServices = (isset($data['services']) && !empty($data['services'])) ? 'YES' : 'NO';
        $keys = !empty($data) ? implode(',', array_keys($data)) : 'EMPTY';
        $storage = $this->useTransient ? 'transient' : 'session';
        $transientKey = $this->useTransient ? $this->buildTransientKey($this->clientId) : 'N/A';
        
        SLN_Plugin::addLog(sprintf('[BookingPersistence] SAVE - storage=%s, key=%s, client_id=%s, session_id=%s, last_id=%s, has_services=%s, keys=%s', 
            $storage,
            $transientKey,
            $this->clientId, 
            session_id(), 
            ($lastId ? $lastId : 'NULL'),
            $hasServices, 
            $keys
        ));
        if ($this->useTransient) {
            if (!$this->clientId) {
                $this->clientId = $this->generateClientId();
            }

            $transientKey = $this->buildTransientKey($this->clientId);
            
            set_transient(
                $transientKey,
                array(
                    'data'    => $data,
                    'last_id' => $lastId,
                ),
                self::TRANSIENT_TTL
            );

            return;
        }

        $_SESSION[$this->sessionKeyData]   = $data;
        $_SESSION[$this->sessionKeyLastId] = $lastId;
    }

    /**
     * Clear persisted data.
     *
     * @param array $defaultData
     * @param int|null $lastId
     * @return void
     */
    public function clear(array $defaultData, $lastId = null)
    {
        if ($this->useTransient) {
            if ($this->clientId) {
                delete_transient($this->buildTransientKey($this->clientId));
            }

            // Ensure we immediately persist the reset state for subsequent requests
            $this->save($defaultData, $lastId);
            return;
        }

        $_SESSION[$this->sessionKeyData]   = $defaultData;
        $_SESSION[$this->sessionKeyLastId] = $lastId;
    }

    /**
     * Remove the stored last booking id only.
     *
     * @return void
     */
    public function removeLastId()
    {
        if ($this->useTransient) {
            if ($this->clientId) {
                $payload = get_transient($this->buildTransientKey($this->clientId));
                if (is_array($payload)) {
                    $payload['last_id'] = null;
                    set_transient(
                        $this->buildTransientKey($this->clientId),
                        $payload,
                        self::TRANSIENT_TTL
                    );
                }
            }
            return;
        }

        unset($_SESSION[$this->sessionKeyLastId]);
    }

    /**
     * Determine whether we are using transient storage.
     *
     * @return bool
     */
    public function isUsingTransient()
    {
        return $this->useTransient;
    }

    /**
     * Retrieve the active client id (if any).
     *
     * @return string|null
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Ensure a client id is available and return it.
     *
     * @return string|null
     */
    public function ensureClientId()
    {
        // CRITICAL FIX: Always generate client_id, regardless of storage mode
        // The client_id serves as a backup identifier for transient storage
        // and is needed for AJAX requests to maintain booking context
        if (empty($this->clientId)) {
            $this->clientId = $this->generateClientId();
        }

        return $this->clientId;
    }

    /**
     * Force switching to transient storage and return the client id.
     *
     * @param array $data
     * @param int|null $lastId
     * @return string
     */
    public function switchToTransient(array $data, $lastId)
    {
        if ($this->useTransient && !empty($this->clientId)) {
            $this->save($data, $lastId);
            return $this->clientId;
        }

        $this->useTransient = true;
        if (empty($this->clientId)) {
            $this->clientId = $this->generateClientId();
        }

        $this->save($data, $lastId);

        return $this->clientId;
    }

    /**
     * Detect if Safari ITP is blocking session cookies across requests.
     * 
     * This happens when:
     * 1. Session exists but is empty (no booking data marker)
     * 2. We have a client_id from the request (from previous booking steps)
     * 3. Session was recently created (new session)
     * 
     * This indicates the browser didn't send the session cookie from the previous request.
     *
     * @return bool True if session cookie appears to be blocked
     */
    private function isSessionCookieBlocked()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return true; // No active session = blocked
        }

        // Check if session has our initialization marker
        // If we don't have this marker but session exists, it's a new session (cookie blocked)
        $markerKey = '_sln_session_initialized';
        
        if (!isset($_SESSION[$markerKey])) {
            // First time we see this session - mark it
            $_SESSION[$markerKey] = time();
            
            // If this session is new but we have existing booking data elsewhere,
            // check if the session is genuinely new or if it's a new session due to cookie blocking
            // We check if there's any booking data in this session
            $hasBookingData = isset($_SESSION[$this->sessionKeyData]) || isset($_SESSION[$this->sessionKeyLastId]);
            
            if (!$hasBookingData) {
                // New session with no data - might be cookie blocked
                // We'll rely on the calling code's client_id check
                return true;
            }
        }

        return false;
    }

    /**
     * Check if sessions work within a single request.
     * 
     * @param string|null $clientId
     * @return bool
     */
    private function checkSessionWorking($clientId)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        // Basic write/read test within this request
        $testKey   = '_sln_session_test_' . uniqid('', true);
        $testValue = 'test_' . wp_rand(1000, 9999);
        $_SESSION[$testKey] = $testValue;

        $works = isset($_SESSION[$testKey]) && $_SESSION[$testKey] === $testValue;
        unset($_SESSION[$testKey]);

        return $works;
    }

    /**
     * Generate a random client identifier.
     *
     * @return string
     */
    private function generateClientId()
    {
        try {
            $bytes = random_bytes(16);
            return bin2hex($bytes);
        } catch (Exception $e) {
            // Fallback handled below.
        } catch (Error $e) {
            // Fallback handled below.
        }

        return md5(wp_rand() . microtime(true));
    }

    /**
     * Build the transient key for the provided client id.
     *
     * @param string $clientId
     * @return string
     */
    private function buildTransientKey($clientId)
    {
        return self::TRANSIENT_PREFIX . $clientId;
    }

    /**
     * Detect if the browser is Safari.
     * 
     * Safari has Intelligent Tracking Prevention (ITP) that aggressively blocks cookies,
     * making sessions unreliable for booking flows. We detect Safari and use transients instead.
     * 
     * @return bool True if Safari browser is detected
     */
    private function isSafariBrowser()
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $userAgent = $_SERVER['HTTP_USER_AGENT'];

        // Safari detection: Contains "Safari" but NOT "Chrome" and NOT "Chromium"
        // Chrome and Chromium include "Safari" in their UA but should not be treated as Safari
        $isSafari = (
            stripos($userAgent, 'Safari') !== false &&
            stripos($userAgent, 'Chrome') === false &&
            stripos($userAgent, 'Chromium') === false
        );

        return $isSafari;
    }
}
