<?php

abstract class SLN_Wrapper_Abstract
{
    protected $object;

    abstract public function getPostType();

    function __construct($object)
    {
        if (!is_object($object)) {
            $object = apply_filters('sln_get_object', get_post($object), $object);
        }

        if(is_object($object) && in_array(get_post_type( clone $object ),['sln_service','sln_attendant','sln_shop']) && !empty($object->ID) && SLN_Helper_Multilingual::isMultilingual()) {
            $this->translationObjectId = $object->ID;
            $this->translationObject = $object;
                $defaultLanguage = SLN_Helper_Multilingual::getDefaultLanguage();
                $objectLanguage = SLN_Helper_Multilingual::getObjectLanguage($this->translationObjectId);
                if($defaultLanguage !== $objectLanguage ){
                    $original_id = SLN_Helper_Multilingual::translateId($this->translationObjectId);
                    if($original_id !== $object->ID) $object  = get_post($original_id);
                }
        }
        $this->object = $object;
    }

    function isMultilingual(){
        return isset($this->translationObjectId);
    }

    public function reload(){
        $this->object = get_post($this->getId());
        if($this->isMultilingual()){
            $this->translationObject = get_post(($this->translationObjectId));
        }
    }

    function getId()
    {
        if ($this->object) {
            return $this->object->ID;
        }
    }

    public function isEmpty()
    {
        return empty($this->object);
    }

    public function getMeta($key, $targetTranslation = false, $single = true)
    {
        $pt = $this->getPostType();

        $id = $targetTranslation && $this->isMultilingual() ? $this->translationObjectId : $this->getId();
        return apply_filters("$pt.$key.get", get_post_meta($id, "_{$pt}_$key", $single), $id);
    }

    public function setMeta($key, $value, $targetTranslation = false )
    {
        $pt = $this->getPostType();
        $id = $targetTranslation && $this->isMultilingual()  ? $this->translationObjectId : $this->getId();
        if (apply_filters("$pt.$key.is_set_meta", true, $id)) update_post_meta($id, "_{$pt}_$key", apply_filters("$pt.$key.set", $value));
    }

    public function addMeta($key, $value, $unique = false, $targetTranslation = false)
    {
        $pt = $this->getPostType();
        $id = $targetTranslation && $this->isMultilingual()  ? $this->translationObjectId : $this->getId();
        add_post_meta($id, "_{$pt}_$key", $value, $unique);
    }

    public function getStatus()
    {
        return $this->object->post_status;
    }

    public function hasStatus($status)
    {
        return SLN_Func::has($this->getStatus(), $status);
    }

    /**
     * @param $status
     * @return $this
     */
    public function setStatus($status)
    {
        // Debug logging (only when debug mode enabled)
        $currentStatusBeforeCheck = $this->object->post_status;
        $postObject = get_post($this->getId());
        SLN_Plugin::addLog(sprintf(
            'setStatus() called for Booking #%d: %s → %s | DB status: %s, post_date_gmt: %s',
            $this->getId(),
            $currentStatusBeforeCheck,
            $status,
            $postObject ? $postObject->post_status : 'null',
            $postObject ? $postObject->post_date_gmt : 'null'
        ));
        
        $post = array();
        $post['ID'] = $this->getId();
        $post['post_status'] = $status;
        
        // CRITICAL FIX: When transitioning from auto-draft status, WordPress requires
        // post_date and post_date_gmt to be set properly. Auto-draft posts have
        // post_date_gmt = '0000-00-00 00:00:00' which can cause wp_update_post to fail silently.
        $currentStatus = $this->object->post_status;
        if ($currentStatus === 'auto-draft' && $status !== 'auto-draft') {
            // Set post_date to current time when transitioning from auto-draft
            $now = current_time('mysql');
            $now_gmt = current_time('mysql', true);
            $post['post_date'] = $now;
            $post['post_date_gmt'] = $now_gmt;
            
            // Debug logging (only when debug mode enabled)
            SLN_Plugin::addLog(sprintf(
                'AUTO-DRAFT FIX TRIGGERED for Booking #%d: Setting post_date=%s, post_date_gmt=%s',
                $this->getId(),
                $now,
                $now_gmt
            ));
            
            // Log the transition for debugging
            if (method_exists('SLN_Plugin', 'addLog')) {
                SLN_Plugin::addLog(sprintf(
                    'setStatus: Transitioning post #%d from auto-draft to %s (setting post_date=%s)',
                    $this->getId(),
                    $status,
                    $now
                ));
            }
        }
        
        $result = wp_update_post($post);
        
        // CRITICAL: If wp_update_post fails outright, use direct database update as fallback
        if (is_wp_error($result) || $result === 0) {
            if (method_exists('SLN_Plugin', 'addLog')) {
                $error_msg = is_wp_error($result) ? $result->get_error_message() : 'wp_update_post returned 0';
                SLN_Plugin::addLog(sprintf(
                    'setStatus ERROR: wp_update_post failed for post #%d from %s to %s: %s. Attempting direct DB update...',
                    $this->getId(),
                    $currentStatus,
                    $status,
                    $error_msg
                ));
            }
            $this->setStatusDirectDb($currentStatus, $status);
        } else {
            // wp_update_post returned "success" but a wp_insert_post_data filter may have
            // silently overridden the status. Verify the status was actually written to the DB.
            clean_post_cache($this->getId());
            $savedStatus = get_post_status($this->getId());

            if ($savedStatus !== $status) {
                // Before treating this as a "silent failure" caused by a third-party plugin,
                // check whether our OWN wp_insert_post_data filter in Metabox/Abstract.php
                // intentionally blocked the change to protect a PAID/CONFIRMED booking from
                // reverting to draft.  In that case the filter is working correctly — we must
                // NOT bypass it with a direct DB write; doing so would defeat the protection.
                $isOwnFilterProtection = (
                    in_array($savedStatus, ['sln-b-paid', 'sln-b-confirmed']) &&
                    in_array($status, ['auto-draft', 'draft'])
                );

                if ($isOwnFilterProtection) {
                    // The filter correctly held the paid/confirmed status.
                    // Align the in-memory status with the actual DB value so the caller
                    // does not see a stale object state.
                    $status = $savedStatus;
                    SLN_Plugin::addLog(sprintf(
                        'setStatus: wp_insert_post_data filter correctly protected booking #%d — reversion from %s to %s was blocked. In-memory status aligned to DB (%s).',
                        $this->getId(),
                        $savedStatus,
                        $this->object->post_status,
                        $savedStatus
                    ));
                } else {
                    // Genuine silent failure from a third-party filter (security plugin, SEO,
                    // caching layer). Force the intended status directly to the DB.
                    SLN_Plugin::addLog(sprintf(
                        'setStatus SILENT FAILURE detected for post #%d: wp_update_post returned success but DB has "%s" instead of "%s". Using direct DB update...',
                        $this->getId(),
                        $savedStatus,
                        $status
                    ));
                    $this->setStatusDirectDb($currentStatus, $status);
                }
            } else {
                SLN_Plugin::addLog(sprintf(
                    'setStatus: wp_update_post VERIFIED for post #%d - DB status is now "%s"',
                    $this->getId(),
                    $savedStatus
                ));
            }
        }
        
        $this->object->post_status = $status;

        return $this;
    }

