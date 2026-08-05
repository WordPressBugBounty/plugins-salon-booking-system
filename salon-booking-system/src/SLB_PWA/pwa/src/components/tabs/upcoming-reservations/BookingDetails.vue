<template>
  <div v-show="show" class="booking-detail-screen">

    <!-- Header -->
    <div class="detail-header">
      <button class="back-btn" @click="close">
        <font-awesome-icon icon="fa-solid fa-arrow-left" />
      </button>
      <h1 class="detail-title">{{ getLabel('bookingDetailsTitle') }} #{{ bookingData.id }}</h1>
      <button class="header-btn" @click="edit">
        <font-awesome-icon icon="fa-solid fa-pen-to-square" />
      </button>
    </div>

    <!-- Date / Time / Status -->
    <div class="detail-card">
      <div class="detail-row">
        <span class="detail-label">
          <font-awesome-icon icon="fa-solid fa-calendar-days" class="detail-icon" />
          {{ getLabel('dateTitle') }}
        </span>
        <span class="detail-value">{{ date }}</span>
      </div>
      <div class="detail-row">
        <span class="detail-label">
          <font-awesome-icon icon="fa-regular fa-clock" class="detail-icon" />
          {{ getLabel('timeTitle') }}
        </span>
        <span class="detail-value">{{ time }}</span>
      </div>
      <div class="detail-row detail-row--last">
        <span class="detail-label">Status</span>
        <span class="status-pill">{{ status }}</span>
      </div>
    </div>

    <!-- Customer -->
    <div class="detail-card">
      <p class="section-label">Customer</p>
      <component
        :is="hasCustomerProfile ? 'button' : 'div'"
        class="customer-row"
        :class="{ 'customer-row--tappable': hasCustomerProfile }"
        @click="viewCustomerProfile"
        type="button"
      >
        <div class="customer-avatar-sm">{{ customerInitials }}</div>
        <div class="customer-info-block">
          <div class="customer-full-name">{{ customerFirstname }} {{ customerLastname }}</div>
          <div class="customer-contact-line" v-if="customerEmail">{{ getDisplayEmail(customerEmail) }}</div>
          <div class="customer-contact-line" v-if="customerPhone">{{ getDisplayPhone(customerPhone) }}</div>
        </div>
        <div class="customer-row-right">
          <div class="photo-thumb" @click.stop="showCustomerImages">
            <img :src="photos[0]['url']" v-if="photos.length > 0" />
            <font-awesome-icon icon="fa-solid fa-images" v-else />
          </div>
          <font-awesome-icon
            v-if="hasCustomerProfile"
            icon="fa-solid fa-chevron-right"
            class="customer-chevron"
          />
        </div>
      </component>
      <div class="contact-actions" v-if="customerPhone && !shouldHidePhone">
        <a :href="'tel:' + customerPhone" class="contact-btn"><font-awesome-icon icon="fa-solid fa-phone" /></a>
        <a :href="'sms:' + customerPhone" class="contact-btn"><font-awesome-icon icon="fa-solid fa-message" /></a>
        <a :href="'https://wa.me/' + customerPhone" class="contact-btn"><font-awesome-icon icon="fa-brands fa-whatsapp" /></a>
      </div>
    </div>

    <!-- Services -->
    <div class="detail-card" v-if="services && services.length">
      <p class="section-label">Services</p>
      <div class="service-row-item" v-for="(service, index) in services" :key="index">
        <div class="service-name-price">
          <span class="service-name">{{ service.service_name }}</span>
          <div class="service-price-block">
            <span class="service-duration" v-if="serviceDurationMinutes(service)">{{ formatDuration(serviceDurationMinutes(service)) }}</span>
            <span class="service-price" v-html="service.service_price + booking.currency"></span>
          </div>
        </div>
        <div class="service-meta" v-if="service.resource_name || service.assistant_name">
          <span v-if="service.resource_name">{{ service.resource_name }}</span>
          <span v-if="service.resource_name && service.assistant_name"> · </span>
          <span v-if="service.assistant_name">{{ service.assistant_name }}</span>
        </div>
      </div>
    </div>

    <!-- Payment -->
    <div class="detail-card">
      <p class="section-label">Payment</p>
      <div class="detail-row">
        <span class="detail-label">{{ getLabel('totalTitle') }}</span>
        <div class="total-value-block">
          <span class="total-duration" v-if="totalDuration">{{ totalDuration }}</span>
          <span class="detail-value detail-value--primary" v-html="totalSum"></span>
        </div>
      </div>
      <div class="detail-row" v-if="discount !== '-'">
        <span class="detail-label">{{ getLabel('discountTitle') }}</span>
        <span class="detail-value" v-html="discount"></span>
      </div>
      <div class="detail-row" v-if="deposit !== '-'">
        <span class="detail-label">{{ getLabel('depositTitle') }}</span>
        <span class="detail-value" v-html="deposit"></span>
      </div>
      <div class="detail-row" :class="deposit !== '-' ? '' : 'detail-row--last'" v-if="deposit !== '-'">
        <span class="detail-label">{{ getLabel('dueTitle') }}</span>
        <span class="detail-value detail-value--primary" v-html="due"></span>
      </div>
      <div class="detail-row detail-row--last" v-if="transactionId.length">
        <span class="detail-label">{{ getLabel('transactionIdTitle') }}</span>
        <span class="detail-value">{{ transactionId.join(', ') }}</span>
      </div>
      <div class="pay-remaining-wrap">
        <PayRemainingAmount :booking="booking" />
      </div>
    </div>

    <!-- Notes -->
    <div class="detail-card" v-if="customerNote || customerPersonalNote || adminNote">
      <p class="section-label">{{ getLabel('notesTitle') || 'Notes' }}</p>
      <div class="note-block" v-if="customerNote">
        <span class="note-block-label">{{ getLabel('customerMessageLabel') || 'Customer message' }}</span>
        <p class="note-block-text">{{ customerNote }}</p>
      </div>
      <div class="note-block" v-if="customerPersonalNote">
        <span class="note-block-label">{{ getLabel('customerPersonalNotesLabel') }}</span>
        <p class="note-block-text">{{ customerPersonalNote }}</p>
      </div>
      <div class="note-block" v-if="adminNote">
        <span class="note-block-label">{{ getLabel('adminNoteLabel') || 'Administration note' }}</span>
        <p class="note-block-text">{{ adminNote }}</p>
      </div>
    </div>

    <!-- Waiting list (Smart Waitlist add-on) -->
    <div class="detail-card" v-if="waitlist && waitlist.slot">
      <p class="section-label">Waiting list</p>
      <p class="wl-slot" v-if="waitlistSlotLabel">{{ waitlistSlotLabel }}</p>

      <!-- Add a customer to the waiting list for this slot -->
      <div class="wl-add">
        <div class="wl-add-field">
          <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="wl-add-icon" />
          <input
            type="text"
            class="wl-add-input"
            v-model="wlSearch"
            @input="onWlSearchInput"
            placeholder="Add a customer — search by name or email…"
          />
          <button v-if="wlSearch" type="button" class="wl-add-clear" @click="clearWlSearch">
            <font-awesome-icon icon="fa-solid fa-xmark" />
          </button>
        </div>
        <div class="wl-results" v-if="wlResults.length">
          <button
            type="button"
            class="wl-result"
            v-for="u in wlResults"
            :key="u.id"
            :disabled="addingId === u.id"
            @click="addCustomer(u.id)"
          >
            <span class="wl-result-avatar">{{ resultInitials(u) }}</span>
            <span class="wl-result-info">
              <span class="wl-result-name">{{ u.first_name }} {{ u.last_name }}</span>
              <span class="wl-result-sub" v-if="u.email">{{ getDisplayEmail(u.email) }}</span>
            </span>
            <font-awesome-icon v-if="addingId === u.id" icon="fa-solid fa-rotate-right" spin class="wl-result-icon" />
            <font-awesome-icon v-else icon="fa-solid fa-plus" class="wl-result-icon" />
          </button>
        </div>
        <p class="wl-add-hint" v-else-if="wlSearch.trim().length >= 2 && !wlSearching">No customers found</p>
      </div>

      <transition name="wl-flash-fade">
        <div class="wl-flash" :class="'wl-flash--' + waitlistFlashType" v-if="waitlistFlash">
          <font-awesome-icon
            :icon="waitlistFlashType === 'error' ? 'fa-solid fa-circle-xmark' : 'fa-regular fa-circle-check'"
            class="wl-flash-icon"
          />
          <span class="wl-flash-text">{{ waitlistFlash }}</span>
          <button type="button" class="wl-flash-close" @click="dismissFlash" aria-label="Dismiss">
            <font-awesome-icon icon="fa-solid fa-xmark" />
          </button>
        </div>
      </transition>

      <template v-if="waitlistCandidates.length">
        <p class="wl-note" :class="{ 'wl-note--ok': waitlistFreed }">{{ waitlistNote }}</p>
        <div class="wl-row" v-for="c in waitlistCandidates" :key="c.entry_id">
          <div class="wl-row-main">
            <div class="wl-name">
              <span class="wl-name-text">{{ c.name }}</span>
              <span class="wl-badge" :class="'wl-badge--' + c.match">{{ matchLabel(c.match) }}</span>
            </div>
            <div class="wl-meta">
              <span v-if="c.requested">{{ requestedLabel(c.requested) }}</span>
              <span v-if="c.requested && c.channels.length" class="wl-dot"> · </span>
              <span v-if="c.channels.length">{{ channelsLabel(c.channels) }}</span>
              <span class="wl-score" v-if="c.score">★ {{ c.score }}</span>
            </div>
          </div>
          <button
            v-if="waitlistFreed"
            type="button"
            class="wl-notify-btn"
            :class="{ 'wl-notify-btn--secondary': c.notified }"
            :disabled="notifyingId === c.entry_id"
            @click="notifyCandidate(c.entry_id)"
          >
            <span v-if="notifyingId === c.entry_id">…</span>
            <span v-else>{{ c.notified ? 'Notify again' : 'Notify' }}</span>
          </button>
        </div>
        <p class="wl-more" v-if="waitlist && waitlist.total > waitlist.shown">
          +{{ waitlist.total - waitlist.shown }} more waiting that day
        </p>
      </template>
      <p class="wl-empty" v-else>No customers are waiting for this day.</p>
    </div>

    <!-- Extra Info -->
    <div class="detail-card" v-if="bookingCustomFieldsList.length">
      <div class="collapsible-header" @click="visibleExtraInfo = !visibleExtraInfo">
        <p class="section-label mb-0">{{ getLabel('extraInfoLabel') }}</p>
        <font-awesome-icon :icon="visibleExtraInfo ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="collapsible-icon" />
      </div>
      <b-collapse v-model="visibleExtraInfo">
        <div class="extra-field" v-for="field in bookingCustomFieldsList" :key="field.key">
          <span class="extra-field-label">{{ field.label }}</span>
          <strong class="extra-field-value">{{ field.value }}</strong>
        </div>
      </b-collapse>
    </div>

  </div>
