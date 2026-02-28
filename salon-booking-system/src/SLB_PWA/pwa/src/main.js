import { createApp } from 'vue'
import App from './App.vue'
import './registerServiceWorker'
import store from './store'

import BootstrapVue3 from 'bootstrap-vue-3'

// Optional, since every component import their Bootstrap functionality
// the following line is not necessary
// import 'bootstrap'

import 'bootstrap/dist/css/bootstrap.css'
import 'bootstrap-vue-3/dist/bootstrap-vue-3.css'

/* import the fontawesome core */
import { library } from '@fortawesome/fontawesome-svg-core'

/* import font awesome icon component */
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

/* import specific icons */
import { faList, faCalendarDays, faMagnifyingGlass, faChevronRight, faTrash, faCircleXmark,
faPenToSquare, faCheck, faUsers, faUserAlt, faPlus, faCircleChevronDown, faCircleChevronUp, faChartSimple, faMedal, faUnlock, faLock,
faPhone, faMessage, faCirclePlus, faStore, faImages, faCamera, faCloudArrowUp} from '@fortawesome/free-solid-svg-icons'

import { faAddressBook, faClock, faCircleCheck } from '@fortawesome/free-regular-svg-icons'

import { faWhatsapp } from '@fortawesome/free-brands-svg-icons'

/* add icons to the library */
library.add(faList, faCalendarDays, faAddressBook, faMagnifyingGlass, faClock, faChevronRight, faTrash, faCircleXmark,
faPenToSquare, faCheck, faUsers, faUserAlt, faPlus, faCircleChevronDown, faCircleChevronUp, faChartSimple, faMedal, faUnlock, faLock,
faPhone, faMessage, faCirclePlus, faWhatsapp, faStore, faImages, faCamera, faCloudArrowUp, faCircleCheck)

import Datepicker from '@vuepic/vue-datepicker';

import '@vuepic/vue-datepicker/dist/main.css';
import mixin from './mixin'

import OneSignalVuePlugin from '@onesignal/onesignal-vue3'

import VueNextSelect from 'vue-next-select';
import 'vue-next-select/dist/index.min.css'

import 'vue3-carousel/dist/carousel.css'
import { Carousel, Slide, Pagination, Navigation } from 'vue3-carousel'

import VueTelInput from 'vue-tel-input';
import 'vue-tel-input/vue-tel-input.css';

var app = createApp(App)
            .use(store)
            .use(BootstrapVue3)
            .use(VueTelInput)
            .component('font-awesome-icon', FontAwesomeIcon)
            .component('Datepicker', Datepicker)
            .component('vue-select', VueNextSelect)
            .component('Carousel', Carousel)
            .component('Slide', Slide)
            .component('Pagination', Pagination)
            .component('Navigation', Navigation)
            .mixin(mixin)

var oneSignalAppId = window.slnPWA.onesignal_app_id

if (oneSignalAppId) {
    app.use(OneSignalVuePlugin, {
        appId: oneSignalAppId,
        serviceWorkerParam: { scope: "/{SLN_PWA_DIST_PATH}/" },
        serviceWorkerPath: "{SLN_PWA_DIST_PATH}/OneSignalSDKWorker.js"
    })
}

app.mount('#app')