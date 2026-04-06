<template>
  <div class="booking-form" @click="showTimeslots = false" @scroll.capture="showTimeslots = false">

    <!-- Date & Time -->
    <div class="form-card">
      <p class="form-section-label">{{ getLabel('dateTitle') }} &amp; {{ getLabel('timeTitle') }}</p>
      <div class="form-row-2col">
        <div class="form-field" @click.stop="showTimeslots = false">
          <label class="field-label">{{ getLabel('dateTitle') }}</label>
          <div class="input-icon-wrap date-picker-wrap" :class="{'field-required': requiredFields.indexOf('date') > -1}" @click="openDatePicker">
            <font-awesome-icon icon="fa-solid fa-calendar-days" class="field-icon" />
            <Datepicker
                ref="datePicker"
                format="yyyy-MM-dd"
                v-model="elDate"
                :auto-apply="true"
                :text-input="true"
                :hide-input-icon="true"
                :clearable="false"
                :enable-time-picker="false"
                :disabled="isLoadingDates"
                @updateMonthYear="handleMonthYearChange"
                @open="handleDatePickerOpen"
            >
              <template #day="{ day, date }">
                <template v-if="isLoadingDates">
                  <div class="day day-loading">{{ day }}</div>
                </template>
                <template v-else-if="isDateAvailable(date)">
                  <div class="day day-available">{{ day }}</div>
                </template>
                <template v-else>
                  <div class="day day-unavailable">{{ day }}</div>
                </template>
              </template>
            </Datepicker>
          </div>
        </div>
        <div class="form-field time-field">
          <label class="field-label">{{ getLabel('timeTitle') }}</label>
          <div class="input-icon-wrap" :class="{'field-required': requiredFields.indexOf('time') > -1}" ref="timeInputWrap">
            <font-awesome-icon icon="fa-regular fa-clock" class="field-icon" />
            <b-form-input
                v-model="elTime"
                @click.stop="openTimeslots"
                class="time-input"
                readonly
            />
            <teleport to="body">
              <div class="timeslots-dropdown" v-if="showTimeslots" @click.stop :style="{ top: timeDropdownTop + 'px' }">
                <span
                    v-for="timeslot in timeslots"
                    :key="timeslot"
                    class="timeslot-item"
                    :class="{free: freeTimeslots.includes(moment(timeslot, getTimeFormat()).format('HH:mm'))}"
                    @click="setTime(timeslot)"
                >{{ timeslot }}</span>
              </div>
            </teleport>
          </div>
        </div>
      </div>
    </div>

    <!-- Customer -->
    <div class="form-card">
      <p class="form-section-label">Customer</p>
      <button class="select-customer-btn" type="button" @click="chooseCustomer">
        <font-awesome-icon icon="fa-solid fa-users" />
        {{ getLabel('selectExistingClientButtonLabel') }}
      </button>
      <div class="form-field">
        <b-form-input
            :placeholder="getLabel('customerFirstnamePlaceholder')"
            v-model="elCustomerFirstname"
            :class="{'field-required': requiredFields.indexOf('customer_first_name') > -1}"
        />
      </div>
      <div class="form-field">
        <b-form-input :placeholder="getLabel('customerLastnamePlaceholder')" v-model="elCustomerLastname" />
      </div>
      <div class="form-field">
        <b-form-input
            :type="(bookingID && shouldHideEmail) ? 'password' : 'text'"
            :placeholder="getLabel('customerEmailPlaceholder')"
            v-model="elCustomerEmail"
        />
      </div>
      <div class="form-field">
        <b-form-input :placeholder="getLabel('customerAddressPlaceholder')" v-model="elCustomerAddress" />
      </div>
      <div class="form-field">
        <b-form-input
            :type="(bookingID && shouldHidePhone) ? 'password' : 'tel'"
            :placeholder="getLabel('customerPhonePlaceholder')"
            v-model="elCustomerPhone"
        />
      </div>
      <div class="form-field">
        <b-form-textarea
            v-model="elCustomerNotes"
            :placeholder="getLabel('customerNotesPlaceholder')"
            rows="2"
            max-rows="4"
        />
      </div>
      <div class="toggle-row">
        <span class="toggle-label">{{ getLabel('saveAsNewCustomerLabel') }}</span>
        <b-form-checkbox v-model="saveAsNewCustomer" switch />
      </div>
    </div>

    <!-- Services -->
    <div class="form-card">
      <p class="form-section-label">Services</p>
      <template v-if="!isLoadingServicesAssistants">
        <div v-for="(service, index) in elServices" :key="index" class="service-block">
          <div class="service-block-header">
            <span class="service-block-num">{{ index + 1 }}</span>
            <button class="remove-btn" type="button" @click="deleteService(index)">
              <font-awesome-icon icon="fa-solid fa-circle-xmark" />
            </button>
          </div>
          <div class="service">
            <vue-select ref="select-service" class="service-select" close-on-select
                        v-model="service.service_id"
                        :options="getServicesListBySearch(servicesList, serviceSearch[index])"
                        label-by="[serviceName, price, duration, category]" value-by="value"
                        :class="{required: requiredFields.indexOf('services_service_' + index) > -1}">
              <template #label="{ selected }">
                <template v-if="selected">
                  <div class="option-item option-item-selected">
                    <div class="name">
                      <span>{{ selected.category }}</span>
                      <span v-if="selected.category"> | </span>
                      <span class="service-name">{{ selected.serviceName }}</span>
                    </div>
                    <div class="info">
                      <div class="price">
                        <span>{{ selected.price }}</span>
                        <span v-html="selected.currency"></span>
                        <span> | </span>
                        <span>{{ selected.duration }}</span>
                      </div>
                    </div>
                  </div>
                </template>
                <template v-else>{{ getLabel('selectServicesPlaceholder') }}</template>
              </template>
              <template #dropdown-item="{ option }">
                <div class="option-item">
                  <div class="availability-wrapper">
                    <div class="availability" :class="{available: option.available}"></div>
                    <div class="name">
                      <span>{{ option.category }}</span>
                      <span v-if="option.category"> | </span>
                      <span class="service-name">{{ option.serviceName }}</span>
                    </div>
                  </div>
                  <div class="info">
                    <div class="price">
                      <span>{{ option.price }}</span>
                      <span v-html="option.currency"></span>
                      <span> | </span>
                      <span>{{ option.duration }}</span>
                    </div>
                  </div>
                </div>
              </template>
            </vue-select>
            <li class="vue-select-search">
              <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="vue-select-search-icon" />
              <b-form-input v-model="serviceSearch[index]" class="vue-select-search-input"
                            :placeholder="getLabel('selectServicesSearchPlaceholder')" @mousedown.stop />
            </li>
          </div>
          <div class="resource" v-if="isShowResource(service)">
            <vue-select ref="select-resource" class="service-select" close-on-select
                        v-model="service.resource_id"
                        :options="getAttendantsOrResourcesListBySearch(resourcesList, resourceSearch[index])"
                        label-by="text" value-by="value"
                        :class="{required: requiredFields.indexOf('services_assistant_' + index) > -1}"
                        @focus="loadAvailabilityResources(service.service_id)">
              <template #label="{ selected }">
                <template v-if="selected">
                  <div class="option-item option-item-selected"><div class="name"><span>{{ selected.text }}</span></div></div>
                </template>
                <template v-else>{{ getLabel('selectResourcesPlaceholder') }}</template>
              </template>
              <template #dropdown-item="{ option }">
                <div class="option-item">
                  <div class="availability-wrapper">
                    <div class="availability" :class="{available: option.available}"></div>
                    <div class="name">{{ option.text }}</div>
                  </div>
                </div>
              </template>
            </vue-select>
            <li class="vue-select-search">
              <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="vue-select-search-icon" />
              <b-form-input v-model="resourceSearch[index]" class="vue-select-search-input"
                            :placeholder="getLabel('selectResourcesSearchPlaceholder')" @mousedown.stop />
            </li>
          </div>
          <div class="attendant" v-if="isShowAttendant(service)">
            <vue-select ref="select-assistant" class="service-select" close-on-select
                        v-model="service.assistant_id"
                        :options="getAttendantsOrResourcesListBySearch(attendantsList, assistantSearch[index])"
                        label-by="text" value-by="value"
                        :class="{required: requiredFields.indexOf('services_assistant_' + index) > -1}"
                        @focus="loadAvailabilityAttendants(service.service_id)">
              <template #label="{ selected }">
                <template v-if="selected">
                  <div class="option-item option-item-selected"><div class="name"><span>{{ selected.text }}</span></div></div>
                </template>
                <template v-else>{{ getLabel('selectAttendantsPlaceholder') }}</template>
              </template>
              <template #dropdown-item="{ option }">
                <div class="option-item">
                  <div class="availability-wrapper">
                    <div class="availability" :class="{available: option.available}"></div>
                    <div class="name">
                      <span>{{ option.text }}</span>
                      <span v-if="option.variable_price"> [{{ option.variable_price }}<span v-html="option.currency"></span>]</span>
                    </div>
                  </div>
                </div>
              </template>
            </vue-select>
            <li class="vue-select-search">
              <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="vue-select-search-icon" />
              <b-form-input v-model="assistantSearch[index]" class="vue-select-search-input"
                            :placeholder="getLabel('selectAssistantsSearchPlaceholder')" @mousedown.stop />
            </li>
          </div>
        </div>
      </template>
      <div class="add-item-row">
        <button class="add-item-btn" type="button" @click="addService" :disabled="isLoadingServicesAssistants">
          <font-awesome-icon icon="fa-solid fa-plus" />
          {{ getLabel('addServiceButtonLabel') }}
        </button>
        <b-spinner variant="primary" small v-if="isLoadingServicesAssistants" />
      </div>
      <b-alert :show="requiredFields.indexOf('services') > -1" fade variant="danger" class="mt-2">
        {{ getLabel('addServiceMessage') }}
      </b-alert>
    </div>

    <!-- Discounts -->
    <div class="form-card" v-if="showDiscount">
      <p class="form-section-label">{{ getLabel('addDiscountButtonLabel') }}</p>
      <template v-if="!isLoadingDiscounts">
        <div v-for="(discount, index) in elDiscounts" :key="index" class="discount-block">
          <div class="discount">
            <vue-select ref="select-discount" class="discount-select" close-on-select
                        v-model="elDiscounts[index]"
                        :options="getDiscountsListBySearch(discountsList, discountSearch[index])"
                        label-by="text" value-by="value">
              <template #label="{ selected }">
                <template v-if="selected"><span class="discount-name">{{ selected.text }}</span></template>
                <template v-else>{{ getLabel('selectDiscountLabel') }}</template>
              </template>
              <template #dropdown-item="{ option }">
                <div class="option-item">
                  <span class="discount-name">{{ option.text }}</span>
                  <div class="info"><span>expires: {{ option.expires }}</span></div>
                </div>
              </template>
            </vue-select>
            <li class="vue-select-search">
              <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="vue-select-search-icon" />
              <b-form-input v-model="discountSearch[index]" class="vue-select-search-input"
                            :placeholder="getLabel('selectDiscountsSearchPlaceholder')" @mousedown.stop />
            </li>
          </div>
          <button class="remove-btn remove-btn--inline" type="button" @click="deleteDiscount(index)">
            <font-awesome-icon icon="fa-solid fa-circle-xmark" />
          </button>
        </div>
      </template>
      <div class="add-item-row">
        <button class="add-item-btn" type="button" @click="addDiscount" :disabled="isLoadingDiscounts">
          <font-awesome-icon icon="fa-solid fa-plus" />
          {{ getLabel('addDiscountButtonLabel') }}
        </button>
        <b-spinner variant="primary" small v-if="isLoadingDiscounts" />
      </div>
    </div>

    <!-- Extra Info -->
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
            <label class="field-label" for="admin_note">{{ getLabel('adminNoteLabel') || 'Administration note' }}</label>
            <textarea
                v-model="elAdminNote"
                id="admin_note"
                class="form-control"
                rows="3"
            ></textarea>
          </div>
          <div class="form-field mt-2">
            <label class="field-label" for="customer_personal_notes">{{ getLabel('customerPersonalNotesLabel') }}</label>
            <b-form-textarea
                v-model="elCustomerPersonalNotes"
                id="customer_personal_notes"
                :placeholder="getLabel('customerPersonalNotesPlaceholder')"
                rows="2"
                max-rows="4"
            />
          </div>
        </div>
      </b-collapse>
    </div>

    <!-- Status & Save -->
    <div class="form-card">
      <p class="form-section-label">Status</p>
      <b-form-select v-model="elStatus" :options="statusesList" class="status-select" />
      <div class="save-row">
        <button class="save-btn" type="button" @click="save" :disabled="isLoading">
          <b-spinner small v-if="isLoading" />
          <font-awesome-icon icon="fa-solid fa-check" v-else />
          {{ getLabel('saveButtonLabel') }}
        </button>
      </div>
      <b-alert :show="isSaved" fade variant="success" class="mt-2">{{ getLabel('savedLabel') }}</b-alert>
      <b-alert :show="isError" fade variant="danger" class="mt-2">{{ errorMessage }}</b-alert>
      <b-alert :show="!isValid && requiredFields.length > 1" fade variant="danger" class="mt-2">{{ getLabel('validationMessage') }}</b-alert>
      <b-alert :show="shopError" fade variant="warning" class="mt-2">{{ getLabel('selectShopFirstMessage') }}</b-alert>
    </div>

  </div>
