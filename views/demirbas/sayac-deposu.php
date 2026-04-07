<?php
require_once __DIR__ . '/../../Autoloader.php';

use App\Model\DemirbasModel;
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;

$Demirbas = new DemirbasModel();
$Personel = new PersonelModel();
$Tanimlamalar = new TanimlamalarModel();
$personeller = $Personel->all(false, 'demirbas');

$sayacKatIds = [];
$tumKategoriler = $Tanimlamalar->getDemirbasKategorileri();
foreach ($tumKategoriler as $kat) {
	$katAdiLower = mb_strtolower($kat->tur_adi, 'UTF-8');
	if (str_contains($katAdiLower, 'sayaç') || str_contains($katAdiLower, 'sayac')) {
		$sayacKatIds[] = (string) $kat->id;
	}
}

$stats = [
	'toplam_giren' => 0,
	'toplam_alinan' => 0,
	'kaski_depoda' => 0,
	'yeni_depoda' => 0,
	'yeni_personelde' => 0,
	'takilan_sayac' => 0,
	'hurda_elimizde' => 0,
	'hurda_personelde' => 0,
	'hurda_kaskiye' => 0,
	'zimmetli' => 0
];

if (!empty($sayacKatIds)) {
	$katPlaceholders = implode(',', array_fill(0, count($sayacKatIds), '?'));
	$paramArr = array_merge($sayacKatIds, [$_SESSION['firma_id']]);

	// 1. Demirbas tablosundan veriler (Lokasyon bazlı)
	$sql1 = $Demirbas->db->prepare("
		SELECT 
			COALESCE(SUM(CASE WHEN lokasyon = 'kaski' AND LOWER(durum) != 'hurda' AND LOWER(durum) != 'kaskiye teslim edildi' THEN kalan_miktar ELSE 0 END), 0) as kaski_depoda,
			COALESCE(SUM(CASE WHEN (lokasyon = 'bizim_depo' OR lokasyon IS NULL) AND LOWER(durum) != 'hurda' AND LOWER(durum) != 'kaskiye teslim edildi' THEN kalan_miktar ELSE 0 END), 0) as bizim_depoda,
			COALESCE(SUM(CASE WHEN LOWER(durum) = 'hurda' THEN kalan_miktar ELSE 0 END), 0) as hurda_elimizde,
			COALESCE(SUM(CASE WHEN LOWER(durum) = 'kaskiye teslim edildi' THEN 1 ELSE 0 END), 0) as hurda_kaskiye
		FROM demirbas
		WHERE kategori_id IN ($katPlaceholders) AND firma_id = ? AND silinme_tarihi IS NULL
	");
	$sql1->execute($paramArr);
	$res1 = $sql1->fetch(PDO::FETCH_OBJ);
	$stats['kaski_depoda'] = (int)$res1->kaski_depoda;
	$stats['yeni_depoda'] = (int)$res1->bizim_depoda;
	$stats['hurda_elimizde'] = (int)$res1->hurda_elimizde;
	$stats['hurda_kaskiye'] = (int)$res1->hurda_kaskiye;

	// 2. Zimmet tablosundan veriler (Personeldeki Yeni ve Hurda)
	$sql2 = $Demirbas->db->prepare("
		SELECT 
			COALESCE(SUM(CASE WHEN LOWER(d.durum) != 'hurda' AND LOWER(d.durum) != 'kaskiye teslim edildi' THEN z.teslim_miktar - COALESCE((SELECT SUM(h.miktar) FROM demirbas_hareketler h WHERE h.zimmet_id = z.id AND h.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h.silinme_tarihi IS NULL), 0) ELSE 0 END), 0) as yeni_personelde,
			COALESCE(SUM(CASE WHEN LOWER(d.durum) = 'hurda' THEN z.teslim_miktar - COALESCE((SELECT SUM(h.miktar) FROM demirbas_hareketler h WHERE h.zimmet_id = z.id AND h.hareket_tipi IN ('iade', 'sarf', 'kayip') AND h.silinme_tarihi IS NULL), 0) ELSE 0 END), 0) as hurda_personelde
		FROM demirbas_zimmet z
		INNER JOIN demirbas d ON z.demirbas_id = d.id
		WHERE z.durum = 'teslim' AND d.kategori_id IN ($katPlaceholders) AND d.firma_id = ? AND z.silinme_tarihi IS NULL
	");
	$sql2->execute($paramArr);
	$res2 = $sql2->fetch(PDO::FETCH_OBJ);
	$stats['yeni_personelde'] = (int)$res2->yeni_personelde;
	$stats['hurda_personelde'] = (int)$res2->hurda_personelde;

	// 3. Montaj (Sarf) istatistiği
	$sql3 = $Demirbas->db->prepare("
		SELECT COALESCE(SUM(h.miktar), 0) as takilan
		FROM demirbas_hareketler h
		INNER JOIN demirbas d ON h.demirbas_id = d.id
		WHERE h.hareket_tipi = 'sarf' AND d.kategori_id IN ($katPlaceholders) AND d.firma_id = ? AND h.silinme_tarihi IS NULL
	");
	$sql3->execute($paramArr);
	$stats['takilan_sayac'] = (int)$sql3->fetchColumn();

	// 4. Toplam Alınan (KASKİ'den depoya giren toplam sayaç)
	$sql4 = $Demirbas->db->prepare("
		SELECT COUNT(*) as toplam
		FROM demirbas
		WHERE kategori_id IN ($katPlaceholders) AND firma_id = ? AND silinme_tarihi IS NULL
	");
	$sql4->execute($paramArr);
	$stats['toplam_alinan'] = (int)$sql4->fetchColumn();

	// 5. Zimmetli sayaç sayısı
	$sql5 = $Demirbas->db->prepare("
		SELECT COALESCE(SUM(CASE WHEN h.hareket_tipi = 'zimmet' THEN h.miktar ELSE 0 END), 0)
			 - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'sarf' THEN h.miktar ELSE 0 END), 0)
			 - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'kayip' THEN h.miktar ELSE 0 END), 0)
			 - COALESCE(SUM(CASE WHEN h.hareket_tipi = 'iade' AND h.aciklama LIKE '[DEPO_IADE]%%' THEN h.miktar ELSE 0 END), 0) as zimmetli
		FROM demirbas_hareketler h
		INNER JOIN demirbas d ON h.demirbas_id = d.id
		WHERE d.kategori_id IN ($katPlaceholders) AND d.firma_id = ? AND h.silinme_tarihi IS NULL AND h.personel_id IS NOT NULL
	");
	$sql5->execute($paramArr);
	$stats['zimmetli'] = max(0, (int)$sql5->fetchColumn());
}

$stats['toplam_giren'] = $stats['toplam_alinan'] ?: ($stats['kaski_depoda'] + $stats['yeni_depoda'] + $stats['yeni_personelde'] + $stats['takilan_sayac']);

$maintitle = "Demirbaş";
$title = "Sayaç Deposu";
?>

<div class="container-fluid">
	<?php include 'layouts/breadcrumb.php'; ?>

	<style>
		/* Preloader Styles */
		.personel-preloader {
			position: absolute; top: 0; left: 0; width: 100%; height: 100%;
			min-height: 400px; background: rgba(255, 255, 255, 0.82); z-index: 1060;
			border-radius: 4px; backdrop-filter: blur(3px);
			display: flex; align-items: center; justify-content: center;
		}
		[data-bs-theme="dark"] .personel-preloader { background: rgba(25, 30, 34, 0.85); }
		.personel-preloader .loader-content {
			background: white; padding: 2.5rem; border-radius: 16px;
			box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15); text-align: center; min-width: 250px;
		}
		[data-bs-theme="dark"] .personel-preloader .loader-content { background: #2a3042; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4); }

		/* Premium Filter Buttons */
		.status-filter-group {
			background: #f8fafc; padding: 4px; border-radius: 50px;
			border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 2px;
		}
		[data-bs-theme="dark"] .status-filter-group { background: #2a3042; border-color: #32394e; }
		.status-filter-group .btn-check + .btn {
			margin-bottom: 0 !important; border: none !important; border-radius: 50px !important;
			font-size: 0.75rem; font-weight: 600; padding: 6px 16px; color: #64748b;
			transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; gap: 6px; line-height: normal;
		}
		[data-bs-theme="dark"] .status-filter-group .btn-check + .btn { color: #a6b0cf; }
		.status-filter-group .btn-check + .btn i { font-size: 0.95rem; display: inline-flex; align-items: center; justify-content: center; margin-top: 1px; }
		.status-filter-group .btn-check + .btn:hover { background: rgba(0, 0, 0, 0.04); color: #1e293b; }
		[data-bs-theme="dark"] .status-filter-group .btn-check + .btn:hover { background: rgba(255, 255, 255, 0.05); color: #fff; }
		.status-filter-group .btn-check:checked + .btn { box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06) !important; }
		.status-filter-group .btn-check:checked + .btn[for*="all"] { background: #3b82f6 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="bosta"] { background: #10b981 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="zimmetli"] { background: #f59e0b !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="hurda"] { background: #ef4444 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="kaskiye"] { background: #06b6d4 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="iade"] { background: #10b981 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="teslim"] { background: #f59e0b !important; color: white !important; }

		/* Bordro Style Card CSS */
		.bordro-summary-card { position: relative; overflow: hidden; }
		.icon-label-container { display: flex; align-items: start; justify-content: space-between; margin-bottom: 0.75rem; }
		.icon-box { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
		.bordro-text-heading { font-size: 1.5rem; letter-spacing: -0.5px; }
		[data-bs-theme="dark"] .icon-label-container .text-muted { color: #a6b0cf !important; }
	</style>

	<div class="card">
		<div class="card-header bg-white">
			<div class="d-flex align-items-center">
				<!-- Sol: Sekmeler -->
				<div class="d-flex align-items-center">
					<div class="bg-white border rounded shadow-sm p-1">
						<ul class="nav nav-pills" id="sayacDepoTab" role="tablist">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" id="kaski-tab" data-bs-toggle="tab" data-bs-target="#kaskiPane" type="button">
									<i class="bx bx-building-house me-1"></i> Kaski
								</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="depo-tab" data-bs-toggle="tab" data-bs-target="#depoPane" type="button">
									<i class="bx bx-store-alt me-1"></i> Bizim Depo
								</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="personel-tab" data-bs-toggle="tab" data-bs-target="#personelPane" type="button">
									<i class="bx bx-user me-1"></i> Personel
								</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" id="hareket-tab" data-bs-toggle="tab" data-bs-target="#hareketPane" type="button">
									<i class="bx bx-history me-1"></i> Hareketler
								</button>
							</li>
						</ul>
					</div>
				</div>

				<!-- Sağ: İşlem Butonları -->
				<div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 gap-1 ms-auto">
					<div class="dropdown">
						<button class="btn btn-link btn-sm px-3 fw-bold dropdown-toggle text-dark d-flex align-items-center"
							type="button" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="bx bx-menu me-1 fs-5"></i> İşlemler
							<i class="bx bx-chevron-down ms-1"></i>
						</button>
						<ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
							<li><a class="dropdown-item py-2" href="javascript:void(0);" id="exportExcel"><i class="bx bx-spreadsheet me-2 text-success fs-5"></i> Excel'e Aktar</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0);" id="btnHurdaSayacIade" style="color: #ef4444;"><i class="bx bx-recycle me-2 fs-5" style="color: #ef4444;"></i> Hurda Sayaç İade Al</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0);" id="btnTopluSilSayac" style="color: #ef4444;"><i class="bx bx-trash me-2 fs-5"></i> Seçilenleri Sil</a></li>
						</ul>
					</div>
					<div class="vr mx-1" style="height: 25px; align-self: center;"></div>
					<button type="button" id="btnSayacEkle" class="btn btn-primary btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1 d-none">
						<i class="bx bx-plus-circle fs-5 me-1"></i> Sayaç Gir
					</button>
					<button type="button" id="btnPersoneleZimmetle" class="btn btn-warning btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1 d-none">
						<i class="bx bx-user-check fs-5 me-1"></i> Personele Zimmetle
					</button>
					<button type="button" id="btnSayacKaskiyeTeslim" class="btn btn-info btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1 d-none">
						<i class="bx bx-upload fs-5 me-1"></i> Kaskiye İade Et
					</button>
				</div>
			</div>
		</div>
		<div class="card-body">
			<!-- Preloader -->
			<div class="personel-preloader" id="personel-loader">
				<div class="loader-content">
					<div class="spinner-border text-primary m-1" role="status"><span class="sr-only">Yükleniyor...</span></div>
					<h5 class="mt-2 mb-0">Sayaç Deposu Hazırlanıyor...</h5>
					<p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
				</div>
			</div>

			<div class="tab-content">
				<!-- 1. KASKI PANE -->
				<div class="tab-pane fade show active" id="kaskiPane" role="tabpanel">
					<!-- Kaski Özet Kartları (Bordro Tarzı) -->
					<div class="row g-2 mb-4">
						<!-- Toplam Alınan Yeni Sayaç -->
						<div class="col-xl-6 col-md-6">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #556ee6; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:36px; height:36px; background: rgba(85, 110, 230, 0.1);">
											<i class="bx bx-down-arrow-alt fs-5" style="color: #556ee6;"></i>
										</div>
										<span class="card-label fw-bold">TOPLAM ALINAN YENİ</span>
									</div>
									<div class="card-value-container">
										<span class="card-value fs-4 fw-bold" id="kaskiCardToplamGiren"><?php echo (int)($stats['toplam_alinan'] ?? 0); ?></span>
									</div>
								</div>
							</div>
						</div>
						<!-- Toplam Teslim Edilen Hurda -->
						<div class="col-xl-6 col-md-6">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #ef4444; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:36px; height:36px; background: rgba(239, 68, 68, 0.1);">
											<i class="bx bx-up-arrow-alt fs-5" style="color: #ef4444;"></i>
										</div>
										<span class="card-label fw-bold">TOPLAM TESLİM EDİLEN HURDA</span>
									</div>
									<div class="card-value-container">
										<span class="card-value fs-4 fw-bold" id="kaskiCardHurdaKaskiye"><?php echo (int)($stats['hurda_kaskiye'] ?? 0); ?></span>
									</div>
								</div>
							</div>
						</div>
					</div>
					<!-- Kaski Tarih Bazlı Döküm -->
					<div class="table-responsive mb-4">
						<table id="kaskiTarihTable" class="table table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th style="width:25%">Tarih</th>
									<th style="width:30%">İşlem</th>
									<th style="width:20%" class="text-center">Yön</th>
									<th style="width:15%" class="text-center">Adet</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<!-- 2. BİZİM DEPO PANE -->
				<div class="tab-pane fade" id="depoPane" role="tabpanel">
			<!-- ÖZET KARTLARI: TEK SATIRDA İKİ KART -->
			<div class="row g-3 mb-4">
				<!-- YENİ SAYAÇ KARTI -->
				<div class="col-xl-6 col-md-6">
					<div class="card border-0 shadow-sm" style="border-left: 4px solid #556ee6 !important; background: #fff; border-radius: 10px;">
						<div class="card-body p-3">
							<div class="d-flex align-items-center mb-3">
								<div class="rounded-2 p-2 me-2" style="background: rgba(85, 110, 230, 0.1);">
									<i class="bx bx-star text-primary fs-5"></i>
								</div>
								<h6 class="mb-0 fw-bold text-dark">Yeni Sayaç</h6>
							</div>
							<div class="row text-center g-0">
								<div class="col-4">
									<div class="border-end">
										<p class="text-muted mb-1 small">Toplam</p>
										<h4 class="fw-bold mb-0" style="color:#556ee6;" id="sayacCardToplamGiren"><?php echo (int)($stats['toplam_giren'] ?? 0); ?></h4>
									</div>
								</div>
								<div class="col-4">
									<div class="border-end">
										<p class="text-muted mb-1 small">Depoda</p>
										<h4 class="fw-bold mb-0" style="color:#10b981;" id="sayacCardDepoKalan"><?php echo (int)($stats['depoda_yeni'] ?? 0); ?></h4>
									</div>
								</div>
								<div class="col-4">
									<div>
										<p class="text-muted mb-1 small">Personelde</p>
										<h4 class="fw-bold mb-0" style="color:#f59e0b;" id="sayacCardPersonelZimmetli"><?php echo (int)($stats['personelde_yeni'] ?? 0); ?></h4>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
				<!-- HURDA SAYAÇ KARTI -->
				<div class="col-xl-6 col-md-6">
					<div class="card border-0 shadow-sm" style="border-left: 4px solid #ef4444 !important; background: #fff; border-radius: 10px;">
						<div class="card-body p-3">
							<div class="d-flex align-items-center mb-3">
								<div class="rounded-2 p-2 me-2" style="background: rgba(239, 68, 68, 0.1);">
									<i class="bx bx-recycle text-danger fs-5"></i>
								</div>
								<h6 class="mb-0 fw-bold text-dark">Hurda Sayaç</h6>
							</div>
							<div class="row text-center g-0">
								<div class="col-4">
									<div class="border-end">
										<p class="text-muted mb-1 small">Teslim Edilen</p>
										<h4 class="fw-bold mb-0" style="color:#64748b;" id="sayacCardKaskiyeTeslim"><?php echo (int)($stats['teslim_edilen_hurda'] ?? 0); ?></h4>
									</div>
								</div>
								<div class="col-4">
									<div class="border-end">
										<p class="text-muted mb-1 small">Depoda</p>
										<h4 class="fw-bold mb-0" style="color:#ef4444;" id="sayacCardHurda"><?php echo (int)($stats['depoda_hurda'] ?? 0); ?></h4>
									</div>
								</div>
								<div class="col-4">
									<div>
										<p class="text-muted mb-1 small">Personelde</p>
										<h4 class="fw-bold mb-0" style="color:#d946ef;" id="sayacCardPersonelHurda"><?php echo (int)($stats['personelde_hurda'] ?? 0); ?></h4>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>

					<!-- Bizim Depo Filtre Butonları -->
					<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
						<div class="status-filter-group d-flex align-items-center" role="group">
							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-all" value="" checked>
							<label class="btn btn-outline-primary fw-medium px-3 active" for="filter-all"><i class="bx bx-list-check me-1"></i> Tümü</label>
							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-depo-yeni" value="yeni">
							<label class="btn btn-outline-success fw-medium px-3" for="filter-depo-yeni"><i class="bx bx-package me-1"></i> Yeni</label>
							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-hurda" value="hurda">
							<label class="btn btn-outline-danger fw-medium px-3" for="filter-hurda"><i class="bx bx-recycle me-1"></i> Hurda</label>
						</div>
					</div>

					<div class="table-responsive mb-4">
						<table id="depoSayacTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:3%">
										<div class="custom-checkbox-container d-inline-block">
											<input type="checkbox" class="custom-checkbox-input" id="selectAllSayac">
											<label class="custom-checkbox-label" for="selectAllSayac"></label>
										</div>
									</th>
									<th style="width:18%" data-filter="string">Sayaç Adı</th>
									<th style="width:12%" data-filter="string">Marka/Model</th>
									<th style="width:12%" data-filter="string">Seri No</th>
									<th style="width:8%" class="text-center" data-filter="select">Stok</th>
									<th style="width:10%" class="text-center" data-filter="select">Durum</th>
									<th style="width:10%" data-filter="date">Tarih</th>
									<th style="width:5%" class="text-center">İşlemler</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<!-- 3. PERSONEL PANE -->
				<div class="tab-pane fade" id="personelPane" role="tabpanel">
					<!-- Personel Özet KPI Kartları -->
					<!-- Personel Özet Özet Kartları (Bordro Tarzı) -->
					<div class="row g-2 mb-4" id="personelKpiCards">
						<div class="col-xl col-md-4">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #556ee6; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:30px; height:30px; background: rgba(85, 110, 230, 0.1);">
											<i class="bx bx-down-arrow-alt fs-6" style="color: #556ee6;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.55rem;">TOPLAM VERİLEN</span>
									</div>
									<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="persKpiVerilen" style="font-size: 1.1rem;">-</h5>
								</div>
							</div>
						</div>
						<div class="col-xl col-md-4">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #2a9d8f; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:30px; height:30px; background: rgba(42, 157, 143, 0.1);">
											<i class="bx bx-check-circle fs-6" style="color: #2a9d8f;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.55rem;">TOPLAM TAKILAN</span>
									</div>
									<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="persKpiTakilan" style="font-size: 1.1rem;">-</h5>
								</div>
							</div>
						</div>
						<div class="col-xl col-md-4">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #f59e0b; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:30px; height:30px; background: rgba(245, 158, 11, 0.1);">
											<i class="bx bx-user-voice fs-6 text-warning"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.55rem;">ELDE KALAN YENİ</span>
									</div>
									<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="persKpiEldeYeni" style="font-size: 1.1rem;">-</h5>
								</div>
							</div>
						</div>
						<div class="col-xl col-md-4">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #64748b; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:30px; height:30px; background: rgba(100, 116, 139, 0.1);">
											<i class="bx bx-log-out-circle fs-6 text-secondary"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.55rem;">TOPLAM HURDA</span>
									</div>
									<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="persKpiHurda" style="font-size: 1.1rem;">-</h5>
								</div>
							</div>
						</div>
						<div class="col-xl col-md-4">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #ef4444; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:30px; height:30px; background: rgba(239, 68, 68, 0.1);">
											<i class="bx bx-recycle fs-6 text-danger"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.55rem;">TESLİM HURDA</span>
									</div>
									<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="persKpiTeslimHurda" style="font-size: 1.1rem;">-</h5>
								</div>
							</div>
						</div>
						<div class="col-xl col-md-4">
							<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #ec4899; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
								<div class="card-body p-2">
									<div class="icon-label-container mb-1">
										<div class="icon-box" style="width:30px; height:30px; background: rgba(236, 72, 153, 0.1);">
											<i class="bx bx-error fs-6" style="color:#ec4899;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.55rem;">ELDE KALAN HURDA</span>
									</div>
									<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="persKpiEldeHurda" style="font-size: 1.1rem;">-</h5>
								</div>
							</div>
						</div>
					</div>

					<!-- Personel Listesi Tablosu -->
					<div class="table-responsive mb-4 shadow-sm rounded border">
						<table id="sayacPersonelTable" class="table table-bordered table-hover nowrap w-100 mb-0">
							<thead class="table-light text-uppercase small fw-bold">
								<tr>
									<th style="width:5%">#</th>
									<th style="width:20%">Personel</th>
									<th class="text-center" style="width:12%">Aldığı Yeni</th>
									<th class="text-center" style="width:12%">Taktığı</th>
									<th class="text-center" style="width:10%">Elinde Yeni</th>
									<th class="text-center" style="width:12%">Aldığı Hurda</th>
									<th class="text-center" style="width:12%">Teslim Hurda</th>
									<th class="text-center" style="width:10%">Elinde Hurda</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<!-- 4. HAREKETLER PANE -->
				<div class="tab-pane fade" id="hareketPane" role="tabpanel">
					<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
						<div class="status-filter-group d-flex align-items-center" role="group">
							<input type="radio" class="btn-check" name="hareket-status-filter" id="hareket-filter-all" value="" checked>
							<label class="btn btn-outline-primary fw-medium px-3 active" for="hareket-filter-all"><i class="bx bx-list-check me-1"></i> Tüm Hareketler</label>
							<input type="radio" class="btn-check" name="hareket-status-filter" id="hareket-filter-kaski" value="kaski">
							<label class="btn btn-outline-info fw-medium px-3" for="hareket-filter-kaski"><i class="bx bx-building-house me-1"></i> Kaski İşlemleri</label>
							<input type="radio" class="btn-check" name="hareket-status-filter" id="hareket-filter-depo" value="depo">
							<label class="btn btn-outline-success fw-medium px-3" for="hareket-filter-depo"><i class="bx bx-store-alt me-1"></i> Depo İşlemleri</label>
							<input type="radio" class="btn-check" name="hareket-status-filter" id="hareket-filter-zimmet" value="zimmet">
							<label class="btn btn-outline-warning fw-medium px-3" for="hareket-filter-zimmet"><i class="bx bx-user-check me-1"></i> Personel Zimmetleri</label>
						</div>
					</div>
					<div class="table-responsive">
						<table id="hareketTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:5%">ID</th>
									<th style="width:15%">Hareket Tipi</th>
									<th style="width:20%">Sayaç</th>
									<th style="width:15%">Seri No</th>
									<th style="width:15%">Lokasyon / Personel</th>
									<th data-filter="date">Tarih</th>
									<th style="width:5%" class="text-center">İşlem</th>
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

<script>
    var sayacKatIds = <?php echo json_encode($sayacKatIds); ?>;
    
    // Tab değiştiğinde buton görünürlüğünü güncelle
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
        var activeTabId = $(e.target).attr('id');
        $("#btnSayacEkle, #btnPersoneleZimmetle, #btnSayacKaskiyeTeslim").addClass("d-none");
        if (activeTabId === "depo-tab") {
            $("#btnSayacEkle, #btnPersoneleZimmetle, #btnSayacKaskiyeTeslim").removeClass("d-none");
        }
    });
</script>

<!-- Sayaç Gir Modal -->
<?php include_once __DIR__ . "/modal/sayac-gir-modal.php" ?>

<!-- Sayaç Zimmet Modal -->
<?php include_once __DIR__ . "/modal/sayac-zimmet-modal.php" ?>

<!-- Kaskiye Teslim Modal -->
<?php include_once __DIR__ . "/modal/kasiye-teslim-modal.php" ?>

<!-- İade Modal -->
<?php include_once __DIR__ . "/modal/iade-modal.php" ?>

<!-- Hurda Sayaç İade Modal -->
<?php include_once __DIR__ . "/modal/hurda-iade-modal.php" ?>

<!-- Personel Detay Modalı -->
<div class="modal fade" id="personelDetayModal" tabindex="-1" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
			<div class="modal-header bg-soft-primary border-bottom">
				<div class="modal-title-section d-flex align-items-center">
					<div class="avatar-xs me-2 rounded bg-primary bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
						<i class="bx bx-user text-primary fs-5"></i>
					</div>
					<div>
						<h6 class="modal-title text-primary mb-0 fw-bold" id="personelDetayBaslik">Personel Detayı</h6>
						<p class="text-muted small mb-0" id="personelDetayAlt" style="font-size: 0.7rem;">Sayaç bakiye ve hareket detayları</p>
					</div>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-4">
				<!-- Kişi KPI Kartları -->
				<!-- Kişi Özet Kartları (Bordro Tarzı) -->
				<div class="row g-2 mb-4" id="personelDetayKpi">
					<div class="col-xl col-md-4">
						<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #556ee6; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
							<div class="card-body p-2">
								<div class="icon-label-container mb-1">
									<div class="icon-box" style="width:30px; height:30px; background: rgba(85, 110, 230, 0.1);">
										<i class="bx bx-down-arrow-alt fs-6" style="color: #556ee6;"></i>
									</div>
									<span class="text-muted fw-bold" style="font-size: 0.55rem;">ALDIĞI YENİ</span>
								</div>
								<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="pdKpiAldigi" style="font-size: 1.1rem;">-</h5>
							</div>
						</div>
					</div>
					<div class="col-xl col-md-4">
						<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #2a9d8f; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
							<div class="card-body p-2">
								<div class="icon-label-container mb-1">
									<div class="icon-box" style="width:30px; height:30px; background: rgba(42, 157, 143, 0.1);">
										<i class="bx bx-check-circle fs-6" style="color: #2a9d8f;"></i>
									</div>
									<span class="text-muted fw-bold" style="font-size: 0.55rem;">TAKTIĞI</span>
								</div>
								<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="pdKpiTaktigi" style="font-size: 1.1rem;">-</h5>
							</div>
						</div>
					</div>
					<div class="col-xl col-md-4">
						<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #f59e0b; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
							<div class="card-body p-2">
								<div class="icon-label-container mb-1">
									<div class="icon-box" style="width:30px; height:30px; background: rgba(245, 158, 11, 0.1);">
										<i class="bx bx-package fs-6 text-warning"></i>
									</div>
									<span class="text-muted fw-bold" style="font-size: 0.55rem;">ELİNDE YENİ</span>
								</div>
								<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="pdKpiEldeYeni" style="font-size: 1.1rem;">-</h5>
							</div>
						</div>
					</div>
					<div class="col-xl col-md-4">
						<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #ef4444; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
							<div class="card-body p-2">
								<div class="icon-label-container mb-1">
									<div class="icon-box" style="width:30px; height:30px; background: rgba(239, 68, 68, 0.1);">
										<i class="bx bx-trash fs-6 text-danger"></i>
									</div>
									<span class="text-muted fw-bold" style="font-size: 0.55rem;">ALDIĞI HURDA</span>
								</div>
								<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="pdKpiHurda" style="font-size: 1.1rem;">-</h5>
							</div>
						</div>
					</div>
					<div class="col-xl col-md-4">
						<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #64748b; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
							<div class="card-body p-2">
								<div class="icon-label-container mb-1">
									<div class="icon-box" style="width:30px; height:30px; background: rgba(100, 116, 139, 0.1);">
										<i class="bx bx-log-out-circle fs-6 text-secondary"></i>
									</div>
									<span class="text-muted fw-bold" style="font-size: 0.55rem;">TESLİM HURDA</span>
								</div>
								<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="pdKpiTeslimHurda" style="font-size: 1.1rem;">-</h5>
							</div>
						</div>
					</div>
					<div class="col-xl col-md-4">
						<div class="card border-0 shadow-sm bordro-summary-card" style="--card-color: #ec4899; border-bottom: 2px solid var(--card-color) !important; background: #fff;">
							<div class="card-body p-2">
								<div class="icon-label-container mb-1">
									<div class="icon-box" style="width:30px; height:30px; background: rgba(236, 72, 153, 0.1);">
										<i class="bx bx-error fs-6" style="color:#ec4899;"></i>
									</div>
									<span class="text-muted fw-bold" style="font-size: 0.55rem;">ELİNDE HURDA</span>
								</div>
								<h5 class="mb-0 fw-bold bordro-text-heading text-center" id="pdKpiEldeHurda" style="font-size: 1.1rem;">-</h5>
							</div>
						</div>
					</div>
				</div>
				<!-- Tarih Bazlı Döküm -->
				<div class="table-responsive">
					<table class="table table-bordered table-hover table-sm mb-0" id="personelDetayTarihTable">
						<thead class="table-light">
							<tr>
								<th style="width:5%"></th>
								<th>Tarih</th>
								<th class="text-center">Aldığı</th>
								<th class="text-center">Taktığı</th>
								<th class="text-center">Hurda Aldığı</th>
								<th class="text-center">Hurda Teslim</th>
								<th class="text-center">Kayıp</th>
							</tr>
						</thead>
						<tbody id="personelDetayTarihBody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer border-top py-2">
				<button type="button" class="btn btn-secondary btn-sm fw-bold px-4" data-bs-dismiss="modal">Kapat</button>
			</div>
		</div>
	</div>
</div>

<!-- Demirbaş İşlem Geçmişi Modal -->
<div class="modal" id="demirbasGecmisModal" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
			<div class="modal-header bg-soft-info border-bottom">
				<div class="modal-title-section d-flex align-items-center">
					<div class="avatar-xs me-2 rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
						<i class="bx bx-history text-info fs-5"></i>
					</div>
					<div>
						<h6 class="modal-title text-info mb-0 fw-bold">Demirbaş İşlem Geçmişi</h6>
						<p class="text-muted small mb-0" id="gecmisDemirbasAdi" style="font-size: 0.7rem;">-</p>
					</div>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
			<div class="modal-body p-0">
				<div class="table-responsive">
					<table id="demirbasGecmisTable" class="table table-hover table-striped dt-responsive nowrap w-100 mb-0">
						<thead class="table-light">
							<tr>
								<th>İşlem Tipi</th>
								<th class="text-center">Miktar</th>
								<th>Tarih</th>
								<th>İlgili Personel</th>
								<th>Açıklama</th>
								<th class="text-end">İşlem Yapan</th>
							</tr>
						</thead>
						<tbody id="demirbasGecmisBody"></tbody>
					</table>
				</div>
			</div>
			<div class="modal-footer border-top py-2">
				<button type="button" class="btn btn-secondary btn-sm fw-bold px-4" data-bs-dismiss="modal">Kapat</button>
			</div>
		</div>
	</div>
</div>
<script src="views/demirbas/js/sayac-deposu.js?v=<?php echo time(); ?>"></script>
