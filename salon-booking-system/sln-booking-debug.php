<?php
/**
 * Salon Booking System - Booking Debug Tool
 * 
 * Upload this file to the WordPress root directory and access it via browser.
 * IMPORTANT: Delete this file after debugging is complete!
 * 
 * Usage: https://yoursite.com/sln-booking-debug.php
 */

// Load WordPress
require_once dirname(__FILE__) . '/wp-load.php';

// Security: Require admin login
if (!current_user_can('manage_options')) {
    wp_die('Access denied. You must be logged in as an administrator to use this tool.');
}

// Security: Add nonce verification for form submissions
$nonce_action = 'sln_booking_debug_action';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salon Booking Debug Tool</title>
    <style>
        :root {
            --bg-dark: #1a1a2e;
            --bg-card: #16213e;
            --accent: #e94560;
            --accent-light: #ff6b6b;
            --text: #eaeaea;
            --text-muted: #a0a0a0;
            --success: #00d26a;
            --warning: #ffc107;
            --danger: #e94560;
            --info: #0dcaf0;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, var(--bg-dark) 0%, #0f0f23 100%);
            color: var(--text);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        h1 {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(90deg, var(--accent), var(--accent-light));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            color: var(--text-muted);
            margin-bottom: 2rem;
        }
        
        .warning-banner {
            background: linear-gradient(90deg, rgba(233, 69, 96, 0.2), rgba(255, 107, 107, 0.1));
            border-left: 4px solid var(--danger);
            padding: 1rem 1.5rem;
            border-radius: 0 8px 8px 0;
            margin-bottom: 2rem;
        }
        
        .warning-banner strong { color: var(--danger); }
        
        .search-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .search-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .form-group {
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid transparent;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        input:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(255, 255, 255, 0.08);
        }
        
        button {
            padding: 0.75rem 2rem;
            background: linear-gradient(90deg, var(--accent), var(--accent-light));
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            align-self: flex-end;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(233, 69, 96, 0.4);
        }
        
        .results-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
        }
        
        .results-card h2 {
            font-size: 1.25rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-paid, .status-confirmed { background: rgba(0, 210, 106, 0.2); color: var(--success); }
        .status-pending, .status-paylater, .status-pendingpayment { background: rgba(255, 193, 7, 0.2); color: var(--warning); }
        .status-canceled, .status-error { background: rgba(233, 69, 96, 0.2); color: var(--danger); }
        .status-trash { background: rgba(128, 128, 128, 0.2); color: #888; }
        .status-draft { background: rgba(13, 202, 240, 0.2); color: var(--info); }
        
        .data-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .data-item {
            background: rgba(255, 255, 255, 0.03);
            padding: 1rem;
            border-radius: 8px;
        }
        
        .data-item .label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }
        
        .data-item .value {
            font-size: 1rem;
            word-break: break-all;
        }
        
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .meta-table th, .meta-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .meta-table th {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .meta-table tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }
        
        .meta-key { color: var(--accent-light); font-family: monospace; }
        .meta-value { color: var(--text); font-family: monospace; font-size: 0.875rem; }
        
        .no-results {
            text-align: center;
            padding: 3rem;
            color: var(--text-muted);
        }
        
        .no-results .icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .btn-small:hover {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: none;
            transform: none;
        }
        
        .log-output {
            background: #0d0d1a;
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 0.8rem;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .highlight { background: rgba(233, 69, 96, 0.3); padding: 0 4px; border-radius: 3px; }
        
        .section-divider {
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            margin: 2rem 0;
        }
        
        .quick-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .quick-action-btn {
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            color: var(--text);
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .quick-action-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--accent);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Salon Booking Debug Tool</h1>
        <p class="subtitle">Investigate missing or problematic bookings</p>
        
        <div class="warning-banner">
            <strong>⚠️ Security Notice:</strong> Delete this file immediately after debugging is complete!
        </div>
        
        <div class="search-card">
            <form method="post" class="search-form">
                <?php wp_nonce_field($nonce_action, 'sln_debug_nonce'); ?>
                <div class="form-group">
                    <label for="booking_id">Booking ID</label>
                    <input type="number" id="booking_id" name="booking_id" 
                           placeholder="e.g., 12345"
                           value="<?php echo isset($_POST['booking_id']) ? intval($_POST['booking_id']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="customer_email">Or Customer Email</label>
                    <input type="text" id="customer_email" name="customer_email" 
                           placeholder="e.g., customer@example.com"
                           value="<?php echo isset($_POST['customer_email']) ? esc_attr(sanitize_email($_POST['customer_email'])) : ''; ?>">
                </div>
                <button type="submit" name="search">Search Booking</button>
            </form>
        </div>
        
        <div class="quick-actions">
            <form method="post" style="display:inline;">
                <?php wp_nonce_field($nonce_action, 'sln_debug_nonce'); ?>
                <button type="submit" name="show_recent_trash" class="quick-action-btn">🗑️ Show Recent Trashed Bookings</button>
            </form>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field($nonce_action, 'sln_debug_nonce'); ?>
                <button type="submit" name="show_drafts" class="quick-action-btn">📝 Show Draft Bookings</button>
            </form>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field($nonce_action, 'sln_debug_nonce'); ?>
                <button type="submit" name="show_errors" class="quick-action-btn">❌ Show Error Bookings</button>
            </form>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field($nonce_action, 'sln_debug_nonce'); ?>
                <button type="submit" name="show_pending_payment" class="quick-action-btn">⏳ Show Pending Payment</button>
            </form>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field($nonce_action, 'sln_debug_nonce'); ?>
                <button type="submit" name="show_log" class="quick-action-btn">📋 Show Plugin Log</button>
            </form>
            <form method="post" style="display:inline;">
                <?php wp_nonce_field($nonce_action, 'sln_debug_nonce'); ?>
                <button type="submit" name="check_statuses" class="quick-action-btn">📊 Booking Status Summary</button>
            </form>
        </div>

<?php

// Verify nonce for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['sln_debug_nonce']) || !wp_verify_nonce($_POST['sln_debug_nonce'], $nonce_action)) {
        wp_die('Security check failed. Please try again.');
    }
}