</template>

<script>
import CustomField from './CustomField.vue'
import mixins from "@/mixin";

export default {
  name: 'EditBooking',
  props: {
    bookingID: {
      default: function () {
        return '';
      },
    },
    date: {
      default: function () {
        return '';
      },
    },
    time: {
      default: function () {
        return '';
      },
    },
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
    customerNotes: {
      default: function () {
        return '';
      },
    },
    customerPersonalNotes: {
      default: function () {
        return '';
      },
    },
    adminNote: {
      default: function () {
        return '';
      },
    },
    services: {
      default: function () {
        return [];
      },
    },
    discounts: {
      default: function () {
        return [];
      },
    },
    status: {
      default: function () {
        return '';
      },
    },
    isLoading: {
      default: function () {
        return false;
      },
    },
    isSaved: {
      default: function () {
        return false;
      },
    },
    isError: {
      default: function () {
        return false;
      },
    },
    errorMessage: {
      default: function () {
        return '';
      },
    },
    customFields: {
      default: function () {
        return [];
      },
    },
    shop: {
      default: function () {
        return {};
      },
    },
  },
  mixins: [mixins],
  mounted() {
    this.loadDiscounts()
    this.loadAvailabilityIntervals()
    this.loadAvailabilityServices()
    this.loadAvailableDates()
    this.loadCustomFields()
    this.isLoadingServicesAssistants = true
    Promise.all([
      this.loadServices(),
      this.loadAttendants(),
      this.loadResources(),
      this.loadServicesCategory()
    ]).then(() => {
      this.isLoadingServicesAssistants = false
      this.elServices.forEach((i, index) => {
        this.addServicesSelectSearchInput(index)
        this.addAssistantsSelectSearchInput(index)
        this.addResourcesSelectSearchInput(index)
      })
    })
  },
  data: function () {
    const originalEmail = this.customerEmail || '';
    const originalPhone = this.customerPhone || '';

    return {
      shopError: false,
      elDate: this.date,
      elTime: this.timeFormat(this.time),
      elCustomerFirstname: this.customerFirstname,
      elCustomerLastname: this.customerLastname,
      elCustomerEmail: (this.bookingID && this.shouldHideEmail) ? '***@***' : originalEmail,
      elCustomerPhone: (this.bookingID && this.shouldHidePhone) ? '*******' : originalPhone,
      originalCustomerEmail: originalEmail,
      originalCustomerPhone: originalPhone,
      elCustomerAddress: this.customerAddress,
      elCustomerNotes: this.customerNotes,
      elCustomerPersonalNotes: this.customerPersonalNotes,
      elAdminNote: this.adminNote,
      elServices: [...this.services].map(s => ({
        service_id: s.service_id,
        assistant_id: s.assistant_id,
        resource_id: s.resource_id
      })),
      bookings: [],
      elDiscounts: [...this.discounts],
      elStatus: this.status,
      visibleDiscountInfo: false,
      elDiscountsList: [],
      elServicesList: [],
      elServicesNameList: [],
      elAttendantsList: [],
      elResourcesList: [],
      showTimeslots: false,
      timeDropdownTop: 0,
      availabilityIntervals: {},
      saveAsNewCustomer: false,
      availabilityServices: [],
      serviceSearch: [],
      discountSearch: [],
      isValid: true,
      requiredFields: [],
      visibleExtraInfo: false,
      customFieldsList: [],
      elCustomFields: this.customFields,
      isLoadingServicesAssistants: false,
      isLoadingDiscounts: false,
      assistantSearch: [],
      resourceSearch: [],
      availabilityAttendants: [],
      availabilityResources: [],
      availabilityDates: [],
      currentMonth: null,
      currentYear: null,
      isLoadingDates: false,
      vueTelInputOptions: {
        'placeholder': this.getLabel('customerPhonePlaceholder')
      },
      specificValidationMessage: this.getLabel('validationMessage'),
    };
  },
  watch: {
    elDate() {
      this.loadAvailabilityIntervals()
      this.loadAvailabilityServices()
      this.loadDiscounts()
      if (this.isError) {
        this.$emit('error-state', {
          isError: false,
          errorMessage: ''
        });
      }
    },
    elTime() {
      this.loadAvailabilityServices()
      this.loadDiscounts()
      if (this.isError) {
        this.$emit('error-state', {
          isError: false,
          errorMessage: ''
        });
      }
    },
    timeslots(newTimeslots) {
      if (newTimeslots.length && !this.elTime) {
        this.elTime = this.moment(newTimeslots[0], this.getTimeFormat()).format('HH:mm');
      }
    },
    bookingServices() {
      this.loadDiscounts()
      this.loadAvailableDates()
    },
    shop(newShop, oldShop) {
      if (newShop?.id !== oldShop?.id) {
        this.loadAvailabilityIntervals();
        this.loadAvailabilityServices();
        this.isLoadingServicesAssistants = true;
        Promise.all([
          this.loadServices(),
          this.loadAttendants(),
          this.loadResources(),
          this.loadServicesCategory()
        ]).then(() => {
          this.isLoadingServicesAssistants = false;
          this.clearServices();
          this.elServices.forEach((i, index) => {
            this.addServicesSelectSearchInput(index);
            this.addAssistantsSelectSearchInput(index);
            this.addResourcesSelectSearchInput(index);
          });
          this.loadDiscounts();
          this.requiredFields = [];
          this.isValid = true;
          this.shopError = false;
        }).catch(() => {
          this.isLoadingServicesAssistants = false;
        });
      }
    },
    'elServices': {
      deep: true,
      handler() {
        if (this.isError) {
          this.$emit('error-state', {
            isError: false,
            errorMessage: ''
          });
        }
      }
    },

  },
  computed: {
    statusesList() {
      var statuses = [];
      for (var key in this.$root.statusesList) {
        statuses.push({value: key, text: this.$root.statusesList[key].label})
      }
      return statuses;
    },
    discountsList() {
      var list = [];
      this.elDiscountsList.forEach((i) => {
        list.push({value: i.id, text: i.name, expires: i.valid_to})
      })
      return list;
    },
    servicesList() {
      var list = [];
      this.elServicesList.forEach((serviceItem) => {
        let categories = [];
        serviceItem.categories.forEach(catId => {
          let category = this.elServicesNameList.find(item => item.id === catId)
          if (category) {
            categories.push(category.name)
          }
        })
        let available = false
        let availabilityService = this.availabilityServices.find(item => item.id === serviceItem.id)
        if (availabilityService) {
          available = availabilityService.available
        }
        let price = serviceItem.price
        if (this.shop && serviceItem.shops) {
          serviceItem.shops.forEach((shopService) => {
            if (shopService.id === this.shop.id) {
              price = shopService.price

            }
          })
        }
        list.push({
          value: serviceItem.id,
          price: price,
          duration: serviceItem.duration,
          currency: serviceItem.currency,
          serviceName: serviceItem.name,
          category: categories.join(', '),
          empty_assistants: serviceItem.empty_assistants,
          empty_resources: serviceItem.empty_resources,
          available: available,
        })
      });

      return list;
    },
    attendantsList() {
      var list = [];
      this.elAttendantsList.forEach((i) => {
        let available = false
        let variable_price = false
        let availabilityAttendant = this.availabilityAttendants.find(item => item.id === i.id)
        if (availabilityAttendant) {
          available = availabilityAttendant.available
          variable_price = availabilityAttendant.variable_price
        }
        list.push({
          value: i.id,
          text: i.name,
          available: available,
          variable_price: variable_price,
          currency: i.currency
        })
      })
      return list;
    },
    resourcesList() {
      var list = [];
      this.elResourcesList.forEach((i) => {
        let available = false
        let availabilityResource = this.availabilityResources.find(item => item.id === i.id)
        if (availabilityResource) {
          available = availabilityResource.status === 1
        }
        list.push({value: i.id, text: i.name, available: available})
      })
      return list;
    },
    timeslots() {
      var timeslots = this.availabilityIntervals.workTimes ? Object.values(this.availabilityIntervals.workTimes) : []
      return timeslots.map(t => this.timeFormat(t))
    },
    freeTimeslots() {
      return this.availabilityIntervals.times ? Object.values(this.availabilityIntervals.times) : []
    },
    showAttendant() {
      return typeof this.$root.settings.attendant_enabled !== 'undefined' ? this.$root.settings.attendant_enabled : true;
    },
    showResource() {
      return typeof this.$root.settings.resources_enabled !== 'undefined' ? this.$root.settings.resources_enabled : true;
    },
    showDiscount() {
      return typeof this.$root.settings.discounts_enabled !== 'undefined' ? this.$root.settings.discounts_enabled : true;
    },
    bookingServices() {
      return JSON.parse(JSON.stringify(this.elServices)).map(s => {
        !s.assistant_id ? s.assistant_id = 0 : s.assistant_id;
        !s.resource_id ? s.resource_id = 0 : s.resource_id;
        return s;
      })
    },
  },
  methods: {
    sprintf(format, ...args) {
      return format.replace(/%s/g, (match) => args.shift() || match);
    },
    close() {
      this.$emit('close');
    },
    chooseCustomer() {
      this.$emit('chooseCustomer');
    },
    convertDurationToMinutes(duration) {
      const [hours, minutes] = duration.split(':').map(Number);
      return hours * 60 + minutes;
    },
    isOverlapping(startA, endA, startB, endB) {
      return startA.isBefore(endB) && endA.isAfter(startB);
    },
    calculateServiceTimes(booking) {
      const serviceTimes = [];
      let currentStartTime = this.moment(`${booking.date} ${booking.time}`, 'YYYY-MM-DD HH:mm');

      booking.services.forEach((service) => {
        const serviceData = this.servicesList.find(s => s.value === service.service_id);

        if (!serviceData) {
          return;
        }

        const durationMinutes = this.convertDurationToMinutes(serviceData.duration);
        const endTime = this.moment(currentStartTime).add(durationMinutes, 'minute');

        const serviceTime = {
          service_id: service.service_id,
          assistant_id: service.assistant_id,
          resource_id: service.resource_id,
          start: currentStartTime.clone(),
          end: endTime.clone(),
          duration: durationMinutes,
          serviceName: serviceData.serviceName
        };

        serviceTimes.push(serviceTime);
        currentStartTime = endTime.clone();
      });

      return serviceTimes;
    },
    async validateAssistantAvailability(booking) {
      try {
        const existingBookings = await this.getExistingBookings(booking.date);
        const newServiceTimes = this.calculateServiceTimes(booking);

        for (const newServiceTime of newServiceTimes) {
          if (!newServiceTime.assistant_id) {
            continue;
          }

          const relevantBookings = existingBookings.filter(b =>
            b.services.some(s => s.assistant_id === newServiceTime.assistant_id)
          );

          for (const existingBooking of relevantBookings) {
            const existingServiceTimes = this.calculateServiceTimes({
              date: existingBooking.date,
              time: existingBooking.time,
              services: existingBooking.services
            });

            for (const existingServiceTime of existingServiceTimes) {
              if (existingServiceTime.assistant_id !== newServiceTime.assistant_id) {
                continue;
              }

              const isOverlapping = this.isOverlapping(
                newServiceTime.start,
                newServiceTime.end,
                existingServiceTime.start,
                existingServiceTime.end
              );

              if (isOverlapping) {
                const assistant = this.attendantsList.find(a => a.value === newServiceTime.assistant_id);
                const assistantName = assistant ? assistant.text : this.getLabel('assistantBusyTitle');

                const errorMessage = `${assistantName} ` + this.sprintf(
                  this.getLabel('assistantBusyMessage'),
                  existingServiceTime.start.format('HH:mm'),
                  existingServiceTime.end.format('HH:mm')
                );

                throw new Error(errorMessage);
              }
            }
          }
        }

        return true;
      } catch (error) {
        this.$emit('error-state', {
          isError: true,
          errorMessage: error.message,
        })
        return error;
      }
    },
    async getExistingBookings(date) {
      try {
        const response = await this.axios.get('bookings', {
          params: {
            start_date: this.moment(date).format('YYYY-MM-DD'),
            end_date: this.moment(date).format('YYYY-MM-DD'),
            per_page: -1,
            shop: this.shop?.id || null,
          },
        });

        return Array.isArray(response.data.items)
            ? response.data.items.filter(b => String(b.id) !== String(this.bookingID))
            : [];
      } catch (error) {
        console.error('Error getting existing bookings:', error);
        return [];
      }
    },
    clearServices() {
      this.elServices = [];
      this.serviceSearch = [];
      this.assistantSearch = [];
      this.resourceSearch = [];
    },
    async save() {
      this.isValid = this.validate();
      if (!this.isValid) {
        if (this.requiredFields.includes('shop') && this.$root.settings.shops_enabled) {
          this.$emit('error', {
            message: this.getLabel('selectShopFirstMessage'),
            type: 'shop'
          })
          return
        }
        return
      }
      const customerEmail = this.bookingID ?
          (this.shouldHideEmail && this.elCustomerEmail === '***@***' ? this.originalCustomerEmail : this.elCustomerEmail) :
          this.elCustomerEmail;

      const customerPhone = this.bookingID ?
          (this.shouldHidePhone && this.elCustomerPhone === '*******' ? this.originalCustomerPhone : this.elCustomerPhone) :
          this.elCustomerPhone;

      const booking = {
        date: this.moment(this.elDate).format('YYYY-MM-DD'),
        time: this.moment(this.elTime, this.getTimeFormat()).format('HH:mm'),
        status: this.elStatus,
        customer_id: this.customerID || 0,
        customer_first_name: this.elCustomerFirstname,
        customer_last_name: this.elCustomerLastname,
        customer_email: customerEmail,
        customer_phone: customerPhone,
        customer_address: this.elCustomerAddress,
        services: this.bookingServices,
        discounts: this.elDiscounts,
        note: this.elCustomerNotes,
        customer_personal_note: this.elCustomerPersonalNotes,
        admin_note: this.elAdminNote,
        save_as_new_customer: this.saveAsNewCustomer,
        custom_fields: this.elCustomFields,
      }

      if (this.shop) {
        booking.shop = {id: this.shop.id};
      }

      const availabilityCheck = await this.validateAssistantAvailability(booking);
      if (availabilityCheck instanceof Error) {
        return;
      }

      this.$emit('error-state', {
        isError: false,
        errorMessage: ''
      });

      this.$emit('save', booking);
    },
    loadDiscounts() {
      this.isLoadingDiscounts = true;
      this.axios
          .get('discounts', {
            params: {
              return_active: true,
              date: this.moment(this.elDate).format('YYYY-MM-DD'),
              time: this.moment(this.elTime, this.getTimeFormat()).format('HH:mm'),
              customer_email: this.elCustomerEmail,
              services: this.bookingServices,
              shop: this.shop ? this.shop.id : null,
            },
          })
          .then(response => {
            this.elDiscountsList = response.data.items;
            this.discountSearch = [];
            this.elDiscounts = this.elDiscounts.filter(elDiscount => {
              const dl = this.discountsList.map(discount => discount.value);
              return dl.includes(elDiscount);
            });
            this.elDiscounts.forEach((i, index) => {
              this.addDiscountsSelectSearchInput(index);
            });
          })
          .catch(() => {
            this.elDiscountsList = [];
          })
          .finally(() => {
            this.isLoadingDiscounts = false;
          });
    },
    loadServices() {
      return this.axios
          .get('services', {
            params: {
              per_page: -1,
              shop: this.shop ? this.shop.id : null,
            },
          })
          .then(response => {
            this.elServicesList = response.data.items;
          });
    },
    loadServicesCategory() {
      return this.axios.get('services/categories').then(response => {
        this.elServicesNameList = response.data.items;
      });
    },
    loadAttendants() {
      return this.axios
          .get('assistants', {
            params: {
              shop: this.shop ? this.shop.id : null,
              orderby: 'order',
              order: 'asc'
            }
          })
          .then(response => {
            this.elAttendantsList = response.data.items;
          });
    },
    loadResources() {
      return this.axios
          .get('resources', {params: {shop: this.shop ? this.shop.id : null}})
          .then(response => {
            this.elResourcesList = response.data.items;
          })
          .catch(() => {
          });
    },
    loadAvailabilityIntervals() {
      this.axios
          .post('availability/intervals', {
            date: this.moment(this.elDate).format('YYYY-MM-DD'),
            time: this.moment(this.elTime, this.getTimeFormat()).format('HH:mm'),
            shop: this.shop ? this.shop.id : 0,
          })
          .then(response => {
            this.availabilityIntervals = response.data.intervals;
          });
    },
    loadAvailabilityServices() {
      this.axios
          .post('availability/booking/services', {
            date: this.moment(this.elDate).format('YYYY-MM-DD'),
            time: this.moment(this.elTime, this.getTimeFormat()).format('HH:mm'),
            booking_id: !this.bookingID ? 0 : this.bookingID,
            is_all_services: true,
            services: this.bookingServices.filter(i => i.service_id),
            shop: this.shop ? this.shop.id : 0,
          })
          .then(response => {
            this.availabilityServices = response.data.services;
          });
    },
    loadAvailabilityAttendants(service_id) {
      this.axios
          .post('availability/booking/assistants', {
            date: this.moment(this.elDate).format('YYYY-MM-DD'),
            time: this.moment(this.elTime, this.getTimeFormat()).format('HH:mm'),
            booking_id: !this.bookingID ? 0 : this.bookingID,
            selected_service_id: service_id ? service_id : 0,
            services: this.bookingServices.filter(i => i.service_id),
            shop: this.shop ? this.shop.id : 0,
          })
          .then(response => {
            this.availabilityAttendants = response.data.assistants;
          });
    },
    loadAvailabilityResources(service_id) {
      this.axios
          .post('availability/booking/resources', {
            date: this.moment(this.elDate).format('YYYY-MM-DD'),
            time: this.moment(this.elTime, this.getTimeFormat()).format('HH:mm'),
            booking_id: !this.bookingID ? 0 : this.bookingID,
            selected_service_id: service_id ? service_id : 0,
            services: this.bookingServices.filter(i => i.service_id),
            shop: this.shop ? this.shop.id : 0,
          })
          .then(response => {
            this.availabilityResources = response.data.resources;
          });
    },
    loadAvailableDates() {
      // Only load if we have services selected
      if (!this.bookingServices || this.bookingServices.length === 0) {
        console.log('EditBooking: No services selected, skipping date availability load');
        this.availabilityDates = [];
        return;
      }

      // Determine date range for the current month view
      const year = this.currentYear || this.moment(this.elDate).year();
      const month = this.currentMonth !== null ? this.currentMonth : this.moment(this.elDate).month();
      
      const fromDate = this.moment().year(year).month(month).startOf('month').format('YYYY-MM-DD');
      const toDate = this.moment().year(year).month(month).endOf('month').format('YYYY-MM-DD');

      console.log('EditBooking: Loading available dates', { fromDate, toDate, shop: this.shop?.id });

      this.isLoadingDates = true;
      this.axios
          .get('availability/stats', {
            params: {
              from_date: fromDate,
              to_date: toDate,
              shop: this.shop ? this.shop.id : 0,
            }
          })
          .then(response => {
            this.availabilityDates = response.data.stats || [];
            console.log('EditBooking: Loaded availability dates', this.availabilityDates.length, 'dates');
            console.log('EditBooking: Sample dates:', this.availabilityDates.slice(0, 5));
          })
          .catch(error => {
            console.error('Error loading available dates:', error);
            this.availabilityDates = [];
          })
          .finally(() => {
            this.isLoadingDates = false;
          });
    },
    handleMonthYearChange(data) {
      console.log('EditBooking: Month/Year changed', data);
      this.currentMonth = data.month;
      this.currentYear = data.year;
      this.loadAvailableDates();
    },
    isDateAvailable(date) {
      if (!this.availabilityDates || this.availabilityDates.length === 0) {
        return true; // Show all dates as available if no data loaded yet
      }
      
      const dateStr = this.moment(date).format('YYYY-MM-DD');
      const dateInfo = this.availabilityDates.find(d => d.date === dateStr);
      
      const available = dateInfo ? dateInfo.available : false;
      // console.log('EditBooking: Date check', dateStr, available ? 'available' : 'unavailable');
      
      return available;
    },
    loadCustomFields() {
      this.axios.get('custom-fields/booking').then(response => {
        this.customFieldsList = response.data.items.filter(
            i => ['html', 'file'].indexOf(i.type) === -1
        );
      });
    },
    addDiscount() {
      this.elDiscounts.push(null);
      this.addDiscountsSelectSearchInput(this.elDiscounts.length - 1);
    },
    deleteDiscount(index) {
      this.elDiscounts.splice(index, 1);
      this.discountSearch.splice(index, 1);
    },
    addService() {
      this.elServices.push({service_id: null, assistant_id: null, resource_id: null});
      this.addServicesSelectSearchInput(this.elServices.length - 1);
      this.addAssistantsSelectSearchInput(this.elServices.length - 1);
      this.addResourcesSelectSearchInput(this.elServices.length - 1);
    },
    deleteService(index) {
      this.elServices.splice(index, 1);
      this.serviceSearch.splice(index, 1);
    },
    openDatePicker(event) {
      // Prevent click from bubbling
      event.stopPropagation();
      
      // Open the date picker programmatically
      if (this.$refs.datePicker && this.$refs.datePicker.openMenu) {
        this.$refs.datePicker.openMenu();
      }
    },
    handleDatePickerOpen() {
      // Close time picker when date picker opens
      this.showTimeslots = false;
      
      // Force full width and remove blue styles using JavaScript
      this.$nextTick(() => {
        setTimeout(() => {
          // Make date picker full width
          const menu = document.querySelector('.dp__menu');
          if (menu) {
            const rect = menu.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.width = '100vw';
            menu.style.left = '0';
            menu.style.right = '0';
            menu.style.top = rect.top + 'px';
            menu.style.margin = '0';
            menu.style.borderRadius = '0';
            menu.style.maxWidth = '100vw';
            menu.style.transform = 'none';
          }
          
          // Make calendar content full width
          const calendar = document.querySelector('.dp__calendar');
          if (calendar) {
            calendar.style.width = '100%';
            calendar.style.maxWidth = '100%';
          }
          
          // Remove blue styles from date picker cells
          const cells = document.querySelectorAll('.dp__cell_inner');
          cells.forEach(cell => {
            cell.style.background = 'transparent';
            cell.style.backgroundColor = 'transparent';
            cell.style.border = 'none';
            cell.style.boxShadow = 'none';
          });
        }, 10);
      });
    },
    openTimeslots() {
      if (this.showTimeslots) {
        this.showTimeslots = false;
        return;
      }
      const el = this.$refs.timeInputWrap;
      if (el) {
        const rect = el.getBoundingClientRect();
        this.timeDropdownTop = rect.bottom + 4;
      }
      this.showTimeslots = true;
    },
    setTime(timeslot) {
      this.elTime = this.moment(timeslot, this.getTimeFormat()).format('HH:mm');
      this.showTimeslots = false;
      this.loadAvailabilityServices();
      this.loadDiscounts();
    },
    getServicesListBySearch(list, search) {
      if (!search) {
        return list
      }
      return list.filter(i =>
          new RegExp(search, 'ig').test([i.category, i.serviceName, i.price, i.duration].join(''))
      );
    },
    getDiscountsListBySearch(list, search) {
      if (!search) {
        return list
      }
      return list.filter(i => new RegExp(search, 'ig').test(i.text));
    },
    validate() {
      this.requiredFields = [];
      this.shopError = false;

      if (this.$root.settings.shops_enabled) {
        if (!this.shop || !this.shop.id) {
          this.requiredFields.push('shop');
          this.shopError = true;
        }
      }

      if (!this.elDate) {
        this.requiredFields.push('date')
      }
      if (!this.elTime.trim()) {
        this.requiredFields.push('time')
      }
      if (!this.elCustomerFirstname.trim()) {
        this.requiredFields.push('customer_first_name')
      }
      if (!this.bookingServices.length) {
        this.requiredFields.push('services')
      }
      this.bookingServices.forEach((i, index) => {
        if (!i.service_id) {
          this.requiredFields.push('services_service_' + index)
        }
        if (this.isShowAttendant(i) && !i.assistant_id) {
          this.requiredFields.push('services_assistant_' + index)
        }
      })

      if (this.requiredFields.length === 1 && this.requiredFields.includes('shop')) {
        this.specificValidationMessage = this.getLabel('selectShopFirstMessage');
      } else {
        this.specificValidationMessage = this.getLabel('validationMessage');
      }
      return this.requiredFields.length === 0
    },
    isShowAttendant(service) {
      let serviceItem = this.servicesList.find((i) => i.value === service.service_id)
      if (!serviceItem) {
        return this.showAttendant
      }
      return this.showAttendant && (!service.service_id || (serviceItem && !serviceItem.empty_assistants))
    },
    isShowResource(service) {
      let serviceItem = this.servicesList.find((i) => i.value === service.service_id)
      if (!serviceItem) {
        return this.showResource
      }
      return this.showResource && (!service.service_id || (serviceItem && !serviceItem.empty_resources))
    },
    updateCustomField(key, value) {
      let field = this.elCustomFields.find(i => i.key === key)
      if (field) {
        field.value = value
      } else {
        this.elCustomFields.push({key: key, value: value})
      }
    },
    getCustomFieldValue(key, default_value) {
      let field = this.elCustomFields.find(i => i.key === key)
      if (field) {
        return field.value
      }
      return default_value
    },
    addServicesSelectSearchInput(index) {
      this.serviceSearch.push('')
      setTimeout(() => {
        window.document
            .querySelectorAll(".service .vue-dropdown")[index]
              .prepend(window.document.querySelectorAll(".service .vue-select-search")[index])

        let i = this.$refs['select-service'][index]

        let blur = i.blur
        i.blur = () => {
        }

        let focus = i.focus
        let input = window.document.querySelectorAll(".service .vue-select-search-input")[index];
        i.focus = () => {
          focus()
          setTimeout(() => {
            input.focus()
          }, 0)
        }
        input.addEventListener('blur', () => {
          blur()
          this.serviceSearch[index] = ''
        })
      }, 0);
    },
    addAssistantsSelectSearchInput(index) {
      this.assistantSearch.push('')
      setTimeout(() => {
        window.document
            .querySelectorAll(".attendant .vue-dropdown")[index]
            .prepend(window.document.querySelectorAll(".attendant .vue-select-search")[index])

        let i = this.$refs['select-assistant'][index]

        let blur = i.blur
        i.blur = () => {
        }

        let focus = i.focus
        let input = window.document.querySelectorAll(".attendant .vue-select-search-input")[index];
        i.focus = () => {
          focus()
          setTimeout(() => {
            input.focus()
          }, 0)
        }
        input.addEventListener('blur', () => {
          blur()
          this.assistantSearch[index] = ''
        })
      }, 0);
    },
    addResourcesSelectSearchInput(index) {
      this.resourceSearch.push('')
      setTimeout(() => {
        window.document
            .querySelectorAll(".resource .vue-dropdown")[index]
            .prepend(window.document.querySelectorAll(".resource .vue-select-search")[index])

        let i = this.$refs['select-resource'][index]

        let blur = i.blur
        i.blur = () => {
        }

        let focus = i.focus
        let input = window.document.querySelectorAll(".resource .vue-select-search-input")[index];
        i.focus = () => {
          focus()
          setTimeout(() => {
            input.focus()
          }, 0)
        }
        input.addEventListener('blur', () => {
          blur()
          this.resourceSearch[index] = ''
        })
      }, 0);
    },
    addDiscountsSelectSearchInput(index) {
      this.discountSearch.push('')
      setTimeout(() => {
        window.document.querySelectorAll(".discount .vue-dropdown")[index].prepend(window.document.querySelectorAll(".discount .vue-select-search")[index])

        let i = this.$refs['select-discount'][index]

        let blur = i.blur
        i.blur = () => {
        }

        let focus = i.focus
        let input = window.document.querySelectorAll(".discount .vue-select-search-input")[index];
        i.focus = () => {
          focus()
          setTimeout(() => {
            input.focus()
          }, 0)
        }
        input.addEventListener('blur', () => {
          blur()
          this.discountSearch[index] = ''
        })
      }, 0);
    },
    getAttendantsOrResourcesListBySearch(list, search) {
      if (!search) {
        return list
      }
      return list.filter(i => new RegExp(search, 'ig').test([i.text].join('')))
    },
  },
  emits: ['close', 'chooseCustomer', 'save', 'error-state'],
  components: {
    CustomField,
  },
}
</script>

