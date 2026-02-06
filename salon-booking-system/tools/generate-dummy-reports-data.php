<?php
/**
 * Dummy Data Generator for Reports Dashboard Testing
 * 
 * This script generates realistic booking data for testing the new Reports Dashboard
 * 
 * Usage:
 * 1. Copy this file to WordPress root directory OR
 * 2. Run via WP-CLI: wp eval-file generate-dummy-reports-data.php OR
 * 3. Access via browser (ensure you're logged in as admin): /generate-dummy-reports-data.php
 * 
 * @version 1.0.0
 * @date 2025-11-13
 */

// Load WordPress
if (!defined('ABSPATH')) {
    // Try to load WordPress
    $wp_load_paths = [
        __DIR__ . '/../../../wp-load.php',
        __DIR__ . '/../../wp-load.php',
        __DIR__ . '/../wp-load.php',
        __DIR__ . '/wp-load.php',
    ];
    
    $wp_loaded = false;
    foreach ($wp_load_paths as $path) {
        if (file_exists($path)) {
            require_once $path;
            $wp_loaded = true;
            break;
        }
    }
    
    if (!$wp_loaded) {
        die('Could not load WordPress. Please run this script from WordPress root or via WP-CLI.');
    }
}

// Security check - only allow admins
if (!current_user_can('manage_options')) {
    die('Unauthorized. Only administrators can run this script.');
}

// Check if SLN plugin is active
if (!class_exists('SLN_Plugin')) {
    die('Salon Booking System plugin is not active.');
}

/**
 * Dummy Data Generator Class
 */
class SLN_DummyDataGenerator {
    
    private $plugin;
    private $stats = [];
    private $start_time;
    
    // Configuration
    private $config = [
        'num_bookings' => 200,          // Number of bookings to create
        'days_back' => 365,              // How far back to generate data
        'min_services_per_booking' => 1, // Minimum services per booking
        'max_services_per_booking' => 3, // Maximum services per booking
        'cancellation_rate' => 0.05,     // 5% cancellation rate
    ];
    
    // Sample data
    private $customer_first_names = [
        'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
        'William', 'Barbara', 'David', 'Elizabeth', 'Richard', 'Susan', 'Joseph', 'Jessica',
        'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa',
        'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley',
        'Steven', 'Kimberly', 'Paul', 'Emily', 'Andrew', 'Donna', 'Joshua', 'Michelle'
    ];
    
