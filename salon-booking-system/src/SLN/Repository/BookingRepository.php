<?php
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.DirectQuery
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query
// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery.NoCaching
/**
 * @method SLN_Wrapper_Booking getOne($criteria = [])
 * @method SLN_Wrapper_Booking[] get($criteria = [])
 * @method SLN_Wrapper_Booking create($data = null)
 */
class SLN_Repository_BookingRepository extends SLN_Repository_AbstractWrapperRepository
{
    protected $bookingCache = array();

    public function getWrapperClass()
    {
        return SLN_Wrapper_Booking::_CLASS;
    }

    protected function processCriteria($criteria)
    {
        if (isset($criteria['time@max'])) {
            $criteria['@wp_query']['meta_query'][] =
                array(
                    'key'     => '_sln_booking_time',
                    'value'   => $criteria['time@max']->format('H:i'),
                    'compare' => '<=',
                );
            unset($criteria['time@max']);
        }

        if (isset($criteria['day'])) {
            $criteria['@wp_query']['meta_query'][] =
                array(
                    'key'     => '_sln_booking_date',
                    'value'   => $criteria['day']->format('Y-m-d'),
                    'compare' => isset($criteria['day_compare']) ? $criteria['day_compare'] : '=',
                );
            unset($criteria['day']);
        } else {
            if (isset($criteria['day@min'])) {
                $criteria['@wp_query']['meta_query'][] =
                    array(
                        'key'     => '_sln_booking_date',
                        'value'   => $criteria['day@min']->format('Y-m-d'),
                        'compare' => '>=',
                    );

                unset($criteria['day@min']);
            }
            if (isset($criteria['day@max'])) {
                $criteria['@wp_query']['meta_query'][] =
                    array(
                        'key'     => '_sln_booking_date',
                        'value'   => $criteria['day@max']->format('Y-m-d'),
                        'compare' => '<=',
                    );
                unset($criteria['day@max']);
            }
        }
        
        // Multi-Shop Support: Add shop filtering if shop ID is provided
        // This ensures calendar bookings and tooltips respect shop selection
        if (isset($criteria['shop']) && intval($criteria['shop']) > 0) {
            $criteria['@wp_query']['meta_query'][] =
                array(
                    'key'     => '_sln_booking_shop',  // Correct meta key used by Multi-Shop
                    'value'   => intval($criteria['shop']),
                    'compare' => '=',
                );
            unset($criteria['shop']);
        }

        $criteria = apply_filters('sln.repository.booking.processCriteria', $criteria);

        return parent::processCriteria($criteria);
    }


    /**
     * @param SLN_Wrapper_Booking $a
     * @param SLN_Wrapper_Booking $b
     *
     * @return int
     */
    public static function sortAscByStartsAt($a, $b)
    {
        return ($a->getStartsAt()->getTimestamp() > $b->getStartsAt()->getTimestamp()
            ? 1 : -1);
    }

    /**
     * @param SLN_Wrapper_Booking $a
     * @param SLN_Wrapper_Booking $b
     *
     * @return int
     */
    public static function sortDescByStartsAt($a, $b)
    {
        return ($a->getStartsAt()->getTimestamp() >= $b->getStartsAt()->getTimestamp()
            ? -1 : 1);
    }


