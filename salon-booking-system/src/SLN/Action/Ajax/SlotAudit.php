<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

use Salon\Util\Date;
use Salon\Util\Time;

/**
 * AJAX endpoint: diagnoses why an assistant's slot is grey (unavailable) in the day calendar.
 *
 * Request params (GET or POST):
 *   attendant_id  int     required
 *   date          string  required  Y-m-d
 *   time          string  required  H:i
 *   shop_id       int     optional  Multi-Shop shop ID (0 = no shop context)
 *
 * Response: JSON audit object describing which working-hour and holiday rules
 * caused the slot to be blocked.
 */
class SLN_Action_Ajax_SlotAudit extends SLN_Action_Ajax_Abstract
{
    public function execute()
    {
        if (!$this->authorizeSalonAjax()) {
            return array('error' => __('Permission denied.', 'salon-booking-system'));
        }

        $plugin   = SLN_Plugin::getInstance();
        $settings = $plugin->getSettings();

        $attId   = intval(isset($_REQUEST['attendant_id']) ? $_REQUEST['attendant_id'] : 0);
        $dateStr = sanitize_text_field(isset($_REQUEST['date']) ? $_REQUEST['date'] : '');
        $timeStr = sanitize_text_field(isset($_REQUEST['time']) ? $_REQUEST['time'] : '');
        $shopId  = intval(isset($_REQUEST['shop_id']) ? $_REQUEST['shop_id'] : 0);

        if (!$attId || !$dateStr || !$timeStr) {
            return array('error' => __('Missing required parameters: attendant_id, date, time.', 'salon-booking-system'));
        }

        $attendant = $plugin->createAttendant($attId);
        $shopName  = '';

        if (class_exists('\SalonMultishop\Addon') && $shopId > 0) {
            try {
                $addon = \SalonMultishop\Addon::getInstance();
                // setCurrentShop() accepts an int and resolves the Shop object internally.
                // We cannot rely on handleCurrentShop() here — it reads $_GET['shop'] or
                // $_POST['shop_id'], neither of which is set under the 'shop_id' GET param
                // we use. We also set $_GET['shop'] so that SalonSettings::get() and other
                // filters that gate on isset($_GET['shop']) fire correctly.
                $addon->setCurrentShop($shopId);
                $_GET['shop'] = $shopId;
                $shop = $addon->getCurrentShop();
                if ($shop) {
                    $shopName  = method_exists($shop, 'getName') ? $shop->getName() : '';
                    $attendant = $shop->getAttendantWrapper($attendant);
                }
            } catch (\Exception $e) {
                SLN_Plugin::addLog('SlotAudit: multishop error: ' . $e->getMessage());
            }
        }

        $interval   = $settings->getInterval();
        $dtInterval = new DateTime('@' . $interval * 60);
        $dateTime   = new DateTime($dateStr . ' ' . $timeStr, new DateTimeZone('UTC'));

        $availItems   = $attendant->getAvailabilityItems();
        $holidayItems = $attendant->getNewHolidayItems();

        $availPass   = $availItems->isValidDatetimeDuration($dateTime, $dtInterval);
        $holidayPass = $holidayItems->isValidDatetimeDuration($dateTime, $dtInterval);

        return array(
            'attendant'    => $attendant->getName(),
            'date'         => $dateStr,
            'time'         => $timeStr,
            'shop'         => $shopName,
            'is_available' => $availPass && $holidayPass,
            'checks'       => array(
                array(
                    'type'    => 'availability',
                    'label'   => __('Working hours', 'salon-booking-system'),
                    'pass'    => $availPass,
                    'details' => $this->auditAvailability($availItems, $dateStr, $timeStr),
                ),
                array(
                    'type'    => 'holiday',
                    'label'   => __('Holiday / lock rules', 'salon-booking-system'),
                    'pass'    => $holidayPass,
                    'details' => $this->auditHolidays($holidayItems, $dateStr, $timeStr),
                ),
            ),
        );
    }

