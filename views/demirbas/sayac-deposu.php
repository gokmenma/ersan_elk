<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

$maintitle = "Demirbaş";
$title = "Sayaç Deposu";
?>

<div class="container-fluid">
	<?php include 'layouts/breadcrumb.php'; ?>

	<div class="card">
		<div class="card-header d-flex align-items-center justify-content-between">
			<h5 class="mb-0">Sayaç Deposu</h5>
			<small class="text-muted">Personel bazlı stok ve hareket görünümü</small>
		</div>
		<div class="card-body">
			<ul class="nav nav-pills mb-3" id="sayacDepoTab" role="tablist">
				<li class="nav-item" role="presentation">
					<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sayaclarPane" type="button">SAYAÇLAR</button>
				</li>
				<li class="nav-item" role="presentation">
					<button class="nav-link" data-bs-toggle="tab" data-bs-target="#sayacHareketPane" type="button">Hareketler</button>
				</li>
			</ul>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="sayaclarPane" role="tabpanel">
					<div class="row g-3 mb-3">
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Depodaki Toplam Yeni</small><h4 id="sayacCardYeniDepo" class="mb-0">0</h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Depodaki Toplam Hurda</small><h4 id="sayacCardHurdaDepo" class="mb-0">0</h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Zimmetli Yeni</small><h4 id="sayacCardYeniPersonel" class="mb-0">0</h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Zimmetli Hurda</small><h4 id="sayacCardHurdaPersonel" class="mb-0">0</h4></div></div></div>
					</div>

					<div class="table-responsive mb-4">
						<table id="sayacPersonelTable" class="table table-bordered table-hover nowrap w-100">
							<thead class="table-light">
								<tr>
									<th>#</th>
									<th>Personel</th>
									<th>Bizden Toplam Aldığı</th>
									<th>Toplam Taktığı</th>
									<th>Elinde Kalan Yeni</th>
									<th>Toplam Hurda</th>
									<th>Teslim Edilen Hurda</th>
									<th>Elinde Kalan Hurda</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>

					<div class="card border" id="sayacPersonelDetailCard" style="display:none;">
						<div class="card-header d-flex justify-content-between align-items-center">
							<h6 class="mb-0" id="sayacSeciliPersonel">Personel Detayı</h6>
							<small class="text-muted">Gün gün aldığı ve verdiği sayaçlar</small>
						</div>
						<div class="card-body">
							<div class="row g-2 mb-3">
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Aldığı</small><div class="fw-bold" id="sp_aldigi">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Taktığı</small><div class="fw-bold" id="sp_taktigi">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Kalan Yeni</small><div class="fw-bold" id="sp_kalan_yeni">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Toplam Hurda</small><div class="fw-bold" id="sp_toplam_hurda">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Teslim Hurda</small><div class="fw-bold" id="sp_teslim_hurda">0</div></div></div>
								<div class="col-md-2"><div class="border rounded p-2"><small class="text-muted">Kalan Hurda</small><div class="fw-bold" id="sp_kalan_hurda">0</div></div></div>
							</div>

							<div class="table-responsive">
								<table id="sayacPersonelHistoryTable" class="table table-sm table-bordered align-middle mb-0">
									<thead class="table-light">
										<tr>
											<th>Tarih</th>
											<th>Alınan</th>
											<th>Takılan</th>
											<th>Hurda Alınan</th>
											<th>Hurda Teslim</th>
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

				<div class="tab-pane fade" id="sayacHareketPane" role="tabpanel">
					<div class="table-responsive">
						<table id="sayacHareketTable" class="table table-bordered table-hover nowrap w-100">
							<thead class="table-light">
								<tr>
									<th>Tarih</th>
									<th>Personel</th>
									<th>Sayaç</th>
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
