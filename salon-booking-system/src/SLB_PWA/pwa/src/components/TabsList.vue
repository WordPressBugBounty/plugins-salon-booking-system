<template>
    <div :class="{'hide-tabs-header': isHideTabsHeader}" >
        <b-tabs pills card end>
            <b-tab :active="isActiveTab('#shops')" v-show="isShopsEnabled" :title-item-class="{hide: !isShopsEnabled}">
                <template #title><span @click="click('#shops')"><font-awesome-icon icon="fa-solid fa-store" /></span></template>
                <ShopsTab @applyShop="applyShop"/>
            </b-tab>
            <b-tab :active="isActiveTab('#upcoming-reservations')">
                <ShopTitle :shop="shop" />
                <template #title><span @click="click('#upcoming-reservations')" ref="upcoming-reservations-tab-link"><font-awesome-icon icon="fa-solid fa-list" /></span></template>
                <UpcomingReservationsTab :shop="shop" @hideTabsHeader="hideTabsHeader"/>
            </b-tab>
            <b-tab :active="isActiveTab('#reservations-calendar')">
                <ShopTitle :shop="shop" />
                <template #title><span @click="click('#reservations-calendar')"><font-awesome-icon icon="fa-solid fa-calendar-days" /></span></template>
                <ReservationsCalendarTab :shop="shop" @hideTabsHeader="hideTabsHeader"/>
            </b-tab>
            <b-tab :active="isActiveTab('#customers')">
                <ShopTitle :shop="shop" />
                <template #title><span @click="click('#customers')"><font-awesome-icon icon="fa-regular fa-address-book" /></span></template>
                <CustomersAddressBookTab :shop="shop" @hideTabsHeader="hideTabsHeader"/>
            </b-tab>
        </b-tabs>
    </div>
</template>

<script>

    import UpcomingReservationsTab from './tabs/UpcomingReservationsTab.vue'
    import ReservationsCalendarTab from './tabs/ReservationsCalendarTab.vue'
    import CustomersAddressBookTab from './tabs/CustomersAddressBookTab.vue'
    import ShopsTab from './tabs/ShopsTab.vue'
    import ShopTitle from './tabs/shops/ShopTitle.vue'

    export default {
        name: 'TabsList',
        props: {
            isShopsEnabled: {
                default: function () {
                    return false;
                },
            },
        },
        components: {
            UpcomingReservationsTab,
            ReservationsCalendarTab,
            CustomersAddressBookTab,
            ShopsTab,
            ShopTitle,
        },
        mounted() {
            window.addEventListener('hashchange', () => {
                this.hash = window.location.hash
            });
            let params = this.getQueryParams()
            if (typeof params['tab'] !== 'undefined') {
                this.hash = '#' + params['tab']
            }
        },
        data: function () {
            return {
                hash: window.location.hash ? window.location.hash : (this.isShopsEnabled ? '#shops' : '#upcoming-reservations'),
                shop: null,
                isHideTabsHeader: false,
            }
        },
        methods: {
            click(href) {
                window.location.href = href
            },
            isActiveTab(hash) {
                return this.hash === hash ? '' : undefined
            },
            applyShop(shop) {
                this.shop = shop
                this.$refs['upcoming-reservations-tab-link'].click()
                this.$emit('applyShop', shop)
            },
            hideTabsHeader(hide) {
                this.isHideTabsHeader = hide
            },
        },
        emits: ['applyShop'],
    }
</script>

<style scoped>
    :deep(.tab-content) {
        margin: 0 30px;
        min-height: calc(100vh - 115px);
        padding-bottom: 50px;
    }
    .tabs :deep(.card-header-tabs) .nav-link.active {
        background-color: #7F8CA2;
    }
    :deep(.card-header) {
        position: fixed;
        width: 100%;
        background-color: #7F8CA2;
        z-index: 100000;
        bottom: 0;
    }
    :deep(.card-header-tabs) {
        font-size: 24px;
        margin: 0 14px;
    }
    :deep(.nav-pills) .nav-link.active {
        color: #C7CED9;
    }
    :deep(.nav-pills) .nav-link {
        color: #fff;
    }
    .tabs :deep(.card-header-tabs) .nav-item.hide {
        display: none;
    }
    .hide-tabs-header :deep(.card-header) {
        display: none;
    }
</style>