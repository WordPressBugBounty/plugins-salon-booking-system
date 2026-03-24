<template>
    <div class="upcoming-screen">
        <ProUpgradeModal
            v-model="proUpgradeModalVisible"
            :message-override="this.getLabel('proUpgradeBookingDetailsMessage')"
            :navigate-to-upcoming-on-dismiss="false"
        />
        <!-- Header -->
        <div class="screen-header">
            <h1 class="screen-title">
                {{ this.getLabel('upcomingReservationsTitle') }}
                <span v-if="isShopsEnabled && shop && shop.name" class="screen-subtitle"> - {{ shop.name }}</span>
                <span v-else-if="isShopsEnabled && !shop" class="screen-subtitle screen-subtitle--warning"> - No shop selected</span>
            </h1>
            <button class="header-icon-btn" @click="isSearchVisible = !isSearchVisible" aria-label="Search">
                <font-awesome-icon :icon="isSearchVisible ? 'fa-solid fa-circle-xmark' : 'fa-solid fa-magnifying-glass'" />
            </button>
        </div>

        <!-- Search bar (expands on icon tap) -->
        <div class="search-bar" v-if="isSearchVisible">
            <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="search-bar-icon" />
            <input
                v-model="search"
                class="search-bar-input"
                placeholder="Search bookings…"
                autofocus
            />
            <font-awesome-icon icon="fa-solid fa-circle-xmark" class="search-bar-clear" @click="search = ''; isSearchVisible = false" v-if="search"/>
        </div>

        <!-- Time filter chips -->
        <div class="filter-chips" v-if="!search">
            <button
                v-for="hour in hours"
                :key="hour.hours"
                class="chip"
                :class="{ 'chip--active': hourValue === hour.hours }"
                @click="hourValue = hour.hours"
            >
                {{ hour.label }}
            </button>
        </div>

        <AssistantFilterChips
            v-if="canUseAssistantFilter && attendants.length > 0 && !search"
            v-model="filterAttendant"
            :assistants="attendants"
        />

        <!-- Single scroll column: full bookings list then promo (no nested list scroller — it trapped scroll on “All” with many cards) -->
        <div class="loading-center" v-if="isLoading">
            <b-spinner></b-spinner>
        </div>
        <div v-else class="upcoming-bookings-and-promo">
            <div
                v-if="filteredBookingsList.length > 0"
                class="bookings-scroll-region"
                :aria-label="getLabel('upcomingBookingsScrollAriaLabel')"
            >
                <div class="bookings-list bookings-list--stacked">
                    <BookingItem
                        v-for="booking in filteredBookingsList"
                        :key="booking.id"
                        :booking="booking"
                        @deleteItem="deleteItem(booking.id)"
                        @showDetails="showDetails(booking)"
                    />
                </div>
            </div>
            <div v-else class="empty-state">
                <div class="empty-state-icon">
                    <font-awesome-icon icon="fa-regular fa-circle-check" />
                </div>
                <p class="empty-state-title">No upcoming bookings</p>
                <p class="empty-state-sub">{{ this.getLabel('upcomingReservationsNoResultLabel') }}</p>
            </div>
            <UpcomingPromoCarousel />
        </div>
    </div>
</template>

