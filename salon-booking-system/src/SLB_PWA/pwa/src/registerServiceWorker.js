/* eslint-disable no-console */

import { register } from 'register-service-worker'

if (process.env.NODE_ENV === 'production') {
  // Force service worker to check for updates every 60 seconds
  setInterval(() => {
    navigator.serviceWorker.getRegistration().then((reg) => {
      if (reg) reg.update();
    });
  }, 60000);

  register(`${process.env.BASE_URL}service-worker.js`, {
    ready () {
      console.log(
        '✅ PWA ready - served from cache by service worker'
      )
    },
    registered (registration) {
      console.log('✅ Service worker registered')
      // Check for updates immediately and every minute
      setInterval(() => {
        registration.update()
      }, 60000)
    },
    cached () {
      console.log('✅ Content cached for offline use')
    },
    updatefound () {
      console.log('🔄 New PWA version downloading...')
    },
    updated (registration) {
      console.log('🎉 New PWA version available!')
      console.log('🔄 Auto-reloading to get fresh code...')
      
      // Tell the service worker to skip waiting and activate immediately
      if (registration && registration.waiting) {
        registration.waiting.postMessage({ type: 'SKIP_WAITING' })
      }
      
      // Auto-reload the page after a short delay to get the new version
      setTimeout(() => {
        console.log('♻️ Reloading page now...')
        window.location.reload()
      }, 1000)
    },
    offline () {
      console.log('📵 Offline mode - no internet connection')
    },
    error (error) {
      console.error('❌ Service worker error:', error)
    }
  })
  
  // Listen for controller change (new service worker activated)
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    console.log('🔄 Service worker updated - reloading...')
    window.location.reload()
  })
}
