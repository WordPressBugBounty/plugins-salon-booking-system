<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch
class SLN_Shortcode_Salon_ResourceStep extends SLN_Shortcode_Salon_Step
{

    protected function dispatchForm()
    {
        if( ! $this->getPlugin()->getSettings()->isResourcesEnabled() ) {
            return true;
        }

        $bb = $this->getPlugin()->getBookingBuilder();
        $bb->removeResources();

        if(empty($_POST['set_resources'])) {
            return true;
        }

        $resources = $this->getResources();

        $services_resources = array();

        foreach ($bb->getServices() as $service) {
            foreach ($resources as $resource) {
                if (in_array($service->getId(), $resource->getServices())) {
                    if (!isset($services_resources[$service->getId()])) {
                        $services_resources[$service->getId()] = array();
                    }
                    $services_resources[$service->getId()][] = $resource;
                }
            }
        }

        if ( ! $services_resources ) {
            return true;
        }

        $bservices = $bb->getAttendantsIds();
        $date      = $bb->getDateTime();

        $ret = $this->dispatchMultiple($bservices, $date, $services_resources);

        if (is_array($ret)) {
            $bb->setResources($ret);
        }

        if ($ret) {
            $bb->save();
            return true;
        } else {
            return false;
        }
    }

    public function dispatchMultiple($services, $date, $availableResources, $selected = array())
    {
        $bb = $this->getPlugin()->getBookingBuilder();
        $ah = $this->getPlugin()->getAvailabilityHelper();
        $ah->setDate($date);
        $bookingServices = SLN_Wrapper_Booking_Services::build($services, $date, 0, $bb->getCountServices(), $bb->getResources());

        $availResources               = null;
        $availResourcesForEachService = array();

        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();
            if (empty($availableResources[$service->getId()])) {
                continue;
            }
            $tmp = $ah->getAvailableResourcesIdsForBookingService($bookingService);
            $availResourcesForEachService[$service->getId()] = $tmp;
            if (empty($tmp)) {
                $this->addError(
                    esc_html(sprintf(
                        // translators: %s will be replaced by the service name
                        __('No one of the resources isn\'t available for %s service', 'salon-booking-system'),
                        $service->getName()
                    ))
                );

                return false;
            } elseif (!empty($selected[$service->getId()])) {
                $resourceId  = $selected[$service->getId()];
                $hasResource = in_array($resourceId, $availResourcesForEachService[$service->getId()]);
                if (!$hasResource) {
                    $resource = $this->getPlugin()->createResource($resourceId);
                    $this->addError(
                        sprintf(
                            // translators: s%1$ will be replaced by resource name, %2$s will be replaced by service name, s%3$ will be replaced by booking time
                            esc_html__('Resource %1$s isn\'t available for %2$s service at %3$s', 'salon-booking-system'),
                            $resource->getName(),
                            $service->getName(),
                            $ah->getDayBookings()->getTime(
                                $bookingService->getStartsAt()->format('H'),
                                $bookingService->getStartsAt()->format('i')
                            )
                        )
                    );

                    return false;
                }
            }

        }

        $ret = array();

        foreach ($bookingServices->getItems() as $bookingService) {
            $service = $bookingService->getService();

            if (empty($availableResources[$service->getId()])) {
                continue;
            }

            if (!empty($selected[$service->getId()])) {
                $resourceId = $selected[$service->getId()];
            } else {
                $availResources = $availResourcesForEachService[$service->getId()];
                $index = mt_rand(0, count($availResources) - 1);
                $resourceId = $availResources[$index];
                $selected[$service->getId()] = $resourceId;
            }

            if (!$resourceId) {
                $this->addError(
                    sprintf(
                        // translators: s%1$ will be replaced by service name, %2$s will be replaced by booking time
                        esc_html__('There is no resources available for %1$s service at %2$s', 'salon-booking-system'),
                        $service->getName(),
                        $ah->getDayBookings()->getTime(
                            $bookingService->getStartsAt()->format('H'),
                            $bookingService->getStartsAt()->format('i')
                        )
                    )
                );

                return false;
            }

            $ret[$service->getId()] = $resourceId;
        }

        return $ret;
    }

    /**
     * @return SLN_Wrapper_Resource[]
     */
    public function getResources()
    {
        if (!isset($this->resources)) {
            /** @var SLN_Repository_ResourceRepository $repo */
            $this->resources = $this->getPlugin()->getRepository(SLN_Plugin::POST_TYPE_RESOURCE)->getAllEnabled();
            $this->resources = apply_filters('sln.shortcode.salon.ResourceStep.getResources', $this->resources);
        }

        return $this->resources;
    }

    public function isValid()
    {
        $tmp = $this->getResources();
        if( ! $this->getPlugin()->getSettings()->isResourcesEnabled() || empty($tmp) ) {
            return true;
        }
        return (!empty($tmp)) && parent::isValid();
    }

}
