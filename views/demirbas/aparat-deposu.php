<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

$maintitle = "Demirbaş";
$title = "";
?>

<div class="container-fluid">
	<?php include 'layouts/breadcrumb.php'; ?>

	<style>
		/* Preloader Styles */
		.personel-preloader {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			min-height: 400px;
			background: rgba(255, 255, 255, 0.82);
			z-index: 1060;
			border-radius: 4px;
			backdrop-filter: blur(3px);
			display: flex;
			align-items: center;
			justify-content: center;
		}

		[data-bs-theme="dark"] .personel-preloader {
			background: rgba(25, 30, 34, 0.85);
		}

		.personel-preloader .loader-content {
			background: white;
			padding: 2.5rem;
			border-radius: 16px;
			box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15);
			text-align: center;
			min-width: 250px;
		}

		[data-bs-theme="dark"] .personel-preloader .loader-content {
			background: #2a3042;
			box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);
		}
	</style>

	<div class="card">
		<div class="card-header bg-white">
			<div class="d-flex justify-content-start">
				<div class="bg-white border rounded shadow-sm p-1">
					<ul class="nav nav-pills" id="aparatDepoTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aparatlarPane" type="button">
								<i class="bx bx-wrench me-1"></i> APARATLAR
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" data-bs-toggle="tab" data-bs-target="#aparatHareketPane" type="button">
								<i class="bx bx-history me-1"></i> Hareketler
							</button>
						</li>
					</ul>
				</div>
			</div>
		</div>
		<div class="card-body">
			<!-- Preloader -->
			<div class="personel-preloader" id="personel-loader">
				<div class="loader-content">
					<div class="spinner-border text-primary m-1" role="status">
						<span class="sr-only">Yükleniyor...</span>
					</div>
					<h5 class="mt-2 mb-0">Aparat Deposu Hazırlanıyor...</h5>
					<p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
				</div>
			</div>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="aparatlarPane" role="tabpanel">
					<!-- Aparat Özet Kartları -->
					<div class="row g-3 mb-4">
						<!-- Depoda -->
						<div class="col-md-3">
							<div class="card border border-light shadow-none h-100 bordro-summary-card"
								style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-2 px-3">
									<div class="icon-label-container">
										<div class="icon-box" style="background: rgba(85, 110, 230, 0.1); width: 32px; height: 32px;">
											<i class="bx bx-package fs-5 text-primary"></i>
										</div>
										<span class="text-muted small fw-bold" style="font-size: 0.55rem; opacity: 0.5;">DEPO</span>
									</div>
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Depoda</p>
									<h4 id="apCardDepo" class="mb-0 fw-bold">0</h4>
								</div>
							</div>
						</div>
						<!-- Personelde -->
						<div class="col-md-3">
							<div class="card border border-light shadow-none h-100 bordro-summary-card"
								style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-2 px-3">
									<div class="icon-label-container">
										<div class="icon-box" style="background: rgba(241, 180, 76, 0.1); width: 32px; height: 32px;">
											<i class="bx bx-user-check fs-5 text-warning"></i>
										</div>
										<span class="text-muted small fw-bold" style="font-size: 0.55rem; opacity: 0.5;">PERSONELDE</span>
									</div>
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Personelde</p>
									<h4 id="apCardPersonel" class="mb-0 fw-bold">0</h4>
								</div>
							</div>
						</div>
						<!-- Tüketilen -->
						<div class="col-md-3">
							<div class="card border border-light shadow-none h-100 bordro-summary-card"
								style="--card-color: #ef4444; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-2 px-3">
									<div class="icon-label-container">
										<div class="icon-box" style="background: rgba(239, 68, 68, 0.1); width: 32px; height: 32px;">
											<i class="bx bx-check-double fs-5" style="color: #ef4444;"></i>
										</div>
										<span class="text-muted small fw-bold" style="font-size: 0.55rem; opacity: 0.5;">TÜKETİLEN</span>
									</div>
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Tüketilen</p>
									<h4 id="apCardTuketilen" class="mb-0 fw-bold">0</h4>
								</div>
							</div>
						</div>
						<!-- Toplam Çeşit -->
						<div class="col-md-3">
							<div class="card border border-light shadow-none h-100 bordro-summary-card"
								style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-2 px-3">
									<div class="icon-label-container">
										<div class="icon-box" style="background: rgba(52, 195, 143, 0.1); width: 32px; height: 32px;">
											<i class="bx bx-grid-alt fs-5 text-success"></i>
										</div>
										<span class="text-muted small fw-bold" style="font-size: 0.55rem; opacity: 0.5;">TOPLAM ÇEŞİT</span>
									</div>
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Toplam Çeşit</p>
									<h4 id="apCardCesit" class="mb-0 fw-bold">0</h4>
								</div>
							</div>
						</div>
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


