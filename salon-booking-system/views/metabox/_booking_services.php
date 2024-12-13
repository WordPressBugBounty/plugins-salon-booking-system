<?php
// phpcs:ignoreFile WordPress.Security.EscapeOutput.OutputNotEscaped

$servicesData = array(0 => array(
    'title' => ', ' . __('Select a service', 'salon-booking-system'),
    'name' => ', ' . __('Select a service', 'salon-booking-system'),
    'price' => 0,
    'duration' => 0,
    'break_duration' => 0,
    'exec_order' => 0,
    'attendants' => array(0),
    'resources' => array(0),
));
$formatter = $plugin->format();
$isAttendants = $plugin->getSettings()->isAttendantsEnabled();
$isMultipleAttendants = $plugin->getSettings()->isMultipleAttendantsEnabled();
$isAttendants = $isAttendants || $booking->getAttendant();
$isMultipleAttendants = $isAttendants && ($isMultipleAttendants || (count($booking->getAttendants(true)) > 1));
/** @var SLN_Repository_ServiceRepository $sRepo */
$sRepo = $plugin->getRepository(SLN_Plugin::POST_TYPE_SERVICE);
$allServices = $sRepo->getAll();
$allServices = $sRepo->sortByExecAndTitleDESC($allServices);
$allServices = apply_filters('sln.shortcode.salon.ServicesStep.getServices', $allServices);

/** @var SLN_Repository_AttendantRepository $sRepo */
$sRepo = $plugin->getRepository(SLN_Plugin::POST_TYPE_ATTENDANT);
$allAttendants = $sRepo->getAll();
$allAttendants = apply_filters('sln.shortcode.salon.AttendantStep.getAttendants', $allAttendants);
$attendantsData = array();
foreach ($allAttendants as $attendant) {
    $attendantsData[$attendant->getId()] = $attendant->getName();
}
$allServicesSelectionArray = array(0 => '' . __('Select a service', 'salon-booking-system'));
foreach ($allServices as $service) {
    $serviceCategoryName = !empty($service->getServiceCategory()) ? $service->getServiceCategory()->getName() . ' sln 998877 slc ' : ' sln 998877 slc ';
    $allServicesSelectionArray[$service->getId()] = $serviceCategoryName . $service->getName() . ' sln 998877 slc ' .
        $formatter->money($service->getPrice()) . ' - ' .
        $service->getDuration()->format('H:i') . ' sln 998877 slc ';
    $servicesData[$service->getId()] = array(
        'title' => $serviceCategoryName . $service->getName() . ', ' . $formatter->money($service->getPrice()) . ' - ' . $service->getDuration()->format('H:i'),
        'name' => $service->getName(),
        'price' => $service->getPrice(),
        'duration' => SLN_Func::getMinutesFromDuration($service->getDuration()),
        'break_duration' => SLN_Func::getMinutesFromDuration($service->getBreakDuration()),
        'exec_order' => $service->getExecOrder(),
        'attendants' => $service->getAttendantsIds(),
        'isMultipleAttendants' => $service->isMultipleAttendantsForServiceEnabled(),
        'countMultipleAttendants' => $service->getCountMultipleAttendants(),
        'isAttendantsEnabled' => $service->isAttendantsEnabled(),
        'resources' => array_map(function ($i) {
            return $i->getId();
        }, $service->getResources()),
    );
}
$isResourcesEnabled = $plugin->getSettings()->isResourcesEnabled();

