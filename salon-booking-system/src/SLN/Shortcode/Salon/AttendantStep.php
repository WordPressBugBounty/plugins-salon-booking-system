<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Shortcode_Salon_AttendantStep extends SLN_Shortcode_Salon_Step
{

    protected function dispatchForm()
    {

        if(isset($_POST['sln'])){
            $attendants                 = isset($_POST['sln']['attendants']) ? array_map('intval',$_POST['sln']['attendants']) : array();
            $attendant                 = isset($_POST['sln']['attendant']) ? sanitize_text_field(wp_unslash($_POST['sln']['attendant'])) : false;
        }
        $isMultipleAttSelection = $this->getPlugin()->getSettings()->isMultipleAttendantsEnabled();
        $bb                     = $this->getPlugin()->getBookingBuilder();
        $ah                     = $this->getPlugin()->getAvailabilityHelper();
        $ah->setDate($bb->getDateTime());
        $bb->removeAttendants();

        if(empty($attendant) && empty($attendants) && $this->getPlugin()->getSettings()->isFormStepsAltOrder() && isset($_POST['attendant_auto']) && $_POST['attendant_auto'] !== true){ return true; }

        $bservices = $bb->getAttendantsIds();
        $date      = $bb->getDateTime();

        if ($isMultipleAttSelection) {
            $ids = isset($attendants) ? $attendants : array();

            $ret = $this->dispatchMultiple($bservices, $date, $ids);
        } else {
            $id = isset($attendant) ? $attendant : null;

            $ret = $this->dispatchSingle($bservices, $date, $id);
        }

        if (is_array($ret)) {
            $bb->setServicesAndAttendants($ret);
        }

        if ($ret) {
            $bb->save();

            return true;
        } else {
            return false;
        }
    }

    public function dispatchMultiple($services, $date, $selected)
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        $ah = $this->getPlugin()->getAvailabilityHelper();
        $ah->setDate($date);
        $bookingServices = SLN_Wrapper_Booking_Services::build($services, $date, 0, $bb->getCountServices());

        $availAtts               = null;
        $availAttsForEachService = array();

        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            // Add null check to prevent fatal error
            if (!$service || !$service->isAttendantsEnabled()) {
                continue;
            }
            $tmp                                        = $ah->getAvailableAttsIdsForBookingService($bookingService);
            $availAttsForEachService[$service->getId()] = $tmp;
            if (empty($tmp)) {
                $this->addError(
                    esc_html(sprintf(
                        // translators: %s will be replaced by the service name
                        __('No one of the attendants isn\'t available for %s service', 'salon-booking-system'),
                        $service->getName()
                    ))
                );

                return false;
            } elseif (!empty($selected[$service->getId()])) {
                $attendantId  = $selected[$service->getId()];
                $hasAttendant = in_array($attendantId, $availAttsForEachService[$service->getId()]);
                if (!$hasAttendant) {
                    $attendant = $this->getPlugin()->createAttendant($attendantId);
                    $this->addError(
                        sprintf(
                            // translators: s%1$ will be replaced by attendant name, s%2$ will be replaced by service name, s%3$ will be replaced by booking time
                            esc_html__('Attendant %1$s isn\'t available for %2$s service at %3$s', 'salon-booking-system'),
                            $attendant->getName(),
                            $service->getName(),
                            $ah->getDayBookings()->getTime(
                                $bookingService->getStartsAt()->format('H'),
                                $bookingService->getStartsAt()->format('i')
                            )
                        )
                    );

                    return false;
                }
            }elseif($service->isMultipleAttendantsForServiceEnabled() && count($tmp) < intval($service->getCountMultipleAttendants())){
                $this->addError(
                    sprintf(
                        // translators: %1$s will be replaced by the service name, %2$s will be replaced by the service count multiple attendants
                        __('There are not enough attendants for %1$s service. Required for the service: %2$s', 'salon-booking-system'),
                        $service->getName(),
                        $service->getCountMultipleAttendants()
                    )
                );
                return false;
            }

        }

        $ret = array();
        $unavAttForParallelServices = array();

        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();

            // Add null check to prevent fatal error
            if (!$service || !$service->isAttendantsEnabled()) {
                if ($service) {
                    $ret[$service->getId()] = 0;
                }
                continue;
            }

            if (!empty($selected[$service->getId()])) {
                $attId = $selected[$service->getId()];
            } else {
                $availAtts = $availAttsForEachService[$service->getId()];
                if ($service->isExecutionParalleled()) {
                    $availAtts = array_values(array_diff($availAtts, $unavAttForParallelServices));
                }
                $index = mt_rand(0, count($availAtts) - 1);
                $attId = $availAtts[$index];
                $selected[$service->getId()] = $attId;
            }

            if (!$attId) {
                $this->addError(
                    sprintf(
                        // translators: s%1$ will be replaced by service name, s%1$ will be replaced by booking time
                        esc_html__('There is no attendants available for %1$s service at %2$s', 'salon-booking-system'),
                        $service->getName(),
                        $ah->getDayBookings()->getTime(
                            $bookingService->getStartsAt()->format('H'),
                            $bookingService->getStartsAt()->format('i')
                        )
                    )
                );

                return false;
            }
            if($service->isMultipleAttendantsForServiceEnabled()){
                $attId = array($attId);
                $countMultipleAtts = intval($service->getCountMultipleAttendants());
                foreach($availAttsForEachService[$service->getId()] as $availAttId){
                    if($availAttId === $selected[$service->getId()]){
                        continue;
                    }
                    if(count($attId) == $countMultipleAtts){
                        break;
                    }
                    $attId[] = $availAttId;
                }
            }

            $ret[$service->getId()] = $attId;

            if ($service->isExecutionParalleled() && !$service->isMultipleAttendantsForServiceEnabled()) {
                $unavAttForParallelServices[] = $attId;
            }elseif($service->isExecutionParalleled() && $service->isMultipleAttendantsForServiceEnabled()){
                $unavAttForParallelServices = array_merge($unavAttForParallelServices, $attId);
            }
        }

        return $ret;
    }

    public function dispatchSingle($services, $date, $selected)
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        $ah = $this->getPlugin()->getAvailabilityHelper();
        $ah->setDate($date);
        $bookingServices = SLN_Wrapper_Booking_Services::build($services, $date, 0, $bb->getCountServices());

        $availAtts = null;
        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            // Add null check to prevent fatal error
            if (!$service || !$service->isAttendantsEnabled()) {
                continue;
            }
            $availAtts = $ah->getAvailableAttendantForService($availAtts, $bookingService);

            if (empty($availAtts)) {
                $this->addError(
                    __('No one of the attendants isn\'t available for selected services', 'salon-booking-system')
                );

                return false;
            }
            // Add null/array check for PHP 8.x compatibility
            if($service->isMultipleAttendantsForServiceEnabled() && is_array($availAtts) && count($availAtts) < $service->getCountMultipleAttendants()){
                $this->addError(
                    sprintf(
                        // translators: %1$s will be replaced by the service name, %2$s will be replaced by the service count multiple attendants
                        __('There are not enough attendants for %1$s service. Required for the service: %2$s', 'salon-booking-system'),
                        $service->getName(),
                        $service->getCountMultipleAttendants()
                    )
                );
                return false;
            }
        }

        if (!$selected) {
            // Add null check for PHP 8.x compatibility
            if (is_array($availAtts) && count($availAtts)) {
                $index = mt_rand(0, count($availAtts) - 1);
                $attId = array_values($availAtts)[$index];
                $selected = $attId;
            } else {
                $attId = 0;
            }
        } else {
            $attId = $selected;
        }

        $ret = array();
        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();

            // Add null check to prevent fatal error
            if (!$service || !$service->isAttendantsEnabled()) {
                if ($service) {
                    $ret[$service->getId()] = 0;
                }
                continue;
            }

            if($service->isMultipleAttendantsForServiceEnabled()){
                $ret[$service->getId()] = array($attId);
                $countMultipleAtts = intval($service->getCountMultipleAttendants());
                foreach($availAtts as $availAttId){
                    if($selected == $availAttId){
                        continue;
                    }
                    if(count($ret[$service->getId()]) == $countMultipleAtts){
                        break;
                    }
                    $ret[$service->getId()][] = $availAttId;
                }
            }else{
                $ret[$service->getId()] = $attId;
            }
        }

        return $ret;
    }


    /**
     * @return SLN_Wrapper_Attendant[]
     */
    public function getAttendants()
    {
        if (!isset($this->attendants)) {
            /** @var SLN_Repository_AttendantRepository $repo */
            $repo             = $this->getPlugin()->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
            $this->attendants = $repo->sortByPos($repo->getAll());
            $this->attendants = apply_filters('sln.shortcode.salon.AttendantStep.getAttendants', $this->attendants);
        }

        return $this->attendants;
    }

    public function isValid()
    {
        $tmp = $this->getAttendants();
        $bb = $this->getPlugin()->getBookingBuilder();
        if($this->getPlugin()->getSettings()->get('skip_attendants_enabled') && $this->isSkipAttendants($tmp)){
            return true;
        }

        return (!empty($tmp)) && parent::isValid();
    }

    protected function isSkipAttendants($attendants){
        $bb = $this->getPlugin()->getBookingBuilder();
        $ah = $this->getPlugin()->getAvailabilityHelper();
        $ah->setDate($this->getPlugin()->getBookingBuilder()->getDateTime());
        $bookingServices = SLN_Wrapper_Booking_Services::build($bb->getAttendantsIds(), $bb->getDateTime(), 0, $bb->getCountServices());
        $validAttendants = array();
        if(!$this->getPlugin()->getSettings()->isMultipleAttendantsEnabled()){
            $services = $bb->getServices();
            foreach($attendants as $attendant){
                if(
                    empty(SLN_Shortcode_Salon_AttendantHelper::validateItem($bookingServices->getItems(), $ah, $attendant)) &&
                    $attendant->hasServices($services)
                ){
                    if(!empty($validAttendants)){
                        return false;
                    }
                    $validAttendants = $attendant;
                }
            }
            if(empty($validAttendants)){
                return false;
            }
            $ret = $this->dispatchSingle($bb->getAttendantsIds(), $bb->getDateTime(), $validAttendants->getId());
            if (is_array($ret)) {
                $bb->setServicesAndAttendants($ret);
                $bb->save();
            } else {
                return false;
            }
        }else{
            foreach($bookingServices as $bookingService){
                $service = $bookingService->getService();
                // Add null check to prevent fatal error
                if (!$service) {
                    return false;
                }
                
                foreach($attendants as $attendant){
                    if(
                        SLN_Shortcode_Salon_AttendantHelper::validateItem($bookingServices->getItems(), $ah, $attendant) &&
                        $attendant->hasService($service)
                    ){
                        if(isset($validAttendants[$service->getId()])){
                            return false;
                        }
                        $validAttendants[$service->getId()] = $attendant->getId();
                    }
                }
                if(!isset($validAttendants[$service->getId()])){
                    return false;
                }
            }
            $ret = $this->dispatchMultiple($bb->getAttendantsIds(), $bb->getDateTime(), $validAttendants);
            if (is_array($ret)) {
                $bb->setServicesAndAttendants($ret);
                $bb->save();
            } else {
                return false;
            }
        }
        return true;
    }

    public function defaultRenderSortIcon($icons, $iconUp, $iconDown){
        $iconUp = ''. $iconUp. '';
        $iconDown = ''. $iconDown. '';
        return array($iconUp, $iconDown);
    }

    public function renderSortIcon(){
        $iconUp = '<span class="sln-icon-sort sln-icon-sort--up"></span>';
        $iconDown = '<span class="sln-icon-sort sln-icon-sort--down"></span>';
        return apply_filters('sln.attendants.renderSortIcon', array($iconUp, $iconDown), $iconUp, $iconDown);
    }

    public function getTitleKey(){
        $isMultipleAttSelection = $this->getPlugin()->getSettings()->isMultipleAttendantsEnabled();
        $bb = $this->getPlugin()->getBookingBuilder();
        return $isMultipleAttSelection && count($bb->getServices()) > 1 ? 'Select your assistants' : 'Select your assistant';
    }

    public function getTitleLabel(){
        $isMultipleAttSelection = $this->getPlugin()->getSettings()->isMultipleAttendantsEnabled();
        $bb = $this->getPlugin()->getBookingBuilder();
        return $isMultipleAttSelection && count($bb->getServices()) > 1 ? __('Select your assistants', 'salon-booking-system') : __('Select your assistant', 'salon-booking-system');
    }

    public function isNeedTotal(){
        $bb = $this->getPlugin()->getBookingBuilder();
        $services = $bb->getServices();
        $showPrices = ($this->getPlugin()->getSettings()->get('hide_prices') != '1') ? true : false;

        if ($showPrices) {
            $_showPrices = false;
            foreach($services as $service) {
                if ($service->getVariablePriceEnabled()) {
                    $_showPrices = true;
                }
            }
            $showPrices = $_showPrices;
        }
        return $showPrices;
    }
}
