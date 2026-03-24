<template>
    <div>
        <TabsList @applyShop="applyShop" :isShopsEnabled="isShopsEnabled"/>
    </div>
</template>

<script>

import TabsList from './components/TabsList.vue'

export default {
    name: 'App',
    mounted() {
        this.loadBookingDragResizePref()
        this.loadSettings()
        this.displayBuildVersion()
    },
    computed: {
        isShopsEnabled() {
            return window.slnPWA.is_shops
        },
    },
    data: function () {
        return {
            settings : {},
            statusesList: {
                'sln-b-pendingpayment': {label: this.getLabel('pendingPaymentStatusLabel'), color: '#ffc107'},
                'sln-b-pending': {label: this.getLabel('pendingStatusLabel'), color: '#ffc107'},
                'sln-b-paid': {label: this.getLabel('paidStatusLabel'), color: '#28a745'},
                'sln-b-paylater': {label: this.getLabel('payLaterStatusLabel'), color: '#17a2b8'},
                'sln-b-error': {label: this.getLabel('errorStatusLabel'), color: '#dc3545'},
                'sln-b-confirmed': {label: this.getLabel('confirmedStatusLabel'), color: '#28a745'},
                'sln-b-canceled': {label: this.getLabel('canceledStatusLabel'), color: '#dc3545'},
            },
            shop: null,
            /** When true, calendar booking cards hide drag-to-resize (see Profile toggle). */
            disableBookingDragResize: false,
        }
    },
    watch: {
        shop() {
            this.loadSettings()
        },
    },
    methods: {
        loadBookingDragResizePref() {
            try {
                if (!window.slnPWA?.can_access_booking_resize_pref) {
                    this.disableBookingDragResize = false
                    return
                }
                this.disableBookingDragResize = localStorage.getItem('sln_pwa_disable_booking_drag_resize') === '1'
            } catch (e) {
                this.disableBookingDragResize = false
            }
        },
        setDisableBookingDragResize(value) {
            if (!window.slnPWA?.can_access_booking_resize_pref) {
                return
            }
            this.disableBookingDragResize = !!value
            try {
                if (this.disableBookingDragResize) {
                    localStorage.setItem('sln_pwa_disable_booking_drag_resize', '1')
                } else {
                    localStorage.removeItem('sln_pwa_disable_booking_drag_resize')
                }
            } catch (e) { /* ignore */ }
        },
        loadSettings() {
          this.axios.get('app/settings', {params: {shop: this.shop ? this.shop.id : null}}).then((response) => {
            this.settings = response.data.settings;
            this.$root.settings = {...this.$root.settings, ...this.settings};
          })
        },
        applyShop(shop) {
            this.shop = shop
        },
        async displayBuildVersion() {
            try {
                const response = await fetch(`${process.env.BASE_URL}version.json?t=${Date.now()}`);
                const version = await response.json();
                
                console.log('\n═══════════════════════════════════════');
                console.log('🎯 PWA BUILD VERSION');
                console.log('═══════════════════════════════════════');
                console.log(`📅 Build Time: ${version.buildTime}`);
                console.log(`🔑 Build Hash: ${version.buildHash}`);
                console.log(`⏱️  Timestamp:  ${version.timestamp}`);
                console.log('═══════════════════════════════════════\n');
                
                // Store in window for debugging
                window.PWA_BUILD_VERSION = version;
            } catch (error) {
                console.warn('⚠️  Could not load build version:', error);
            }
        },
    },
    components: {
        TabsList,
    },
    beforeCreate() {
        if (this.$OneSignal) {
            this.$OneSignal.showSlidedownPrompt()
            this.$OneSignal.on('subscriptionChange', (isSubscribed) => {
                if (isSubscribed) {
                    this.$OneSignal.getUserId((userId) => {
                        if (userId) {
                            this.axios.put('users', {onesignal_player_id: userId})
                        }
                    });
                }
            });
        }
    },
}
</script>

<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
  /* Brand */
  --color-primary: #2563EB;
  --color-primary-light: #EFF6FF;

  /* Surface */
  --color-surface: #FFFFFF;
  --color-background: #F4F6FA;
  --color-border: #E2E8F0;

  /* Text */
  --color-text-primary: #0F172A;
  --color-text-secondary: #64748B;
  --color-text-muted: #94A3B8;

  /* Status */
  --color-confirmed: #16A34A;
  --color-pending: #D97706;
  --color-info: #0891B2;
  --color-error: #DC2626;

  /* Nav */
  --color-nav-active: #2563EB;
  --color-nav-inactive: #94A3B8;

  /* Radius */
  --radius-sm: 8px;
  --radius-md: 12px;
  --radius-lg: 16px;
  --radius-xl: 24px;
  --radius-pill: 999px;

  /* Spacing */
  --spacing-page: 16px;
  --spacing-card: 14px;
  --spacing-gap: 8px;
}

* {
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  box-sizing: border-box;
}

body {
  background-color: var(--color-background) !important;
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif !important;
}

#app {
  font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  color: var(--color-text-primary);
  background-color: var(--color-background);
  margin-top: 0;
  min-height: 100vh;
}

/* ── Spinner ── */
.spinner-border {
  color: var(--color-primary) !important;
}

/* ── vue-next-select overrides ── */
.service-select .vue-dropdown .vue-dropdown-item.highlighted,
.discount-select .vue-dropdown .vue-dropdown-item.highlighted {
  background-color: var(--color-primary);
}
.service-select .vue-dropdown .vue-dropdown-item.highlighted span,
.service-select .vue-dropdown .vue-dropdown-item.highlighted .option-item,
.discount-select .vue-dropdown .vue-dropdown-item.highlighted span,
.discount-select .vue-dropdown .vue-dropdown-item.highlighted .option-item {
  color: #fff;
}
.service-select .vue-dropdown,
.discount-select .vue-dropdown {
  background-color: #edeff2;
  padding: 0 10px;
}
.service-select .vue-input,
.discount-select .vue-input {
  width: 100%;
  font-size: 1rem;
}
</style>
