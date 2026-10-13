<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

class SLN_Action_Ajax_ImportBookings extends SLN_Action_Ajax_AbstractImport{

    protected $fields;
    protected $required;
    protected $data;

    public function __construct($plugin){
        parent::__construct($plugin);
        $this->type = SLN_Plugin::POST_TYPE_BOOKING;
        $this->fields = array(
            'datetime' => __('DATE/TIME', 'salon-booking-system'),
            'firstname' => __('CUSTOMER FIRST NAME', 'salon-booking-system'),
            'lastname' => __('CUSTOMER LAST NAME', 'salon-booking-system'),
            'email' => __('CUSTOMER EMAIL', 'salon-booking-system'),
            'phone' => __('CUSTOMER PHONE', 'salon-booking-system'),
            'address' => __('CUSTOMER ADDRESS', 'salon-booking-system'),
            'services' => __('SERVICES', 'salon-booking-system'),
            'attendants' => __('ASSISTANTS', 'salon-booking-system'),
            'count_services' => __('NUMBER OF FOR EACH SERVICE', 'salon-booking-system'),
            'total' => __('TOTAL PRICE', 'salon-booking-system'),
            'status' => __('STATUS', 'salon-booking-system'),
        );
        if (class_exists('\SalonMultishop\Addon')) {
            $this->fields['shop'] = __('SHOP', 'salon-booking-system');
        }
        $this->required = array(
            __('CUSTOMER EMAIL', 'salon-booking-system'),
            __('SERVICES', 'salon-booking-system'),
            __('TOTAL PRICE', 'salon-booking-system'),
            __('STATUS', 'salon-booking-system'),
            __('NUMBER OF FOR EACH SERVICE', 'salon-booking-system'),
        );
    }

    public function getData($key){
        if (!isset($this->fields[$key])) {
            return '';
        }
        $label = $this->fields[$key];

        return isset($this->data[$label]) ? $this->data[$label] : '';
    }

    public function stepStart(){
        if(!isset($_FILES['file'])){
            $this->addError(__('File not found', 'salon-booking-system'));
            return false;
        }
        $filename = tempnam('/tmp', 'sln_import');
        if(!$filename){
            $this->addError(__('Cannot create tmp file', 'salon-booking-system'));
            return false;
        }
        $moved = move_uploaded_file($_FILES['file']['tmp_name'], $filename);
        if(!$moved){
            $this->addError(__('Cannot write to tmp file', 'salon-booking-system'));
            return false;
        }
        set_transient($this->getTransientKey(), $filename, 60*60*24);

        $fh = fopen($filename, 'r');
        $headers = array();
        while(!isset($headers[0]) || empty($headers[0]) || substr($headers[0], 3) === __('Reservations', 'salon-booking-system')){ // remove 3 first unreadable char to compare string. For the test, print the string character by character
            $headers = fgetcsv($fh);
        }

        $items = array();
        while($row = fgetcsv($fh)){
            $item = array();
            foreach($row as $i => $v){
                $item[$headers[$i]] = $v;
            }
            $items[] = $item;
        }

        fclose($fh);

        $items = array_filter($items);
        $items = $this->prepareRows($items);
        $import = array(
            'total' => count($items),
            'items' => $items,
        );

        file_put_contents($filename, $this->jsonEncodePartialOnError($import));

        $args = array(
            'headers' => $headers,
            'rows' => $this->getItemsForPreview($items),
            'columns' => array_values($this->fields),
            'required' => $this->required,
        );
        $matching = $this->plugin->loadView('admin/_tools_import_matching', $args);
        return array(
            'total' => $import['total'],
            'left' => $import['total'],
            'matching' => $matching,
            'rows' => $args['rows'],
            'columns' =>$args['columns'],
            'headers' => $args['headers'],
        );
    }

