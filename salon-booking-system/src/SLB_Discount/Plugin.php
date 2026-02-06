<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

class SLB_Discount_Plugin {

	const POST_TYPE_DISCOUNT = 'sln_discount';

	/**
	 * @var SLB_Discount_Plugin
	 */
	private static $instance;

	/**
	 * @var SLN_Plugin
	 */
	private $plugin;

	public static function getInstance()
	{
		if (!self::$instance) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * SLN_Plugin_Discount constructor.
	 */
	private function __construct() {
		add_action('plugins_loaded', array($this, 'hook_plugins_loaded'));
		add_action('init', array($this, 'hook_init'));
	}

    private function init_admin() {
        $this->init_ajax();
		SLB_Discount_Admin_ExportDiscountsCsv::init_hooks();
    }

    private function init_ajax() {
    	$enableDiscountSystem = $this->plugin->getSettings()->get('enable_discount_system');
        if (!$enableDiscountSystem) {
            return;
        }
        $callback = array($this, 'ajax');
        add_action('wp_ajax_salon_discount', $callback);
        add_action('wp_ajax_nopriv_salon_discount', $callback);
    }

    public function hook_plugins_loaded() {
		$plugin = SLN_Plugin::getInstance();
		$plugin->templating()->addPath(SLN_PLUGIN_DIR.'/views/discount/%s.php', 11);
		$this->plugin = $plugin;
	}

	public function hook_init() {
		$enableDiscountSystem = $this->plugin->getSettings()->get('enable_discount_system');
        if (!$enableDiscountSystem) {
            return;
        }
		$plugin = $this->getSlnPlugin();
		$plugin->addRepository(
			new SLB_Discount_Repository_DiscountRepository(
				$plugin,
				new SLB_Discount_PostType_Discount($plugin, self::POST_TYPE_DISCOUNT)
			)
		);
		add_action('admin_init', array($this, 'hook_admin_init'));

		add_action('admin_enqueue_scripts', array($this, 'hook_admin_enqueue_scripts'));
		add_action('wp_enqueue_scripts', array($this, 'hook_wp_enqueue_scripts'));

		add_filter('sln.func.isSalonPage', array($this, 'hook_isSalonPage'));

		add_action('sln.metabox.booking.pre_eval',array($this,'hook_metabox_pre_eval'));

		add_filter('sln.calc_booking_total.get_services', array($this, 'get_services_calc_booking_total'), 10, 3);
		add_filter('sln.calc_booking_total.get_discounts_html', array($this, 'get_discounts_html_calc_booking_total'));

		add_action('sln.booking_builder.create', array($this, 'hook_booking_builder_create'), 10, 1);

		add_action('sln.shortcode.summary.dispatchForm.before_booking_creation', array($this, 'hook_summary_dispatchForm_before_booking_creation'), 10, 2);

		add_action('sln.template.summary.before_total_amount', array($this, 'hook_summary_before_total_amount'), 10, 2);
		add_action('sln.template.summary.after_total_amount', array($this, 'hook_summary_after_total_amount'), 10, 2);

		add_filter('sln.template.metabox.booking.total_amount_label', array($this, 'hook_booking_total_amount_label'), 10, 2);
		add_action('sln.template.metabox.booking.total_amount_row', array($this, 'hook_booking_total_amount_row'));

		add_action('sln.booking.setStatus', array($this, 'hook_booking_setStatus'), 10, 3);

		add_action('sln.mail.summary_details', array($this, 'hook_mail_summary_details'));
		add_action('sln.mail.special', array($this, 'hook_mail_special_offer'), 10, 2);

		add_action('sln.my_account.nav', array($this, 'hook_history_nav'));
		add_action('sln.my_account.content', array($this, 'hook_history_content'));

		add_filter('sln.action.ajaxcalendar.wrapBooking.discountAmount', array($this, 'get_discount_amount_ajaxcalendar_booking'), 10, 2);
        add_action('sln.api.booking.pre_eval',array($this,'hook_api_pre_eval'), 10, 2);

        add_filter('sln.customer.fidelity_score.discounts_score', array($this, 'get_fidelity_score_discounts_score'), 10, 2);

        if (is_admin()) {
            $this->init_admin();
        }
	}

	// js
	// sconti cumulabili
	public function hook_metabox_pre_eval($booking){
		$enableDiscountSystem = $this->plugin->getSettings()->get('enable_discount_system');
		if(!$enableDiscountSystem) return;
		$old_discounts = SLB_Discount_Helper_Booking::getBookingDiscountIds($booking);
		$data = array();
		$items = $booking->getServicesMeta();
		if(!empty($_POST['_sln_booking_discounts']) && !is_array($_POST['_sln_booking_discounts'])){
			if(intval($_POST['_sln_booking_discounts'])) $discounts = array(intval($_POST['_sln_booking_discounts']));
			else return;
		}
		if(!empty($_POST['_sln_booking_discounts']) && is_array($_POST['_sln_booking_discounts'])) $discounts = array_map('intval',$_POST['_sln_booking_discounts']);
		$discounts_to_compare =  empty($discounts) ? array() : $discounts;
		$discounts_to_decrement = array_diff($old_discounts,$discounts_to_compare);
		$discounts_to_increment = array_diff($discounts_to_compare,$old_discounts);
		$dRepo = SLN_Plugin::getInstance()->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT);
		$discountId = null;
		if (empty($discounts)){
			foreach (['discounts',"discount_amount", "discount_score"] as $k) {
	            delete_post_meta($booking->getId(), '_'.SLN_Plugin::POST_TYPE_BOOKING.'_'.$k);
            }
	    	foreach($items as $sId => &$atId) {
	    		$service = new SLN_Wrapper_Service($sId);
				$price       = $service->getPrice();
				if( is_array( $atId ) ){
					$items[$sId] = array_merge($atId,array(
						'price'     => $price
					));
				} else{
					$items[$sId] = array( 'service' => $sId,
										  'attendant' => $atId,
										  'price' => $price );
				}
			}
			foreach ($discounts_to_decrement as $discountId) {
				$discount        = $dRepo->create($discountId);
				$discount->decrementUsagesNumber($booking->getUserId());
				$discount->decrementTotalUsagesNumber();
			}
			update_post_meta($booking->getId(), '_'.SLN_Plugin::POST_TYPE_BOOKING.'_services', $items);
			update_post_meta($booking->getId(), '_'.SLN_Plugin::POST_TYPE_BOOKING.'_discount_'.$discountId, false);
			return;
		}
		$bookingServices = $booking->getBookingServices();
		foreach ($bookingServices->getItems() as $bookingService) {
		    $service = new SLN_Wrapper_Service($bookingService->getService()->getId());
		    $price   = $service->getPrice();
		    $bookingService->setPrice($price);
		}
		$data["discounts"] = array();
		$discountValues = 0;
		$first = true;
		foreach ($discounts as $discountId) {
			$discount        = $dRepo->create($discountId);
			$discountValues  = $discount->applyDiscountToBookingServices($bookingServices, true,  $booking->getAttendantsIds());

			$data["discounts"][] = $discountId;
			$data["discount_{$discountId}"] = true;
			$data["discount_amount"] = array_merge(isset($data["discount_amount"]) ? $data["discount_amount"] : array(),$discountValues);

            $discountScores = array();

            $rules = $discount->getDiscountRules();
            if (!empty($rules)) {
                foreach($rules as $rule) {
                    if ($rule['mode'] === 'score') {
                        $discountScores[] = (int)$rule['score_number'];
                    }
                }
				if(!empty($discountScores)){
					$discountScores = array(max($discountScores));
				}
            }


            $data["discount_score"] = array_merge(isset($data["discount_score"]) ? $data["discount_score"] : array(), $discountScores);

			foreach($items as $sId => $atId) {
				if($first){
					$service = new SLN_Wrapper_Service($sId);
					$price       = $service->getPrice();
				}
				else $price      = isset($items[$sId]['price']) ? $items[$sId]['price'] : ($bookingServices->findByService($sId) ? $bookingServices->findByService($sId)->getPrice() : '');

				if( is_array( $atId ) ){
					$items[$sId] = array_merge($atId,array(
						'price'     => $price - $discountValues[$atId['service']]
					));
				} else{
					$items[$sId] = array( 'service' => $sId,
										  'attendant' => $atId,
										  'price' => $price );
				}
                                if ($bookingServices->findByService($sId)) {
                                    $bookingServices->findByService($sId)->setPrice($items[$sId]['price']);
                                }
			}
			if(in_array($discountId,$discounts_to_increment)){
					$discount->incrementUsagesNumber($booking->getUserId());
					$discount->incrementTotalUsagesNumber();
			}
			$first = false;
		}
		foreach ($discounts_to_decrement as $discountId) {
				$data["discount_{$discountId}"] = false;
				$discount        = $dRepo->create($discountId);
				$discount->decrementUsagesNumber($booking->getUserId());
				$discount->decrementTotalUsagesNumber();
		}

		$data["services"] = $items;

		foreach ($data as $k => $v) {
	            update_post_meta($booking->getId(), '_'.SLN_Plugin::POST_TYPE_BOOKING.'_'.$k, $v);
	    }
	}

