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
          <span class="service-price" v-html="service.service_price + booking.currency"></span>
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
        <span class="detail-value detail-value--primary" v-html="totalSum"></span>
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
                bookingData: this.booking
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
            },
            showCustomerImages() {
                this.$emit('showCustomerImages', {id: this.bookingData.customer_id, photos: this.photos})
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
}
.service-name {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
}
.service-price {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-primary, #2563EB);
}
.service-meta {
  font-size: 12px;
  color: var(--color-text-secondary, #64748B);
  margin-top: 3px;
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
</style>