</template>

<script>
    import PayRemainingAmount from './PayRemainingAmount.vue'
    import mixins from "@/mixin";

    export default {
        name: 'BookingDetails',
        mixins: [mixins],
        props: {
            booking: {
                default: function () {
                    return {};
                },
            },
        },
        computed: {
            date() {
                return this.dateFormat(this.bookingData.date)
            },
            time() {
                return this.timeFormat(this.bookingData.time)
            },
            customerFirstname() {
                return this.bookingData.customer_first_name
            },
            customerLastname() {
                return this.bookingData.customer_last_name
            },
            customerEmail() {
                return this.getDisplayEmail(this.bookingData.customer_email);
            },
            customerPhone() {
                const phone = this.bookingData.customer_phone ?
                    this.bookingData.customer_phone_country_code + this.bookingData.customer_phone : '';
                return this.getDisplayPhone(phone);
            },
            customerNote() {
                return this.bookingData.note
            },
            customerPersonalNote() {
                return this.bookingData.customer_personal_note
            },
            adminNote() {
                return this.bookingData.admin_note
            },
            services() {
                return this.bookingData.services
            },
            totalSum() {
                return this.bookingData.amount + this.bookingData.currency
            },
            transactionId() {
                return this.bookingData.transaction_id
            },
            discount() {
                const dd = this.bookingData.discounts_details ?? [];
                return dd.length > 0 ? dd.map(item => item.name + ' (' + item.amount_string + ')').join(', ') : '-'
            },
            deposit() {
                return +this.bookingData.deposit > 0 ? (this.bookingData.deposit + this.bookingData.currency) : '-'
            },
            due() {
                return (+this.bookingData.amount - +this.bookingData.deposit) + this.bookingData.currency
            },
            status() {
                return this.$root.statusesList[this.booking.status].label
            },
            customFieldsList() {
                return this.bookingData.custom_fields.filter(i => ['html', 'file'].indexOf(i.type) === -1)
            },
            bookingCustomFieldsList() {
                return this.customFieldsList.filter(i => !i.is_customer && i.value)
            },
            photos() {
                return this.bookingData.customer_photos
            },
            customerInitials() {
                const f = this.bookingData.customer_first_name || '';
                const l = this.bookingData.customer_last_name || '';
                return ((f[0] || '') + (l[0] || '')).toUpperCase() || '?';
            },
            hasCustomerProfile() {
                return !!this.bookingData.customer_id && Number(this.bookingData.customer_id) > 0;
            },
            totalDuration() {
                if (this.bookingData.duration) {
                    return this.formatDuration(this.bookingData.duration);
                }
                return null;
            },
            waitlistCandidates() {
                return this.waitlist && Array.isArray(this.waitlist.candidates) ? this.waitlist.candidates : [];
            },
            waitlistFreed() {
                return !!(this.waitlist && this.waitlist.freed);
            },
            waitlistSlotLabel() {
                const slot = this.waitlist && this.waitlist.slot;
                if (!slot || !slot.date) {
                    return '';
                }
                const time = slot.time ? this.timeFormat(slot.time) : '';
                return (this.dateFormat(slot.date) + ' ' + time).trim();
            },
            waitlistNote() {
                return this.waitlistFreed
                    ? 'This slot is free — notify a customer to offer it to them. Exact time matches are listed first.'
                    : 'These customers are waiting for this day. Notifying is enabled once this booking is cancelled or marked no-show.';
            },
        },
        mounted() {
            this.toggleShow()
            this.update()
            setInterval(() => this.update(), 60000)
        },
        components: {
            PayRemainingAmount,
        },
        data: function () {
            return {
                show: true,
                visibleExtraInfo: false,
                bookingData: this.booking,
                waitlist: null,
                notifyingId: null,
                waitlistFlash: null,
                waitlistFlashType: 'success',
                waitlistFlashTimer: null,
                wlSearch: '',
                wlResults: [],
                wlSearching: false,
                wlSearchTimer: null,
                addingId: null,
            }
        },
        methods: {
            close() {
                this.$emit('close');
            },
            edit() {
                this.$emit('edit');
            },
            toggleShow() {
                this.show = false
                setTimeout(() => {
                    this.show = true
                }, 0)
            },
            update() {
                this.axios.get('bookings/' + this.bookingData.id).then((response) => {
                    this.bookingData = response.data.items[0]
                })
                this.updateWaitlist()
            },
            /**
             * Fetch waiting-list candidates for this booking's slot from the
             * Smart Waitlist add-on. Degrades silently (no card) when the add-on
             * is inactive/unlicensed and the route 404s.
             */
            updateWaitlist() {
                if (!this.bookingData || !this.bookingData.id) {
                    return
                }
                this.axios.get('waitlist/booking/' + this.bookingData.id).then((response) => {
                    this.waitlist = response.data && response.data.enabled ? response.data : null
                }).catch(() => {
                    this.waitlist = null
                })
            },
            /** Manually offer this freed slot to a waiting customer. */
            notifyCandidate(entryId) {
                if (this.notifyingId) {
                    return
                }
                this.notifyingId = entryId
                this.dismissFlash()
                this.axios.post('waitlist/booking/' + this.bookingData.id + '/notify', {entry_id: entryId})
                    .then((response) => {
                        if (response.data && response.data.enabled) {
                            this.waitlist = response.data
                        }
                        this.setFlash((response.data && response.data.message) || 'The customer has been notified.', 'success')
                    })
                    .catch((error) => {
                        const msg = error && error.response && error.response.data ? error.response.data.message : null
                        this.setFlash(msg || 'Could not notify the customer.', 'error')
                    })
                    .finally(() => {
                        this.notifyingId = null
                    })
            },
            /** Debounced customer autocomplete for the "add to waiting list" field. */
            onWlSearchInput() {
                clearTimeout(this.wlSearchTimer)
                if (this.wlSearch.trim().length < 2) {
                    this.wlResults = []
                    this.wlSearching = false
                    return
                }
                this.wlSearching = true
                this.wlSearchTimer = setTimeout(() => this.searchCustomers(), 300)
            },
            searchCustomers() {
                const q = this.wlSearch.trim()
                if (q.length < 2) {
                    this.wlSearching = false
                    return
                }
                this.axios.get('customers', {params: {search: q, search_type: 'contains', order_by: 'first_name_last_name'}})
                    .then((response) => {
                        this.wlResults = (response.data && response.data.items) || []
                    })
                    .catch(() => {
                        this.wlResults = []
                    })
                    .finally(() => {
                        this.wlSearching = false
                    })
            },
            clearWlSearch() {
                clearTimeout(this.wlSearchTimer)
                this.wlSearch = ''
                this.wlResults = []
                this.wlSearching = false
            },
            /** Add a searched customer to the waiting list for this booking's slot. */
            addCustomer(userId) {
                if (this.addingId) {
                    return
                }
                this.addingId = userId
                this.dismissFlash()
                this.axios.post('waitlist/booking/' + this.bookingData.id + '/add-customer', {user_id: userId})
                    .then((response) => {
                        if (response.data && response.data.enabled) {
                            this.waitlist = response.data
                        }
                        this.setFlash((response.data && response.data.message) || 'Customer added to the waiting list.', 'success')
                        this.clearWlSearch()
                    })
                    .catch((error) => {
                        const msg = error && error.response && error.response.data ? error.response.data.message : null
                        this.setFlash(msg || 'Could not add the customer to the waiting list.', 'error')
                    })
                    .finally(() => {
                        this.addingId = null
                    })
            },
            /** Show a waiting-list feedback message that auto-dismisses after a few seconds. */
            setFlash(message, type) {
                this.waitlistFlash = message
                this.waitlistFlashType = type || 'success'
                clearTimeout(this.waitlistFlashTimer)
                this.waitlistFlashTimer = setTimeout(() => {
                    this.waitlistFlash = null
                }, 5000)
            },
            dismissFlash() {
                clearTimeout(this.waitlistFlashTimer)
                this.waitlistFlash = null
            },
            resultInitials(u) {
                const f = (u.first_name || '')[0] || ''
                const l = (u.last_name || '')[0] || ''
                return (f + l).toUpperCase() || '?'
            },
            matchLabel(match) {
                if (match === 'manual') return 'Manual'
                if (match === 'exact') return 'Exact'
                return 'Same day'
            },
            channelsLabel(channels) {
                return (channels || []).map(c => c === 'sms' ? 'SMS' : (c.charAt(0).toUpperCase() + c.slice(1))).join(', ')
            },
            requestedLabel(requested) {
                if (!requested) {
                    return 'Any'
                }
                return String(requested).split(' ').map((part) => {
                    if (/^\d{4}-\d{2}-\d{2}$/.test(part)) {
                        return this.dateFormat(part)
                    }
                    if (/^\d{1,2}:\d{2}/.test(part)) {
                        return this.timeFormat(part)
                    }
                    return part
                }).join(' ')
            },
            showCustomerImages() {
                this.$emit('showCustomerImages', {id: this.bookingData.customer_id, photos: this.photos})
            },
            serviceDurationMinutes(service) {
                if (!service.start_at || !service.end_at) return null;
                const [sh, sm] = service.start_at.split(':').map(Number);
                const [eh, em] = service.end_at.split(':').map(Number);
                const minutes = (eh * 60 + em) - (sh * 60 + sm);
                return minutes > 0 ? minutes : null;
            },
            formatDuration(value) {
                let totalMinutes;
                if (typeof value === 'string' && value.includes(':')) {
                    const [h, m] = value.split(':').map(Number);
                    totalMinutes = h * 60 + m;
                } else {
                    totalMinutes = Number(value);
                }
                if (!totalMinutes) return null;
                const h = Math.floor(totalMinutes / 60);
                const m = totalMinutes % 60;
                if (h > 0 && m > 0) return `${h}h ${m}min`;
                if (h > 0) return `${h}h`;
                return `${m}min`;
            },
            viewCustomerProfile() {
                if (!this.hasCustomerProfile) return;
                this.$emit('viewCustomerProfile', {
                    id: this.bookingData.customer_id,
                    first_name: this.bookingData.customer_first_name,
                    last_name: this.bookingData.customer_last_name,
                    email: this.bookingData.customer_email,
                    phone: this.bookingData.customer_phone_country_code
                        ? this.bookingData.customer_phone_country_code + this.bookingData.customer_phone
                        : this.bookingData.customer_phone,
                    address: this.bookingData.customer_address,
                    note: this.bookingData.customer_personal_note,
                });
            },
        },
        emits: ['close', 'edit', 'showCustomerImages', 'viewCustomerProfile']
    }
