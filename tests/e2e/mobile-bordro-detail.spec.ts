import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

const source = readFileSync(resolve('mobile/pages/bordro.php'), 'utf8');
const modal = source.slice(source.indexOf('<div id="bordroSheet"'), source.indexOf('<script>\nconst bordroPeriodToken'));
const script = source.slice(source.indexOf('<script>\nconst bordroPeriodToken') + 8, source.lastIndexOf('</script>')).replace(/<\?=[\s\S]*?\?>/g, 'null');
const css = readFileSync(resolve('views/personel-pwa/assets/css/tailwind-build.css'), 'utf8');
const detail = `<div class="bordro-compact-view">
  <div class="header-glass"><h5>Test Personel</h5><span>Eylül 2026</span></div>
  <div class="calculation-toggle" role="button" tabindex="0" data-collapse-target="#contract" aria-expanded="false">SÖZLEŞME ALACAĞI HESABI</div>
  <div id="contract" class="collapse bordro-main-calculation-collapse"><table class="unified-table"><tbody><tr><td>Nöbet</td><td>3.500,00 ₺</td></tr></tbody></table></div>
  <div class="calculation-toggle" role="button" tabindex="0" data-collapse-target="#official" aria-expanded="false">RESMÎ ALACAĞI HESABI <span class="badge bg-primary">32.380,41 ₺</span></div>
  <div id="official" class="collapse bordro-main-calculation-collapse">32.380,41 ₺</div>
  <table class="unified-table"><tbody><tr data-bs-toggle="collapse" data-bs-target=".deduction" aria-expanded="false"><td>Kesinti</td><td>300,00 ₺</td></tr>
  <tr class="collapse deduction"><td>Resmî Tutardan Kesilen</td><td>100,00 ₺</td></tr><tr class="collapse deduction"><td>Elden Kesilen</td><td>200,00 ₺</td></tr></tbody></table>
  <div style="height:1000px">ÖDENECEK NET MAAŞ · Banka · Elden · Sodexo · Diğer</div></div>`;

async function setup(page: any) {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.route('**/fixture', (route: any) => route.fulfill({ contentType: 'text/html', body: `<html><head><meta charset="UTF-8"><style>${css}</style></head><body><div style="transform:translateX(0);position:relative;z-index:0"><button onclick="openPayrollDetail('encrypted-token')">Detay</button>${modal}</div><nav style="position:fixed;bottom:0;z-index:50">Alt Menü</nav><script>${script}</script></body></html>` }));
  await page.goto('http://bordro.test/fixture');
}

test('mobil detay personel bilgisi kapalı, hesap kartları açık gelir ve accordionlar çalışır', async ({ page }) => {
  let action = '';
  await page.route('**/api/bordro.php', async route => {
    action = new URLSearchParams(route.request().postData() || '').get('action') || '';
    await route.fulfill({ json: { status: 'success', html: detail } });
  });
  await setup(page);
  await page.getByRole('button', { name: 'Detay', exact: true }).click();
  await expect(page.locator('#contract')).toBeVisible();
  await expect(page.locator('#official')).toBeVisible();
  await expect(page.locator('#mobilePersonSummaryContent')).toBeHidden();
  await expect(page.getByRole('button', { name: /Personel Bilgileri Test Personel/ })).toHaveAttribute('aria-expanded', 'false');
  await expect(page.locator('.calculation-toggle .badge.bg-primary')).toHaveCSS('color', 'rgb(255, 255, 255)');
  await expect(page.locator('.calculation-toggle .badge.bg-primary')).toHaveCSS('background-color', 'rgb(37, 99, 235)');
  expect(action).toBe('get-detail-old');
  await expect.poll(() => page.locator('#bordroSheetPanel').evaluate(el => Math.round(el.getBoundingClientRect().bottom))).toBe(844);
  expect(await page.locator('#bordroSheet').evaluate(el => el.parentElement?.tagName)).toBe('BODY');
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBeTruthy();
  expect(await page.locator('#bordroSheetBody').evaluate(el => el.scrollHeight > el.clientHeight)).toBeTruthy();
  await page.getByRole('button', { name: /Personel Bilgileri Test Personel/ }).click();
  await expect(page.locator('#mobilePersonSummaryContent')).toBeVisible();
  await expect(page.getByText('Eylül 2026')).toBeVisible();
  await page.getByRole('button', { name: /Personel Bilgileri Test Personel/ }).press('Space');
  await expect(page.locator('#mobilePersonSummaryContent')).toBeHidden();
  await page.getByRole('button', { name: 'SÖZLEŞME ALACAĞI HESABI', exact: true }).press('Enter');
  await expect(page.locator('#contract')).toBeHidden();
  await page.getByRole('button', { name: 'SÖZLEŞME ALACAĞI HESABI', exact: true }).press('Space');
  await expect(page.locator('#contract')).toBeVisible();
  await expect(page.getByText('Resmî Tutardan Kesilen')).toBeHidden();
  await page.getByText('Kesinti', { exact: true }).click();
  await expect(page.getByText('Resmî Tutardan Kesilen')).toBeVisible();
  await expect(page.getByText('Elden Kesilen', { exact: true })).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(page.getByRole('dialog')).toBeHidden();
  await expect(page.getByRole('button', { name: 'Detay', exact: true })).toBeFocused();
});

test('API hatası güvenli metin olarak gösterilir ve panel kapanabilir', async ({ page }) => {
  await page.route('**/api/bordro.php', route => route.fulfill({ status: 403, json: { status: 'error', message: '<img src=x onerror="alert(1)"> Yetkisiz erişim' } }));
  await setup(page);
  await page.getByRole('button', { name: 'Detay', exact: true }).click();
  await expect(page.getByRole('alert')).toContainText('<img');
  await expect(page.locator('#bordroSheetBody img')).toHaveCount(0);
  await page.getByRole('button', { name: 'Paneli kapat' }).click();
  await expect(page.getByRole('dialog')).toBeHidden();
});

test('kapatılan detayın geciken yanıtı yeni panel içeriğini değiştirmez', async ({ page }) => {
  let release!: () => void;
  const pending = new Promise<void>(resolve => { release = resolve; });
  await page.route('**/api/bordro.php', async route => { await pending; await route.fulfill({ json: { status: 'success', html: detail } }); });
  await setup(page);
  await page.getByRole('button', { name: 'Detay', exact: true }).click();
  await page.getByRole('button', { name: 'Paneli kapat' }).click();
  await page.evaluate(() => (window as any).showBordroSheet('Diğer İşlemler', '<p>Yeni panel</p>'));
  const response = page.waitForResponse('**/api/bordro.php');
  release();
  await response;
  await expect(page.locator('#bordroSheetBody')).toHaveText('Yeni panel');
});
