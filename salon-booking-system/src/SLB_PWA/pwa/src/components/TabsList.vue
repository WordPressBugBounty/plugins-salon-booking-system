<template>
    <div :class="{'hide-tabs-header': isHideTabsHeader}" >
        <b-tabs pills card end>
            <b-tab v-if="isShopsEnabled" :active="isActiveTab('#shops')" :title-item-class="{ hide: !isShopsEnabled }" >
                <template #title><span @click="click('#shops')"><font-awesome-icon icon="fa-solid fa-store" /></span></template>
                <ShopsTab :isShopsEnabled="isShopsEnabled" @applyShop="applyShopAndSwitch"/>
            </b-tab>
            <b-tab :active="isActiveTab('#upcoming-reservations')">
                <ShopTitle v-if="isShopsEnabled" :shop="shop" @applyShop="applyShop"/>
                <template #title><span @click="click('#upcoming-reservations');scrollInto()" ref="upcoming-reservations-tab-link"><font-awesome-icon icon="fa-solid fa-list" /></span></template>
                <UpcomingReservationsTab :shop="shop" @hideTabsHeader="hideTabsHeader"/>
            </b-tab>
            <b-tab :active="isActiveTab('#reservations-calendar')">
                <ShopTitle v-if="isShopsEnabled" :shop="shop" @applyShop="applyShop"/>
                <template #title><span @click="click('#reservations-calendar')"><font-awesome-icon icon="fa-solid fa-calendar-days" /></span></template>
                <ReservationsCalendarTab :shop="shop" @hideTabsHeader="hideTabsHeader"/>
            </b-tab>
            <b-tab :active="isActiveTab('#customers')">
                <ShopTitle v-if="isShopsEnabled" :shop="shop" @applyShop="applyShop"/>
                <template #title><span @click="click('#customers')"><font-awesome-icon icon="fa-regular fa-address-book" /></span></template>
                <CustomersAddressBookTab :shop="shop" @hideTabsHeader="hideTabsHeader"/>
            </b-tab>
            <b-tab title-item-class="nav-item-profile" :active="isActiveTab('#user-profile')">
                <template #title>
                    <span @click="click('#user-profile')">
                      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 27 30" class="svg-inline--fa">
                        <g fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="3">
                            <path d="M25.5 28.5v-3a6 6 0 0 0-6-6h-12a6 6 0 0 0-6 6v3"></path>
                            <path d="M19.5 7.5a6 6 0 1 1-6-6 6 6 0 0 1 6 6Z"></path>
                        </g>
                      </svg>
                  </span>
                </template>
                <UserProfileTab/>
            </b-tab>
        </b-tabs>
    </div>
</template>

<script>

    import UpcomingReservationsTab from './tabs/UpcomingReservationsTab.vue'
    import ReservationsCalendarTab from './tabs/ReservationsCalendarTab.vue'
    import CustomersAddressBookTab from './tabs/CustomersAddressBookTab.vue'
    import UserProfileTab from './tabs/UserProfileTab.vue';
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
            UserProfileTab,
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
                isShopSelected: false,
            }
        },
        watch: {
            shop(newShop) {
                this.isShopSelected = !!newShop && !!newShop.id;
            },
        },
        methods: {
            click(href) {
                window.location.href = href;
              if(document.querySelector('.dp__active_date.dp__today') !== null){
                document.querySelector('.current-time-line').style.display = 'block';
                document.querySelector('.current-time-line').scrollIntoView({ behavior: 'smooth', block: 'center' })
              } else {
                document.querySelector('.current-time-line').style.display = 'none';
              }
            },
            isActiveTab(hash) {
                return this.hash === hash ? '' : undefined
            },
            applyShop(shop) {
                this.shop = shop
                this.$emit('applyShop', shop)
            },
            applyShopAndSwitch(shop) {
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
      color: #fff;
    }
    :deep(.nav-pills) .nav-link {
      color: #C7CED9;
    }
    .tabs :deep(.card-header-tabs) .nav-item.hide {
        display: none;
    }
    .hide-tabs-header :deep(.card-header) {
        display: none;
    }
    :deep(.nav-item-profile) {
      margin-left: auto;
    }
</style>