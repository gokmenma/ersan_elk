/* Real IndexedDB and browser networking; no application database is touched. */
const { chromium } = require('@playwright/test');
const http = require('node:http');
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const rootPath = () => path.resolve(__dirname,'../..');
const queue = fs.readFileSync(path.join(__dirname, '../../views/personel-pwa/assets/js/pwa-offline-queue.js'));
let state;
function reset() {
  state = { account: 'account-a', calls: [], receipts: new Map(), videos: new Map(), counts: { main: 0, photo: 0, video: 0 }, drop: '', reject: '', permission: '', failIndex: null, delay: 0 };
}
function multipart(req, body) {
  const boundary = /boundary=(?:"([^"]+)"|([^;]+))/.exec(req.headers['content-type'] || '');
  const data = {};
  if (!boundary) return data;
  for (const part of body.toString('latin1').split('--' + (boundary[1] || boundary[2]))) {
    const split = part.indexOf('\r\n\r\n');
    if (split < 0) continue;
    const name = /name="([^"]+)"/.exec(part.slice(0, split));
    if (name) data[name[1]] = Buffer.from(part.slice(split + 4).replace(/\r\n$/, ''), 'latin1');
  }
  return data;
}
const server = http.createServer(async (req, res) => {
  if (req.url.startsWith('/assets/libs/sweetalert2/')) { res.setHeader('Content-Type','text/javascript; charset=utf-8');return res.end(fs.readFileSync(path.join(rootPath(), 'assets/libs/sweetalert2/sweetalert2.all.min.js'))); }
  if (req.url.startsWith('/sw.js')) { res.setHeader('Content-Type','text/javascript; charset=utf-8');return res.end(fs.readFileSync(path.join(__dirname,'../../views/personel-pwa/sw.js'))); }
  if (req.url.startsWith('/queue.js') || req.url.startsWith('/assets/js/pwa-offline-queue.js')) { res.setHeader('Content-Type', 'text/javascript; charset=utf-8'); return res.end(queue); }
  if (req.url.startsWith('/worker.js')) {
    res.setHeader('Content-Type', 'text/javascript; charset=utf-8');
    return res.end(`importScripts('/queue.js'); self.addEventListener('message', e => { if(e.data==='flush') e.waitUntil(OfflineQueue.flush({elle:true}).then(r => e.source.postMessage({result:r}))); });`);
  }
  if (!req.url.startsWith('/api.php')) {
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    return res.end(`<script>window.PWA_ACCOUNT_KEY=${JSON.stringify(state.account)};window.testOnline=false;Object.defineProperty(navigator,'onLine',{get:()=>window.testOnline});</script><div id="panel"></div><script src="/queue.js"></script>`);
  }
  const chunks = [];
  for await (const chunk of req) chunks.push(chunk);
  const data = multipart(req, Buffer.concat(chunks));
  const val = key => (data[key] || Buffer.alloc(0)).toString('utf8');
  const action = val('action');
  state.calls.push({ action, index: val('index'), operation: val('operation_key') });
  const reply = (success, value = {}, message = '') => {
    res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify({ success, data: value, message }));
  };
  if (action === 'pwaTransferIdentity') return reply(true, { account_key: state.account });
  if (val('account_key') !== state.account) { res.statusCode = 403; return reply(false); }
  if (state.permission === action) { res.statusCode=403; return reply(false, {transfer_error:'permission'}, 'Oturum'); }
  if (state.reject === action) return reply(false, { transfer_error: 'validation' }, 'Geçersiz fotoğraf');
  if (state.delay) await new Promise(resolve => setTimeout(resolve, state.delay));
  const operation = val('operation_key');
  if (operation && state.receipts.has(operation)) return reply(true, state.receipts.get(operation));
  let output = {};
  if (['saveKacakBildirim', 'updateKacakBildirim', 'createIhbar', 'updateIhbar'].includes(action)) {
    state.counts.main++; output = { target_token: 'encrypted-record' };
  } else if (action === 'pwaTransferPhoto') {
    state.counts.photo++;
  } else if (action === 'pwaVideoStart') {
    const key = val('video_key');
    if (state.receipts.has(key + '_complete')) return reply(true, { completed: true });
    if (!state.videos.has(key)) state.videos.set(key, { size: +val('size'), hash: val('hash'), parts: new Map() });
    output = { parts: [...state.videos.get(key).parts.keys()] };
  } else if (action === 'pwaVideoChunk') {
    const index = +val('index');
    if (index === state.failIndex) { state.failIndex = null; res.statusCode = 503; return reply(false, {}, 'network interruption'); }
    const video = state.videos.get(val('video_key'));
    assert.equal(crypto.createHash('sha256').update(data.chunk).digest('hex'), val('chunk_hash'));
    assert.equal(data.chunk.length, Math.min(262144, video.size - index * 262144));
    video.parts.set(index, data.chunk);
    output = { parts: [...video.parts.keys()] };
  } else if (action === 'pwaVideoComplete') {
    const video = state.videos.get(val('video_key'));
    const bytes = Buffer.concat([...video.parts.entries()].sort((a,b) => a[0]-b[0]).map(x => x[1]));
    assert.equal(bytes.length, video.size);
    assert.equal(crypto.createHash('sha256').update(bytes).digest('hex'), video.hash);
    state.counts.video++; output = { completed: true };
    state.videos.delete(val('video_key'));
  }
  if (operation) state.receipts.set(operation, output);
  if (state.drop === action) { state.drop = ''; res.setHeader('Content-Type','application/json'); return res.end('truncated-response'); }
  reply(true, output);
});
async function record(page, action = 'createIhbar', video = true) {
  return page.evaluate(async ({ action, video }) => {
    const photo = { alan: /Ihbar/.test(action) ? 'fotograflar[]' : 'tutanak_foto', ad: 'photo.jpg', blob: new Blob(['image'], {type:'image/jpeg'}) };
    const bytes = new Uint8Array(600000); for (let i=0; i<bytes.length; i++) bytes[i]=i%251;
    return (await OfflineQueue.ekle(action, {}, [photo], { ilce: 'Test' }, { dosyalar:[photo], videolar: video ? [{dosya:new File([bytes],'video.mp4',{type:'video/mp4'}),sure:20,kapak:''}] : [] })).uuid;
  }, { action, video });
}
async function flush(page) { return page.evaluate(() => { window.testOnline=true; return OfflineQueue.flush({elle:true}); }); }
(async () => {
  reset(); await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  const browser = await chromium.launch({ headless:true });
  let passed = 0;
  async function test(name, fn) {
    reset(); const context = await browser.newContext(); const page = await context.newPage(); await page.goto(base);
    try { await fn(page, context); console.log('PASS ' + name); passed++; }
    finally { await context.close(); }
  }
  try {
    await test('offline save and reopen retain all photos and video bytes', async page => {
      const uuid = await record(page); assert.equal((await page.evaluate(()=>OfflineQueue.flush())).gonderildi,0);
      assert.equal(state.counts.main,0); await page.reload();
      const sizes = await page.evaluate(async uuid => { const k=await OfflineQueue.oku(uuid); return [k.dosyalar.length,k.ekDosyalar.length,k.videolar[0].blob.size]; },uuid);
      assert.deepEqual(sizes,[1,1,600000]); assert.equal((await flush(page)).gonderildi,1);
      assert.deepEqual(state.counts,{main:1,photo:1,video:1});
    });
    await test('lost main response reuses fixed operation without duplicate report', async page => {
      const uuid=await record(page,'saveKacakBildirim',false); state.drop='saveKacakBildirim';
      await flush(page); assert.ok(await page.evaluate(uuid=>OfflineQueue.oku(uuid),uuid));
      await page.reload(); await flush(page); assert.equal(state.counts.main,1); assert.equal(state.counts.photo,1);
    });
    await test('video interruption resumes only missing chunks after reopening', async page => {
      const uuid=await record(page); state.failIndex=1; await flush(page);
      assert.equal(state.counts.video,0);
      const k=await page.evaluate(uuid=>OfflineQueue.oku(uuid),uuid); assert.equal(k.anaGonderildi,true); assert.deepEqual(k.videolar[0].parts,[0]);
      await page.reload(); await flush(page);
      assert.equal(state.calls.filter(c=>c.action==='pwaVideoChunk'&&c.index==='0').length,1);
      assert.equal(state.counts.video,1);
    });
    await test('lost completion response does not retransmit completed video', async page => {
      await record(page); state.drop='pwaVideoComplete'; await flush(page);
      const chunks=state.calls.filter(c=>c.action==='pwaVideoChunk').length;
      await page.reload(); await flush(page); assert.equal(state.counts.video,1);
      assert.equal(state.calls.filter(c=>c.action==='pwaVideoChunk').length,chunks);
    });
    await test('two tabs send one report and one set of attachments', async (page,context) => {
      await record(page); const second=await context.newPage();await second.goto(base);state.delay=80;
      const results=await Promise.all([flush(page),flush(second)]);
      assert.equal(results.reduce((n,r)=>n+r.gonderildi,0),1);assert.deepEqual(state.counts,{main:1,photo:1,video:1});
    });
    await test('service worker and tab share the IndexedDB lease', async page => {
      await record(page); await page.evaluate(async()=>{ await navigator.serviceWorker.register('/worker.js'); await navigator.serviceWorker.ready; });
      state.delay=80;
      const workerResult=page.evaluate(()=>new Promise(resolve=>{ const listener=e=>{if(e.data.result){navigator.serviceWorker.removeEventListener('message',listener);resolve(e.data.result);}};navigator.serviceWorker.addEventListener('message',listener); navigator.serviceWorker.ready.then(r=>r.active.postMessage('flush')); }));
      const results=await Promise.all([flush(page),workerResult]);
      assert.equal(results.reduce((n,r)=>n+r.gonderildi,0),1);assert.deepEqual(state.counts,{main:1,photo:1,video:1});
    });
    await test('account change preserves but does not send another account queue', async page => {
      const uuid=await record(page);state.account='account-b';await page.reload();await flush(page);
      assert.equal(state.counts.main,0);assert.equal((await page.evaluate(()=>OfflineQueue.listele())).length,0);
      state.account='account-a';await page.reload();assert.ok(await page.evaluate(uuid=>OfflineQueue.oku(uuid),uuid));await flush(page);assert.equal(state.counts.main,1);
    });
    await test('photo rejection preserves the pending video and prevents false completion', async page => {
      const uuid=await record(page);state.reject='pwaTransferPhoto';await flush(page);
      const k=await page.evaluate(uuid=>OfflineQueue.oku(uuid),uuid);assert.equal(k.durum,'hata');assert.equal(await page.evaluate(async uuid=>(await OfflineQueue.oku(uuid)).videolar[0].blob.size,uuid),600000);assert.equal(state.counts.video,0);
      await page.evaluate(()=>OfflineQueue.mountPanel('ihbar','panel'));await page.waitForFunction(()=>document.querySelector('#panel').textContent.includes('Gönderim hatası'));
      assert.ok(!(await page.locator('#panel').textContent()).includes('Tamamlandı'));
    });
    await test('stalled request aborts after 120 seconds and remains queued', async page => {
      const uuid=await record(page,'createIhbar',false);
      await page.evaluate(()=>{const original=window.fetch;window.fetch=(url,options)=>String(url).includes('action=createIhbar')?new Promise((resolve,reject)=>options.signal.addEventListener('abort',()=>reject(new DOMException('aborted','AbortError')))):original(url,options);const timeout=window.setTimeout;window.setTimeout=(fn,ms,...args)=>timeout(fn,ms===120000?30:ms,...args);});
      await flush(page);const k=await page.evaluate(uuid=>OfflineQueue.oku(uuid),uuid);assert.equal(k.durum,'bekliyor');assert.equal(k.deneme,1);
    });
    await test('existing record edits are durable independent operations', async page => {
      await record(page,'updateIhbar',false);await record(page,'updateKacakBildirim',false);await page.reload();
      assert.equal((await flush(page)).gonderildi,2);assert.equal(state.counts.main,2);assert.equal(state.counts.photo,2);
    });
    await test('expired sender lease cannot overwrite another sender progress', async (page,context) => {
      await record(page);const second=await context.newPage();await second.goto(base);state.delay=250;
      const first=flush(page);
      for(let i=0;i<100&&!state.calls.some(c=>c.action==='createIhbar');i++)await new Promise(r=>setTimeout(r,10));
      await second.evaluate(()=>new Promise((resolve,reject)=>{const req=indexedDB.open('ersan-pwa-offline',2);req.onsuccess=()=>{const db=req.result;const tx=db.transaction('transfer_meta','readwrite');const store=tx.objectStore('transfer_meta');const get=store.get('sender');get.onsuccess=()=>store.put({...get.result,expires:0});tx.oncomplete=()=>{db.close();resolve();};tx.onerror=()=>reject(tx.error);};}));
      const results=await Promise.all([first,flush(second)]);
      assert.equal(results.reduce((n,r)=>n+r.gonderildi,0),1);assert.deepEqual(state.counts,{main:1,photo:1,video:1});
      assert.equal((await page.evaluate(()=>OfflineQueue.listele())).length,0);
    });
    await test('authorization failure waits for explicit retry and keeps files', async page => {
      const uuid=await record(page,'createIhbar',false);state.permission='pwaTransferPhoto';await flush(page);
      assert.equal((await page.evaluate(uuid=>OfflineQueue.oku(uuid),uuid)).durum,'oturum');
      state.permission='';await page.evaluate(()=>OfflineQueue.flush());assert.equal(state.counts.photo,0);
      await page.evaluate(uuid=>OfflineQueue.tekrarDene(uuid),uuid);assert.equal(state.counts.photo,1);
    });
    await test('temporary network failure respects retry backoff', async page => {
      await record(page);state.failIndex=1;await flush(page);const attempts=state.calls.filter(c=>c.action==='pwaVideoChunk').length;
      await page.evaluate(()=>OfflineQueue.flush());assert.equal(state.calls.filter(c=>c.action==='pwaVideoChunk').length,attempts);
      await flush(page);assert.equal(state.counts.video,1);
    });
    await test('v1 queue migration retains legacy files and requires account claim', async page => {
      const uuid=await page.evaluate(()=>new Promise((resolve,reject)=>{const req=indexedDB.open('ersan-pwa-offline',1);req.onupgradeneeded=()=>{req.result.createObjectStore('kuyruk',{keyPath:'uuid'});req.result.createObjectStore('referans',{keyPath:'anahtar'});};req.onsuccess=()=>{const db=req.result;const uuid=crypto.randomUUID();const photo={alan:'tutanak_foto',ad:'old.jpg',blob:new Blob(['old'],{type:'image/jpeg'})};const tx=db.transaction('kuyruk','readwrite');tx.objectStore('kuyruk').put({uuid,action:'saveKacakBildirim',alanlar:{client_uuid:uuid},dosyalar:[photo],ekDosyalar:[photo],ekGonderilen:0,anaGonderildi:false,durum:'bekliyor',olusturma:new Date().toISOString()});tx.oncomplete=()=>{db.close();resolve(uuid);};tx.onerror=()=>reject(tx.error);};}));
      await page.reload();await flush(page);assert.equal(state.counts.main,0);
      await page.evaluate(uuid=>OfflineQueue.claimLegacy(uuid),uuid);await flush(page);assert.equal(state.counts.main,1);assert.equal(state.counts.photo,1);
    });
    await test('production service worker serves versioned static queue assets offline', async (page,context) => {
      await page.evaluate(async()=>{await navigator.serviceWorker.register('/sw.js');await navigator.serviceWorker.ready;if(!navigator.serviceWorker.controller)await new Promise(r=>navigator.serviceWorker.addEventListener('controllerchange',r,{once:true}));});
      await context.setOffline(true);
      const source=await page.evaluate(()=>fetch('/assets/js/pwa-offline-queue.js?v=unseen-version').then(r=>r.text()));
      assert.ok(source.includes('function reliableSend'));
      assert.equal(await page.evaluate(async()=>{eval(await fetch('/assets/libs/sweetalert2/sweetalert2.all.min.js').then(r=>r.text()));return typeof window.Swal.fire;}),'function');
    });
    await test('storage quota failure rejects save without claiming success', async page => {
      await page.evaluate(()=>{const original=IDBObjectStore.prototype.put;IDBObjectStore.prototype.put=function(value){if(this.name==='kuyruk')throw new DOMException('quota','QuotaExceededError');return original.call(this,value);};});
      await assert.rejects(()=>record(page),/quota/);assert.equal(state.counts.main,0);
    });
    console.log(`${passed} browser scenarios passed`);
  } finally { await browser.close();server.close(); }
})().catch(e=>{ console.error(e);process.exitCode=1;server.close(); });