/**
 * Helper function to get status class
 */
function get_status_class($status) {
    $status = str_replace(['sln-b-', '-'], '', $status);
    return 'status-' . $status;
}

/**
 * Helper function to display booking details
 */
function display_booking_details($post, $show_meta = true) {
    global $wpdb;
    
    $status_labels = [
        'sln-b-pendingpayment' => 'Pending Payment',
        'sln-b-pending' => 'Pending',
        'sln-b-paid' => 'Paid',
        'sln-b-paylater' => 'Pay Later',
        'sln-b-confirmed' => 'Confirmed',
        'sln-b-canceled' => 'Canceled',
        'sln-b-error' => 'Error',
        'trash' => 'Trashed',
        'auto-draft' => 'Draft',
        'draft' => 'Draft',
    ];
    
    $status_label = isset($status_labels[$post->post_status]) ? $status_labels[$post->post_status] : $post->post_status;
    $status_class = get_status_class($post->post_status);
    
    // Get all booking meta
    $meta = get_post_meta($post->ID);
    
    // Extract key booking info
    $booking_date = isset($meta['_sln_booking_date'][0]) ? $meta['_sln_booking_date'][0] : 'N/A';
    $booking_time = isset($meta['_sln_booking_time'][0]) ? $meta['_sln_booking_time'][0] : 'N/A';
    $firstname = isset($meta['_sln_booking_firstname'][0]) ? $meta['_sln_booking_firstname'][0] : 'N/A';
    $lastname = isset($meta['_sln_booking_lastname'][0]) ? $meta['_sln_booking_lastname'][0] : 'N/A';
    $email = isset($meta['_sln_booking_email'][0]) ? $meta['_sln_booking_email'][0] : 'N/A';
    $phone = isset($meta['_sln_booking_phone'][0]) ? $meta['_sln_booking_phone'][0] : 'N/A';
    $amount = isset($meta['_sln_booking_amount'][0]) ? $meta['_sln_booking_amount'][0] : 'N/A';
    $deposit = isset($meta['_sln_booking_deposit'][0]) ? $meta['_sln_booking_deposit'][0] : '0';
    $transaction_id = isset($meta['_sln_booking_transaction_id'][0]) ? $meta['_sln_booking_transaction_id'][0] : 'None';
    $stripe_session = isset($meta['_sln_booking_stripe_session_id'][0]) ? $meta['_sln_booking_stripe_session_id'][0] : 'None';
    $paypal_token = isset($meta['_sln_booking_paypal_token'][0]) ? $meta['_sln_booking_paypal_token'][0] : 'None';
    
    ?>
    <div class="results-card">
        <h2>
            Booking #<?php echo esc_html($post->ID); ?> 
            <span class="status-badge <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span>
        </h2>
        
        <div class="data-grid">
            <div class="data-item">
                <div class="label">Post Title</div>
                <div class="value"><?php echo esc_html($post->post_title); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Customer Name</div>
                <div class="value"><?php echo esc_html($firstname . ' ' . $lastname); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Email</div>
                <div class="value"><?php echo esc_html($email); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Phone</div>
                <div class="value"><?php echo esc_html($phone); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Booking Date/Time</div>
                <div class="value"><?php echo esc_html($booking_date . ' @ ' . $booking_time); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Amount</div>
                <div class="value"><?php echo esc_html($amount); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Deposit</div>
                <div class="value"><?php echo esc_html($deposit); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Transaction ID</div>
                <div class="value"><?php 
                    $trans = maybe_unserialize($transaction_id);
                    echo esc_html(is_array($trans) ? implode(', ', $trans) : $transaction_id); 
                ?></div>
            </div>
            <div class="data-item">
                <div class="label">Stripe Session</div>
                <div class="value"><?php echo esc_html($stripe_session); ?></div>
            </div>
            <div class="data-item">
                <div class="label">PayPal Token</div>
                <div class="value"><?php echo esc_html($paypal_token); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Post Status (Raw)</div>
                <div class="value"><code><?php echo esc_html($post->post_status); ?></code></div>
            </div>
            <div class="data-item">
                <div class="label">Created</div>
                <div class="value"><?php echo esc_html($post->post_date); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Modified</div>
                <div class="value"><?php echo esc_html($post->post_modified); ?></div>
            </div>
            <div class="data-item">
                <div class="label">Author (User ID)</div>
                <div class="value"><?php echo esc_html($post->post_author); ?></div>
            </div>
        </div>
        
        <?php if ($post->post_status === 'trash'): ?>
        <div class="action-buttons">
            <a href="<?php echo admin_url('post.php?action=untrash&post=' . $post->ID . '&_wpnonce=' . wp_create_nonce('untrash-post_' . $post->ID)); ?>" 
               class="btn-small" style="text-decoration:none;">
                ♻️ Restore from Trash
            </a>
            <a href="<?php echo admin_url('edit.php?post_type=sln_booking&post_status=trash'); ?>" 
               class="btn-small" style="text-decoration:none;">
                🗑️ View All Trash
            </a>
        </div>
        <?php endif; ?>
        
        <?php 
        // Check if this booking has payment data but wrong status (stuck booking)
        $has_transaction = !empty($meta['_sln_booking_transaction_id'][0]) || 
                          !empty(array_filter(array_keys($meta), function($k) { return strpos($k, '_sln_paypal_ipn_') === 0; }));
        $is_stuck = in_array($post->post_status, ['auto-draft', 'draft', 'sln-b-pendingpayment', 'sln-b-error']) && $has_transaction;
        ?>
        
        <?php if ($is_stuck): ?>
        <div style="background: rgba(255, 193, 7, 0.15); border: 1px solid var(--warning); border-radius: 8px; padding: 1rem; margin-top: 1rem;">
            <strong style="color: var(--warning);">⚠️ This booking appears to be STUCK!</strong>
            <p style="margin: 0.5rem 0; color: var(--text-muted);">
                Payment data exists but status is "<?php echo esc_html($post->post_status); ?>". 
                This usually means the payment webhook failed to update the status.
            </p>
            <div class="action-buttons" style="margin-top: 0.75rem;">
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('sln_booking_debug_action', 'sln_debug_nonce'); ?>
                    <input type="hidden" name="fix_booking_id" value="<?php echo esc_attr($post->ID); ?>">
                    <input type="hidden" name="new_status" value="sln-b-paid">
                    <button type="submit" name="fix_status" class="btn-small" style="background: var(--success);">
                        ✅ Fix: Set to PAID
                    </button>
                </form>
                <form method="post" style="display:inline;">
                    <?php wp_nonce_field('sln_booking_debug_action', 'sln_debug_nonce'); ?>
                    <input type="hidden" name="fix_booking_id" value="<?php echo esc_attr($post->ID); ?>">
                    <input type="hidden" name="new_status" value="sln-b-confirmed">
                    <button type="submit" name="fix_status" class="btn-small" style="background: var(--info);">
                        ✅ Fix: Set to CONFIRMED
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="<?php echo admin_url('post.php?post=' . $post->ID . '&action=edit'); ?>" 
               class="btn-small" style="text-decoration:none;">
                ✏️ Edit Booking
            </a>
            <?php if ($post->post_status !== 'trash'): ?>
            <a href="<?php echo admin_url('edit.php?post_type=sln_booking'); ?>" 
               class="btn-small" style="text-decoration:none;">
                📋 View All Bookings
            </a>
            <?php endif; ?>
        </div>
        
        <?php if ($show_meta): ?>
        <div class="section-divider"></div>
        <h3 style="margin-bottom: 1rem;">All Meta Data</h3>
        <div style="overflow-x: auto;">
            <table class="meta-table">
                <thead>
                    <tr>
                        <th>Meta Key</th>
                        <th>Meta Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($meta as $key => $values): ?>
                    <?php if (strpos($key, '_sln_') === 0 || strpos($key, 'sln_') === 0): ?>
                    <tr>
                        <td class="meta-key"><?php echo esc_html($key); ?></td>
                        <td class="meta-value">
                            <?php 
                            $value = $values[0];
                            $unserialized = maybe_unserialize($value);
                            if (is_array($unserialized) || is_object($unserialized)) {
                                echo '<pre style="margin:0;white-space:pre-wrap;">' . esc_html(print_r($unserialized, true)) . '</pre>';
                            } else {
                                echo esc_html($value);
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// Handle fix status action
if (isset($_POST['fix_status']) && isset($_POST['fix_booking_id']) && isset($_POST['new_status'])) {
    $fix_booking_id = intval($_POST['fix_booking_id']);
    $new_status = sanitize_text_field($_POST['new_status']);
    
    // Validate status
    $allowed_statuses = ['sln-b-paid', 'sln-b-confirmed', 'sln-b-pending', 'sln-b-paylater'];
    
    if (in_array($new_status, $allowed_statuses) && $fix_booking_id > 0) {
        $post = get_post($fix_booking_id);
        
        if ($post && $post->post_type === 'sln_booking') {
            $old_status = $post->post_status;
            
            // Update the post status
            wp_update_post([
                'ID' => $fix_booking_id,
                'post_status' => $new_status
            ]);
            
            // Clear any caches
            clean_post_cache($fix_booking_id);
            
            echo '<div class="results-card" style="background: rgba(0, 210, 106, 0.15); border: 2px solid var(--success);">';
            echo '<h2 style="color: var(--success);">✅ Booking Status Fixed!</h2>';
            echo '<p>Booking #' . esc_html($fix_booking_id) . ' status changed:</p>';
            echo '<p><code>' . esc_html($old_status) . '</code> → <code style="color: var(--success);">' . esc_html($new_status) . '</code></p>';
            echo '<p style="margin-top: 1rem;"><a href="' . admin_url('edit.php?post_type=sln_booking') . '" style="color: var(--accent-light);">→ View in Bookings List</a></p>';
            echo '</div>';
            
            // Refresh and display the updated booking
            $post = get_post($fix_booking_id);
            display_booking_details($post);
        } else {
            echo '<div class="results-card" style="background: rgba(233, 69, 96, 0.1); border: 1px solid var(--danger);">';
            echo '<h2 style="color: var(--danger);">❌ Error</h2>';
            echo '<p>Could not find booking with ID: ' . esc_html($fix_booking_id) . '</p>';
            echo '</div>';
        }
    } else {
        echo '<div class="results-card" style="background: rgba(233, 69, 96, 0.1); border: 1px solid var(--danger);">';
        echo '<h2 style="color: var(--danger);">❌ Invalid Status</h2>';
        echo '<p>The requested status is not allowed.</p>';
        echo '</div>';
    }
}

// Handle search
if (isset($_POST['search'])) {
    $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
    
    $found = false;
    
    // Search by ID first
    if ($booking_id > 0) {
        // Get post regardless of status (including trash)
        $post = get_post($booking_id);
        
        if ($post && $post->post_type === 'sln_booking') {
            $found = true;
            echo '<div class="results-card" style="background: rgba(0, 210, 106, 0.1); border: 1px solid var(--success);">';
            echo '<h2 style="color: var(--success);">✅ Booking Found by ID</h2>';
            echo '</div>';
            display_booking_details($post);
        } else if ($post) {
            echo '<div class="results-card" style="background: rgba(255, 193, 7, 0.1); border: 1px solid var(--warning);">';
            echo '<h2 style="color: var(--warning);">⚠️ Post Found but NOT a Booking</h2>';
            echo '<p>Post ID ' . esc_html($booking_id) . ' exists but is of type: <code>' . esc_html($post->post_type) . '</code></p>';
            echo '</div>';
        } else {
            echo '<div class="results-card" style="background: rgba(233, 69, 96, 0.1); border: 1px solid var(--danger);">';
            echo '<h2 style="color: var(--danger);">❌ Booking ID Not Found</h2>';
            echo '<p>No post exists with ID: ' . esc_html($booking_id) . '</p>';
            echo '<p>This could mean:</p>';
            echo '<ul style="margin-left: 1.5rem; margin-top: 0.5rem;">';
            echo '<li>The booking was permanently deleted</li>';
            echo '<li>The booking was never created (payment failed before creation)</li>';
            echo '<li>The ID is from a different website/database</li>';
            echo '</ul>';
            echo '</div>';
        }
    }
    
    // Search by email
    if (!empty($customer_email)) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare("
            SELECT DISTINCT p.* 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE pm.meta_key = '_sln_booking_email' 
            AND pm.meta_value = %s
            AND p.post_type = 'sln_booking'
            ORDER BY p.post_date DESC
            LIMIT 50
        ", $customer_email));
        
        if ($results) {
            $found = true;
            echo '<div class="results-card" style="background: rgba(0, 210, 106, 0.1); border: 1px solid var(--success);">';
            echo '<h2 style="color: var(--success);">✅ Found ' . count($results) . ' Booking(s) for Email: ' . esc_html($customer_email) . '</h2>';
            echo '</div>';
            
            foreach ($results as $post) {
                display_booking_details($post, count($results) === 1);
            }
        } else if (!$found) {
            echo '<div class="results-card" style="background: rgba(233, 69, 96, 0.1); border: 1px solid var(--danger);">';
            echo '<h2 style="color: var(--danger);">❌ No Bookings Found for Email</h2>';
            echo '<p>No bookings found with email: ' . esc_html($customer_email) . '</p>';
            echo '</div>';
        }
    }
    
    if (!$booking_id && empty($customer_email)) {
        echo '<div class="results-card">';
        echo '<div class="no-results">';
        echo '<div class="icon">🔍</div>';
        echo '<p>Please enter a Booking ID or Customer Email to search</p>';
        echo '</div>';
        echo '</div>';
    }
}

// Show recent trashed bookings
if (isset($_POST['show_recent_trash'])) {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT * FROM {$wpdb->posts} 
        WHERE post_type = 'sln_booking' 
        AND post_status = 'trash'
        ORDER BY post_modified DESC
        LIMIT 20
    ");
    
    echo '<div class="results-card">';
    echo '<h2>🗑️ Recent Trashed Bookings (' . count($results) . ')</h2>';
    
    if ($results) {
        echo '<div style="overflow-x: auto;"><table class="meta-table">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Email</th><th>Date</th><th>Trashed</th><th>Action</th></tr></thead>';
        echo '<tbody>';
        foreach ($results as $post) {
            $email = get_post_meta($post->ID, '_sln_booking_email', true);
            $date = get_post_meta($post->ID, '_sln_booking_date', true);
            echo '<tr>';
            echo '<td>' . esc_html($post->ID) . '</td>';
            echo '<td>' . esc_html($post->post_title) . '</td>';
            echo '<td>' . esc_html($email) . '</td>';
            echo '<td>' . esc_html($date) . '</td>';
            echo '<td>' . esc_html($post->post_modified) . '</td>';
            echo '<td><a href="' . admin_url('post.php?action=untrash&post=' . $post->ID . '&_wpnonce=' . wp_create_nonce('untrash-post_' . $post->ID)) . '">Restore</a></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p style="color: var(--text-muted);">No trashed bookings found.</p>';
    }
    echo '</div>';
}

// Show draft bookings
if (isset($_POST['show_drafts'])) {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT * FROM {$wpdb->posts} 
        WHERE post_type = 'sln_booking' 
        AND (post_status = 'auto-draft' OR post_status = 'draft')
        ORDER BY post_date DESC
        LIMIT 20
    ");
    
    echo '<div class="results-card">';
    echo '<h2>📝 Draft Bookings (' . count($results) . ')</h2>';
    echo '<p style="color: var(--text-muted); margin-bottom: 1rem;">These are incomplete bookings (payment may have been interrupted)</p>';
    
    if ($results) {
        echo '<div style="overflow-x: auto;"><table class="meta-table">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Email</th><th>Created</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        foreach ($results as $post) {
            $email = get_post_meta($post->ID, '_sln_booking_email', true);
            echo '<tr>';
            echo '<td>' . esc_html($post->ID) . '</td>';
            echo '<td>' . esc_html($post->post_title) . '</td>';
            echo '<td>' . esc_html($email ?: 'N/A') . '</td>';
            echo '<td>' . esc_html($post->post_date) . '</td>';
            echo '<td><code>' . esc_html($post->post_status) . '</code></td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p style="color: var(--text-muted);">No draft bookings found.</p>';
    }
    echo '</div>';
}

// Show error bookings
if (isset($_POST['show_errors'])) {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT * FROM {$wpdb->posts} 
        WHERE post_type = 'sln_booking' 
        AND post_status = 'sln-b-error'
        ORDER BY post_date DESC
        LIMIT 20
    ");
    
    echo '<div class="results-card">';
    echo '<h2>❌ Error Bookings (' . count($results) . ')</h2>';
    
    if ($results) {
        foreach ($results as $post) {
            display_booking_details($post, false);
        }
    } else {
        echo '<p style="color: var(--success);">✅ No error bookings found.</p>';
    }
    echo '</div>';
}

// Show pending payment bookings
if (isset($_POST['show_pending_payment'])) {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT * FROM {$wpdb->posts} 
        WHERE post_type = 'sln_booking' 
        AND post_status = 'sln-b-pendingpayment'
        ORDER BY post_date DESC
        LIMIT 20
    ");
    
    echo '<div class="results-card">';
    echo '<h2>⏳ Pending Payment Bookings (' . count($results) . ')</h2>';
    echo '<p style="color: var(--text-muted); margin-bottom: 1rem;">These bookings are waiting for payment confirmation</p>';
    
    if ($results) {
        echo '<div style="overflow-x: auto;"><table class="meta-table">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Email</th><th>Amount</th><th>Created</th><th>Stripe Session</th></tr></thead>';
        echo '<tbody>';
        foreach ($results as $post) {
            $email = get_post_meta($post->ID, '_sln_booking_email', true);
            $amount = get_post_meta($post->ID, '_sln_booking_amount', true);
            $stripe = get_post_meta($post->ID, '_sln_booking_stripe_session_id', true);
            echo '<tr>';
            echo '<td>' . esc_html($post->ID) . '</td>';
            echo '<td>' . esc_html($post->post_title) . '</td>';
            echo '<td>' . esc_html($email ?: 'N/A') . '</td>';
            echo '<td>' . esc_html($amount ?: 'N/A') . '</td>';
            echo '<td>' . esc_html($post->post_date) . '</td>';
            echo '<td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;">' . esc_html($stripe ?: 'None') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p style="color: var(--success);">✅ No pending payment bookings found.</p>';
    }
    echo '</div>';
}

// Show plugin log
if (isset($_POST['show_log'])) {
    $log_paths = [
        WP_PLUGIN_DIR . '/developer-starter/log.txt',
        WP_PLUGIN_DIR . '/salon-booking-system/log.txt',
        WP_PLUGIN_DIR . '/developer-starter-developer-starter/log.txt',
    ];
    
    $log_content = null;
    $log_file = null;
    
    foreach ($log_paths as $path) {
        if (file_exists($path)) {
            $log_file = $path;
            // Read last 200 lines
            $lines = file($path);
            $last_lines = array_slice($lines, -200);
            $log_content = implode('', $last_lines);
            break;
        }
    }
    
    echo '<div class="results-card">';
    echo '<h2>📋 Plugin Log (Last 200 lines)</h2>';
    
    if ($log_content) {
        echo '<p style="color: var(--text-muted); margin-bottom: 1rem;">File: ' . esc_html($log_file) . '</p>';
        
        // Highlight search terms if provided
        $booking_id = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
        $customer_email = isset($_POST['customer_email']) ? sanitize_email($_POST['customer_email']) : '';
        
        $log_display = esc_html($log_content);
        
        if ($booking_id) {
            $log_display = preg_replace('/(' . preg_quote($booking_id, '/') . ')/', '<span class="highlight">$1</span>', $log_display);
        }
        if ($customer_email) {
            $log_display = preg_replace('/(' . preg_quote($customer_email, '/') . ')/i', '<span class="highlight">$1</span>', $log_display);
        }
        
        echo '<div class="log-output">' . $log_display . '</div>';
    } else {
        echo '<p style="color: var(--warning);">⚠️ Log file not found or empty.</p>';
        echo '<p style="color: var(--text-muted);">Checked paths:</p>';
        echo '<ul style="color: var(--text-muted); margin-left: 1.5rem;">';
        foreach ($log_paths as $path) {
            echo '<li><code>' . esc_html($path) . '</code></li>';
        }
        echo '</ul>';
    }
    echo '</div>';
}

// Booking status summary
if (isset($_POST['check_statuses'])) {
    global $wpdb;
    
    $results = $wpdb->get_results("
        SELECT post_status, COUNT(*) as count 
        FROM {$wpdb->posts} 
        WHERE post_type = 'sln_booking'
        GROUP BY post_status
        ORDER BY count DESC
    ");
    
    // Also get recent bookings
    $recent = $wpdb->get_results("
        SELECT * FROM {$wpdb->posts} 
        WHERE post_type = 'sln_booking'
        ORDER BY post_date DESC
        LIMIT 10
    ");
    
    echo '<div class="results-card">';
    echo '<h2>📊 Booking Status Summary</h2>';
    
    if ($results) {
        echo '<div class="data-grid">';
        $total = 0;
        foreach ($results as $row) {
            $total += $row->count;
            $status_class = get_status_class($row->post_status);
            echo '<div class="data-item">';
            echo '<div class="label">' . esc_html($row->post_status) . '</div>';
            echo '<div class="value"><span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($row->count) . ' bookings</span></div>';
            echo '</div>';
        }
        echo '<div class="data-item">';
        echo '<div class="label">TOTAL</div>';
        echo '<div class="value"><strong>' . esc_html($total) . ' bookings</strong></div>';
        echo '</div>';
        echo '</div>';
    }
    
    echo '<div class="section-divider"></div>';
    echo '<h3 style="margin-bottom: 1rem;">10 Most Recent Bookings</h3>';
    
    if ($recent) {
        echo '<div style="overflow-x: auto;"><table class="meta-table">';
        echo '<thead><tr><th>ID</th><th>Title</th><th>Status</th><th>Created</th></tr></thead>';
        echo '<tbody>';
        foreach ($recent as $post) {
            $status_class = get_status_class($post->post_status);
            echo '<tr>';
            echo '<td>' . esc_html($post->ID) . '</td>';
            echo '<td>' . esc_html($post->post_title) . '</td>';
            echo '<td><span class="status-badge ' . esc_attr($status_class) . '">' . esc_html($post->post_status) . '</span></td>';
            echo '<td>' . esc_html($post->post_date) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    }
    
    echo '</div>';
}

?>

    </div>
    
    <script>
        // Auto-focus on first empty input
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[type="text"], input[type="number"]');
            for (let input of inputs) {
                if (!input.value) {
                    input.focus();
                    break;
                }
            }
        });
    </script>
</body>
</html>

