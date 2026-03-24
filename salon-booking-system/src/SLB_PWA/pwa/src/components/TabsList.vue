<template>
    <div :class="{'hide-tabs-header': isHideTabsHeader}" >
        <ProUpgradeModal v-model="proUpgradeModalVisible" @dismiss-to-upcoming="goToUpcomingReservations" />
        <b-tabs
            :key="'sln-pwa-tabs-' + tabsMountKey"
            v-model="tabModelIndex"
            pills
            card
            end
            @activate-tab="onActivateTab"
        >
            <b-tab v-if="isShopsEnabled" :title-item-class="{ hide: !isShopsEnabled }" >
                <template #title>
                    <span class="tab-item" @click="click('#shops')">
                        <font-awesome-icon icon="fa-solid fa-store" class="tab-icon" />
                        <span class="tab-label">Shops</span>
                    </span>
                </template>
                <ShopsTab :isShopsEnabled="isShopsEnabled" @applyShop="applyShopAndSwitch"/>
            </b-tab>
            <b-tab>
                <ShopTitle v-if="isShopsEnabled" :shop="shop" @applyShop="applyShop"/>
                <template #title>
                    <span
                        class="tab-item"
                        ref="upcoming-reservations-tab-link"
                        @click="click('#upcoming-reservations'); scrollUpcomingIntoViewIfNeeded()"
                    >
                        <font-awesome-icon icon="fa-solid fa-list" class="tab-icon" />
                        <span class="tab-label">Upcoming</span>
                    </span>
                </template>
                <UpcomingReservationsTab :shop="shop" @hideTabsHeader="hideTabsHeader"/>
            </b-tab>
            <b-tab>
                <ShopTitle v-if="isShopsEnabled" :shop="shop" @applyShop="applyShop"/>
                <template #title>
                    <span class="tab-item" @click="navigateToTab('#reservations-calendar', $event)">
                        <font-awesome-icon icon="fa-solid fa-calendar-days" class="tab-icon" />
                        <span class="tab-label">Calendar</span>
                    </span>
                </template>
                <ReservationsCalendarTab
                    v-if="isProUser"
                    :shop="shop"
                    @hideTabsHeader="hideTabsHeader"
                />
                <div v-else class="pwa-pro-only-tab-placeholder" />
            </b-tab>
            <b-tab>
                <ShopTitle v-if="isShopsEnabled" :shop="shop" @applyShop="applyShop"/>
                <template #title>
                    <span class="tab-item" @click="navigateToTab('#customers', $event)">
                        <font-awesome-icon icon="fa-regular fa-address-book" class="tab-icon" />
                        <span class="tab-label">Customers</span>
                    </span>
                </template>
                <CustomersAddressBookTab
                    v-if="isProUser"
                    :shop="shop"
                    @hideTabsHeader="hideTabsHeader"
                />
                <div v-else class="pwa-pro-only-tab-placeholder" />
            </b-tab>
            <b-tab title-item-class="nav-item-profile">
                <template #title>
                    <span class="tab-item" @click="click('#user-profile')">
                        <font-awesome-icon icon="fa-solid fa-user-alt" class="tab-icon" />
                        <span class="tab-label">Profile</span>
                    </span>
                </template>
                <UserProfileTab/>
            </b-tab>
        </b-tabs>
    </div>
</template>