	public function get_services_calc_booking_total($items, $bookingServices, $bookingAttendants) {

		$enableDiscountSystem = $this->plugin->getSettings()->get('enable_discount_system');

		if( ! $enableDiscountSystem ) {
		    return;
		}

		$discounts = !empty($_POST['_sln_booking_discounts']) ? $_POST['_sln_booking_discounts'] : array();

		if ( empty( $discounts ) ) {
		    return $items;
		}

		$dRepo = SLN_Plugin::getInstance()->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT);

		$discountValues = array();
		$first = true;
		foreach ($discounts as $discountId) {
		    $discount        = $dRepo->create($discountId);
		    $discountValues  = $discount->applyDiscountToBookingServices($bookingServices, true, $bookingAttendants);
		    foreach($items as $sId => $atId) {
		    	$price = 0;
				if($first){
				    $service = new SLN_Wrapper_Service($sId);
	                $service = apply_filters('sln.booking_services.buildService', $service);
					if(!$service->getVariablePriceEnabled()){
					    $price = $service->getPrice();
					}else{
						$price = $service->getVariablePrice(isset($atId['attendant']) ? $atId['attendant'] : $atId);
					}
				} else {
					if(isset($items[$sId]['price'])){
						$price = $items[$sId]['price'];
					}else{
						$service = $bookingservices->findByService($sId);
						if(!$service->getVariablePriceEnabled()){
							$price = $service->getPrice();
						}else{
							$price = $service->getVariablePrice(isset($attId['attendant']) ? $atId['attendant'] : $atId);
						}
					}
				    $price = isset($items[$sId]['price']) ? $items[$sId]['price'] : $bookingServices->findByService($sId)->getPrice();
				}

				if( is_array( $atId ) ){
					$items[$sId] = array_merge($atId,array(
						'price' => $price - $discountValues[$sId]
					));
				} else{
					$items[$sId] = array( 'service' => $sId,
											  'attendant' => $atId,
											  'price' => $price );
				}
				$bookingServices->findByService($sId)->setPrice($items[$sId]['price']);
		    }
		    $first = false;
		}

