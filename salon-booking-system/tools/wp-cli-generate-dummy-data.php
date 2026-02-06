<?php
/**
 * WP-CLI Command for Generating Dummy Booking Data
 * 
 * Usage via WP-CLI:
 * wp salon generate-dummy-data --bookings=200 --days=365
 * 
 * @version 1.0.0
 */

if (defined('WP_CLI') && WP_CLI) {
    
    class Salon_Dummy_Data_CLI_Command {
        
        /**
         * Generate dummy booking data for testing Reports Dashboard
         *
         * ## OPTIONS
         *
         * [--bookings=<number>]
         * : Number of bookings to generate
         * ---
         * default: 200
         * ---
         *
         * [--days=<number>]
         * : How many days back to generate data
         * ---
         * default: 365
         * ---
         *
         * [--customers=<number>]
         * : Number of unique customers to create
         * ---
         * default: 50
         * ---
         *
         * [--assistants=<number>]
         * : Number of assistants to create if none exist
         * ---
         * default: 5
         * ---
         *
         * [--clean]
         * : Delete all existing dummy data first
         *
         * ## EXAMPLES
         *
         *     # Generate 200 bookings for the last year
         *     wp salon generate-dummy-data
         *
         *     # Generate 500 bookings for the last 2 years
         *     wp salon generate-dummy-data --bookings=500 --days=730
         *
         *     # Generate 100 bookings for the last month
         *     wp salon generate-dummy-data --bookings=100 --days=30
         *
         *     # Clean existing data and generate new
         *     wp salon generate-dummy-data --clean --bookings=300
         *
         * @when after_wp_load
         */
        public function generate_dummy_data($args, $assoc_args) {
            
            // Check if plugin is active
            if (!class_exists('SLN_Plugin')) {
                WP_CLI::error('Salon Booking System plugin is not active.');
                return;
            }
            
            // Parse arguments
            $bookings = isset($assoc_args['bookings']) ? (int)$assoc_args['bookings'] : 200;
            $days = isset($assoc_args['days']) ? (int)$assoc_args['days'] : 365;
            $customers_count = isset($assoc_args['customers']) ? (int)$assoc_args['customers'] : 50;
            $assistants_count = isset($assoc_args['assistants']) ? (int)$assoc_args['assistants'] : 5;
            $clean = isset($assoc_args['clean']);
            
            WP_CLI::line('');
            WP_CLI::line(WP_CLI::colorize('%G=== SALON BOOKING SYSTEM - DUMMY DATA GENERATOR ===%n'));
            WP_CLI::line('');
            
            // Clean existing data if requested
            if ($clean) {
                WP_CLI::line('Cleaning existing dummy data...');
                $this->clean_dummy_data();
                WP_CLI::success('Existing data cleaned.');
                WP_CLI::line('');
            }
            
            // Display configuration
            WP_CLI::line('Configuration:');
            WP_CLI::line("  Bookings to generate: $bookings");
            WP_CLI::line("  Date range: Last $days days");
            WP_CLI::line("  Customers: $customers_count");
            WP_CLI::line('');
            
            // Load the generator
            require_once __DIR__ . '/generate-dummy-reports-data.php';
            
            $generator = new SLN_DummyDataGenerator();
            
            $config = [
                'num_bookings' => $bookings,
                'days_back' => $days,
                'min_services_per_booking' => 1,
                'max_services_per_booking' => 3,
            ];
            
            // Start progress bar
            $progress = \WP_CLI\Utils\make_progress_bar('Generating bookings', $bookings);
            
            // Capture output
            ob_start();
            $result = $generator->generate($config);
            $output = ob_get_clean();
            
            $progress->finish();
            
            WP_CLI::line('');
            WP_CLI::line($output);
            
            if ($result) {
                WP_CLI::success('Dummy data generation completed!');
                WP_CLI::line('');
                WP_CLI::line('Next steps:');
                WP_CLI::line('  View Reports: ' . admin_url('admin.php?page=salon-reports'));
                WP_CLI::line('  View Bookings: ' . admin_url('edit.php?post_type=sln_booking'));
            } else {
                WP_CLI::error('Data generation failed. Check the output above for details.');
            }
        }
        
        /**
         * Clean dummy booking data
         *
         * ## OPTIONS
         *
         * [--force]
         * : Skip confirmation
         *
         * ## EXAMPLES
         *
         *     wp salon clean-dummy-data
         *     wp salon clean-dummy-data --force
         */
        public function clean_dummy_data($args = [], $assoc_args = []) {
            
            $force = isset($assoc_args['force']);
            
            if (!$force) {
                WP_CLI::confirm('This will delete all bookings with email addresses ending in @example.com. Continue?');
            }
            
            global $wpdb;
            
            // Find all customers with @example.com emails
            $dummy_users = $wpdb->get_col(
                "SELECT ID FROM {$wpdb->users} WHERE user_email LIKE '%@example.com'"
            );
            
            if (empty($dummy_users)) {
                WP_CLI::warning('No dummy customers found.');
                return;
            }
            
            WP_CLI::line("Found " . count($dummy_users) . " dummy customers.");
            
            // Delete bookings for these customers
            $booking_ids = get_posts([
                'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
                'author__in' => $dummy_users,
                'posts_per_page' => -1,
                'fields' => 'ids',
            ]);
            
            if (!empty($booking_ids)) {
                $progress = \WP_CLI\Utils\make_progress_bar('Deleting bookings', count($booking_ids));
                
                foreach ($booking_ids as $booking_id) {
                    wp_delete_post($booking_id, true);
                    $progress->tick();
                }
                
                $progress->finish();
                WP_CLI::success("Deleted " . count($booking_ids) . " bookings.");
            }
            
            // Delete dummy customers
            $progress = \WP_CLI\Utils\make_progress_bar('Deleting customers', count($dummy_users));
            
            foreach ($dummy_users as $user_id) {
                wp_delete_user($user_id);
                $progress->tick();
            }
            
            $progress->finish();
            WP_CLI::success("Deleted " . count($dummy_users) . " customers.");
        }
        
        /**
         * Show statistics about existing booking data
         *
         * ## EXAMPLES
         *
         *     wp salon data-stats
         */
        public function data_stats($args, $assoc_args) {
            
            if (!class_exists('SLN_Plugin')) {
                WP_CLI::error('Salon Booking System plugin is not active.');
                return;
            }
            
            global $wpdb;
            
            WP_CLI::line('');
            WP_CLI::line(WP_CLI::colorize('%G=== BOOKING DATA STATISTICS ===%n'));
            WP_CLI::line('');
            
            // Total bookings
            $total_bookings = wp_count_posts(SLN_Plugin::POST_TYPE_BOOKING);
            WP_CLI::line('Total Bookings: ' . $total_bookings->publish);
            
            // By status
            WP_CLI::line('');
            WP_CLI::line('By Status:');
            foreach ((array)$total_bookings as $status => $count) {
                if ($status !== 'auto-draft') {
                    WP_CLI::line("  $status: $count");
                }
            }
            
            // Date range
            $first_booking = $wpdb->get_var(
                "SELECT meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = '_sln_booking_date' 
                ORDER BY meta_value ASC LIMIT 1"
            );
            
            $last_booking = $wpdb->get_var(
                "SELECT meta_value FROM {$wpdb->postmeta} 
                WHERE meta_key = '_sln_booking_date' 
                ORDER BY meta_value DESC LIMIT 1"
            );
            
            WP_CLI::line('');
            WP_CLI::line('Date Range:');
            WP_CLI::line("  First booking: $first_booking");
            WP_CLI::line("  Last booking: $last_booking");
            
            // Services count
            $services_count = wp_count_posts(SLN_Plugin::POST_TYPE_SERVICE);
            WP_CLI::line('');
            WP_CLI::line('Services: ' . $services_count->publish);
            
            // Assistants count
            $assistants_count = wp_count_posts(SLN_Plugin::POST_TYPE_ATTENDANT);
            WP_CLI::line('Assistants: ' . $assistants_count->publish);
            
            // Customers count
            $customers_count = count_users();
            $customer_role_count = isset($customers_count['avail_roles'][SLN_Plugin::USER_ROLE_CUSTOMER]) 
                ? $customers_count['avail_roles'][SLN_Plugin::USER_ROLE_CUSTOMER] 
                : 0;
            WP_CLI::line('Customers: ' . $customer_role_count);
            
            // Dummy data
            $dummy_customers = $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_email LIKE '%@example.com'"
            );
            WP_CLI::line('');
            WP_CLI::line('Dummy Data:');
            WP_CLI::line("  Dummy customers: $dummy_customers");
            
            WP_CLI::line('');
        }
    }
    
    WP_CLI::add_command('salon', 'Salon_Dummy_Data_CLI_Command');
}