<script>
    import { defineAsyncComponent, h } from 'vue'
    import ShopTitle from './tabs/shops/ShopTitle.vue'
    import ProUpgradeModal from './ProUpgradeModal.vue'

    const PwaTabLoading = {
        name: 'PwaTabLoading',
        render() {
            return h('div', { class: 'pwa-tab-async-loading', role: 'status', 'aria-busy': 'true' });
        },
    };

    const asyncOpts = {
        loadingComponent: PwaTabLoading,
        delay: 0,
        timeout: 120000,
    };

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
            UpcomingReservationsTab: defineAsyncComponent({
                loader: () => import('./tabs/UpcomingReservationsTab.vue'),
                ...asyncOpts,
            }),
            ReservationsCalendarTab: defineAsyncComponent({
                loader: () => import('./tabs/ReservationsCalendarTab.vue'),
                ...asyncOpts,
            }),
            CustomersAddressBookTab: defineAsyncComponent({
                loader: () => import('./tabs/CustomersAddressBookTab.vue'),
                ...asyncOpts,
            }),
            UserProfileTab: defineAsyncComponent({
                loader: () => import('./tabs/UserProfileTab.vue'),
                ...asyncOpts,
            }),
            ShopsTab: defineAsyncComponent({
                loader: () => import('./tabs/ShopsTab.vue'),
                ...asyncOpts,
            }),
            ShopTitle,
            ProUpgradeModal,
        },
        created() {
            let h = this.resolveInitialHashFromUrl()
            if (!this.isProUser && this.isProOnlyHash(h)) {
                this.proUpgradeModalVisible = true
                h = '#upcoming-reservations'
                this.syncLocationToHash(h)
            }
            this.hash = h
            this.tabModelIndex = this.hashToTabIndex(this.hash)
        },
        mounted() {
            window.addEventListener('hashchange', this.onHashChange)

            // Restore selected shop from localStorage
            if (this.isShopsEnabled) {
                const savedShop = localStorage.getItem('sln_selected_shop');
                if (savedShop) {
                    try {
                        this.shop = JSON.parse(savedShop);
                        // If shop is already selected and user is on shops screen, navigate to upcoming
                        if (this.hash === '#shops' || !this.hash) {
                            this.hash = '#upcoming-reservations';
                            window.location.hash = '#upcoming-reservations';
                        }
                    } catch (e) {
                        console.error('Error parsing saved shop:', e);
                    }
                }
            }
        },
        beforeUnmount() {
            window.removeEventListener('hashchange', this.onHashChange)
        },
        data: function () {
            return {
                hash: '#upcoming-reservations',
                /** Drives BTabs active pane (must stay in sync with hash; see bootstrap-vue-3 BTabs modelValue). */
                tabModelIndex: 0,
                isSyncingTabs: false,
                proUpgradeModalVisible: false,
                /** Bump to remount BTabs so internal active index resets after PRO modal (preventDefault can desync v-model). */
                tabsMountKey: 0,
                /** Skip tab resync when modal closed via dismiss-to-upcoming (goToUpcomingReservations already applied). */
                skipResyncAfterModalClose: false,
                shop: null,
                isHideTabsHeader: false,
                isShopSelected: false,
            }
        },
        computed: {
            /** Strict: only boolean true counts as PRO (avoids truthy strings from bad JSON). */
            isProUser() {
                return window.slnPWA?.is_pro === true
            },
        },
        watch: {
            shop(newShop) {
                this.isShopSelected = !!newShop && !!newShop.id;
            },
            hash(newHash) {
                const idx = this.hashToTabIndex(newHash)
                if (this.tabModelIndex === idx) {
                    return
                }
                this.isSyncingTabs = true
                this.tabModelIndex = idx
                this.$nextTick(() => {
                    this.isSyncingTabs = false
                })
            },
            tabModelIndex(newIdx) {
                if (this.isSyncingTabs) {
                    return
                }
                if (!this.isProUser && this.isRestrictedTabIndex(newIdx)) {
                    this.proUpgradeModalVisible = true
                    this.resyncTabModelToHash()
                    return
                }
                const h = this.tabIndexToHash(newIdx)
                if (h !== this.hash) {
                    this.click(h)
                }
            },
            proUpgradeModalVisible(visible) {
                if (!visible && !this.isProUser) {
                    if (this.skipResyncAfterModalClose) {
                        this.skipResyncAfterModalClose = false
                        return
                    }
                    this.resyncTabModelToHash()
                }
            },
        },
        methods: {
            /**
             * BTabs runs before tab switch (cancelable). This blocks Calendar / Customers for free edition
             * even when the click hits the nav button outside the inner span.
             */
            onActivateTab(newIdx, _oldIdx, event) {
                if (this.isProUser) {
                    return
                }
                if (this.isRestrictedTabIndex(newIdx)) {
                    if (event && typeof event.preventDefault === 'function') {
                        event.preventDefault()
                    }
                    this.proUpgradeModalVisible = true
                }
            },
            resyncTabModelToHash() {
                this.isSyncingTabs = true
                this.tabModelIndex = this.hashToTabIndex(this.hash)
                this.$nextTick(() => {
                    this.isSyncingTabs = false
                })
            },
            /** After "Not now" / backdrop / X — force URL + visible tab to Upcoming. */
            goToUpcomingReservations() {
                this.skipResyncAfterModalClose = true
                const target = '#upcoming-reservations'
                const idx = this.hashToTabIndex(target)
                this.hash = target
                this.syncLocationToHash(target)
                this.tabModelIndex = idx
                this.tabsMountKey += 1
                this.$nextTick(() => {
                    this.tabModelIndex = idx
                    this.$nextTick(() => {
                        window.setTimeout(() => {
                            this.click(target)
                        }, 50)
                    })
                })
            },
            scrollUpcomingIntoViewIfNeeded() {
                try {
                    const line = document.querySelector('.current-time-line')
                    if (!line || document.querySelector('.dp__active_date.dp__today') === null) {
                        if (line) {
                            line.style.display = 'none'
                        }
                        return
                    }
                    line.style.display = 'block'
                    line.scrollIntoView({ behavior: 'smooth', block: 'center' })
                } catch (e) {
                    /* ignore */
                }
            },
            hashToTabIndex(hash) {
                const sh = this.isShopsEnabled
                const map = sh
                    ? {
                        '#shops': 0,
                        '#upcoming-reservations': 1,
                        '#reservations-calendar': 2,
                        '#customers': 3,
                        '#user-profile': 4,
                    }
                    : {
                        '#upcoming-reservations': 0,
                        '#reservations-calendar': 1,
                        '#customers': 2,
                        '#user-profile': 3,
                    }
                if (Object.prototype.hasOwnProperty.call(map, hash)) {
                    return map[hash]
                }
                return sh ? 1 : 0
            },
            tabIndexToHash(idx) {
                const sh = this.isShopsEnabled
                const arr = sh
                    ? ['#shops', '#upcoming-reservations', '#reservations-calendar', '#customers', '#user-profile']
                    : ['#upcoming-reservations', '#reservations-calendar', '#customers', '#user-profile']
                return arr[idx] != null ? arr[idx] : '#upcoming-reservations'
            },
            isRestrictedTabIndex(idx) {
                const sh = this.isShopsEnabled
                const cal = sh ? 2 : 1
                const cust = sh ? 3 : 2
                return idx === cal || idx === cust
            },
            resolveInitialHashFromUrl() {
                const params = this.getQueryParams()
                if (typeof params.tab !== 'undefined' && params.tab) {
                    const t = decodeURIComponent(String(params.tab)).replace(/^#/, '')
                    return t.startsWith('#') ? t : '#' + t
                }
                if (window.location.hash) {
                    return window.location.hash
                }
                return this.isShopsEnabled ? '#shops' : '#upcoming-reservations'
            },
            isProOnlyHash(hash) {
                if (!hash) {
                    return false
                }
                const h = hash.startsWith('#') ? hash : '#' + hash
                return h === '#reservations-calendar' || h === '#customers'
            },
            syncLocationToHash(hash) {
                try {
                    const u = new URL(window.location.href)
                    u.searchParams.delete('tab')
                    const frag = (hash.startsWith('#') ? hash : '#' + hash).slice(1)
                    u.hash = frag
                    window.history.replaceState(null, '', u.toString())
                } catch (e) {
                    window.location.hash = hash.startsWith('#') ? hash : '#' + hash
                }
            },
            onHashChange() {
                const next = window.location.hash || (this.isShopsEnabled ? '#shops' : '#upcoming-reservations')
                if (!this.isProUser && this.isProOnlyHash(next)) {
                    this.proUpgradeModalVisible = true
                    this.hash = '#upcoming-reservations'
                    this.syncLocationToHash('#upcoming-reservations')
                    return
                }
                this.hash = next
            },
            navigateToTab(href, e) {
                if (!this.isProUser && this.isProOnlyHash(href)) {
                    if (e) {
                        e.preventDefault()
                        e.stopPropagation()
                    }
                    this.proUpgradeModalVisible = true
                    this.resyncTabModelToHash()
                    return
                }
                this.click(href)
            },
            click(href) {
                window.location.href = href;
                const line = document.querySelector('.current-time-line')
                if (!line) {
                    return
                }
                if (document.querySelector('.dp__active_date.dp__today') !== null) {
                    line.style.display = 'block'
                    line.scrollIntoView({ behavior: 'smooth', block: 'center' })
                } else {
                    line.style.display = 'none'
                }
            },
            applyShop(shop) {
                this.shop = shop
                // Persist shop selection to localStorage
                if (shop && shop.id) {
                    localStorage.setItem('sln_selected_shop', JSON.stringify(shop));
                } else {
                    localStorage.removeItem('sln_selected_shop');
                }
                this.$emit('applyShop', shop)
            },
            applyShopAndSwitch(shop) {
                this.shop = shop
                // Persist shop selection to localStorage
                if (shop && shop.id) {
                    localStorage.setItem('sln_selected_shop', JSON.stringify(shop));
                } else {
                    localStorage.removeItem('sln_selected_shop');
                }
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
    .pwa-pro-only-tab-placeholder {
        min-height: 45vh;
    }

    /* ── Content area ── */
    :deep(.tab-content) {
        margin: 0;
        padding: 0 var(--spacing-page, 16px);
        min-height: calc(100vh - 72px);
        min-height: calc(100dvh - 72px);
        padding-bottom: 88px;
        padding-top: 8px;
    }

    /* ── Card wrapper ── */
    :deep(.card) {
        background-color: transparent;
        border: none;
    }

    /* ── Bottom nav bar ── */
    :deep(.card-header) {
        position: fixed;
        width: 100%;
        bottom: 0;
        background-color: #FFFFFF;
        border-top: 1px solid #E2E8F0;
        box-shadow: 0 -2px 12px rgba(0, 0, 0, 0.06);
        z-index: 100000;
        padding: 0;
        padding-bottom: env(safe-area-inset-bottom, 0);
    }

    :deep(.card-header-tabs) {
        margin: 0;
        border: none;
    }

    /* ── Nav pills layout ── */
    :deep(.nav-pills) {
        display: flex;
        width: 100%;
        justify-content: space-around;
        align-items: stretch;
    }

    :deep(.nav-pills) .nav-item {
        flex: 1;
        display: flex;
        justify-content: center;
    }

    :deep(.nav-pills) .nav-link {
        padding: 10px 4px 8px;
        border-radius: 0;
        background: transparent;
        color: var(--color-nav-inactive, #94A3B8);
        display: flex;
        align-items: center;
        justify-content: center;
        width: 100%;
        position: relative;
        transition: color 0.15s ease;
    }

    /* Active state */
    :deep(.nav-pills) .nav-link.active {
        background: transparent;
        color: var(--color-nav-active, #2563EB);
    }

    /* Blue pill indicator above icon on active tab */
    :deep(.nav-pills) .nav-link.active::before {
        content: '';
        position: absolute;
        top: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 24px;
        height: 4px;
        background-color: var(--color-primary, #2563EB);
        border-radius: 0 0 4px 4px;
    }

    /* Hide tabs when requested */
    :deep(.card-header-tabs) .nav-item.hide {
        display: none;
    }

    .hide-tabs-header :deep(.card-header) {
        display: none;
    }

    .pwa-tab-async-loading {
        min-height: 200px;
        margin: 24px 0;
        border-radius: 12px;
        background: linear-gradient(90deg, #E2E8F0 25%, #CBD5E1 50%, #E2E8F0 75%);
        background-size: 200% 100%;
        animation: pwa-tab-skeleton-shimmer 1.2s infinite;
    }

    @keyframes pwa-tab-skeleton-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    /* Profile tab pushed to right */
    :deep(.nav-item-profile) {
        margin-left: auto;
    }

    /* ── Tab item inner layout ── */
    :deep(.tab-item) {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 3px;
        cursor: pointer;
        width: 100%;
        min-height: 100%;
    }

    :deep(.tab-icon) {
        font-size: 20px;
        line-height: 1;
    }

    :deep(.tab-label) {
        font-size: 10px;
        font-weight: 500;
        font-family: 'Inter', sans-serif;
        line-height: 1;
        letter-spacing: 0.01em;
    }
</style>