<script>

    import AssistantFilterChips from '../../AssistantFilterChips.vue'
    import ProUpgradeModal from '../../ProUpgradeModal.vue'
    import BookingItem from './BookingItem.vue'
    import UpcomingPromoCarousel from './UpcomingPromoCarousel.vue'
    import { assistantIdsWithBookings, isAssistantBusyNow } from '../../../utils/assistantFilterBusy'

    export default {
        name: 'UpcomingReservations',
        props: {
            shop: {
                default: function () {
                    return {};
                },
            }
        },
        data: function () {
            return {
                hours: [
                    {label: this.getLabel('label8Hours'), hours: 8},
                    {label: this.getLabel('label24Hours'), hours: 24},
                    {label: this.getLabel('label3Days'), hours: 72},
                    {label: this.getLabel('label1Week'), hours: 168},
                ],
                hourValue: 8,
                bookingsList: [],
                isLoading: false,
                filterAttendant: '',
                search: '',
                proUpgradeModalVisible: false,
                timeout: null,
                isSearchVisible: false,
                /** id -> image_url from GET assistants (fallback when booking payload has no assistant_image_url) */
                assistantImagesById: {},
                /** Full shop assistant list from GET assistants (filter strip shows everyone) */
                assistantFilterList: [],
                /** Bumps periodically so busy/bounce state refreshes without full reload */
                busyNowTick: 0,
            }
        },
        mounted() {
            this.load();
            setInterval(() => this.update(), 60000);
            this._busyNowIntervalId = setInterval(() => {
                this.busyNowTick = Date.now();
            }, 30000);
        },
        beforeUnmount() {
            if (this._busyNowIntervalId) {
                clearInterval(this._busyNowIntervalId);
            }
        },
        components: {
            AssistantFilterChips,
            ProUpgradeModal,
            BookingItem,
            UpcomingPromoCarousel,
        },
        watch: {
            hourValue(newVal) {
                newVal && this.load();
            },
            search(newVal) {
                if (newVal) {
                    this.hourValue = ''
                    this.loadSearch()
                } else {
                    this.hourValue = 8
                }
            },
            shop() {
                this.load()
            },
        },
        computed: {
            isShopsEnabled() {
                return window.slnPWA?.is_shops || false;
            },
            attendants() {
                void this.busyNowTick;
                if (!this.canUseAssistantFilter) return [];
                const allRow = {
                    id: '',
                    name: this.getLabel('allTitle'),
                    imageUrl: '',
                    filterable: true,
                    busyNow: false,
                };
                const bookings = this.bookingsList || [];
                const withBookings = assistantIdsWithBookings(bookings);
                const nowMs = Date.now();

                const byId = new Map();
                (this.assistantFilterList || []).forEach((a) => {
                    const idNum = Number(a.id);
                    if (!Number.isFinite(idNum) || idNum <= 0) return;
                    byId.set(idNum, {
                        id: idNum,
                        name: a.name || '',
                        image_url: typeof a.image_url === 'string' ? a.image_url.trim() : '',
                    });
                });
                bookings.forEach((b) => {
                    (b.services || []).forEach((s) => {
                        const idNum = Number(s.assistant_id);
                        if (!Number.isFinite(idNum) || idNum <= 0) return;
                        if (!byId.has(idNum)) {
                            byId.set(idNum, {
                                id: idNum,
                                name: s.assistant_name || ('#' + idNum),
                                image_url: '',
                            });
                        }
                    });
                });

                const apiOrder = (this.assistantFilterList || [])
                    .map((a) => Number(a.id))
                    .filter((n) => Number.isFinite(n) && n > 0);
                const extraIds = [...byId.keys()]
                    .filter((id) => !apiOrder.includes(id))
                    .sort((a, b) => String(byId.get(a).name || '').localeCompare(String(byId.get(b).name || ''), undefined, { sensitivity: 'base' }));
                const orderedIds = [...apiOrder.filter((id) => byId.has(id)), ...extraIds];

                if (orderedIds.length === 0) return [];

                const rows = orderedIds.map((id) => {
                    const raw = byId.get(id);
                    const fromMap = (this.assistantImagesById[id] || '').trim();
                    const imageUrl = fromMap || raw.image_url || '';
                    return {
                        id,
                        name: raw.name,
                        imageUrl,
                        filterable: withBookings.has(id),
                        busyNow: isAssistantBusyNow(bookings, id, nowMs, this.moment),
                    };
                });

                return [allRow, ...rows];
            },
            filteredBookingsList() {
                if (!this.canUseAssistantFilter) {
                    return this.bookingsList;
                }
                return this.bookingsList.filter((booking) => {
                    var existsAttendant = false
                    booking.services.forEach((service) => {
                        if (String(this.filterAttendant) === String(service.assistant_id)) {
                            existsAttendant = true
                        }
                    })
                    return this.filterAttendant === '' || existsAttendant
                });
            },
        },
        methods: {
            deleteItem(id) {
                this.axios
                    .delete('bookings/' + id)
                    .then(() => {
                        this.bookingsList = this.bookingsList.filter(item => item.id !== id)
                    })
            },
            showDetails(booking) {
                if (window.slnPWA?.is_pro !== true) {
                    this.proUpgradeModalVisible = true
                    return
                }
                this.$emit('showItem', booking)
            },
            fetchAssistantThumbs() {
                if (!this.canUseAssistantFilter) {
                    return Promise.resolve();
                }
                const params = {
                    per_page: -1,
                    orderby: 'order',
                    order: 'asc',
                };
                if (this.isShopsEnabled && this.shop && this.shop.id) {
                    params.shop = this.shop.id;
                }
                return this.axios
                    .get('assistants', { params })
                    .then((response) => {
                        const items = response.data.items || [];
                        this.assistantFilterList = items;
                        const map = { ...this.assistantImagesById };
                        items.forEach((a) => {
                            const idNum = Number(a.id);
                            if (!Number.isFinite(idNum) || idNum <= 0) return;
                            const url = typeof a.image_url === 'string' ? a.image_url.trim() : '';
                            if (url) {
                                map[idNum] = url;
                            }
                        });
                        this.assistantImagesById = map;
                    })
                    .catch(() => { /* optional: keep previous map */ });
            },
            /**
             * Shop-filtered GET assistants/ omits staff not linked to that shop in meta,
             * but bookings can still reference them — fetch each missing id via GET assistants/:id.
             */
            enrichAssistantThumbsForBookings(bookings) {
                if (!this.canUseAssistantFilter || !Array.isArray(bookings) || !bookings.length) {
                    return Promise.resolve();
                }
                const ids = new Set();
                bookings.forEach((b) => {
                    (b.services || []).forEach((s) => {
                        const aid = s.assistant_id;
                        if (typeof aid !== 'number' && typeof aid !== 'string') return;
                        const idNum = Number(aid);
                        if (!Number.isFinite(idNum) || idNum <= 0) return;
                        const have = (this.assistantImagesById[idNum] || '').trim()
                            || (s.assistant_image_url || '').trim();
                        if (!have) {
                            ids.add(idNum);
                        }
                    });
                });
                if (!ids.size) {
                    return Promise.resolve();
                }
                return Promise.all(
                    [...ids].map((id) =>
                        this.axios
                            .get(`assistants/${id}`)
                            .then((r) => {
                                const item = r.data?.items?.[0];
                                const url = item?.image_url && String(item.image_url).trim();
                                return url ? { id, url } : null;
                            })
                            .catch(() => null)
                    )
                ).then((rows) => {
                    const next = { ...this.assistantImagesById };
                    rows.forEach((row) => {
                        if (row) {
                            next[row.id] = row.url;
                        }
                    });
                    this.assistantImagesById = next;
                });
            },
            load() {
                this.isLoading = true;
                this.bookingsList = [];
                this.assistantImagesById = {};
                this.assistantFilterList = [];
                const upcomingParams = { hours: this.hourValue, shop: this.shop ? this.shop.id : null };
                Promise.all([
                    this.axios.get('bookings/upcoming', { params: upcomingParams }),
                    this.fetchAssistantThumbs(),
                ])
                    .then(([response]) => {
                        this.bookingsList = response.data.items;
                        return this.enrichAssistantThumbsForBookings(this.bookingsList);
                    })
                    .finally(() => {
                        this.isLoading = false;
                    });
            },
            loadSearch() {
                this.timeout && clearTimeout(this.timeout)
                this.timeout = setTimeout(() => {
                    this.isLoading = true;
                    this.bookingsList = [];
                    this.assistantImagesById = {};
                    this.assistantFilterList = [];
                    Promise.all([
                        this.axios.get('bookings', {params: {
                            search: this.search,
                            per_page: -1,
                            order_by: 'date_time',
                            order: 'asc',
                            start_date: this.moment().format('YYYY-MM-DD'),
                            shop: this.shop ? this.shop.id : null,
                        }}),
                        this.fetchAssistantThumbs(),
                    ])
                        .then(([response]) => {
                            this.bookingsList = response.data.items;
                            return this.enrichAssistantThumbsForBookings(this.bookingsList);
                        })
                        .finally(() => {
                            this.isLoading = false;
                        })
                }, 1000)
            },
            update() {
                Promise.all([
                    this.axios.get('bookings/upcoming', {params: {hours: this.hourValue, shop: this.shop ? this.shop.id : null}}),
                    this.fetchAssistantThumbs(),
                ]).then(([response]) => {
                    this.bookingsList = response.data.items;
                    return this.enrichAssistantThumbsForBookings(this.bookingsList);
                });
            },
        },
        emits: ['showItem']
    }
