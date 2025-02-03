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
                    @delete="deleteItem"
                    @show-details="showDetails"
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
                    @delete="deleteItem"
                    @show-details="showDetails"
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

export default {
  name: "ReservationsCalendar",
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
      processingSlots: [],

      // UI state
      search: "",
      activeSlotIndex: -1,
      currentTimeLinePosition: 0,
      showCurrentTimeLine: false,

      // loading states
      isLoadingTimeslots: false,
      isLoadingCalendar: false,
      isLoading: false,

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
          const width = this.columnWidths[attendant.id] || this.attendantColumnWidth;
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
        return this.bookingsList;
      }

      return this.bookingsList.flatMap((booking) => {
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
              end: lastService.end_at || this.calculateEndTime(
                  lastService.start_at || booking.time,
                  this.getDefaultDuration(booking)
              )
            },
            _assistantId: parseInt(assistantId),
            _isDefaultDuration: !lastService.end_at
          };
        });
      });
    },
    sortedAttendants() {
      if (!this.attendants || !this.bookingsList) return [];
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
        const maxOverlap = this.getOverlappingBookingsCount(attendant.id);
        widths[attendant.id] = this.cardWidth * maxOverlap;
      });

      return widths;
    },
    isReadyToRender() {
      return !this.isLoadingTimeslots && this.attendantsLoaded && this.timeslots.length > 0;
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
    modelValue(newVal, oldVal) {
      if (newVal.getTime() !== oldVal?.getTime()) {
        this.activeSlotIndex = -1;
        this.loadLockedTimeslots();
        this.loadBookingsList();
        this.loadAvailabilityIntervals();
        this.$nextTick(() => {
          this.updateCurrentTimeLinePosition();
        });
      }
    },
    shop: {
      handler(newVal, oldVal) {
        if (newVal?.id !== oldVal?.id) {
          this.activeSlotIndex = -1;
          this.load();
        }
      },
      deep: true
    },
    "$root.settings": {
      handler(newSettings) {
        if (newSettings?.attendant_enabled) {
          this.loadAttendants();
        }
      },
      deep: true
    },
    isAttendantView(newValue) {
      localStorage.setItem('isAttendantView', newValue);
      this.isLoading = true;

      Promise.all([
        this.loadTimeslots(),
        this.loadLockedTimeslots(),
        this.loadBookingsList(),
      ]).finally(() => {
        this.isLoading = false;
      });
    },
    date(newVal, oldVal) {
      if (newVal.getTime() !== oldVal?.getTime()) {
        this.loadTimeslots();
        this.loadLockedTimeslots();
        this.loadBookingsList();
      }
    },
  },
  mounted() {
    this.load();
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
    })
    if (this.$refs.slotsContainer) {
      this.$refs.slotsContainer.addEventListener("click", (e) => {
        if (e.target === this.$refs.slotsContainer) {
          this.handleOutsideClick();
        }
      });
    }
    if (this.$root.settings?.attendant_enabled) {
      this.loadAttendants();
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
    load() {
      this.loadTimeslots();
      this.loadLockedTimeslots();
      this.loadBookingsList();
      const d = this.date;
      const y = d.getFullYear();
      const m = d.getMonth();
      const firstDate = new Date(y, m, 1);
      const lastDate = new Date(y, m + 1, 0);
      this.loadAvailabilityStats(firstDate, lastDate);
    },
    update() {
      this.updateBookingsList();
    },
    async loadTimeslots() {
      this.isLoadingTimeslots = true;
      try {
        const response = await this.axios.get('calendar/intervals', {
          params: {shop: this.shop?.id || null},
        });
        this.timeslots = response.data.items || [];
      } catch (error) {
        console.error('error loading timeslots:', error);
      } finally {
        this.isLoadingTimeslots = false;
      }
    },
    addBookingForAttendant({timeslot, attendantId}) {
      const selectedDate = this.modelValue;
      this.$emit("add", selectedDate, timeslot, attendantId);
    },
    async loadLockedTimeslots() {
      this.isLoading = true;
      try {
        const formattedDate = this.moment(this.date).format('YYYY-MM-DD');
        const response = await this.axios.get('holiday-rules', {
          params: {
            assistants_mode: this.isAttendantView ? 'true' : 'false',
            date: formattedDate,
          },
        });

        if (response.data?.status === 'OK') {
          if (this.isAttendantView) {
            const assistantsRules = response.data.assistants_rules || {};
            this.lockedTimeslots = Object.entries(assistantsRules).flatMap(([assistantId, rules]) =>
                rules.map(rule => ({
                  ...rule,
                  assistant_id: Number(assistantId) || null,
                }))
            );
          } else {
            this.lockedTimeslots = response.data.items || [];
          }
        } else {
          console.error('unexpected response:', response.data);
        }
      } catch (error) {
        console.error('error loading locked timeslots:', error.response?.data || error.message);
      } finally {
        this.isLoading = false;
      }
    },
    loadAvailabilityStats(fd, td) {
      this.isLoadingCalendar = true;
      this.axios
          .get("availability/stats", {
            params: {
              from_date: this.moment(fd).format("YYYY-MM-DD"),
              to_date: this.moment(td).format("YYYY-MM-DD"),
              shop: this.shop?.id || null
            },
          })
          .then((r) => {
            this.availabilityStats = r.data.stats;
          })
          .finally(() => {
            this.isLoadingCalendar = false;
          });
    },
    async loadBookingsList() {
      try {
        const response = await this.axios.get('bookings', {
          params: {
            start_date: this.moment(this.date).format('YYYY-MM-DD'),
            end_date: this.moment(this.date).format('YYYY-MM-DD'),
            per_page: -1,
            statuses: [
              'sln-b-pendingpayment',
              'sln-b-pending',
              'sln-b-paid',
              'sln-b-paylater',
              'sln-b-canceled',
              'sln-b-confirmed',
            ],
            shop: this.shop?.id || null,
          },
        });
        this.bookingsList = response.data.items || [];
      } catch (error) {
        console.error('error loading bookings list:', error);
      }
    },
    handleSearch(value) {
      this.activeSlotIndex = -1;
      if (value) {
        this.loadFilteredBookings(value);
      } else {
        this.loadBookingsList();
      }
    },
    loadFilteredBookings(searchQuery) {
      this.isLoadingTimeslots = true;
      this.bookingsList = [];
      const currentView = this.isAttendantView;

      return this.axios
          .get("bookings", {
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
                "sln-b-canceled",
                "sln-b-confirmed",
              ],
              shop: this.shop?.id || null,
            },
          })
          .then((r) => {
            this.bookingsList = r.data.items;
            this.arrangeBookings();
            this.isAttendantView = currentView;
          })
          .finally(() => {
            this.isLoadingTimeslots = false;
          });
    },
    updateBookingsList() {
      const currentView = this.isAttendantView;

      this.axios
          .get("bookings", {
            params: {
              start_date: this.moment(this.date).format("YYYY-MM-DD"),
              end_date: this.moment(this.date).format("YYYY-MM-DD"),
              per_page: -1,
              statuses: [
                "sln-b-pendingpayment",
                "sln-b-pending",
                "sln-b-paid",
                "sln-b-paylater",
                "sln-b-canceled",
                "sln-b-confirmed"
              ],
              shop: this.shop?.id || null
            }
          })
          .then((r) => {
            this.bookingsList = r.data.items;
            this.arrangeBookings();
            this.isAttendantView = currentView;
          });
    },
    loadAvailabilityIntervals() {
      if (!this.timeslots.length) return;
      this.axios
          .post("availability/intervals", {
            date: this.moment(this.date).format("YYYY-MM-DD"),
            time: this.timeslots[0],
            shop: this.shop?.id || 0
          })
          .then((r) => {
            this.availabilityIntervals = r.data.intervals;
          });
    },
    loadAttendants() {
      this.axios
          .get("assistants", {params: {shop: this.shop?.id || null, per_page: -1}})
          .then((response) => {
            this.attendants = response.data.items;
            this.attendantsLoaded = true;
          })
          .catch((error) => {
            console.error("Error loading attendants:", error);
            this.attendantsLoaded = true;
          });
    },
    handleSlotLock(holidayRule) {
      if (!this.validatedHolidayRule(holidayRule)) return;

      const exists = this.lockedTimeslots.some(locked =>
          locked.from_time === holidayRule.from_time &&
          locked.to_time === holidayRule.to_time &&
          locked.from_date === holidayRule.from_date &&
          locked.to_date === holidayRule.to_date &&
          locked.assistant_id === holidayRule.assistant_id
      );

      if (!exists) {
        this.lockedTimeslots = [...this.lockedTimeslots, holidayRule];
      } else {
        console.warn("slot is already locked!");
      }
    },

    handleSlotUnlock(holidayRule) {
      const newLockedTimeslots = this.lockedTimeslots.filter(locked =>
          !(locked.from_time === holidayRule.from_time &&
              locked.to_time === holidayRule.to_time &&
              locked.from_date === holidayRule.from_date &&
              locked.to_date === holidayRule.to_date &&
              locked.assistant_id === holidayRule.assistant_id)
      );

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
    isHoliday(date) {
      return this.availabilityStats.some(
          (stat) =>
              stat.date === this.moment(date).format("YYYY-MM-DD") &&
              stat.error &&
              stat.error.type === "holiday_rules"
      );
    },
    isAvailable(start) {
      if (this.isHoliday(this.date)) return false;

      if (
          this.availabilityIntervals.universalSuggestedDate !==
          this.moment(this.date).format("YYYY-MM-DD")
      ) {
        return false;
      }
      return Object.values(this.availabilityIntervals.times).indexOf(start) > -1;
    },
    isSlotLocked(currentSlot, nextSlot) {
      if (this.isHoliday(this.date)) {
        return true;
      }

      if (!Array.isArray(this.lockedTimeslots)) {
        console.error('lockedTimeslots is not an array:', this.lockedTimeslots);
        return false;
      }

      return this.lockedTimeslots.some(locked => {
        const timeMatch = locked.from_time === currentSlot &&
            locked.to_time === nextSlot;
        const dateMatch = locked.from_date === this.moment(this.date).format("YYYY-MM-DD");
        const processingCheck = !this.isSlotProcessing(currentSlot, nextSlot);

        return timeMatch && dateMatch && processingCheck;
      });
    },
    setSlotProcessing(slotKey, isProcessing) {
      if (isProcessing) {
        this.slotProcessingStates.set(slotKey, true);
      } else {
        this.slotProcessingStates.delete(slotKey);
      }
    },
    isSlotProcessing(slotKey) {
      return this.slotProcessingStates.has(slotKey);
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
      this.columns = [];
      if (!Array.isArray(this.bookingsList)) return;

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
      for (const b of columnBookings) {
        const bStart = this.getBookingStart(b);
        const bEnd = this.getBookingEnd(b);
        if (bStart < newEnd && bEnd > newStart) {
          return true;
        }
      }
      return false;
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
      let endAt = booking.time;
      if (booking.services?.length) {
        const lastService = booking.services[booking.services.length - 1];
        endAt = lastService.end_at || booking.time;
      }
      return this.getMinutes(endAt);
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
        leftPx = colIndex * (this.cardWidth + this.gap);
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
    getOverlappingBookingsCount(attendantId) {
      const bookings = this.processedBookings.filter(b => b._assistantId === attendantId);
      if (bookings.length <= 1) return 1;

      let maxOverlap = 1;
      for (let i = 0; i < bookings.length; i++) {
        let currentOverlap = 1;
        const current = bookings[i];
        const currentStart = this.getMinutes(current._serviceTime.start);
        const currentEnd = this.getMinutes(current._serviceTime.end);

        for (let j = i + 1; j < bookings.length; j++) {
          const other = bookings[j];
          const otherStart = this.getMinutes(other._serviceTime.start);
          const otherEnd = this.getMinutes(other._serviceTime.end);

          if (currentStart < otherEnd && currentEnd > otherStart) {
            currentOverlap++;
          }
        }
        maxOverlap = Math.max(maxOverlap, currentOverlap);
      }
      return maxOverlap;
    },
    getAssistantColumnLeft(index) {
      return this.sortedAttendants.slice(0, index).reduce((sum, attendant) => {
        const width = this.columnWidths[attendant.id] || this.attendantColumnWidth;
        return sum + width + this.attendantColumnGap;
      }, 0);
    },
    getBookingPosition(booking) {
      const attendantId = booking._assistantId;
      const overlappingBookings = this.processedBookings
          .filter((b) => b._assistantId === attendantId && this.doBookingsOverlap(booking, b) && b.id !== booking.id)
          .sort((a, b) => this.getMinutes(a._serviceTime.start) - this.getMinutes(b._serviceTime.start));

      let position = 0;
      const usedPositions = new Set(overlappingBookings.map(b => b._position));
      while (usedPositions.has(position)) {
        position++;
      }
      booking._position = position;
      return position * this.cardWidth;
    },
    doBookingsOverlap(booking1, booking2) {
      if (!booking1 || !booking2) return false;
      const start1 = this.getMinutes(booking1._serviceTime.start);
      const end1 = this.getMinutes(booking1._serviceTime.end);
      const start2 = this.getMinutes(booking2._serviceTime.start);
      const end2 = this.getMinutes(booking2._serviceTime.end);
      return start1 < end2 && end1 > start2;
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
      if (booking.services?.length === 1 && realDuration === 15) {
        return 30;
      }
      if (realDuration <= 30) return 30;
      if (realDuration <= 45) return 60;
      return Math.ceil(realDuration / 30) * 30;
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
    }
  },
  emits: [
    "update:modelValue",
    'update:lockedTimeslots',
    "add",
    "showItem",
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