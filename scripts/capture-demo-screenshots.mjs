import { chromium } from 'playwright-core';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';

const baseUrl = process.env.CENA_BASE_URL ?? 'http://127.0.0.1:8000';
const password = process.env.CENA_DEMO_PASSWORD;
const output = process.env.CENA_SCREENSHOT_DIR ?? 'screenshot-output';
if (!password) throw new Error('CENA_DEMO_PASSWORD is required for seeded demo screenshots');

await mkdir(output, { recursive: true });
const entries = JSON.parse(await readFile('database/data/nike-multiview-demo.json', 'utf8'));
const adidasEntries = JSON.parse(await readFile('database/data/adidas-multiview-demo.json', 'utf8'));
const browser = await chromium.launch({
  headless: true,
  executablePath: process.env.CHROME_PATH,
  args: ['--no-sandbox', '--disable-dev-shm-usage'],
});
const manifest = [];
let sequence = 0;

async function newPage() {
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, locale: 'vi-VN' });
  return { context, page: await context.newPage() };
}

async function screenshot(page, title) {
  await page.locator('body').waitFor();
  await page.waitForLoadState('networkidle', { timeout: 10000 }).catch(() => {});
  await Promise.race([
    page.evaluate(() => Promise.all(Array.from(document.images).map(img => img.decode().catch(() => {})))),
    page.waitForTimeout(8000),
  ]);
  const filename = String(++sequence).padStart(2, '0') + '-' + title.replace(/[^a-z0-9-]+/g, '-') + '.png';
  await page.screenshot({ path: path.join(output, filename), animations: 'disabled' });
  manifest.push({ filename, title, url: page.url() });
  console.log(filename, page.url());
}

async function visit(page, url, title) {
  const response = await page.goto(new URL(url, baseUrl).href, { waitUntil: 'domcontentloaded', timeout: 30000 });
  if (!response || response.status() >= 400) throw new Error(`Cannot capture ${url}: HTTP ${response?.status()}`);
  if (new URL(page.url()).pathname === '/login' && url !== '/login') throw new Error(`Unexpected login redirect for ${url}`);
  await screenshot(page, title);
}

async function login(page, email) {
  await page.goto(new URL('/login', baseUrl).href, { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);
  await Promise.all([
    page.waitForURL(url => url.pathname !== '/login', { timeout: 20000 }),
    page.locator('form[aria-label="Đăng nhập"] button[type="submit"]').click(),
  ]);
}

try {
  const { context: publicContext, page: publicPage } = await newPage();
  await visit(publicPage, '/', 'trang-chu');
  await visit(publicPage, '/shoes', 'danh-sach-san-pham');
  await visit(publicPage, '/categories', 'danh-muc');
  await visit(publicPage, '/shoes/' + entries[10].slug, 'chi-tiet-giay-pegasus-42');
  await publicPage.getByRole('button', { name: 'Ảnh 2', exact: true }).first().click();
  await screenshot(publicPage, 'chi-tiet-chuyen-goc-anh');
  await visit(publicPage, '/shoes/' + adidasEntries[0].slug, 'chi-tiet-adidas-samba-og');
  if (await publicPage.getByRole('group', { name: 'Chọn ảnh' }).getByRole('button').count() !== 4) {
    throw new Error('adidas product gallery must show four angles');
  }
  const adidasMainImage = publicPage.locator('[data-gallery-zoom] img').first();
  await adidasMainImage.evaluate(img => img.decode());
  if (!await adidasMainImage.evaluate(img => img.currentSrc.startsWith('https://assets.adidas.com/') && img.naturalWidth >= 1000)) {
    throw new Error('adidas main photo did not load at high resolution');
  }
  await publicPage.getByRole('button', { name: 'Ảnh 2', exact: true }).first().click();
  await screenshot(publicPage, 'chi-tiet-adidas-chuyen-goc');
  await visit(publicPage, '/news', 'danh-sach-bai-viet');
  const newsHref = await publicPage.locator('a[href*="/news/"]').evaluateAll(nodes =>
    nodes.map(a => a.getAttribute('href')).find(href => /\/news\/[a-z0-9-]+(?:\?|$)/.test(href ?? '')));
  if (newsHref) await visit(publicPage, newsHref, 'chi-tiet-bai-viet');
  await visit(publicPage, '/login', 'dang-nhap');
  await visit(publicPage, '/register', 'dang-ky');
  await visit(publicPage, '/forgot-password', 'quen-mat-khau');
  await publicContext.close();

  const { context: customerContext, page: customerPage } = await newPage();
  await login(customerPage, 'khach1.qa@demo.cena.test');
  await visit(customerPage, '/account', 'tai-khoan-khach-hang');
  await visit(customerPage, '/account/addresses', 'dia-chi-giao-hang');
  await visit(customerPage, '/account/notifications', 'thong-bao-khach-hang');
  await visit(customerPage, '/account/orders', 'don-hang-cua-toi');
  const orderHref = await customerPage.locator('a[href*="/account/orders/"]').evaluateAll(nodes =>
    nodes.map(a => a.getAttribute('href')).find(href => /\/account\/orders\/ORD-/.test(href ?? '')));
  if (orderHref) await visit(customerPage, orderHref, 'chi-tiet-don-hang');
  await visit(customerPage, '/shoes/' + entries[10].slug, 'chon-size-va-them-gio');
  await customerPage.getByRole('button', { name: /^Size 39/ }).first().click();
  await customerPage.getByRole('button', { name: /Thêm vào giỏ hàng/ }).first().click();
  await customerPage.waitForTimeout(500);
  await visit(customerPage, '/cart', 'gio-hang');
  await visit(customerPage, '/checkout', 'thanh-toan');
  await customerContext.close();

  const { context: adminContext, page: adminPage } = await newPage();
  await login(adminPage, 'admin.qa@demo.cena.test');
  const adminPages = [
    ['/admin', 'tong-quan-quan-tri'],
    ['/admin/products', 'quan-ly-san-pham'],
    ['/admin/products/create', 'them-san-pham'],
    ['/admin/categories', 'quan-ly-danh-muc'],
    ['/admin/classifications', 'phan-loai-san-pham'],
    ['/admin/suppliers', 'nha-cung-cap'],
    ['/admin/stock-imports', 'phieu-nhap-kho'],
    ['/admin/orders', 'quan-ly-don-hang'],
    ['/admin/operations-work-items', 'cong-viec-van-hanh'],
    ['/admin/reports', 'bao-cao-thong-ke'],
    ['/admin/users', 'quan-ly-tai-khoan'],
    ['/admin/coupons', 'ma-giam-gia'],
    ['/admin/articles', 'quan-ly-bai-viet'],
    ['/admin/banners', 'quan-ly-banner'],
    ['/admin/reviews', 'quan-ly-danh-gia'],
  ];
  for (const [url, title] of adminPages) await visit(adminPage, url, title);
  await adminContext.close();

  const lines = ['ẢNH CHỤP CENA STORE – DỮ LIỆU DEMO (20/11/2026)', '', 'Ảnh desktop 1440 × 900 px, chụp bằng Chromium từ project Laravel và FullDemoSeeder.', 'Tài khoản, đơn hàng, tồn kho và giá trong ảnh là dữ liệu mô phỏng.', '', ...manifest.map(item => `${item.filename}  |  ${item.title}  |  ${new URL(item.url).pathname}`)];
  await writeFile(path.join(output, 'DANH-SACH-ANH.txt'), lines.join('\n') + '\n');
  if (manifest.length < 20) throw new Error(`Only ${manifest.length} screenshots captured`);
} finally {
  await browser.close();
}
