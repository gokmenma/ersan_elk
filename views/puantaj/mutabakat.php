<?php
use App\Helper\Security;
use App\Service\Gate;
Gate::authorizeOrDie('puantaj/mutabakat');
$maintitle='Puantaj Yönetimi'; $title='Puantaj Mutabakatı';
?>
<script>try{document.documentElement.classList.toggle('mutabakat-summary-hidden',localStorage.getItem('mutabakat_summary_cards_state')==='hidden')}catch(e){}</script>
<meta name="csrf-token" content="<?= htmlspecialchars(Security::csrf(),ENT_QUOTES,'UTF-8') ?>">
<?php include 'layouts/breadcrumb.php'; ?>
<div class="container-fluid">
  <div class="row align-items-center mb-3">
    <div class="col"><div class="d-flex align-items-center gap-3"><div class="p-2 bg-primary-subtle text-primary rounded-3 border border-primary-subtle"><i class="bx bx-git-compare fs-4"></i></div><div><h4 class="font-size-16 fw-bold text-dark mb-1">Puantaj Mutabakatı</h4><p class="font-size-12 text-muted mb-0">Endeks, kesme/açma, sayaç sökme-takma ve mühürleme farkları</p></div></div></div>
    <div class="col-auto personel-action-toolbar"><button id="btnToggleSummaryCards" class="btn btn-outline-secondary bg-white top-icon-btn shadow-sm" title="Özet kartlarını gizle" aria-expanded="true"><i class="bx bx-chevron-up"></i></button></div>
  </div>
  <div id="summaryCardsContainer" class="row g-3 mb-3">
    <?php foreach ([['Toplam Dönem','primary','calendar'],['Mutabık','success','check-circle'],['Farklı','danger','error-circle'],['Açık Fark','warning','git-compare']] as $i=>$c): ?>
    <div class="col-12 col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex align-items-center gap-3"><div class="p-2 bg-<?= $c[1] ?>-subtle text-<?= $c[1] ?> rounded-3"><i class="bx bx-<?= $c[2] ?> fs-4"></i></div><div><div class="text-muted font-size-12"><?= htmlspecialchars($c[0],ENT_QUOTES,'UTF-8') ?></div><div id="summary<?= $i ?>" class="summary-kpi-value fw-bold fs-4">0</div></div></div></div></div>
    <?php endforeach; ?>
  </div>
  <div id="mutabakatListCard" class="card"><div class="card-header bg-transparent d-flex justify-content-between align-items-center"><div class="d-flex align-items-center gap-2"><div class="p-2 bg-primary-subtle text-primary rounded-3"><i class="bx bx-list-ul"></i></div><div><h5 class="font-size-14 fw-bold mb-0">Tespit Edilen Farklar</h5><small class="text-muted">Yetkili onayı bekleyen ve uygulanmış değişiklikler</small></div></div></div>
    <div class="card-body"><table id="mutabakatTable" class="table table-bordered table-hover nowrap align-middle w-100 mb-0"><thead><tr><th data-filter="string">Dönem</th><th data-filter="date">Tarih</th><th data-filter="select">Kaynak</th><th data-filter="select">Fark Türü</th><th data-filter="number">Eski</th><th data-filter="number">Yeni</th><th data-filter="select">Durum</th><th data-filter="none">İşlem</th></tr></thead></table></div>
  </div>
</div>
<style>#summaryCardsContainer{overflow:hidden;max-height:1100px;opacity:1;transition:max-height .3s ease,opacity .3s ease,margin .3s ease}.mutabakat-summary-hidden #summaryCardsContainer{max-height:0!important;opacity:0;margin-top:0!important;margin-bottom:0!important;pointer-events:none}.table-action-btn{width:27px;height:27px;padding:0;display:inline-flex;align-items:center;justify-content:center}</style>
<script>
$(function(){
 const csrf=$('meta[name="csrf-token"]').attr('content');
 const sourceLabels={okuma:'Endeks Okuma',kesme:'Kesme/Açma',sayac:'Sayaç Sökme-Takma',muhurleme:'Mühürleme'};
 const table=$('#mutabakatTable').DataTable(applyLengthStateSave({...getDatatableOptions(),processing:true,serverSide:true,ajax:{url:'views/puantaj/mutabakat-api.php',type:'POST',data:{action:'list'}},columns:[{data:'donem'},{data:'islem_tarihi'},{data:'kaynak',render:v=>sourceLabels[v]||v},{data:'fark_tipi'},{data:'eski_miktar'},{data:'yeni_miktar'},{data:'durum'},{data:null,orderable:false,searchable:false,className:'text-center',render:r=>r.durum==='acik'?`<button class="btn btn-subtle-primary table-action-btn btn-apply" data-id="${r.encrypted_id}" title="Güncel veriyi uygula"><i class="bx bx-check"></i></button>`:'-'}]}));
 function summary(){$.post('views/puantaj/mutabakat-api.php',{action:'summary'},r=>{if(r.status==='success'){['toplam','mutabik','farkli','acik_fark'].forEach((k,i)=>$('#summary'+i).text(r.data[k]||0))}},'json')}; summary();
 $('#mutabakatTable').on('click','.btn-apply',function(){const id=this.dataset.id;Swal.fire({title:'Güncel veriyi uygula',input:'textarea',inputLabel:'Çözüm gerekçesi',showCancelButton:true,confirmButtonText:'Uygula',cancelButtonText:'Vazgeç',preConfirm:v=>{if(!v||v.trim().length<5)Swal.showValidationMessage('En az 5 karakter girin');return v}}).then(x=>{if(!x.isConfirmed)return;$.post('views/puantaj/mutabakat-api.php',{action:'apply',id,reason:x.value,csrf_token:csrf},r=>{if(r.status==='success'){Swal.fire('Tamam',r.message,'success');table.ajax.reload(null,false);summary()}else Swal.fire('Hata',r.message,'error')},'json')})});
 $('#btnToggleSummaryCards').on('click',function(){const hidden=!document.documentElement.classList.contains('mutabakat-summary-hidden');document.documentElement.classList.toggle('mutabakat-summary-hidden',hidden);localStorage.setItem('mutabakat_summary_cards_state',hidden?'hidden':'visible');$(this).attr({'aria-expanded':String(!hidden),title:hidden?'Özet kartlarını göster':'Özet kartlarını gizle'}).find('i').attr('class',hidden?'bx bx-chevron-down':'bx bx-chevron-up')});
});
</script>