    /**
     * Writes post_status directly to the database, bypassing wp_update_post and all filters.
     * Used as a fallback when wp_update_post fails or is silently overridden by a third-party
     * wp_insert_post_data filter (e.g. security plugins, caching layers, SEO plugins).
     */
    private function setStatusDirectDb($currentStatus, $status)
    {
        global $wpdb;

        $updateData   = array('post_status' => $status);
        $updateFormat = array('%s');

        // When transitioning from auto-draft, also set post dates so WordPress
        // treats the post as a real published record instead of an unsaved draft.
        if ($currentStatus === 'auto-draft' && $status !== 'auto-draft') {
            $updateData['post_date']     = current_time('mysql');
            $updateData['post_date_gmt'] = current_time('mysql', true);
            $updateFormat[]              = '%s';
            $updateFormat[]              = '%s';
        }

        $directResult = $wpdb->update(
            $wpdb->posts,
            $updateData,
            array('ID' => $this->getId()),
            $updateFormat,
            array('%d')
        );

        if ($directResult !== false) {
            clean_post_cache($this->getId());
            $this->reload();
            SLN_Plugin::addLog(sprintf(
                'setStatus: Direct DB update SUCCEEDED for post #%d from %s to %s',
                $this->getId(),
                $currentStatus,
                $status
            ));
        } else {
            SLN_Plugin::addLog(sprintf(
                'setStatus CRITICAL ERROR: Direct DB update also FAILED for post #%d from %s to %s. DB Error: %s',
                $this->getId(),
                $currentStatus,
                $status,
                $wpdb->last_error
            ));
        }
    }

    public function getTitle()
    {
        $object = $this->isMultilingual()  ? $this->translationObject : $this->object;
        if ($object) {
            if (strpos($object->post_title, '&lt') !== false || strpos($object->post_title, '&gt') !== false) {
                // fix XSS when js on attribute 'onerror' or similar on page attendant
                $object->post_title = str_replace('&lt', '&amp;lt', $object->post_title);
                $object->post_title = str_replace('&gt', '&amp;gt', $object->post_title);
            }
            return esc_html($object->post_title);
        }
    }

    public function getPostDate()
    {
        if ($this->object) {
            return SLN_TimeFunc::getPostDateTime($this->object);
        }
    }

    public function getExcerpt()
    {
        $object = $this->isMultilingual()  ? $this->translationObject : $this->object;
        if ($object) {
            return $object->post_excerpt;
        }
    }

    public function getTerms($taxonomy, $field)
    {
        $terms = get_the_terms($this->getId(), $taxonomy);
        $terms_names = wp_list_pluck($terms, $field);
        return $terms_names;
    }
}
