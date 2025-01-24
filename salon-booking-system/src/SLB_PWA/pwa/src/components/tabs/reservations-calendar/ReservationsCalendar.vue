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
          <div v-if="isLoadingTimeslots || !attendantsLoaded" class="spinner-wrapper"/>
          <b-spinner v-if="isLoadingTimeslots || !attendantsLoaded" variant="primary"/>
        </div>
      </b-col>
    </b-row>

    <div class="slots-headline">
      <div class="selected-date">
        {{ formattedDate }}
      </div>
      <div class="attendant-toggle" v-if="$root.settings?.attendant_enabled && attendants.length > 0">
        Assistants view
        <b-form-checkbox v-model="isAttendantView" switch size="lg">
          {{ getLabel('attendantViewLabel') }}
        </b-form-checkbox>
      </div>
    </div>

    <div class="slots" :class="{ 'slots--assistants': isAttendantView }" ref="slotsContainer">
      <b-spinner variant="primary" v-if="isLoadingTimeslots || !attendantsLoaded"/>
      <div v-else-if="timeslots.length > 0 && attendantsLoaded" class="slots-inner">
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
          <div v-if="attendantsLoaded" class="attendants-list"
               :class="{ 'attendants-list--hidden': !shouldShowAttendants }">
            <div v-for="(attendant, index) in sortedAttendants" :key="attendant.id" class="attendant-column"
                 :style="{ width: columnWidths[attendant.id] + 'px', marginRight: (index === sortedAttendants.length - 1 ? 0 : attendantColumnGap) + 'px' }">
              <div class="attendant-header">
                <div class="attendant-avatar">
                  <img
                      v-if="attendant.image_url"
                      :src="attendant.image_url"
                      :alt="attendant.name"
                  />
                  <font-awesome-icon v-else icon="fa-solid fa-user-alt" class="default-avatar-icon"/>
                </div>
                <div class="attendant-name" :title="attendant.name">
                  {{ attendant.name }}
                </div>
              </div>
            </div>
          </div>
          <div class="bookings-canvas" :style="canvasStyle">
            <div v-if="isAttendantView" class="assistant-columns">
              <div v-for="(attendant, index) in sortedAttendants"
                   :key="'col-' + attendant.id"
                   class="assistant-column-highlight"
                   :style="{
                      width: (columnWidths[attendant.id] || attendantColumnWidth) + 'px',
                      left: getAssistantColumnLeft(index) + 'px'
                    }"/>
            </div>
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
                v-for="(booking, bIndex) in processedBookings"
                :key="booking.id + (booking._serviceTime?.start || '')"
                class="booking-card"
                :class="{ 'booking-card--default-duration': booking._isDefaultDuration }"
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

function debounce(fn, delay = 300) {
  let timer = null;
  return (...args) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
      fn(...args);
    }, delay);
  };
}

