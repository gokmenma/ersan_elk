
<?php 

require_once "../../../vendor/autoload.php";
use App\Model\SmsSablonModel;

$SmsSablon = new SmsSablonModel();

$sablonlar = $SmsSablon->getAllTemplates();

?>


<div class="modal-header">
	<h5 class="modal-title">Şablondan Seç</h5>
	<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
</div>
<div class="modal-body">
	<div class="list-group">
		<?php if (!empty($sablonlar)): ?>
			<?php foreach ($sablonlar as $sablon): ?>
				<a href="#" class="list-group-item list-group-item-action sablon-sec" data-icerik="<?= htmlspecialchars($sablon->icerik) ?>">
					<div class="fw-bold mb-1"><?= htmlspecialchars($sablon->baslik) ?></div>
					<div class="small text-muted"><?= htmlspecialchars(mb_strimwidth($sablon->icerik, 0, 60, '...')) ?></div>
				</a>
			<?php endforeach; ?>
		<?php else: ?>
			<div class="alert alert-info">Kayıtlı şablon bulunamadı.</div>
		<?php endif; ?>
	</div>
</div>
<div class="modal-footer">
	<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
</div>
<script>
$(document).on('click', '.sablon-sec', function(e) {
	e.preventDefault();
	var icerik = $(this).data('icerik');
	// Ana sayfadaki mesaj alanına içeriği ekle
	if(window.parent && window.parent.document) {
		var textarea = window.parent.document.getElementById('message');
		if(textarea) textarea.value = icerik;
		// Modalı kapat
		if(window.parent.$) window.parent.$('.modal').modal('hide');
	}
});

</script>