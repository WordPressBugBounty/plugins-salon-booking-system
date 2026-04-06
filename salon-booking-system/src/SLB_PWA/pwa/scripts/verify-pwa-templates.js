/**
 * After sync-pwa-dist-templates.js: ensure *template* files are safe to commit
 * (WordPress replaces {SLN_PWA_DIST_PATH} at runtime). Rejects machine-specific
 * paths that would break other installs if committed.
 */
const fs = require('fs');
const path = require('path');

const dist = path.join(__dirname, '../dist');
const TOKEN = '{SLN_PWA_DIST_PATH}';
const URL_TOKEN = '/{SLN_PWA_DIST_PATH}/';

function fail(msg) {
  console.error(`verify-pwa-templates: ${msg}`);
  process.exit(1);
}

function mustContain(rel, text, needle) {
  if (!text.includes(needle)) {
    fail(`${rel} must include "${needle}" (broken or stale build).`);
  }
}

function mustNotContainMachinePath(rel, text) {
  const lower = text.toLowerCase();
  if (lower.includes('symlink')) {
    fail(`${rel} contains "symlink" — run npm run build from pwa/; do not commit PHP-processed dist.`);
  }
  if (text.includes('/wp-content/plugins/') || text.includes('wp-content\\plugins\\')) {
    fail(
      `${rel} contains wp-content/plugins — looks WordPress-processed. Rebuild PWA; commit only placeholder paths.`
    );
  }
}

const checks = [
  ['js/app.template.js', [TOKEN, URL_TOKEN]],
  ['service-worker.template.js', [TOKEN, URL_TOKEN]],
];

for (const [rel, needles] of checks) {
  const full = path.join(dist, rel);
  if (!fs.existsSync(full)) {
    fail(`missing ${rel}`);
  }
  const text = fs.readFileSync(full, 'utf8');
  for (const n of needles) {
    mustContain(rel, text, n);
  }
  mustNotContainMachinePath(rel, text);
}

const indexTpl = path.join(dist, 'index.template.html');
if (fs.existsSync(indexTpl)) {
  const html = fs.readFileSync(indexTpl, 'utf8');
  mustContain('index.template.html', html, TOKEN);
  mustNotContainMachinePath('index.template.html', html);
}

console.log('verify-pwa-templates: OK');
