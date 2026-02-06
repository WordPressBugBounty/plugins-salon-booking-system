const { defineConfig } = require('@vue/cli-service')
module.exports = defineConfig({
  transpileDependencies: true,
  pwa: {
    name: "Salon Booking Plugin",
    themeColor: "#ffd100",
    manifestOptions: {
      start_url: '../../../../../../../salon-booking-pwa',
    }
  },
  filenameHashing: false,
  publicPath: process.env.NODE_ENV === 'production'
    ? '/{SLN_PWA_DIST_PATH}/'
    : '/',
  // Optimize build performance and reduce memory usage
  parallel: false, // Disable parallel processing in CI to reduce memory usage
  productionSourceMap: false, // Disable source maps to speed up build
  configureWebpack: {
    performance: {
      hints: false, // Disable performance hints for large assets
      maxEntrypointSize: 512000,
      maxAssetSize: 512000
    },
    optimization: {
      splitChunks: {
        chunks: 'all',
        cacheGroups: {
          fontawesome: {
            test: /[\\/]node_modules[\\/]@fortawesome[\\/]/,
            name: 'fontawesome',
            priority: 10,
            reuseExistingChunk: true
          }
        }
      }
    }
  }
})
