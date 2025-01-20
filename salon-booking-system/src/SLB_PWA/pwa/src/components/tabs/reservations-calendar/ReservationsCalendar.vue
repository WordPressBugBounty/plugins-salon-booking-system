<template>
  <div class="reservations-calendar">
    <h5 class="title">{{ this.getLabel("reservationsCalendarTitle") }}</h5>

    <div class="search">
      <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="search-icon"/>
      <b-form-input v-model="search" class="search-input"/>
      <font-awesome-icon icon="fa-solid fa-circle-xmark"
                         class="clear"
                         @click="search = ''"
                         v-if="search"/>
    </div>

    <b-row>
      <b-col sm="12">
        <div class="calendar">
          <Datepicker
              v-model="date"
              inline
              autoApply
              noSwipe
              :enableTimePicker="false"
              :monthChangeOnScroll="false"
              @updateMonthYear="handleMonthYear"
          >
            <template #day="{ day, date }">
              <template v-if="isDayWithBookings(date)">
                <div class="day day-with-bookings">{{ day }}</div>
              </template>
              <template v-else-if="isDayFullBooked(date)">
                <div class="day day-full-booked">{{ day }}</div>
              </template>
              <template v-else-if="isAvailableBookings(date)">
                <div class="day day-available-book">{{ day }}</div>
              </template>
              <template v-else-if="isHoliday(date)">
                <div class="day day-holiday">{{ day }}</div>
              </template>
              <template v-else>
                <div class="day day-disable-book">{{ day }}</div>
              </template>
            </template>
          </Datepicker>
          <div class="spinner-wrapper" v-if="isLoadingCalendar"></div>
          <b-spinner variant="primary" v-if="isLoadingCalendar"></b-spinner>
        </div>
      </b-col>
    </b-row>

    <div class="selected-date">
      {{ formattedDate }}
    </div>

    <div class="slots" ref="slotsContainer">
      <b-spinner variant="primary" v-if="isLoadingTimeslots"></b-spinner>
      <div v-else-if="timeslots.length > 0" class="slots-inner">
        <div class="time-axis">
          <div
              class="time-axis-item"
              v-for="(timeslot, index) in timeslots"
              :key="'axis-' + index"
              :style="{ height: slotHeight + 'px' }"
          >
            {{ timeslot }}
          </div>
        </div>
        <div
            class="slots-content"
            ref="dragScrollContainer"
            @mousedown="onMouseDown"
            @mousemove="onMouseMove"
            @mouseup="onMouseUp"
            @mouseleave="onMouseLeave"
            @touchstart.stop="onTouchStart"
            @touchmove.stop="onTouchMove"
            @touchend.stop="onTouchEnd"
        >
          <div class="bookings-canvas" :style="{ height: canvasHeight + 'px', width: canvasWidth + 'px' }">
            <div
                v-for="(slot, idx) in timeslots"
                :key="'line-' + idx"
                class="time-slot-line"
                :style="getTimeSlotLineStyle(idx)"
                :class="{
                  active: activeSlotIndex === idx,
                  locked: isSlotLocked(timeslots[idx], timeslots[idx + 1]),
                  processing: isSlotProcessing(timeslots[idx], timeslots[idx + 1])
                }"
                @click="toggleSlotActions(idx)"
            >

              <div class="time-slot-actions">
                <BookingAdd
                    v-if="!isSlotLocked(timeslots[idx], timeslots[idx + 1])"
                    :timeslot="slot"
                    :isAvailable="isAvailable(slot)"
                    @add="addBooking(slot)"
                />
                <BookingBlockSlot
                    v-if="!hasOverlappingBookings(idx) || isSlotLocked(timeslots[idx], timeslots[idx + 1])"
                    :isLock="isSlotLocked(timeslots[idx], timeslots[idx + 1])"
                    :start="timeslots[idx]"
                    :end="timeslots[idx + 1]"
                    :date="date"
                    :shop="shop"
                    @lock-start="handleLockStart"
                    @lock="handleSlotLock"
                    @lock-end="handleLockEnd"
                    @unlock-start="handleUnlockStart"
                    @unlock="handleSlotUnlock"
                    @unlock-end="handleUnlockEnd"
                />
              </div>
            </div>

            <div
                v-for="(booking, bIndex) in bookingsList"
                :key="booking.id"
                class="booking-card"
                :style="getBookingStyle(booking, bIndex)"
            >
              <BookingCard
                  :booking="booking"
                  @deleteItem="deleteItem(booking.id)"
                  @showDetails="showDetails(booking)"
              />
            </div>
          </div>
        </div>
        <div
            class="current-time-line"
            v-if="showCurrentTimeLine"
            :style="{ top: currentTimeLinePosition + 'px' }"
        />
      </div>
      <span v-else>{{ this.getLabel("noResultTimeslotsLabel") }}</span>
    </div>
  </div>
