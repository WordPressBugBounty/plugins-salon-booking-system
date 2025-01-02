<template>
  <div>
    <h5 class="title">
      {{ this.getLabel("reservationsCalendarTitle") }}
    </h5>
    <div class="search">
      <font-awesome-icon icon="fa-solid fa-magnifying-glass" class="search-icon" />
      <b-form-input v-model="search" class="search-input"></b-form-input>
      <font-awesome-icon
        icon="fa-solid fa-circle-xmark"
        class="clear"
        @click="search = ''"
        v-if="search"
      />
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
            :locale="locale"
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
    <b-row>
      <b-col sm="12">
        <div class="attendants">
          <b-button
            v-for="attendant in attendants"
            :key="attendant.id"
            variant="outline-primary"
            :pressed="filterAttendant === attendant.id"
            @click="
              filterAttendant === attendant.id
                ? (filterAttendant = '')
                : (filterAttendant = attendant.id)
            "
          >
            {{ attendant.name }}
          </b-button>
        </div>
      </b-col>
    </b-row>
    <div class="slots">
      <b-spinner variant="primary" v-if="isLoadingTimeslots"></b-spinner>
      <div v-else-if="timeslots.length > 0">
        <template v-for="(timeslot, index) in timeslots" :key="timeslot">
          <BookingSlot
            :timeslot="timeslot"
            @add="add(timeslot)"
            :isAvailable="isAvailable(timeslot, timeslot)"
          />
          <template v-if="getBookingsListByTime(timeslot, timeslots[index + 1]).length > 0">
            <BookingItem
              v-for="booking in getBookingsListByTime(timeslot, timeslots[index + 1])"
              :key="booking.id"
              :booking="booking"
              @deleteItem="deleteItem(booking.id)"
              @showDetails="showDetails(booking)"
            />
          </template>
          <BookingBlockSlot
            v-else-if="index < timeslots.length - 1"
            :start="timeslots[index]"
            :end="timeslots[index + 1]"
            :date="date"
            :shop="shop"
            @lock="lock"
            @unlock="unlock"
            :isLock="isLock(timeslots[index], timeslots[index + 1])"
          />
        </template>
      </div>
      <span v-else>{{ this.getLabel("noResultTimeslotsLabel") }}</span>
    </div>
  </div>
</template>

<script>
import BookingSlot from "./BookingSlot.vue";
import BookingBlockSlot from "./BookingBlockSlot.vue";
import BookingItem from "./../upcoming-reservations/BookingItem.vue";