		return $items;
	}

	public function hook_api_pre_eval($booking, $discounts){
		if(!$this->plugin->getSettings()->get('enable_discount_system')){
			return;
		}
		$old_discounts = SLB_Discount_Helper_Booking::getBookingDiscounts($booking);
		if(!is_array($discounts)){
			$discounts = $discounts instanceof SLB_Discount_Wrapper_Discount
						? array($discounts) 
						: (
							intval($discounts) 
							? array(new SLB_Discount_Wrapper_Discount($discountId))
							: array()
						);
		}
		$discounts = array_map(array($this, 'createDiscount'), $discounts);
		$dRepo = $this->plugin->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT);
        if(!empty(array_intersect($old_discounts, $discounts))){
            $old_discounts = array();
        }
		$discounts_to_decrement = array_diff($old_discounts, $discounts);
		$discounts_to_increment = array_diff($discounts, $old_discounts);
		if(empty($discounts_to_decrement) && empty($discounts_to_increment)){
			return;
		}

		$bookingServices = $booking->getBookingServices();
		$countServices = $booking->getCountServices();
		// Reset prices for services
		$amount = 0;
		foreach($bookingServices->getItems() as $bookingService){
			$service = $bookingService->getService();
			$price = $service->getPrice() * $bookingService->getCountServices();
			$amount += $price;
			$bookingService->setPrice($price);
		}
		$booking->setMeta('amount', $amount);
		
		if(!empty($discounts_to_decrement)){
			// Remove data about old discounts
			foreach(['discount', 'discount_amount', 'discount_score'] as $k){
				delete_post_meta($booking->getId(), '_'.SLN_Plugin::POST_TYPE_BOOKING.'_'.$k);
			}
			foreach($discounts_to_decrement as $discount){
				$discount = $dRepo->create($discount);
				$discount->decrementUsagesNumber($booking->getUserId());
				$discount->decrementTotalUsagesNumber();
				delete_post_meta($booking->getId(), '_'.SLN_Plugin::POST_TYPE_BOOKING.'_discount_'.$discountId);
			}
		}
		$data = array('discounts' => array());
		if(!empty($discounts_to_increment)){
			// Calculete discount data
			foreach($discounts_to_increment as $discount){
				$data['discounts'][] = $discount->getId();
				$data['discount_'.$discount->getId()] = 1;
				if(empty($data['discount_amount']) || !isset($data['discount_amount'])){
					$data['discount_amount'] = $discount->applyDiscountToBookingServices($bookingServices, true, $booking->getAttendantsIds());
				}else{
					foreach($discount->applyDiscountToBookingServices($bookingServices, true, $booking->getAttendantsIds()) as $sId => $value){
						$data['discount_amount'][$sId] += $value;
					}
				}
				$rules = $discount->getDiscountRules();
				if (!empty($rules)) {
					$discountScores = array();
					foreach($rules as $rule) {
						if ($rule['mode'] === 'score') {
							$discountScores[] = (int)$rule['score_number'];
						}
					}
					if(!empty($discountScores)){
						$discountScores = array(max($discountScores));
					}
					$data["discount_score"] = array_merge(isset($data["discount_score"]) ? $data["discount_score"] : array(), $discountScores);
				}
				//$discount->incrementUsagesNumber($booking->getUserId());
				//$discount->incrementTotalUsagesNumber();
			}
			// Calculate new service price
			foreach($bookingServices->getItems() as $bookingService){
				$serviceID = $bookingService->getService()->getId();
				$price = $bookingService->getPrice() - $data['discount_amount'][$serviceID];
				$bookingService->setPrice($price);
			}
		}
		$data['services'] = $bookingServices->toArrayRecursive();
		foreach($data as $key => $value){
			$booking->setMeta($key, $value);
		}
	}

	public function hook_admin_init() {
		new SLB_Discount_Metabox_Discount($this->getSlnPlugin(), self::POST_TYPE_DISCOUNT);
	}

	public function hook_admin_enqueue_scripts() {
		wp_enqueue_script('admin-discount', SLN_PLUGIN_URL.'/js/discount/admin-discount.js', array('jquery'), false, true);
	}

	public function hook_wp_enqueue_scripts() {
		wp_enqueue_script('salon-discount', SLN_PLUGIN_URL.'/js/discount/salon-discount.js', array('jquery'), false, true);
	}

	public function hook_isSalonPage($ret) {
		global $pagenow, $post;
		$page = sanitize_text_field( $pagenow );
		$_post = $post;
		return (
			$ret ||
	        isset($page) && $page === self::POST_TYPE_DISCOUNT ||
			!empty($_post) && get_post_type($_post) === self::POST_TYPE_DISCOUNT
		);
	}

	public function hook_booking_builder_getTotal($total, $bb){
		$discountData = $bb->get('discount');
		if(!empty($discountData)){
			$total -= $discountData['amount'];
		}
		return $total;
	}

	/**
	 * @param SLN_Wrapper_Booking_Builder $bb
	 */
	public function hook_booking_builder_create($bb) {
		$discountData = $bb->get('discount');

        if (empty($discountData)) {
            $discountItems = SLB_Discount_Helper_DiscountItems::buildDiscountItems();
            $discount      = $discountItems->getDiscountForBB($bb);
            if($discount){
                $discountValue = $discount->applyDiscountToBookingServices($bb->getBookingServices(), false, $bb->getAttendantsIds());
                if($discountValue){
                    $discount      = $discountItems->getDiscountForBB($bb);
                    $discountData['id'] = $discount->getId();
                }
            }
        }
		if (!empty($discountData)) {
			$discountId = $discountData['id'];
			$items      = $bb->get('services');

			if ($items) {
				$discount        = $this->createDiscount($discountId);
				$bookingServices = $bb->getBookingServices();
				$discountValues  = $discount->applyDiscountToBookingServices($bookingServices, true, $bb->getAttendantsIds());
				foreach($items as $i => $item) {
                    if (is_array($item) && array_intersect(array_keys($item), array('service', 'attendant', 'price', 'duration', 'break_duration', 'break_duration_data', 'resource'))) {
                        $sId = $item['service'];
                        $atId = $item['attendant'];
                    } else {
                        $sId = $i;
                        $atId = $item;
                    }
					$price       = $bookingServices->findByService($sId)->getPrice();
					$items[$sId] = array(
						'service'   => $sId,
						'attendant' => $atId,
						'price'     => $price - $discountValues[$sId],
					);
				}

                $discountScores = array();

                $rules = $discount->getDiscountRules();
                if (!empty($rules)) {
                    foreach($rules as $rule) {
                        if ($rule['mode'] === 'score') {
                            $discountScores[] = (int)$rule['score_number'];
                        }
                    }
					if(!empty($discountScores)){
						$discountScores = array(max($discountScores));
					}
                }

				$bb->set('services', $items);
				$bb->set("discount_{$discountId}", true);
				$bb->set("discount_amount", $discountValues);
				$bb->set("discounts", array($discountId));
                $bb->set('discount_score', $discountScores);

				$discount->incrementUsagesNumber(get_current_user_id());
				$discount->incrementTotalUsagesNumber();
			}
		}

		$bb->set('discount', null);
	}

	/**
	 * @param SLN_Shortcode_Salon_SummaryStep $step
	 * @param SLN_Wrapper_Booking_Builder $bb
	 */
	public function hook_summary_dispatchForm_before_booking_creation($step, $bb) {
		$discountData = $bb->get('discount');
		if (!empty($discountData)) {
			$discountId = $discountData['id'];
			$discount   = $this->createDiscount($discountId);

			$errors     = $discount->validateDiscountFullForBB($bb);
			if (!empty($errors)) {
				$bb->set('discount', null);
				$bb->save();
				$step->addError(reset($errors));
			}
		}
	}

	/**
	 * @param SLN_Wrapper_Booking $bb
	 * @param int $size
	 */
	public function hook_summary_before_total_amount($bb, $size) {
		$plugin   = $this->plugin;
        $discountAmount = $bb->getMeta('discount_amount');
        $discountAmount = is_array($discountAmount) ? $discountAmount : [];

		if (!empty($discountAmount)) {
			$discountValue = array_sum($discountAmount);
		} else {
			$discountValue = 0;
		}

		echo $plugin->loadView('shortcode/_salon_summary_before_total_amount', compact('discountValue', 'size'));
	}

	/**
	 * @param SLN_Wrapper_Booking_Builder $bb
	 * @param int $size
	 */
	public function hook_summary_after_total_amount($bb, $size) {
		$plugin   = $this->plugin;

		echo $plugin->loadView('shortcode/_salon_summary_after_total_amount', compact('size'));
	}

	public function hook_history_nav(){
		echo '<li class="sln-account__nav__item sln-account__nav__discounts" role="presentation"><a href="#sln-account__discount__content" data-target="#sln-account__discount__content" aria-controls="sln-account__discount__content" role="tab" data-toggle="tab"><span>'. esc_html__('Discounts', 'salon-booking-system').'</span></a></li>';
	}

	public function hook_history_content($booking_history){
		$customer = new SLN_Wrapper_Customer(get_current_user_id(), false);
		echo $this->plugin->loadView('shortcode/_salon_my_account_content', compact('booking_history', 'customer'));
	}

	/**
	 * @param string $label
	 * @param SLN_Wrapper_Booking $booking
	 *
	 * @return string
	 */
	public function hook_booking_total_amount_label($label, $booking) {
		if (SLB_Discount_Helper_Booking::hasAppliedDiscount($booking)) {
			return __('Discounted price', 'salon-booking-system');
		}

		return $label;
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 */
	public function hook_booking_total_amount_row($booking) {
		$plugin = $this->plugin;

		if (SLB_Discount_Helper_Booking::hasAppliedDiscount($booking)) {
			$discounts = SLB_Discount_Helper_Booking::getBookingDiscounts($booking);
			echo $plugin->loadView('metabox/_booking_total_amount_row', compact('discounts'));
		}
	}

	public function get_discounts_html_calc_booking_total($html) {

	    $discountIds = !empty($_POST['_sln_booking_discounts']) ? $_POST['_sln_booking_discounts'] : array();

	    if (empty($discountIds)) {
		return $html;
	    }

	    $discounts = array();
	    foreach ($discountIds as $discountId) {
		$discounts[$discountId] = new SLB_Discount_Wrapper_Discount($discountId);
	    }

	    return $this->plugin->loadView('metabox/_booking_total_amount_row', compact('discounts'));
	}

	/**
	 * @param SLN_Wrapper_Booking $booking
	 * @param string $oldStatus
	 * @param string $newStatus
	 */
	public function hook_booking_setStatus($booking, $oldStatus, $newStatus)
	{
		if ($oldStatus !== SLN_Enum_BookingStatus::CANCELED && $newStatus === SLN_Enum_BookingStatus::CANCELED) {
			if (SLB_Discount_Helper_Booking::hasAppliedDiscount($booking)) {
				$pt = $booking->getPostType();

				foreach(SLB_Discount_Helper_Booking::getBookingDiscounts($booking) as $discount) {
					$discount->decrementUsagesNumber($booking->getUserId());
					$discount->decrementTotalUsagesNumber();
					delete_post_meta($booking->getId(), "_{$pt}_discount_{$discount->getId()}");
				}

				$discountAmount = $booking->getMeta('discount_amount');

				$bookingServicesArray = $booking->getBookingServices()->toArrayRecursive();
				foreach($bookingServicesArray as &$item) {
					$serviceId      = $item['service'];
					$item['price'] += isset($discountAmount[$serviceId]) ? $discountAmount[$serviceId] : 0;
				}
				unset($item);
				$booking->setMeta('services', $bookingServicesArray);
				$booking->evalBookingServices();
				$booking->evalTotal();

				delete_post_meta($booking->getId(), "_{$pt}_discount_amount");
				delete_post_meta($booking->getId(), "_{$pt}_discounts");
                delete_post_meta($booking->getId(), "_{$pt}_discount_score");
			}
		}
	}

	public function hook_mail_summary_details($booking) {
		$plugin = $this->plugin;

		if (SLB_Discount_Helper_Booking::hasAppliedDiscount($booking)) {
			$discounts  = SLB_Discount_Helper_Booking::getBookingDiscounts($booking);
			$_discounts = array();
			foreach ($discounts as $discount) {
                            $discountAmount = $discount->getAmount();
                            if (!$discountAmount) {
                                continue;
                            }
			    $_discounts[] = $discount->getAmountString(true);
			}
			$discountText = implode(', ', $_discounts);
			echo $plugin->loadView('metabox/_mail_summary_details', compact('discountText'));
		}
	}

	public function hook_mail_special_offer($booking, $customer){
		$criteria = array(
			'@wp_query' => array(
				'meta_query' => array(
					array(
						'key'   => '_' . SLB_Discount_Plugin::POST_TYPE_DISCOUNT . '_email_notify',
						'value' => 1,
					),
				),
			),
                        'post_status' => 'publish',
		);
		$discounts = $this->plugin->getRepository(SLB_Discount_Plugin::POST_TYPE_DISCOUNT)->get($criteria);
		if(empty($discounts)){
			return;
		}
		foreach($discounts as $iter => $discount){
			$error = $discount->validateDiscountForMail((new DateTime())->getTimestamp(), $customer);
			if(!empty($error)){
				unset($discounts[$iter]);
			}
		}
		if(empty($discounts)){
			return;
		}
		$settings = $this->plugin->getSettings();
		echo $this->plugin->loadView('metabox/_mail_special_offer', compact('discounts', 'settings'));
	}

	public function get_discount_amount_ajaxcalendar_booking($discount, $booking) {
		$plugin = $this->plugin;

		if (SLB_Discount_Helper_Booking::hasAppliedDiscount($booking)) {
			$discounts  = $booking->getMeta('discount_amount');
			$_discount  = 0;
			foreach ($discounts as $discount) {
			    $_discount += $discount;
			}
			return $_discount;
		}

		return $discount;
	}

    public function get_fidelity_score_discounts_score($score, $customer) {

        $plugin     = $this->plugin;
        $bookings = $customer->getCompletedBookings();

        foreach($bookings as $booking) {
            if (SLB_Discount_Helper_Booking::hasAppliedDiscount($booking)) {
                $discountScores = $booking->getMeta('discount_score');
                $discountScores = $discountScores && is_array($discountScores) ? $discountScores : array();
                $score += array_sum($discountScores);
            }
        }

		return $score;
	}

	/**
	 * @param $discount
	 * @return SLB_Discount_Wrapper_Discount
	 * @throws Exception
	 */
	public function createDiscount($discount)
	{
		if($discount instanceof SLB_Discount_Wrapper_Discount){
			return $discount;
		}
		return $this->getSlnPlugin()->getRepository(self::POST_TYPE_DISCOUNT)->create($discount);
	}

	public function getSlnPlugin() {
		return $this->plugin;
	}

        public function ajax()
        {
            SLN_TimeFunc::startRealTimezone();

            $method = sanitize_text_field(wp_unslash( $_REQUEST['method'] ));

            $className = 'SLB_Discount_Action_Ajax_'.ucwords($method);

            if ( ! class_exists($className) ) {
                throw new Exception("discount ajax method not found '$method'");
            }

            SLN_Plugin::addLog('discount calling ajax '.$className);

            $obj = new $className($this->getSlnPlugin());

            $ret = $obj->execute();

            SLN_Plugin::addLog("$className returned:\r\n".wp_json_encode($ret));

            if (is_array($ret)) {
                header('Content-Type: application/json');
                echo wp_json_encode($ret);
            } elseif (is_string($ret)) {
                echo $ret;
            } else {
                throw new Exception("no content returned from $className");
            }

            exit();
        }
}