</script>

<style scoped>
/* ── Screen ── */
.upcoming-screen {
    padding-top: 4px;
}

/* ── Header ── */
.screen-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0 8px;
    flex-shrink: 0;
}

.screen-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--color-text-primary, #0F172A);
    margin: 0;
}

.screen-subtitle {
    font-size: 16px;
    font-weight: 500;
    color: var(--color-text-secondary, #64748B);
}

.screen-subtitle--warning {
    color: var(--color-warning, #F59E0B);
    font-style: italic;
}

.header-icon-btn {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: none;
    background: transparent;
    color: var(--color-text-secondary, #64748B);
    font-size: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background-color 0.12s ease;
}

.header-icon-btn:active {
    background-color: var(--color-border, #E2E8F0);
}

/* ── Search bar ── */
.search-bar {
    position: relative;
    margin-bottom: 12px;
}

.search-bar-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted, #94A3B8);
    font-size: 15px;
    pointer-events: none;
}

.search-bar-input {
    width: 100%;
    padding: 10px 40px 10px 40px;
    border-radius: var(--radius-pill, 999px);
    border: 1px solid var(--color-border, #E2E8F0);
    background-color: var(--color-surface, #FFFFFF);
    font-size: 15px;
    font-family: inherit;
    color: var(--color-text-primary, #0F172A);
    outline: none;
}

.search-bar-input::placeholder {
    color: var(--color-text-muted, #94A3B8);
}

.search-bar-input:focus {
    border-color: var(--color-primary, #2563EB);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-bar-clear {
    position: absolute;
    right: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--color-text-muted, #94A3B8);
    cursor: pointer;
    font-size: 15px;
}

/* ── Filter chips ── */
.filter-chips {
    display: flex;
    gap: 8px;
    overflow-x: auto;
    padding-bottom: 2px;
    margin-bottom: 10px;
    scrollbar-width: none;
}

.filter-chips::-webkit-scrollbar {
    display: none;
}

.chip {
    flex-shrink: 0;
    padding: 6px 16px;
    border-radius: var(--radius-pill, 999px);
    border: 1px solid var(--color-border, #E2E8F0);
    background-color: var(--color-background, #F4F6FA);
    color: var(--color-text-secondary, #64748B);
    font-size: 13px;
    font-weight: 500;
    font-family: inherit;
    cursor: pointer;
    transition: all 0.12s ease;
    white-space: nowrap;
}

.chip--active {
    background-color: var(--color-primary, #2563EB);
    border-color: var(--color-primary, #2563EB);
    color: #FFFFFF;
}

/*
 * One scroll column for bookings + promo (filters/header sit outside).
 * Avoid a second scroll inside the list: with many bookings (“All”), nested overflow
 * captures touch and the promo below the list never scrolls into view.
 */
.upcoming-bookings-and-promo {
    min-height: 0;
    max-height: calc(100vh - 168px);
    max-height: calc(100dvh - 168px);
    overflow-y: auto;
    overflow-x: hidden;
    -webkit-overflow-scrolling: touch;
    overscroll-behavior-y: contain;
    /* Clear fixed bottom tab bar + home indicator when scrolled to the promo */
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 100px);
}

.bookings-scroll-region {
    min-height: 0;
}

.bookings-list.bookings-list--stacked {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding-bottom: 8px;
}

.loading-center {
    display: flex;
    justify-content: center;
    padding: 40px 0;
}

/* ── Empty state ── */
.empty-state {
    text-align: center;
    padding: 60px 24px 24px;
}

.empty-state-icon {
    font-size: 48px;
    color: var(--color-text-muted, #94A3B8);
    margin-bottom: 16px;
}

.empty-state-title {
    font-size: 17px;
    font-weight: 600;
    color: var(--color-text-primary, #0F172A);
    margin: 0 0 6px;
}

.empty-state-sub {
    font-size: 14px;
    color: var(--color-text-secondary, #64748B);
    margin: 0;
}
</style>