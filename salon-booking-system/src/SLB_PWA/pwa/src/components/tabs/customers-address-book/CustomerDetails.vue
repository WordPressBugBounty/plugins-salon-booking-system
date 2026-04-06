<template>
  <div class="customer-details-screen">

    <!-- Header -->
    <div class="detail-header">
      <button class="back-btn" type="button" @click="close">
        <font-awesome-icon icon="fa-solid fa-arrow-left" />
      </button>
      <div class="header-center">
        <div class="customer-photo-section">
          <button class="customer-thumb" type="button" @click="takePicture">
            <img v-if="customerPhoto" :src="customerPhoto" alt="Customer" class="customer-thumb-img" />
            <svg v-else class="customer-thumb-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
              <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
            </svg>
          </button>
          <span class="take-picture-text" @click="takePicture">Take a picture</span>
        </div>
        <h1 class="detail-title">Update {{ customerFirstname }} {{ customerLastname }}</h1>
      </div>
      <span class="back-btn-placeholder"></span>
    </div>
    
    <!-- Hidden file input for camera -->
    <input 
      ref="cameraInput" 
      type="file" 
      accept="image/*" 
      capture="environment"
      style="display: none"
      @change="handlePhotoCapture"
    />

    <!-- Stats section: skeleton while loading -->
    <div class="stats-card stats-skeleton" v-if="isLoadingStats">
      <div class="stats-row">
        <div class="stat-item" v-for="i in 4" :key="i">
          <span class="skeleton-line skeleton-value"></span>
          <span class="skeleton-line skeleton-label"></span>
        </div>
      </div>
    </div>

    <!-- Stats section: data once loaded -->
    <div class="stats-card" v-else-if="stats.bookingsCount !== null">
      <div class="stats-row">
        <div class="stat-item">
          <span class="stat-value" v-html="statFormatAmount(stats.totalSpent)"></span>
          <span class="stat-label">Total Spent</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-value">{{ stats.bookingsCount }}</span>
          <span class="stat-label">Bookings</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item">
          <span class="stat-value">{{ statFormatLastVisit(stats.lastVisit) }}</span>
          <span class="stat-label">Last Visit</span>
        </div>
        <div class="stat-divider"></div>
        <div class="stat-item stat-item--score">
          <template v-if="$root.settings.fidelity_score_enabled">
            <span class="stat-value stat-value--score stat-value--score-row" :style="{ color: scoreColor(stats.score) }">
              <span class="stat-value--score-num">{{ stats.score !== null ? stats.score : '—' }}</span>
              <font-awesome-icon icon="fa-solid fa-medal" class="stat-score-medal" aria-hidden="true" />
            </span>
            <span class="stat-label">Score</span>
          </template>
          <template v-else>
            <span class="stat-value stat-value--score-row">
              <span>0</span>
              <font-awesome-icon icon="fa-solid fa-medal" class="stat-score-medal stat-score-medal--inactive" aria-hidden="true" />
            </span>
            <span class="stat-label stat-label--inactive-score">Score not active</span>
          </template>
        </div>
      </div>
    </div>
    <p
      v-if="!isLoadingStats && stats.bookingsCount !== null"
      class="stats-admin-disclaimer"
    >
      these data are visible to salon administrator only
    </p>

    <!-- Recent Bookings: skeleton while loading -->
    <div class="form-card recent-bookings-card" v-if="isLoadingStats">
      <p class="form-section-label">Recent Bookings</p>
      <div class="booking-row-skeleton" v-for="i in 3" :key="i">
        <div>
          <span class="skeleton-line" style="width: 140px; height: 14px;"></span>
          <span class="skeleton-line" style="width: 90px; height: 11px; margin-top: 5px;"></span>
        </div>
        <span class="skeleton-line" style="width: 72px; height: 24px; border-radius: 999px;"></span>
      </div>
    </div>

    <!-- Recent Bookings: data once loaded -->
    <div class="form-card recent-bookings-card" v-else-if="stats.recentBookings.length > 0">
      <p class="form-section-label">Recent Bookings</p>
      <div
        class="recent-booking-row"
        v-for="(booking, idx) in stats.recentBookings"
        :key="booking.id"
        :class="{ 'recent-booking-row--last': idx === stats.recentBookings.length - 1 && !hasMoreBookings }"
        @click="openBookingDetails(booking)"
      >
        <div class="recent-booking-info">
          <span class="recent-booking-services">{{ statServiceNames(booking) }}</span>
          <span class="recent-booking-date">{{ dateFormat(booking.date) }} &bull; {{ booking.time }}</span>
          
          <!-- Rating -->
          <div v-if="booking.rating" class="recent-booking-rating">
            <span class="rating-stars">
              <span v-for="star in 5" :key="star" class="star" :class="{ 'star-filled': star <= booking.rating }">★</span>
            </span>
          </div>
          
          <!-- Feedback/Comment -->
          <div v-if="booking.feedback" class="recent-booking-feedback">
            <span class="feedback-text">{{ booking.feedback }}</span>
          </div>
        </div>
        <span
          class="recent-booking-status"
          :style="{ color: statStatusColor(booking.status), backgroundColor: statStatusColor(booking.status) + '18' }"
        >{{ statStatusLabel(booking.status) }}</span>
      </div>
      
      <!-- Load More Button -->
      <button 
        v-if="hasMoreBookings" 
        class="load-more-btn"
        @click="loadMoreBookings"
        :disabled="isLoadingMoreBookings"
      >
        <b-spinner small v-if="isLoadingMoreBookings" />
        <span v-else>Load More ({{ allBookings.length - stats.recentBookings.length }} more)</span>
      </button>
      
      <!-- Pagination Info -->
      <div v-if="!hasMoreBookings && allBookings.length > bookingsPerPage" class="pagination-info">
        Showing all {{ stats.recentBookings.length }} bookings
      </div>
    </div>

    <!-- Fields card -->
    <div class="form-card">
      <p class="form-section-label">Customer Info</p>
      <div class="form-field" :class="{'field-error': requiredFields.indexOf('customer_first_name') > -1}">
        <label class="field-label">{{ getLabel('customerFirstnamePlaceholder') }}</label>
        <b-form-input :placeholder="getLabel('customerFirstnamePlaceholder')" v-model="elCustomerFirstname" />
      </div>
      <div class="form-field">
        <label class="field-label">{{ getLabel('customerLastnamePlaceholder') }}</label>
        <b-form-input :placeholder="getLabel('customerLastnamePlaceholder')" v-model="elCustomerLastname" />
      </div>
      <div class="form-field">
        <label class="field-label">{{ getLabel('customerEmailPlaceholder') }}</label>
        <b-form-input
            :type="shouldHideEmail ? 'password' : 'text'"
            :placeholder="getLabel('customerEmailPlaceholder')"
            v-model="elCustomerEmail"
        />
      </div>
      <div class="form-field">
        <label class="field-label">{{ getLabel('customerAddressPlaceholder') }}</label>
        <b-form-input :placeholder="getLabel('customerAddressPlaceholder')" v-model="elCustomerAddress" />
      </div>
      <div class="form-field">
        <label class="field-label">{{ getLabel('customerPhonePlaceholder') }}</label>
        <b-form-input
            :type="shouldHidePhone ? 'password' : 'tel'"
            :placeholder="getLabel('customerPhonePlaceholder')"
            v-model="elCustomerPhone"
        />
      </div>
    </div>

    <!-- Extra info -->
    <div class="form-card">
      <div class="collapsible-header" @click="visibleExtraInfo = !visibleExtraInfo">
        <p class="form-section-label mb-0">{{ getLabel('extraInfoLabel') }}</p>
        <font-awesome-icon :icon="visibleExtraInfo ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down'" class="collapsible-icon" />
      </div>
      <b-collapse v-model="visibleExtraInfo">
        <div class="mt-2">
          <template v-for="field in customFieldsList" :key="field.key">
            <CustomField :field="field" :value="getCustomFieldValue(field.key, field.default_value)" @update="updateCustomField" />
          </template>
          <div class="form-field mt-2">
            <label class="field-label" for="customer_personal_notes">{{ getLabel('customerPersonalNotesLabel') }}</label>
            <b-form-textarea
                v-model.lazy="elCustomerPersonalNotes"
                id="customer_personal_notes"
                :placeholder="getLabel('customerPersonalNotesPlaceholder')"
                rows="2"
                max-rows="4"
            />
          </div>
        </div>
      </b-collapse>
    </div>

    <!-- Actions -->
    <div class="form-card actions-card">
      <button class="save-btn" type="button" @click="save" :disabled="isLoading">
        <b-spinner small v-if="isLoading" />
        {{ getLabel('customerDetailsUpdateButtonLabel') }}
      </button>
      <b-alert :show="isSaved" fade variant="success" class="mt-2">{{ getLabel('savedLabel') }}</b-alert>
      <b-alert :show="isError" fade variant="danger" class="mt-2">{{ errorMessage }}</b-alert>
      <b-alert :show="!isValid" fade variant="danger" class="mt-2">{{ getLabel('validationMessage') }}</b-alert>
    </div>

  </div>
