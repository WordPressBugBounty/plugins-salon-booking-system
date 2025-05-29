<template>
    <div>
        <TabsList @applyShop="applyShop" :isShopsEnabled="isShopsEnabled"/>
        <PWAPrompt/>
    </div>
</template>

<script>

import TabsList from './components/TabsList.vue'
import PWAPrompt from './components/PWAPrompt.vue'

export default {
    name: 'App',
    mounted() {
        this.loadSettings()
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
        }
    },
    watch: {
        shop() {
            this.loadSettings()
        },
    },
    methods: {
        loadSettings() {
          this.axios.get('app/settings', {params: {shop: this.shop ? this.shop.id : null}}).then((response) => {
            this.settings = response.data.settings;
            this.$root.settings = {...this.$root.settings, ...this.settings};
          })
        },
        applyShop(shop) {
            this.shop = shop
        },
    },
    components: {
        TabsList,
        PWAPrompt,
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
#app {
  font-family: Avenir, Helvetica, Arial, sans-serif;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
  text-align: center;
  color: #2c3e50;
  margin-top: 50px;
}
.service-select .vue-dropdown .vue-dropdown-item.highlighted,
.discount-select .vue-dropdown .vue-dropdown-item.highlighted {
  background-color:#0d6efd;
}
.service-select .vue-dropdown .vue-dropdown-item.highlighted span,
.service-select .vue-dropdown .vue-dropdown-item.highlighted .option-item,
.discount-select .vue-dropdown .vue-dropdown-item.highlighted span,
.discount-select .vue-dropdown .vue-dropdown-item.highlighted .option-item {
  color:#fff;
}
.service-select .vue-dropdown,
.discount-select .vue-dropdown {
  background-color:#edeff2;
  padding: 0px 10px;
}
.service-select .vue-input,
.discount-select .vue-input {
  width:100%;
  font-size: 1rem;
}
</style>
