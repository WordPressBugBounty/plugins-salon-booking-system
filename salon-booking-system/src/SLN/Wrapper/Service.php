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
        $object = SLN_Helper_Multilingual::isMultilingual()  ? $this->translationObject : $this->object;
        if ($object) {
            return $this->getTitle();
        } else {
            return 'n.d.';
        }
    }

    public function getContent()
    {
        $object = SLN_Helper_Multilingual::isMultilingual()  ? $this->translationObject : $this->object;
        if ($object) {
            if(isset($object->post_excerpt))
            return $object->post_excerpt;
        }
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
