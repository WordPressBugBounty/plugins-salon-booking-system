<?php
// phpcs:ignoreFile WordPress.DB.SlowDBQuery.slow_db_query_meta_query

class SLN_Action_WeeklyReport
{
    const EMAIL = 'email';
    const SMS = 'sms';

    /** @var SLN_Plugin */
    private $plugin;
    private $mode;

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function executeSms()
    {
        $this->mode = self::SMS;

        $this->execute();
    }

    public function executeEmail()
    {
        $this->mode = self::EMAIL;

        $this->execute();
    }

    private function execute()
    {
        SLN_TimeFunc::startRealTimezone();

        $type = $this->mode;
        $p = $this->plugin;
        $p->addLog($type.' weekly report execution');
        $phone = $p->getSettings()->get('gen_phone');
        if (self::SMS === $type && empty($phone)) {
            $p->addLog($type.' salon phone field is empty');
        }
        else {
            $data = $this->getData();
            $this->send($data);
            $p->addLog($type.' weekly report sent');
        }
        $p->addLog($type.' weekly report execution ended');

        SLN_TimeFunc::endRealTimezone();
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function send($stats)
    {
        $p = $this->plugin;
        if (self::EMAIL == $this->mode) {
            $args = compact('stats');
            $p->sendMail('mail/weekly_report', $args);
        } else {
            throw new Exception();
        }
    }

    private function getData() {
        $p    = $this->plugin;
        $data = array(
            'total'         => array(
                'count'  => 0,
                'amount' => .0,
            ),
            'paid'          => array(
                'count'  => 0,
                'amount' => .0,
            ),
            'pay_later'     => array(
                'count'  => 0,
                'amount' => .0,
            ),
            'canceled'     => array(
                'count'  => 0,
                'amount' => .0,
            ),
            'services'      => array(),
            'attendants'    => array(),
            'weekdays'      => array(),
            'new_customers' => 0,
            'customers'     => array(),
        );
        $datetimeEnd = SLN_TimeFunc::currentDateTime();
        $datetimeStart = $datetimeEnd->modify('last Monday');

        $bookings = $this->getBookings($datetimeStart, $datetimeEnd);

        foreach($bookings as $booking) {
            if ($booking->getStatus() !== SLN_Enum_BookingStatus::CANCELED) {
                //START collect total statistics
                $data['total']['count'] ++;
                $data['total']['amount'] += $booking->getAmount();
                //END collect total statistics

                if ($booking->getStatus() === SLN_Enum_BookingStatus::PAID) {
                    $data['paid']['count'] ++;
                    $data['paid']['amount'] += $booking->getAmount();
                }
                elseif ($booking->getStatus() === SLN_Enum_BookingStatus::PAY_LATER) {
                    $data['pay_later']['count'] ++;
                    $data['pay_later']['amount'] += $booking->getAmount();
                }

                //START collect services statistics
                foreach($booking->getBookingServices()->getItems() as $item) {
                    if (!isset($data['services'][$item->getService()->getId()])) {
                        $data['services'][$item->getService()->getId()] = array(
                            'count'  => 0,
                            'amount' => 0.0,
                            'name'   => $item->getService()->getName(),
                        );
                    }
                    $data['services'][$item->getService()->getId()]['count']  += $item->getCountServices();
                    $data['services'][$item->getService()->getId()]['amount'] += $item->getPrice();
                }
                //END collect services statistics

                //START collect attendants statistics
                if ($p->getSettings()->isAttendantsEnabled()) {
                    foreach($booking->getBookingServices()->getItems() as $item) {
                        if (!$item->getAttendant()) {
                            continue;
                        }

                        $attendants = is_array($item->getAttendant()) ? $item->getAttendant() : array($item->getAttendant());

                        foreach ($attendants as $attendant) {
                            if (!isset($data['attendants'][$attendant->getId()])) {
                                $data['attendants'][$attendant->getId()] = array(
                                    'count'  => 0,
                                    'amount' => 0.0,
                                    'name'   => $attendant->getName(),
                                );
                            }
                            $data['attendants'][$attendant->getId()]['count']  += $item->getCountServices();
                            $data['attendants'][$attendant->getId()]['amount'] += $item->getPrice();
                        }
                    }
                }
                //END collect attendants statistics

                //START collect weekdays statistics
                $weekday = (int)$booking->getStartsAt()->format('w');
                if (!isset($data['weekdays'][$weekday])) {
                    $data['weekdays'][$weekday] = array(
                        'count'  => 0,
                        'amount' => 0.0,
                    );
                }
                $data['weekdays'][$weekday]['count'] ++;
                $data['weekdays'][$weekday]['amount'] += $booking->getAmount();
                //END collect weekdays statistics

                //START collect customers statistics
                $userID = $booking->getUserId();
                if (SLN_Wrapper_Customer::isCustomer($userID)) {
                    if (!isset($data['customers'][$userID])) {
                        $data['customers'][$userID] = array(
                            'count'  => 0,
                            'amount' => .0,
                            'name'   => (new SLN_Wrapper_Customer($userID))->getName(),
                        );
                    }
                    $data['customers'][$userID]['count'] ++;
                    $data['customers'][$userID]['amount'] += $booking->getAmount();
                }
                //END collect customers statistics
            }
            else {
                //START collect canceled statistics
                $data['canceled']['count'] ++;
                $data['canceled']['amount'] += $booking->getAmount();
                //END collect canceled statistics
            }
        }

        uasort($data['services'], function ($a, $b) {
            $key = 'amount';
            if ($a[$key] === $b[$key]) {
                return 0;
            }
            return $a[$key] < $b[$key] ? 1 : -1;
        });
        uasort($data['attendants'], function ($a, $b) {
            $key = 'amount';
            if ($a[$key] === $b[$key]) {
                return 0;
            }
            return $a[$key] < $b[$key] ? 1 : -1;
        });
        uasort($data['weekdays'], function ($a, $b) {
            $key = 'amount';
            if ($a[$key] === $b[$key]) {
                return 0;
            }
            return $a[$key] < $b[$key] ? 1 : -1;
        });
        uasort($data['customers'], function ($a, $b) {
            $key = 'amount';
            if ($a[$key] === $b[$key]) {
                return 0;
            }
            return $a[$key] < $b[$key] ? 1 : -1;
        });

        $newCustomers          = $this->getCustomers($datetimeStart, $datetimeEnd);
        $data['new_customers'] = count($newCustomers);

        return $data;
    }

    /**
     * @return SLN_Wrapper_Booking[]
     * @throws Exception
     */
    private function getBookings($timeBegin, $timeEnd)
    {
        $statuses = SLN_Enum_BookingStatus::toArray();
        unset($statuses[SLN_Enum_BookingStatus::ERROR], $statuses[SLN_Enum_BookingStatus::PENDING], $statuses[SLN_Enum_BookingStatus::PENDING_PAYMENT]);
        $statuses = array_keys($statuses);

        $args = array(
            'post_type'   => SLN_Plugin::POST_TYPE_BOOKING,
            'post_status' => $statuses,
            '@wp_query'   => array(
                'meta_query' => array(
                    array(
                        'key'     => '_sln_booking_date',
                        'value'   => $timeBegin->format('Y-m-d'),
                        'compare' => '>=',
                    ),
                    array(
                        'key'     => '_sln_booking_date',
                        'value'   => $timeEnd->format('Y-m-d'),
                        'compare' => '<=',
                    )
                ),
            ),
        );

        /** @var SLN_Repository_BookingRepository $repo */
        $repo = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING);
        $tmp = $repo->get($args);
        $ret = array();
        foreach ($tmp as $booking) {
            $startsAt  = $booking->getStartsAt();
            if ($startsAt >= $timeBegin && $startsAt <= $timeEnd) {
                $ret[] = $booking;
            }
        }

        return $ret;
    }

    /**
     * @return SLN_Wrapper_Customer[]
     * @throws Exception
     */
    private function getCustomers($timeBegin, $timeEnd)
    {
        $user_query  = new WP_User_Query(
            array(
                'role' => SLN_Plugin::USER_ROLE_CUSTOMER,
                'date_query' => array(
                    'after'  => $timeBegin->format('Y-m-d H:i:s'),
                    'before' => $timeEnd->format('Y-m-d H:i:s'),
                )
            )
        );

        $ret = array();
        foreach ($user_query->get_results() as $user) {
            $customer = new SLN_Wrapper_Customer($user);
            $ret[] = $customer;
        }

        return $ret;
    }
}