export default {
  name: "ReservationsCalendar",
  props: {
    shop: {
        default: function () {
            return {};
        },
    }
  },
  mounted() {
    this.load()
    setTimeout(() => {
      window.document
        .querySelectorAll(".dp__calendar")[0]
        .appendChild(window.document.querySelectorAll(".spinner-wrapper")[0]);
      window.document
        .querySelectorAll(".dp__calendar")[0]
        .appendChild(window.document.querySelectorAll(".calendar .spinner-border")[0]);
    }, 0);
    setInterval(() => this.update(), 60000)
  },
  data: function () {
    return {
      date: new Date(),
      timeslots: [],
      isLoadingTimeslots: false,
      lockedTimeslots: [],
      availabilityStats: [],
      bookingsList: [],
      filterAttendant: "",
      search: "",
      timeout: null,
      availabilityIntervals: {},
      isLoadingCalendar: false,
    };
  },
  watch: {
    date() {
      this.loadLockedTimeslots();
      this.loadBookingsList();
      this.loadAvailabilityIntervals();
    },
    search(newVal) {
      if (newVal) {
        this.loadSearchBookingsList();
      } else {
        this.loadBookingsList();
      }
    },
    timeslots() {
      this.loadAvailabilityIntervals();
    },
    lockedTimeslots() {
      if (this.timeslots.length > 0) {
        this.loadAvailabilityIntervals();
      }
    },
    shop() {
        this.load()
    },
  },
  computed: {
    attendants() {
      var attendants = {};
      attendants[0] = {
        id: "",
        name: this.getLabel("allTitle"),
      };
      this.bookingsList.forEach((booking) => {
        booking.services.forEach((service) => {
          if (service.assistant_id > 0) {
            attendants[service.assistant_id] = {
              id: service.assistant_id,
              name: service.assistant_name,
            };
          }
        });
      });
      return Object.values(attendants).length > 1 ? Object.values(attendants) : [];
    },
    filteredBookingsList() {
      return this.bookingsList.filter((booking) => {
        var existsAttendant = false;
        booking.services.forEach((service) => {
          if (this.filterAttendant === service.assistant_id) {
            existsAttendant = true;
          }
        });
        return this.filterAttendant === "" || existsAttendant;
      });
    },
  },
  methods: {
    load() {
        this.loadTimeslots();
        this.loadLockedTimeslots();
        var date = this.date,
          y = date.getFullYear(),
          m = date.getMonth();
        var firstDate = new Date(y, m, 1);
        var lastDate = new Date(y, m + 1, 0);
        this.loadAvailabilityStats(firstDate, lastDate);
        this.loadBookingsList();
    },
    add(timeslot) {
      this.$emit("add", this.date, timeslot ? timeslot : this.timeslots[0]);
    },
    lock(lockedItems) {
      this.lockedTimeslots = lockedItems;
    },
    unlock(lockedItems) {
      this.lockedTimeslots = lockedItems;
    },
    loadTimeslots() {
      this.isLoadingTimeslots = true;
      this.axios
        .get("calendar/intervals", {params: {shop: this.shop ? this.shop.id : null}})
        .then((response) => {
          this.timeslots = response.data.items;
        })
        .finally(() => {
          this.isLoadingTimeslots = false;
        });
    },
    loadLockedTimeslots() {
      this.isLoadingTimeslots = true;
      this.axios
        .get("holiday-rules", {
          params: { date: this.moment(this.date).format("YYYY-MM-DD"), shop: this.shop ? this.shop.id : null },
        })
        .then((response) => {
          this.lockedTimeslots = response.data.items;
        })
        .finally(() => {
          this.isLoadingTimeslots = false;
        });
    },
    loadAvailabilityStats(from_date, to_date) {
      this.isLoadingCalendar = true;
      this.axios
        .get("availability/stats", {
          params: {
            from_date: this.moment(from_date).format("YYYY-MM-DD"),
            to_date: this.moment(to_date).format("YYYY-MM-DD"),
            shop: this.shop ? this.shop.id : null,
          },
        })
        .then((response) => {
          this.availabilityStats = response.data.stats;
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
                'sln-b-pendingpayment',
                'sln-b-pending',
                'sln-b-paid',
                'sln-b-paylater',
                'sln-b-canceled',
                'sln-b-confirmed'
            ],
            shop: this.shop ? this.shop.id : null,
          },
        })
        .then((response) => {
          this.bookingsList = response.data.items;
        })
        .finally(() => {
          this.isLoadingTimeslots = false;
        });
    },
    loadAvailabilityIntervals() {
      this.axios
        .post("availability/intervals", {
          date: this.moment(this.date).format("YYYY-MM-DD"),
          time: this.timeslots[0],
          shop: this.shop ? this.shop.id : 0,
        })
        .then((response) => {
          this.availabilityIntervals = response.data.intervals;
        });
    },
    isAvailable(start) {
      if (
        this.availabilityIntervals.universalSuggestedDate !==
        this.moment(this.date).format("YYYY-MM-DD")
      ) {
        return false;
      }
      if (Object.values(this.availabilityIntervals.times).indexOf(start) > -1) {
        return true;
      }
      return false;

    },
    isLock(start, end) {
      var result = false;
      this.lockedTimeslots.forEach((i) => {
        var fromDateTime = this.moment(
          i.from_date + " " + i.from_time,
          "YYYY-MM-DD HH:mm"
        ).unix();
        var toDateTime = this.moment(
          i.to_date + " " + i.to_time,
          "YYYY-MM-DD HH:mm"
        ).unix();
        var date = this.moment(this.date).format("YYYY-MM-DD");
        var currentFromDateTime = this.moment(
          date + " " + start,
          "YYYY-MM-DD HH:mm"
        ).unix();
        var currentToDateTime = this.moment(date + " " + end, "YYYY-MM-DD HH:mm").unix();
        if (
          fromDateTime <= currentFromDateTime &&
          toDateTime >= currentToDateTime &&
          toDateTime > currentFromDateTime
        ) {
          result = true;
        }
      });
      return result;
    },
    handleMonthYear(obj) {
      var firstDate = new Date(obj.year, obj.month, 1);
      var lastDate = new Date(obj.year, obj.month + 1, 0);
      this.loadAvailabilityStats(firstDate, lastDate);
    },
    isDayWithBookings(date) {
      var result = false;
      this.availabilityStats.forEach((i) => {
        if (
          i.date === this.moment(date).format("YYYY-MM-DD") &&
          i.data &&
          i.data.bookings > 0
        ) {
          result = true;
        }
      });
      return result;
    },
    isAvailableBookings(date) {
      var result = false;
      this.availabilityStats.forEach((i) => {
        if (
          i.date === this.moment(date).format("YYYY-MM-DD") &&
          i.available &&
          !i.full_booked
        ) {
          result = true;
        }
      });
      return result;
    },
    isDayFullBooked(date) {
      var result = false;
      this.availabilityStats.forEach((i) => {
        if (i.date === this.moment(date).format("YYYY-MM-DD") && i.full_booked) {
          result = true;
        }
      });
      return result;
    },
    isHoliday(date) {
      return this.availabilityStats.some(
          (i) =>
              i.date === this.moment(date).format("YYYY-MM-DD") &&
              i.error &&
              i.error.type === "holiday_rules"
      );
    },
    getBookingsListByTime(timeStart, timeEnd) {
      return this.filteredBookingsList.filter((i) => this.moment(i.time, 'HH:mm').unix() >= this.moment(timeStart, 'HH:mm').unix() && (!timeEnd || this.moment(i.time, 'HH:mm').unix() < this.moment(timeEnd, 'HH:mm').unix())).sort((a, b) => this.moment(a.time, 'HH:mm').unix() - this.moment(b.time, 'HH:mm').unix());
    },
    deleteItem(id) {
      this.axios.delete("bookings/" + id).then(() => {
        this.bookingsList = this.bookingsList.filter((item) => item.id !== id);
      });
    },
    showDetails(booking) {
      this.$emit("showItem", booking);
    },
    loadSearchBookingsList() {
      this.timeout && clearTimeout(this.timeout);
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
                'sln-b-pendingpayment',
                'sln-b-pending',
                'sln-b-paid',
                'sln-b-paylater',
                'sln-b-canceled',
                'sln-b-confirmed'
              ],
              shop: this.shop ? this.shop.id : null,
            },
          })
          .then((response) => {
            this.bookingsList = response.data.items;
          })
          .finally(() => {
            this.isLoadingTimeslots = false;
          });
      }, 1000);
    },
    update() {
        this.updateBookingsList();
    },
    updateBookingsList() {
      this.axios
        .get("bookings", {
          params: {
            start_date: this.moment(this.date).format("YYYY-MM-DD"),
            end_date: this.moment(this.date).format("YYYY-MM-DD"),
            per_page: -1,
            statuses: [
                'sln-b-pendingpayment',
                'sln-b-pending',
                'sln-b-paid',
                'sln-b-paylater',
                'sln-b-canceled',
                'sln-b-confirmed'
            ],
            shop: this.shop ? this.shop.id : null,
          },
        })
        .then((response) => {
            this.bookingsList = response.data.items;
        })
    },
  },
  components: {
    BookingSlot,
    BookingBlockSlot,
    BookingItem,
  },
  emits: ["add", "showItem"],
};
</script>