    protected function processRow($data){
        $this->data = $data;
        $services = array();
        $repository = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
        foreach(explode(', ', $this->getData('services')) as $service_name){
            $service_name = trim($service_name);
            $criteria = array('@wp_query' => array('title' => $service_name,));
            $service = $repository->getIDs($criteria);
            if(empty($service) || !isset($service[0]) || count($service) > 1){
                $service_message = (count($service) > 1 ? esc_html__('One or more services with the name', 'salon-booking-system') : esc_html__('Undefined service', 'salon-booking-system')) . ': ' . $service_name;
                $service_message = empty($service_name) ? esc_html__('Service required', 'salon-booking-system') : $service_message;
                return $this->dataToErrorRespons($data, $service_message);
            }
            $services[] = $repository->create($service[0]);
        }
        $attendants = array();
        $has_services_attendant = True;
        foreach($services as $service){
            $has_services_attendant = $has_services_attendant && $service->isAttendantsEnabled();
        }
        if($this->plugin->getSettings()->isAttendantsEnabled() && $has_services_attendant){
            $repository = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
            
            foreach(explode(', ', $this->getData('attendants')) as $attendant_name){

                $attendant_name = trim($attendant_name);
                $criteria = array('@wp_query' => array('title' => $attendant_name));
                $attendant = $repository->getIDs($criteria);
                if(empty($attendant) || !isset($attendant[0]) || count($attendant) > 1){
                    $attendant_message = empty($attendant_name) ? esc_html__('Attendant required', 'salon-booking-system') : esc_html__('Undefined attendant', 'salon-booking-system'). ' '. $attendant_name;
                    return $this->dataToErrorRespons($data, $attendant_message);
                }
                $attendants[] = $repository->create($attendant[0]);
            }
        }
        $booking_services = array();
        for($i = 0; $i < count($services); $i++){
            $booking_service = array('service' => $services[$i]->getId());
            if($services[$i]->isAttendantsEnabled()){
                $service = $services[$i];
                if(empty($attendants)){
                    return $this->dataToErrorRespons($data, esc_html__('Service requires attendant but no attendants provided', 'salon-booking-system'));
                }
                if($service->isMultipleAttendantsForServiceEnabled()){
                    $attendants = array_slice($attendants, 0, $service->getCountMultipleAttendants());
                    $attendants = array_map(function($att){return $att->getId();}, $attendants);
                    $booking_service['attendant'] = $attendants;
                }else{
                    $booking_service['attendant'] = $attendants[$i%count($attendants)]->getId();
                }
            }
            $booking_services[] = $booking_service;
        }
        if(!($booking_status = array_search($this->getData('status'), SLN_Enum_BookingStatus::getLabels()))){
            return $this->dataToErrorRespons($data, esc_html__('Invalid booking status', 'salon-booking-system'));
        }
        $args = array(
            'post_title' => $this->getData('firstname'). ' '.$this->getData('lastname'),
            'post_type' => SLN_Plugin::POST_TYPE_BOOKING,
        );
        $args = apply_filters('sln.booking_builder.create.getPostArgs', $args);
        $args['post_status'] = $booking_status;

        if(empty($this->getData('datetime'))){
            return $this->dataToErrorRespons($data, esc_html__('Invalid date', 'salon-booking-system'));
        }

        $shopName = trim((string) $this->getData('shop'));
        $shopId = 0;
        if ($shopName !== '') {
            $shopIds = $this->findShopIdsByName($shopName);
            if (count($shopIds) !== 1) {
                $shopMessage = count($shopIds) > 1
                    ? esc_html__('One or more shops with the name', 'salon-booking-system')
                    : esc_html__('Undefined shop', 'salon-booking-system');
                return $this->dataToErrorRespons($data, $shopMessage . ': ' . $shopName);
            }
            $shopId = $shopIds[0];
        }
        
        $booking_id = wp_insert_post($args);
        
        // Normalize date format to handle localized month names
        $datetime_string = $this->getData('datetime');
        $date = $this->parseDateTime($datetime_string);
        
        if (!$date) {
            return $this->dataToErrorRespons($data, esc_html__('Invalid date format', 'salon-booking-system'));
        }
        $bookingServices = SLN_Wrapper_Booking_Services::build($booking_services, $date, 0, $this->parseCounts($this->getData('count_services')));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_services', $bookingServices->toArrayRecursive());
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_services_processed', 1);
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_date', $date->format('Y-m-d'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_time', $date->format('H:i'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_firstname', $this->getData('firstname'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_lastname', $this->getData('lastname'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_email', $this->getData('email'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_phone', $this->getData('phone'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_address', $this->getData('address'));
        if ($shopId) {
            update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_shop', $shopId);
        }


        $booking = $this->plugin->createBooking($booking_id);
        $booking->evalDuration();
        $booking->setMeta('amount', $this->parseAmount($this->getData('total')));
        $booking->setMeta('deposit', 0);
        return true;
    }

    protected function dataToErrorRespons($data, $error_message){
        return array(
            'id' => $data['ID'],
            'datetime' => $this->getData('datetime'),
            'first_name' => $this->getData('firstname'),
            'last_name' => $this->getData('lastname'),
            'email' => $this->getData('email'),
            'error' => $error_message,
        );
    }
    
    /**
     * Quantities are comma-separated. Older exports used a semicolon.
     *
     * @param string $raw
     * @return string[]
     */
    protected function parseCounts($raw) {
        $raw = str_replace(';', ',', (string) $raw);
        $parts = array_map('trim', explode(',', $raw));
        $parts = array_filter($parts, 'strlen');

        return array_values($parts);
    }

    /**
     * Strip a currency symbol so "40.10 $" and "40,10" both become a number.
     *
     * @param string $raw
     * @return string
     */
    protected function parseAmount($raw) {
        $raw = html_entity_decode((string) $raw, ENT_QUOTES, 'UTF-8');
        // Export appends "(… already paid as deposit)" for paid deposits; the total is before it.
        $parenthesis = strpos($raw, '(');
        if ($parenthesis !== false) {
            $raw = substr($raw, 0, $parenthesis);
        }
        $raw = preg_replace('/[^\d,.\-]/', '', $raw);
        if (!is_string($raw) || $raw === '' || $raw === '-') {
            return '0';
        }
        $lastComma = strrpos($raw, ',');
        $lastDot = strrpos($raw, '.');
        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($lastComma !== false) {
            $raw = str_replace(',', '.', $raw);
        }

        return is_numeric($raw) ? $raw : '0';
    }

    /**
     * Match shops by exact title.
     *
     * @param string $name
     * @return int[]
     */
    protected function findShopIdsByName($name) {
        $shops = get_posts(array(
            'post_type' => SLN_Plugin::POST_TYPE_SHOP,
            'post_status' => array('publish', 'private', 'draft', 'pending'),
            'posts_per_page' => -1,
            'suppress_filters' => false,
        ));
        $matches = array();
        foreach ($shops as $shop) {
            if (isset($shop->post_title) && $shop->post_title === $name) {
                $matches[] = (int) $shop->ID;
            }
        }

        return $matches;
    }

    /**
     * Parse datetime string from various formats including localized month names
     * 
     * @param string $datetime_string Date/time string from CSV
     * @return SLN_DateTime|false DateTime object or false on failure
     */
    protected function parseDateTime($datetime_string) {
        // Try direct parsing first (standard formats)
        try {
            return new SLN_DateTime($datetime_string);
        } catch (Exception $e) {
            // Continue to try other formats
        }
        
        // Map of localized month abbreviations to English
        $month_map = array(
            // Polish
            'Sty' => 'Jan', 'Lut' => 'Feb', 'Mar' => 'Mar', 'Kwi' => 'Apr',
            'Maj' => 'May', 'Cze' => 'Jun', 'Lip' => 'Jul', 'Sie' => 'Aug',
            'Wrz' => 'Sep', 'Paź' => 'Oct', 'Lis' => 'Nov', 'Gru' => 'Dec',
            // Spanish
            'Ene' => 'Jan', 'Feb' => 'Feb', 'Abr' => 'Apr', 'Jun' => 'Jun',
            'Jul' => 'Jul', 'Ago' => 'Aug', 'Sep' => 'Sep', 'Oct' => 'Oct',
            'Nov' => 'Nov', 'Dic' => 'Dec',
            // French
            'Jan' => 'Jan', 'Fév' => 'Feb', 'Avr' => 'Apr', 'Mai' => 'May',
            'Jui' => 'Jun', 'Juil' => 'Jul', 'Aoû' => 'Aug', 'Déc' => 'Dec',
            // German
            'Mär' => 'Mar', 'Mai' => 'May', 'Okt' => 'Oct', 'Dez' => 'Dec',
            // Italian
            'Gen' => 'Jan', 'Feb' => 'Feb', 'Mag' => 'May', 'Giu' => 'Jun',
            'Lug' => 'Jul', 'Ago' => 'Aug', 'Set' => 'Sep', 'Ott' => 'Oct',
            'Dic' => 'Dec',
        );
        
        // Replace localized month names with English equivalents
        $normalized = str_replace(array_keys($month_map), array_values($month_map), $datetime_string);
        
        // Try parsing the normalized string
        try {
            return new SLN_DateTime($normalized);
        } catch (Exception $e) {
            // Log the error for debugging
            SLN_Plugin::addLog('ImportBookings: Failed to parse datetime: ' . $datetime_string . ' (normalized: ' . $normalized . ')');
            return false;
        }
    }
}