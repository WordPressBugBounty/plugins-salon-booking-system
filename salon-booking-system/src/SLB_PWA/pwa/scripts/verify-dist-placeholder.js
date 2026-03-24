/**
 * Ensures production dist contains the WordPress runtime token `/{SLN_PWA_DIST_PATH}/`
 * (replaced in SLB_PWA\Plugin.php). A bad local build can bake an absolute path
 * (e.g. a "… symlink …" folder), which breaks chunk loading → blank tabs.
 */
const fs = require('fs');
const path = require('path');

const dist = path.join(__dirname, '../dist');
const appJs = path.join(dist, 'js/app.js');
const PLACEHOLDER = '/{SLN_PWA_DIST_PATH}/';

function walkJsFiles(dir, out) {
  if (!fs.existsSync(dir)) return;
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    const st = fs.statSync(full);
    if (st.isDirectory()) walkJsFiles(full, out);
    else if (name.endsWith('.js')) out.push(full);
  }
}

if (!fs.existsSync(appJs)) {
  console.error('verify-dist-placeholder: dist/js/app.js not found (run npm run build first).');
  process.exit(1);
}

const appSrc = fs.readFileSync(appJs, 'utf8');

if (!appSrc.includes(PLACEHOLDER)) {
  console.error(
    'verify-dist-placeholder: dist/js/app.js must include the literal "/{SLN_PWA_DIST_PATH}/" (vue.config.js publicPath).'
  );
  process.exit(1);
}

if (appSrc.toLowerCase().includes('symlink')) {
  console.error(
    'verify-dist-placeholder: dist/js/app.js contains "symlink" — rebuild; vue.config must use mode-based publicPath.'
  );
  process.exit(1);
}

const jsFiles = [];
walkJsFiles(path.join(dist, 'js'), jsFiles);
for (const f of jsFiles) {
  const s = fs.readFileSync(f, 'utf8');
  if (s.toLowerCase().includes('symlink')) {
    console.error(`verify-dist-placeholder: ${path.relative(dist, f)} contains "symlink".`);
    process.exit(1);
  }
}

const swPath = path.join(dist, 'service-worker.js');
if (fs.existsSync(swPath)) {
  const sw = fs.readFileSync(swPath, 'utf8');
  if (sw.toLowerCase().includes('symlink')) {
    console.error('verify-dist-placeholder: dist/service-worker.js contains "symlink".');
    process.exit(1);
  }
}

console.log('verify-dist-placeholder: OK');
