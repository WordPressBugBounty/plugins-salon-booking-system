/**
 * WordPress (SLB_PWA\Plugin.php) replaces {SLN_PWA_DIST_PATH} in *template* files on
 * each request. After npm run build, copy fresh artifacts into those templates so a
 * stale app.template.js cannot undo a good app.js (or vice versa).
 */
const fs = require('fs');
const path = require('path');

const dist = path.join(__dirname, '../dist');

const pairs = [
  ['js/app.js', 'js/app.template.js'],
  ['js/app.js.map', 'js/app.js.template.map'],
  ['service-worker.js', 'service-worker.template.js'],
  ['service-worker.js.map', 'service-worker.js.template.map'],
  /** Plugin.php also hydrates index.html from this template on each PWA load. */
  ['index.html', 'index.template.html'],
];

for (const [fromRel, toRel] of pairs) {
  const from = path.join(dist, fromRel);
  const to = path.join(dist, toRel);
  if (!fs.existsSync(from)) {
    console.warn(`sync-pwa-dist-templates: skip missing ${fromRel}`);
    continue;
  }
  fs.copyFileSync(from, to);
}

console.log('sync-pwa-dist-templates: OK');
