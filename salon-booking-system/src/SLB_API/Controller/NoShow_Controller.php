<?php

namespace SLB_API\Controller;

use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class NoShow_Controller extends REST_Controller
{
    protected $rest_base = 'no-shows';

    public function register_routes()
    {
        // Get no-show statistics
        register_rest_route($this->namespace, '/' . $this->rest_base . '/stats', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_stats'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'start_date' => array(
                        'required' => true,
                        'type'     => 'string',
                        'format'   => 'date',
                    ),
                    'end_date' => array(
                        'required' => true,
                        'type'     => 'string',
                        'format'   => 'date',
                    ),
                    'shop' => array(
                        'required' => false,
                        'type'     => 'integer',
                        'default'  => 0,
                    ),
                ),
            ),
        ));

        // Get no-show bookings list
        register_rest_route($this->namespace, '/' . $this->rest_base . '/bookings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_bookings'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'start_date' => array(
                        'required' => true,
                        'type'     => 'string',
                        'format'   => 'date',
                    ),
                    'end_date' => array(
                        'required' => true,
                        'type'     => 'string',
                        'format'   => 'date',
                    ),
                    'shop' => array(
                        'required' => false,
                        'type'     => 'integer',
                        'default'  => 0,
                    ),
                    'limit' => array(
                        'required' => false,
                        'type'     => 'integer',
                        'default'  => 10,
                    ),
                ),
            ),
        ));

        // Get no-show rate by period
        register_rest_route($this->namespace, '/' . $this->rest_base . '/rate-by-period', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'get_rate_by_period'),
                'permission_callback' => array($this, 'check_permissions'),
                'args'                => array(
                    'start_date' => array(
                        'required' => true,
                        'type'     => 'string',
                        'format'   => 'date',
                    ),
                    'end_date' => array(
                        'required' => true,
                        'type'     => 'string',
                        'format'   => 'date',
                    ),
                    'shop' => array(
                        'required' => false,
                        'type'     => 'integer',
                        'default'  => 0,
                    ),
                    'period' => array(
                        'required' => false,
                        'type'     => 'string',
                        'enum'     => array('day', 'week', 'month'),
                        'default'  => 'week',
                    ),
                ),
            ),
        ));
    }

    public function check_permissions($request)
    {
        // Administrators and users with manage_salon capability
        if (current_user_can('manage_salon') || current_user_can('manage_options')) {
            return true;
        }
        
        // Shop managers can read no-show data for their shops
        if ($this->is_shop_manager()) {
            return true;
        }
        
        return false;
    }

    /**
     * Get no-show statistics
     */
    public function get_stats($request)
    {
        $start_date = sanitize_text_field($request->get_param('start_date'));
        $end_date = sanitize_text_field($request->get_param('end_date'));
        $shop = intval($request->get_param('shop'));

        // Build base query args
        // Note: Don't filter by post_status - SLN uses custom statuses (sln-b-*)
        $args = array(
            'post_type'      => 'sln_booking',
            'posts_per_page' => -1,
            'post_status'    => 'any',
            'date_query'     => array(
                array(
                    'after'     => $start_date,
                    'before'    => $end_date,
                    'inclusive' => true,
                ),
            ),
        );

        // Add shop filter if specified
        if ($shop > 0) {
            $args['meta_query'] = array(
                array(
                    'key'   => '_sln_booking_shop',
                    'value' => $shop,
                ),
            );
        }

        // Get all bookings in date range
        $all_bookings = get_posts($args);
        $total_bookings = count($all_bookings);

        // Count no-shows manually (meta_query doesn't work reliably)
        $no_show_count = 0;
        foreach ($all_bookings as $booking_post) {
            $no_show_value = get_post_meta($booking_post->ID, 'no_show', true);
            // Check for both string '1' and integer 1
            if ($no_show_value == 1 || $no_show_value === '1') {
                $no_show_count++;
            }
        }

        // Calculate no-show rate
        $no_show_rate = $total_bookings > 0 ? round(($no_show_count / $total_bookings) * 100, 2) : 0;

        // Get recent no-shows (last 10)
        $recent_no_shows = $this->get_recent_no_shows($start_date, $end_date, $shop, 10);

        return new WP_REST_Response(array(
            'total_bookings'   => $total_bookings,
            'no_show_count'    => $no_show_count,
            'no_show_rate'     => $no_show_rate,
            'recent_no_shows'  => $recent_no_shows,
            'period' => array(
                'start' => $start_date,
                'end'   => $end_date,
            ),
        ), 200);
    }

    /**
     * Get list of no-show bookings
     */
    public function get_bookings($request)
    {
        $start_date = sanitize_text_field($request->get_param('start_date'));
        $end_date = sanitize_text_field($request->get_param('end_date'));
        $shop = intval($request->get_param('shop'));
        $limit = intval($request->get_param('limit'));

        $bookings = $this->get_recent_no_shows($start_date, $end_date, $shop, $limit);

        return new WP_REST_Response(array(
            'bookings' => $bookings,
            'count'    => count($bookings),
        ), 200);
    }

    /**
     * Get no-show rate by period (daily, weekly, monthly)
     */
    public function get_rate_by_period($request)
    {
        $start_date = sanitize_text_field($request->get_param('start_date'));
        $end_date = sanitize_text_field($request->get_param('end_date'));
        $shop = intval($request->get_param('shop'));
        $period = sanitize_text_field($request->get_param('period'));

        $start = new \DateTime($start_date);
        $end = new \DateTime($end_date);
        $interval = new \DateInterval('P1D');

        if ($period === 'week') {
            $interval = new \DateInterval('P7D');
        } elseif ($period === 'month') {
            $interval = new \DateInterval('P1M');
        }

        $periods_data = array();
        $current = clone $start;

        while ($current <= $end) {
            $period_end = clone $current;
            
            if ($period === 'week') {
                $period_end->add(new \DateInterval('P6D'));
            } elseif ($period === 'month') {
                $period_end->modify('last day of this month');
            }

            if ($period_end > $end) {
                $period_end = clone $end;
            }

            // Build query args for this period
            // Note: Don't filter by post_status - SLN uses custom statuses (sln-b-*)
            $args = array(
                'post_type'      => 'sln_booking',
                'posts_per_page' => -1,
                'post_status'    => 'any',
                'date_query'     => array(
                    array(
                        'after'     => $current->format('Y-m-d'),
                        'before'    => $period_end->format('Y-m-d'),
                        'inclusive' => true,
                    ),
                ),
            );

            // Add shop filter if specified
            if ($shop > 0) {
                $args['meta_query'] = array(
                    array(
                        'key'   => '_sln_booking_shop',
                        'value' => $shop,
                    ),
                );
            }

            $period_bookings = get_posts($args);
            $total = count($period_bookings);
            $no_shows = 0;

            // Count no-shows manually (meta_query doesn't work reliably)
            foreach ($period_bookings as $booking_post) {
                $no_show_value = get_post_meta($booking_post->ID, 'no_show', true);
                // Check for both string '1' and integer 1
                if ($no_show_value == 1 || $no_show_value === '1') {
                    $no_shows++;
                }
            }

            $rate = $total > 0 ? round(($no_shows / $total) * 100, 2) : 0;

            $periods_data[] = array(
                'period_start' => $current->format('Y-m-d'),
                'period_end'   => $period_end->format('Y-m-d'),
                'total_bookings' => $total,
                'no_shows'     => $no_shows,
                'rate'         => $rate,
            );

            if ($period === 'week') {
                $current->add(new \DateInterval('P7D'));
            } elseif ($period === 'month') {
                $current->modify('first day of next month');
            } else {
                $current->add(new \DateInterval('P1D'));
            }
        }

        return new WP_REST_Response(array(
            'periods' => $periods_data,
            'period_type' => $period,
        ), 200);
    }

    /**
     * Helper function to get recent no-shows
     */
    private function get_recent_no_shows($start_date, $end_date, $shop, $limit)
    {
        // Get all bookings in date range (no meta_query, we'll filter manually)
        // Note: Don't filter by post_status - SLN uses custom statuses (sln-b-*)
        $args = array(
            'post_type'      => 'sln_booking',
            'posts_per_page' => -1, // Get all, we'll limit manually after filtering
            'post_status'    => 'any',
            'date_query'     => array(
                array(
                    'after'     => $start_date,
                    'before'    => $end_date,
                    'inclusive' => true,
                ),
            ),
        );

        // Add shop filter if specified
        if ($shop > 0) {
            $args['meta_query'] = array(
                array(
                    'key'   => '_sln_booking_shop',
                    'value' => $shop,
                ),
            );
        }

        $all_bookings = get_posts($args);
        
        // Manually filter for no-shows and collect with metadata
        $no_show_posts = array();
        foreach ($all_bookings as $post) {
            $no_show_value = get_post_meta($post->ID, 'no_show', true);
            if ($no_show_value == 1 || $no_show_value === '1') {
                $marked_at = get_post_meta($post->ID, 'no_show_marked_at', true);
                $no_show_posts[] = array(
                    'post' => $post,
                    'marked_at' => $marked_at ? $marked_at : '',
                );
            }
        }
        
        // Sort by marked_at descending
        usort($no_show_posts, function($a, $b) {
            return strcmp($b['marked_at'], $a['marked_at']);
        });
        
        // Limit results
        $no_show_posts = array_slice($no_show_posts, 0, $limit);
        
        $no_shows = array();

        foreach ($no_show_posts as $item) {
            $post = $item['post'];
            $booking = \SLN_Plugin::getInstance()->createBooking($post->ID);
            $marked_at = $item['marked_at'];
            $marked_by = get_post_meta($post->ID, 'no_show_marked_by', true);
            
            $marked_by_name = '';
            if ($marked_by) {
                $user = get_user_by('id', $marked_by);
                if ($user) {
                    $marked_by_name = $user->display_name;
                }
            }

            $no_shows[] = array(
                'id'             => $post->ID,
                'customer_name'  => $booking->getDisplayName(),
                'customer_id'    => $booking->getUserId(),
                'customer_email' => $booking->getEmail(),
                'booking_date'   => $booking->getDate()->format('Y-m-d H:i:s'),
                'marked_at'      => $marked_at,
                'marked_by'      => $marked_by,
                'marked_by_name' => $marked_by_name,
                'services'       => $this->get_booking_services($booking),
            );
        }

        return $no_shows;
    }

    /**
     * Helper function to get booking services
     */
    private function get_booking_services($booking)
    {
        $services = array();
        foreach ($booking->getBookingServices()->getItems() as $booking_service) {
            $services[] = $booking_service->getService()->getName();
        }
        return $services;
    }
}

