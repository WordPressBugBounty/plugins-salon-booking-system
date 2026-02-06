<?php
// phpcs:ignoreFile WordPress.WP.I18n.TextDomainMismatch

namespace SLB_PWA;

class LabelProvider
{
    protected $labels;

    private static $instance;

    public static function getInstance(): LabelProvider
    {
        if (!self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public function __construct()
    {
        $this->labels = array(
            'pendingPaymentStatusLabel' => __('Pending payment', 'salon-booking-system'),
            'pendingStatusLabel' => __('Pending', 'salon-booking-system'),
            'paidStatusLabel' => __('Paid', 'salon-booking-system'),
            'payLaterStatusLabel' => __('Pay later', 'salon-booking-system'),
            'errorStatusLabel' => __('Error', 'salon-booking-system'),
            'canceledStatusLabel' => __('Canceled', 'salon-booking-system'),
            'confirmedStatusLabel' => __('Confirmed', 'salon-booking-system'),
            'upcomingReservationsTitle' => __('Upcoming reservations', 'salon-booking-system'),
            'upcomingReservationsNoResultLabel' => __('No upcoming bookings ...', 'salon-booking-system'),
            'label8Hours' => __('8 hrs', 'salon-booking-system'),
            'label24Hours' => __('24 hrs', 'salon-booking-system'),
            'label3Days' => __('3 days', 'salon-booking-system'),
            'label1Week' => __('1 week', 'salon-booking-system'),
            'allTitle' => __('All', 'salon-booking-system'),
            'deleteBookingConfirmText' => __('Are you sure ?', 'salon-booking-system'),
            'deleteBookingButtonLabel' => __('Yes, delete', 'salon-booking-system'),
            'deleteBookingGoBackLabel' => __('Go back', 'salon-booking-system'),
            'editReservationTitle' => __('Edit the reservation', 'salon-booking-system'),
            'dateTitle' => __('date', 'salon-booking-system'),
            'timeTitle' => __('time', 'salon-booking-system'),
            'customerFirstnamePlaceholder' => __('firstname', 'salon-booking-system'),
            'customerLastnamePlaceholder' => __('lastname', 'salon-booking-system'),
            'customerEmailPlaceholder' => __('email', 'salon-booking-system'),
            'customerAddressPlaceholder' => __('address', 'salon-booking-system'),
            'customerPhonePlaceholder' => __('phone', 'salon-booking-system'),
            'customerNotesPlaceholder' => __('notes', 'salon-booking-system'),
            'customerPersonalNotesPlaceholder' => __('customer personal notes', 'salon-booking-system'),
            'customerPersonalNotesLabel' => __('Customer personal notes', 'salon-booking-system'),
            'saveAsNewCustomerLabel' => __('Save this customer', 'salon-booking-system'),
            'extraInfoLabel' => __('Extra info', 'salon-booking-system'),
            'addAndManageDiscountButtonLabel' => __('Add and manage discount', 'salon-booking-system'),
            'selectDiscountLabel' => __('Select a discount', 'salon-booking-system'),
            'addDiscountButtonLabel' => __('Add a discount', 'salon-booking-system'),
            'saveButtonLabel' => __('Save booking', 'salon-booking-system'),
            'savedLabel' => __('Saved', 'salon-booking-system'),
            'validationMessage' => __('Please fill the required fields', 'salon-booking-system'),
            'selectServicesPlaceholder' => __('Select services', 'salon-booking-system'),
            'selectAttendantsPlaceholder' => __('Select an assistant', 'salon-booking-system'),
            'selectResourcesPlaceholder' => __('Select a resource', 'salon-booking-system'),
            'selectServicesSearchPlaceholder' => __('Type service name', 'salon-booking-system'),
            'selectAssistantsSearchPlaceholder' => __('Type assistant name', 'salon-booking-system'),
            'selectResourcesSearchPlaceholder' => __('Type resource name', 'salon-booking-system'),
            'selectDiscountsSearchPlaceholder' => __('Type discount name', 'salon-booking-system'),
            'addServiceButtonLabel' => __('Add a service', 'salon-booking-system'),
            'addServiceMessage' => __('Please add services', 'salon-booking-system'),
            'selectExistingClientButtonLabel' => __('Select existing client', 'salon-booking-system'),
            'bookingDetailsTitle' => __('Booking details', 'salon-booking-system'),
            'totalTitle' => __('Total', 'salon-booking-system'),
            'transactionIdTitle' => __('Transaction ID', 'salon-booking-system'),
            'discountTitle' => __('Discount', 'salon-booking-system'),
            'depositTitle' => __('Deposit', 'salon-booking-system'),
            'dueTitle' => __('Due', 'salon-booking-system'),
            'editButtonLabel' => __('Edit booking', 'salon-booking-system'),
            'reservationsCalendarTitle' => __('Reservations calendar', 'salon-booking-system'),
            'noResultTimeslotsLabel' => __('No timeslots ...', 'salon-booking-system'),
            'addReservationTitle' => __('Add the reservation', 'salon-booking-system'),
            'customersAddressBookTitle' => __('Customers', 'salon-booking-system'),
            'goBackButtonLabel' => __('GO BACK', 'salon-booking-system'),
            'customersAddressBookNoResultLabel' => __('No customers found ...', 'salon-booking-system'),
            'installPWAPromptText' => __('Add to home screen ?', 'salon-booking-system'),
            'calendarLocale' => $lang = substr( get_locale(), 0, 2 ),
            'installPWAPromptInstallBtnLabel' => __('Install!', 'salon-booking-system'),
            'installPWAPromptNoInstallBtnLabel' => __('No, thanks', 'salon-booking-system'),
            'installPWAIOSText' => __('Install this app on your IPhone=> __( tap menu and then Add to homescreen',
                'salon-booking-system'),
            'shopsTitle' => __('Select a shop', 'salon-booking-system'),
            'shopsNoResultLabel' => __('No shops ...', 'salon-booking-system'),
            'shopTitleLabel' => __('Shop', 'salon-booking-system'),
            'selectShopFirstMessage' => __('Please select a shop first to edit booking', 'salon-booking-system'),
            'selectShopPlaceholder' => __('Select shop', 'salon-booking-system'),
            'successMessagePayRemainingAmount' => __('Email sent', 'salon-booking-system'),
            'errorMessagePayRemainingAmount' => __('Error, email not sent', 'salon-booking-system'),
            'takePhotoButtonLabel' => __('Take Photo', 'salon-booking-system'),
            'selectPhotoButtonLabel' => __('Select from phone', 'salon-booking-system'),
            'backImagesButtonLabel' => __('Go back', 'salon-booking-system'),
            'photoCameraButtonLabel' => __('Photo', 'salon-booking-system'),
            'customerDetailsUpdateButtonLabel' => __('Update customer', 'salon-booking-system'),
            'customerDetailsGoBackButtonLabel' => __('Go back', 'salon-booking-system'),
            'assistantBusyTitle' => __('Assistant is busy', 'salon-booking-system'),
            'assistantBusyMessage' => __('is busy from %s to %s. Please select another time or assistant.', 'salon-booking-system'),
            'attendantViewLabel' => __('Assistants view', 'salon-booking-system'),
            'bookingActionEdit' => __('Edit', 'salon-booking-system'),
            'bookingActionDelete' => __('Delete', 'salon-booking-system'),
            'bookingActionCallCustomer' => __('Call customer', 'salon-booking-system'),
            'bookingActionWhatsappCustomer' => __('Whatsapp customer', 'salon-booking-system'),
            'bookingActionOpenProfile' => __('Open customer profile', 'salon-booking-system'),
        );
    }

    public function getLabels(): array
    {
        return $this->labels;
    }
}