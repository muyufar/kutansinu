import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const screenshotsDir = path.join(__dirname, 'screenshots-keuangan');

function loadEnvFile() {
  const envPath = path.join(__dirname, 'capture.env');
  const config = {
    LIVE_BASE_URL: process.env.LIVE_BASE_URL || 'https://keuangan.numartmagelang.com',
    LIVE_USERNAME: process.env.LIVE_USERNAME || '',
    LIVE_PASSWORD: process.env.LIVE_PASSWORD || '',
  };

  if (fs.existsSync(envPath)) {
    const lines = fs.readFileSync(envPath, 'utf8').split('\n');
    for (const line of lines) {
      const trimmed = line.trim();
      if (!trimmed || trimmed.startsWith('#')) continue;
      const [key, ...rest] = trimmed.split('=');
      config[key.trim()] = rest.join('=').trim();
    }
  }

  return config;
}

const config = loadEnvFile();
const baseUrl = config.LIVE_BASE_URL.replace(/\/$/, '');
const fallbackBaseUrl = (config.FALLBACK_BASE_URL || 'http://keuangan.test').replace(/\/$/, '');

const publicPages = [
  ['01-login', '/login.php'],
];

const authPages = [
  ['02-dashboard', '/index.php'],
  ['03-daftar-akun', '/akun/index.php'],
  ['04-tambah-transaksi', '/transaksi/tambah.php'],
  ['05-daftar-transaksi', '/transaksi/index.php'],
  ['06-laporan-transaksi', '/laporan/transaksi.php'],
  ['07-neraca', '/laporan/neraca.php'],
  ['08-laba-rugi', '/laporan/laba-rugi.php'],
  ['09-arus-kas', '/laporan/arus-kas.php'],
  ['10-jurnal-umum', '/laporan/jurnal.php'],
  ['11-pengaturan-perusahaan', '/pengaturan/perusahaan.php'],
];

if (!fs.existsSync(screenshotsDir)) {
  fs.mkdirSync(screenshotsDir, { recursive: true });
}

async function loginWithCredentials(page, urlBase) {
  await page.goto(`${urlBase}/login.php`, { waitUntil: 'networkidle', timeout: 30000 });
  await page.fill('input[name="username"]', config.LIVE_USERNAME);
  await page.fill('input[name="password"]', config.LIVE_PASSWORD);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(2500);
  if (page.url().includes('login.php')) {
    throw new Error(`Login gagal di ${urlBase}`);
  }
}

async function loginWithBootstrap(page, urlBase, userId = 1) {
  const bootstrapUrl = `${urlBase}/docs/_screenshot_bootstrap.php?user_id=${userId}&redirect=${encodeURIComponent('/index.php')}`;
  await page.goto(bootstrapUrl, { waitUntil: 'networkidle', timeout: 30000 });
  if (page.url().includes('login.php')) {
    throw new Error(`Bootstrap session gagal di ${urlBase}`);
  }
}

async function capture(page, name, urlPath, urlBase, fullPage = true) {
  await page.goto(`${urlBase}${urlPath}`, { waitUntil: 'networkidle', timeout: 30000 });
  await page.waitForTimeout(1500);
  const filePath = path.join(screenshotsDir, `${name}.png`);
  await page.screenshot({ path: filePath, fullPage });
  console.log(`Captured ${name}.png from ${urlBase}${urlPath}`);
}

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

for (const [name, urlPath] of publicPages) {
  try {
    await capture(page, name, urlPath, baseUrl);
  } catch (error) {
    console.warn(`Skip ${name}: ${error.message}`);
  }
}

let authBaseUrl = baseUrl;
let loggedIn = false;

if (config.LIVE_USERNAME && config.LIVE_PASSWORD) {
  try {
    await loginWithCredentials(page, baseUrl);
    loggedIn = true;
    console.log(`Login live berhasil (${baseUrl}).`);
  } catch (error) {
    console.warn(`Login live gagal: ${error.message}`);
  }
}

if (!loggedIn) {
  try {
    await loginWithBootstrap(page, fallbackBaseUrl);
    authBaseUrl = fallbackBaseUrl;
    loggedIn = true;
    console.warn(`Menggunakan fallback ${fallbackBaseUrl} untuk halaman login-required.`);
    console.warn('Isi docs/capture.env agar semua screenshot diambil dari live server.');
  } catch (error) {
    console.warn(`Fallback login gagal: ${error.message}`);
  }
}

if (loggedIn) {
  for (const [name, urlPath] of authPages) {
    try {
      await capture(page, name, urlPath, authBaseUrl);
    } catch (error) {
      console.warn(`Skip ${name}: ${error.message}`);
    }
  }

  try {
    await page.goto(`${authBaseUrl}/index.php`, { waitUntil: 'networkidle' });
    const laporanToggle = page.locator('a.nav-link.dropdown-toggle', { hasText: 'Laporan' });
    if (await laporanToggle.count()) {
      await laporanToggle.first().click();
      await page.waitForTimeout(800);
      await page.screenshot({
        path: path.join(screenshotsDir, '12-menu-laporan.png'),
        fullPage: false,
      });
      console.log(`Captured 12-menu-laporan.png from ${authBaseUrl}`);
    }
  } catch (error) {
    console.warn(`Skip menu laporan: ${error.message}`);
  }
}

await browser.close();
console.log(`Screenshot capture finished. Output: ${screenshotsDir}`);
