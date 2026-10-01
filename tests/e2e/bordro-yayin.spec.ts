import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';

// Gerçek PWA JS'i; HTTP yanıtları izole edilir, personel/veritabanı değiştirilmez.
const script = readFileSync(resolve('views/personel-pwa/assets/js/bordro-yayin.js'), 'utf8');
const beyan = 'Bu döneme ait resmî alacak dökümümü görüntüledim ve okudum.';

async function kur(page: any, options: { offlineBeyan?: boolean, revizyon?: boolean, testMode?: boolean } = {}) {
  let okundu = false;
  const actions: string[] = [];
  const d = () => ({token:'encrypted-token', surum:options.testMode ? 'T1' : 1, durum:options.revizyon ? 'revizyon' : (options.testMode ? 'test' : 'yayinda'), yayin_tarihi:'2026-10-01 12:00:00', goruntuleme_tarihi:null, beyan_tarihi:okundu ? '2026-10-01 12:10:00' : null, beyan_metni:beyan, beyan_metin_surumu:1, talepler:[], icerik:{personel:'Test <script>alert(1)</script>', departman:'Birim', gorev:'Personel', donem:'2026/09', baslangic:'2026-09-01', bitis:'2026-09-30', calisma_gun:30, fiili_gun:22, banka_net_kurus:2600000, kalemler:[{etiket:'Resmî net ücret',kurus:2600000}]}});
  await page.route('**/api.php', async (route: any) => {
    const body = route.request().postData() || '';
    const action = /name="action"\r\n\r\n([^\r]+)/.exec(body)?.[1] || '';
    actions.push(action);
    if (action === 'bordro-yayin-beyan' && options.offlineBeyan) return route.abort();
    if (action === 'bordro-yayin-beyan') okundu = true;
    if (action === 'bordro-yayin-talep') return route.fulfill({json:{success:true,data:{...d(),talepler:[{mesaj:'Gün hesabını kontrol etmenizi istiyorum.',tarih:'2026-10-01',durum:'acik',yanitlar:[]}]}}});
    const data = action === 'bordro-yayin-liste' ? [{...d(),donem:'2026/09',banka_net_kurus:2600000}] : d();
    await route.fulfill({json:{success:true,data}});
  });
  await page.route('**/fixture', route => route.fulfill({contentType:'text/html; charset=utf-8',body:`<html><head><meta charset="utf-8"></head><body><section id="resmi-bordro-panel"><p id="resmi-bordro-mesaj"></p><div id="resmi-bordro-liste"></div><div id="resmi-bordro-detay" hidden></div></section><script>window.bordroYayinCsrf='test-csrf';</script><script>${script}</script></body></html>`}));
  await page.goto('http://bordro.test/fixture');
  await page.getByRole('button', {name:/2026\/09/}).click();
  await expect.poll(() => actions.includes('bordro-yayin-goruntule')).toBeTruthy();
  return actions;
}

test('mobil döküm ayrı görüntüleme ve açık okuma beyanı alır; HTML kaçar', async ({page}) => {
  await page.setViewportSize({width:390,height:844});
  const actions = await kur(page);
  await expect(page.getByRole('checkbox')).not.toBeChecked();
  await expect(page.locator('#resmi-bordro-detay')).toContainText('Test <script>alert(1)</script>');
  expect(actions).not.toContain('bordro-yayin-beyan');
  await expect(page.getByRole('link', {name:'PDF indir'})).toHaveAttribute('href', /bordro-goster\.php\?token=encrypted-token/);
  await page.getByRole('checkbox').check();
  await page.getByRole('button', {name:'Okudum beyanını kaydet'}).click();
  await expect(page.locator('#resmi-bordro-detay')).toContainText('Beyanınız kaydedildi');
  expect(actions.filter(a => a === 'bordro-yayin-beyan')).toHaveLength(1);
});

test('ağ hatasında beyan başarılı gösterilmez', async ({page}) => {
  await kur(page,{offlineBeyan:true});
  await page.getByRole('checkbox').check();
  await page.getByRole('button', {name:'Okudum beyanını kaydet'}).click();
  await expect(page.locator('#resmi-bordro-mesaj')).toContainText('Bağlantınızı kontrol edip tekrar deneyin.');
  await expect(page.locator('#resmi-bordro-detay')).not.toContainText('Beyanınız kaydedildi');
  await expect(page.getByRole('button', {name:'Okudum beyanını kaydet'})).toBeEnabled();
});

test('inceleme talebi beyan gerektirmeden açılır', async ({page}) => {
  const actions = await kur(page);
  await page.getByLabel('İnceleme talebi açıklaması').fill('Gün hesabını kontrol etmenizi istiyorum.');
  await page.getByRole('button', {name:'İnceleme talebi oluştur'}).click();
  await expect(page.locator('#resmi-bordro-detay')).toContainText('Açık talebiniz yetkilinin incelemesini bekliyor.');
  expect(actions).not.toContain('bordro-yayin-beyan');
  await expect(page.getByRole('checkbox')).not.toBeChecked();
});

test('revizyondaki sürüm okunabilir, yeni beyan butonu gösterilmez', async ({page}) => {
  await kur(page,{revizyon:true});
  await expect(page.locator('#resmi-bordro-detay')).toContainText('Revizyon sürecinde');
  await expect(page.getByRole('button', {name:'Okudum beyanını kaydet'})).toHaveCount(0);
});

test('bildirimsiz test sürümü açık okuma beyanı alır', async ({page}) => {
  const actions = await kur(page,{testMode:true});
  await expect(page.locator('#resmi-bordro-detay')).toContainText('bildirimsiz bir test yayınıdır');
  await page.getByRole('checkbox').check();
  await page.getByRole('button', {name:'Okudum beyanını kaydet'}).click();
  await expect(page.locator('#resmi-bordro-detay')).toContainText('Beyanınız kaydedildi');
  expect(actions.filter(a => a === 'bordro-yayin-beyan')).toHaveLength(1);
});
