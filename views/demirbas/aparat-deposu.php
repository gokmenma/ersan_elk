<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

$maintitle = "Demirbaş";
$title = "Aparat Deposu";
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

		/* Premium Filter Buttons */
		.status-filter-group {
			background: #f8fafc;
			padding: 4px;
			border-radius: 50px;
			border: 1px solid #e2e8f0;
			display: inline-flex;
			align-items: center;
			gap: 2px;
		}

		[data-bs-theme="dark"] .status-filter-group {
			background: #2a3042;
			border-color: #32394e;
		}

		.status-filter-group .btn-check + .btn {
			margin-bottom: 0 !important;
			border: none !important;
			border-radius: 50px !important;
			font-size: 0.75rem;
			font-weight: 600;
			padding: 6px 16px;
			color: #64748b;
			transition: all 0.2s ease;
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 6px;
			line-height: normal;
		}

		[data-bs-theme="dark"] .status-filter-group .btn-check + .btn {
			color: #a6b0cf;
		}

		.status-filter-group .btn-check + .btn i {
			font-size: 0.95rem;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			margin-top: 1px;
		}

		.status-filter-group .btn-check + .btn:hover {
			background: rgba(0, 0, 0, 0.04);
			color: #1e293b;
		}

		[data-bs-theme="dark"] .status-filter-group .btn-check + .btn:hover {
			background: rgba(255, 255, 255, 0.05);
			color: #fff;
		}

		.status-filter-group .btn-check:checked + .btn {
			box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important;
		}

		.status-filter-group .btn-check:checked + .btn[for*="all"] { background: #3b82f6 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="zimmet"] { background: #f59e0b !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="sarf"] { background: #ef4444 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="iade"] { background: #10b981 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="kayip"] { background: #6c757d !important; color: white !important; }
	</style>

	<div class="card">
		<div class="card-header bg-white">
			<div class="d-flex align-items-center">
				<div class="bg-white border rounded shadow-sm p-1">
					<ul class="nav nav-pills" id="aparatDepoTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#aparatListPane" type="button">
								<i class="bx bx-list-check me-1"></i> Envanter
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" data-bs-toggle="tab" data-bs-target="#aparatlarPane" type="button">
								<i class="bx bx-user me-1"></i> Personel Özeti
							</button>
						</li>
						<li class="nav-item" role="presentation">
							<button class="nav-link" data-bs-toggle="tab" data-bs-target="#aparatHareketPane" type="button">
								<i class="bx bx-history me-1"></i> Hareketler
							</button>
						</li>
					</ul>
				</div>

				<div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
					<div class="dropdown">
						<button class="btn btn-link btn-sm px-3 fw-bold dropdown-toggle text-dark d-flex align-items-center"
							type="button" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="bx bx-menu me-1 fs-5"></i> İşlemler
							<i class="bx bx-chevron-down ms-1"></i>
						</button>
						<ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
							<li>
								<a class="dropdown-item py-2" href="javascript:void(0);" id="exportExcel">
									<i class="bx bx-spreadsheet me-2 text-success fs-5"></i> Excel'e Aktar
								</a>
							</li>
						</ul>
					</div>

					<div class="vr mx-1" style="height: 25px; align-self: center;"></div>

					<button type="button" id="btnYeniAparat"
						class="btn btn-success btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1"
						data-bs-toggle="modal" data-bs-target="#demirbasModal">
						<i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Aparat
					</button>
					<button type="button" id="btnAparatPersoneleVer"
						class="btn btn-warning btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1">
						<i class="bx bx-user-plus fs-5 me-1"></i> Personele Ver
					</button>
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
				<!-- 1. Sekme: Envanter Listesi -->
				<div class="tab-pane fade show active" id="aparatListPane" role="tabpanel">
					<div class="d-flex justify-content-center mb-3">
						<div class="status-filter-group">
							<input type="radio" class="btn-check" name="aparat-status-filter" id="ap-all" value="" checked>
							<label class="btn btn-outline-primary active" for="ap-all"><i class="bx bx-list-ul"></i> Tümü</label>
							<input type="radio" class="btn-check" name="aparat-status-filter" id="ap-bosta" value="bosta">
							<label class="btn btn-outline-success" for="ap-bosta"><i class="bx bx-package"></i> Boşta</label>
							<input type="radio" class="btn-check" name="aparat-status-filter" id="ap-zimmetli" value="zimmetli">
							<label class="btn btn-outline-warning" for="ap-zimmetli"><i class="bx bx-user-check"></i> Zimmetli</label>
							<input type="radio" class="btn-check" name="aparat-status-filter" id="ap-hurda" value="hurda">
							<label class="btn btn-outline-danger" for="ap-hurda"><i class="bx bx-trash"></i> Hurda</label>
							<input type="radio" class="btn-check" name="aparat-status-filter" id="ap-kaskiye" value="kaskiye">
							<label class="btn btn-outline-info" for="ap-kaskiye"><i class="bx bx-link-external"></i> Kaskiye</label>
						</div>
					</div>
					<div class="table-responsive mb-4">
						<table id="aparatTable" class="table table-bordered table-hover nowrap w-100 table-demirbas">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:3%"></th>
									<th class="text-center" style="width:5%" data-filter="string">Sıra</th>
									<th style="width:8%" class="text-center" data-filter="string">D.No</th>
									<th style="width:20%" data-filter="string">Aparat Adı</th>
									<th style="width:15%" data-filter="string">Marka/Model</th>
									<th style="width:15%" data-filter="string">Seri No</th>
									<th style="width:10%" class="text-center" data-filter="select">Stok</th>
									<th style="width:10%" class="text-center" data-filter="select">Durum</th>
									<th style="width:10%" data-filter="date">Edinme Tarihi</th>
									<th style="width:7%" class="text-center">İşlemler</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<!-- 2. Sekme: Personel Özeti (Eski Aparatlar Sekmesi) -->
				<div class="tab-pane fade" id="aparatlarPane" role="tabpanel">
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
									<th style="width:5%">#</th>
									<th data-filter="string">Personel</th>
									<th class="text-center" data-filter="number">Toplam Verilen</th>
									<th class="text-center" data-filter="number">Tüketilen</th>
									<th class="text-center" data-filter="number">Depoya İade</th>
									<th class="text-center" data-filter="number">Kayıp</th>
									<th class="text-center" data-filter="number">Kalan</th>
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
					<div class="d-flex justify-content-center mb-3">
						<div class="status-filter-group">
							<input type="radio" class="btn-check" name="aparat-hareket-status" id="ap-hareket-all" value="" checked>
							<label class="btn btn-outline-primary active" for="ap-hareket-all">
								<i class="bx bx-list-ul"></i> Tümü
							</label>

							<input type="radio" class="btn-check" name="aparat-hareket-status" id="ap-hareket-zimmet" value="zimmet">
							<label class="btn btn-outline-warning" for="ap-hareket-zimmet">
								<i class="bx bx-user-plus"></i> Zimmet
							</label>

							<input type="radio" class="btn-check" name="aparat-hareket-status" id="ap-hareket-sarf" value="sarf">
							<label class="btn btn-outline-danger" for="ap-hareket-sarf">
								<i class="bx bx-check-double"></i> Tüketildi
							</label>

							<input type="radio" class="btn-check" name="aparat-hareket-status" id="ap-hareket-iade" value="iade">
							<label class="btn btn-outline-success" for="ap-hareket-iade">
								<i class="bx bx-undo"></i> Depoya İade
							</label>

							<input type="radio" class="btn-check" name="aparat-hareket-status" id="ap-hareket-kayip" value="kayip">
							<label class="btn btn-outline-secondary" for="ap-hareket-kayip">
								<i class="bx bx-help-circle"></i> Kayıp
							</label>
						</div>
					</div>

					<div class="table-responsive">
						<table id="aparatHareketTable" class="table table-bordered table-hover nowrap w-100">
							<thead class="table-light">
								<tr>
									<th data-filter="date">Tarih</th>
									<th data-filter="string">Personel</th>
									<th data-filter="string">Aparat</th>
									<th data-filter="select">Hareket</th>
									<th class="text-center" data-filter="number">Miktar</th>
									<th data-filter="string">Açıklama</th>
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

<!-- Demirbaş Modal -->
<?php include_once "modal/general-modal.php" ?>

<!-- Toplu Aparat Zimmet Modal -->
<?php include_once "modal/toplu-aparat-zimmet-modal.php" ?>