export default {
  name: "ReservationsCalendar",
  components: {
    BookingCard,
    BookingBlockSlot,
    BookingAdd
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

      // search
      searchTimeout: null
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
      if (!this.$refs.dragScrollContainer) return 500;
      return this.$refs.dragScrollContainer.clientWidth;
    },
    canvasHeight() {
      return this.timeslots.length * this.slotHeight;
    },
    formattedDate() {
      return this.moment(this.modelValue).format("dddd DD YYYY");
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
    search: {
      handler: debounce(function (newVal) {
        this.activeSlotIndex = -1;
        if (newVal) {
          this.loadSearchBookingsList();
        } else {
          this.loadBookingsList();
        }
      }, 600),
      immediate: false
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
    }
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
    loadTimeslots() {
      this.isLoadingTimeslots = true;
      this.axios
          .get("calendar/intervals", {params: {shop: this.shop?.id || null}})
          .then((r) => {
            this.timeslots = r.data.items;
            this.loadAvailabilityIntervals();
            this.$nextTick(() => this.updateCurrentTimeLinePosition());
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
            }
          })
          .then((response) => {
            this.lockedTimeslots = response.data.items;
            setTimeout(() => {
              this.loadAvailabilityIntervals();
            }, 300);
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
    loadBookingsList() {
      this.isLoadingTimeslots = true;
      this.bookingsList = [];
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
    loadSearchBookingsList() {
      if (this.searchTimeout) clearTimeout(this.searchTimeout);
      this.searchTimeout = setTimeout(() => {
        this.isLoadingTimeslots = true;
        this.bookingsList = [];
        const currentView = this.isAttendantView;

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
              this.isAttendantView = currentView;
            })
            .finally(() => {
              this.isLoadingTimeslots = false;
            });
      }, 1000);
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
          .get("assistants", {
            params: {
              shop: this.shop?.id || null,
              per_page: -1
            }
          })
          .then((response) => {
            this.attendants = response.data.items;
            this.attendantsLoaded = true;
          })
          .catch((error) => {
            // eslint-disable-next-line
            console.error("Error loading attendants:", error);
            this.attendantsLoaded = true;
          });
    },
    handleLockStart(slot) {
      if (!this.processingSlots.some(
          s => s.from_time === slot.from_time && s.to_time === slot.to_time
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
    handleMonthYear({year, month}) {
      const fd = new Date(year, month, 1);
      const td = new Date(year, month + 1, 0);
      this.loadAvailabilityStats(fd, td);
    },
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
      if (this.isHoliday(this.date)) return false;

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
      if (this.timeslots.length < 2) return 30;
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
    "add",
    "showItem",
    "lock",
    "unlock",
    "lock-start",
    "lock-end",
    "unlock-start",
    "unlock-end"
  ],
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

.slots.slots--assistants .slots-content, .slots.slots--assistants .time-axis {
  padding-top: 64px;
}

.slots.slots--assistants .current-time-line {
  margin-top: 64px;
}

.slots-inner {
  position: relative;
  display: flex;
}

.time-axis {
  flex-shrink: 0;
  transition: .15s ease-in-out;
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

.time-slot-line.processing .time-slot-actions, .time-slot-line.locked .time-slot-actions {
  opacity: 1;
  pointer-events: auto;
}

.time-slot-line.processing :deep(.booking-add), .time-slot-line.locked :deep(.booking-add) {
  display: none;
}


.time-slot-line.active {
  background-color: rgb(237 240 245 / 40%) !important;
  z-index: 13;
  backdrop-filter: blur(5px);
}

.time-slot-line.locked {
  z-index: 13;
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

.time-slot-line.processing {
  background-color: #fff3cd !important;
  cursor: wait;
}

.time-slot-line.processing .time-slot-actions {
  display: flex;
}

.attendant-toggle {
  margin-top: 55px;
  color: #4A454F;
  font-weight: 400;
  font-size: 14px;
  user-select: none;
}

.selected-date {
  margin-top: 55px;
  font-size: 18px;
  font-weight: 700;
  color: #322d38;
  text-align: left;
}

.slots-headline {
  display: flex;
  align-items: center;
}

.attendants-list {
  display: flex;
  position: absolute;
  top: 0;
  z-index: 10;
  padding: 8px 0;
  opacity: 1;
  transform: translateY(-10px);
  transition: opacity 0.3s ease, transform 0.3s ease;
  width: 100%;
  overflow: hidden;
}

.attendants-list--hidden {
  opacity: 0;
  transform: translateY(-10px);
}

.attendant-name {
  font-weight: 500;
  color: #333;
}

.attendant-toggle {
  margin-left: auto;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
}

.slots--assistants .attendants-list {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 20;
  padding: 8px 0;
  display: flex;
  width: fit-content;
}

.attendant-header {
  position: relative;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgb(255 255 255 / 50%);
  border-radius: 8px;
  height: 48px;
  box-shadow: 0 1px 2px rgba(0, 0, 0, .05);
}

.attendant-header::after {
  content: '';
  position: absolute;
  top: -2px;
  right: -2px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background-color: #28a745;
  opacity: 0;
  transform: scale(0);
  transition: all 0.3s ease;
}

.attendant-column {
  flex-shrink: 0;
}

.attendant-column[data-has-bookings="true"] .attendant-header::after {
  opacity: 1;
  transform: scale(1);
}

.assistant-column-highlight {
  position: absolute;
  top: 0;
  bottom: 0;
  background: rgba(171, 180, 187, .33);
  z-index: 10;
  border-radius: 8px;
  user-select: none;
  pointer-events: none;
}

.slots--assistants .slots-content {
  padding-top: 64px;
  position: relative;
  overflow-x: auto;
  -webkit-overflow-scrolling: touch;
}

.slots--assistants .bookings-canvas {
  min-width: fit-content;
}

.attendant-avatar {
  display: flex;
  justify-content: center;
  align-items: center;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  overflow: hidden;
  border: 2px solid #fff;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  color: #04409F;
}

.attendant-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.attendant-name {
  font-weight: 500;
  color: #333;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 200px;
}

:deep(.booking) {
  box-shadow: 0 0 10px 5px rgb(0 0 0 / 10%);
}

:deep(.form-check-input:focus), :deep(.form-check-input) {
  box-shadow: none;
  border-color: rgba(0, 0, 0, .25);
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3E%3Ccircle r='3' fill='rgba(0, 0, 0, 0.25)'/%3E%3C/svg%3E");
}

:deep(.form-check-input:checked) {
  background-color: #04409F;
  border-color: #04409F;
  background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='-4 -4 8 8'%3E%3Ccircle r='3' fill='%23fff'/%3E%3C/svg%3E") !important;
}


@media (hover: hover) {
  .slots-content {
    cursor: grab;
  }

  .slots-content:active {
    cursor: grabbing;
  }

  .time-slot-line:hover {
    background-color: rgb(225 233 247 / 40%) !important;
  }

  .time-slot-line.locked:hover {
    background-color: #f1b0b7 !important;
  }
}

@media screen and (max-width: 600px) {
  .attendant-toggle {
    margin-top: 32px;
  }

  .selected-date {
    font-size: 16px;
    margin-top: 32px;
  }
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

</style>