<style scoped>
.booking-form {
  background: var(--color-background, #F4F6FA);
  padding-bottom: 32px;
}
.form-card {
  background: var(--color-surface, #fff);
  border-radius: var(--radius-md, 12px);
  margin: 12px var(--spacing-page, 16px) 0;
  padding: var(--spacing-card, 14px);
}
.form-section-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--color-text-muted, #94A3B8);
  margin-bottom: 10px;
}
.form-row-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.form-field {
  margin-bottom: 12px;
}
.form-field:last-child { margin-bottom: 0; }
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
.form-field.vue-select-search :deep(.form-control) {
  border: none;
  border-radius: 0;
  padding: 6px 10px;
  font-size: 14px;
  box-shadow: none;
}
.input-icon-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.field-icon {
  position: absolute;
  left: 10px;
  color: var(--color-text-muted, #94A3B8);
  z-index: 1;
  pointer-events: none;
}
.input-icon-wrap :deep(.dp__input),
.input-icon-wrap .time-input {
  padding-left: 32px !important;
  height: 48px !important;
  min-height: 48px !important;
}
.field-required :deep(.dp__input),
.field-required .time-input {
  border-color: #dc3545 !important;
}
.time-field { position: relative; }
.time-input { width: 100%; cursor: pointer; }

/* Make entire date picker input area clickable */
.date-picker-wrap {
  cursor: pointer;
}
.date-picker-wrap :deep(.dp__input) {
  cursor: pointer;
  pointer-events: all;
}
.date-picker-wrap :deep(.dp__input_wrap) {
  width: 100%;
}
.date-picker-wrap :deep(.dp__input_icon) {
  pointer-events: none;
}
.timeslots-dropdown {
  position: fixed;
  left: 0;
  right: 0;
  background: var(--color-surface, #fff);
  border-top: 1px solid var(--color-border, #E2E8F0);
  border-radius: var(--radius-md, 12px) var(--radius-md, 12px) 0 0;
  /* 6 rows × (item height 30px + gap 4px) + top/bottom padding 12px */
  max-height: 216px;
  overflow-y: auto;
  z-index: 9999;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  padding: 12px;
  gap: 8px;
  box-shadow: 0 -4px 24px rgba(0,0,0,0.12);
}
.timeslot-item {
  padding: 6px 4px;
  border-radius: var(--radius-pill, 999px);
  font-size: 13px;
  font-weight: 500;
  text-align: center;
  cursor: pointer;
  color: var(--color-error, #DC2626);
  background: rgba(220,38,38,0.08);
}
.timeslot-item.free {
  color: var(--color-confirmed, #16A34A);
  background: rgba(22,163,74,0.08);
}
.select-customer-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 10px 14px;
  margin-bottom: 12px;
  border: 1.5px dashed var(--color-primary, #2563EB);
  border-radius: var(--radius-sm, 8px);
  background: var(--color-primary-light, #EFF6FF);
  color: var(--color-primary, #2563EB);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
}
.toggle-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-top: 6px;
}
.toggle-label {
  font-size: 14px;
  color: var(--color-text-secondary, #64748B);
}
.service-block {
  background: var(--color-background, #F4F6FA);
  border-radius: var(--radius-sm, 8px);
  padding: 10px;
  margin-bottom: 8px;
  display: flex;
  flex-direction: column;
  gap: 10px;
}
.service-block-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.service-block-num {
  font-size: 12px;
  font-weight: 700;
  color: var(--color-text-muted, #94A3B8);
}
.remove-btn {
  background: none;
  border: none;
  padding: 0;
  color: var(--color-error, #DC2626);
  font-size: 18px;
  cursor: pointer;
}
.remove-btn--inline {
  margin-left: 8px;
  flex-shrink: 0;
}
.discount-block {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  margin-bottom: 8px;
}
.discount-block .discount { flex: 1; }
.add-item-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding-top: 4px;
}
.add-item-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  border-radius: var(--radius-pill, 999px);
  border: 1.5px solid var(--color-primary, #2563EB);
  background: none;
  color: var(--color-primary, #2563EB);
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
}
.add-item-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.collapsible-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  cursor: pointer;
}
.collapsible-icon { color: var(--color-text-muted, #94A3B8); font-size: 14px; }
.status-select {
  width: 100%;
  margin-bottom: 12px;
}
.save-row { display: flex; justify-content: flex-end; }
.save-btn {
  display: flex;
  align-items: center;
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
  justify-content: center;
  letter-spacing: 0.01em;
  box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
  transition: background 0.15s, box-shadow 0.15s;
}
.save-btn:hover { background: #1D4ED8; box-shadow: 0 6px 16px rgba(37, 99, 235, 0.35); }
.save-btn:disabled { opacity: 0.6; cursor: not-allowed; box-shadow: none; }

/* vue-select search */
.service-select,
.discount-select {
  width: 100%;
  font-size: 1rem;
  color: #212529;
  line-height: 1.5;
  border-radius: .375rem;
}
.option-item {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  align-items: center;
  color: #637491;
  padding: 4px;
}
.option-item-selected {
  color: #000;
  width: 100%;
  padding-right: 10px;
  padding-left: 10px;
}
.availability-wrapper { display: flex; align-items: center; }
.availability {
  width: 10px;
  height: 10px;
  margin-right: 10px;
  background-color: #9F0404;
  border-radius: 10px;
}
.availability.available { background-color: #1EAD3F; }
.service-name { font-weight: bold; }
.required { border: solid 1px #9F0404; }

/* Date picker day styling */
.day {
  width: 100%;
  height: 100%;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  padding: 4px;
}

.day-loading {
  color: #D1D5DB !important;
  background: transparent !important;
  font-weight: 400;
  cursor: not-allowed;
  pointer-events: none;
}

.day-available {
  color: var(--color-confirmed, #16A34A) !important;
  background: rgba(22,163,74,0.08) !important;
  font-weight: 500;
}

.day-unavailable {
  color: #DC2626 !important;
  background: rgba(220, 38, 38, 0.08) !important;
  font-weight: 500;
}

/* Make date picker span full width */
:deep(.dp__menu) {
  position: fixed !important;
  width: 100vw !important;
  left: 0 !important;
  right: 0 !important;
  margin: 0 !important;
  border-radius: 0 !important;
  max-width: 100vw !important;
}

:deep(.dp__instance_calendar) {
  width: 100% !important;
}

:deep(.dp__calendar) {
  width: 100% !important;
}

:deep(.dp__calendar_header),
:deep(.dp__calendar_row) {
  width: 100% !important;
}

:deep(.dp__calendar_item) {
  flex: 1 !important;
  background: transparent !important;
}

/* AGGRESSIVE: Force remove ALL default blue styles - target the actual cell inner */
:deep(.dp__cell_inner) {
  border: 0 !important;
  box-shadow: none !important;
  outline: none !important;
  background: transparent !important;
  background-color: transparent !important;
}

:deep(.dp__cell_inner):hover {
  background: transparent !important;
  background-color: transparent !important;
}

/* Override the day wrapper to take full control */
:deep(.dp__cell_inner .day) {
  border: none !important;
  position: absolute;
  inset: 0;
  margin: 2px;
}

/* Disable date picker cells while loading */
:deep(.dp__calendar_item.dp__disabled) {
  pointer-events: none;
  cursor: not-allowed;
}

.vue-select-search {
  display: none;
  position: relative;
  margin-top: 6px;
  margin-bottom: 6px;
}
.vue-dropdown .vue-select-search { display: list-item; }
.vue-select-search-icon {
  position: absolute;
  z-index: 1000;
  top: 10px;
  left: 12px;
  color: #7F8CA2;
}
.vue-select-search-input {
  padding-left: 36px;
  padding-right: 16px;
  border-radius: 30px;
  border-color: #fff;
}
.service-select :deep(.vue-dropdown) {
  padding-top: 12px;
  padding-bottom: 12px;
}
:deep(.vue-dropdown) {
  left: 0;
  width: 100%;
}
</style>