    private $customer_last_names = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson',
        'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson',
        'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker'
    ];
    
    private $booking_hours = [
        '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
        '12:00', '12:30', '13:00', '13:30', '14:00', '14:30',
        '15:00', '15:30', '16:00', '16:30', '17:00', '17:30'
    ];
    
    public function __construct() {
        $this->plugin = SLN_Plugin::getInstance();
        $this->start_time = microtime(true);
    }
    
    /**
     * Main generation method
     */
    public function generate($config = []) {
        $this->config = array_merge($this->config, $config);
        
        $this->log('=== SALON BOOKING SYSTEM - DUMMY DATA GENERATOR ===');
        $this->log('Starting data generation...');
        $this->log('Configuration:', $this->config);
        
        // Get existing data
        $services = $this->getServices();
        $assistants = $this->getAssistants();
        
        if (empty($services)) {
            $this->log('ERROR: No services found. Please create at least one service first.');
            return false;
        }
        
        if (empty($assistants)) {
            $this->log('WARNING: No assistants found. Creating sample assistants...');
            $assistants = $this->createSampleAssistants();
        }
        
        $this->log("Found {count} services", ['count' => count($services)]);
        $this->log("Found {count} assistants", ['count' => count($assistants)]);
        
        // Create customers
        $this->log('Creating customers...');
        $customers = $this->createCustomers(50); // Create 50 unique customers
        $this->log("Created {count} customers", ['count' => count($customers)]);
        
        // Generate bookings
        $this->log('Generating bookings...');
        $bookings_created = $this->generateBookings($customers, $services, $assistants);
        
        // Summary
        $elapsed_time = round(microtime(true) - $this->start_time, 2);
        $this->log('');
        $this->log('=== GENERATION COMPLETE ===');
        $this->log("Total bookings created: {$bookings_created}");
        $this->log("Time elapsed: {$elapsed_time} seconds");
        $this->log('');
        $this->log('Statistics:', $this->stats);
        $this->log('');
        $this->log('You can now test the Reports Dashboard at:');
        $this->log(admin_url('admin.php?page=salon-reports'));
        
        return true;
    }
    
    /**
     * Get all services
     */
    private function getServices() {
        $query = new WP_Query([
            'post_type' => SLN_Plugin::POST_TYPE_SERVICE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);
        
        $services = [];
        foreach ($query->posts as $post) {
            $service = $this->plugin->createService($post->ID);
            $services[] = [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'price' => $service->getPrice(),
                'duration' => $service->getDuration(),
            ];
        }
        
        return $services;
    }
    
    /**
     * Get all assistants
     */
    private function getAssistants() {
        $query = new WP_Query([
            'post_type' => SLN_Plugin::POST_TYPE_ATTENDANT,
            'post_status' => 'publish',
            'posts_per_page' => -1,
        ]);
        
        $assistants = [];
        foreach ($query->posts as $post) {
            $assistants[] = [
                'id' => $post->ID,
                'name' => get_the_title($post->ID),
            ];
        }
        
        return $assistants;
    }
    
    /**
     * Create sample assistants if none exist
     */
    private function createSampleAssistants() {
        $assistant_names = [
            'Emma Johnson',
            'Sophia Williams',
            'Olivia Brown',
            'Ava Davis',
            'Isabella Miller'
        ];
        
        $assistants = [];
        
        foreach ($assistant_names as $name) {
            $post_id = wp_insert_post([
                'post_title' => $name,
                'post_type' => SLN_Plugin::POST_TYPE_ATTENDANT,
                'post_status' => 'publish',
            ]);
            
            if ($post_id) {
                $assistants[] = [
                    'id' => $post_id,
                    'name' => $name,
                ];
            }
        }
        
        return $assistants;
    }
    
    /**
     * Create dummy customers
     */
    private function createCustomers($count) {
        $customers = [];
        
        for ($i = 0; $i < $count; $i++) {
            $first_name = $this->customer_first_names[array_rand($this->customer_first_names)];
            $last_name = $this->customer_last_names[array_rand($this->customer_last_names)];
            $email = strtolower($first_name . '.' . $last_name . $i . '@example.com');
            
            // Check if user exists
            $user = get_user_by('email', $email);
            
            if (!$user) {
                $user_id = wp_create_user(
                    strtolower($first_name . $last_name . $i),
                    wp_generate_password(),
                    $email
                );
                
                if (is_wp_error($user_id)) {
                    continue;
                }
                
                // Update user meta
                wp_update_user([
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => "$first_name $last_name",
                ]);
                
                // Add customer role
                $user = new WP_User($user_id);
                $user->add_role(SLN_Plugin::USER_ROLE_CUSTOMER);
                
                // Add phone number
                update_user_meta($user_id, '_sln_phone', $this->generatePhone());
            } else {
                $user_id = $user->ID;
            }
            
            $customers[] = [
                'id' => $user_id,
                'name' => "$first_name $last_name",
                'email' => $email,
            ];
        }
        
        return $customers;
    }
    
    /**
     * Generate random phone number
     */
    private function generatePhone() {
        return sprintf('(%03d) %03d-%04d', 
            rand(200, 999), 
            rand(200, 999), 
            rand(1000, 9999)
        );
    }
    
    /**
     * Generate bookings
     */
    private function generateBookings($customers, $services, $assistants) {
        $count = 0;
        $statuses = [
            'paid' => 0,
            'confirmed' => 0,
            'pay_later' => 0,
            'pending' => 0,
            'canceled' => 0,
        ];
        
        for ($i = 0; $i < $this->config['num_bookings']; $i++) {
            // Random date within range
            $days_ago = rand(0, $this->config['days_back']);
            $date = new DateTime();
            $date->modify("-{$days_ago} days");
            
            // Skip Sundays (closed day for most salons)
            if ($date->format('w') == 0) {
                continue;
            }
            
            // Random time
            $time = $this->booking_hours[array_rand($this->booking_hours)];
            
            // Random customer (with some customers more frequent than others)
            $customer = $this->weightedRandomCustomer($customers);
            
            // Random services (1-3 services per booking)
            $num_services = rand(
                $this->config['min_services_per_booking'],
                $this->config['max_services_per_booking']
            );
            $selected_services = $this->selectRandomServices($services, $num_services);
            
            // Calculate total price and duration
            $total_price = 0;
            $total_duration_minutes = 0;
            $booking_services = [];
            
            foreach ($selected_services as $service) {
                $assistant = $assistants[array_rand($assistants)];
                
                $total_price += $service['price'];
                
                // Extract minutes from duration
                if ($service['duration']) {
                    $duration_parts = explode(':', $service['duration']);
                    $hours = isset($duration_parts[0]) ? (int)$duration_parts[0] : 0;
                    $minutes = isset($duration_parts[1]) ? (int)$duration_parts[1] : 0;
                    $total_duration_minutes += ($hours * 60 + $minutes);
                }
                
                $booking_services[] = [
                    'service_id' => $service['id'],
                    'assistant_id' => $assistant['id'],
                    'price' => $service['price'],
                    'duration' => $service['duration'],
                ];
            }
            
            // Determine status (most should be paid/confirmed)
            $status = $this->determineBookingStatus($days_ago);
            $statuses[$status]++;
            
            // Create booking
            $booking_id = $this->createBooking([
                'date' => $date->format('Y-m-d'),
                'time' => $time,
                'customer_id' => $customer['id'],
                'services' => $booking_services,
                'total_price' => $total_price,
                'duration_minutes' => $total_duration_minutes,
                'status' => $status,
            ]);
            
            if ($booking_id) {
                $count++;
                
                if ($count % 50 == 0) {
                    $this->log("Generated {$count} bookings...");
                }
            }
        }
        
        $this->stats = $statuses;
        
        return $count;
    }
    
    /**
     * Select weighted random customer (some customers book more frequently)
     */
    private function weightedRandomCustomer($customers) {
        $weights = [];
        
        foreach ($customers as $index => $customer) {
            // First 10 customers are VIPs (book more frequently)
            if ($index < 10) {
                $weights[] = 5; // 5x more likely
            } elseif ($index < 25) {
                $weights[] = 3; // 3x more likely
            } else {
                $weights[] = 1; // Normal frequency
            }
        }
        
        $total_weight = array_sum($weights);
        $random = rand(1, $total_weight);
        
        $current_weight = 0;
        foreach ($customers as $index => $customer) {
            $current_weight += $weights[$index];
            if ($random <= $current_weight) {
                return $customer;
            }
        }
        
        return $customers[0];
    }
    
    /**
     * Select random services
     */
    private function selectRandomServices($services, $count) {
        $selected = [];
        $available = $services;
        
        for ($i = 0; $i < $count && !empty($available); $i++) {
            $index = array_rand($available);
            $selected[] = $available[$index];
            unset($available[$index]);
            $available = array_values($available); // Re-index
        }
        
        return $selected;
    }
    
    /**
     * Determine booking status based on how many days ago
     */
    private function determineBookingStatus($days_ago) {
        // Future or today bookings
        if ($days_ago <= 0) {
            return rand(0, 1) ? 'confirmed' : 'pending';
        }
        
        // Past bookings
        $rand = rand(1, 100);
        
        // 5% canceled
        if ($rand <= 5) {
            return 'canceled';
        }
        
        // 70% paid, 20% confirmed, 5% pay_later
        if ($rand <= 75) {
            return 'paid';
        } elseif ($rand <= 95) {
            return 'confirmed';
        } else {
            return 'pay_later';
        }
    }
    
    /**
     * Create a booking
     */
    private function createBooking($data) {
        // Create booking post
        $post_id = wp_insert_post([
            'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
            'post_status' => $data['status'],
            'post_title' => "Booking - {$data['date']} {$data['time']}",
            'post_author' => $data['customer_id'],
        ]);
        
        if (is_wp_error($post_id)) {
            return false;
        }
        
        // Add booking meta
        update_post_meta($post_id, '_sln_booking_date', $data['date']);
        update_post_meta($post_id, '_sln_booking_time', $data['time']);
        update_post_meta($post_id, '_sln_booking_amount', $data['total_price']);
        update_post_meta($post_id, '_sln_booking_deposit', 0);
        update_post_meta($post_id, '_sln_booking_duration', $data['duration_minutes']);
        
        // Serialize booking services
        $services_data = [];
        foreach ($data['services'] as $index => $service) {
            $services_data[$index] = [
                'service' => $service['service_id'],
                'attendant' => $service['assistant_id'],
                'price' => $service['price'],
                'duration' => $service['duration'],
            ];
        }
        update_post_meta($post_id, '_sln_booking_services', $services_data);
        
        // Add customer meta to booking
        $customer = get_userdata($data['customer_id']);
        if ($customer) {
            update_post_meta($post_id, '_sln_booking_firstname', $customer->first_name);
            update_post_meta($post_id, '_sln_booking_lastname', $customer->last_name);
            update_post_meta($post_id, '_sln_booking_email', $customer->user_email);
            update_post_meta($post_id, '_sln_booking_phone', get_user_meta($data['customer_id'], '_sln_phone', true));
        }
        
        return $post_id;
    }
    
    /**
     * Log message
     */
    private function log($message, $context = []) {
        if (is_array($context) && !empty($context)) {
            foreach ($context as $key => $value) {
                if (is_array($value)) {
                    $value = json_encode($value);
                }
                $message = str_replace("{{$key}}", $value, $message);
            }
        }
        
        echo $message . "\n";
        
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::log($message);
        }
    }
}

// Run the generator
$generator = new SLN_DummyDataGenerator();

// Configuration options
$config = [
    'num_bookings' => isset($_GET['bookings']) ? (int)$_GET['bookings'] : 200,
    'days_back' => isset($_GET['days']) ? (int)$_GET['days'] : 365,
    'min_services_per_booking' => 1,
    'max_services_per_booking' => 3,
    'cancellation_rate' => 0.05,
];

echo "<pre>";
$generator->generate($config);
echo "</pre>";

echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li><a href='" . admin_url('admin.php?page=salon-reports') . "'>View Reports Dashboard</a></li>";
echo "<li><a href='" . admin_url('edit.php?post_type=sln_booking') . "'>View All Bookings</a></li>";
echo "<li><a href='?bookings=50&days=30'>Generate 50 bookings for last 30 days</a></li>";
echo "<li><a href='?bookings=500&days=730'>Generate 500 bookings for last 2 years</a></li>";
echo "</ul>";


