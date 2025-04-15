<template>
  <teleport to="body">
    <div class="modal-root" v-if="show">
      <div class="modal-backdrop" @click="close"></div>
      <div class="modal-container">
        <div class="modal-content">
          <div class="modal-item" @click="editBooking">
            <div class="modal-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                   stroke="#ffffff"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"></path>
              </svg>
            </div>
            <div class="modal-text">
              {{ this.getLabel('bookingActionEdit') }}
            </div>
          </div>

          <div class="modal-divider"></div>

          <div class="modal-item" @click="deleteBooking">
            <div class="modal-icon">
              <svg xmlns="http://www.w3.org/2000/svg"
                   viewBox="0 0 448 512">
                <path fill="#fff"
                      d="M135.2 17.7C140.6 6.8 151.7 0 163.8 0L284.2 0c12.1 0 23.2 6.8 28.6 17.7L320 32l96 0c17.7 0 32 14.3 32 32s-14.3 32-32 32L32 96C14.3 96 0 81.7 0 64S14.3 32 32 32l96 0 7.2-14.3zM32 128l384 0 0 320c0 35.3-28.7 64-64 64L96 512c-35.3 0-64-28.7-64-64l0-320zm96 64c-8.8 0-16 7.2-16 16l0 224c0 8.8 7.2 16 16 16s16-7.2 16-16l0-224c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16l0 224c0 8.8 7.2 16 16 16s16-7.2 16-16l0-224c0-8.8-7.2-16-16-16zm96 0c-8.8 0-16 7.2-16 16l0 224c0 8.8 7.2 16 16 16s16-7.2 16-16l0-224c0-8.8-7.2-16-16-16z"/>
              </svg>
            </div>
            <div class="modal-text">
              {{ this.getLabel('bookingActionDelete') }}
            </div>
          </div>

          <div class="modal-divider"></div>

          <div class="modal-item" v-if="hasPhone" @click="callCustomer">
            <div class="modal-icon">
              <svg xmlns="http://www.w3.org/2000/svg"
                   viewBox="0 0 512 512">
                <path fill="#fff"
                      d="M497.4 361.8l-112-48a24 24 0 0 0 -28 6.9l-49.6 60.6A370.7 370.7 0 0 1 130.6 204.1l60.6-49.6a23.9 23.9 0 0 0 6.9-28l-48-112A24.2 24.2 0 0 0 122.6 .6l-104 24A24 24 0 0 0 0 48c0 256.5 207.9 464 464 464a24 24 0 0 0 23.4-18.6l24-104a24.3 24.3 0 0 0 -14-27.6z"/>
              </svg>
            </div>
            <div class="modal-text">
              {{ this.getLabel('bookingActionCallCustomer') }}
            </div>
          </div>

          <div class="modal-divider" v-if="hasPhone"></div>

          <div class="modal-item" v-if="hasPhone" @click="whatsappCustomer">
            <div class="modal-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="31.5" height="31.5" viewBox="52.15 351.25 31.5 31.5">
                <path
                    d="M78.932 355.827a15.492 15.492 0 0 0-11.04-4.577c-8.605 0-15.609 7.003-15.609 15.61 0 2.749.718 5.435 2.082 7.804l-2.215 8.086 8.276-2.173a15.562 15.562 0 0 0 7.46 1.899h.007c8.6 0 15.757-7.003 15.757-15.61 0-4.17-1.772-8.086-4.718-11.039Zm-11.04 24.02c-2.334 0-4.619-.627-6.609-1.808l-.47-.281-4.908 1.287 1.307-4.789-.309-.492a12.931 12.931 0 0 1-1.983-6.905c0-7.15 5.822-12.972 12.98-12.972 3.466 0 6.722 1.35 9.169 3.804 2.447 2.454 3.951 5.709 3.944 9.175 0 7.158-5.97 12.98-13.12 12.98Zm7.116-9.718c-.386-.197-2.306-1.14-2.664-1.266-.359-.133-.62-.197-.88.197s-1.005 1.266-1.237 1.533c-.225.26-.457.295-.844.098-2.292-1.146-3.796-2.046-5.308-4.64-.4-.69.4-.64 1.146-2.13.127-.26.063-.486-.035-.683-.099-.197-.88-2.116-1.203-2.897-.316-.759-.64-.654-.878-.668-.225-.014-.486-.014-.746-.014s-.682.099-1.04.486c-.359.393-1.364 1.335-1.364 3.255s1.399 3.776 1.589 4.036c.197.26 2.749 4.198 6.665 5.892 2.475 1.069 3.446 1.16 4.683.977.752-.112 2.306-.942 2.63-1.856.323-.914.323-1.694.225-1.856-.092-.176-.352-.274-.739-.464Z"
                    fill="#fff" fill-rule="evenodd"/>
              </svg>
            </div>
            <div class="modal-text">
              {{ this.getLabel('bookingActionWhatsappCustomer') }}
            </div>
          </div>

          <div class="modal-divider" v-if="hasCustomer"></div>

          <div class="modal-item" v-if="hasCustomer" @click="openCustomerProfile">
            <div class="modal-icon">
              <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                   stroke="currentColor"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-user">
                <path stroke="#fff" d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                <circle stroke="#fff" cx="12" cy="7" r="4"></circle>
              </svg>
            </div>
            <div class="modal-text">
              {{ this.getLabel('bookingActionOpenProfile') }}
            </div>
          </div>
        </div>
        <div class="modal-close" @click="close">
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
               stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-x">
            <line stroke="#fff" x1="18" y1="6" x2="6" y2="18"></line>
            <line stroke="#fff" x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </div>
      </div>
    </div>
  </teleport>
