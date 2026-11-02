<?php
use App\Helper\Form;
use App\Helper\Security;
use App\Model\PuantajMutabakatModel;
use App\Service\Gate;
Gate::authorizeOrDie('puantaj/mutabakat');
$maintitle='Puantaj Yönetimi'; $title='Puantaj Mutabakatı';
$monthNames=['01'=>'Ocak','02'=>'Şubat','03'=>'Mart','04'=>'Nisan','05'=>'Mayıs','06'=>'Haziran','07'=>'Temmuz','08'=>'Ağustos','09'=>'Eylül','10'=>'Ekim','11'=>'Kasım','12'=>'Aralık'];
$periodOptions=[];
foreach ((new PuantajMutabakatModel())->periods((int)($_SESSION['firma_id']??0)) as $period) {
    [$year,$month]=explode('-',(string)$period['donem']);
    $periodOptions[Security::encrypt((int)$period['id'])]=($monthNames[$month]??$month).' '.$year;
}
$sources=[
    'okuma'=>['Endeks Okuma','bx-tachometer'],
    'kesme'=>['Kesme/Açma','bx-cut'],
    'sayac'=>['Sayaç Sökme-Takma','bx-transfer-alt'],
    'muhurleme'=>['Mühürleme','bx-lock-alt'],
];
?>
<script>try{document.documentElement.classList.toggle('mutabakat-summary-hidden',localStorage.getItem('mutabakat_summary_cards_state')==='hidden');document.documentElement.classList.toggle('mutabakat-info-hidden',localStorage.getItem('mutabakat_info_state')==='hidden')}catch(e){}</script>
<meta name="csrf-token" content="<?= htmlspecialchars(Security::csrf(),ENT_QUOTES,'UTF-8') ?>">
<?php include 'layouts/breadcrumb.php'; ?>
<div class="container-fluid">
  <div class="row align-items-center mb-3">
    <div class="col-lg-7 col-12 d-flex align-items-center gap-3">
      <div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px"><i class="bx bx-git-compare fs-4"></i></div>
      <div>
        <h4 class="font-size-16 fw-bold text-dark mb-0">Puantaj Mutabakatı</h4>
        <p class="font-size-12 text-muted mb-0">Ay sonunda alınan puantaj kopyası ile dış sistemdeki güncel veriler arasındaki farklar</p>
      </div>
    </div>
    <div class="col-lg-5 col-12 personel-action-toolbar d-flex align-items-center justify-content-lg-end gap-2 mt-2 mt-lg-0">
      <div class="mutabakat-period-select">
        <?= Form::FormSelect2('mutabakatDonem',$periodOptions,array_key_first($periodOptions),'Dönem','calendar','key','','form-select select2') ?>
      </div>
      <button type="button" id="btnShowInfo" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" title="Bu sayfa nasıl kullanılır?" aria-label="Bu sayfa nasıl kullanılır?"><i class="bx bx-help-circle"></i></button>
      <button type="button" id="btnToggleSummaryCards" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" title="Özet kartlarını gizle" aria-label="Özet kartlarını gizle" aria-expanded="true"><i class="bx bx-chevron-up"></i></button>
    </div>
  </div>

  <div id="mutabakatInfo" class="card mb-3">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center gap-2">
        <div class="bg-info-subtle text-info rounded-3 d-flex align-items-center justify-content-center" style="width:38px;height:38px"><i class="bx bx-info-circle fs-5"></i></div>
        <div><h5 class="font-size-14 fw-bold mb-0">Mutabakat Nasıl Çalışır?</h5><small class="text-muted">Seçili dönemin otomatik kontrol takvimi ve ilerleyişi</small></div>
      </div>
      <button type="button" class="btn-close" id="btnHideInfo" aria-label="Bilgilendirmeyi kapat"></button>
    </div>
    <div class="card-body">
      <div id="mutabakatTimeline" class="mutabakat-timeline mb-3"></div>
      <div class="row g-3 font-size-12">
        <div class="col-12 col-lg-4">
          <div class="fw-semibold mb-1"><i class="bx bx-camera me-1 text-primary"></i>Ay sonu kopyası</div>
          <div class="text-muted">Ayın son günü saat 23:55'te Endeks Okuma, Kesme/Açma, Sayaç Sökme-Takma ve Mühürleme kayıtlarının puantajdaki hali mühürlenerek saklanır. Bu kopya sonradan değişmez; tablodaki <strong>Önceki</strong> değer budur.</div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="fw-semibold mb-1"><i class="bx bx-refresh me-1 text-primary"></i>Ayın 1, 2 ve 3'ünde kontrol</div>
          <div class="text-muted">Her gece 03:00'te kapanan ayın her günü dış sistemden (online sorgulama) yeniden çekilir ve kopyayla karşılaştırılır; dış sistemdeki değer <strong>Güncel</strong> olarak görünür. 1. ve 2. gün ön kontrol, 3. gün final kontroldür. Dış servis boş yanıt verirse fark üretilmez, kontrol eksik sayılır. Ayarlarda e-posta alıcısı tanımlıysa fark raporu CSV olarak gönderilir.</div>
        </div>
        <div class="col-12 col-lg-4">
          <div class="fw-semibold mb-1"><i class="bx bx-check-shield me-1 text-primary"></i>İnceleme ve bordro</div>
          <div class="text-muted">Bir satıra tıklayarak alanları karşılaştırın; doğruysa <strong>Uygula</strong> ile güncel değeri puantaja yazın. Uygulanan fark sonrası dönemdeki bordrolar yeniden hesaplanmalıdır. Final kontrol tamamlanıp açık fark kalmayana kadar dönem <strong>Mutabık</strong> olmaz ve bordro dönemi kapatılamaz.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div class="status-filter-group mutabakat-source-tabs" role="tablist" aria-label="Kaynak">
      <?php foreach ($sources as $key=>[$label,$icon]): ?>
      <input type="radio" class="btn-check" name="mutabakatKaynak" id="kaynak_<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>" value="<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>" autocomplete="off" <?= $key==='okuma'?'checked':'' ?>>
      <label class="btn" for="kaynak_<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>"><i class="bx <?= htmlspecialchars($icon,ENT_QUOTES,'UTF-8') ?>"></i><?= htmlspecialchars($label,ENT_QUOTES,'UTF-8') ?><span class="badge rounded-pill bg-secondary-subtle text-secondary source-count" data-source="<?= htmlspecialchars($key,ENT_QUOTES,'UTF-8') ?>">0</span></label>
      <?php endforeach; ?>
    </div>
    <div id="periodStatus" class="d-flex align-items-center gap-2 font-size-12 text-muted"></div>
  </div>

  <div id="summaryCardsContainer" class="row g-3 mb-3">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">TOPLAM FARK</span><div class="summary-kpi-icon bg-primary-subtle text-primary border border-primary-subtle"><i class="bx bx-git-compare"></i></div></div>
        <h3 class="summary-kpi-value my-1" id="sumTotal">0</h3>
        <div class="d-flex justify-content-between align-items-center gap-2"><span class="summary-kpi-subtext" id="sumTotalSub">—</span><button type="button" class="btn btn-sm btn-subtle-primary rounded-pill px-2 py-0 status-quick-filter summary-pill-btn" data-status="all"><i class="bx bx-layer"></i> Tümü</button></div>
      </div></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">ONAY BEKLEYEN</span><div class="summary-kpi-icon bg-warning-subtle text-warning border border-warning-subtle"><i class="bx bx-time-five"></i></div></div>
        <h3 class="summary-kpi-value my-1 text-warning" id="sumOpen">0</h3>
        <div class="d-flex justify-content-between align-items-center gap-2"><span class="summary-kpi-subtext">İncelenip uygulanması gereken</span><button type="button" class="btn btn-sm btn-subtle-warning rounded-pill px-2 py-0 status-quick-filter summary-pill-btn active" data-status="acik"><i class="bx bx-filter-alt"></i> Göster</button></div>
      </div></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">UYGULANAN</span><div class="summary-kpi-icon bg-success-subtle text-success border border-success-subtle"><i class="bx bx-check-double"></i></div></div>
        <h3 class="summary-kpi-value my-1 text-success" id="sumApplied">0</h3>
        <div class="d-flex justify-content-between align-items-center gap-2"><span class="summary-kpi-subtext">Puantaja yazılmış farklar</span><button type="button" class="btn btn-sm btn-subtle-success rounded-pill px-2 py-0 status-quick-filter summary-pill-btn" data-status="uygulandi"><i class="bx bx-filter-alt"></i> Göster</button></div>
      </div></div>
    </div>
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="card summary-kpi-card h-100 mb-0"><div class="card-body p-2 px-3 d-flex flex-column justify-content-between">
        <div class="d-flex justify-content-between align-items-center"><span class="summary-kpi-label">NET MİKTAR ETKİSİ</span><div class="summary-kpi-icon bg-info-subtle text-info border border-info-subtle"><i class="bx bx-trending-up"></i></div></div>
        <h3 class="summary-kpi-value my-1" id="sumNet">0</h3>
        <div class="d-flex justify-content-between align-items-center gap-2"><span class="summary-kpi-subtext" id="sumNetSub">Bekleyen farklar uygulanırsa</span></div>
      </div></div>
    </div>
  </div>

  <div id="mutabakatListCard" class="card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
      <div class="d-flex align-items-center gap-2">
        <div class="bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center" style="width:38px;height:38px"><i class="bx bx-list-ul fs-5"></i></div>
        <div><h5 class="font-size-14 fw-bold mb-0" id="listTitle">Endeks Okuma Farkları</h5><small class="text-muted" id="listSubtitle">Okuyucu bazında günlük okunan abone sayıları</small></div>
      </div>
    </div>
    <div class="card-body">
      <table id="mutabakatTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0">
        <thead><tr>
          <th data-filter="date">Tarih</th>
          <th data-filter="string">Kayıt</th>
          <th data-filter="select" data-filter-options="Yeni kayıt|Kayıt silinmiş|Miktar arttı|Miktar azaldı|Bilgi değişti">Değişiklik</th>
          <th data-filter="number">Önceki</th>
          <th data-filter="number">Güncel</th>
          <th data-filter="number">Fark</th>
          <th data-filter="select" data-filter-options="Onay bekliyor|Uygulandı">Durum</th>
          <th data-filter="none">İşlem</th>
        </tr></thead>
      </table>
    </div>
  </div>
