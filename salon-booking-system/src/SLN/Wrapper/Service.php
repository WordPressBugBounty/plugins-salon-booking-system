<?php

class SLN_Wrapper_Service extends SLN_Wrapper_Abstract implements SLN_Wrapper_ServiceInterface
{
    const _CLASS = 'SLN_Wrapper_Service';

    private $availabilityItems;
    private $attendants;

    public function getPostType()
    {
        return SLN_Plugin::POST_TYPE_SERVICE;
    }

    function getPrice()
    {
        $ret = $this->getMeta('price');
        $ret = empty($ret) ? 0 : floatval($ret);
        $settings = SLN_Plugin::getInstance()->getSettings();

        return $ret;
    }


    function getUnitPerHour()
    {
        $ret = $this->getMeta('unit');
        $ret = empty($ret) ? 0 : intval($ret);

        return $ret;
    }

    /**
     * Get maximum units a customer can select for variable duration
     * Falls back to getUnitPerHour() for backward compatibility
     * 
     * @return int
     */
    function getMaxVariableDuration()
    {
        $ret = $this->getMeta('max_variable_duration');
        
        // If not set, fall back to unit value for backward compatibility
        if (empty($ret)) {
            return $this->getUnitPerHour();
        }
        
        return intval($ret);
    }

    /**
     * Whether customers can book this service multiple times in a single
     * reservation (Fase 1 "quantity" feature, e.g. a haircut for parent + child).
     * Independent from variable duration; both reuse the same count plumbing.
     *
     * @return bool
     */
    function isQuantityEnabled()
    {
        $ret = $this->getMeta('enable_quantity');
        $ret = empty($ret) ? false : true;

        // PRO-only feature: always disabled in the free edition.
        return defined("SLN_VERSION_PAY") ? $ret : false;
    }

    /**
     * Maximum quantity a customer can select for this service. Per product
     * decision the limit is the service "Units per session" value; falls back
     * to 10 when that field is unset (0).
     *
     * @return int
     */
    function getMaxQuantity()
    {
        $unit = $this->getUnitPerHour();

        return $unit > 0 ? $unit : 10;
    }

    function getDuration()
    {
        $settings = SLN_Plugin::getInstance()->getSettings();
        $ret = $this->getMeta('duration');
        if (empty($ret) || 'basic' === $settings->getAvailabilityMode()) {
            $ret = '00:00';
        }
        $ret = SLN_Func::filter($ret, 'time');
        $ret = SLN_Func::getMinutesFromDuration($ret)*60;
        $date = new SLN_DateTime('@'.$ret);

        return $date;
    }

    function getBreakDuration()
    {
        $ret = $this->getMeta('break_duration');
        if (empty($ret)) {
            $ret = '00:00';
        }

        if(intval($ret) == $ret){
            $ret = intval($ret);
            $ret = SLN_Func::filter(floor($ret/60). ':'. $ret%60, 'time');
        }else{
            $ret = SLN_Func::filter($ret, 'time');
        }

        return new SLN_DateTime('1970-01-01 '.$ret);
    }

    function getTotalDuration()
    {
        $duration = $this->getDuration();
        $break    = $this->getBreakDuration();
        return new SLN_DateTime('1970-01-01 '.SLN_Func::convertToHoursMins(SLN_Func::getMinutesFromDuration($duration->format('H:i')) + SLN_Func::getMinutesFromDuration($break->format('H:i'))));
    }

    function isSecondary()
    {
        $ret = $this->getMeta('secondary');
        $ret = empty($ret) ? false : ($ret ? true : false);

        return $ret;
    }

    function isExclusive()
    {
        $ret = $this->getMeta('exclusive');
        $ret = empty($ret) ? false : ($ret ? true : false);

        return $ret;
    }

    function isHideOnFrontend()
    {
        $ret = $this->getMeta('hide_on_frontend');
        $ret = empty($ret) ? false : ($ret ? true : false);

        return $ret;
    }

    function getPosOrder()
    {
        $ret = $this->getMeta('order');
        $ret     = empty($ret) ? 0 : $ret;

        return $ret;
    }

    function getExecOrder()
    {
        $ret = $this->getMeta('exec_order');
        $ret = empty($ret) || 1 > $ret || 10 < $ret ? 1 : $ret;

        return $ret;
    }

    public function getAttendantsIds()
    {
        $ret = array();
        foreach ($this->getAttendants() as $attendant) {
            $ret[] = $attendant->getId();
        }

        return $ret;
    }

