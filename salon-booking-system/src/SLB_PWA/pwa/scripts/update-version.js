#!/usr/bin/env node

/**
 * Update version.json with build time and hash
 * This runs automatically before every build (prebuild script)
 */

const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

// Generate build info
const buildTime = new Date().toISOString();
const buildHash = crypto.randomBytes(8).toString('hex');

const versionData = {
  buildTime: buildTime,
  buildHash: buildHash,
  timestamp: Date.now()
};

// Write to public/version.json (will be copied to dist during build)
const versionPath = path.join(__dirname, '../public/version.json');
fs.writeFileSync(versionPath, JSON.stringify(versionData, null, 2));

console.log('\n✅ Version updated:');
console.log(`   Build Time: ${buildTime}`);
console.log(`   Build Hash: ${buildHash}`);
console.log(`   Timestamp:  ${versionData.timestamp}\n`);

