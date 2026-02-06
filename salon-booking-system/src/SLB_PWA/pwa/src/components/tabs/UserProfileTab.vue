<template>
  <div>
    <!-- Toast container for notifications -->
    <b-toaster name="b-toaster-top-center" class="toast-container-custom"></b-toaster>
    
    <b-spinner variant="primary" v-if="isLoading"></b-spinner>
    <div v-else-if="user" class="user-profile">
      <div class="user-profile-top">
        <h2 class="user-profile-name">{{ user.name }}</h2>
        <p class="user-profile-email">{{ user.email }}</p>
        <p class="user-profile-role">{{ user.role }}</p>
      </div>
      
      <!-- Admin-only: Calendar Reset Button -->
      <div v-if="isAdmin" class="admin-tools-section">
        <h3 class="admin-tools-title">Administrator Tools</h3>
        <button 
          class="btn-reset-calendar"
          @click="resetCalendar"
          :disabled="isResetting"
          title="Reset calendar cache - clears all cached data and reloads from server"
        >
          <i class="fas fa-sync-alt" :class="{ 'fa-spin': isResetting }"></i>
          {{ isResetting ? 'Resetting...' : 'Reset Calendar Cache' }}
        </button>
        <p class="admin-tools-description">
          Use this to clear calendar cache and reload all data from the server. 
          Only use if you experience data synchronization issues.
        </p>
      </div>
      
      <b-button class="btn-logout" variant="primary" @click="logOut">Log-out</b-button>
    </div>
    <div v-else>
      <p>Failed to load user information. Please try again.</p>
    </div>
  </div>
</template>

<script>
export default {
  name: 'UserProfileTab',
  data() {
    return {
      isLoading: true,
      user: null,
      isResetting: false,
    };
  },
  computed: {
    isAdmin() {
      // Check if user has administrator role
      return this.user?.role?.toLowerCase().includes('admin') || 
             this.user?.role?.toLowerCase().includes('administrator');
    }
  },
  methods: {
    loadUserProfile() {
      this.isLoading = true;
      this.axios
          .get('/users/current')
          .then((response) => {
            this.user = response.data;
          })
          .catch((error) => {
            // eslint-disable-next-line
            console.error('Error loading user profile:', error);
            this.user = null;
          })
          .finally(() => {
            this.isLoading = false;
          });
    },
    logOut() {
      this.axios
          .post('/users/logout')
          .then(() => {
            this.user = null;
            window.location.href = '/';
          })
          .catch((error) => {
            // eslint-disable-next-line
            console.error('Logout failed:', error);
          });
    },
    async resetCalendar() {
      if (this.isResetting) return;
      
      try {
        this.isResetting = true;
        
        console.log('=== ADMIN: CALENDAR RESET INITIATED ===');
        
        // 1. Clear localStorage cache
        console.log('1. Clearing localStorage cache...');
        const localStorageKeys = Object.keys(localStorage);
        let clearedCount = 0;
        localStorageKeys.forEach(key => {
          if (key.startsWith('sln_') || key.startsWith('salon_')) {
            localStorage.removeItem(key);
            clearedCount++;
          }
        });
        console.log(`   Cleared ${clearedCount} localStorage items`);
        
        // 2. Clear sessionStorage cache
        console.log('2. Clearing sessionStorage...');
        sessionStorage.clear();
        
        // 3. Notify user to refresh calendar tab
        console.log('3. Calendar cache cleared successfully');
        console.log('=== ADMIN: CALENDAR RESET COMPLETE ===');
        
        // Show success message
        this.$bvToast.toast(
          'Calendar cache has been cleared. Please switch to the Calendar tab to reload data.',
          {
            title: 'Cache Cleared',
            variant: 'success',
            solid: true,
            autoHideDelay: 5000,
          }
        );
        
      } catch (error) {
        console.error('Error during calendar reset:', error);
        
        this.$bvToast.toast(
          'Failed to clear calendar cache. Please try again or contact support.',
          {
            title: 'Reset Failed',
            variant: 'danger',
            solid: true,
            autoHideDelay: 5000,
          }
        );
      } finally {
        this.isResetting = false;
      }
    },
  },
  mounted() {
    this.loadUserProfile();
  },
};
</script>

<style scoped>
.user-profile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 293px;
  padding: 36px 30px 75px;
  background-color: #F3F6FC;
  border-radius: 3px;
}

.user-profile .user-profile-top {
  text-align: left;
  width: 100%;
}

.user-profile .user-profile-name {
  font-size: 26px;
  line-height: 32px;
  font-weight: 700;
  color: #322D38;
  text-transform: capitalize;
  margin: 0 0 22px;
}

.user-profile p {
  margin-bottom: 0;
  font-size: 22px;
  line-height: 27px;
  color: #7F8CA2;
  overflow: hidden;
  text-overflow: ellipsis;
}

.user-profile .user-profile-email {
  padding-bottom: 10px;
}

.user-profile .user-profile-role {
  text-transform: capitalize;
}

.user-profile .btn-logout {
  font-size: 25px;
  line-height: 1;
  letter-spacing: 1.75px;
  font-weight: 500;
  padding: 19px;
  display: flex;
  justify-content: center;
  align-items: center;
  color: #04409F;
  background-color: #F3F6FC;
  border: 2px solid #04409F;
  border-radius: 3px;
  max-width: 318px;
  width: 100%;
  margin: auto;
  transition: all .3s ease;
}

.user-profile .btn-logout:active,
.user-profile .btn-logout:hover {
  color: #F3F6FC;
  background-color: #7f8ca2;
  border-color: #7f8ca2;
}

/* Admin Tools Section */
.admin-tools-section {
  width: 100%;
  padding: 20px;
  background-color: #FFF9E6;
  border: 2px solid #FFC107;
  border-radius: 8px;
  margin: 20px 0;
}

.admin-tools-title {
  font-size: 18px;
  font-weight: 700;
  color: #FF9800;
  margin: 0 0 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.admin-tools-title::before {
  content: "⚙️";
  font-size: 20px;
}

.admin-tools-description {
  font-size: 13px;
  color: #7F8CA2;
  margin: 8px 0 0;
  line-height: 1.4;
}

.btn-reset-calendar {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px 16px;
  background-color: #FFF;
  border: 2px solid #FF9800;
  border-radius: 6px;
  color: #FF9800;
  font-size: 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-reset-calendar:hover:not(:disabled) {
  background-color: #FF9800;
  color: #FFF;
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(255, 152, 0, 0.3);
}

.btn-reset-calendar:active:not(:disabled) {
  transform: translateY(0);
  box-shadow: 0 2px 4px rgba(255, 152, 0, 0.2);
}

.btn-reset-calendar:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none;
}

.btn-reset-calendar i {
  font-size: 16px;
}

.btn-reset-calendar i.fa-spin {
  animation: fa-spin 1s infinite linear;
}

@keyframes fa-spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.toast-container-custom {
  z-index: 9999;
}

@media screen and (max-width: 424px){
  .user-profile p {
    font-size: 18px;
    line-height: 1.2;
  }
  .user-profile .user-profile-name {
     font-size: 22px;
     line-height: 26px;
     margin: 0 0 18px;
   }
  .user-profile .btn-logout {
    font-size: 22px;
    letter-spacing: 1px;
    padding: 14px;
  }
}
</style>
