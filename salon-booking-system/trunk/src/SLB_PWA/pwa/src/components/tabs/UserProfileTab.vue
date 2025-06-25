<template>
  <div>
    <b-spinner variant="primary" v-if="isLoading"></b-spinner>
    <div v-else-if="user" class="user-profile">
      <div class="user-profile-top">
        <h2 class="user-profile-name">{{ user.name }}</h2>
        <p class="user-profile-email">{{ user.email }}</p>
        <p class="user-profile-role">{{ user.role }}</p>
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
    };
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
