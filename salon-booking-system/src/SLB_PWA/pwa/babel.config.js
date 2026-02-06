module.exports = {
  presets: [
    '@vue/cli-plugin-babel/preset'
  ],
  // Optimize for production builds
  compact: process.env.NODE_ENV === 'production',
  // Increase code generator limit for large icon files
  generatorOpts: {
    compact: true,
    minified: true
  }
}