</script>

<style scoped>
.booking-detail-screen {
  min-height: 100vh;
  background: var(--color-background, #F4F6FA);
  padding-bottom: 100px;
}
.detail-header {
  display: flex;
  align-items: center;
  padding: 12px var(--spacing-page, 16px);
  position: sticky;
  top: 0;
  z-index: 10;
}
.back-btn {
  background: none;
  border: none;
  padding: 6px 8px;
  color: var(--color-text-primary, #0F172A);
  font-size: 18px;
  cursor: pointer;
  min-width: 40px;
  min-height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  transition: background 0.15s;
}
.back-btn:hover { background: rgba(0,0,0,0.06); }
.header-btn {
  background: none;
  border: none;
  padding: 6px 8px;
  color: var(--color-primary, #2563EB);
  font-size: 18px;
  cursor: pointer;
  min-width: 64px;
  text-align: right;
}
.detail-title {
  flex: 1;
  text-align: center;
  font-size: 17px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  margin: 0;
}
.detail-card {
  background: var(--color-surface, #fff);
  border-radius: var(--radius-md, 12px);
  margin: 12px var(--spacing-page, 16px) 0;
  padding: var(--spacing-card, 14px);
}
.section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-muted, #94A3B8);
  margin-bottom: 10px;
}
.detail-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 9px 0;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
}
.detail-row--last { border-bottom: none; }
.detail-icon { margin-right: 6px; color: var(--color-text-muted, #94A3B8); }
.detail-label {
  font-size: 14px;
  color: var(--color-text-secondary, #64748B);
}
.detail-value {
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-primary, #0F172A);
}
.detail-value--primary {
  font-weight: 700;
  color: var(--color-primary, #2563EB);
}
.status-pill {
  font-size: 12px;
  font-weight: 600;
  padding: 4px 12px;
  border-radius: var(--radius-pill, 999px);
  background: rgba(37,99,235,0.1);
  color: var(--color-primary, #2563EB);
}
.customer-row {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 10px;
  border-radius: var(--radius-sm, 8px);
  transition: background 0.12s;
}
.customer-row--tappable {
  cursor: pointer;
  margin: -6px -6px 4px;
  padding: 6px 6px;
  background: none;
  border: none;
  width: calc(100% + 12px);
  text-align: left;
  font: inherit;
}
.customer-row--tappable:active {
  background: var(--color-background, #F4F6FA);
}
.customer-row-right {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}
.customer-chevron {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
}
.customer-avatar-sm {
  width: 44px;
  height: 44px;
  border-radius: 50%;
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: 700;
  flex-shrink: 0;
}
.customer-info-block { flex: 1; }
.customer-full-name {
  font-size: 15px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
}
.customer-contact-line {
  font-size: 13px;
  color: var(--color-text-secondary, #64748B);
  margin-top: 2px;
}
.photo-thumb {
  width: 44px;
  height: 44px;
  border-radius: var(--radius-sm, 8px);
  overflow: hidden;
  cursor: pointer;
  flex-shrink: 0;
  background: var(--color-background, #F4F6FA);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: var(--color-text-muted, #94A3B8);
}
.photo-thumb img { width: 100%; height: 100%; object-fit: cover; }
.contact-actions { display: flex; gap: 8px; margin-bottom: 10px; }
.contact-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  font-size: 15px;
  text-decoration: none;
}
.note-block {
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
}
.note-block:last-child { border-bottom: none; }
.note-block-label {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
  margin-bottom: 4px;
  display: block;
}
.note-block-text {
  font-size: 14px;
  color: var(--color-text-primary, #0F172A);
  margin: 0;
  line-height: 1.5;
  white-space: pre-wrap;
}
.service-row-item {
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
}
.service-row-item:last-child { border-bottom: none; }
.service-name-price {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 8px;
}
.service-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  flex: 1;
}
.service-price-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
  flex-shrink: 0;
}
.service-duration {
  font-size: 11px;
  font-weight: 400;
  color: var(--color-text-muted, #94A3B8);
  line-height: 1.2;
}
.service-price {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-primary, #2563EB);
  line-height: 1.2;
}
.service-meta {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
  margin-top: 3px;
}
.total-value-block {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 1px;
}
.total-duration {
  font-size: 11px;
  font-weight: 400;
  color: var(--color-text-muted, #94A3B8);
  line-height: 1.2;
}
.pay-remaining-wrap { padding-top: 4px; }
.collapsible-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}
.collapsible-icon { color: var(--color-text-muted, #94A3B8); font-size: 14px; }
.extra-field {
  display: flex;
  flex-direction: column;
  padding: 8px 0;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
}
.extra-field:last-child { border-bottom: none; }
.extra-field-label {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
  margin-bottom: 2px;
}
.extra-field-value {
  font-size: 14px;
  color: var(--color-text-primary, #0F172A);
}
.wl-slot {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  margin: 0 0 4px;
}
.wl-note {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
  margin: 0 0 10px;
  line-height: 1.4;
}
.wl-note--ok { color: #166534; }
.wl-flash {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  font-size: 13px;
  line-height: 1.4;
  border-radius: var(--radius-sm, 8px);
  padding: 10px 10px 10px 12px;
  margin-bottom: 10px;
}
.wl-flash-icon { font-size: 15px; margin-top: 1px; flex-shrink: 0; }
.wl-flash-text { flex: 1; min-width: 0; }
.wl-flash-close {
  border: none;
  background: none;
  cursor: pointer;
  padding: 0 2px;
  font-size: 13px;
  color: inherit;
  opacity: 0.55;
  flex-shrink: 0;
}
.wl-flash-close:hover { opacity: 1; }
.wl-flash--success {
  color: #166534;
  background: #dcfce7;
}
.wl-flash--error {
  color: #b42318;
  background: #fee4e2;
}
.wl-flash-fade-enter-active,
.wl-flash-fade-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}
.wl-flash-fade-enter-from,
.wl-flash-fade-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
.wl-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
}
.wl-row:last-child { border-bottom: none; }
.wl-row-main { flex: 1; min-width: 0; }
.wl-name {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
}
.wl-name-text {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wl-badge {
  display: inline-block;
  padding: 2px 8px;
  border-radius: var(--radius-pill, 999px);
  font-size: 10px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.03em;
  flex-shrink: 0;
}
.wl-badge--exact { background: #dcfce7; color: #166534; }
.wl-badge--near { background: #f1f5f9; color: #64748B; }
.wl-badge--manual { background: #dbeafe; color: #1e40af; }
.wl-meta {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
  margin-top: 3px;
}
.wl-dot { color: var(--color-text-muted, #94A3B8); }
.wl-score { margin-left: 6px; color: var(--color-text-muted, #94A3B8); }
.wl-notify-btn {
  flex-shrink: 0;
  border: none;
  border-radius: var(--radius-pill, 999px);
  background: var(--color-primary, #2563EB);
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  padding: 8px 16px;
  min-height: 36px;
  cursor: pointer;
}
.wl-notify-btn:disabled { opacity: 0.6; cursor: default; }
.wl-notify-btn--secondary {
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
}
.wl-more {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  margin: 10px 0 0;
}
.wl-empty {
  font-size: 13px;
  font-style: italic;
  color: var(--color-text-muted, #94A3B8);
  margin: 4px 0 0;
}
.wl-add { margin: 10px 0 4px; }
.wl-add-field {
  display: flex;
  align-items: center;
  gap: 8px;
  background: var(--color-background, #F4F6FA);
  border: 1px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  padding: 8px 10px;
}
.wl-add-icon { color: var(--color-text-muted, #94A3B8); font-size: 13px; }
.wl-add-input {
  flex: 1;
  border: none;
  background: transparent;
  outline: none;
  font-size: 14px;
  color: var(--color-text-primary, #0F172A);
  min-width: 0;
}
.wl-add-clear {
  border: none;
  background: none;
  color: var(--color-text-muted, #94A3B8);
  cursor: pointer;
  padding: 2px 4px;
  font-size: 13px;
}
.wl-results {
  margin-top: 6px;
  border: 1px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  overflow: hidden;
  max-height: 260px;
  overflow-y: auto;
}
.wl-result {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  border: none;
  background: var(--color-surface, #fff);
  border-bottom: 1px solid var(--color-border, #E2E8F0);
  padding: 8px 10px;
  cursor: pointer;
  text-align: left;
}
.wl-result:last-child { border-bottom: none; }
.wl-result:active { background: var(--color-background, #F4F6FA); }
.wl-result:disabled { opacity: 0.6; cursor: default; }
.wl-result-avatar {
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  flex-shrink: 0;
}
.wl-result-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.wl-result-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wl-result-sub {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.wl-result-icon { color: var(--color-primary, #2563EB); font-size: 13px; flex-shrink: 0; }
.wl-add-hint {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  margin: 8px 0 0;
}
</style>
