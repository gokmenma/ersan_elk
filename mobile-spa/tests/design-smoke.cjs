const {chromium}=require('@playwright/test');
const assert=require('node:assert/strict');
const path=require('node:path');
const os=require('node:os');
const baseUrl=process.env.MOBILE_SPA_TEST_URL || 'http://localhost:5173';
(async()=>{
 const browser=await chromium.launch({headless:true});
 const errors=[];
 const permissions={personnel:true,vehicles:true,reports:true,requests:true,tasks:true,documents:true,invoices:true,cash:true,current_accounts:true,shifts:true,payroll:true,notices:true,illegal:true,support:true};
 const context=await browser.newContext({viewport:{width:390,height:844},isMobile:true,hasTouch:true});
 const page=await context.newPage(); page.on('pageerror',e=>errors.push(e.message));
 await context.route('**/api/**',async route=>{
 const url=new URL(route.request().url()); let body={success:true};
 if(url.pathname.endsWith('auth.php')) body={success:true,authenticated:true,user:{name:'Gökmen Akın',username:'gokmen',initials:'GA'},company:{name:'Ersan Elektrik',id:'encrypted-company'},companies:[{id:'encrypted-company',name:'Ersan Elektrik'}],permissions};
 else if(url.pathname.endsWith('dashboard.php')) body={success:true,data:{personnel:permissions.personnel?{total:128,working:104,on_leave:12}:undefined,vehicles:permissions.vehicles?{total:42,field:34,service:3,idle:5}:undefined,operations:permissions.reports?[{key:'sealing',label:'Mühürleme',daily:24,monthly:628},{key:'cut_open',label:'Kesme / Açma',daily:48,monthly:1240}]:[],announcements:[]}};
 else if(url.pathname.endsWith('personnel.php')) body={success:true,items:[],summary:{total:128,active:104,passive:24},departments:[],pagination:{page:1,total:0,has_more:false},can_edit:false};
 else if(url.pathname.endsWith('requests.php')) body={success:true,counts:{avans:0,izin:0,talep:0,total:0},permissions:{avans:true,izin:true,talep:true},data:{avans:[],izin:[],talep:[]}};
 else if(url.pathname.endsWith('fatura.php')) body={success:true,data:undefined,summary:{},pagination:{page:1,total:0,has_more:false}};
 else if(url.pathname.endsWith('bordro.php')) body={success:true,donemler:[{id:'enc-d1',name:'Ocak 2026',closed:false,start_date:'01.01.2026',end_date:'31.01.2026'}],selected_donem:{id:'enc-d1',name:'Ocak 2026',closed:false,start_date:'01.01.2026',end_date:'31.01.2026'},is_closed:false,summary:{personel:2,net:65000,alacak:70000,kesinti:5000,banka:45000,elden:15000,sodexo:5000,diger:0,hesaplanan:2,hesaplanmayan:0},items:[{token:'bp-1',personel_token:'p-1',name:'Ali Yılmaz',initials:'AY',avatar:null,department:'Saha',job:'Teknisyen',is_calculated:true,net:32500,alacak:35000,kesinti:2500,icra:0,banka:22500,sodexo:2500,elden:7500,diger:0,work_days:30}]};
 else body={success:true,items:[],summary:undefined,pagination:{page:1,total:0,has_more:false},kategoriler:[],hesap_adlari:[]};
 await route.fulfill({json:body});
 });
 await page.goto(baseUrl + '/');await page.getByText('BUGÜN SAHADA').waitFor();
 await page.screenshot({path:path.join(os.tmpdir(), 'ersan-mobile-home.png'),fullPage:true});
 for(const width of [320,390,480]){
 await page.setViewportSize({width,height:844});
 for(const path of ['/','/personel','/talepler','/raporlar','/gorevler','/profil','/cari-takip','/fatura','/kasa','/bordro']){
 await page.goto(baseUrl + '/#'+path);await page.waitForTimeout(400);
 const dimensions=await page.evaluate(()=>({viewport:innerWidth,scroll:document.documentElement.scrollWidth}));
 assert(dimensions.scroll<=dimensions.viewport,`${path} overflows ${width}: ${dimensions.scroll}`);
 assert(await page.locator('h1').count()>0,`${path} no heading`);
 }
 }
 await page.setViewportSize({width:390,height:844});await page.goto(baseUrl + '/');await page.getByText('BUGÜN SAHADA').waitFor();
 const more=page.getByRole('button',{name:'Daha fazla menüsünü aç'});await more.click();
 await page.getByRole('dialog').waitFor();await page.waitForTimeout(300);await page.screenshot({path:path.join(os.tmpdir(), 'ersan-mobile-menu.png'),fullPage:false});
 assert.equal(await page.evaluate(()=>document.body.style.overflow),'hidden');
 await page.keyboard.press('Shift+Tab');assert.equal(await page.evaluate(()=>document.activeElement.textContent.trim()),'Güvenli Çıkış');
 await page.keyboard.press('Tab');assert.equal(await page.evaluate(()=>document.activeElement.getAttribute('aria-label')),'Kapat');
 await page.keyboard.press('Escape');await page.getByRole('dialog').waitFor({state:'hidden'});
 assert.equal(await page.evaluate(()=>document.body.style.overflow),'');
 assert.equal(await more.getAttribute('aria-expanded'),'false');
 for(const key of Object.keys(permissions)) permissions[key]=false;
 await page.reload();await page.getByText('Görüntülenebilir modül bulunmuyor').waitFor();
 assert.equal(await page.locator('.bottom-nav .nav-link').count(),2);
 assert.equal(await page.locator('.bento-overview').count(),0);
 assert.equal(await page.locator('.quick-action-tile').count(),0);
 await more.click();assert.equal(await page.getByRole('button',{name:'Kasa Yönetimi',exact:true}).count(),0);
 assert.equal(await page.locator('.nav-sheet-item').count(),2);
 await page.keyboard.press('Escape');await page.goto(baseUrl + '/#/personel');await page.waitForTimeout(300);assert.equal(new URL(page.url()).hash,'#/');
 const guest = await browser.newContext({viewport:{width:320,height:740}});
 const loginPage = await guest.newPage();
 await guest.route('**/api/auth.php**', route=>route.fulfill({json:{success:true,authenticated:false,permissions:{}}}));
 await loginPage.goto(baseUrl + '/#/giris');
 await loginPage.getByRole('button',{name:'Giriş yap',exact:true}).waitFor();
 assert.equal(await loginPage.evaluate(()=>document.documentElement.scrollWidth),320);
 assert.equal(await loginPage.locator('.auth-screen').evaluate(el=>el.scrollWidth),320);
 assert.equal(await loginPage.locator('.auth-field input').first().evaluate(el=>getComputedStyle(el).fontSize),'16px');
 assert.equal(await loginPage.locator('.auth-visual').evaluate(el=>getComputedStyle(el).backgroundImage),'none');
 await loginPage.getByRole('button',{name:'Şifreyi göster',exact:true}).click();
 assert.equal(await loginPage.locator('input[autocomplete="current-password"]').getAttribute('type'),'text');
 assert.equal(await loginPage.locator('.auth-screen').evaluate(el=>el.scrollWidth),320);
 await loginPage.screenshot({path:path.join(os.tmpdir(), 'ersan-mobile-login.png'),fullPage:true});
 await guest.close();
 assert.deepEqual(errors,[]);
 console.log('PASS: 27 route/viewport checks; menu focus, Escape, scroll lock; permission-filtered navigation and route guard; login layout and password visibility. API responses mocked.');
 await browser.close();
})().catch(e=>{console.error(e);process.exit(1)});
