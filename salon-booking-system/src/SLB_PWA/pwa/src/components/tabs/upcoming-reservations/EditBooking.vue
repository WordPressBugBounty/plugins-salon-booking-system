<template>
  <div @click="showTimeslots = false">
    <b-row>
      <b-col sm="12">
        <div class="booking-details-customer-info">
          <b-row>
            <b-col sm="10"></b-col>
            <b-col sm="2" class="actions">
              <font-awesome-icon icon="fa-solid fa-circle-xmark" @click="close"/>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="6">
              <div class="date">
                <span>{{ this.getLabel('dateTitle') }}</span>
                <b-input-group>
                  <template #prepend>
                    <b-input-group-text>
                      <font-awesome-icon icon="fa-solid fa-calendar-days"/>
                    </b-input-group-text>
                  </template>
                  <Datepicker
                      format="yyyy-MM-dd"
                      v-model="elDate"
                      :auto-apply="true"
                      :text-input="true"
                      :hide-input-icon="true"
                      :clearable="false"
                      :class="{required: requiredFields.indexOf('date') > -1}"
                  ></Datepicker>
                </b-input-group>
              </div>
            </b-col>
            <b-col sm="6">
              <div class="time">
                <span>{{ this.getLabel('timeTitle') }}</span>
                <b-input-group>
                  <template #prepend>
                    <b-input-group-text>
                      <font-awesome-icon icon="fa-regular fa-clock"/>
                    </b-input-group-text>
                  </template>
                  <b-form-input v-model="elTime"
                                @click.stop="showTimeslots = !showTimeslots"
                                class="timeslot-input"
                                :class="{required: requiredFields.indexOf('time') > -1}"/>
                  <div class="timeslots" :class="{hide: !this.showTimeslots}" @click.stop>
                                <span v-for="timeslot in timeslots"
                                      :key="timeslot"
                                      class="timeslot"
                                      :class="{free: freeTimeslots.includes(this.moment(timeslot, this.getTimeFormat()).format('HH:mm'))}"
                                      @click="setTime(timeslot)">
                                    {{ timeslot }}
                                </span>
                  </div>
                </b-input-group>
              </div>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="12">
              <div class="select-existing-client">
                <b-button variant="primary" @click="chooseCustomer">
                  <font-awesome-icon icon="fa-solid fa-users"/>
                  {{ this.getLabel('selectExistingClientButtonLabel') }}
                </b-button>
              </div>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="12">
              <div class="customer-firstname">
                <b-form-input :placeholder="this.getLabel('customerFirstnamePlaceholder')" v-model="elCustomerFirstname"
                              :class="{required: requiredFields.indexOf('customer_first_name') > -1}"/>
              </div>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="12">
              <div class="customer-lastname">
                <b-form-input :placeholder="this.getLabel('customerLastnamePlaceholder')" v-model="elCustomerLastname"/>
              </div>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="12">
              <div class="customer-email">
                <b-form-input
                    :type="shouldHideEmail ? 'password' : 'text'"
                    :placeholder="getLabel('customerEmailPlaceholder')"
                    v-model="elCustomerEmail"
                />
              </div>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="12">
              <div class="customer-address">
                <b-form-input :placeholder="this.getLabel('customerAddressPlaceholder')" v-model="elCustomerAddress"/>
              </div>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="12">
              <div class="customer-phone">
                <b-form-input
                    :type="shouldHidePhone ? 'password' : 'tel'"
                    :placeholder="getLabel('customerPhonePlaceholder')"
                    v-model="elCustomerPhone"
                />
              </div>
            </b-col>
          </b-row>
          <b-row>
            <b-col sm="12">
              <div class="customer-notes">
                <b-form-textarea
                    v-model="elCustomerNotes"
                    :placeholder="this.getLabel('customerNotesPlaceholder')"
                    rows="3"
                    max-rows="6"
                ></b-form-textarea>
              </div>
            </b-col>
          </b-row>
          <b-row>
            <div class="save-as-new-customer">
              <b-form-checkbox v-model="saveAsNewCustomer" switch
              >{{ this.getLabel('saveAsNewCustomerLabel') }}
              </b-form-checkbox>
            </div>
          </b-row>
        </div>
      </b-col>
    </b-row>
    <b-row>
      <b-col sm="12">
        <div class="booking-details-extra-info">
          <div class="booking-details-extra-info-header">
            <div class="booking-details-extra-info-header-title">
              {{ this.getLabel('extraInfoLabel') }}
            </div>
            <div>
                            <span
                                class="booking-details-extra-info-header-btn"
                                :class="visibleExtraInfo ? null : 'collapsed'"
                                :aria-expanded="visibleExtraInfo ? 'true' : 'false'"
                                aria-controls="collapse-2"
                                @click="visibleExtraInfo = !visibleExtraInfo"
                            >
                                <font-awesome-icon icon="fa-solid fa-circle-chevron-down" v-if="!visibleExtraInfo"/>
                                <font-awesome-icon icon="fa-solid fa-circle-chevron-up" v-else/>
                            </span>
            </div>
          </div>
          <b-collapse id="collapse-2" class="mt-2" v-model="visibleExtraInfo">
            <template v-for="field in customFieldsList" :key="field.key">
              <CustomField :field="field" :value="getCustomFieldValue(field.key, field.default_value)"
                           @update="updateCustomField"/>
            </template>
            <b-row class="field">
              <b-col sm="12">
                <div class="customer-personal-notes">
                  <label class="label" for="customer_personal_notes">{{
                      this.getLabel('customerPersonalNotesLabel')
                    }}</label>
                  <b-form-textarea
                      v-model.lazy="elCustomerPersonalNotes"
                      id="customer_personal_notes"
                      :placeholder="this.getLabel('customerPersonalNotesPlaceholder')"
                      rows="3"
                      max-rows="6"
                  ></b-form-textarea>
                </div>
              </b-col>
            </b-row>
          </b-collapse>
        </div>
      </b-col>
    </b-row>
    <b-row>
      <b-col sm="12">
        <div class="booking-details-total-info">
          <template v-if="!isLoadingServicesAssistants">
            <b-row v-for="(service, index) in elServices" :key="index" class="service-row">
              <b-col sm>
                <div class="service">
                  <vue-select ref="select-service" class="service-select" close-on-select v-model="service.service_id"
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
                      <template v-else>{{ this.getLabel('selectServicesPlaceholder') }}</template>
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
                    <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="vue-select-search-icon"/>
                    <b-form-input v-model="serviceSearch[index]" class="vue-select-search-input"
                                  :placeholder="this.getLabel('selectServicesSearchPlaceholder')"
                                  @mousedown.stop></b-form-input>
                  </li>
                </div>
              </b-col>
              <b-col sm v-if="isShowResource(service)">
                <div class="resource">
                  <vue-select ref="select-resource" class="service-select" close-on-select v-model="service.resource_id"
                              :options="getAttendantsOrResourcesListBySearch(resourcesList, resourceSearch[index])"
                              label-by="text" value-by="value"
                              :class="{required: requiredFields.indexOf('services_assistant_' + index) > -1}"
                              @focus="loadAvailabilityResources(service.service_id)">
                    <template #label="{ selected }">
                      <template v-if="selected">
                        <div class="option-item option-item-selected">
                          <div class="name">
                            <span>{{ selected.text }}</span>
                          </div>
                        </div>
                      </template>
                      <template v-else>{{ this.getLabel('selectResourcesPlaceholder') }}</template>
                    </template>
                    <template #dropdown-item="{ option }">
                      <div class="option-item">
                        <div class="availability-wrapper">
                          <div class="availability" :class="{available: option.available}"></div>
                          <div class="name">
                            {{ option.text }}
                          </div>
                        </div>
                      </div>
                    </template>
                  </vue-select>
                  <li class="vue-select-search">
                    <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="vue-select-search-icon"/>
                    <b-form-input v-model="resourceSearch[index]" class="vue-select-search-input"
                                  :placeholder="this.getLabel('selectResourcesSearchPlaceholder')"
                                  @mousedown.stop></b-form-input>
                  </li>
                </div>
              </b-col>
              <b-col sm v-if="isShowAttendant(service)">
                <div class="attendant">
                  <vue-select ref="select-assistant" class="service-select" close-on-select
                              v-model="service.assistant_id"
                              :options="getAttendantsOrResourcesListBySearch(attendantsList, assistantSearch[index])"
                              label-by="text" value-by="value"
                              :class="{required: requiredFields.indexOf('services_assistant_' + index) > -1}"
                              @focus="loadAvailabilityAttendants(service.service_id)">
                    <template #label="{ selected }">
                      <template v-if="selected">
                        <div class="option-item option-item-selected">
                          <div class="name">
                            <span>{{ selected.text }}</span>
                          </div>
                        </div>
                      </template>
                      <template v-else>{{ this.getLabel('selectAttendantsPlaceholder') }}</template>
                    </template>
                    <template #dropdown-item="{ option }">
                      <div class="option-item">
                        <div class="availability-wrapper">
                          <div class="availability" :class="{available: option.available}"></div>
                          <div class="name">
                            <span>{{ option.text }}</span>
                            <span v-if="option.variable_price">
                                                    <span> [</span>
                                                    <span>{{ option.variable_price }}</span>
                                                    <span v-html="option.currency"></span>
                                                    <span>]</span>
                                                </span>
                          </div>
                        </div>
                      </div>
                    </template>
                  </vue-select>
                  <li class="vue-select-search">
                    <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="vue-select-search-icon"/>
                    <b-form-input v-model="assistantSearch[index]" class="vue-select-search-input"
                                  :placeholder="this.getLabel('selectAssistantsSearchPlaceholder')"
                                  @mousedown.stop></b-form-input>
                  </li>
                </div>
              </b-col>
              <b-col sm="1" class="service-row-delete">
                <font-awesome-icon icon="fa-solid fa-circle-xmark" @click="deleteService(index)"/>
              </b-col>
            </b-row>
          </template>
          <b-row>
            <b-col sm="6" class="add-service-wrapper">
              <div class="add-service">
                <b-button variant="primary" @click="addService" :disabled="isLoadingServicesAssistants">
                  <font-awesome-icon icon="fa-solid fa-plus"/>
                  {{ this.getLabel('addServiceButtonLabel') }}
                </b-button>
                <b-spinner variant="primary" class="selects-loader" v-if="isLoadingServicesAssistants"></b-spinner>
              </div>
              <div class="add-service-required">
                <b-alert :show="requiredFields.indexOf('services') > -1" fade variant="danger">
                  {{ this.getLabel('addServiceMessage') }}
                </b-alert>
              </div>
            </b-col>
          </b-row>
        </div>
      </b-col>
    </b-row>
    <b-row>
      <b-col sm="12">
        <div v-if="showDiscount" class="booking-discount-info">
          <template v-if="!isLoadingDiscounts">
            <b-row v-for="(discount, index) in elDiscounts" :key="index" class="discount-row">
              <b-col sm="5">
                <div class="discount">
                  <vue-select ref="select-discount" class="discount-select" close-on-select
                              v-model="elDiscounts[index]"
                              :options="getDiscountsListBySearch(discountsList, discountSearch[index])"
                              label-by="text" value-by="value">
                    <template #label="{ selected }">
                      <template v-if="selected">
                        <span class="discount-name">{{ selected.text }}</span>
                      </template>
                      <template v-else>{{ this.getLabel('selectDiscountLabel') }}</template>
                    </template>
                    <template #dropdown-item="{ option }">
                      <div class="option-item">
                        <span class="discount-name">{{ option.text }}</span>
                        <div class="info">
                          <span>expires: {{ option.expires }}</span>
                        </div>
                      </div>
                    </template>
                  </vue-select>
                  <li class="vue-select-search">
                    <font-awesome-icon icon="fa-solid fa-magnifying-glass"
                                       class="vue-select-search-icon"/>
                    <b-form-input v-model="discountSearch[index]" class="vue-select-search-input"
                                  :placeholder="this.getLabel('selectDiscountsSearchPlaceholder')"
                                  @mousedown.stop></b-form-input>
                  </li>
                </div>
              </b-col>
              <b-col sm="2" class="discount-row-delete">
                <font-awesome-icon icon="fa-solid fa-circle-xmark" @click="deleteDiscount(index)"/>
              </b-col>
            </b-row>
          </template>
          <b-row>
            <b-col sm="6" class="add-discount-wrapper">
              <div class="add-discount">
                <b-button variant="primary" @click="addDiscount" :disabled="isLoadingDiscounts">
                  <font-awesome-icon icon="fa-solid fa-plus"/>
                  {{ this.getLabel('addDiscountButtonLabel') }}
                </b-button>
                <b-spinner variant="primary" class="selects-loader"
                           v-if="isLoadingDiscounts"></b-spinner>
              </div>
            </b-col>
          </b-row>
        </div>
      </b-col>
    </b-row>
    <b-row>
      <b-col sm="12">
        <div class="booking-details-status-info">
          <b-row>
            <b-col sm="6" class="status">
              <b-form-select v-model="elStatus" :options="statusesList"></b-form-select>
            </b-col>
            <b-col sm="6">
              <b-row>
                <b-col sm="6" class="save-button-wrapper">
                  <b-button variant="primary" @click="save">
                    <font-awesome-icon icon="fa-solid fa-check"/>
                    {{ this.getLabel('saveButtonLabel') }}
                  </b-button>
                </b-col>
                <b-col sm="6" class="save-button-result-wrapper">
                  <b-spinner variant="primary" v-if="isLoading"></b-spinner>
                  <b-alert :show="isSaved" fade variant="success">{{ this.getLabel('savedLabel') }}</b-alert>
                  <b-alert :show="isError" fade variant="danger">{{ errorMessage }}</b-alert>
                                <b-alert :show="!isValid && requiredFields.length > 1" fade variant="danger">{{ this.getLabel('validationMessage') }}</b-alert>
                                <b-alert :show="shopError" fade variant="warning">{{ this.getLabel('selectShopFirstMessage') }}</b-alert>
                </b-col>
              </b-row>
            </b-col>
          </b-row>
        </div>
      </b-col>
    </b-row>
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
    const originalEmail = this.customerEmail;
    const originalPhone = this.customerPhone;

    return {
                shopError: false,
      elDate: this.date,
      elTime: this.timeFormat(this.time),
      elCustomerFirstname: this.customerFirstname,
      elCustomerLastname: this.customerLastname,
      elCustomerEmail: this.shouldHideEmail ? '***@***' : originalEmail,
      elCustomerPhone: this.shouldHidePhone ? '*******' : originalPhone,
      originalCustomerEmail: originalEmail,
      originalCustomerPhone: originalPhone,
      elCustomerAddress: this.customerAddress,
      elCustomerNotes: this.customerNotes,
      elCustomerPersonalNotes: this.customerPersonalNotes,
      elServices: [...this.services].map(s => ({
        service_id: s.service_id, assistant_id: s.assistant_id,
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
    async validateAssistantAvailability(booking) {
      try {
        const existingBookings = await this.getExistingBookings(booking.date);

        for (const newService of booking.services) {
          if (!newService.assistant_id) continue;

          const newServiceData = this.servicesList.find(s => s.value === newService.service_id);
          if (!newServiceData) continue;

          const durationMinutes = this.convertDurationToMinutes(newServiceData.duration);

          const newServiceStartTime = this.moment(`${booking.date} ${booking.time}`, 'YYYY-MM-DD HH:mm');
          const newServiceEndTime = this.moment(newServiceStartTime).add(durationMinutes, 'minutes');

          const relevantBookings = existingBookings.filter(b =>
              b.services.some(s => s.assistant_id === newService.assistant_id)
          );

          for (const existingBooking of relevantBookings) {
            for (const existingService of existingBooking.services) {
              if (existingService.assistant_id !== newService.assistant_id) continue;

              const existingServiceData = this.servicesList.find(s => s.value === existingService.service_id);
              if (!existingServiceData) continue;

              const existingDurationMinutes = this.convertDurationToMinutes(existingServiceData.duration);

              const existingServiceStartTime = this.moment(`${existingBooking.date} ${existingBooking.time}`, 'YYYY-MM-DD HH:mm');
              const existingServiceEndTime = this.moment(existingServiceStartTime).add(existingDurationMinutes, 'minutes');

              if (
                  this.isOverlapping(
                      newServiceStartTime,
                      newServiceEndTime,
                      existingServiceStartTime,
                      existingServiceEndTime
                  )
              ) {
                const assistant = this.attendantsList.find(a => a.value === newService.assistant_id);
                const assistantName = assistant ? assistant.text : this.getLabel('assistantBusyTitle');
                throw new Error(
                    `${assistantName} ` + this.sprintf(
                        this.getLabel('assistantBusyMessage'),
                        existingServiceStartTime.format('HH:mm'),
                        existingServiceEndTime.format('HH:mm')
                    )
                );
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
            ? response.data.items.filter(b => b.id !== this.bookingID)
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
      const booking = {
        date: this.moment(this.elDate).format('YYYY-MM-DD'),
        time: this.moment(this.elTime, this.getTimeFormat()).format('HH:mm'),
        status: this.elStatus,
        customer_id: this.customerID || 0,
        customer_first_name: this.elCustomerFirstname,
        customer_last_name: this.elCustomerLastname,
        customer_email: this.originalCustomerEmail,
        customer_phone: this.originalCustomerPhone,
        customer_address: this.elCustomerAddress,
        services: this.bookingServices,
        discounts: this.elDiscounts,
        note: this.elCustomerNotes,
        customer_personal_note: this.elCustomerPersonalNotes,
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
            this.isLoadingDiscounts = false;
            this.discountSearch = [];
            this.elDiscounts = this.elDiscounts.filter(elDiscount => {
              const dl = this.discountsList.map(discount => discount.value);
              return dl.includes(elDiscount);
            });
            this.elDiscounts.forEach((i, index) => {
              this.addDiscountsSelectSearchInput(index);
            });
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
          .get('assistants', {params: {shop: this.shop ? this.shop.id : null}})
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
.booking-details-customer-info,
.booking-details-total-info,
.booking-discount-info,
.booking-details-status-info,
.booking-details-extra-info {
  border: solid 1px #ccc;
  padding: 20px;
  text-align: left;
  margin-bottom: 20px;
}

.actions {
  text-align: right;
}

.date,
.time,
.customer-firstname,
.customer-lastname,
.customer-email,
.customer-address,
.customer-phone,
.customer-notes,
.service,
.attendant,
.resource,
.discount {
  border-bottom: solid 1px #ccc;
  margin-bottom: 20px;
  padding-bottom: 5px;
}

.booking-details-status-info .row {
  align-items: center;
}

.fa-circle-xmark {
  cursor: pointer;
}

.select-existing-client {
  margin-bottom: 20px;
}

.alert {
  padding: 6px 12px;
  margin-bottom: 0;
}

.spinner-border {
  vertical-align: middle;
}

.discount-row {
  align-items: center;
}

.service-row {
  align-items: baseline;
}

.timeslots {
  width: 50%;
  height: 200px;
  position: absolute;
  z-index: 100000;
  background-color: white;
  top: 40px;
  display: flex;
  border: solid 1px #ccc;
  overflow-y: auto;
  overflow-x: hidden;
  padding: 20px;
  flex-wrap: wrap;
}

.time {
  position: relative;
}

.timeslot {
  padding: 10px;
  color: #dc3545;
  cursor: pointer;
}

.timeslot.free {
  color: #28a745;
}

.timeslots.hide {
  display: none;
}

.timeslot-input {
  width: 100%;
  max-width: 274px;
}

.input-group {
  flex-wrap: nowrap;
}

.form-control option {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

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

.form-switch {
  display: flex;
  justify-content: space-between;
  flex-direction: row-reverse;
  padding-left: 0;
  align-items: center;
}

.form-switch :deep(.form-check-input) {
  width: 3em;
  height: 1.5em;
}

.vue-select-search {
  display: none;
  position: relative;
  margin-top: 10px;
  margin-bottom: 20px;
}

.vue-dropdown .vue-select-search {
  display: list-item;
}

.vue-select-search-icon {
  position: absolute;
  z-index: 1000;
  top: 12px;
  left: 15px;
  color: #7F8CA2;
}

.vue-select-search-input {
  padding-left: 40px;
  padding-right: 20px;
  border-radius: 30px;
  border-color: #fff;
}

.service-select :deep(.vue-dropdown) {
  padding-top: 15px;
  padding-bottom: 15px;
}

.availability-wrapper {
  display: flex;
  align-items: center;
}

.availability {
  width: 10px;
  height: 10px;
  margin-right: 10px;
  background-color: #9F0404;
  border-radius: 10px;
}

.availability.available {
  background-color: #1EAD3F;
}

.service-name {
  font-weight: bold;
}

.required {
  border: solid 1px #9F0404;
}

.add-service-wrapper {
  display: flex;
}

.add-service-required {
  margin-left: 10px;
}

.booking-details-extra-info-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.booking-details-extra-info-header-btn {
  font-size: 22px;
  color: #0d6efd;
}

.selects-loader {
  margin-left: 20px;
}

.save-button-result-wrapper {
  display: flex;
  gap: 8px;
  flex-direction: column;
  align-items: flex-start;
}

@media (max-width: 576px) {
  .status {
    margin-bottom: 10px;
  }

  .timeslot-input {
    max-width: 100%;
  }

  .timeslots {
    width: 100%;
  }

  .service-row,
  .discount-row {
    width: 100%;
    position: relative;
  }

  .service-row-delete {
    position: absolute;
    top: 30%;
    text-align: right;
    right: -20px;
    width: 30px;
  }

  .discount-row-delete {
    position: absolute;
    text-align: right;
    top: 40%;
    right: -20px;
    width: 30px;
  }

  .save-button-wrapper {
    width: 60%;
  }

  .save-button-result-wrapper {
    width: 40%;
    text-align: center;
  }

  :deep(.vue-dropdown) {
    left: -50px;
    width: calc(100vw - 25px);
  }
}
</style>