/** @var SLN_Repository_ResourceRepository $sRepo */
$sRepo = $plugin->getRepository(SLN_Plugin::POST_TYPE_RESOURCE);
$allResources = $sRepo->getAllEnabled();
$allResources = apply_filters('sln.shortcode.salon.ResourceStep.getResources', $allResources);
$resourcesData = array(0 => __('Select a resource', 'salon-booking-system'));
foreach ($allResources as $resource) {
    $resourcesData[$resource->getId()] = $resource->getTitle();
}
$bookingServices = $booking->getBookingServices()->getItems();
?>
<div id="sln_booking_services" class="form-group sln_meta_field row">
    <div class="col-xs-12">
        <div class="sln-booking-services-alerts">
            <span id="sln-alert-noservices"
                class="sln-alert sln-alert--warning sln-alert--inline <?php echo $bookingServices ? 'hide' : '' ?>"><?php echo esc_html__('No services addded yet', 'salon-booking-system') ?></span>
            <span id="sln-alert-no-duration"
                class="sln-alert sln-alert--error sln-alert--inline hide"><?php echo esc_html__('This service has no duration, change it or add another one!', 'salon-booking-system') ?></span>
            <span id="sln-alert-duration-exceeded"
                class="sln-alert sln-alert--error sln-alert--inline hide"><?php echo esc_html__('Total services duration exceeded, change or remove one!', 'salon-booking-system') ?></span>
        </div>
    </div>

    <?php ob_start(); ?>
    <div class="col-xs-12 sln-booking-service-line">
        <div class="sln-row sln-booking-service-line__content <?php if ('highend' == $plugin->getSettings()->getAvailabilityMode()) {
                                                                    echo ' sln-booking-service-line__content--highend';
                                                                }
                                                                if ($isResourcesEnabled) {
                                                                    echo ' sln-booking-service-line__content--resources';
                                                                } ?>">
            <div class="sln-booking-service--move-line">
                <div class="bar"></div>
                <div class="bar"></div>
                <div class="bar"></div>
            </div>
            <?php if ('highend' == $plugin->getSettings()->getAvailabilityMode()): ?>
                <div class="sln-booking-service--startend">
                    <div>
                        <h5 class="sln-booking-service-line__label"><?php esc_html_e('Start at', 'salon-booking-system') ?></h5>
                        <label class="time"></label>
                    </div>
                    <div>
                        <h5 class="sln-booking-service-line__label"><?php esc_html_e('End at', 'salon-booking-system') ?></h5>
                        <label class="time"></label>
                    </div>
                </div>
            <?php endif; ?>
            <div class="sln-booking-service--itemselection sln-select">
                <h5 class="sln-booking-service-line__label"><?php esc_html_e('Service', 'salon-booking-system') ?></h5>
                <?php SLN_Form::fieldSelect(
                    '_sln_booking[services][]',
                    $allServicesSelectionArray,
                    '__service_id__',
                    array(
                        'attrs' => array(
                            'data-price' => '__service_price__',
                            'data-duration' => '__service_duration__',
                            'data-break' => '__service_break_duration__',
                            'data-selection' => 'service-selected',
                        ),
                        'no_id' => true,
                    ),
                    true
                )
                ?>
                <?php SLN_Form::fieldText(
                    '_sln_booking[service][__service_id__]',
                    '__service_id__',
                    array('type' => 'hidden')
                )
                ?>
                <?php SLN_Form::fieldText(
                    '_sln_booking[price][__service_id__]',
                    '__service_price__',
                    array('type' => 'hidden')
                )
                ?>
                <?php SLN_Form::fieldText(
                    '_sln_booking[duration][__service_id__]',
                    '__service_duration__',
                    array('type' => 'hidden')
                )
                ?>
                <?php SLN_Form::fieldText(
                    '_sln_booking[break_duration][__service_id__]',
                    '__service_break_duration__',
                    array('type' => 'hidden')
                )
                ?>
            </div>
            <?php if ($isResourcesEnabled): ?>
                <div class="sln-booking-service--resources sln-select">
                    <h5 class="sln-booking-service-line__label"><?php esc_html_e('Resource', 'salon-booking-system') ?></h5>
                    <p class="sln-no-resources hide"><?php esc_html_e('No resources', 'salon-booking-system') ?></p>
                    <?php SLN_Form::fieldSelect(
                        '_sln_booking[services_resources][__service_id__]',
                        array('__resource_id__' => '__resource_title__'),
                        '__resource_id__',
                        array(
                            'attrs' => array(
                                'data-service' => '__service_id__',
                                'data-resource' => '',
                                'data-selection' => 'resource-selected'
                            ),
                            'no_id' => true
                        ),
                        true
                    ) ?>
                </div>
            <?php endif ?>
            <?php if ($isMultipleAttendants || $isAttendants): ?>
                <div class="sln-booking-service--attendants sln-select">
                    <h5 class="sln-booking-service-line__label"><?php esc_html_e('Attendant', 'salon-booking-system') ?></h5>
                    <p class="sln-alert sln-alert--multiple sln-alert--warning sln-alert--inline hide" data-alert="<?php esc_html_e('more assistant') ?>"></p>
                    <p class="sln-no-attendant-required hide"><?php esc_html_e('No assistant required', 'salon-booking-system') ?></p>
                    <?php SLN_Form::fieldSelect(
                        '_sln_booking[attendants][__service_id__]',
                        array('__attendant_id__' => '__attendant_name__'),
                        '__attendant_id__',
                        array(
                            'attrs' => array('data-service' => '__service_id__', 'data-attendant' => '', 'data-selection' => 'attendant-selected'),
                            'no_id' => true
                        ),
                        true
                    ) ?>
                </div>
            <?php endif ?>
            <div class="sln-booking-service--action">
                <span class="sln-alert sln-alert--fadeinout sln-alert--ok sln-alert--onremove hide"><?php echo esc_html__('Service addded', 'salon-booking-system') ?></span>
                <button type="button" class="sln-btn sln-btn--big sln-btn--icon sln-btn--icon--left--alt sln-icon--times sln-btn--textonly sln-btn--textonly--emph" data-collection="remove"><?php echo esc_html__('Remove', 'salon-booking-system') ?></button>
            </div>
        </div>
    </div>
    <?php
    $lineItem = ob_get_clean();
    $lineItem = preg_replace("/\r\n|\n/", ' ', $lineItem);
    ?>

    <?php
    $at_id = 0;
    $arr_ids = array();
    ?>

    <?php foreach ($bookingServices as $bookingService): ?>
        <?php
        $at_id = rand(1, 1000);
        while (in_array($at_id, $arr_ids)) {
            $at_id = rand(1, 1000);
        }
        $arr_ids[] = $at_id;
        $serviceName = $bookingService->getService()->getName();
        $serviceId = $bookingService->getService()->getId();

        $servicesData[$serviceId] = array_merge(
            isset($servicesData[$serviceId]) ? $servicesData[$serviceId] : array(),
            array(
                'old_price' => $bookingService->getPrice(),
                'old_duration' => SLN_Func::getMinutesFromDuration($bookingService->getDuration()),
                'old_break_duration' => SLN_Func::getMinutesFromDuration($bookingService->getBreakDuration()),
            )
        );
        ?>

        <div class="col-xs-12 sln-booking-service-line <?php if ('highend' == $plugin->getSettings()->getAvailabilityMode()) {
                                                            echo ' sln-booking-service-line--highend';
                                                        }
                                                        if ($isResourcesEnabled) {
                                                            echo ' sln-booking-service-line--resources';
                                                        } ?>">
            <div class="sln-row sln-booking-service-line__content <?php if ('highend' == $plugin->getSettings()->getAvailabilityMode()) {
                                                                        echo ' sln-booking-service-line__content--highend';
                                                                    }
                                                                    if ($isResourcesEnabled) {
                                                                        echo ' sln-booking-service-line__content--resources';
                                                                    } ?>">
                <div class="sln-booking-service--move-line">
                    <div class="bar"></div>
                    <div class="bar"></div>
                    <div class="bar"></div>
                </div>

                <?php if ('highend' == $plugin->getSettings()->getAvailabilityMode()): ?>
                    <div class="sln-booking-service--startend">
                        <div>
                            <h5 class="sln-booking-service-line__label"><?php esc_html_e('Start at', 'salon-booking-system') ?></h5>
                            <label class="time"><?php echo $formatter->time($bookingService->getStartsAt()) ?></label>
                        </div>
                        <div>
                            <h5 class="sln-booking-service-line__label"><?php esc_html_e('End at', 'salon-booking-system') ?></h5>
                            <label class="time"><?php echo $formatter->time($bookingService->getEndsAt()) ?></label>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="sln-booking-service--itemselection sln-select">
                    <h5 class="sln-booking-service-line__label"><?php esc_html_e('Service', 'salon-booking-system') ?></h5>
                    <?php SLN_Form::fieldSelect(
                        '_sln_booking[services][]',
                        $allServicesSelectionArray,
                        $bookingService->getService()->getId(),
                        array(
                            'attrs' => array(
                                'data-price' => $servicesData[$serviceId]['old_price'],
                                'data-duration' => $servicesData[$serviceId]['old_duration'],
                                'data-selection' => 'service-selected',
                            ),
                            'no_id' => true,
                        ),
                        true
                    );
                    ?>
                    <?php SLN_Form::fieldText(
                        '_sln_booking[service][' . $serviceId . ']',
                        $serviceId,
                        array('type' => 'hidden')
                    )
                    ?>
                    <?php SLN_Form::fieldText(
                        '_sln_booking[price][' . $serviceId . ']',
                        $servicesData[$serviceId]['old_price'],
                        array('type' => 'hidden')
                    )
                    ?>
                    <?php SLN_Form::fieldText(
                        '_sln_booking[duration][' . $serviceId . ']',
                        $servicesData[$serviceId]['old_duration'],
                        array('type' => 'hidden')
                    )
                    ?>
                    <?php SLN_Form::fieldText(
                        '_sln_booking[break_duration][' . $serviceId . ']',
                        $servicesData[$serviceId]['old_break_duration'],
                        array('type' => 'hidden')
                    )
                    ?>
                </div>
                <?php if ($isResourcesEnabled): ?>
                    <div class="sln-booking-service--resources sln-select">
                        <h5 class="sln-booking-service-line__label"><?php esc_html_e('Resource', 'salon-booking-system') ?></h5>
                        <p class="sln-no-resources hide"><?php esc_html_e('No resources', 'salon-booking-system') ?></p>
                        <?php
                        $resource  = $bookingService->getResource();
                        SLN_Form::fieldSelect(
                            '_sln_booking[services_resources][' . $serviceId . ']',
                            $resourcesData,
                            ($resource ? $resource->getId() : ''),
                            array(
                                'attrs' => array(
                                    'data-service'  => $at_id,
                                    'data-resource' => '',
                                    'data-selection' => 'resource-selected'
                                ),
                                'no_id' => true
                            ),
                            true
                        );
                        ?>
                    </div>
                <?php endif ?>
                <?php if ($isMultipleAttendants || $isAttendants): ?>
                    <div class="sln-booking-service--attendants sln-select">
                        <h5 class="sln-booking-service-line__label"><?php esc_html_e('Attendant', 'salon-booking-system') ?></h5>
                        <p class="sln-alert sln-alert--multiple sln-alert--warning sln-alert--inline hide" data-alert="<?php esc_html_e('more assistant') ?>"></p>
                        <p class="sln-no-attendant-required hide"><?php esc_html_e('No assistant required', 'salon-booking-system') ?></p>
                        <?php
                        $attendant = $bookingService->getAttendant();
                        $multipleAttr = is_array($attendant) ? array('multiple' => '') : array();
                        if (!is_array($attendant) && $attendant) {
                            $attendantItem = array($attendant->getId() => $attendant->getName());
                        } elseif (is_array($attendant)) {
                            $attendantItem = array();
                            foreach ($attendant as $att) {
                                $attendantItem[$att->getId()] = $att->getName();
                            }
                        }
                        SLN_Form::fieldSelect(
                            '_sln_booking[attendants][' . $serviceId . ']' . (is_array($attendant) ? '[]' : ''),
                            ($attendant ? $attendantItem : array('')),
                            ($attendant ? array_keys($attendantItem) : ''),
                            array(
                                'attrs' => array('data-service' => $serviceId, 'data-attendant' => '', 'data-selection' => 'attendant-selected') + $multipleAttr,
                                'no_id' => true
                            ),
                            true
                        );
                        ?>
                    </div>
                <?php endif ?>
                <div class="sln-booking-service--action">
                    <span class="sln-alert sln-alert--fadeinout sln-alert--ok sln-alert--onremove hide"><?php echo esc_html__('Service addded', 'salon-booking-system') ?></span>
                    <button type="button" class="sln-btn sln-btn--big sln-btn--icon sln-btn--icon--left--alt sln-icon--times sln-btn--textonly sln-btn--textonly--emph" data-collection="remove"><?php echo esc_html__('Remove', 'salon-booking-system') ?></button>
                </div>

            </div>
        </div>
    <?php endforeach ?>

    <div class="col-xs-12 sln-booking-service-action">
    </div>
    <div class="row">
        <?php if ($isMultipleAttendants): ?>
            <div class="col-xs-12 col-sm-5 col-sm-offset-2 col-md-offset-2 sln-booking-service-action__btns">
            <?php else: ?>
                <div class="col-xs-12 col-sm-5 col-lg-6 sln-booking-service-action__btns">
                <?php endif; ?>
                <button id="sln-addservice" data-collection="addnewserviceline" class="sln-btn sln-btn--main--tonal sln-btn--big sln-btn--icon sln-icon--plus">
                    <?php esc_html_e('Add a service', 'salon-booking-system') ?>
                </button>
                </div>
                <script>
                    var servicesData = '<?php echo addslashes(wp_json_encode($servicesData)); ?>';
                    var attendantsData = '<?php echo addslashes(wp_json_encode(array(0 => __('Select an assistant', 'salon-booking-system')) + $attendantsData)); ?>';
                    var lineItem = '<?php echo addslashes($lineItem); ?>';
                    var resourcesData = '<?php echo addslashes(wp_json_encode($resourcesData)); ?>';
                </script>
            </div>
    </div>