    /**
     * Inspect each availability rule and determine which sub-condition fails.
     *
     * Critical: replicates the getDateSubset() priority logic from SLN_Helper_AvailabilityItems.
     * When any date-range rule covers today, always-on rules are excluded from evaluation.
     * Checking all rules independently would give wrong results in that scenario.
     */
    private function auditAvailability(SLN_Helper_AvailabilityItems $items, $dateStr, $timeStr)
    {
        $rules        = $items->toArray();
        $hasRealRules = !($rules[0] instanceof SLN_Helper_AvailabilityItemNull);
        $dayNames     = SLN_Func::getDays(); // array: 1=Sun,2=Mon,...,7=Sat

        if (!$hasRealRules) {
            return array(
                'summary' => __('No working hour rules configured — assistant follows the general shop schedule.', 'salon-booking-system'),
                'rules'   => array(),
            );
        }

        $dateTimeObj = new DateTime($dateStr . ' ' . $timeStr, new DateTimeZone('UTC'));
        $dateObj     = Date::create($dateTimeObj);
        $timeObj     = Time::create($timeStr);
        // getDays() uses format("w") + 1: Sun=1, Mon=2, ..., Sat=7
        // Date::getWeekday() uses format("w"): Sun=0, Mon=1, ..., Sat=6
        $weekdayIdx  = $dateObj->getWeekday() + 1;

        // Replicate processDateSubset() to know which rules are ACTUALLY evaluated.
        // Step 1: date-range rules that cover today.
        $activeSubset = array();
        foreach ($rules as $rule) {
            if (!($rule instanceof SLN_Helper_AvailabilityItemNull) && !$rule->isAlwaysOn() && $rule->isValidDayOfPeriod($dateObj)) {
                $activeSubset[] = $rule;
            }
        }
        // Step 2: if no date-range rules matched, fall back to always-on rules.
        $alwaysOnSuperseded = !empty($activeSubset);
        if (empty($activeSubset)) {
            foreach ($rules as $rule) {
                if (!($rule instanceof SLN_Helper_AvailabilityItemNull) && $rule->isAlwaysOn()) {
                    $activeSubset[] = $rule;
                }
            }
        }

        $result = array();
        foreach ($rules as $rule) {
            if ($rule instanceof SLN_Helper_AvailabilityItemNull) {
                $result[] = array(
                    'label'        => __('Default rule (always open)', 'salon-booking-system'),
                    'shifts'       => array(),
                    'fail_reasons' => array(),
                    'pass'         => true,
                    'active'       => true,
                    'superseded'   => false,
                );
                continue;
            }

            $data        = $rule->getData();
            $failReasons = array();

            // Check whether this rule is in the active subset.
            $isActive = false;
            foreach ($activeSubset as $activeRule) {
                if ($activeRule === $rule) {
                    $isActive = true;
                    break;
                }
            }

            // If not active, explain why it was excluded.
            if (!$isActive) {
                $supersededMsg = $rule->isAlwaysOn()
                    ? __('Superseded: a date-range rule covers today, so this always-on rule is not evaluated.', 'salon-booking-system')
                    : sprintf(
                        __('Inactive: date %s is outside this rule\'s period (%s → %s).', 'salon-booking-system'),
                        $dateStr,
                        isset($data['from_date']) ? $data['from_date'] : '—',
                        isset($data['to_date'])   ? $data['to_date']   : '—'
                    );

                $result[] = array(
                    'label'        => (string) $rule,
                    'shifts'       => array(),
                    'fail_reasons' => array($supersededMsg),
                    'pass'         => false,
                    'active'       => false,
                    'superseded'   => true,
                );
                continue;
            }

            // Active rule — check weekday / specific dates.
            if (!$rule->isSelectSpecificDates()) {
                $enabledDayLabels = array();
                if (isset($data['days'])) {
                    foreach ($data['days'] as $idx => $v) {
                        if (isset($dayNames[$idx])) {
                            $enabledDayLabels[] = $dayNames[$idx];
                        }
                    }
                }
                if (!isset($data['days'][$weekdayIdx])) {
                    $currentDayLabel = isset($dayNames[$weekdayIdx]) ? $dayNames[$weekdayIdx] : $dateStr;
                    $failReasons[]   = sprintf(
                        __('%s is not enabled in this rule. Enabled days: %s', 'salon-booking-system'),
                        $currentDayLabel,
                        empty($enabledDayLabels) ? __('none', 'salon-booking-system') : implode(', ', $enabledDayLabels)
                    );
                }
            } else {
                if (!$rule->isValidSpecificDates($dateObj)) {
                    $specific      = isset($data['specific_dates']) ? $data['specific_dates'] : __('(empty)', 'salon-booking-system');
                    $failReasons[] = sprintf(
                        __('Date %s is not in the specific dates list: %s', 'salon-booking-system'),
                        $dateStr, $specific
                    );
                }
            }

            // Collect shift labels.
            $shifts = array();
            if (isset($data['from'], $data['to'])) {
                $count = max(count($data['from']), count($data['to']));
                for ($i = 0; $i < $count; $i++) {
                    if (!isset($data['from'][$i], $data['to'][$i])) continue;
                    if ($data['from'][$i] === '00:00' && $data['to'][$i] === '00:00') continue;
                    $shifts[] = $data['from'][$i] . ' → ' . $data['to'][$i];
                }
            }

            // Check shift hours only when date/weekday already passed.
            if (empty($failReasons) && !$rule->isValidTime($timeObj)) {
                $failReasons[] = sprintf(
                    __('Time %s is outside the shift hours: %s', 'salon-booking-system'),
                    $timeStr,
                    empty($shifts) ? __('(no shifts defined)', 'salon-booking-system') : implode(', ', $shifts)
                );
            }

            $result[] = array(
                'label'        => (string) $rule,
                'shifts'       => $shifts,
                'fail_reasons' => $failReasons,
                'pass'         => empty($failReasons),
                'active'       => true,
                'superseded'   => false,
            );
        }

        // Summary: only consider active rules for pass/fail.
        $anyActivePass = false;
        foreach ($result as $r) {
            if ($r['active'] && $r['pass']) {
                $anyActivePass = true;
                break;
            }
        }

        $summary = $anyActivePass
            ? __('At least one active working hour rule allows this time slot.', 'salon-booking-system')
            : __('No active working hour rule covers this date and time.', 'salon-booking-system');

        if ($alwaysOnSuperseded) {
            $summary .= ' ' . __('Note: always-on rules were not evaluated because a date-range rule takes priority today.', 'salon-booking-system');
        }

        return array(
            'summary' => $summary,
            'rules'   => $result,
        );
    }

