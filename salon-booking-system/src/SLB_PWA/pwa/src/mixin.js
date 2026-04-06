import axios from 'axios'
import dayjs from 'dayjs'
import customParseFormat from 'dayjs/plugin/customParseFormat'
import isBetween from 'dayjs/plugin/isBetween'
import isoWeek from 'dayjs/plugin/isoWeek'
import localizedFormat from 'dayjs/plugin/localizedFormat'

import 'dayjs/locale/en'
import 'dayjs/locale/de'
import 'dayjs/locale/es'
import 'dayjs/locale/fr'
import 'dayjs/locale/it'
import 'dayjs/locale/nl'
import 'dayjs/locale/pl'
import 'dayjs/locale/pt'
import 'dayjs/locale/ro'

dayjs.extend(customParseFormat)
dayjs.extend(isBetween)
dayjs.extend(isoWeek)
dayjs.extend(localizedFormat)

// Provide a minimal mock in dev mode so the app renders without a WordPress backend
if (process.env.NODE_ENV === 'development' && typeof window.slnPWA === 'undefined') {
    window.slnPWA = {
        api: 'http://localhost:8081/__mock_api__/',
        token: 'dev-token',
        locale: 'en_US',
        is_pro: false,
        pro_pricing_url: 'https://www.salonbookingsystem.com/plugin-pricing/',
        is_shops: false,
        /** Match production: assistant filter chips only for admin + shop managers */
        can_use_assistant_filter: true,
        can_access_booking_resize_pref: true,
        /** Extra sample promo slides on Upcoming when featured_addon_promos is empty */
        dummy_promo_cards: true,
        /** Dev mock: set to [] to exercise legacy static promos */
        featured_addon_promos: [
            {
                title: 'Sample featured add-on',
                body: 'Production loads EDD downloads with the featured category plus a specific add-on category (same rules as Salon → Extensions).',
                href: 'https://www.salonbookingsystem.com/',
                category: 'Integrations',
            },
        ],
        /** Dev: simulates free edition license promo (prepend). Set null to hide. */
        license_upgrade_promo: {
            kind: 'free_pro',
            href: 'https://www.salonbookingsystem.com/plugin-pricing/',
        },
        onesignal_app_id: null,
        mock_user: {
            id: 1,
            name: 'John Smith',
            email: 'admin@salon.com',
            role: 'Administrator',
        },
        labels: {
            upcomingReservationsTitle: 'Upcoming',
            customersAddressBookTitle: 'Customers',
            upcomingReservationsNoResultLabel: 'No bookings found',
            upcomingBookingsScrollAriaLabel: 'Upcoming bookings',
            upcomingPromosSectionTitle: 'Features & add-ons',
            upcomingPromoCtaLabel: 'Learn more',
            upcomingPromo1Title: 'Dynamic pricing',
            upcomingPromo1Body:
                'Offer demand-based pricing with the Dynamic Pricing add-on for Salon Booking System.',
            upcomingPromo2Title: 'SMS notifications',
            upcomingPromo2Body:
                'Send automated text reminders and cut no-shows with the right messaging add-on.',
            upcomingPromo3Title: 'Full mobile workflow',
            upcomingPromo3Body:
                'Unlock the calendar, customers, and full booking details in this app with PRO.',
            customersAddressBookNoResultLabel: 'No customers found',
            allTitle: 'All',
            label8Hours: '8h',
            label24Hours: '24h',
            label3Days: '3 days',
            label1Week: '1 week',
            deleteBookingConfirmText: 'Are you sure you want to delete this booking?',
            deleteBookingButtonLabel: 'Delete Booking',
            deleteBookingGoBackLabel: 'Go back',
            goBackButtonLabel: 'Go back',
            proUpgradeModalTitle: 'PRO feature',
            proUpgradeModalMessage:
                'The calendar and customers directory are available in Salon Booking System PRO. Upgrade to unlock them in the mobile app.',
            proUpgradeModalCtaLabel: 'View PRO plans',
            proUpgradeModalCloseLabel: 'Not now',
            proUpgradeBookingDetailsMessage:
                'Opening a booking to view or edit details is available in Salon Booking System PRO. Upgrade to unlock full mobile booking management.',
            pwaLicensePromoFreeProTitle: 'Unlock Salon Booking PRO',
            pwaLicensePromoFreeProBody:
                'Payments, deposits, SMS, advanced calendar, and the full mobile app workflow — upgrade to a PRO license on our website.',
            pwaLicensePromoFreeProCta: 'View PRO plans',
            pwaLicensePromoBasicBusinessTitle: 'Upgrade to Business Plan',
            pwaLicensePromoBasicBusinessBody:
                'Your Basic plan is active. Move up to Business for more included add-ons, higher limits, and advanced features for growing salons.',
            pwaLicensePromoBasicBusinessCta: 'Compare plans',
        },
    };
}