<style scoped>
.calendar,
.slots,
.calendar .btn,
.attendants,
.search {
  margin-top: 1.5rem;
}
.calendar :deep(.dp__menu) {
  margin: 0 auto;
}
:deep(.dp__cell_inner) {
  --dp-hover-color: #6983862B;
  height: auto;
  width: auto;
  padding: 0;
  border: none;
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
.day-available-book,.day-with-bookings {
  color: #04409F;
  border-color: #04409F;
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
  background-color: #04409F;
}
.day-disable-book,
.day-full-booked {
  border-color: #C7CED9;
  color: #c7ced9;
}
.day-holiday {
  color: #9F04048E;
  border-color: #9F04048F;
}
@media screen and (max-width: 450px){
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
@media screen and (max-width: 361px){
  :deep(.dp__calendar_header_item) {
    width: 37px;
  }
  .day {
    width: 33px;
    height: 33px;
  }
}
.attendants .btn {
  margin-right: 20px;
}
.search {
  position: relative;
}
.clear {
  position: absolute;
  top: 10px;
  z-index: 1000;
  right: 15px;
  cursor: pointer;
}
.title {
  text-align: left;
  font-weight: bold;
  color: #322d38;
  font-size: 22px;
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
.attendants .btn {
  border-radius: 30px;
  padding: 4px 20px;
  background-color: #e1e6ef9b;
  color: #04409f;
  border-color: #7f8ca2;
}
.attendants .btn:hover, .attendants .btn.active {
  color: #04409f;
  background-color: #7f8ca2;
  border-color: #7f8ca2;
}
.attendants {
  white-space: nowrap;
  overflow: auto;
}
.attendants::-webkit-scrollbar {
  display: none;
}
:deep(.dp__month_year_select) {
  width: 100%;
  pointer-events: none;
}
:deep(.dp__month_year_select + .dp__month_year_select) {
  display: none;
}
:deep(.dp__menu),
:deep(.dp__menu:focus) {
  border: none;
}
:deep(.dp__calendar_header_separator) {
  height: 0;
}
:deep(.dp__today) {
  border: none;
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
.spinner-wrapper {
  width: 100%;
  height: 100%;
  position: absolute;
  background-color: #e0e0e0d1;
  opacity: 0.5;
  top: 0;
}
.calendar .spinner-border {
  position: absolute;
  top: 45%;
  left: 45%;
}
</style>
