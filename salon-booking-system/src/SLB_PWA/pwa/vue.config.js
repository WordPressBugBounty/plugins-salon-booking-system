const { defineConfig } = require('@vue/cli-service')

/**
 * Do not rely on `process.env.NODE_ENV` while this file loads — it is often still
 * unset, so publicPath would fall back to "/" and webpack bakes a machine-specific
 * URL → broken PWA after zip/deploy. `argv` contains `build` for production builds.
 */
const isProdBuild = process.argv.includes('build')
const pwaPublicPath = isProdBuild ? '/{SLN_PWA_DIST_PATH}/' : '/'

module.exports = defineConfig({
    transpileDependencies: true,
    lintOnSave: false, // Disable ESLint during build to prevent config lookup errors
    publicPath: pwaPublicPath,
    chainWebpack: (config) => {
      // Belt-and-suspenders: ensure nothing overrides publicPath after merge
      config.output.publicPath(pwaPublicPath)

      config.module.rules.delete('eslint')

      // Configure script injection to ensure FontAwesome loads before app
      config.plugin('html').tap((args) => {
        args[0].scriptLoading = 'blocking'
        return args
      })
    },
    devServer: {
      client: {
        overlay: false,
      },
    },
    pwa: {
      name: 'Salon Booking Plugin',
      themeColor: '#ffd100',
      manifestOptions: {
        start_url: '../../../../../../../salon-booking-pwa',
      },
      workboxOptions: {
        runtimeCaching: [
          {
            urlPattern: ({ request, url }) =>
              request.method === 'GET' &&
              /\/wp-json\/salon\/api\/mobile\/v1\/calendar\/intervals/.test(url.pathname),
            handler: 'CacheFirst',
            options: {
              cacheName: 'sln-pwa-calendar-intervals',
              expiration: { maxEntries: 20, maxAgeSeconds: 86400 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            urlPattern: ({ request, url }) =>
              request.method === 'GET' &&
              /\/wp-json\/salon\/api\/mobile\/v1\/app\/settings/.test(url.pathname),
            handler: 'CacheFirst',
            options: {
              cacheName: 'sln-pwa-app-settings',
              expiration: { maxEntries: 20, maxAgeSeconds: 86400 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            urlPattern: ({ request, url }) =>
              request.method === 'GET' &&
              /\/wp-json\/salon\/api\/mobile\/v1\/availability\/stats/.test(url.pathname),
            handler: 'StaleWhileRevalidate',
            options: {
              cacheName: 'sln-pwa-availability-stats',
              expiration: { maxEntries: 40, maxAgeSeconds: 300 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            urlPattern: ({ request, url }) =>
              request.method === 'GET' &&
              /\/wp-json\/salon\/api\/mobile\/v1\/bookings$/.test(url.pathname),
            handler: 'NetworkFirst',
            options: {
              cacheName: 'sln-pwa-bookings-list',
              networkTimeoutSeconds: 30,
              expiration: { maxEntries: 30, maxAgeSeconds: 120 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            urlPattern: ({ request, url }) =>
              request.method === 'GET' &&
              /\/wp-json\/salon\/api\/mobile\/v1\/holiday-rules/.test(url.pathname),
            handler: 'NetworkFirst',
            options: {
              cacheName: 'sln-pwa-holiday-rules',
              networkTimeoutSeconds: 30,
              expiration: { maxEntries: 40, maxAgeSeconds: 300 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
        ],
      },
    },
    filenameHashing: false,
    // Optimize build performance and reduce memory usage
    parallel: false, // Disable parallel processing in CI to reduce memory usage
    productionSourceMap: false, // Disable source maps to speed up build
    configureWebpack: {
      performance: {
        hints: false, // Disable performance hints for large assets
        maxEntrypointSize: 512000,
        maxAssetSize: 512000,
      },
      optimization: {
        splitChunks: {
          chunks: 'all',
          cacheGroups: {
            fontawesome: {
              test: /[\\/]node_modules[\\/]@fortawesome[\\/]/,
              name: 'fontawesome',
              priority: 10,
              reuseExistingChunk: true,
            },
          },
        },
      },
    },
})
