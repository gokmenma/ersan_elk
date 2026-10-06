/* Offline browser fixture: real draft page, no database writes or EDM requests. */
const {chromium} = require('@playwright/test');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../..');
const mode = process.env.EFATURA_SYNC_MODE || 'taslak-list';
const syncButton = mode === 'gelen-list' ? '#btnSyncIncoming' : (mode === 'giden-list' ? '#btnSyncOutgoing' : '#btnSyncDrafts');
const calls = [];
let syncFixtureJob = null;
let syncSequence = 0;
const server = http.createServer(async (request, response) => {
    const url = new URL(request.url, 'http://localhost');
    if (url.pathname === '/api/efatura-api.php') {
        let body = ''; for await (const chunk of request) body += chunk;
        const form = new URLSearchParams(body);
        const action = url.searchParams.get('action') || form.get('action');
        calls.push({action,token:request.headers['x-csrf-token']});
        response.setHeader('Content-Type', 'application/json');
        if (request.method === 'POST' && request.headers['x-csrf-token'] !== 'offline-browser-token') {
            response.statusCode = 403; return response.end(JSON.stringify({status:'error',message:'CSRF'}));
        }
    if (action === 'sync_job_start') {
      syncFixtureJob = {created_at:`2026-10-04 14:00:00.${++syncSequence}`,job_token:'offline-job-token',job_status:'running',start_date:form.get('start_date'),end_date:form.get('end_date'),processed_count:2,added_count:2,updated_count:0,message:'Aktarım arka planda sürüyor.'};
      return response.end(JSON.stringify({status:'success',data:syncFixtureJob}));
    }
    if (action === 'sync_job_errors') return response.end(JSON.stringify({status:'success',data:{failed_count:1,rows:[{fatura_no:'=UNSAFE',uuid:'offline-failed',issue_date:'2026-01-05',supplier:'<script>window.fixtureXss=true</script>',message:'Invalid source'}]}}));
    if (action === 'sync_job_pause') syncFixtureJob = {...syncFixtureJob,pause_requested:true};
    if (action === 'sync_job_retry') syncFixtureJob = {...syncFixtureJob,job_status:'running',failed_count:0,pause_requested:false};
    if (action === 'sync_job_resume') syncFixtureJob = {...syncFixtureJob,job_status:'running',pause_requested:false,message:'Aktarım kaldığı yerden devam ediyor.'};
    if (['sync_job_status','sync_job_resume','sync_job_pause','sync_job_retry'].includes(action)) return response.end(JSON.stringify({status:'success',data:syncFixtureJob}));
        if (action === 'summary_stats') return response.end(JSON.stringify({status:'success',data:{}}));
        if (['list_invoices','list_giden'].includes(action)) return response.end(JSON.stringify({draw:Number(url.searchParams.get('draw') || 1),recordsTotal:0,recordsFiltered:0,summary:{},data:[]}));
        return response.end(JSON.stringify({status:'error',message:'Unexpected action'}));
    }
    if (url.pathname === '/') {
        response.setHeader('Content-Type','text/html');
        const html = execFileSync('/opt/lampp/bin/php', [path.join(root,'tests/Fixtures/efatura/render.php'),mode],{cwd:root}).toString();
        return response.end(html.replace('</head>', '<link rel="stylesheet" href="/assets/css/bootstrap.min.css"></head>'));
    }
    const resource = path.resolve(root, '.' + url.pathname);
    if ((resource.startsWith(root+'/assets/') || resource.startsWith(root+'/views/efatura/js/')) && fs.existsSync(resource)) {
        response.setHeader('Content-Type',resource.endsWith('.js')?'application/javascript':'text/css');
        return response.end(fs.readFileSync(resource));
    }
    response.statusCode=404; response.end();
});
(async () => {
    let browser;
    try {
        await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
        browser = await chromium.launch({headless:true,...(process.env.EFATURA_CHROME?{executablePath:process.env.EFATURA_CHROME}:{})});
        const page = await browser.newPage(); const errors=[];
        page.on('pageerror',error=>errors.push(error.message));
        await page.route('https://**',route=>route.abort());
        const base='http://127.0.0.1:'+server.address().port;
    await page.goto(base + '/?mode=' + mode);
    if (mode === 'giden-list') await page.locator('.personel-action-toolbar .dropdown-toggle').click();
    await page.locator(syncButton).click();
    await page.locator('.swal2-confirm').click();
    await page.locator('.sync-counts').filter({hasText:'2 işlendi'}).waitFor();
    assert.equal(await page.locator(syncButton).isDisabled(), true);
    await page.locator('.swal2-confirm').click();
    await page.locator('.sync-pause').click();
    await page.locator('.sync-pause').filter({hasText:'Durduruluyor'}).waitFor();
    assert.equal(await page.locator('.sync-pause').isDisabled(), true);
    // Simulate a job pausing while this tab is closed; server state survives navigation.
    syncFixtureJob = {...syncFixtureJob,job_status:'paused',message:'EDM bağlantısı kesildi.'};
    await page.goto(base + '/?mode=' + mode);
    await page.locator('.sync-resume').waitFor({state:'visible'});
    assert.equal(await page.locator('.sync-counts').textContent(), '2 işlendi · 2 yeni · 0 güncellendi');
    await page.locator('.sync-resume').click();
    await page.waitForFunction(() => document.querySelector('.sync-resume').classList.contains('d-none'));
    syncFixtureJob = {...syncFixtureJob,job_status:'completed',processed_count:5,added_count:5,message:'Aktarım tamamlandı.'};
    await page.locator('.sync-counts').filter({hasText:'5 işlendi'}).waitFor();
    assert.equal(await page.locator(syncButton).isEnabled(), true);
    assert.ok(calls.filter(call => call.action.startsWith('sync_job_')).every(call => call.token === 'offline-browser-token'));
        syncFixtureJob = {...syncFixtureJob,job_status:'partial',failed_count:1,message:'1 fatura doğrulanamadı: XML fatura numarası geçersiz.'};
        await page.goto(base + '/?mode=' + mode);
        await page.locator('.sync-counts').filter({hasText:'1 aktarılamadı'}).waitFor();
        assert.equal(await page.locator('.sync-heading').locator('..').locator('..').locator('..').evaluate(node=>node.classList.contains('alert-warning')), true);
        assert.equal(await page.locator(syncButton).isEnabled(), true);
        await page.locator('.sync-errors').click();
        await page.locator('.swal2-title').filter({hasText:'Aktarılamayan faturalar'}).waitFor();
        assert.equal(await page.evaluate(() => window.fixtureXss), undefined);
        const downloading = page.waitForEvent('download');
        await page.locator('.swal2-confirm').click();
        const download = await downloading;
        const stream = await download.createReadStream();
        let csv = ''; for await (const chunk of stream) csv += chunk.toString('utf8');
        assert.ok(csv.includes("'=UNSAFE"));
        assert.ok(csv.includes('offline-failed'));
        await page.locator('.sync-retry').click();
        await page.waitForFunction(() => document.querySelector('.sync-retry').classList.contains('d-none'));
        assert.ok(calls.some(call => call.action === 'sync_job_retry'));
        syncFixtureJob = {...syncFixtureJob,job_status:'partial',failed_count:1};
        await page.goto(base + '/?mode=' + mode);
        await page.locator('.sync-dismiss').waitFor({state:'visible'});
        await page.locator('.sync-dismiss').click();
        assert.equal(await page.locator('.sync-heading').isVisible(), false);
        await page.goto(base + '/?mode=' + mode);
        await page.waitForFunction(() => document.querySelector('.sync-counts')?.textContent.includes('1 aktarılamadı'));
        assert.equal(await page.locator('.sync-heading').isVisible(), false);
        if (mode === 'giden-list') await page.locator('.personel-action-toolbar .dropdown-toggle').click();
    await page.locator(syncButton).click();
        await page.locator('.swal2-confirm').click();
        await page.locator('.sync-heading').waitFor({state:'visible'});
        assert.equal(await page.locator('.sync-dismiss').isVisible(), false);
        assert.deepEqual(errors,[]);
        console.log('OK: background sync start, progress, page reload, resume, completion and CSRF.');
    } finally {
        await browser?.close();
        await new Promise(resolve=>server.close(resolve));
    }
})().catch(error=>{console.error(error);process.exit(1);});
