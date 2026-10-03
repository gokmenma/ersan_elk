/* Offline HTTP fixture: renders real PHP views; no application DB or EDM calls. */
const { chromium } = require('@playwright/test');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const php = process.env.EFATURA_PHP || '/opt/lampp/bin/php';
const calls = [];
let savedPayload;
const html = mode => execFileSync(php, [path.join(root, 'tests/Fixtures/efatura/render.php'), mode], {cwd: root}).toString();
const server = http.createServer(async (request, response) => {
  const url = new URL(request.url, 'http://localhost');
  if (url.pathname === '/api/efatura-api.php') {
    let body = ''; for await (const data of request) body += data;
    const form = new URLSearchParams(body);
    const action = url.searchParams.get('action') || form.get('action');
    calls.push({action, token: request.headers['x-csrf-token'], body});
    response.setHeader('Content-Type', 'application/json');
    if (request.method === 'POST' && request.headers['x-csrf-token'] !== 'offline-browser-token') { response.statusCode = 403; return response.end(JSON.stringify({status:'error', message:'CSRF'})); }
    if (action === 'calculate_invoice') return response.end(execFileSync(php, [path.join(root,'tests/Fixtures/efatura/calculate.php')], {input:body}));
    if (action === 'save_draft') {
      savedPayload = JSON.parse(body);
      const validation = JSON.parse(execFileSync(php, [path.join(root,'tests/Fixtures/efatura/calculate.php')], {input:body}));
      return response.end(JSON.stringify({...validation, message: 'Taslak kaydedildi', encrypted_id:'offline-encrypted-id'}));
    }
    if (action === 'check_taxpayer') return response.end(JSON.stringify({status:'success',data:{is_einvoice_user:false,aliases:[]}}));
    if (action === 'summary_stats') return response.end(JSON.stringify({status:'success',data:{}}));
    if (action === 'list_invoices' || action === 'list_giden') return response.end(JSON.stringify({draw:Number(url.searchParams.get('draw') || 1),recordsTotal:0,recordsFiltered:0,data:[]}));
    if (action === 'download_pdf') {
      response.setHeader('Content-Type','application/pdf'); response.setHeader('Content-Disposition','attachment; filename="test.pdf"');
      return response.end('%PDF-1.7 offline-test');
    }
    return response.end(JSON.stringify({status:'success',data:{events:[],report_status:'REPORT - SUCCESS'}}));
  }
  if (url.pathname === '/') { response.setHeader('Content-Type','text/html'); return response.end(html(url.searchParams.get('mode') || 'olustur')); }
  const resource = path.resolve(root, '.' + url.pathname);
  if (resource.startsWith(root + '/assets/') || resource.startsWith(root + '/views/efatura/js/')) {
    if (fs.existsSync(resource)) { response.setHeader('Content-Type',resource.endsWith('.js') ? 'application/javascript' : 'text/css'); return response.end(fs.readFileSync(resource)); }
  }
  response.statusCode = 404; response.end();
});
(async () => {
  let browser;
  try {
    await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
    browser = await chromium.launch({headless:true});
    const page = await browser.newPage(); const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.route('https://**', route => route.abort());
    const base = 'http://127.0.0.1:' + server.address().port;
    await page.goto(base); await page.locator('.kalem-row').waitFor();
    await page.locator('.invoice-not-editor .note-editor').waitFor();
    assert.equal(await page.evaluate(() => window.fixtureXss), undefined, 'Customer data executed a script');
    assert.equal(await page.locator('.kalem-row .select2-container').count(), 4);
    assert.equal(await page.locator('.tax-detail-fields').isVisible(), false);
    await page.locator('.btn-tax-detail-toggle').click();
    assert.equal(await page.locator('.tax-detail-fields').isVisible(), true);
    await page.locator('.kalem-ad').fill('İstisnalı ürün'); await page.locator('.kalem-fiyat').fill('100');
    await page.locator('#alici_vkn_tckn').fill('9876543210'); await page.locator('#alici_unvan').fill('Test Alıcı');
    await page.evaluate(() => {
      $('#fatura_tipi').val('ISTISNA').trigger('change');
      $('.kalem-kdv').val('0').trigger('change');
      $('.kalem-istisna').val('350').trigger('change');
      $('#para_birimi').val('USD').trigger('change'); $('#doviz_kuru').val('34.50');
    });
    await page.locator('.kalem-istisna-aciklama').fill('İstisna açıklaması');
    await page.waitForFunction(() => document.getElementById('lblOdenecekTutar').textContent.includes('100,00 USD'));
    await page.locator('#btnTaslakKaydet').click(); await page.locator('.swal2-title').filter({hasText:'Taslak Kaydedildi'}).waitFor();
    assert.equal(savedPayload.lines[0].kdv_orani, '0'); assert.equal(savedPayload.lines[0].istisna_kodu,'350');
    assert.equal(savedPayload.header.para_birimi,'USD'); assert.equal(savedPayload.header.doviz_kuru,'34.50');
    await page.evaluate(() => Swal.close());
    await page.goto(base); await page.locator('.kalem-row').waitFor();
    await page.locator('#btnSatirEkle').click(); assert.equal(await page.locator('.kalem-row').count(),2);
    assert.equal(await page.locator('.kalem-row select').count(), 8);
    assert.equal(await page.locator('.kalem-row .select2-container').count(), 8);
    await page.locator('.btn-tax-detail-toggle').first().click();
    assert.equal(await page.locator('.tax-detail-fields').first().isVisible(), true);
    assert.equal(await page.locator('.tax-detail-fields').nth(1).isVisible(), false);
    const downloadPromise = page.waitForEvent('download');
    await page.evaluate(() => window.efaturaDownloadPdf('encrypted-id'));
    const download = await downloadPromise; assert.equal(download.suggestedFilename(),'test.pdf');
    await page.evaluate(() => localStorage.setItem('efatura_summary_cards_state','hidden'));
    await page.goto(base + '/?mode=giden-list');
    await page.waitForFunction(() => document.getElementById('btnToggleSummaryCards').getAttribute('aria-expanded') === 'false');
    assert.equal(await page.locator('#summaryCardsContainer').evaluate(node => getComputedStyle(node).maxHeight),'0px');
    await page.locator('#btnToggleSummaryCards').click();
    assert.equal(await page.locator('#btnToggleSummaryCards').getAttribute('aria-expanded'),'true');
    assert.equal(await page.evaluate(() => localStorage.getItem('efatura_summary_cards_state')),'visible');
    assert.equal((await page.locator('#btnToggleSummaryCards').getAttribute('aria-label')).includes('gizle'),true);
    assert.deepEqual(errors,[]);
    assert.ok(calls.filter(call => call.action === 'calculate_invoice' || call.action === 'save_draft').every(call => call.token === 'offline-browser-token'));
    console.log('OK: actual PHP form/Select2, escaped data, zero VAT, USD totals/payload, CSRF, dynamic row IDs, PDF download, saved summary visibility.');
  } finally { await browser?.close(); await new Promise(resolve => server.close(resolve)); }
})().catch(error => { console.error(error); process.exitCode=1; });
