<?php
// phpcs:ignoreFile WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
class SLN_Action_UpdatePhoneCountryDialCode
{
    /** @var SLN_Plugin */
    private $plugin;

    const CHUNK_LENGTH = 1000;

    // Progress lives in options, not transients: booking changes used to run
    // DELETE ... LIKE '_transient_sln_%', which wiped the "done" flag and restarted this
    // migration on the next request — loading every customer and 1000 bookings per request,
    // enough to exhaust a 128M memory limit. A transient can always vanish; a flag can't.
    const OPTION_UPDATED     = 'sln_phone_country_dial_code_updated';
    const OPTION_CHUNK_INDEX = 'sln_phone_country_dial_code_chunk_index';

    public function __construct(SLN_Plugin $plugin)
    {
        $this->plugin = $plugin;
        add_action('wp_loaded', array($this, 'execute'));
    }

    public function execute()
    {
        if (get_option(self::OPTION_UPDATED)) {
            return;
        }
        // Sites that finished before the move to options still carry the old transient flag.
        if (get_transient(self::OPTION_UPDATED)) {
            update_option(self::OPTION_UPDATED, 1);
            return;
        }

        $bookingIDs = $this->plugin->getRepository(SLN_Plugin::POST_TYPE_BOOKING)->getIDs();
        $chunkIndex = (int) get_option(self::OPTION_CHUNK_INDEX, 0);
        $bookingIDs = array_slice($bookingIDs, self::CHUNK_LENGTH * $chunkIndex, 1000);

        $phone_codes = json_decode(file_get_contents(SLN_PLUGIN_DIR .'/CountryCodes.json'), true);

        foreach ($bookingIDs as $bookingID) {
            $booking = new SLN_Wrapper_Booking($bookingID);
            $phone = $booking->getPhone();
            if (substr($phone, 0, 1) !== '+') {
                continue;
            }
            foreach ($phone_codes as $phone_code) {
                $dial_code = $phone_code['dial_code'];
                if (strstr($phone, $dial_code)) {
                    $phone = str_replace($dial_code, '', $phone);
                    $booking->setMeta('phone', $phone);
                    $booking->setMeta('sms_prefix', $dial_code);
                    break;
                }
            }
        }

        if (!$chunkIndex) {
            $query = new WP_User_Query(array(
                'role' => SLN_Plugin::USER_ROLE_CUSTOMER,
                'number' => -1,
            ));

            $customers = array();

            foreach ($query->results as $customer) {
                $customers[] = new SLN_Wrapper_Customer($customer);
            }

            foreach ($customers as $customer) {
                $phone = $customer->getMeta('phone');
                if (substr($phone, 0, 1) !== '+') {
                    continue;
                }
                foreach ($phone_codes as $phone_code) {
                    $dial_code = $phone_code['dial_code'];
                    if (strstr($phone, $dial_code)) {
                        $phone = str_replace($dial_code, '', $phone);
                        $customer->setMeta('phone', $phone);
                        $customer->setMeta('sms_prefix', $dial_code);
                        break;
                    }
                }
            }
        }

        if (count($bookingIDs) === 1000) {
            update_option(self::OPTION_CHUNK_INDEX, $chunkIndex + 1, false);
        } else {
            delete_option(self::OPTION_CHUNK_INDEX);
            update_option(self::OPTION_UPDATED, 1);
        }
    }

}