</div>
<style>
#summaryCardsContainer,#mutabakatInfo{overflow:hidden;max-height:1100px;opacity:1;transition:max-height .3s ease,opacity .3s ease,margin .3s ease,padding .3s ease}
.mutabakat-summary-hidden #summaryCardsContainer,.mutabakat-info-hidden #mutabakatInfo{max-height:0!important;opacity:0;margin-top:0!important;margin-bottom:0!important;padding-top:0!important;padding-bottom:0!important;border-width:0!important;pointer-events:none}
@media (prefers-reduced-motion:reduce){#summaryCardsContainer,#mutabakatInfo{transition:none}}
.mutabakat-period-select{min-width:190px}
.mutabakat-timeline{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px}
.mutabakat-step{position:relative;border:1px solid var(--bs-border-color);border-radius:12px;padding:10px 12px;background:var(--bs-tertiary-bg)}
.mutabakat-step .step-icon{width:28px;height:28px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:1rem}
.mutabakat-step .step-title{font-weight:600;font-size:.8rem;margin-top:6px}
.mutabakat-step .step-time{font-size:.72rem;color:var(--bs-secondary-color)}
.mutabakat-step .step-state{font-size:.72rem;margin-top:4px}
.mutabakat-step.is-current{border-color:var(--bs-primary);box-shadow:0 0 0 2px var(--bs-primary-bg-subtle)}
@media (max-width:991.98px){.mutabakat-timeline{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:575.98px){.mutabakat-timeline{grid-template-columns:1fr}}
.mutabakat-source-tabs{background:#f8f9fa;padding:4px;border-radius:50px;border:1px solid #e2e8f0;display:inline-flex;flex-wrap:wrap;align-items:center;gap:2px}
.mutabakat-source-tabs .btn-check+.btn{margin:0;border:none!important;border-radius:50px!important;font-size:.8rem;font-weight:600;padding:7px 16px;color:#64748b;display:inline-flex;align-items:center;gap:6px;background:transparent!important}
.mutabakat-source-tabs .btn-check+.btn i{font-size:1.05rem}
.mutabakat-source-tabs .btn-check:checked+.btn{background:#556ee6!important;color:#fff!important;box-shadow:0 2px 6px rgba(85,110,230,.35)}
.mutabakat-source-tabs .btn-check:checked+.btn .source-count{background:rgba(255,255,255,.25)!important;color:#fff!important}
[data-bs-theme="dark"] .mutabakat-source-tabs{background:#2a3042;border-color:#32394e}
[data-bs-theme="dark"] .mutabakat-source-tabs .btn-check+.btn{color:#a6b0cf}
#mutabakatTable tbody tr:not(.child-detail-row){cursor:pointer}
#mutabakatTable tbody tr.shown>td{background:var(--bs-primary-bg-subtle)}
.mutabakat-detail{padding:8px 4px}
.mutabakat-detail table{max-width:760px}
.mutabakat-detail tr.changed td{background:var(--bs-warning-bg-subtle)}
.table-action-btn{width:27px;height:27px;padding:0;display:inline-flex;align-items:center;justify-content:center}
</style>
<script>
$(function(){
 const api='views/puantaj/mutabakat-api.php';
 const csrf=$('meta[name="csrf-token"]').attr('content');
 const sources={
  okuma:{label:'Endeks Okuma',hint:'Okuyucu bazında günlük okunan abone sayıları',unit:'abone'},
  kesme:{label:'Kesme/Açma',hint:'Ekip bazında sonuçlanan kesme/açma iş emri adetleri',unit:'iş'},
  sayac:{label:'Sayaç Sökme-Takma',hint:'Ekip bazında sökülen/takılan sayaç iş emirleri',unit:'sayaç'},
  muhurleme:{label:'Mühürleme',hint:'Ekip bazında mühürleme iş emri adetleri',unit:'iş'}
 };
 const diffTypes={
  eklenmis:['Yeni kayıt','info','bx-plus-circle','Kapanıştan sonra dış sistemde eklenmiş'],
  silinmis:['Kayıt silinmiş','danger','bx-minus-circle','Kapanışta vardı, dış sistemde artık yok'],
  miktar_artmis:['Miktar arttı','success','bx-up-arrow-alt','Dış sistemdeki adet kapanıştakinden fazla'],
  miktar_azalmis:['Miktar azaldı','warning','bx-down-arrow-alt','Dış sistemdeki adet kapanıştakinden az'],
  alan_degismis:['Bilgi değişti','secondary','bx-edit-alt','Adet aynı, kayıt bilgileri değişmiş']
 };
 const periodStates={snapshot_bekliyor:['Kopya bekleniyor','secondary'],on_kontrol:['Ön kontrolde','info'],fark_var:['Fark var','danger'],mutabik:['Mutabık','success'],hata:['Kontrol eksik','warning']};
 const state={kaynak:'okuma',durum:'acik'};
 const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
 const num=v=>Number(v||0).toLocaleString('tr-TR',{maximumFractionDigits:2});
 const signed=v=>{const n=Number(v||0);return (n>0?'+':n<0?'−':'')+num(Math.abs(n))};
 const trDate=v=>{if(!v)return '';const p=String(v).substring(0,10).split('-');return p.length===3?`${p[2]}.${p[1]}.${p[0]}`:esc(v)};
 const badge=(text,color,icon,title)=>`<span class="badge bg-${color}-subtle text-${color} border border-${color}-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold"${title?` title="${esc(title)}"`:''}>${icon?`<i class="bx ${icon} me-1"></i>`:''}${esc(text)}</span>`;

 $('#mutabakatDonem').select2({minimumResultsForSearch:Infinity,width:'100%'});

 const table=$('#mutabakatTable').DataTable(applyLengthStateSave({...getDatatableOptions(),processing:true,serverSide:true,order:[[0,'desc']],
  ajax:{url:api,type:'POST',data:d=>{d.action='list';d.kaynak=state.kaynak;d.durum=state.durum;d.period_id=$('#mutabakatDonem').val()||''}},
  columns:[
   {data:'islem_tarihi',render:v=>trDate(v)},
   {data:'kayit',orderable:false,render:(v,t,r)=>`<div class="fw-semibold text-truncate" style="max-width:320px" title="${esc(v)}">${esc(v)}</div>${r.kayit_detay?`<div class="text-muted font-size-11 text-truncate" style="max-width:320px">${esc(r.kayit_detay)}</div>`:''}`},
   {data:'fark_tipi',render:(v,t,r)=>{const d=diffTypes[v]||[v,'secondary','',''];const extra=v==='alan_degismis'&&r.degisen_alanlar.length?`<div class="text-muted font-size-11 mt-1">${esc(r.degisen_alanlar.join(', '))}</div>`:'';return badge(d[0],d[1],d[2],d[3])+extra}},
   {data:'eski_miktar',className:'text-end',render:(v,t,r)=>r.fark_tipi==='eklenmis'?'<span class="text-muted">—</span>':num(v)},
   {data:'yeni_miktar',className:'text-end',render:(v,t,r)=>r.fark_tipi==='silinmis'?'<span class="text-muted">—</span>':num(v)},
   {data:null,className:'text-end fw-semibold',render:r=>{const n=Number(r.yeni_miktar)-Number(r.eski_miktar);return n===0?'<span class="text-muted">0</span>':`<span class="text-${n>0?'success':'danger'}">${signed(n)}</span>`}},
   {data:'durum',render:(v,t,r)=>v==='uygulandi'?badge('Uygulandı','success','bx-check-double',r.cozum_tarihi?`${trDate(r.cozum_tarihi)} · ${r.cozum_gerekcesi||''}`:''):badge('Onay bekliyor','warning','bx-time-five','')},
   {data:null,orderable:false,searchable:false,className:'text-center',width:'90px',render:r=>r.durum==='acik'
     ?`<div class="action-btn-group"><button type="button" class="btn btn-subtle-success table-action-btn btn-apply" data-id="${esc(r.encrypted_id)}" title="Güncel veriyi puantaja uygula"><i class="bx bx-check"></i></button></div>`
     :`<span class="text-muted" title="${esc(r.cozum_tarihi?'Uygulandı: '+trDate(r.cozum_tarihi):'Uygulandı')}"><i class="bx bx-lock-alt"></i></span>`}
  ],
  language:{...(getDatatableOptions().language||{}),emptyTable:'Bu kaynak için gösterilecek fark yok.',zeroRecords:'Filtrelere uyan fark bulunamadı.'}
 }));

 function detailHtml(r){
  const rows=(r.alanlar||[]).map(f=>`<tr class="${f.degisti?'changed':''}"><td class="text-muted">${esc(f.alan)}</td><td>${f.eski===null?'<span class="text-muted">—</span>':esc(f.eski)}</td><td>${f.yeni===null?'<span class="text-muted">—</span>':esc(f.yeni)}</td></tr>`).join('');
  const note={eklenmis:'Bu kayıt dönem kapanışında puantajda yoktu; dış sistemde sonradan oluşmuş.',silinmis:'Bu kayıt dönem kapanışında puantajda vardı; dış sistemde artık bulunmuyor.'}[r.fark_tipi]||'Değişen alanlar sarı ile işaretlenmiştir.';
  return `<div class="mutabakat-detail"><div class="font-size-12 text-muted mb-2"><i class="bx bx-info-circle me-1"></i>${esc(note)} <span class="ms-2">Kayıt anahtarı: <code>${esc(r.kaynak_anahtar)}</code></span></div>
   <table class="table table-sm table-bordered mb-0 font-size-12"><thead><tr><th style="width:30%">Alan</th><th>Önceki (kapanış)</th><th>Güncel (dış sistem)</th></tr></thead><tbody>${rows||'<tr><td colspan="3" class="text-muted">Detay bilgisi yok.</td></tr>'}</tbody></table></div>`;
 }

 $('#mutabakatTable tbody').on('click','tr',function(e){
  if($(e.target).closest('td:last-child, .btn, a').length)return;
  const row=table.row(this);
  if(!row.data())return;
  if(row.child.isShown()){row.child.hide();$(this).removeClass('shown')}
  else{row.child(detailHtml(row.data()),'child-detail-row').show();$(this).addClass('shown')}
 });

 const trDateTime=v=>v?`${trDate(v)} ${String(v).substring(11,16)}`:'';
 const pad=n=>String(n).padStart(2,'0');
 function renderTimeline(p){
  const t=p&&p.zaman_cizelgesi;
  if(!t||!t.donem_sonu){$('#mutabakatTimeline').html('<div class="text-muted font-size-12">Bu dönem için henüz kontrol takvimi oluşmadı. İlk kopya ayın son günü 23:55\'te alınır.</div>');return}
  const end=new Date(t.donem_sonu+'T23:55:00'),now=new Date();
  const next=new Date(end.getFullYear(),end.getMonth()+1,1);
  const plan=day=>new Date(next.getFullYear(),next.getMonth(),day,3,0);
  const fmt=dt=>`${pad(dt.getDate())}.${pad(dt.getMonth()+1)}.${dt.getFullYear()} ${pad(dt.getHours())}:${pad(dt.getMinutes())}`;
  const steps=[];
  const late=t.snapshot_tarihi&&new Date(String(t.snapshot_tarihi).replace(' ','T'))>new Date(end.getTime()+3600000);
  steps.push(t.snapshot_tarihi
   ?{title:'Ay sonu kopyası',time:fmt(end),done:true,state:late?['Geç alındı · '+trDateTime(t.snapshot_tarihi),'warning','bx-error']:['Alındı · '+trDateTime(t.snapshot_tarihi),'success','bx-check'],
     hint:late?'Kopya ay sonunda değil ilk kontrolde alındı; ay sonu ile bu saat arasında puantaja giren kayıtlar kopyaya dahil oldu.':''}
   :{title:'Ay sonu kopyası',time:fmt(end),state:now>end?['Alınmadı','warning','bx-error']:['Planlandı','secondary','bx-time'],done:false});
  [1,2,3].forEach(day=>{
   const k=(t.kontroller||{})[day],at=plan(day),title=day===3?'3. gün · Final kontrol':`${day}. gün · Ön kontrol`;
   let state,done=false;
   if(!k)state=now>new Date(at.getTime()+3600000)?['Çalışmadı','warning','bx-error']:['Bekliyor','secondary','bx-time'];
   else if(k.calisiyor>0)state=['Çalışıyor','info','bx-loader-alt bx-spin'];
   else if(k.basarili>=t.beklenen){state=['Tamamlandı · '+trDateTime(k.bitis),'success','bx-check'];done=true}
   else state=[`Eksik · ${num(k.basarili)}/${num(t.beklenen)} sorgu`,'warning','bx-error'];
   steps.push({title,time:fmt(at),state,done});
  });
  const finalDone=steps[3].done;
  steps.push({title:'Sonuç',time:'Final kontrol sonrası',done:p.durum==='mutabik',
   state:p.durum==='mutabik'?['Mutabık · dönem kapatılabilir','success','bx-check-double']:finalDone?['Fark var · inceleme bekliyor','danger','bx-error-circle']:['Final kontrol bekleniyor','secondary','bx-time']});
  const current=steps.findIndex(s=>!s.done);
  $('#mutabakatTimeline').html(steps.map((s,i)=>`<div class="mutabakat-step${i===current?' is-current':''}"${s.hint?` title="${esc(s.hint)}"`:''}>
   <span class="step-icon bg-${s.state[1]}-subtle text-${s.state[1]}"><i class="bx ${s.state[2]}"></i></span>
   <div class="step-title">${esc(s.title)}</div><div class="step-time">${esc(s.time)}</div>
   <div class="step-state text-${s.state[1]}">${esc(s.state[0])}</div></div>`).join(''));
 }

 function renderPeriod(d){
  renderTimeline(d.donem);
  if(!d.donem){$('#periodStatus').html('<span>Henüz mutabakat dönemi oluşmadı.</span>');return}
  const s=periodStates[d.donem.durum]||[d.donem.durum,'secondary'];
  let html=`<span>Dönem durumu:</span>${badge(s[0],s[1],'','')}`;
  if(d.son_kontrol_gunu)html+=`<span>Son kontrol: ayın ${esc(d.son_kontrol_gunu)}. günü</span>`;
  if(d.donem.yeniden_hesaplama_gerekli)html+=badge('Bordro yeniden hesaplanmalı','danger','bx-error','Uygulanan farklar bordroya henüz yansımadı');
  $('#periodStatus').html(html);
 }

 function summary(){
  $.post(api,{action:'summary',kaynak:state.kaynak,period_id:$('#mutabakatDonem').val()||''},r=>{
   if(r.status!=='success')return;
   const d=r.data,unit=sources[state.kaynak].unit;
   $('#sumTotal').text(num(d.toplam));
   const parts=Object.entries(d.fark_tipleri||{}).filter(([,c])=>c>0).map(([k,c])=>`${(diffTypes[k]||[k])[0]}: ${num(c)}`);
   $('#sumTotalSub').text(parts.length?parts.join(' · '):'Bekleyen fark yok').attr('title',parts.join(' · '));
   $('#sumOpen').text(num(d.acik));
   $('#sumApplied').text(num(d.uygulandi));
   const net=Number(d.net_etki||0);
   $('#sumNet').text(signed(net)+' '+unit).removeClass('text-success text-danger').addClass(net>0?'text-success':net<0?'text-danger':'');
   $('#sumNetSub').text(net===0?'Bekleyen farkların net etkisi yok':`Bekleyen farklar uygulanırsa ${net>0?'artar':'azalır'}`);
   $('.source-count').each(function(){const c=Number((d.kaynaklar||{})[this.dataset.source]||0);$(this).text(num(c)).toggleClass('bg-danger-subtle text-danger',c>0).toggleClass('bg-secondary-subtle text-secondary',c===0)});
   renderPeriod(d);
  },'json');
 }

 function reloadAll(){table.ajax.reload();summary()}

 $('input[name="mutabakatKaynak"]').on('change',function(){
  state.kaynak=this.value;
  $('#listTitle').text(sources[state.kaynak].label+' Farkları');
  $('#listSubtitle').text(sources[state.kaynak].hint);
  reloadAll();
 });
 $('#mutabakatDonem').on('change',reloadAll);
 $('.status-quick-filter').on('click',function(){
  state.durum=this.dataset.status;
  $('.status-quick-filter').removeClass('active');$(this).addClass('active');
  table.ajax.reload();
 });

 $('#mutabakatTable').on('click','.btn-apply',function(){
  const r=table.row($(this).closest('tr')).data();if(!r)return;
  const d=diffTypes[r.fark_tipi]||[r.fark_tipi];
  const html=`<div class="text-start font-size-13">
   <div class="mb-2"><strong>${esc(r.kayit)}</strong><div class="text-muted font-size-12">${esc(trDate(r.islem_tarihi))} · ${esc(sources[r.kaynak]?.label||r.kaynak)} · ${esc(d[0])}</div></div>
   <div class="d-flex align-items-center gap-2 mb-2"><span class="badge bg-light text-dark border px-2 py-1">Önceki: ${num(r.eski_miktar)}</span><i class="bx bx-right-arrow-alt"></i><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Güncel: ${num(r.yeni_miktar)}</span></div>
   <div class="alert alert-warning py-2 px-2 mb-0 font-size-12">Güncel değer puantaja yazılacak. Sonrasında dönemdeki bordrolar yeniden hesaplanmalıdır.</div></div>`;
  Swal.fire({title:'Güncel veriyi uygula',html,input:'textarea',inputLabel:'Çözüm gerekçesi',inputPlaceholder:'Örn: Dış sistemde sonradan girilen kayıt',showCancelButton:true,confirmButtonText:'Uygula',cancelButtonText:'Vazgeç',
   preConfirm:v=>{if(!v||v.trim().length<5){Swal.showValidationMessage('En az 5 karakter girin');return false}return v}})
  .then(x=>{if(!x.isConfirmed)return;
   $.post(api,{action:'apply',id:r.encrypted_id,reason:x.value,csrf_token:csrf},res=>{
    if(res.status==='success'){Swal.fire('Uygulandı',res.message,'success');table.ajax.reload(null,false);summary()}
    else Swal.fire('Hata',res.message,'error');
   },'json').fail(xhr=>Swal.fire('Hata',xhr.responseJSON?.message||'İşlem tamamlanamadı.','error'));
  });
 });

 $('#btnHideInfo,#btnShowInfo').on('click',function(){
  const hidden=this.id==='btnHideInfo'?true:!document.documentElement.classList.contains('mutabakat-info-hidden');
  document.documentElement.classList.toggle('mutabakat-info-hidden',hidden);
  try{localStorage.setItem('mutabakat_info_state',hidden?'hidden':'visible')}catch(e){}
 });

 $('#btnToggleSummaryCards').on('click',function(){
  const hidden=!document.documentElement.classList.contains('mutabakat-summary-hidden');
  document.documentElement.classList.toggle('mutabakat-summary-hidden',hidden);
  try{localStorage.setItem('mutabakat_summary_cards_state',hidden?'hidden':'visible')}catch(e){}
  syncToggle();
 });
 function syncToggle(){
  const hidden=document.documentElement.classList.contains('mutabakat-summary-hidden');
  const label=hidden?'Özet kartlarını göster':'Özet kartlarını gizle';
  $('#btnToggleSummaryCards').attr({'aria-expanded':String(!hidden),title:label,'aria-label':label}).find('i').attr('class',hidden?'bx bx-chevron-down':'bx bx-chevron-up');
 }
 syncToggle();
 summary();
});
</script>
