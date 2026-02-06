<?php
// phpcs:ignoreFile WordPress.Security.NonceVerification.Missing

class SLN_Action_Ajax_DuplicateClone extends SLN_Action_Ajax_Abstract
{
    const STATUS_ERROR = -1;
    const STATUS_UNCHECKED = 0;
    const STATUS_CHECKED = 1;

    /** @var  SLN_Wrapper_Booking_Builder */
    protected $bb;
    /** @var  SLN_Helper_Availability */
    protected $ah;

    protected $date;
    protected $time;
    protected $errors = array();

    public function execute()
    {
        $bookingId = (int)$_POST['bookingId'];
        $unit = (int)$_POST['unit'];
        $week_time = (int)$_POST['week_time'];
        $weeks_map = [
            1 => 1, // every week
            2 => 2, // every two weeks
            3 => 3, // every three weeks
            4 => 4, // every four weeks
        ];
        $interval_weeks = $weeks_map[$week_time] ?? 1;

        for ($i = 0; $i < $unit; $i++) {
            $booking = SLN_Plugin::getInstance()->createBooking($bookingId);

            $bb = new SLN_Wrapper_Booking_Builder(SLN_Plugin::getInstance());
            $dateString = $booking->getMeta('date');
            $date = new DateTime($dateString);

            //$date->modify('+'.($i+1).' week');
            $weeks_to_add = $interval_weeks * ($i + 1);
            $date->modify('+' . $weeks_to_add . ' week');

            $bb->setDate($date->format('Y-m-d'));
            $bb->setTime($booking->getMeta('time'));

            $bb->set('firstname', $booking->getFirstname());
            $bb->set('lastname', $booking->getLastname());
            $bb->set('email', $booking->getEmail());
            $bb->set('phone', $booking->getPhone());
            $bb->set('address', $booking->getAddress());
            //$bb->set('discounts', $request->get_param('discounts'));
            $bb->set('note', $booking->getNote());
            //$bb->set('transaction_id', $request->get_param('transaction_id'));
            $bb->set('admin_note', $booking->getAdminNote());
            $services = array();

            foreach ($booking->getAttendantsIds() as $service=>$attendantId) {
                $services[] = array(
                    'attendant' => isset($attendantId) ? $attendantId : '',
                    'service'   => isset($service) ? $service : '',
                );
            }
            $bb->set('services', $services);

            $bb->create();

            $booking = $bb->getLastBooking();
            $booking->setStatus('sln-b-confirmed');
        }
        return array(
            'id'	  => $booking->getId(),
            'customer_id' => $booking->getUserId(),
        );
    }



}
