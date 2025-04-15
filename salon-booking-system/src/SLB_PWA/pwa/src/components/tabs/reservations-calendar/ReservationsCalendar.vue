<template>
  <div class="reservations-calendar">
    <h5 class="title">{{ this.getLabel("reservationsCalendarTitle") }}</h5>

    <SearchInput
        v-model="search"
        @search="handleSearch"
    />

    <BookingCalendar
        v-model="date"
        :availability-stats="availabilityStats"
        :is-loading="isLoadingTimeslots || !attendantsLoaded"
        @month-year-update="handleMonthYear"
    />

    <SlotsHeadline
        :date="date"
        :settings="$root.settings"
        :attendants="attendants"
        v-model:is-attendant-view="isAttendantView"
    />

    <div class="slots" :class="{ 'slots--assistants': isAttendantView }" ref="slotsContainer">

      <b-spinner variant="primary" v-if="isLoading"/>

      <div v-else-if="isReadyToRender" class="slots-inner">
        <TimeAxis
            :timeslots="timeslots"
            :slot-height="slotHeight"
            :time-format-new="timeFormatNew"
        />
        <div
            class="slots-content"
            ref="dragScrollContainer"
            v-on="dragHandlers"
        >
          <!--with-->
          <template v-if="isAttendantView">
            <AttendantsList
                v-if="attendantsLoaded"
                :attendants="sortedAttendants"
                :column-widths="columnWidths"
                :column-gap="attendantColumnGap"
                :is-hidden="!shouldShowAttendants"
            />

            <div class="bookings-canvas" :style="canvasStyle">
              <AttendantTimeSlots
                  v-if="sortedAttendants.length > 0 && timeslots.length > 0"
                  :sorted-attendants="sortedAttendants"
                  :timeslots="timeslots"
                  :column-widths="columnWidths"
                  :slot-height="slotHeight"
                  :selected-slots="selectedTimeSlots"
                  :processed-bookings="processedBookings"
                  :availability-intervals="availabilityIntervals"
                  v-model:lockedTimeslots="lockedTimeslots"
                  @lock="handleAttendantLock"
                  @unlock="handleAttendantUnlock"
                  @slot-processing="setSlotProcessing"
                  :date="date"
                  :shop="shop"
                  @add="addBookingForAttendant"
              />
              <!-- Bookings display -->
              <template v-for="booking in processedBookings" :key="booking.id + (booking._serviceTime?.start || '')">
                <BookingCard
                    v-if="booking._assistantId"
                    :booking="booking"
                    :style="getBookingStyle(booking)"
                    :class="{ 'booking-card--default-duration': booking._isDefaultDuration }"
                    @deleteItem="deleteItem"
                    @showDetails="showDetails"
                    @viewCustomerProfile="viewCustomerProfile"
                />
              </template>
            </div>
          </template>
          <!--without-->
          <template v-else>
            <div class="bookings-canvas" :style="canvasStyle">

              <TimeSlots
                  :timeslots="timeslots"
                  :slot-style="getTimeSlotLineStyle"
                  :is-locked="isSlotLocked"
                  :is-processing="(start, end) => slotProcessing[`${start}-${end}`]"
                  :active-index="activeSlotIndex"
                  @toggle="toggleSlotActions"
              >
                <template #actions="{ timeSlot, slotIndex }">
                  <SlotActions
                      :time-slot="timeSlot"
                      :index="slotIndex"
                      :timeslots="timeslots"
                      :is-locked="isSlotLocked"
                      :is-available="isAvailable"
                      :has-overlapping="hasOverlappingBookings"
                      :date="date"
                      :shop="shop"
                      @add="addBooking"
                      @lock="handleSlotLock"
                      @unlock="handleSlotUnlock"
                      @update-processing="updateSlotProcessing"
                  />
                </template>
              </TimeSlots>

              <template v-for="booking in bookingsList" :key="booking.id">
                <BookingCard
                    :booking="booking"
                    :style="getBookingStyle(booking)"
                    @deleteItem="deleteItem"
                    @showDetails="showDetails"
                    @viewCustomerProfile="viewCustomerProfile"
                />
              </template>
            </div>
          </template>
        </div>

        <div v-if="showCurrentTimeLine"
             class="current-time-line"
             :style="{ top: currentTimeLinePosition + 'px' }"
        />
      </div>

      <span v-else>{{ this.getLabel("noResultTimeslotsLabel") }}</span>
    </div>

  </div>
</template>

<script>
import TimeAxis from './TimeAxis.vue';
import AttendantsList from './AttendantsList.vue';
import TimeSlots from './TimeSlots.vue';
import SlotActions from './SlotActions.vue';
import SlotsHeadline from './SlotsHeadline.vue';
import SearchInput from './SearchInput.vue';
import BookingCalendar from './BookingCalendar.vue';
import BookingCard from "./BookingCard.vue";
import AttendantTimeSlots from './AttendantTimeSlots.vue';
import mixins from '@/mixin';

