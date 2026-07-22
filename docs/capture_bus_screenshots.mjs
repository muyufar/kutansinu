import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screenshotsDir = path.join(__dirname, 'screenshots');
const htmlDir = path.join(__dirname, 'screenshots-html');

if (!fs.existsSync(screenshotsDir)) {
  fs.mkdirSync(screenshotsDir, { recursive: true });
}

const htmlFiles = [
  ['01-login', '01-login.html'],
  ['02-jadwal-bus-umum', '02-jadwal-bus-umum.html'],
  ['03-dashboard-bus', '03-dashboard-bus.html'],
  ['04-form-pesan', '04-form-pesan.html'],
  ['05-riwayat', '05-riwayat.html'],
  ['06-cetak-tiket', '06-cetak-tiket.html'],
];

const liveUrls = [
  ['07-live-login', 'http://keuangan.test/login.php'],
  ['08-live-jadwal', 'http://keuangan.test/jadwal_bus_umum.php'],
];

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1366, height: 900 } });

for (const [name, file] of htmlFiles) {
  const filePath = path.join(htmlDir, file);
  await page.goto(`file:///${filePath.replace(/\\/g, '/')}`, { waitUntil: 'networkidle' });
  await page.screenshot({ path: path.join(screenshotsDir, `${name}.png`), fullPage: true });
  console.log(`Captured ${name}.png`);
}

for (const [name, url] of liveUrls) {
  try {
    await page.goto(url, { waitUntil: 'networkidle', timeout: 15000 });
    await page.screenshot({ path: path.join(screenshotsDir, `${name}.png`), fullPage: true });
    console.log(`Captured ${name}.png from ${url}`);
  } catch (error) {
    console.warn(`Skip ${name}: ${error.message}`);
  }
}

await browser.close();
console.log('Screenshot capture finished.');