</template>

<script>
import CustomField from "@/components/tabs/upcoming-reservations/CustomField.vue";
import mixins from "@/mixin";

export default {
    name: 'CustomerDetails',
    components: {CustomField},
    mixins: [mixins],
    props: {
        customerID: {
            default: function () {
                return '';
            },
        },
        customerFirstname: {
            default: function () {
                return '';
            },
        },
        customerLastname: {
            default: function () {
                return '';
            },
        },
        customerEmail: {
            default: function () {
                return '';
            },
        },
        customerAddress: {
            default: function () {
                return '';
            },
        },
        customerPhone: {
            default: function () {
                return '';
            },
        },
        customerPersonalNotes: {
            default: function () {
                return '';
            },
        },
    },
    computed: {
        hasMoreBookings() {
            return this.allBookings.length > this.stats.recentBookings.length
        },
        totalBookingsPages() {
            return Math.ceil(this.allBookings.length / this.bookingsPerPage)
        },
    },
    mounted() {
        console.log('🔵 CustomerDetails mounted, customerID:', this.customerID)
        this.loadCustomFields()
        this.loadStats()
        this.loadCustomerPhoto()
    },
        data: function () {
            return {
                elCustomerFirstname: this.customerFirstname,
                elCustomerLastname: this.customerLastname,
                elCustomerAddress: this.customerAddress,
                originalEmail: this.customerEmail,
                originalPhone: this.customerPhone,
                elCustomerEmail: this.shouldHideEmail ? '***@***' : this.customerEmail,
                elCustomerPhone: this.shouldHidePhone ? '*******' : this.customerPhone,
                elCustomerPersonalNotes: this.customerPersonalNotes,
                isValid: true,
                requiredFields: [],
                visibleExtraInfo: false,
                customFieldsList: [],
                elCustomFields: [],
                vueTelInputOptions: {
                    'placeholder': this.getLabel('customerPhonePlaceholder')
                },
                isLoading: false,
                isSaved: false,
                isError: false,
                errorMessage: '',
                isLoadingStats: true,
                stats: {
                    totalSpent: null,
                    bookingsCount: null,
                    lastVisit: null,
                    score: null,
                    recentBookings: [],
                },
                // Pagination
                bookingsPage: 1,
                bookingsPerPage: 5,
                allBookings: [],
                isLoadingMoreBookings: false,
                customerPhoto: null,
                isUploadingPhoto: false,
            };
        },
    methods: {
        close() {
            this.$emit('close');
        },
        save() {
            this.isValid = this.validate()
            if (!this.isValid) {
                return;
            }
            var customer = {
                id: this.customerID ? this.customerID : 0,
                first_name: this.elCustomerFirstname,
                last_name: this.elCustomerLastname,
                email: this.originalEmail,
                phone: this.originalPhone,
                address: this.elCustomerAddress,
                note: this.elCustomerPersonalNotes,
                custom_fields: this.customFieldsList,
            }

            this.isLoading = true

            this.axios.put('customers/' + customer.id, customer).then(() => {
                this.isSaved = true
                setTimeout(() => {
                    this.isSaved = false
                }, 3000)
            }, (e) => {
                this.isError = true
                this.errorMessage = e.response.data.message
                setTimeout(() => {
                    this.isError = false
                    this.errorMessage = ''
                }, 3000)
            }).finally(() => {
                this.isLoading = false
            })
        },
        validate() {
            this.requiredFields = []
            if (!this.elCustomerFirstname.trim()) {
                this.requiredFields.push('customer_first_name')
            }
            return this.requiredFields.length === 0
        },
        updateCustomField(key, value) {
            let field = this.customFieldsList.find(i => i.key === key)
            if (field) {
                field.value = value
            } else {
                this.customFieldsList.push({key: key, value: value})
            }
        },
        getCustomFieldValue(key, default_value) {
            let field = this.customFieldsList.find(i => i.key === key)
            if (field) {
                return field.value
            }
            return default_value
        },
        loadCustomFields() {
            this.axios.get('custom-fields/booking', {params: {user_profile: 1, customer_id: this.customerID}}).then((response) => {
                this.customFieldsList = response.data.items.filter(i => ['html', 'file'].indexOf(i.type) === -1)
            })
        },
        loadStats() {
            console.log('🔵 loadStats called, customerID:', this.customerID)
            if (!this.customerID) {
                console.log('🔴 No customerID, skipping stats load')
                this.isLoadingStats = false
                return
            }
            console.log('🔵 Loading stats for customer:', this.customerID)
            this.isLoadingStats = true
            
            // Simply fetch the customer data which includes bookings
            // This matches exactly what CustomersAddressBook does
            Promise.all([
                this.axios.get('customers/' + this.customerID).catch(() => null),
            ]).then(([customerRes]) => {
                const customerData = customerRes ? (customerRes.data.items || [])[0] || {} : {}
                console.log('🔵 Customer data:', customerData)
                console.log('🔵 Customer bookings:', customerData.bookings)
                
                const bookingsCount = customerData.bookings?.length || 0
                console.log('🔵 Bookings count:', bookingsCount)
                
                // If we have booking IDs, fetch the actual booking details
                if (customerData.bookings && customerData.bookings.length > 0) {
                    const bookingIds = customerData.bookings.slice(0, 100) // Limit to 100
                    
                    // Fetch each booking's details
                    const bookingPromises = bookingIds.map(id => 
                        this.axios.get('bookings/' + id).catch(() => null)
                    )
                    
                    Promise.all(bookingPromises).then(responses => {
                        const bookings = responses
                            .filter(res => res && res.data && res.data.items)
                            .map(res => res.data.items[0])
                            .filter(b => b)
                        
                        console.log('🔵 Fetched bookings:', bookings.length)
                        
                        // Store all bookings for pagination
                        this.allBookings = bookings
                        this.bookingsPage = 1
                        
                        this.stats = {
                            totalSpent: bookings.reduce((sum, b) => sum + (parseFloat(b.amount) || 0), 0),
                            bookingsCount: bookings.length,
                            lastVisit: bookings.length > 0 ? bookings[0].date : null,
                            score: customerData.score ?? null,
                            recentBookings: bookings.slice(0, this.bookingsPerPage),
                        }
                        
                        console.log('🔵 Stats set, recentBookings length:', this.stats.recentBookings.length)
                        this.isLoadingStats = false
                    }).catch(err => {
                        console.error('🔴 Error fetching booking details:', err)
                        this.isLoadingStats = false
                    })
                } else {
                    // No bookings
                    this.allBookings = []
                    this.stats = {
                        totalSpent: 0,
                        bookingsCount: 0,
                        lastVisit: null,
                        score: customerData.score ?? null,
                        recentBookings: [],
                    }
                    console.log('🔵 No bookings found')
                    this.isLoadingStats = false
                }
            }).catch(err => {
                console.error('🔴 Error in loadStats:', err)
                this.isLoadingStats = false
            })
        },
        statStatusLabel(status) {
            if (this.$root.statusesList && this.$root.statusesList[status]) {
                return this.$root.statusesList[status].label
            }
            return status
        },
        statStatusColor(status) {
            const map = {
                'sln-b-confirmed': '#16A34A',
                'sln-b-paid': '#16A34A',
                'sln-b-pending': '#D97706',
                'sln-b-pendingpayment': '#D97706',
                'sln-b-paylater': '#0891B2',
                'sln-b-canceled': '#DC2626',
            }
            return map[status] || '#64748B'
        },
        statFormatLastVisit(date) {
            if (!date) return '—'
            return this.moment(date).format('MMM D')
        },
        statFormatAmount(amount) {
            const num = parseFloat(amount)
            if (isNaN(num)) return '—'
            const symbol = (this.$root.settings && this.$root.settings.currency_symbol) || ''
            return symbol + num.toFixed(2)
        },
        scoreColor(score) {
            if (score === null || score === undefined) return 'var(--color-text-muted, #94A3B8)'
            if (score >= 8) return '#16A34A'
            if (score >= 5) return '#D97706'
            return '#DC2626'
        },
        statServiceNames(booking) {
            const services = booking.services || []
            if (services.length === 0) return '—'
            return services.map(s => s.service_name).filter(Boolean).join(', ')
        },
        openBookingDetails(booking) {
            console.log('🔵 openBookingDetails called with booking:', booking)

            // First, emit the showBooking event for CustomersAddressBookTab
            this.$emit('showBooking', booking)

            // Then try to call parent method directly for other tabs (Upcoming, Calendar)
            let parent = this.$parent
            while (parent) {
                if (parent.showBookingFromCustomer && typeof parent.showBookingFromCustomer === 'function') {
                    console.log('🔵 Found parent with showBookingFromCustomer method')
                    parent.showBookingFromCustomer(booking)
                    return
                }
                parent = parent.$parent
            }

            console.log('🔵 Event emitted to parent')
        },
        loadMoreBookings() {
            this.isLoadingMoreBookings = true

            // Simulate a small delay for smooth UX
            setTimeout(() => {
                this.bookingsPage++
                const endIndex = this.bookingsPage * this.bookingsPerPage
                this.stats.recentBookings = this.allBookings.slice(0, endIndex)
                this.isLoadingMoreBookings = false
            }, 300)
        },
        takePicture() {
            this.$refs.cameraInput.click()
        },
        async handlePhotoCapture(event) {
            const file = event.target.files[0]
            if (!file) return
            
            this.isUploadingPhoto = true
            
            try {
                // Create FormData for file upload
                const formData = new FormData()
                formData.append('file', file) // Changed from 'photo' to 'file'
                formData.append('default', '1')
                
                // Upload the photo
                const response = await this.axios.post(
                    `customers/${this.customerID}/photos`,
                    formData,
                    {
                        headers: {
                            'Content-Type': 'multipart/form-data'
                        }
                    }
                )
                
                console.log('🔵 Photo upload response:', response)
                
                // Update the customer photo display
                if (response.data && response.data.items && response.data.items[0]) {
                    const customer = response.data.items[0]
                    const photos = customer.photos || []
                    const defaultPhoto = photos.find(p => p.default) || photos[0]
                    
                    if (defaultPhoto) {
                        this.customerPhoto = defaultPhoto.url
                    }
                }
                
                // Reset the input
                event.target.value = ''
            } catch (error) {
                console.error('🔴 Error uploading photo:', error)
                console.error('🔴 Error response:', error.response)
                // Don't show alert, just log the error
            } finally {
                this.isUploadingPhoto = false
            }
        },
        async loadCustomerPhoto() {
            try {
                const response = await this.axios.get(`customers/${this.customerID}`)
                if (response.data && response.data.items && response.data.items[0]) {
                    const customer = response.data.items[0]
                    const photos = customer.photos || []
                    const defaultPhoto = photos.find(p => p.default) || photos[0]
                    
                    if (defaultPhoto) {
                        this.customerPhoto = defaultPhoto.url
                    }
                }
            } catch (error) {
                console.error('Error loading customer photo:', error)
            }
        },
    },
    emits: ['close', 'save', 'showBooking'],
}
</script>