export default {
  name: "ReservationsCalendar",
  mixins: [mixins],
  components: {
    TimeAxis,
    AttendantsList,
    TimeSlots,
    SlotActions,
    SlotsHeadline,
    SearchInput,
    BookingCalendar,
    BookingCard,
    AttendantTimeSlots,
  },
  props: {
    modelValue: {
      type: Date,
      default: () => new Date()
    },
    shop: {
      default: function () {
        return {};
      },
    },
  },
  data() {
    return {
      // calendar data
      timeslots: [],
      lockedTimeslots: [],
      availabilityStats: [],
      bookingsList: [],
      availabilityIntervals: {},

      // UI state
      search: "",
      activeSlotIndex: -1,
      currentTimeLinePosition: 0,
      showCurrentTimeLine: true,

      // loading states
      isLoadingTimeslots: false,
      isLoadingCalendar: false,
      isLoading: false,
      loadingQueue: [],

      // layout configuration
      slotHeight: 110,
      cardWidth: 245,
      gap: 0,

      // drag handling
      isDragging: false,
      wasRecentlyDragging: false,
      possibleDrag: false,
      startX: 0,
      startY: 0,
      scrollLeft: 0,

      // timeouts and intervals
      intervalId: null,

      // attendants
      isAttendantView: localStorage.getItem('isAttendantView') === 'true' || false,
      attendantColumnWidth: 245,
      attendantColumnGap: 8,
      attendants: [],
      attendantsLoaded: false,
      timeFormatNew: 'simple',

      // slot data
      slotProcessingStates: new Map(),
      slotProcessing: {},
      selectedTimeSlots: [],
    };
  },
  computed: {
    dragHandlers() {
      return {
        mousedown: this.onMouseDown,
        mousemove: this.onMouseMove,
        mouseup: this.onMouseUp,
        mouseleave: this.onMouseLeave,
        touchstart: this.onTouchStart,
        touchmove: this.onTouchMove,
        touchend: this.onTouchEnd
      }
    },
    date: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit('update:modelValue', value);
      }
    },
    canvasWidth() {
      return this.$refs.dragScrollContainer?.clientWidth ?? 500;
    },
    canvasHeight() {
      return this.timeslots.length * this.slotHeight;
    },
    canvasStyle() {
      if (this.isAttendantView) {
        const totalWidth = this.sortedAttendants.reduce((sum, attendant, index) => {
          const width = this.columnWidths?.[attendant.id] ?? this.attendantColumnWidth;
          const gap = (index < this.sortedAttendants.length - 1) ? this.attendantColumnGap : 0;
          return sum + width + gap;
        }, 0);

        return {
          height: `${this.canvasHeight}px`,
          width: `${totalWidth}px`,
          minWidth: `${totalWidth}px`
        };
      }

      const dynamicWidth = Math.max(
          this.bookingsList.length * (this.cardWidth + this.gap),
          this.canvasWidth
      );

      return {
        height: `${this.canvasHeight}px`,
        width: `${dynamicWidth}px`,
        minWidth: 'calc(100% + 245px)'
      };
    },
    processedBookings() {
      if (!this.isAttendantView) {
        return [...this.bookingsList];
      }
      return this.bookingsList.flatMap(booking => {
        if (!booking.services || booking.services.length === 0) {
          return [{
            ...booking,
            _serviceTime: {
              start: booking.time,
              end: this.calculateEndTime(booking.time, this.getDefaultDuration(booking))
            },
            _assistantId: 0,
            _isDefaultDuration: true
          }];
        }
        const servicesByAssistant = booking.services.reduce((acc, service) => {
          const assistantId = service.assistant_id || 0;
          if (!acc[assistantId]) {
            acc[assistantId] = [];
          }
          acc[assistantId].push(service);
          return acc;
        }, {});
        return Object.entries(servicesByAssistant).map(([assistantId, services]) => {
          const sortedServices = [...services].sort((a, b) => {
            const aStart = this.getMinutes(a.start_at || booking.time);
            const bStart = this.getMinutes(b.start_at || booking.time);
            return aStart - bStart;
          });
          const firstService = sortedServices[0];
          const lastService = sortedServices[sortedServices.length - 1];
          return {
            ...booking,
            services: sortedServices,
            _serviceTime: {
              start: firstService.start_at || booking.time,
              end: lastService.end_at || this.calculateEndTime(lastService.start_at || booking.time, this.getDefaultDuration(booking))
            },
            _assistantId: parseInt(assistantId),
            _isDefaultDuration: !lastService.end_at
          };
        });
      });
    },
    sortedAttendants() {
      if (!Array.isArray(this.attendants) || this.attendants.length === 0) return [];
      const bookingsMap = new Map();
      this.bookingsList.forEach((booking) => {
        if (booking.services) {
          booking.services.forEach((service) => {
            if (service.assistant_id) {
              if (!bookingsMap.has(service.assistant_id)) {
                bookingsMap.set(service.assistant_id, []);
              }
              bookingsMap.get(service.assistant_id).push(booking);
            }
          });
        }
      });
      return [...this.attendants].sort((a, b) => {
        const aHas = bookingsMap.has(a.id);
        const bHas = bookingsMap.has(b.id);

        if (aHas && !bHas) return -1;
        if (!aHas && bHas) return 1;
        if (aHas && bHas) {
          return bookingsMap.get(b.id).length - bookingsMap.get(a.id).length;
        }
        return a.name.localeCompare(b.name);
      });
    },
    shouldShowAttendants() {
      return this.isAttendantView && this.attendants && this.attendants.length > 0;
    },
    columnWidths() {
      if (!this.isAttendantView) return {};

      const widths = {};
      this.sortedAttendants.forEach((attendant) => {
        const timeSlotMap = new Map();
        const attendantBookings = this.processedBookings.filter(b => b._assistantId === attendant.id);

        attendantBookings.forEach(booking => {
          if (!booking._serviceTime) return;
          const startTime = this.getMinutes(booking._serviceTime.start);
          const realDuration = this.getMinutes(booking._serviceTime.end) - startTime;
          const displayDuration = this.getDisplayDuration(booking, realDuration);
          const endTime = startTime + displayDuration;

          for (let time = startTime; time < endTime; time++) {
            const currentCount = timeSlotMap.get(time) || 0;
            timeSlotMap.set(time, currentCount + 1);
          }
        });

        const maxConcurrent = timeSlotMap.size > 0 ? Math.max(...timeSlotMap.values()) : 1;
        widths[attendant.id] = (this.cardWidth * maxConcurrent) + (this.attendantColumnGap * (maxConcurrent - 1));

      });

      return widths;
    },
    isReadyToRender() {
      if (this.bookingsList.length > 0 && this.timeslots.length > 0 && this.availabilityIntervals.length > 0) {
        this.bookingsList.forEach(booking => {
          let bookingTime = booking.time;
          if (!this.timeslots.includes(bookingTime) && bookingTime < this.timeslots[0]) {
            this.timeslots.unshift(bookingTime);
          }
        });
      }

      if (this.isAttendantView) {
        if (!this.attendantsLoaded) {
          return false;
        }

        if (this.attendants.length === 0) {
          return false;
        }

        if (!this.availabilityIntervals ||
            Object.keys(this.availabilityIntervals).length === 0) {
          return false;
        }
      }

      return !this.isLoadingTimeslots &&
          this.attendantsLoaded &&
          this.timeslots.length > 0;
    },
    validatedHolidayRule() {
      return (rule) => {
        if (!rule || typeof rule !== 'object') return false;
        if (!rule.from_date || !rule.to_date) return false;
        if (!rule.from_time || !rule.to_time) return false;

        return this.moment(rule.from_date, 'YYYY-MM-DD').isValid() &&
            this.moment(rule.to_date, 'YYYY-MM-DD').isValid() &&
            /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(rule.from_time) &&
            /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(rule.to_time);
      };
    }
  },
  watch: {
    shop: {
      handler(newVal, oldVal) {
        if (newVal?.id !== oldVal?.id) {
          this.activeSlotIndex = -1;
          this.loadAllData();
        }
      },
      deep: true
    },
    bookingsList() {
      this.arrangeBookings();
      this.$nextTick(() => {
        this.$forceUpdate();
      });
    },
    attendantsLoaded(newVal) {
      if (newVal) {
        this.$nextTick(() => {
          this.$forceUpdate();
        });
      }
    },
    "$root.settings": {
      handler(newSettings) {
        if (newSettings?.attendant_enabled) {
          this.loadAttendants();
        } else {
          this.attendantsLoaded = true;
          this.isAttendantView = false;
        }
        this.timeFormatNew = newSettings?.time_format.js_format === 'H:iip' ? 'am' : 'simple';
        this.dateFormat = newSettings?.date_format || 'YYYY-MM-DD';
      },
      deep: true
    },
    isAttendantView(newValue) {
      localStorage.setItem('isAttendantView', newValue);
      this.loadAllData();
    },
    date(newVal, oldVal) {
      if (newVal.getTime() !== oldVal?.getTime()) {
        this.loadAllData();
      }
    },
  },
  mounted() {
    this.loadAllData();

    setTimeout(() => {
      const cals = window.document.querySelectorAll(".dp__calendar");
      if (cals[0]) {
        const spinWrap = window.document.querySelectorAll(".spinner-wrapper")[0];
        const spinBorder = window.document.querySelectorAll(".calendar .spinner-border")[0];
        if (spinWrap) cals[0].appendChild(spinWrap);
        if (spinBorder) cals[0].appendChild(spinBorder);
      }
    }, 0);

    setInterval(() => this.update(), 60000);
    this.intervalId = setInterval(() => {
      this.updateCurrentTimeLinePosition();
    }, 60000);

    this.$nextTick(() => {
      this.updateCurrentTimeLinePosition();
      const container = this.$refs.dragScrollContainer;
      if (container) {
        container.addEventListener("touchmove", this.onTouchMove, {passive: false});
      }
    });

    if (this.$refs.slotsContainer) {
      this.$refs.slotsContainer.addEventListener("click", (e) => {
        if (e.target === this.$refs.slotsContainer) {
          this.handleOutsideClick();
        }
      });
    }
  },
  beforeUnmount() {
    if (this.intervalId) clearInterval(this.intervalId);
    const container = this.$refs.dragScrollContainer;
    if (container) {
      container.removeEventListener("touchmove", this.onTouchMove);
    }
    if (this.$refs.slotsContainer) {
      this.$refs.slotsContainer.removeEventListener("click", this.handleOutsideClick);
    }
  },
  methods: {
    loadAllData() {
      this.cancelPendingLoads();
      this.isLoading = true;

      const loadSettings = () => {
        if (this.shop?.id) {
          return this.axios.get('app/settings', {
            params: {shop: this.shop.id}
          }).then(response => {
            if (response.data?.settings) {
              this.$root.settings = response.data.settings;
            }
            return response;
          });
        } else {
          return this.axios.get('app/settings').then(response => {
            if (response.data?.settings) {
              this.$root.settings = response.data.settings;
            }
            return response;
          });
        }
      };

      loadSettings()
          .then(() => this.loadTimeslots())
          .then(() => {
            const additionalTasks = [
              this.loadLockedTimeslots(),
              this.loadBookingsList(),
              this.loadAvailabilityIntervals()
            ];

            const d = this.date;
            const y = d.getFullYear();
            const m = d.getMonth();
            const firstDate = new Date(y, m, 1);
            const lastDate = new Date(y, m + 1, 0);

            additionalTasks.push(this.loadAvailabilityStats(firstDate, lastDate));

            if (this.isAttendantView && this.$root.settings?.attendant_enabled && !this.attendantsLoaded) {
              additionalTasks.push(this.loadAttendants());
            }

            this.loadingQueue = additionalTasks;

            return Promise.all(additionalTasks);
          })
          .then(() => {
            this.$nextTick(() => {
              this.arrangeBookings();
              this.$forceUpdate();
            });
          })
          .catch(error => {
            console.error('Error loading calendar data:', error);
          })
          .finally(() => {
            this.isLoading = false;
          });
    },
    cancelPendingLoads() {
      this.loadingQueue = [];
    },
    async loadTimeslots() {
      this.isLoadingTimeslots = true;
      return this.axios.get('calendar/intervals', {
        params: {shop: this.shop?.id || null},
      }).then(response => {
        this.timeslots = response.data.items || [];
        this.updateCurrentTimeLinePosition();
        return response;
      }).catch(error => {
        console.error('Error loading timeslots:', error);
        throw error;
      }).finally(() => {
        this.isLoadingTimeslots = false;
      });
    },
    async loadLockedTimeslots() {
      try {
        const salonRulesResponse = await this.axios.get('holiday-rules', {
          params: {
            assistants_mode: 'false',
            date: this.moment(this.date).format('YYYY-MM-DD'),
          },
        });

        let salonRules = [];
        if (salonRulesResponse.data?.status === 'OK') {
          salonRules = salonRulesResponse.data.items || [];
        }

        if (this.isAttendantView) {
          const assistantsResponse = await this.axios.get('holiday-rules', {
            params: {
              assistants_mode: 'true',
              date: this.moment(this.date).format('YYYY-MM-DD'),
            },
          });

          if (assistantsResponse.data?.status === 'OK') {
            const assistantsRules = assistantsResponse.data.assistants_rules || {};
            const formattedAssistantRules = Object.entries(assistantsRules).flatMap(([assistantId, rules]) =>
                rules.map(rule => ({
                  ...rule,
                  assistant_id: Number(assistantId) || null,
                }))
            );

            const formattedSalonRules = salonRules.map(rule => ({
              ...rule,
              assistant_id: null,
            }));

            this.lockedTimeslots = [...formattedSalonRules, ...formattedAssistantRules];
          }
        } else {
          this.lockedTimeslots = salonRules;
        }

        return {data: {status: 'OK'}};
      } catch (error) {
        console.error('Error loading locked timeslots:', error.response?.data || error.message);
        throw error;
      }
    },
    async loadBookingsList() {
      return this.axios.get('bookings', {
        params: {
          start_date: this.moment(this.date).format('YYYY-MM-DD'),
          end_date: this.moment(this.date).format('YYYY-MM-DD'),
          per_page: -1,
          statuses: [
            'sln-b-pendingpayment', 'sln-b-pending', 'sln-b-paid',
            'sln-b-paylater',
            'sln-b-confirmed',
          ],
          shop: this.shop?.id || null,
        },
      }).then(response => {
        const newBookings = response.data.items || [];
        const newBookingsMap = new Map(newBookings.map(b => [b.id, b]));
        this.bookingsList = [];
        this.bookingsList = this.bookingsList.map(existingBooking =>
            newBookingsMap.has(existingBooking.id)
                ? {...existingBooking, ...newBookingsMap.get(existingBooking.id)}
                : existingBooking
        );

        newBookings.forEach(newBooking => {
          if (!this.bookingsList.some(existing => existing.id === newBooking.id)) {
            this.bookingsList.push(newBooking);
          }
        });
        return response;
      }).catch(error => {
        console.error('Error loading bookings list:', error);
        throw error;
      });
    },
    updateSlotProcessing({slot, status}) {
      this.slotProcessing = {
        ...this.slotProcessing,
        [slot]: status,
      };
    },
    handleAttendantLock(payload) {
      console.log('Lock payload:', payload);
    },
    handleAttendantUnlock(payload) {
      console.log('Unlock payload:', payload);
    },
    async loadAvailabilityStats(fd, td) {
      this.isLoadingCalendar = true;
      try {
        const response = await this.axios.get("availability/stats", {
          params: {
            from_date: this.moment(fd).format("YYYY-MM-DD"),
            to_date: this.moment(td).format("YYYY-MM-DD"),
            shop: this.shop?.id || null
          },
        });
        this.availabilityStats = response.data.stats;
        return response;
      } catch (error) {
        console.error('Error loading availability stats:', error);
        throw error;
      } finally {
        this.isLoadingCalendar = false;
      }
    },
    async loadAvailabilityIntervals() {
      const timeParam = this.timeslots.length > 0 ? this.timeslots[0] : '09:00';

      try {
        const response = await this.axios.post("availability/intervals", {
          date: this.moment(this.date).format("YYYY-MM-DD"),
          time: timeParam,
          shop: this.shop?.id || 0
        });
        this.availabilityIntervals = response.data.intervals;
        return response;
      } catch (error) {
        console.error('Error loading availability intervals:', error);
        throw error;
      }
    },
    async loadAttendants() {
      try {
        const response = await this.axios.get("assistants", {
          params: {shop: this.shop?.id || null, per_page: -1}
        });
        this.attendants = response.data.items;
        this.attendantsLoaded = true;
        return response;
      } catch (error) {
        console.error("Error loading attendants:", error);
        this.attendantsLoaded = true;
        throw error;
      }
    },
    update() {
      return this.loadBookingsList();
    },
    addBookingForAttendant({timeslot, attendantId}) {
      const selectedDate = this.modelValue;
      this.$emit("add", selectedDate, timeslot, attendantId);
    },
    handleSearch(value) {
      this.activeSlotIndex = -1;
      if (value) {
        this.loadFilteredBookings(value);
      } else {
        this.loadBookingsList();
      }
    },
    async loadFilteredBookings(searchQuery) {
      this.isLoadingTimeslots = true;
      this.bookingsList = [];
      const currentView = this.isAttendantView;

      try {
        const response = await this.axios.get("bookings", {
          params: {
            start_date: this.moment(this.date).format("YYYY-MM-DD"),
            end_date: this.moment(this.date).format("YYYY-MM-DD"),
            search: searchQuery,
            per_page: -1,
            statuses: [
              "sln-b-pendingpayment",
              "sln-b-pending",
              "sln-b-paid",
              "sln-b-paylater",
              "sln-b-confirmed",
            ],
            shop: this.shop?.id || null,
          },
        });

        this.bookingsList = response.data.items;
        this.arrangeBookings();
        this.isAttendantView = currentView;
      } finally {
        this.isLoadingTimeslots = false;
      }
    },
    handleSlotLock(holidayRule) {
      // validate holiday rule format
      if (!this.validatedHolidayRule(holidayRule)) return;

      // check if this exact rule already exists
      const exists = this.lockedTimeslots.some(locked =>
          locked.from_time === holidayRule.from_time &&
          locked.to_time === holidayRule.to_time &&
          locked.from_date === holidayRule.from_date &&
          locked.to_date === holidayRule.to_date &&
          locked.assistant_id === holidayRule.assistant_id
      );

      // add rule if it doesn't exist yet
      if (!exists) {
        this.lockedTimeslots = [...this.lockedTimeslots, holidayRule];
      } else {
        console.warn("slot is already locked!");
      }
    },
    handleSlotUnlock(holidayRule) {
      // filter out the rule that matches the parameters
      const newLockedTimeslots = this.lockedTimeslots.filter(locked =>
          !(locked.from_time === holidayRule.from_time &&
              locked.to_time === holidayRule.to_time &&
              locked.from_date === holidayRule.from_date &&
              locked.to_date === holidayRule.to_date &&
              locked.assistant_id === holidayRule.assistant_id)
      );

      // update the locked timeslots array
      if (newLockedTimeslots.length > 0) {
        this.lockedTimeslots = newLockedTimeslots;
      } else {
        console.warn("no locked slots left, keeping last state");
        this.lockedTimeslots = [...this.lockedTimeslots];
      }
    },
    handleMonthYear({year, month}) {
      const fd = new Date(year, month, 1);
      const td = new Date(year, month + 1, 0);
      this.loadAvailabilityStats(fd, td);
    },
    isSlotLocked(currentSlot) {
      try {
        // if no availability data, consider slot as available
        if (!this.availabilityIntervals || Object.keys(this.availabilityIntervals).length === 0) {
          return false;
        }

        const formattedDate = this.moment(this.date).format("YYYY-MM-DD");
        const currentDate = this.moment(formattedDate, "YYYY-MM-DD");

        // check if salon is open on this day of week
        const weekday = currentDate.day() + 1; // 1=Sunday, 2=Monday...
        if (this.$root.settings?.available_days &&
            !this.$root.settings.available_days[weekday]) {
          return true; // ==> salon is closed on this day
        }

        const slotMinutes = this.timeToMinutes(currentSlot);

        // check if slot is in salon holiday period
        const holidayPeriod = this.$root.settings?.holidays?.find(holiday => {
          if (!holiday || !holiday.from_date || !holiday.to_date) return false;

          const holidayFromDate = this.moment(holiday.from_date, "YYYY-MM-DD");
          const holidayToDate = this.moment(holiday.to_date, "YYYY-MM-DD");

          if (currentDate.isSameOrAfter(holidayFromDate, 'day') &&
              currentDate.isSameOrBefore(holidayToDate, 'day')) {

            // handle single-day holiday (same start and end date)
            if (currentDate.isSame(holidayFromDate, 'day') && currentDate.isSame(holidayToDate, 'day')) {
              const fromTimeMinutes = this.timeToMinutes(holiday.from_time);
              const toTimeMinutes = this.timeToMinutes(holiday.to_time);
              return slotMinutes >= fromTimeMinutes && slotMinutes < toTimeMinutes;
            }
            // first day of multi-day holiday
            else if (currentDate.isSame(holidayFromDate, 'day')) {
              const fromTimeMinutes = this.timeToMinutes(holiday.from_time);
              return slotMinutes >= fromTimeMinutes;
            }
            // last day of multi-day holiday
            else if (currentDate.isSame(holidayToDate, 'day')) {
              const toTimeMinutes = this.timeToMinutes(holiday.to_time);
              return slotMinutes < toTimeMinutes;
            }
            // day in between first and last days
            else {
              return true; // ==> entire day is blocked
            }
          }

          return false;
        });
        if (holidayPeriod) return true;

        // check if slot is in daily holiday rule
        const dailyHoliday = this.$root.settings?.holidays_daily?.find(rule => {
          if (rule.assistant_id !== null) return false;

          if (formattedDate === rule.from_date && formattedDate === rule.to_date) {
            const ruleFromTime = this.normalizeTime(rule.from_time);
            const ruleToTime = this.normalizeTime(rule.to_time);
            const ruleFromMinutes = this.timeToMinutes(ruleFromTime);
            const ruleToMinutes = this.timeToMinutes(ruleToTime);

            return slotMinutes >= ruleFromMinutes && slotMinutes < ruleToMinutes;
          }
          return false;
        });
        if (dailyHoliday) return true;

        // check if slot is in manual locks (holiday rules)
        const dayHoliday = this.lockedTimeslots.find(rule => {
          const currentDate = this.moment(formattedDate, "YYYY-MM-DD").startOf('day');
          const fromDate = this.moment(rule.from_date, "YYYY-MM-DD").startOf('day');
          const toDate = this.moment(rule.to_date, "YYYY-MM-DD").startOf('day');

          // handle different date scenarios for manual locks
          if (currentDate.isSame(fromDate, 'day') && currentDate.isSame(toDate, 'day')) {
            const ruleFromMinutes = this.timeToMinutes(rule.from_time);
            const ruleToMinutes = this.timeToMinutes(rule.to_time);
            return slotMinutes >= ruleFromMinutes && slotMinutes < ruleToMinutes;
          } else if (currentDate.isSame(fromDate, 'day')) {
            const ruleFromMinutes = this.timeToMinutes(rule.from_time);
            return slotMinutes >= ruleFromMinutes;
          } else if (currentDate.isSame(toDate, 'day')) {
            const ruleToMinutes = this.timeToMinutes(rule.to_time);
            return slotMinutes < ruleToMinutes;
          } else if (currentDate.isAfter(fromDate, 'day') && currentDate.isBefore(toDate, 'day')) {
            return true;
          }

          return false;
        });
        if (dayHoliday) return true;

        // check salon working hours
        const availabilities = this.$root.settings?.availabilities || [];
        if (availabilities.length > 0) {
          // find rule for current day
          const availabilityRule = availabilities.find(rule =>
              rule.days && rule.days[weekday]
          );

          if (availabilityRule) {
            const shifts = availabilityRule.shifts || [];
            // check if slot is in any of the salon's shifts
            const isInShift = shifts.some(shift => {
              if (!shift.from || !shift.to || shift.disabled) return false;

              const shiftFromMinutes = this.timeToMinutes(shift.from);
              const shiftToMinutes = this.timeToMinutes(shift.to);

              return slotMinutes >= shiftFromMinutes && slotMinutes < shiftToMinutes;
            });
            if (!isInShift) return true; // ==> not in salon working hours
          } else {
            return true;
          }
        }

        // check API-provided available times
        const workTimes = this.availabilityIntervals?.workTimes || {};
        const times = this.availabilityIntervals?.times || {};
        const allowedTimes = Object.keys(workTimes).length ? workTimes : times;

        const currentSlotMinutes = this.timeToMinutes(currentSlot);

        const isAllowed = Object.values(allowedTimes).some(time => {
          const timeMinutes = this.timeToMinutes(time);
          return currentSlotMinutes === timeMinutes;
        });

        return !isAllowed;
      } catch (error) {
        console.error('error isSlotLocked:', error);
        return true;
      }
    },
    isAvailable(start) {
      if (!this.availabilityIntervals || Object.keys(this.availabilityIntervals).length === 0) {
        return true;
      }

      const formattedDate = this.moment(this.date).format("YYYY-MM-DD");
      const currentDate = this.moment(formattedDate, "YYYY-MM-DD");

      // check if day is in salon working days
      const weekday = currentDate.day() + 1;
      if (this.$root.settings?.available_days &&
          !this.$root.settings.available_days[weekday]) {
        return false; // ==> salon is closed on this day
      }

      const slotMinutes = this.timeToMinutes(start);

      // check salon holiday periods
      const holidayPeriod = this.$root.settings?.holidays?.find(holiday => {
        if (!holiday || !holiday.from_date || !holiday.to_date) return false;

        const holidayFromDate = this.moment(holiday.from_date, "YYYY-MM-DD");
        const holidayToDate = this.moment(holiday.to_date, "YYYY-MM-DD");

        if (currentDate.isSameOrAfter(holidayFromDate, 'day') &&
            currentDate.isSameOrBefore(holidayToDate, 'day')) {

          // handle single-day holiday
          if (currentDate.isSame(holidayFromDate, 'day') && currentDate.isSame(holidayToDate, 'day')) {
            const fromTimeMinutes = this.timeToMinutes(holiday.from_time);
            const toTimeMinutes = this.timeToMinutes(holiday.to_time);
            return slotMinutes >= fromTimeMinutes && slotMinutes < toTimeMinutes;
          }
          // first day of multi-day holiday
          else if (currentDate.isSame(holidayFromDate, 'day')) {
            const fromTimeMinutes = this.timeToMinutes(holiday.from_time);
            return slotMinutes >= fromTimeMinutes;
          }
          // last day of multi-day holiday
          else if (currentDate.isSame(holidayToDate, 'day')) {
            const toTimeMinutes = this.timeToMinutes(holiday.to_time);
            return slotMinutes < toTimeMinutes;
          }

          return true; // ==> day between start and end dates
        }

        return false;
      });
      if (holidayPeriod) return false; // ==> slot not available during holiday

      // check daily holiday rules
      const dailyHoliday = this.$root.settings?.holidays_daily?.find(rule => {
        if (rule.assistant_id !== null) return false;

        if (formattedDate === rule.from_date && formattedDate === rule.to_date) {
          const ruleFromTime = this.normalizeTime(rule.from_time);
          const ruleToTime = this.normalizeTime(rule.to_time);
          const ruleFromMinutes = this.timeToMinutes(ruleFromTime);
          const ruleToMinutes = this.timeToMinutes(ruleToTime);

          return slotMinutes >= ruleFromMinutes && slotMinutes < ruleToMinutes;
        }
        return false;
      });
      if (dailyHoliday) return false; // ==> slot not available during daily holiday

      // check manual locks
      const dayHoliday = this.lockedTimeslots.find(rule => {
        if (formattedDate >= rule.from_date && formattedDate <= rule.to_date) {
          if (formattedDate === rule.from_date) {
            return slotMinutes >= this.timeToMinutes(rule.from_time);
          } else if (formattedDate === rule.to_date) {
            return slotMinutes < this.timeToMinutes(rule.to_time);
          } else {
            return true;
          }
        }
        return false;
      });
      if (dayHoliday) return false; // ==> slot not available during locked period

      // check if day is marked as full holiday in stats
      const fullDayHoliday = this.availabilityStats.find(
          stat => stat.date === formattedDate && stat.error?.type === "holiday_rules"
      );
      if (fullDayHoliday) return false; // ==> full day holiday

      // check salon shifts
      const availabilities = this.$root.settings?.availabilities || [];
      if (availabilities.length > 0) {
        const availabilityRule = availabilities.find(rule =>
            rule.days && rule.days[weekday]
        );

        if (availabilityRule) {
          const shifts = availabilityRule.shifts || [];
          // check if slot is in any working shift
          const isInShift = shifts.some(shift => {
            if (!shift.from || !shift.to || shift.disabled) return false;

            const shiftFromMinutes = this.timeToMinutes(shift.from);
            const shiftToMinutes = this.timeToMinutes(shift.to);

            return slotMinutes >= shiftFromMinutes && slotMinutes < shiftToMinutes;
          });

          if (!isInShift) return false; // ==> not in any working shift
        } else {
          return false; // ==> no rule for this day
        }
      }

      // final check against API-provided allowed times
      const workTimes = this.availabilityIntervals?.workTimes || {};
      const times = this.availabilityIntervals?.times || {};
      const allowedTimes = Object.keys(workTimes).length ? workTimes : times;

      const startMinutes = this.timeToMinutes(start);

      return Object.values(allowedTimes).some(time => {
        const timeMinutes = this.timeToMinutes(time);
        return startMinutes === timeMinutes;
      });
    },
    normalizeTime(time) {
      if (!time) return time;

      if (this.$root.settings?.time_format?.js_format === 'h:iip') {
        const momentTime = this.moment(time, 'h:mm A');
        return momentTime.format('HH:mm');
      }

      const momentFormat = this.getTimeFormat();
      return this.moment(time, momentFormat).format('HH:mm');
    },
    timeToMinutes(time) {
      if (!time) return 0;
      const [hours, minutes] = time.split(':').map(Number);
      return hours * 60 + minutes;
    },
    setSlotProcessing(slotKey, isProcessing) {
      if (isProcessing) {
        this.slotProcessingStates.set(slotKey, true);
      } else {
        this.slotProcessingStates.delete(slotKey);
      }
    },
    toggleSlotActions(idx) {
      if (!this.isDragging && !this.wasRecentlyDragging) {
        this.activeSlotIndex = this.activeSlotIndex === idx ? -1 : idx;
      }
    },
    addBooking(timeslot) {
      const selectedDate = this.modelValue;
      const selectedTime = timeslot || this.timeslots[0];
      this.$emit("add", selectedDate, selectedTime);
    },
    deleteItem(id) {
      this.axios.delete("bookings/" + id).then(() => {
        this.bookingsList = this.bookingsList.filter((item) => item.id !== id);
      });
    },
    showDetails(booking) {
      this.$emit("showItem", booking);
    },
    updateCurrentTimeLinePosition() {
      if (!this.timeslots || !this.timeslots.length) {
        this.showCurrentTimeLine = false;
        return;
      }
      const startTime = this.timeslots[0];
      const endTime = this.timeslots[this.timeslots.length - 1];
      const now = this.moment();

      const opening = this.moment(startTime, "HH:mm").set({
        year: now.year(),
        month: now.month(),
        date: now.date()
      });
      let closing = this.moment(endTime, "HH:mm").set({
        year: now.year(),
        month: now.month(),
        date: now.date()
      });
      if (closing.isBefore(opening)) {
        closing.add(1, "day");
      }

      if (now.isBefore(opening)) {
        this.currentTimeLinePosition = 0;
        this.showCurrentTimeLine = true;
        return;
      }

      if (now.isAfter(closing)) {
        this.currentTimeLinePosition = this.timeslots.length * this.slotHeight - 2;
        this.showCurrentTimeLine = true;
        return;
      }

      const slotDuration = this.calcSlotStep();
      const minutesSinceOpening = now.diff(opening, "minutes");
      const position = (minutesSinceOpening / slotDuration) * this.slotHeight;

      this.currentTimeLinePosition = Math.max(
          0,
          Math.min(position, this.timeslots.length * this.slotHeight)
      );
      this.showCurrentTimeLine = true;
    },
    arrangeBookings() {
      if (!Array.isArray(this.bookingsList)) return;

      this.columns = [];
      const sorted = [...this.bookingsList].sort((a, b) => {
        const aStart = this.getBookingStart(a);
        const bStart = this.getBookingStart(b);
        return aStart - bStart;
      });


      sorted.forEach((booking) => {
        if (booking) {
          booking._column = this.findFreeColumn(booking);
        }
      });
      if (document.querySelector('.dp__active_date.dp__today') !== null) {
        if (document.querySelector('.current-time-line') !== null) {
          document.querySelector('.current-time-line').style.display = 'block';
          document.querySelector('.current-time-line').scrollIntoView({behavior: 'smooth', block: 'center'})
        }
      } else {
        if (document.querySelector('.current-time-line') !== null)
          document.querySelector('.current-time-line').style.display = 'none';
      }
    },
    findFreeColumn(booking) {

      for (let column = 0; column < this.columns.length; column++) {
        if (!this.doesOverlapColumn(booking, this.columns[column])) {
          this.columns[column].push(booking);
          return column;
        }
      }

      this.columns.push([booking]);
      return this.columns.length - 1;
    },
    doesOverlapColumn(newBooking, columnBookings) {
      const newStart = this.getBookingStart(newBooking);
      const newEnd = this.getBookingEnd(newBooking);

      return columnBookings.some(existingBooking => {
        const existingStart = this.getBookingStart(existingBooking);
        const existingEnd = this.getBookingEnd(existingBooking);
        return (newStart < existingEnd && newEnd > existingStart);
      });
    },
    hasOverlappingBookings(slotIndex) {
      const slotStart = this.getMinutes(this.timeslots[slotIndex]);
      const slotEnd = (slotIndex + 1 < this.timeslots.length)
          ? this.getMinutes(this.timeslots[slotIndex + 1])
          : slotStart + this.calcSlotStep();

      return this.bookingsList.some((booking) => {
        const bookingStart = this.getBookingStart(booking);
        const bookingEnd = this.getBookingEnd(booking);
        return bookingStart < slotEnd && bookingEnd > slotStart;
      });
    },
    calcSlotStep() {
      if (!this.timeslots || this.timeslots.length < 2) return 30;
      const t1 = this.getMinutes(this.timeslots[0]);
      const t2 = this.getMinutes(this.timeslots[1]);
      return t2 - t1;
    },
    getBookingStart(booking) {
      if (!booking || !booking.time) return 0;
      return this.getMinutes(booking.time);
    },
    getBookingEnd(booking) {
      if (!booking) return 0;

      let realEndTime = booking.time;
      if (booking.services?.length) {
        const lastService = booking.services[booking.services.length - 1];
        realEndTime = lastService.end_at || booking.time;
      }

      const startMin = this.getMinutes(booking.time);
      const endMin = this.getMinutes(realEndTime);
      const realDuration = endMin - startMin;

      const duration = this.getDisplayDuration(booking, realDuration);

      return startMin + duration;
    },
    getBookingStyle(booking) {
      const openStr = this.timeslots[0];
      const openMin = this.getMinutes(openStr);
      let startMin, endMin, duration;

      if (this.isAttendantView) {
        startMin = this.getMinutes(booking._serviceTime.start);
        const realDuration = this.getMinutes(booking._serviceTime.end) - startMin;
        duration = this.getDisplayDuration(booking, realDuration);
        endMin = startMin + duration;
      } else {
        startMin = this.getMinutes(booking.time);
        if (booking.services?.length) {
          const lastService = booking.services[booking.services.length - 1];
          const endTime = lastService.end_at || booking.time;
          const realDuration = this.getMinutes(endTime) - startMin;
          duration = this.getDisplayDuration(booking, realDuration);
          endMin = startMin + duration;
        } else {
          duration = this.getDefaultDuration(booking);
          endMin = startMin + duration;
        }
      }

      const pxPerMin = this.slotHeight / this.calcSlotStep();
      const topPx = (startMin - openMin) * pxPerMin;
      const heightPx = Math.max((endMin - startMin) * pxPerMin, this.slotHeight);

      let leftPx = 0;
      if (this.isAttendantView) {
        const columnIndex = this.sortedAttendants.findIndex(a => a.id === booking._assistantId);
        if (columnIndex >= 0) {
          leftPx = this.getAssistantColumnLeft(columnIndex);
          leftPx += this.getBookingPosition(booking);
        }
      } else {
        const colIndex = booking._column || 0;
        leftPx = colIndex * this.cardWidth;
      }

      return {
        position: "absolute",
        top: `${topPx}px`,
        left: `${leftPx}px`,
        width: `${this.cardWidth}px`,
        height: `${heightPx}px`,
      };
    },
    getTimeSlotLineStyle(index) {
      const topPx = index * this.slotHeight;
      return {
        position: "absolute",
        left: "0",
        right: "0",
        top: `${topPx}px`,
        height: `${this.slotHeight}px`,
        display: "flex",
        alignItems: "center",
        borderTop: index > 0 ? "1px solid #ddd" : "none",
        backgroundColor: "#EDF0F5",
        boxSizing: "border-box",
      };
    },
    getAssistantColumnLeft(index) {
      return this.sortedAttendants.slice(0, index).reduce((sum, attendant) => {
        const width = this.columnWidths[attendant.id] || this.attendantColumnWidth;
        return sum + width + this.attendantColumnGap;
      }, 0);
    },
    getBookingPosition(booking) {
      const attendantId = booking._assistantId;

      const startMin = this.getMinutes(booking._serviceTime.start);
      const realDuration = this.getMinutes(booking._serviceTime.end) - startMin;
      const displayDuration = this.getDisplayDuration(booking, realDuration);
      const bookingEnd = startMin + displayDuration;

      const overlappingBookings = this.processedBookings
          .filter(b => {
            if (b._assistantId !== attendantId || b.id === booking.id) return false;
            const bStart = this.getMinutes(b._serviceTime.start);
            const bRealDuration = this.getMinutes(b._serviceTime.end) - bStart;
            const bDisplayDuration = this.getDisplayDuration(b, bRealDuration);
            const bEnd = bStart + bDisplayDuration;
            return startMin < bEnd && bookingEnd > bStart;
          })
          .sort((a, b) => {
            const timeA = this.getMinutes(a._serviceTime.start);
            const timeB = this.getMinutes(b._serviceTime.start);
            return timeA === timeB ? a.id - b.id : timeA - timeB;
          });

      if (overlappingBookings.length === 0) {
        booking._position = 0;
        return 0;
      }

      const usedPositions = new Set(overlappingBookings.map(b => b._position || 0));

      let position = 0;
      while (usedPositions.has(position)) {
        position++;
      }
      booking._position = position;

      return position * this.cardWidth;
    },
    getMinutes(str) {
      const [hh, mm] = str.split(":").map(Number);
      return hh * 60 + mm;
    },
    getDefaultDuration(booking) {
      if (!booking.services?.length) return 30;
      return this.getDisplayDuration(booking, 30);
    },
    getDisplayDuration(booking, realDuration) {
      /* if (booking.services?.length === 1 && realDuration === 15) {
         return 30;
       }*/
      /*if (realDuration <= 30) return 30;
      if (realDuration <= 45) return 45;*/
      //return Math.ceil(realDuration / 30) * 30;
      return realDuration;
    },
    calculateEndTime(startTime, durationMinutes) {
      const [hours, minutes] = startTime.split(":").map(Number);
      const totalMinutes = hours * 60 + minutes + durationMinutes;
      const newHours = Math.floor(totalMinutes / 60);
      const newMinutes = totalMinutes % 60;
      return `${String(newHours).padStart(2, "0")}:${String(newMinutes).padStart(2, "0")}`;
    },
    onMouseDown(e) {
      if (!this.$refs.dragScrollContainer) return;
      this.possibleDrag = true;
      this.isDragging = false;
      this.wasRecentlyDragging = false;
      this.startX = e.pageX - this.$refs.dragScrollContainer.offsetLeft;
      this.scrollLeft = this.$refs.dragScrollContainer.scrollLeft;
      document.body.style.userSelect = "none";
    },
    onMouseMove(e) {
      if (!this.possibleDrag) return;
      const x = e.pageX - this.$refs.dragScrollContainer.offsetLeft;
      const walk = Math.abs(x - this.startX);
      if (walk > 5) {
        this.isDragging = true;
        this.activeSlotIndex = -1;
      }
      if (this.isDragging) {
        e.preventDefault();
        this.$refs.dragScrollContainer.scrollLeft =
            this.scrollLeft - (x - this.startX);
      }
    },
    onMouseUp() {
      this.possibleDrag = false;
      if (this.isDragging) {
        this.isDragging = false;
        this.wasRecentlyDragging = true;
        setTimeout(() => {
          this.wasRecentlyDragging = false;
        }, 200);
      }
      document.body.style.userSelect = "";
    },
    onMouseLeave() {
      if (this.possibleDrag) {
        this.onMouseUp();
      }
    },
    onTouchStart(e) {
      if (!this.$refs.dragScrollContainer) return;
      this.isDragging = false;
      this.possibleDrag = true;
      this.startX = e.touches[0].clientX - this.$refs.dragScrollContainer.offsetLeft;
      this.startY = e.touches[0].clientY;
      this.scrollLeft = this.$refs.dragScrollContainer.scrollLeft;
    },
    onTouchMove(e) {
      if (!this.possibleDrag) return;
      const x = e.touches[0].clientX - this.$refs.dragScrollContainer.offsetLeft;
      const y = e.touches[0].clientY;
      const walkX = Math.abs(x - this.startX);
      const walkY = Math.abs(y - this.startY);
      if (walkX > 5 && walkX > walkY) {
        this.isDragging = true;
        this.activeSlotIndex = -1;
        if (e.cancelable) e.preventDefault();
        this.$refs.dragScrollContainer.scrollLeft =
            this.scrollLeft - (x - this.startX);
      }
    },
    onTouchEnd() {
      this.possibleDrag = false;
      if (this.isDragging) {
        this.isDragging = false;
        this.wasRecentlyDragging = true;
        setTimeout(() => {
          this.wasRecentlyDragging = false;
        }, 200);
      }
    },
    handleOutsideClick() {
      if (!this.isDragging && !this.wasRecentlyDragging) {
        this.activeSlotIndex = -1;
      }
    },
    viewCustomerProfile(customer) {
      this.$emit("viewCustomerProfile", customer);
    }
  },
  emits: [
    "update:modelValue",
    'update:lockedTimeslots',
    "add",
    "showItem",
    "viewCustomerProfile",
    "lock",
    "unlock",
    "lock-start",
    "lock-end",
    "unlock-start",
  ],
};
</script>

