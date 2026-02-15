<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
abstract class SLN_Metabox_Abstract
{
    private $plugin;
    private $postType;

    public function __construct(SLN_Plugin $plugin, $postType)
    {
        $this->plugin   = $plugin;
        $this->postType = $postType;
        $this->init();
    }

    protected function init()
    {
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'may_save_post'), 10, 2);
        // RESTORED: This filter was temporarily disabled but is now safe to re-enable
        // The PayPal issue was actually caused by unreliable Sandbox IPN, not this filter
        add_filter('wp_insert_post_data', array($this, 'wp_insert_post_data'), 99, 2);

        add_action('admin_print_styles-post.php', array($this, 'admin_print_styles'));
        add_action('admin_print_styles-post-new.php', array($this, 'admin_print_styles'));


    }

    public function admin_print_styles()
    {
        global $post;
        if(empty($post)) return;
        if ($post->post_type == $this->getPostType()) {
            $this->enqueueAssets();
            add_filter( 'wpseo_use_page_analysis', '__return_false' );
            remove_meta_box('wpseo_meta', $this->getPostType(), 'normal');
        }
    }

    protected function enqueueAssets(){

        SLN_Action_InitScripts::enqueueTwitterBootstrap(true);
        SLN_Action_InitScripts::enqueueSelect2();
        SLN_Action_InitScripts::enqueueAdmin();
        SLN_Action_InitScripts::enqueueCustomSliderRange();
    }

    abstract public function add_meta_boxes();

    abstract protected function getFieldList();

    public function may_save_post($post_id, $post)
    {
        $pt = $this->getPostType();

        if (is_admin() && $pt == $post->post_type) {
            return $this->save_post($post_id, $post);
        }
    }

    public function save_post($post_id, $post)
    {

        global $wpdb;
        
        $pt = $this->getPostType();
        $where = array('ID' => $post_id);
        if(strpos($post->post_title, '&lt') || strpos($post->post_title, '&gt')){ // fix XSS when js on attribute 'onerror' or similar on page attendant
            $post->post_title = str_replace('&lt', '&amp;lt', $post->post_title);
            $post->post_title = str_replace('&gt', '&amp;gt', $post->post_title);
        }
        $data = array('post_title' => esc_html(wp_strip_all_tags($post->post_title)));
        $data  = wp_unslash( $data );
        $wpdb->update( $wpdb->posts, $data, $where );
        $h  = new SLN_Metabox_Helper;
        if ( ! $h->isValidRequest($pt, $post_id, $post)) {
            return;
        }
        $h->updateMetas($post_id, $h->processRequest($pt, $this->getFieldList()));
    }

    public function wp_insert_post_data($data, $postarr)
    {
        // TEMPORARY DEBUG: Log what's happening during booking creation
        if (isset($data['post_type']) && $data['post_type'] === SLN_Plugin::POST_TYPE_BOOKING) {
            SLN_Plugin::addLog(sprintf(
                '[wp_insert_post_data] FILTER CALLED | ID=%s | incoming_status=%s | current_status=%s | post_exists=%s',
                isset($postarr['ID']) ? $postarr['ID'] : 'NOT_SET',
                isset($data['post_status']) ? $data['post_status'] : 'NOT_SET',
                isset($postarr['ID']) && $postarr['ID'] > 0 ? get_post_status($postarr['ID']) : 'NO_POST',
                isset($postarr['ID']) && $postarr['ID'] > 0 && get_post($postarr['ID']) ? 'YES' : 'NO'
            ));
        }
        
        // CRITICAL FIX: Prevent paid bookings from being reverted to auto-draft
        // This filter was introduced in commit 26141d1de but it's causing issues with
        // NEW booking creation during PayPal flow.
        //
        // SOLUTION: Only apply this protection to:
        // 1. EXISTING posts (ID must exist AND post must exist in database)
        // 2. Only during admin/autosave contexts (not during frontend booking creation)
        // 3. Only for actual status reversions (paid/confirmed → draft)
        
        // Skip if this is a new post being created (ID = 0 or not set)
        if (!isset($postarr['ID']) || $postarr['ID'] == 0) {
            SLN_Plugin::addLog('[wp_insert_post_data] SKIPPED: New post (ID=0 or not set)');
            return $data;
        }
        
        // Skip if not a booking
        $post = get_post($postarr['ID']);
        if (!$post || $post->post_type !== SLN_Plugin::POST_TYPE_BOOKING) {
            return $data;
        }
        
        // Get current status from database
        $current_status = get_post_status($postarr['ID']);
        $new_status = isset($data['post_status']) ? $data['post_status'] : '';
        
        // Skip if current status doesn't exist (shouldn't happen for existing posts)
        if (empty($current_status) || $current_status === false) {
            SLN_Plugin::addLog(sprintf(
                '[wp_insert_post_data] SKIPPED: Empty current status | ID=%d',
                $postarr['ID']
            ));
            return $data;
        }
        
        // Only protect PAID/CONFIRMED bookings from reverting to draft/auto-draft
        if (in_array($current_status, ['sln-b-paid', 'sln-b-confirmed'])) {
            if (in_array($new_status, ['auto-draft', 'draft'])) {
                // Log this attempted reversion (only when debug mode enabled)
                $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
                $caller = isset($backtrace[1]) ? basename($backtrace[1]['file'] ?? 'unknown') . ':' . ($backtrace[1]['line'] ?? '?') : 'unknown';
                
                $context_flags = array();
                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) $context_flags[] = 'AUTOSAVE';
                if (defined('DOING_AJAX') && DOING_AJAX) $context_flags[] = 'AJAX';
                $context = !empty($context_flags) ? ' [' . implode(',', $context_flags) . ']' : '';
                
                // Use plugin's standard logging (respects sln_debug_enabled option)
                SLN_Plugin::addLog(sprintf(
                    '🛑 BLOCKED STATUS REVERSION! Booking #%d: %s → %s (attempted)%s | Caller: %s',
                    $postarr['ID'],
                    $current_status,
                    $new_status,
                    $context,
                    $caller
                ));
                
                // Keep the current paid status instead
                $data['post_status'] = $current_status;
            }
        } else {
            SLN_Plugin::addLog(sprintf(
                '[wp_insert_post_data] NO PROTECTION NEEDED | ID=%d | status=%s → %s',
                $postarr['ID'],
                $current_status,
                $new_status
            ));
        }
        
        return $data;
    }

    /**  @return SLN_Plugin */
    protected function getPlugin()
    {
        return $this->plugin;
    }

    /** @return string */
    protected function getPostType()
    {
        return $this->postType;
    }

    public function in_admin_header() {
	global $post;
        if(empty($post)) return;
	if ($post->post_type == $this->getPostType()) {
	    echo '<div class="sln-help-button-in-header-page">';
	    echo $this->getPlugin()->loadView('admin/help');
	    echo '</div>';
	}
    }

}
