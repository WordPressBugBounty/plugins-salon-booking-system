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
                return;
            }
        }

        $sessionWorking = $this->checkSessionWorking();

        if ($sessionWorking) {
            $this->useTransient = false;
            $this->clientId     = $clientId; // Now guaranteed to have a value
            return;
        }

        // Fallback to transients if sessions don't work
        $this->useTransient = true;
        $this->clientId     = $clientId; // Already generated above
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
            
            if (is_array($payload) && isset($payload['data'])) {
                $loadedData = is_array($payload['data']) ? $payload['data'] : $defaultData;
                $loadedLastId = isset($payload['last_id']) ? $payload['last_id'] : null;
                
                return array(
                    'data'    => $loadedData,
                    'last_id' => $loadedLastId,
                );
            }

            return array('data' => $defaultData, 'last_id' => null);
        }

        $data   = isset($_SESSION[$this->sessionKeyData]) ? $_SESSION[$this->sessionKeyData] : $defaultData;
        $lastId = isset($_SESSION[$this->sessionKeyLastId]) ? $_SESSION[$this->sessionKeyLastId] : null;

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
        if ($this->useTransient) {
            if (!$this->clientId) {
                $this->clientId = $this->generateClientId();
            }

            set_transient(
                $this->buildTransientKey($this->clientId),
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
     * Basic session write/read test.
     *
     * @return bool
     */
    private function checkSessionWorking()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

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
}
