<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

$maintitle = "Demirbaş";
$title = "Aparat Deposu";
?>

<div class="container-fluid">
	<?php include 'layouts/breadcrumb.php'; ?>

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h5 class="mb-0">Aparat Deposu</h5>
			<small class="text-muted">Personel bazlı aparat stok ve hareket görünümü</small>
		</div>
		<div class="card-body">
			<ul class="nav nav-pills mb-3" id="aparatDepoTab" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aparatlarPane" type="button">APARATLAR</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" data-bs-toggle="tab" data-bs-target="#aparatHareketPane" type="button">Hareketler</button>
				</li>
			</ul>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="aparatlarPane" role="tabpanel">
					<div class="row g-3 mb-3">
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Depoda</small><h4 id="apCardDepo" class="mb-0">0</h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Personelde</small><h4 id="apCardPersonel" class="mb-0">0</h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Tüketilen</small><h4 id="apCardTuketilen" class="mb-0">0</h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Toplam Çeşit</small><h4 id="apCardCesit" class="mb-0">0</h4></div></div></div>
					</div>

					<div class="table-responsive mb-4">
						<table id="aparatPersonelTable" class="table table-bordered table-hover nowrap w-100">
							<thead class="table-light">
								<tr>
									<th>#</th>
									<th>Personel</th>
									<th>Toplam Verilen</th>
									<th>Tüketilen</th>
									<th>Depoya İade</th>
									<th>Kayıp</th>
									<th>Kalan</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>

					<div class="card border" id="aparatPersonelDetailCard" style="display:none;">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h6 class="mb-0" id="aparatSeciliPersonel">Personel Detayı</h6>
							<small class="text-muted">Gün gün aparat hareketleri</small>
						</div>
						<div class="card-body">
							<div class="row g-2 mb-3">
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Verilen</small><div class="fw-bold" id="ap_verilen">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Tüketilen</small><div class="fw-bold" id="ap_tuketilen">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Depo İade</small><div class="fw-bold" id="ap_depo_iade">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Kayıp</small><div class="fw-bold" id="ap_kayip">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Kalan</small><div class="fw-bold" id="ap_kalan">0</div></div></div>
							</div>

							<div class="table-responsive">
								<table id="aparatPersonelHistoryTable" class="table table-sm table-bordered align-middle mb-0">
									<thead class="table-light">
										<tr>
											<th>Tarih</th>
											<th>Verilen</th>
											<th>Tüketilen</th>
											<th>Depo İade</th>
											<th>Kayıp</th>
											<th>Net</th>
										</tr>
									</thead>
									<tbody></tbody>
								</table>
							</div>
						</div>
					</div>
				</div>

				<div class="tab-pane fade" id="aparatHareketPane" role="tabpanel">
					<div class="table-responsive">
						<table id="aparatHareketTable" class="table table-bordered table-hover nowrap w-100">
							<thead class="table-light">
								<tr>
									<th>Tarih</th>
									<th>Personel</th>
									<th>Aparat</th>
									<th>Hareket</th>
									<th>Miktar</th>
									<th>Açıklama</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
