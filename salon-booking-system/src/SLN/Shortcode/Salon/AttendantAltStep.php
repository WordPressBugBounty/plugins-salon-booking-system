<?php

class SLN_Shortcode_Salon_AttendantAltStep extends SLN_Shortcode_Salon_AttendantStep
{
    public function dispatchMultiple($services, $date, $selected)
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        $ah = $this->getPlugin()->getAvailabilityHelper();
        $ah->setDate($date);
        $bookingServices = SLN_Wrapper_Booking_Services::build($services, $date, 0, $bb->getCountServices());

        $availAtts = null;
        $availAttsForEachService = array();

        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            if (!$service->isAttendantsEnabled()) {
                continue;
            }
            $tmp = $service->getAttendantsIds();
            $availAttsForEachService[$service->getId()] = $tmp;
            if (empty($tmp)) {
                $this->addError(
                    sprintf(
                        // translators: %s will be replaced by $service name,
                        esc_html__('No one of the attendants isn\'t available for %s service', 'salon-booking-system'),
                        $service->getName()
                    )
                );

                return false;
            } elseif (!empty($selected[$service->getId()])) {
                $attendantId = $selected[$service->getId()];
                $hasAttendant = in_array($attendantId, $availAttsForEachService[$service->getId()]);
                if (!$hasAttendant) {
                    $attendant = $this->getPlugin()->createAttendant($attendantId);
                    $this->addError(
                        sprintf(
                            // translators: %1$s will be replaced by the attendant name, %2$s will be replaced by the service name
                            __('Attendant %1$s isn\'t available for %2$s service', 'salon-booking-system'),
                            $attendant->getName(),
                            $service->getName()
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

        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            if (!$service->isAttendantsEnabled()) {
                $ret[$service->getId()] = 0;
                continue;
            }

            if (empty($selected[$service->getId()]) || (isset($_POST['attendant_auto']) && $_POST['attendant_auto'] !== true)) {
                $errors = 1;
                while (!empty($errors)) {
                    $index = mt_rand(0, count($availAttsForEachService[$service->getId()]) - 1);
                    $attId = $availAttsForEachService[$service->getId()][$index];
                    $attendant = apply_filters('sln.booking_services.buildAttendant', new SLN_Wrapper_Attendant($attId));
                    $errors = SLN_Shortcode_Salon_AttendantHelper::validateItem($bookingServices->getItems(), $ah, $attendant);
                }
                $selected[$service->getId()] = $attId;
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
                        SLN_Helper_Availability_AdminRuleLog::getInstance()->addAttendant($availAttId);
                    }
                }
            } else {
                $attId = $selected[$service->getId()];
                SLN_Helper_Availability_AdminRuleLog::getInstance()->addAttendant($attId);
            }

            $ret[$service->getId()] = $attId;
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
            if (!$service->isAttendantsEnabled()) {
                continue;
            }
            if (is_null($availAtts)) {
                $availAtts = $service->getAttendantsIds();
            }
            $availAtts = array_intersect($availAtts, $service->getAttendantsIds());
            if (empty($availAtts)) {
                $this->addError(
                    __('No one of the attendants isn\'t available for selected services', 'salon-booking-system')
                );

                return false;
            }
            if($service->isMultipleAttendantsForServiceEnabled() && count($availAtts) < $service->getCountMultipleAttendants()){
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
        if (!$selected || isset($_POST['attendant_auto']) && $_POST['attendant_auto'] == true) {
            $attId = 0;
        }
        else {
            $attId = $selected;
        }
        SLN_Helper_Availability_AdminRuleLog::getInstance()->addAttendant($attId);

        $ret = array();
        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();

            if (!$service->isAttendantsEnabled()) {
                $ret[$service->getId()] = 0;
                continue;
            }
            if($service->isMultipleAttendantsForServiceEnabled() && !empty($atId)){
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

}
