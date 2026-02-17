<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Shortcode_Salon_AttendantAltStep extends SLN_Shortcode_Salon_AttendantStep
{
    public function dispatchMultiple($services, $date, $selected)
    {
        // Performance monitoring - START
        $perfStartTime = microtime(true);
        $perfStartMemory = memory_get_usage();
        SLN_Plugin::addLog('[AttendantAltStep Performance] START dispatchMultiple - Memory: ' . round($perfStartMemory / 1024 / 1024, 2) . 'MB');
        
        $bb = $this->getPlugin()->getBookingBuilder();
        $ah = $this->getPlugin()->getAvailabilityHelper();
        $ah->setDate($date);
        $bookingServices = SLN_Wrapper_Booking_Services::build($services, $date, 0, $bb->getCountServices());

        $availAtts = null;
        $availAttsForEachService = array();

        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            // Add null check to prevent fatal error
            if (!$service || !$service->isAttendantsEnabled()) {
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
            // Add null check to prevent fatal error
            if (!$service || !$service->isAttendantsEnabled()) {
                if ($service) {
                    $ret[$service->getId()] = 0;
                }
                continue;
            }

            // Check if "Choose assistant for me" was selected (empty value)
            $isAutoAttendant = empty($selected[$service->getId()]);
            
            if ($isAutoAttendant) {
                SLN_Plugin::addLog('[AttendantAltStep] Choose assistant for me - service ' . $service->getId() . ' | multi_att=' . ($service->isMultipleAttendantsForServiceEnabled() ? $service->getCountMultipleAttendants() : 0));
                // SMART AVAILABILITY: When "Choose assistant for me" + feature enabled (DEFAULT)
                // Don't pre-assign random assistant - let date/time check ALL assistants
                // This prevents timeout issues and ensures optimal attendant selection
                if ($this->getPlugin()->getSettings()->isAutoAttendantCheckEnabled()) {
                    // Set to false (marker for auto-assignment after date/time selection)
                    $ret[$service->getId()] = false;
                    SLN_Plugin::addLog('[AttendantAltStep] Smart Availability enabled - deferring attendant assignment for service ' . $service->getId());
                    continue;
                }
                
                // LEGACY BEHAVIOR: Random assignment when feature disabled
                // WARNING: This can cause timeout with complex availability rules
                SLN_Plugin::addLog('[AttendantAltStep] Legacy random assignment mode - Smart Availability is disabled');
                
                // Pre-warm cache to improve performance
                $ah->getCachedDays();
                
                $errors = 1;
                $maxIterations = 100; // Increased from 50 to reduce timeout errors
                $iteration = 0;
                
                while (!empty($errors) && $iteration < $maxIterations) {
                    $iteration++;
                    $index = mt_rand(0, count($availAttsForEachService[$service->getId()]) - 1);
                    $attId = $availAttsForEachService[$service->getId()][$index];
                    $attendant = apply_filters('sln.booking_services.buildAttendant', new SLN_Wrapper_Attendant($attId));
                    $errors = SLN_Shortcode_Salon_AttendantHelper::validateItem($bookingServices->getItems(), $ah, $attendant);
                    
                    // Log progress every 10 iterations to detect timeout issues
                    if ($iteration % 10 == 0) {
                        SLN_Plugin::addLog('[AttendantAltStep] Legacy mode - iteration ' . $iteration . ' - still searching...');
                    }
                }
                
                // Check if we exceeded max iterations
                if ($iteration >= $maxIterations) {
                    SLN_Plugin::addLog('[AttendantAltStep] Max iterations reached - no available attendant found');
                    $this->addError(
                        sprintf(
                            // translators: %s will be replaced by the service name
                            __('No available assistant found for %s. Please try selecting a specific date or enable Smart Availability.', 'salon-booking-system'),
                            $service->getName()
                        )
                    );
                    return false;
                }
                
                SLN_Plugin::addLog('[AttendantAltStep] Found available attendant after ' . $iteration . ' iteration(s)');
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
        
        // Performance monitoring - END
        $perfEndTime = microtime(true);
        $perfEndMemory = memory_get_usage();
        $perfPeakMemory = memory_get_peak_usage();
        $perfExecutionTime = round($perfEndTime - $perfStartTime, 3);
        $perfMemoryUsed = round(($perfEndMemory - $perfStartMemory) / 1024 / 1024, 2);
        $perfPeakMemoryMB = round($perfPeakMemory / 1024 / 1024, 2);
        
        SLN_Plugin::addLog('[AttendantAltStep Performance] END dispatchMultiple - Execution time: ' . $perfExecutionTime . 's');
        SLN_Plugin::addLog('[AttendantAltStep Performance] END dispatchMultiple - Memory used: ' . $perfMemoryUsed . 'MB, Peak: ' . $perfPeakMemoryMB . 'MB');
        
        if ($perfExecutionTime > 5) {
            SLN_Plugin::addLog('[AttendantAltStep Performance] WARNING: Execution time exceeded 5 seconds - potential timeout risk');
        }
        
        return $ret;
    }

    public function dispatchSingle($services, $date, $selected)
    {
        // Performance monitoring - START
        $perfStartTime = microtime(true);
        $perfStartMemory = memory_get_usage();
        SLN_Plugin::addLog('[AttendantAltStep Performance] START dispatchSingle - Memory: ' . round($perfStartMemory / 1024 / 1024, 2) . 'MB');
        SLN_Plugin::addLog('[AttendantAltStep] dispatchSingle START | ' . date('H:i:s'));
        
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
        
        // Check if "Choose assistant for me" was selected (empty value)
        $isAutoAttendant = !$selected;
        
        if ($isAutoAttendant) {
            // SMART AVAILABILITY: When "Choose assistant for me" + feature enabled (DEFAULT)
            // Don't pre-assign random assistant - let date/time check ALL assistants
            // This prevents timeout issues and ensures optimal attendant selection
            if ($this->getPlugin()->getSettings()->isAutoAttendantCheckEnabled()) {
                // Return false for all services (marker for auto-assignment after date/time selection)
                $ret = array();
                foreach ($bookingServices->getItems() as $bookingService) {
                    $service = $bookingService->getService();
                    if ($service && $service->isAttendantsEnabled()) {
                        $ret[$service->getId()] = false;
                    } elseif ($service) {
                        $ret[$service->getId()] = 0;
                    }
                }
                SLN_Plugin::addLog('[AttendantAltStep] Smart Availability enabled (dispatchSingle) - deferring attendant assignment');
                return $ret;
            }
            
            // LEGACY BEHAVIOR: Random assignment when feature disabled
            // WARNING: This is a simplified random selection without validation
            SLN_Plugin::addLog('[AttendantAltStep] Legacy random assignment mode (dispatchSingle) - Smart Availability is disabled');
            
            // Add null check for PHP 8.x compatibility
            if (is_array($availAtts) && count($availAtts)) {
                $index = mt_rand(0, count($availAtts) - 1);
                $attId = array_values($availAtts)[$index];
                $selected = $attId;
            } else {
                $attId = 0;
            }
        }
        else {
            $attId = $selected;
        }
        SLN_Helper_Availability_AdminRuleLog::getInstance()->addAttendant($attId);

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
            if($service->isMultipleAttendantsForServiceEnabled() && !empty($attId)){
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
        
        // Performance monitoring - END
        $perfEndTime = microtime(true);
        $perfEndMemory = memory_get_usage();
        $perfPeakMemory = memory_get_peak_usage();
        $perfExecutionTime = round($perfEndTime - $perfStartTime, 3);
        $perfMemoryUsed = round(($perfEndMemory - $perfStartMemory) / 1024 / 1024, 2);
        $perfPeakMemoryMB = round($perfPeakMemory / 1024 / 1024, 2);
        
        SLN_Plugin::addLog('[AttendantAltStep Performance] END dispatchSingle - Execution time: ' . $perfExecutionTime . 's');
        SLN_Plugin::addLog('[AttendantAltStep Performance] END dispatchSingle - Memory used: ' . $perfMemoryUsed . 'MB, Peak: ' . $perfPeakMemoryMB . 'MB');
        
        if ($perfExecutionTime > 5) {
            SLN_Plugin::addLog('[AttendantAltStep Performance] WARNING: Execution time exceeded 5 seconds - potential timeout risk');
        }
        
        return $ret;
    }

}