<style scoped>
.customer-details-screen {
  min-height: 100vh;
  background: var(--color-background, #F4F6FA);
  padding-bottom: 100px;
}

/* ── Header ── */
.detail-header {
  display: flex;
  align-items: center;
  padding: 14px var(--spacing-page, 16px);
  padding-top: max(14px, env(safe-area-inset-top, 0px));
  position: sticky;
  top: 0;
  z-index: 10;
  background: var(--color-surface, #fff);
  border-bottom: 1px solid var(--color-border, #E2E8F0);
  box-shadow: 0 1px 0 rgba(15, 23, 42, 0.04);
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
.back-btn-placeholder { min-width: 40px; }
.detail-title {
  flex: 1;
  text-align: center;
  font-size: 17px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  margin: 0;
}

/* ── Stats Card ── */
.stats-card {
  background: var(--color-surface, #fff);
  border-radius: var(--radius-lg, 16px);
  margin: 12px var(--spacing-page, 16px) 0;
  padding: 20px var(--spacing-card, 14px);
  border: 1px solid var(--color-border, #E2E8F0);
}
.stats-row {
  display: flex;
  align-items: center;
  justify-content: space-around;
}
.stat-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  flex: 1;
}
.stat-value {
  font-size: calc(22px * 0.85);
  font-weight: 700;
  color: var(--color-primary, #2563EB);
  line-height: 1.1;
  letter-spacing: -0.02em;
}
.stat-value--score-row {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
}
.stat-score-medal {
  font-size: calc(16px * 0.85);
  flex-shrink: 0;
  opacity: 0.92;
}
.stat-score-medal--inactive {
  color: var(--color-primary, #2563EB);
  opacity: 0.35;
}
.stat-label {
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--color-text-muted, #94A3B8);
}
.stat-item--score {
  min-width: 0;
}
.stat-label--inactive-score {
  text-transform: none;
  letter-spacing: 0.02em;
  font-weight: 600;
  font-size: 9px;
  line-height: 1.25;
  text-align: center;
  max-width: 88px;
}
.stat-divider {
  width: 1px;
  height: calc(36px * 0.85);
  background: var(--color-border, #E2E8F0);
  flex-shrink: 0;
}

.stats-admin-disclaimer {
  margin: 8px var(--spacing-page, 16px) 0;
  padding: 0 var(--spacing-card, 14px);
  font-size: 11px;
  line-height: 1.35;
  font-weight: 500;
  text-align: center;
  color: var(--color-text-muted, #94A3B8);
}

/* ── Recent Bookings ── */
.recent-bookings-card { padding-bottom: 4px; }
.recent-booking-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
  cursor: pointer;
  transition: background-color 0.15s ease;
  margin: 0 -14px;
  padding-left: 14px;
  padding-right: 14px;
  border-radius: 8px;
}
.recent-booking-row--last { border-bottom: none; }
.recent-booking-row:hover {
  background-color: #F8FAFC;
}
.recent-booking-info {
  display: flex;
  flex-direction: column;
  gap: 3px;
  min-width: 0;
}
.recent-booking-services {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-text-primary, #0F172A);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.recent-booking-date {
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
}
.recent-booking-status {
  font-size: 11px;
  font-weight: 600;
  padding: 4px 10px;
  border-radius: 999px;
  white-space: nowrap;
  flex-shrink: 0;
}

/* ── Rating & Feedback ── */
.recent-booking-rating {
  margin-top: 6px;
}
.rating-stars {
  display: inline-flex;
  gap: 2px;
}
.star {
  font-size: 14px;
  color: #E2E8F0;
}
.star-filled {
  color: #FFA500;
}
.recent-booking-feedback {
  margin-top: 6px;
  padding: 8px 12px;
  background: #F8FAFC;
  border-radius: 8px;
  border-left: 3px solid var(--color-primary, #2563EB);
}
.feedback-text {
  font-size: 13px;
  color: var(--color-text-secondary, #64748B);
  line-height: 1.5;
  font-style: italic;
}

/* ── Load More Button ── */
.load-more-btn {
  width: 100%;
  padding: 12px 20px;
  margin-top: 12px;
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  border: 2px solid var(--color-primary, #2563EB);
  border-radius: var(--radius-pill, 999px);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}
.load-more-btn:hover {
  background: var(--color-primary, #2563EB);
  color: #fff;
}
.load-more-btn:active {
  transform: scale(0.98);
}
.load-more-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

/* ── Pagination Info ── */
.pagination-info {
  text-align: center;
  padding: 12px;
  margin-top: 8px;
  font-size: 12px;
  color: var(--color-text-muted, #94A3B8);
  font-weight: 500;
}

/* ── Skeleton loader ── */
.skeleton-line {
  display: block;
  background: linear-gradient(90deg, #E2E8F0 25%, #F1F5F9 50%, #E2E8F0 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.4s infinite;
  border-radius: 6px;
}
.skeleton-value { width: 64px; height: calc(22px * 0.85); }
.skeleton-label { width: 48px; height: 11px; margin-top: 6px; }
.booking-row-skeleton {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid var(--color-border, #E2E8F0);
}
.booking-row-skeleton:last-child { border-bottom: none; }
@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

/* ── Form Cards ── */
.form-card {
  background: var(--color-surface, #fff);
  border-radius: var(--radius-lg, 16px);
  margin: 12px var(--spacing-page, 16px) 0;
  padding: var(--spacing-card, 14px) var(--spacing-card, 14px);
  border: 1px solid var(--color-border, #E2E8F0);
}
.form-section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.07em;
  color: var(--color-text-muted, #94A3B8);
  margin-bottom: 14px;
}
.form-field {
  margin-bottom: 12px;
}
.form-field:last-child { margin-bottom: 0; }

/* ── Labels ── */
.field-label {
  font-size: 12px;
  font-weight: 600;
  color: var(--color-text-secondary, #64748B);
  margin-bottom: 5px;
  display: block;
}

/* ── Input overrides ── */
.form-field :deep(.form-control) {
  border: 1.5px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-sm, 8px);
  padding: 10px 12px;
  font-size: 15px;
  color: var(--color-text-primary, #0F172A);
  background: var(--color-surface, #fff);
  transition: border-color 0.15s, box-shadow 0.15s;
  line-height: 1.5;
}
.form-field :deep(.form-control::placeholder) {
  color: var(--color-text-muted, #94A3B8);
  font-size: 14px;
}
.form-field :deep(.form-control:focus) {
  border-color: var(--color-primary, #2563EB);
  box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
  outline: none;
}
.field-error :deep(.form-control) {
  border-color: var(--color-error, #DC2626) !important;
}

/* ── Collapsible Extra Info ── */
.collapsible-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
  padding: 2px 0;
}
.collapsible-icon { color: var(--color-text-muted, #94A3B8); font-size: 13px; }

/* ── Actions ── */
.actions-card {
  display: flex;
  flex-direction: column;
  background: transparent !important;
  border: none !important;
  padding-left: 0 !important;
  padding-right: 0 !important;
}
.save-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: var(--radius-pill, 999px);
  border: none;
  background: var(--color-primary, #2563EB);
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  width: 100%;
  letter-spacing: 0.01em;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
  transition: background 0.15s, box-shadow 0.15s;
}
.save-btn:hover { background: #1D4ED8; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }
.save-btn:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }

/* ── Header with photo ── */
.header-center {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 8px;
}

.customer-photo-section {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 6px;
}

.customer-thumb {
  width: 64px;
  height: 64px;
  border-radius: 50%;
  background: var(--color-primary-light, #EFF6FF);
  border: 3px solid var(--color-primary, #2563EB);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;
  padding: 0;
  overflow: hidden;
  position: relative;
}

.customer-thumb:active {
  transform: scale(0.95);
}

.customer-thumb-img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  position: absolute;
  top: 0;
  left: 0;
}

.customer-thumb-icon {
  width: 32px;
  height: 32px;
  color: var(--color-primary, #2563EB);
  z-index: 1;
}

.take-picture-text {
  font-size: 12px;
  color: var(--color-primary, #2563EB);
  cursor: pointer;
  font-weight: 600;
}

.detail-title {
  text-align: center;
}
</style>