    /**
     * @todo add in src/SLN/Helper/Availability/AbstractDayBookings.php
     * @param $date
     * @param SLN_Wrapper_Booking|null $currentBooking
     *
     * @return array
     */
    public function getForAvailabilityBookings($date, SLN_Wrapper_Booking $currentBooking = null)
    {
        global $wpdb;

        $criteria       = array('day' => $date, 'foravailability' => true);
        $noTimeStatuses = SLN_Enum_BookingStatus::$noTimeStatuses;
        $ret            = array();

        if (empty($this->bookingCache)) {

            $hb     = new SLN_Helper_HoursBefore(SLN_Plugin::getInstance()->getSettings());
            $from  = $hb->getFromDate();
            $to     = $hb->getToDate();

            // PERFORMANCE OPTIMIZATION: Optimized query for 36K+ bookings
            // 1. Use IN() instead of multiple OR for post_status (index-friendly)
            // 2. Avoid DATE() function on meta_value (prevents index use)
            // 3. Use string comparison directly (meta_value is stored as YYYY-MM-DD)
            $posts = $wpdb->get_results($wpdb->prepare("
                SELECT
                    p.*, pm.meta_value
                FROM
                    $wpdb->postmeta pm
                INNER JOIN
                    $wpdb->posts p ON pm.post_id = p.ID
                WHERE
                    pm.meta_key = '_sln_booking_date'
                    AND pm.meta_value >= %s
                    AND pm.meta_value < %s
                    AND p.post_type = 'sln_booking'
                    AND p.post_status IN ('sln-b-pendingpayment', 'sln-b-pending', 'sln-b-paid', 'sln-b-paylater', 'sln-b-confirmed')
                ORDER BY
                    pm.meta_value, p.post_date DESC
           ", $from->format('Y-m-d'), $to->modify('+1 day')->format('Y-m-d')));

            foreach ($posts as $post) {
                if (!isset($this->bookingCache[$post->meta_value])) {
                    $this->bookingCache[$post->meta_value] = array();
                }
                $this->bookingCache[$post->meta_value][] = $post;
            }
            $this->cacheMultidateBookings($posts);
        }

        $posts = isset($this->bookingCache[$date->format('Y-m-d')]) ? $this->bookingCache[$date->format('Y-m-d')] : array();

        $result = array();
        foreach ($posts as $post) {
            $result[] = $this->create($post);
        }

        foreach ($result as $b) {
            // Exclude the current booking being rescheduled from availability calculations
            if (!empty($currentBooking) && $b->getId() == $currentBooking->getId()) {
                SLN_Plugin::addLog(sprintf(
                    'BookingRepository::getForAvailabilityBookings - Excluding current booking #%d from availability (rescheduling)',
                    $currentBooking->getId()
                ));
                continue; // Skip this booking - it's being rescheduled
            }
            
            if (! $b->hasStatus($noTimeStatuses)) {
                $ret[] = $b;
            }
        }

        return $ret;
    }

    protected function cacheMultidateBookings($posts)
    {
        foreach ($posts as $post) {
            foreach (get_post_meta($post->ID, '_sln_booking_services', true) as $service) {
                $service = $service['service'];
                $isLock = get_post_meta($service, '_sln_service_lock_for_service', true);
                $isOffset = get_post_meta($service, '_sln_service_offset_for_service', true);
                $isLock = !empty($isLock) && $isLock;
                $isOffset = !empty($isOffset) && $isOffset;
                if ($isLock || $isOffset) {
                    $lockInterval = get_post_meta($service, '_sln_service_' . ($isLock ? 'lock' : 'offset') . '_for_service_interval', true);
                    $startDate = new SLN_DateTime($post->meta_value . ' ' . get_post_meta($post->ID, '_sln_booking_time', true));
                    $endDate = (clone $startDate)->modify('+' . $lockInterval . ' hours');
                    if ($isLock) {
                        $startDate->modify('-' . $lockInterval . ' hours');
                    }
                    while ($startDate <= $endDate) {
                        if (!isset($this->bookingCache[$startDate->format('Y-m-d')])) {
                            $this->bookingCache[$startDate->format('Y-m-d')] = array();
                        }
                        if ($startDate->format('Y-m-d') != $post->meta_value) {
                            $this->bookingCache[$startDate->format('Y-m-d')][] = $post;
                        }
                        $startDate->modify('+1 day');
                    }
                }
            }
        }
    }

    public function getForAvailability($date, SLN_Wrapper_Booking $currentBooking = null)
    {
        $ret = $this->getForAvailabilityBookings($date, $currentBooking);
        return apply_filters('sln_booking_repository_for_availability', $ret);
    }

    public function getForAvailabilityAllBookings($date, SLN_Wrapper_Booking $currentBooking = null)
    {
        $ret = $this->getForAvailabilityBookings($date, $currentBooking);
        return $ret;
    }

    public function getForDaySearch($search, $day)
    {
        $search_parts = explode(' ', $search);
        $search_parts = array_filter($search_parts, function ($str) {
            return !empty($str);
        });

        if (empty($search_parts)) return [];

        $criteria = [
            'day' => $day,
            '@wp_query' => []
        ];

        $map_query = function ($key, $search) {
            return array(
                'key'     => $key,
                'value'   => $search,
                'compare' => 'LIKE',
            );
        };

        $id = false;
        $meta_query = [];
        $byId = [];

        foreach ($search_parts as $search_part) {
            if (!$id && ctype_digit(strval($search_part))) {
                $id = $search_part;
            }
            $item = ['relation' => 'OR'];
            $item[] = $map_query('_sln_booking_email', $search_part);
            $item[] = $map_query('_sln_booking_firstname', $search_part);
            $item[] = $map_query('_sln_booking_lastname', $search_part);
            $item[] = $map_query('_sln_booking_phone', $search_part);
            $meta_query[] = $item;
        }

        if ($id) {
            $ids_criteria = $criteria;
            $ids_criteria['@wp_query']['p'] = $id;
            $byId =  $this->get($ids_criteria) ?: [];
        }

        $criteria['@wp_query']['meta_query'] = $meta_query;
        $byField = $this->get($criteria) ?: [];
        $b_temp = array_merge($byId, $byField);

        $bookings = [];
        foreach ($b_temp as $booking) {
            $bookings[$booking->getId()] = $booking;
        }
        $ret = [];
        foreach ($bookings as $b) {
            $item = [
                'customer' => $b->getDisplayName(),
                'start_date' => $this->plugin->format()->datetime($b->getStartsAt()),
                'status' => SLN_Enum_BookingStatus::getLabel($b->getStatus()),
                'time' => $booking->getStartsAt()->format('H:i'),
                'services' => [],
                'amount' => $this->plugin->format()->money($b->getAmount()),
                'edit_url' => get_edit_post_link($b->getId()),
                'id' => $b->getId(),
            ];
            $services = $b->getBookingServices()->getItems();
            foreach ($services as $service) {
                $attendant = $service->getAttendant();
                $attendant_name = $attendant ? (!is_array($attendant) ? $attendant->getName() : SLN_Wrapper_Attendant::implodeArrayAttendantsName(' ', $attendant)) : '';
                $item['services'][] = [
                    'name' => $service->getService()->getName(),
                    'attendant' => $attendant_name,
                ];
            }
            $ret[] = $item;
        }

        return $ret;
    }

    public function getForMonthSearch($search, $currentMonth)
    {
        $search_parts = explode(' ', $search);
        $search_parts = array_filter($search_parts, function ($str) {
            return !empty($str);
        });
        if (empty($search_parts)) return [];
        $startDate = (clone $currentMonth)->modify('-1 month')->modify('first day of this month')->setTime(0, 0);
        $endDate = (clone $currentMonth)->modify('+1 month')->modify('last day of this month')->setTime(23, 59, 59);
        $criteria = [
            'day@min' => $startDate,
            'day@max' => $endDate,
            '@wp_query' => []
        ];
        $map_query = function ($key, $search) {
            return array(
                'key'     => $key,
                'value'   => $search,
                'compare' => 'LIKE',
            );
        };
        $id = false;
        $meta_query = [];
        $byId = [];
        foreach ($search_parts as $search_part) {
            if (!$id && ctype_digit(strval($search_part))) {
                $id = $search_part;
            }
            $item = ['relation' => 'OR'];
            $item[] = $map_query('_sln_booking_email', $search_part);
            $item[] = $map_query('_sln_booking_firstname', $search_part);
            $item[] = $map_query('_sln_booking_lastname', $search_part);
            $item[] = $map_query('_sln_booking_phone', $search_part);
            $item[] = $map_query('_sln_booking_status', $search_part);
            $item[] = $map_query('_sln_booking_amount', $search_part);
            $meta_query[] = $item;
        }
        if ($id) {
            $ids_criteria = $criteria;
            $ids_criteria['@wp_query']['p'] = $id;
            $byId = $this->get($ids_criteria) ?: [];
        }
        $criteria['@wp_query']['meta_query'] = $meta_query;
        $byField = $this->get($criteria) ?: [];
        $b_temp = array_merge($byId, $byField);
        $bookings = [];
        foreach ($b_temp as $booking) {
            $bookings[$booking->getId()] = $booking;
        }
        $ret = [];
            foreach ($bookings as $b) {
                // Get shop name for Multi-Shop support
                $shopName = '';
                if (class_exists('\SalonMultishop\Addon')) {
                    $shopId = get_post_meta($b->getId(), '_sln_booking_shop', true);
                    if (!empty($shopId)) {
                        $shopName = get_the_title($shopId);
                    }
                }
                
                // Get status color for UI display (matches booking stats colors)
                $statusColors = [
                    'sln-b-paid' => '#6aa84f',
                    'sln-b-confirmed' => '#6aa84f',
                    'sln-b-paylater' => '#6d9eeb',
                    'sln-b-pending' => '#f58120',
                    'sln-b-pendingpayment' => '#f58120',
                    'sln-b-canceled' => '#e54747',
                    'sln-b-error' => '#e54747',
                ];
                $statusKey = $b->getStatus();
                $statusColor = isset($statusColors[$statusKey]) ? $statusColors[$statusKey] : '#1b1b21';
                
                // Check for no-show meta
                $isNoShow = get_post_meta($b->getId(), 'no_show', true) == 1;
                if ($isNoShow) {
                    $statusColor = '#1b1b21';
                }
                
                $item = [
                    'customer' => $b->getDisplayName(),
                    'start_date' => $this->plugin->format()->datetime($b->getStartsAt()),
                    'status' => SLN_Enum_BookingStatus::getLabel($b->getStatus()),
                    'status_color' => $statusColor,
                    'time' => $b->getStartsAt()->format('d/m/Y - H:i'),
                    'calendar_time' => $b->getStartsAt()->format('l F j Y H:i'),
                    'services' => [],
                    'amount' => $this->plugin->format()->money($b->getAmount()),
                    'edit_url' => get_edit_post_link($b->getId()),
                    'id' => $b->getId(),
                    'shop_name' => $shopName,
                ];
            $services = $b->getBookingServices()->getItems();
            foreach ($services as $service) {
                $attendant = $service->getAttendant();
                $attendant_name = $attendant ? (!is_array($attendant) ? $attendant->getName() : SLN_Wrapper_Attendant::implodeArrayAttendantsName(' ', $attendant)) : '';
                $item['services'][] = [
                    'name' => $service->getService()->getName(),
                    'attendant' => $attendant_name,
                ];
            }
            $ret[] = $item;
        }
        return $ret;
    }
}