</template>

<script>
import BookingCard from "./BookingCard.vue";
import BookingAdd from "./BookingAdd.vue";
import BookingBlockSlot from "./BookingBlockSlot.vue";

export default {
  name: "ReservationsCalendar",
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
  components: {
    BookingCard,
    BookingBlockSlot,
    BookingAdd
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
      columns: [],

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
      gap: 24,

      // drag handling
      isDragging: false,
      wasRecentlyDragging: false,
      possibleDrag: false,
      startX: 0,
      startY: 0,
      scrollLeft: 0,

      // timeouts and intervals
      timeout: null,
      intervalId: null,
      loadIntervalsTimeout: null,
      processingTimeouts: new Map()
    };
  },
  computed: {
    date: {
      get() {
        return this.modelValue;
      },
      set(value) {
        this.$emit('update:modelValue', value);
      }
    },
    canvasWidth() {
      return this.bookingsList.length * (this.cardWidth + this.gap);
    },
    canvasHeight() {
      return this.timeslots.length * this.slotHeight;
    },
    formattedDate() {
      return this.moment(this.modelValue).format("dddd DD MMMM YYYY");
    }
  },
  watch: {
    modelValue: {
      handler() {
        this.activeSlotIndex = -1;
        this.loadLockedTimeslots();
        this.loadBookingsList();
        this.loadAvailabilityIntervals();
      },
      immediate: true
    },
    search(newVal) {
      this.activeSlotIndex = -1;
      if (newVal) this.loadSearchBookingsList();
      else this.loadBookingsList();
    },
    timeslots() {
      this.loadAvailabilityIntervals();
      this.$nextTick(() => {
        this.updateCurrentTimeLinePosition();
      });
    },
    lockedTimeslots: {
      handler() {
        if (this.timeslots.length > 0) {
          if (this.loadIntervalsTimeout) {
            clearTimeout(this.loadIntervalsTimeout);
          }
          this.loadIntervalsTimeout = setTimeout(() => {
            this.loadAvailabilityIntervals();
          }, 300);
        }
      },
      deep: true
    },
    shop() {
      this.activeSlotIndex = -1;
      this.load();
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
        container.addEventListener("touchmove", this.onTouchMove, {
          passive: false,
        });
      }
    });
    this.$refs.slotsContainer.addEventListener('click', (e) => {
      if (e.target === this.$refs.slotsContainer) {
        this.handleOutsideClick();
      }
    });
  },
  beforeUnmount() {
    if (this.intervalId) clearInterval(this.intervalId);
    if (this.loadIntervalsTimeout) clearTimeout(this.loadIntervalsTimeout);
    this.processingTimeouts.forEach(timeout => clearTimeout(timeout));
    this.processingTimeouts.clear();
    const container = this.$refs.dragScrollContainer;
    if (container) {
      container.removeEventListener("touchmove", this.onTouchMove);
    }
    if (this.$refs.slotsContainer) {
      this.$refs.slotsContainer.removeEventListener('click', this.handleOutsideClick);
    }
  },
  methods: {
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
    /*load*/
    loadTimeslots() {
      this.isLoadingTimeslots = true;
      this.axios
          .get("calendar/intervals", {params: {shop: this.shop?.id || null}})
          .then((r) => {
            this.timeslots = r.data.items;
          })
          .finally(() => {
            this.isLoadingTimeslots = false;
          });
    },
    loadLockedTimeslots() {
      this.isLoading = true;
      this.axios
          .get("holiday-rules", {
            params: {
              date: this.moment(this.date).format("YYYY-MM-DD"),
              shop: this.shop ? this.shop.id : null
            },
          })
          .then((response) => {
            this.lockedTimeslots = response.data.items;
          })
          .finally(() => {
            this.isLoading = false;
          });
    },
    loadAvailabilityStats(fd, td) {
      this.isLoadingCalendar = true;
      this.axios
          .get("availability/stats", {
            params: {
              from_date: this.moment(fd).format("YYYY-MM-DD"),
              to_date: this.moment(td).format("YYYY-MM-DD"),
              shop: this.shop?.id || null,
            },
          })
          .then((r) => {
            this.availabilityStats = r.data.stats;
          })
          .finally(() => {
            this.isLoadingCalendar = false;
          });
    },
    loadBookingsList() {
      this.isLoadingTimeslots = true;
      this.bookingsList = [];
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
                "sln-b-confirmed",
              ],
              shop: this.shop?.id || null,
            },
          })
          .then((r) => {
            this.bookingsList = r.data.items;
            this.arrangeBookings();
          })
          .finally(() => {
            this.isLoadingTimeslots = false;
          });
    },
    loadAvailabilityIntervals() {
      if (!this.timeslots.length) return;
      this.axios
          .post("availability/intervals", {
            date: this.moment(this.date).format("YYYY-MM-DD"),
            time: this.timeslots[0],
            shop: this.shop?.id || 0,
          })
          .then((r) => {
            this.availabilityIntervals = r.data.intervals;
          });
    },
    loadSearchBookingsList() {
      if (this.timeout) clearTimeout(this.timeout);
      this.timeout = setTimeout(() => {
        this.isLoadingTimeslots = true;
        this.bookingsList = [];
        this.axios
            .get("bookings", {
              params: {
                start_date: this.moment(this.date).format("YYYY-MM-DD"),
                end_date: this.moment(this.date).format("YYYY-MM-DD"),
                search: this.search,
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
            })
            .finally(() => {
              this.isLoadingTimeslots = false;
            });
      }, 1000);
    },
    /*handle*/
    handleLockStart(slot) {
      if (!this.processingSlots.some(s =>
          s.from_time === slot.from_time && s.to_time === slot.to_time
      )) {
        this.processingSlots.push(slot);
        setTimeout(() => {
          this.handleLockEnd(slot);
        }, 5000);
      }
    },
    handleLockEnd(slot) {
      this.processingSlots = this.processingSlots.filter(
          (s) => !(s.from_time === slot.from_time && s.to_time === slot.to_time)
      );
    },
    handleUnlockStart(slot) {
      this.processingSlots.push(slot);
    },
    handleUnlockEnd(slot) {
      this.processingSlots = this.processingSlots.filter(
          (s) => !(s.from_time === slot.from_time && s.to_time === slot.to_time)
      );
    },
    handleSlotLock(holidayRule) {
      const exists = this.lockedTimeslots.some(
          locked =>
              locked.from_time === holidayRule.from_time &&
              locked.to_time === holidayRule.to_time &&
              locked.from_date === holidayRule.from_date &&
              locked.to_date === holidayRule.to_date
      );

      if (!exists) {
        this.lockedTimeslots = [...this.lockedTimeslots, holidayRule];
      }
    },
    handleSlotUnlock(holidayRule) {
      this.lockedTimeslots = this.lockedTimeslots.filter(
          (locked) =>
              !(
                  locked.from_time === holidayRule.from_time &&
                  locked.to_time === holidayRule.to_time &&
                  locked.from_date === holidayRule.from_date &&
                  locked.to_date === holidayRule.to_date
              )
      );
    },
    handleMonthYear(obj) {
      const fd = new Date(obj.year, obj.month, 1);
      const td = new Date(obj.year, obj.month + 1, 0);
      this.loadAvailabilityStats(fd, td);
    },
    /* is */
    isDayWithBookings(date) {
      return this.availabilityStats.some(
          (stat) =>
              stat.date === this.moment(date).format("YYYY-MM-DD") &&
              stat.data &&
              stat.data.bookings > 0
      );
    },
    isAvailableBookings(date) {
      return this.availabilityStats.some(
          (stat) =>
              stat.date === this.moment(date).format("YYYY-MM-DD") &&
              stat.available &&
              !stat.full_booked
      );
    },
    isDayFullBooked(date) {
      return this.availabilityStats.some(
          (stat) =>
              stat.date === this.moment(date).format("YYYY-MM-DD") && stat.full_booked
      );
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
      if (this.isHoliday(this.date)) {
        return false;
      }

      if (
          this.availabilityIntervals.universalSuggestedDate !==
          this.moment(this.date).format("YYYY-MM-DD")
      ) {
        return false;
      }
      return Object.values(this.availabilityIntervals.times).indexOf(start) > -1;
    },
    isSlotLocked(from, to) {
      if (this.isHoliday(this.date)) {
        return true;
      }
      const slotDate = this.moment(this.date).format('YYYY-MM-DD');
      let slotStart = this.moment(`${slotDate} ${from}`, 'YYYY-MM-DD HH:mm');
      let slotEnd = this.moment(`${slotDate} ${to}`, 'YYYY-MM-DD HH:mm');

      if (slotStart.isSameOrAfter(slotEnd)) {
        slotEnd = slotEnd.add(1, 'day');
      }

      return this.lockedTimeslots.some((lockedSlot) => {
        const lockedStart = this.moment(`${lockedSlot.from_date} ${lockedSlot.from_time}`, 'YYYY-MM-DD HH:mm');
        const lockedEnd = this.moment(`${lockedSlot.to_date} ${lockedSlot.to_time}`, 'YYYY-MM-DD HH:mm');

        return slotStart.isBefore(lockedEnd) && slotEnd.isAfter(lockedStart);
      });
    },
    isSlotProcessing(from, to) {
      return this.processingSlots.some(
          slot => slot.from_time === from && (to === undefined || slot.to_time === to)
      );
    },
    /*other*/
    updateBookingsList() {
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
                "sln-b-confirmed",
              ],
              shop: this.shop?.id || null,
            },
          })
          .then((r) => {
            this.bookingsList = r.data.items;
            this.arrangeBookings();
          });
    },
    calcSlotStep() {
      if (this.timeslots.length < 2) return 30;
      const t1 = this.getMinutes(this.timeslots[0]);
      const t2 = this.getMinutes(this.timeslots[1]);
      return t2 - t1;
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
      if (!this.timeslots || this.timeslots.length < 1) {
        this.showCurrentTimeLine = false;
        return;
      }
      const startTime = this.timeslots[0];
      const endTime = this.timeslots[this.timeslots.length - 1];
      const now = this.moment();
      const opening = this.moment(startTime, "HH:mm");
      const closing = this.moment(endTime, "HH:mm");

      opening.year(now.year()).month(now.month()).date(now.date());
      closing.year(now.year()).month(now.month()).date(now.date());

      if (closing.isBefore(opening)) {
        closing.add(1, 'day');
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
      const minutesSinceOpening = now.diff(opening, 'minutes');
      const position = (minutesSinceOpening / slotDuration) * this.slotHeight;

      this.currentTimeLinePosition = Math.max(0, Math.min(position, this.timeslots.length * this.slotHeight));
      this.showCurrentTimeLine = true;
    },
    arrangeBookings() {
      this.columns = [];
      const sorted = [...this.bookingsList].sort((a, b) => {
        const aStart = this.getBookingStart(a);
        const bStart = this.getBookingStart(b);
        return aStart - bStart;
      });
      sorted.forEach(booking => {
        booking._column = this.findFreeColumn(booking);
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
    hasOverlappingBookings(slotIndex) {
      const slotStart = this.getMinutes(this.timeslots[slotIndex]);
      const slotEnd = slotIndex + 1 < this.timeslots.length
          ? this.getMinutes(this.timeslots[slotIndex + 1])
          : slotStart + this.calcSlotStep();

      return this.bookingsList.some(booking => {
        const bookingStart = this.getBookingStart(booking);
        const bookingEnd = this.getBookingEnd(booking);
        return bookingStart < slotEnd && bookingEnd > slotStart;
      });
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
    /*get*/
    getBookingStart(booking) {
      return this.getMinutes(booking.time);
    },
    getBookingEnd(booking) {
      let endAt = booking.time;
      if (booking.services?.length) {
        endAt = booking.services[booking.services.length - 1].end_at || booking.time;
      }
      return this.getMinutes(endAt);
    },
    getDisplayDuration(booking, realDuration) {
      if (booking.services?.length === 1 && realDuration === 15) {
        return 30;
      }
      if (realDuration <= 30) {
        return 30;
      }
      if (realDuration <= 45) {
        return 60;
      }
      return Math.ceil(realDuration / 30) * 30;
    },
    getBookingStyle(booking) {
      const openStr = this.timeslots[0];
      const openMin = this.getMinutes(openStr);
      const startMin = this.getMinutes(booking.time);
      let endAt = booking.time;
      if (booking.services?.length) {
        endAt = booking.services[booking.services.length - 1].end_at || booking.time;
      }
      const endMin = this.getMinutes(endAt);
      const realDuration = endMin - startMin;
      const topMin = Math.max(startMin, openMin);
      const pxPerMin = this.slotHeight / this.calcSlotStep();
      const displayDuration = this.getDisplayDuration(booking, realDuration);
      const heightPx = displayDuration * pxPerMin;
      const colIndex = booking._column || 0;
      const leftPx = colIndex * (this.cardWidth + this.gap);
      const topPx = (topMin - openMin) * pxPerMin;

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
    getMinutes(str) {
      const [hh, mm] = str.split(":").map(Number);
      return hh * 60 + mm;
    },
    /*on*/
    onMouseDown(e) {
      if (!this.$refs.dragScrollContainer) return;

      this.possibleDrag = true;
      this.isDragging = false;
      this.wasRecentlyDragging = false;

      this.startX = e.pageX - this.$refs.dragScrollContainer.offsetLeft;
      this.scrollLeft = this.$refs.dragScrollContainer.scrollLeft;
      document.body.style.userSelect = 'none';
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
        this.$refs.dragScrollContainer.scrollLeft = this.scrollLeft - (x - this.startX);
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
      document.body.style.userSelect = '';
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

        if (e.cancelable) {
          e.preventDefault();
        }
        this.$refs.dragScrollContainer.scrollLeft = this.scrollLeft - (x - this.startX);
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
  emits: ["update:modelValue", "add", "showItem", 'lock', 'unlock', 'lock-start', 'lock-end', 'unlock-start', 'unlock-end'],
};
</script>

<style scoped>
.reservations-calendar {
  margin-bottom: 48px;
}

.calendar, .slots, .calendar .btn, .search {
  margin-top: 1.5rem;
}

.title {
  text-align: left;
  font-weight: bold;
  color: #322d38;
  font-size: 22px;
}

.search {
  position: relative;
}

.search-icon {
  position: absolute;
  z-index: 1000;
  top: 12px;
  left: 15px;
  color: #7f8ca2;
}

.search .search-input {
  padding-left: 40px;
  padding-right: 20px;
  border-radius: 30px;
  border-color: #7f8ca2;
}

.clear {
  position: absolute;
  top: 10px;
  z-index: 1000;
  right: 15px;
  cursor: pointer;
}

.slots {
  margin-top: 12px;
  background: #EDF0F5;
  padding: 16px;
  border-radius: 12px;
  position: relative;
}

.slots-inner {
  position: relative;
  display: flex;
}

.time-axis {
  flex-shrink: 0;
}

.time-axis-item {
  color: #5f5f5f;
  padding: 7px 14px 0 0;
  font-size: 14px;
  border-bottom: 1px solid #ddd;
  box-sizing: border-box;
}

.time-axis-item:last-child {
  border-bottom: none;
}

.slots-content {
  position: relative;
  flex: 1;
  overflow-x: auto;
  overflow-y: hidden;
  scrollbar-width: none;
  -webkit-overflow-scrolling: touch;
}

@media (hover: hover) {
  .slots-content {
    cursor: grab;
  }

  .slots-content:active {
    cursor: grabbing;
  }
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

.time-slot-line {
  position: absolute;
  left: 0;
  width: 100%;
  z-index: 5;
  display: flex;
  align-items: center;
  border-top: 1px solid #ddd;
  box-sizing: border-box;
  margin-top: -1px;
  transition: all 0.15s ease;
}

.time-slot-line:first-child {
  border-top: none;
}

.time-slot-line.processing .time-slot-actions,
.time-slot-line.locked .time-slot-actions {
  opacity: 1;
  pointer-events: auto;
}

.time-slot-line.processing :deep(.booking-add),
.time-slot-line.locked :deep(.booking-add) {
  display: none;
}

@media (hover: hover) {
  .time-slot-line:hover {
    background-color: rgb(225 233 247 / 40%) !important;
  }

  .time-slot-line.locked:hover {
    background-color: #f1b0b7 !important;
  }
}

.time-slot-line.active {
  background-color: rgb(237 240 245 / 40%) !important;
  z-index: 6;
  backdrop-filter: blur(5px);
}

.time-slot-line.locked {
  background-color: rgba(248, 215, 218, 0.4) !important;
}

.time-slot-line.processing {
  background-color: #fff3cd;
  cursor: wait;
}

.time-slot-line.processing .time-slot-actions {
  display: flex;
}

.time-slot-actions {
  display: flex;
  align-items: center;
  gap: 95px;
  position: sticky;
  left: 35px;
  width: calc(100vw - 235px);
  justify-content: center;
  opacity: 0;
  pointer-events: none;
  transition: opacity 0.15s ease;
}

.time-slot-line.active .time-slot-actions {
  display: flex;
  opacity: 1;
  pointer-events: auto;
}

.booking-card {
  z-index: 5;
  display: flex;
  padding: 10px 0;
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

.calendar .spinner-border {
  position: absolute;
  top: 45%;
  left: 45%;
}

.calendar :deep(.dp__menu) {
  margin: 0 auto;
}

:deep(.dp__cell_inner) {
  --dp-hover-color: #6983862B;
  height: auto;
  width: auto;
  padding: 0;
  border-radius: 50%;
}

:deep(.dp__calendar_row) {
  margin: 10px 0;
  gap: 10px;
}

:deep(.dp__calendar_header) {
  gap: 9px;
}

:deep(.dp__calendar_header_item) {
  height: 30px;
  width: 45px;
}

:deep(.dp__month_year_select) {
  width: 100%;
  pointer-events: none;
}

:deep(.dp__month_year_select + .dp__month_year_select) {
  display: none;
}

:deep(.dp__cell_inner), :deep(.dp__today), :deep(.dp__menu), :deep(.dp__menu:focus) {
  border: none;
}

:deep(.dp__today:not(.dp__active_date)) .day {
  border-color: green;
  color: green;
}

:deep(.dp__calendar_header_separator) {
  height: 0;
}

:deep(.dp__active_date) {
  background: none;
}

:deep(.dp__active_date) .day {
  background: #04409f;
  border-color: #fff;
  color: #fff;
}

:deep(.dp__active_date) .day.day-holiday {
  background: #a78a8a;
  border-color: #9f04048f;
}

:deep(.dp__active_date) .day.day-with-bookings::before {
  background-color: #fff;
}

.day {
  display: flex;
  align-items: center;
  text-align: center;
  justify-content: center;
  border-radius: 30px;
  font-weight: 500;
  font-size: 16px;
  line-height: 1;
  width: 44px;
  height: 44px;
  padding: 0;
  border: 2px solid #C7CED9;
  box-sizing: border-box;
  position: relative;
}

.day-available-book, .day-with-bookings {
  color: #04409f;
  border-color: #04409f;
}

.day-with-bookings::before {
  content: '';
  position: absolute;
  left: 50%;
  bottom: 4px;
  transform: translateX(-50%);
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background-color: #04409f;
}

.day-disable-book, .day-full-booked {
  border-color: #C7CED9;
  color: #c7ced9;
}

.day-holiday {
  color: #9F04048E;
  border-color: #9F04048F;
}

@media screen and (max-width: 450px) {
  :deep(.dp__calendar_row) {
    margin: 5px 0;
    gap: 5px;
  }

  :deep(.dp__calendar_header) {
    gap: 0;
  }

  .day {
    width: 38px;
    height: 38px;
  }
}

@media screen and (max-width: 361px) {
  :deep(.dp__calendar_header_item) {
    width: 37px;
  }

  .day {
    width: 33px;
    height: 33px;
  }
}

.time-slot-line.processing {
  background-color: #fff3cd !important;
  cursor: wait;
}

.time-slot-line.processing .time-slot-actions {
  display: flex;
}

.selected-date {
  margin-top: 55px;
  font-size: 18px;
  font-weight: 700;
  color: #322d38;
  text-align: left;
}

@media screen and (max-width: 600px) {
  .selected-date {
    font-size: 16px;
    margin-top: 32px;
  }
}
</style>