</template>

<script>
export default {
  name: 'CustomerActionsMenu',
  props: {
    booking: {
      type: Object,
      required: true
    },
    show: {
      type: Boolean,
      default: false
    }
  },
  data() {
    return {
      originalOverflow: ''
    }
  },
  computed: {
    customerPhone() {
      return this.booking?.customer_phone_country_code
          ? this.booking.customer_phone_country_code + this.booking.customer_phone
          : this.booking?.customer_phone || '';
    },
    hasPhone() {
      return !!this.customerPhone;
    },
    hasCustomer() {
      return !!this.booking?.customer_id;
    }
  },
  watch: {
    show(newVal) {
      if (newVal) {
        this.originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
      } else {
        document.body.style.overflow = this.originalOverflow;
      }
    }
  },
  methods: {
    close() {
      this.$emit('close');
    },
    editBooking() {
      this.$emit('edit', this.booking);
      this.close();
    },
    deleteBooking() {
      this.$emit('delete', this.booking.id);
      this.close();
    },
    callCustomer() {
      if (this.customerPhone) {
        window.open(`tel:${this.customerPhone}`, '_blank');
      }
      this.close();
    },
    whatsappCustomer() {
      if (this.customerPhone) {
        const digitsOnly = this.customerPhone.replace(/\D/g, '');
        window.open(`https://wa.me/${digitsOnly}`, '_blank');
      }
      this.close();
    },
    openCustomerProfile() {
      if (this.hasCustomer) {
        this.$emit('view-profile', {
          id: this.booking.customer_id,
          first_name: this.booking.customer_first_name,
          last_name: this.booking.customer_last_name,
          email: this.booking.customer_email,
          phone: this.customerPhone,
          address: this.booking.customer_address,
          note: this.booking.customer_personal_note
        });
      }
      this.close();
    }
  },
  beforeUnmount() {
    if (this.show) {
      document.body.style.overflow = this.originalOverflow;
    }
  },
  emits: ['close', 'edit', 'delete', 'view-profile']
}
</script>

<style scoped>
.modal-root {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 999;
}

.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  z-index: 99;
}

.modal-container {
  position: relative;
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 100;
  padding: 10px;
  width: 100%;
  max-width: 450px;
}

.modal-content {
  width: 100%;
  max-width: 450px;
  background: #8B9098;
  border-radius: 5px;
  position: relative;
  padding: 52px 32px;
  z-index: 100;
}

.modal-close {
  position: absolute;
  top: 15px;
  right: 15px;
  width: 40px;
  height: 40px;
  z-index: 101;
  display: flex;
  justify-content: center;
  align-items: center;
  cursor: pointer;
  font-size: 24px;
}

.modal-item {
  display: flex;
  align-items: center;
  padding: 25px 0 15px;
  cursor: pointer;
  transition: background-color 0.2s;
}

.modal-icon {
  width: 30px;
  height: 30px;
  display: flex;
  justify-content: center;
  align-items: center;
  margin-right: 24px;
}

.modal-icon svg {
  width: 100%;
  height: 100%;
}

.modal-text {
  color: #FFFFFF;
  font-size: 18px;
  font-weight: 500;
}

.modal-divider {
  height: 1px;
  background-color: rgba(255, 255, 255, 0.2);
  margin: 0;
}
</style>