    /**
     * Inspect each holiday/lock rule and determine whether it blocks the slot.
     */
    private function auditHolidays(SLN_Helper_HolidayItems $items, $dateStr, $timeStr)
    {
        $rules = $items->toArray();

        if (empty($rules)) {
            return array(
                'summary' => __('No holiday or lock rules configured.', 'salon-booking-system'),
                'rules'   => array(),
            );
        }

        $result = array();
        foreach ($rules as $rule) {
            $data      = $rule->getData();
            $inRange   = $rule->isDateContained($dateStr);
            $blocked   = !$rule->isValidDate($dateStr) || ($inRange && !$rule->isValidTime($dateStr . ' ' . $timeStr));

            $fromDate  = isset($data['from_date']) ? $data['from_date'] : '?';
            $fromTime  = isset($data['from_time']) ? $data['from_time'] : '00:00';
            $toDate    = isset($data['to_date']) ? $data['to_date'] : '?';
            $toTime    = isset($data['to_time']) ? $data['to_time'] : '00:00';
            $label     = sprintf('%s %s → %s %s', $fromDate, $fromTime, $toDate, $toTime);

            $reason = '';
            if ($blocked) {
                $reason = $inRange
                    ? sprintf(
                        __('Date %s falls within this lock period (%s %s → %s %s)', 'salon-booking-system'),
                        $dateStr, $fromDate, $fromTime, $toDate, $toTime
                      )
                    : __('Blocked by a day-level holiday rule', 'salon-booking-system');
            }

            $result[] = array(
                'label'     => $label,
                'is_daily'  => !empty($data['daily']),
                'is_manual' => !empty($data['is_manual']),
                'blocked'   => $blocked,
                'pass'      => !$blocked,
                'reason'    => $reason,
            );
        }

        $blockedCount = count(array_filter($result, function ($r) { return $r['blocked']; }));
        $summary      = $blockedCount > 0
            ? sprintf(
                _n(
                    '%d holiday/lock rule is blocking this slot.',
                    '%d holiday/lock rules are blocking this slot.',
                    $blockedCount,
                    'salon-booking-system'
                ),
                $blockedCount
              )
            : __('No holiday or lock rules are blocking this slot.', 'salon-booking-system');

        return array(
            'summary' => $summary,
            'rules'   => $result,
        );
    }
}