<style scoped>
.reservations-calendar {
  margin-bottom: 48px;
}

.title {
  text-align: left;
  font-weight: bold;
  color: #322d38;
  font-size: 22px;
}

.slots {
  margin-top: 12px;
  background: #EDF0F5;
  padding: 16px;
  border-radius: 12px;
  position: relative;
}

.slots.slots--assistants .current-time-line {
  margin-top: 64px;
}

.slots.slots--assistants .slots-content,
.slots.slots--assistants .time-axis {
  padding-top: 64px;
}

.slots-inner {
  position: relative;
  display: flex;
}

.slots-content {
  display: flex;
  position: relative;
  flex: 1;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
  transition: .15s ease-in-out;
}

.slots-content * {
  cursor: default;
}

.slots-content::-webkit-scrollbar {
  display: none;
}

.slots-content, .slots-content * {
  user-select: none;
}

.bookings-canvas {
  position: relative;
  min-width: calc(100% + 245px);
  width: auto;
  height: auto;
  overflow: visible;
}

.booking-card {
  z-index: 11;
  display: flex;
  padding: 10px;
  pointer-events: none;
}

.current-time-line {
  position: absolute;
  left: 0;
  right: 0;
  height: 2px;
  background-color: #FF0000;
  z-index: 555;
  pointer-events: none;
}

.current-time-line::before, .current-time-line::after {
  content: '';
  position: absolute;
  background-color: #FF0000;
  top: 50%;
  width: 16px;
  height: 16px;
  mask-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'%3E%3Cpath d='M310.6 233.4a32 32 0 0 1 0 45.3l-192 192a32 32 0 0 1-45.3-45.3L242.7 256 73.4 86.6a32 32 0 0 1 45.3-45.3l192 192z'/%3E%3C/svg%3E");
  mask-repeat: no-repeat;
  mask-size: contain;
  mask-position: center;
}

.current-time-line::before {
  transform: translateY(-50%);
  left: -13px;
}

.current-time-line::after {
  transform: translateY(-50%) rotate(180deg);
  right: -13px;
}

.spinner-wrapper {
  width: 100%;
  height: 100%;
  position: absolute;
  background-color: #e0e0e0d1;
  opacity: 0.5;
  inset: 0;
  border-radius: 12px;
}

.attendant-column {
  position: relative;
  width: 100%;
  display: flex;
  flex-direction: column;
}

.time-slot-actions {
  position: absolute;
  display: flex;
  align-items: center;
  gap: 16px;
  z-index: 20;
  left: 50%;
  transform: translateX(-50%);
}
</style>