    /**
     * @return SLN_Wrapper_Attendant[]
     */
    public function getAttendants()
    {
        if(!isset($this->attendants)) {
            if ($this->isAttendantsEnabled()) {
                /** @var SLN_Repository_AttendantRepository $repo */
                $repo = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);

                $this->attendants = $repo->findByService($this);
            } else {
                $this->attendants = array();
            }
        }
        return $this->attendants;
    }

    public function isAttendantsEnabled() {
        $ret = $this->getMeta('attendants');
        $ret     = empty($ret) ? 1 : !$ret;

        return $ret;
    }

	public function isExecutionParalleled() {
		$ret = $this->getMeta('parallel_exec');
		$ret = empty($ret) ? false : ($ret ? true : false);

		return $ret;
	}

	/**
	 * Services this one may share a start time with.
	 * An empty list means "every other service in the booking" (historical behaviour).
	 *
	 * @return int[]
	 */
	public function getParallelServiceIds() {
		$ret = $this->getMeta('parallel_services');
		if (!is_array($ret)) {
			return array();
		}
		$ids = array();
		foreach ($ret as $id) {
			$id = intval($id);
			if ($id > 0 && $id !== intval($this->getId())) {
				$ids[$id] = $id;
			}
		}

		return array_values($ids);
	}

	/**
	 * Which services in one booking start at the booking start time.
	 *
	 * Parallel execution with an empty partner list keeps the historical behaviour:
	 * the service always starts with the booking. A partner list limits that to
	 * bookings that also contain one of those services, and pulls that partner
	 * onto the same start time so the pair is counted as the longer duration.
	 *
	 * partner_scoped is true when that shared start comes from a partner list.
	 * An empty list stays partner_scoped false, so existing salons are unchanged.
	 *
	 * @param int[] $serviceIds
	 * @return array{parallel: array<int,bool>, partner_scoped: array<int,bool>}
	 */
	public static function resolveParallelExecution(array $serviceIds) {
		$ids = array();
		foreach ($serviceIds as $id) {
			$id = intval($id);
			if ($id > 0) {
				$ids[$id] = $id;
			}
		}

		$enabled = array();
		$partnersById = array();
		foreach ($ids as $id) {
			$service = apply_filters(
				'sln.booking_services.buildService',
				SLN_Plugin::getInstance()->createService($id)
			);
			$enabled[$id] = $service && method_exists($service, 'isExecutionParalleled') && $service->isExecutionParalleled();
			$partnersById[$id] = ($service && method_exists($service, 'getParallelServiceIds'))
				? $service->getParallelServiceIds()
				: array();
		}

		return self::applyParallelRules($ids, $enabled, $partnersById);
	}

	/**
	 * @param array<int,int> $ids service id => service id
	 * @param array<int,bool> $enabled parallel execution checkbox
	 * @param array<int,int[]> $partnersById partner lists; empty means every service
	 * @return array{parallel: array<int,bool>, partner_scoped: array<int,bool>}
	 */
	public static function applyParallelRules(array $ids, array $enabled, array $partnersById) {
		$parallel = array();
		$legacyAlways = array();
		foreach ($ids as $id) {
			$parallel[$id] = false;
			$legacyAlways[$id] = false;
			if (empty($enabled[$id])) {
				continue;
			}
			$partners = isset($partnersById[$id]) ? $partnersById[$id] : array();
			if (empty($partners)) {
				$parallel[$id] = true;
				$legacyAlways[$id] = true;
				continue;
			}
			foreach ($partners as $partnerId) {
				if (isset($ids[$partnerId])) {
					$parallel[$id] = true;
					break;
				}
			}
		}

		foreach ($ids as $id) {
			if (empty($parallel[$id]) || !empty($legacyAlways[$id])) {
				continue;
			}
			$partners = isset($partnersById[$id]) ? $partnersById[$id] : array();
			foreach ($partners as $partnerId) {
				if (isset($ids[$partnerId])) {
					$parallel[$partnerId] = true;
				}
			}
		}

		$partnerScoped = array();
		$partnerRules = false;
		foreach ($ids as $id) {
			$partnerScoped[$id] = !empty($parallel[$id]) && empty($legacyAlways[$id]);
			if (!empty($enabled[$id]) && !empty($partnersById[$id])) {
				$partnerRules = true;
			}
		}

		return array(
			'parallel' => $parallel,
			'partner_scoped' => $partnerScoped,
			'partner_rules' => $partnerRules,
		);
	}

	/**
	 * Booking length from sequential and parallel minutes.
	 *
	 * Parallel lines and the first sequential line all start at the booking start.
	 * Without partner lists the historical sum is kept so existing salons see no
	 * change. With a partner list, a service whose partners are absent runs
	 * sequentially next to any legacy parallel line, so the overlap is counted once.
	 *
	 * @param int  $sequentialMinutes
	 * @param int  $parallelMaxMinutes
	 * @param bool $partnerRules
	 * @return int
	 */
	public static function combineParallelDurations($sequentialMinutes, $parallelMaxMinutes, $partnerRules) {
		return $partnerRules
			? max($sequentialMinutes, $parallelMaxMinutes)
			: $sequentialMinutes + $parallelMaxMinutes;
	}

    function isNotAvailableOnDate(SLN_DateTime $date)
    {
        $ret = !$this->getAvailabilityItems()->isValidDatetimeDuration($date, $this->getDuration());
        return $ret;
    }

    public function isMultipleAttendantsForServiceEnabled(){
        $res = $this->getMeta('multiple_attendants_for_service');
        return (!empty($res) && defined('SLN_VERSION_PAY')) ? $res : false;
    }

    public function getCountMultipleAttendants(){
        $res = $this->getMeta('multiple_count_attendants');
        return !empty($res) ? intval($res) : 1;
    }

    public function getNotAvailableString()
    {
        return implode('<br/>',$this->getAvailabilityItems()->toArray());
    }

    public function getName()
    {
        $title = $this->getTitle();
        return $title ? $title : 'n.d.';
    }

    public function getContent()
    {
        return $this->getExcerpt();
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function isOffsetEnabled(){
        $ret = $this->getMeta('offset_for_service');
        return !empty($ret) && isset($ret) && $ret && defined('SLN_VERSION_PAY') && SLN_VERSION_PAY;
    }

    public function getOffsetInterval(){
        $ret = $this->getMeta('offset_for_service_interval');
        if($this->isOffsetEnabled() && !$this->isLockEnabled()){
            return !empty($ret) ? intval($ret) : 0;
        }
        return 0;
    }

    public function isLockEnabled(){
        $ret = $this->getMeta('lock_for_service');
        return !empty($ret) && isset($ret) && $ret && defined('SLN_VERSION_PAY') && SLN_VERSION_PAY;
    }

    public function getLockInterval(){
        if(!$this->isLockEnabled())
            return 0;
        $ret = $this->getMeta('lock_for_service_interval');
        return !empty($ret) ? intval($ret) * 60 : 0;

    }

    /**
     * @return SLN_Helper_AvailabilityItems
     */
    function getAvailabilityItems()
    {
        if (!isset($this->availabilityItems)) {
            $this->availabilityItems = new SLN_Helper_AvailabilityItems($this->getMeta('availabilities'));
        }
        return $this->availabilityItems;
    }

    function getServiceCategory()
    {
        $post_terms = get_the_terms($this->getId(), SLN_Plugin::TAXONOMY_SERVICE_CATEGORY);

        if ($post_terms) {
            return new SLN_Wrapper_ServiceCategory($post_terms[0]);
        }

        return null;
    }

    function getBreakDurationData()
    {
        $ret = $this->getMeta('break_duration_data');
        if (empty($ret)) {
            $ret = array(
                'from' => 0,
                'to'   => SLN_Func::getMinutesFromDuration($this->getBreakDuration())
            );
        }

        return $ret;
    }

    function getVariablePriceEnabled() {
        return defined("SLN_VERSION_PAY") ? $this->getMeta('variable_price_enabled') && !$this->isMultipleAttendantsForServiceEnabled() : false;
    }

    function getVariablePrice($id) {
        $prices = $this->getMeta('variable_price') ? $this->getMeta('variable_price') : array();
        if($this->isMultipleAttendantsForServiceEnabled()){
            return $this->getPrice();
        }
        $price = isset($prices[$id]) && !empty($prices[$id]) ? $prices[$id] : $this->getPrice();
        return $price;
    }

    function isVariableDuration()
    {
        $ret = $this->getMeta('variable_duration');
        $ret = empty($ret) ? false : ($ret ? true : false);

        return defined("SLN_VERSION_PAY") ? $ret : false;
    }

    /**
     * @return SLN_Wrapper_Resource[]
     */
    public function getResources()
    {
        if(!isset($this->resources)) {
            /** @var SLN_Repository_ResourceRepository $repo */
            $repo = SLN_Plugin::getInstance()->getRepository(SLN_Plugin::POST_TYPE_RESOURCE);
            $this->resources = $repo->findByService($this);
        }
        return $this->resources;
    }

}
