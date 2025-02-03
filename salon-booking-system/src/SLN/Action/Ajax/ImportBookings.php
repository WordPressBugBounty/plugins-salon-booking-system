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
        $this->required = array(
            __('CUSTOMER EMAIL', 'salon-booking-system'),
            __('SERVICES', 'salon-booking-system'),
            __('TOTAL PRICE', 'salon-booking-system'),
            __('STATUS', 'salon-booking-system'),
            __('NUMBER OF FOR EACH SERVICE', 'salon-booking-system'),
        );
    }

    public function getData($key){
        return $this->data[$this->fields[$key]];
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
            if($services[$i]->isAttendantsEnabled()){
                $service = $services[$i];
                $booking_service = array('service' => $service->getId());
                if($service->isMultipleAttendantsForServiceEnabled()){
                    $attendants = array_slice($attendants, 0, $service->getCountMultipleAttendants());
                    $attendants = array_map(function($att){return $att->getId();}, $attendants);
                    $booking_service['attendant'] = $attendants;
                }else{
                    $booking_service['attendant'] = $attendants[$i%count($attendants)]->getId();
                }
                $booking_services[] = $booking_service;
            }
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
        
        $booking_id = wp_insert_post($args);
        $date = new SLN_DateTime($this->getData('datetime'));
        $bookingServices = SLN_Wrapper_Booking_Services::build($booking_services, $date, 0, explode(', ', $this->getData('count_services')));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_services', $bookingServices->toArrayRecursive());
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_services_processed', 1);
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_date', $date->format('Y-m-d'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_time', $date->format('H:i'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_firstname', $this->getData('firstname'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_lastname', $this->getData('lastname'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_email', $this->getData('email'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_phone', $this->getData('phone'));
        update_post_meta($booking_id, '_'.SLN_Plugin::POST_TYPE_BOOKING.'_address', $this->getData('address'));


        $booking = $this->plugin->createBooking($booking_id);
        $booking->evalDuration();
        $booking->setMeta('amount', $this->getData('total'));
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
}