export default {
    computed: {
        axios() {
            return axios.create({
                baseURL: window.slnPWA.api,
                headers: {'Access-Token': window.slnPWA.token},
            });
        },
        /** Drop-in replacement for moment (Day.js). */
        moment() {
            return dayjs
        },
        locale() {
            return window.slnPWA.locale
        },
        shouldHideEmail() {
            return this.$root.settings && this.$root.settings.hide_customers_email;
        },
        shouldHidePhone() {
            return this.$root.settings && this.$root.settings.hide_customers_phone;
        },
        /** Administrator and shop manager (shop_manager / sln_shop_manager) — see SLB_PWA Plugin.php */
        canUseAssistantFilter() {
            return !!window.slnPWA?.can_use_assistant_filter;
        },
        /** Administrator + shop_manager / sln_shop_manager — Profile “disable drag resize” */
        canAccessBookingResizePref() {
            return !!window.slnPWA?.can_access_booking_resize_pref;
        },
        /** No-show controls in PWA calendar/upcoming are PRO-only (no separate setting). */
        canUsePwaNoShowControl() {
            return window.slnPWA?.is_pro === true;
        },
    },
    methods: {
        /** Map WordPress-style locale (e.g. en_US) to Day.js locale id. */
        dayjsLocale(wpLocale) {
            if (!wpLocale) {
                return 'en';
            }
            const s = String(wpLocale).split('_')[0].split('-')[0].toLowerCase();
            return s || 'en';
        },
        dateFormat(date, dateFormat) {

            var format = this.$root.settings.date_format ? this.$root.settings.date_format.js_format : null;

            if (!format) {
                return date
            }

            var momentJsFormat = format
                                    .replace('dd', 'DD')
                                    .replace('M', 'MMM')
                                    .replace('mm', 'MM')
                                    .replace('yyyy', 'YYYY')

            return dayjs(date).format(dateFormat ? dateFormat : momentJsFormat)
        },
        timeFormat(time) {
            return dayjs(time, 'HH:mm').format(this.getTimeFormat())
        },
        getTimeFormat() {

            var format = this.$root.settings.time_format ? this.$root.settings.time_format.js_format : null;

            if (!format) {
                return
            }

            var momentJsFormat = format.indexOf('p') > -1 ?
                                    format
                                        .replace('H', 'hh')
                                        .replace('p', 'a')
                                        .replace('ii', 'mm')
                                    :
                                    format
                                        .replace('hh', 'HH')
                                        .replace('ii', 'mm')

            return momentJsFormat
        },
        getQueryParams() {
            let query = window.location.search
            query = query.replace('?', '')
            let paramsList = query.split('&').map(i => ({key: i.split('=')[0], value: i.split('=')[1]}))
            let params = {};
            paramsList.forEach(i => {
                params[i.key] = i.value
            })
            return params;
        },
        getLabel(key) {
            return window.slnPWA.labels[key];
        },
        getDisplayEmail(email) {
            return this.shouldHideEmail ? '***@***' : email;
        },
        getDisplayPhone(phone) {
            return this.shouldHidePhone ? '*******' : phone;
        }
    },
}
