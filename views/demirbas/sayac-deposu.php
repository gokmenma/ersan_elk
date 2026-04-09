<?php
require_once __DIR__ . '/../../Autoloader.php';

use App\Model\DemirbasModel;
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
?>

<style>
	/* Tablo Satır Seçimi Görselleştirme */
	.table.dataTable tbody tr {
		transition: background-color 0.2s;
		cursor: pointer;
	}
	.table.dataTable tbody tr.selected {
		background-color: rgba(85, 110, 230, 0.1) !important;
		border-left: 3px solid #556ee6 !important;
	}
	.table.dataTable tbody tr:hover {
		background-color: rgba(0, 0, 0, 0.02) !important;
	}
	.custom-checkbox-container {
		pointer-events: none; /* TR click üzerinden yönetilecek */
	}
	.custom-checkbox-input, .custom-checkbox-label {
		pointer-events: auto; /* Checkbox'ın kendisine direkt basılabilir */
	}
</style>

<?php
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
	'zimmetli' => 0,
    'toplam_alinan_yeni' => 0,
    'kayip_yeni' => 0,
    'toplam_hurda' => 0,
    'kayip_hurda' => 0
];

// Global top-level variables for layout compatibility
$stats['toplam_giren'] = 0;

$maintitle = "Sayaçlar";
$title = "Sayaç Deposu";
?>

<div class="container-fluid">
	<?php include 'layouts/breadcrumb.php'; ?>

	<style>
		/* Preloader Styles */
		.personel-preloader {
			position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
			background: rgba(255, 255, 255, 0.7); z-index: 9999;
			backdrop-filter: blur(4px);
			display: flex; align-items: center; justify-content: center;
		}
		[data-bs-theme="dark"] .personel-preloader { background: rgba(25, 30, 34, 0.85); }
		.personel-preloader .loader-content {
			background: white; padding: 2.5rem; border-radius: 16px;
			box-shadow: 0 15px 35px rgba(0, 0, 0, 0.15); text-align: center; min-width: 250px;
		}
		[data-bs-theme="dark"] .personel-preloader .loader-content { background: #2a3042; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4); }

		/* Neo Style Cards */
		.neo-card {
			background: #ffffff;
			border: none;
			border-radius: 16px;
			box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
			transition: all 0.3s ease;
			position: relative;
			overflow: hidden;
		}
		.neo-card:hover {
			transform: translateY(-5px);
			box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
		}
		.neo-card::after {
			content: '';
			position: absolute;
			bottom: 0;
			left: 0;
			right: 0;
			height: 4px;
			background: var(--card-color, #556ee6);
			opacity: 0.8;
		}
		.neo-card .icon-box {
			width: 48px;
			height: 48px;
			border-radius: 12px;
			display: flex;
			align-items: center;
			justify-content: center;
			background: var(--icon-bg, rgba(85, 110, 230, 0.1));
			color: var(--card-color, #556ee6);
			margin-bottom: 1rem;
		}
		.neo-value {
			font-size: 1.5rem;
			font-weight: 800;
			line-height: 1;
			margin-bottom: 0.25rem;
			color: #2a3042;
		}
		.neo-label {
			font-size: 0.7rem;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			font-weight: 600;
			color: #74788d;
			margin-bottom: 0;
		}
		.grid-5-cols {
			display: grid;
			grid-template-columns: repeat(5, 1fr);
			gap: 0;
		}
		.neo-stat-item {
			padding: 1rem 0.5rem;
			border-right: 1px solid #f1f5f9;
		}
		.neo-stat-item:last-child {
			border-right: none;
		}

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
		
		/* View Mode and Action Button specific styles within group */
		.status-filter-group .btn-check:checked + .btn[for*="grouped"] { background: #64748b !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="list"] { background: #556ee6 !important; color: white !important; }
		
		/* Red Action Button (Delete) to match filter style */
		.btn-filter-danger {
			background: transparent; border: none !important; border-radius: 50px !important;
			font-size: 0.75rem; font-weight: 600; padding: 6px 16px; color: #ef4444;
			transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; gap: 6px; line-height: normal;
		}
		.btn-filter-danger:hover { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
		.btn-filter-danger:active { background: #ef4444; color: white; }

		.btn-filter-warning {
			background: transparent; border: none !important; border-radius: 50px !important;
			font-size: 0.75rem; font-weight: 600; padding: 6px 16px; color: #f59e0b;
			transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; gap: 6px; line-height: normal;
		}
		.btn-filter-warning:hover { background: rgba(245, 158, 11, 0.1); color: #d97706; }
		.btn-filter-warning:active { background: #f59e0b; color: white; }

		.btn-filter-info {
			background: transparent; border: none !important; border-radius: 50px !important;
			font-size: 0.75rem; font-weight: 600; padding: 6px 16px; color: #06b6d4;
			transition: all 0.2s ease; display: inline-flex; align-items: center; justify-content: center; gap: 6px; line-height: normal;
		}
		.btn-filter-info:hover { background: rgba(6, 182, 212, 0.1); color: #0891b2; }
		.btn-filter-info:active { background: #06b6d4; color: white; }

		/* Bordro Style Card CSS */
		.bordro-summary-card { position: relative; overflow: hidden; }
		.icon-label-container { display: flex; align-items: start; justify-content: space-between; margin-bottom: 0.75rem; }
		.icon-box { width: 42px; height: 42px; border-radius: 12px; display: flex; align-items: center; justify-content: center; }
		.bordro-text-heading { font-size: 1.5rem; letter-spacing: -0.5px; }
		[data-bs-theme="dark"] .icon-label-container .text-muted { color: #a6b0cf !important; }
		/* Personel Tarih Accordion */
		.personel-tarih-row { transition: background-color 0.2s ease; }
		.personel-tarih-row:hover { background-color: rgba(85, 110, 230, 0.05) !important; }
		.expand-chevron { transition: transform 0.3s ease; }
		.personel-tarih-row.expanded { background-color: rgba(85, 110, 230, 0.05) !important; }
		.personel-detail-row { background-color: #f8fafc; }
		.personel-detail-row table { border-radius: 8px; overflow: hidden; }
		
		/* Masaüstü Tablo Yükseklik Ayarı */
		.table-demirbas thead th { padding: 8px 10px !important; font-size: 0.85rem !important; }
		.table-demirbas tbody td { padding: 6px 10px !important; font-size: 0.85rem !important; vertical-align: middle !important; }
		.table-demirbas .btn-sm { padding: 0.2rem 0.4rem; font-size: 0.75rem; }
		.dt-filter-row th { padding: 4px 6px !important; }
		.dt-filter-control { padding: 4px 8px !important; font-size: 0.75rem !important; height: auto !important; }

		/* Floating Action Buttons */
		.floating-action-bar {
			position: fixed;
			bottom: 24px;
			right: 24px;
			z-index: 1050;
			display: flex;
			flex-direction: column;
			gap: 10px;
			align-items: flex-end;
			pointer-events: none;
			opacity: 0;
			transform: translateY(20px);
			transition: opacity 0.35s ease, transform 0.35s ease;
		}
		.floating-action-bar.visible {
			pointer-events: auto;
			opacity: 1;
			transform: translateY(0);
		}
		.floating-action-bar .fab-btn {
			display: flex;
			align-items: center;
			gap: 8px;
			padding: 12px 22px;
			border: none;
			border-radius: 50px;
			font-weight: 700;
			font-size: 0.85rem;
			cursor: pointer;
			box-shadow: 0 6px 20px rgba(0,0,0,0.18);
			transition: transform 0.2s, box-shadow 0.2s;
			white-space: nowrap;
		}
		.floating-action-bar .fab-btn:hover {
			transform: scale(1.05);
			box-shadow: 0 8px 28px rgba(0,0,0,0.24);
		}
		.floating-action-bar .fab-btn:active {
			transform: scale(0.97);
		}
		.fab-btn-zimmet {
			background: linear-gradient(135deg, #f59e0b, #f97316);
			color: #fff;
		}
		.fab-btn-kaski {
			background: linear-gradient(135deg, #06b6d4, #0ea5e9);
			color: #fff;
		}
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
					<!-- Sayaç İşlemleri Menüsü -->
					<div class="dropdown">
						<button class="btn btn-link btn-sm px-3 fw-bold dropdown-toggle text-dark d-flex align-items-center"
							type="button" data-bs-toggle="dropdown" aria-expanded="false">
							<i class="bx bx-cog me-1 fs-5"></i> Sayaç İşlemleri
							<i class="bx bx-chevron-down ms-1"></i>
						</button>
						<ul class="dropdown-menu dropdown-menu-end shadow-lg border-0">
							<li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0);" id="btnSayacEkleDrop"><i class="bx bx-plus-circle me-2 text-primary fs-5"></i> Sayaç Gir</a></li>
							<li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0);" id="btnPersoneleZimmetleDrop"><i class="bx bx-user-check me-2 text-warning fs-5"></i> Personele Zimmetle</a></li>
							<li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0);" id="btnSayacKaskiyeTeslimDrop"><i class="bx bx-upload me-2 text-info fs-5"></i> Kaskiye İade Et</a></li>
							<li><hr class="dropdown-divider"></li>
							<li><a class="dropdown-item py-2 fw-bold" href="javascript:void(0);" id="btnHurdaSayacIade" style="color: #ef4444;"><i class="bx bx-recycle me-2 fs-5" style="color: #ef4444;"></i> Hurda Sayaç İade Al</a></li>
						</ul>
					</div>

					<div class="vr mx-1" style="height: 25px; align-self: center;"></div>

					<!-- Araçlar (Excel, Sil vb.) -->
					<div class="d-flex align-items-center gap-1">
						<button type="button" id="exportExcel" class="btn btn-link btn-sm text-success p-2" title="Excel'e Aktar">
							<i class="bx bx-spreadsheet fs-4"></i>
						</button>
						<button type="button" id="btnTopluSilSayac" class="btn btn-link btn-sm text-danger p-2" title="Seçilenleri Sil">
							<i class="bx bx-trash fs-4"></i>
						</button>
					</div>

				</div>

				<!-- Destekleyici Gizli Butonlar (JS uyumluluğu için) -->
				<div class="d-none">
					<button type="button" id="btnSayacEkle"></button>
					<button type="button" id="btnPersoneleZimmetle"></button>
					<button type="button" id="btnSayacKaskiyeTeslim"></button>
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
					<!-- ÖZET KARTLARI (KASKİ) -->
					<div class="row g-3 mb-4">
						<!-- TOPLAM ALINAN SAYAÇ KARTI -->
						<div class="col-xl-4 col-md-6">
							<div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-3">
									<div class="icon-label-container mb-3">
										<div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
											<i class="bx bx-plus-circle fs-4" style="color: #556ee6;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.85rem;">TOPLAM ALINAN</span>
									</div>
									<div class="text-center">
										<h3 class="mb-0 fw-bold text-primary" id="kaskiSummaryToplamAlinan">0</h3>
									</div>
								</div>
							</div>
						</div>
						<!-- KASKİ'YE İADE EDİLDİ SAYAÇ KARTI -->
						<div class="col-xl-4 col-md-6">
							<div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #10b981; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-3">
									<div class="icon-label-container mb-3">
										<div class="icon-box" style="background: rgba(16, 185, 129, 0.1);">
											<i class="bx bx-redo fs-4" style="color: #10b981;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.85rem;">KASKİ'YE İADE EDİLDİ</span>
									</div>
									<div class="text-center">
										<h3 class="mb-0 fw-bold text-success" id="kaskiSummaryIadeEdilen">0</h3>
									</div>
								</div>
							</div>
						</div>
						<!-- BAKİYE (FARK) SAYAÇ KARTI -->
						<div class="col-xl-4 col-md-6">
							<div class="card border-0 shadow-sm h-100 bordro-summary-card" style="--card-color: #ef4444; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-3">
									<div class="icon-label-container mb-3">
										<div class="icon-box" style="background: rgba(239, 68, 68, 0.1);">
											<i class="bx bx-calculator fs-4" style="color: #ef4444;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.85rem;">BAKİYE (FARK)</span>
									</div>
									<div class="text-center">
										<h3 class="mb-0 fw-bold text-danger" id="kaskiSummaryFark">0</h3>
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
									<th style="width:5%" data-filter="none"></th>
									<th style="width:25%" data-filter="date">Tarih</th>
									<th style="width:30%" data-filter="string">İşlem</th>
									<th style="width:20%" class="text-center" data-filter="string">Yön</th>
									<th style="width:15%" class="text-center" data-filter="number">Adet</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<!-- 2. BİZİM DEPO PANE -->
				<div class="tab-pane fade" id="depoPane" role="tabpanel">
					<!-- ÖZET KARTLARI (BİZİM DEPO) -->
					<div class="row g-3 mb-4">
						<!-- YENİ SAYAÇ KARTI -->
						<div id="yeniSayacCardCol" class="col-xl-6 col-md-6">
							<div class="card border-0 shadow-sm h-100 bordro-summary-card" id="yeniSayacCard" style="--card-color: #556ee6; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-3">
									<div class="icon-label-container mb-3">
										<div class="icon-box" style="background: rgba(85, 110, 230, 0.1);">
											<i class="bx bx-package fs-4" style="color: #556ee6;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.65rem;">BİZİM DEPO: YENİ SAYAÇ</span>
									</div>
									<div class="grid-5-cols text-center">
										<div class="neo-stat-item">
											<p class="neo-label">Top. Alınan</p>
											<div class="neo-value text-primary" id="sayacCardToplamGiren"><?php echo (int)($stats['toplam_alinan_yeni'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Depoda</p>
											<div class="neo-value text-success" id="sayacCardDepoKalan"><?php echo (int)($stats['yeni_depoda'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Takılan</p>
											<div class="neo-value" style="color: #6366f1;" id="sayacCardTakilan"><?php echo (int)($stats['takilan_sayac'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Personelde</p>
											<div class="neo-value text-warning" id="sayacCardPersonelZimmetli"><?php echo (int)($stats['yeni_personelde'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Kayıp</p>
											<div class="neo-value text-danger" id="sayacCardKayipYeni"><?php echo (int)($stats['kayip_yeni'] ?? 0); ?></div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- HURDA SAYAÇ KARTI -->
						<div id="hurdaSayacCardCol" class="col-xl-6 col-md-6">
							<div class="card border-0 shadow-sm h-100 bordro-summary-card" id="hurdaSayacCard" style="--card-color: #ef4444; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-3">
									<div class="icon-label-container mb-3">
										<div class="icon-box" style="background: rgba(239, 68, 68, 0.1);">
											<i class="bx bx-recycle fs-4" style="color: #ef4444;"></i>
										</div>
										<span class="text-muted fw-bold" style="font-size: 0.65rem;">BİZİM DEPO: HURDA SAYAÇ</span>
									</div>
									<div class="grid-5-cols text-center">
										<div class="neo-stat-item">
											<p class="neo-label">Top. Hurda</p>
											<div class="neo-value text-danger" id="sayacCardToplamHurda"><?php echo (int)($stats['toplam_hurda'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Kaski Tesl.</p>
											<div class="neo-value text-secondary" id="sayacCardKaskiyeTeslim"><?php echo (int)($stats['hurda_kaskiye'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Depoda</p>
											<div class="neo-value text-danger" id="sayacCardHurda"><?php echo (int)($stats['hurda_elimizde'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Personelde</p>
											<div class="neo-value" style="color: #d946ef;" id="sayacCardPersonelHurda"><?php echo (int)($stats['hurda_personelde'] ?? 0); ?></div>
										</div>
										<div class="neo-stat-item">
											<p class="neo-label">Kayıp</p>
											<div class="neo-value text-danger" id="sayacCardKayipHurda"><?php echo (int)($stats['kayip_hurda'] ?? 0); ?></div>
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
							<label class="btn btn-outline-primary fw-medium px-3 text-nowrap" for="filter-all"><i class="bx bx-list-check me-1"></i> Tümü</label>
							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-depo-yeni" value="yeni">
							<label class="btn btn-outline-success fw-medium px-3 text-nowrap" for="filter-depo-yeni"><i class="bx bx-package me-1"></i> Yeni</label>
							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-hurda" value="hurda">
							<label class="btn btn-outline-danger fw-medium px-3 text-nowrap" for="filter-hurda"><i class="bx bx-recycle me-1"></i> Hurda</label>
						</div>

						<div class="d-flex align-items-center">
							<div class="status-filter-group d-flex align-items-center">
								<button type="button" class="btn-filter-danger" id="btnTopluSilSayacTab" disabled>
									<i class="bx bx-trash-alt"></i> Seçilenleri Sil
								</button>
							</div>
						</div>
					</div>

					<div class="table-responsive mb-4">
						<table id="depoSayacTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:3%" data-filter="none">
										<div class="custom-checkbox-container d-inline-block">
											<input type="checkbox" class="custom-checkbox-input" id="selectAllSayac">
											<label class="custom-checkbox-label" for="selectAllSayac"></label>
										</div>
									</th>
									<th style="width:18%" data-filter="string">Sayaç Adı</th>
									<th style="width:12%" data-filter="string">Marka/Model</th>
									<th style="width:12%" data-filter="string">Abone No</th>
									<th style="width:8%" class="text-center" data-filter="select">Stok</th>
									<th style="width:10%" class="text-center" data-filter="select">Durum</th>
									<th style="width:15%" data-filter="string">Açıklama</th>
									<th style="width:10%" data-filter="date">Tarih</th>
									<th style="width:5%" class="text-center" data-filter="none">İşlemler</th>
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
									<th style="width:5%" data-filter="none">#</th>
									<th style="width:20%" data-filter="string">Personel</th>
									<th class="text-center" style="width:12%" data-filter="number">Aldığı Yeni</th>
									<th class="text-center" style="width:12%" data-filter="number">Taktığı</th>
									<th class="text-center" style="width:10%" data-filter="number">Elinde Yeni</th>
									<th class="text-center" style="width:12%" data-filter="number">Aldığı Hurda</th>
									<th class="text-center" style="width:12%" data-filter="number">Teslim Hurda</th>
									<th class="text-center" style="width:10%" data-filter="number">Elinde Hurda</th>
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
							<label class="btn btn-outline-primary fw-medium px-3 text-nowrap" for="hareket-filter-all"><i class="bx bx-list-check me-1"></i> Tüm Sayaç Hareketleri</label>
							<input type="radio" class="btn-check" name="hareket-status-filter" id="hareket-filter-kaski" value="kaski">
							<label class="btn btn-outline-info fw-medium px-3 text-nowrap" for="hareket-filter-kaski"><i class="bx bx-building-house me-1"></i> Kaski Sayaç İşlemleri</label>
							<input type="radio" class="btn-check" name="hareket-status-filter" id="hareket-filter-depo" value="depo">
							<label class="btn btn-outline-success fw-medium px-3 text-nowrap" for="hareket-filter-depo"><i class="bx bx-store-alt me-1"></i> Depo Sayaç İşlemleri</label>
							<input type="radio" class="btn-check" name="hareket-status-filter" id="hareket-filter-zimmet" value="zimmet">
							<label class="btn btn-outline-warning fw-medium px-3 text-nowrap" for="hareket-filter-zimmet"><i class="bx bx-user-check me-1"></i> Personel Sayaç Zimmetleri</label>
						</div>

						<div class="d-flex align-items-center">
							<div class="status-filter-group d-flex align-items-center">
								<button type="button" class="btn-filter-danger d-none" id="btnTopluSilHareket">
									<i class="bx bx-trash-alt"></i> Seçilenleri Sil
								</button>

								<input type="radio" class="btn-check" name="hareket-view-mode" id="hareket-view-grouped" value="grouped" checked>
								<label class="btn" for="hareket-view-grouped" title="Grup Görünümü (Personel + Tarih)"><i class="bx bx-grid-alt me-1"></i> Grup</label>
								
								<input type="radio" class="btn-check" name="hareket-view-mode" id="hareket-view-list" value="list">
								<label class="btn" for="hareket-view-list" title="Liste Görünümü"><i class="bx bx-list-ul me-1"></i> Liste</label>
							</div>
						</div>
					</div>
					<div class="table-responsive">
						<table id="hareketTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:40px" data-filter="none">
										<div class="form-check d-flex justify-content-center m-0">
											<input class="form-check-input" type="checkbox" id="selectAllHareket">
										</div>
									</th>
									<th class="text-center" style="width:5%" data-filter="number">ID</th>
									<th style="width:15%" data-filter="string">Hareket Tipi</th>
									<th style="width:15%" data-filter="string">Sayaç</th>
									<th style="width:15%" data-filter="string">Seri / Abone No</th>
									<th class="text-center" style="width:8%" data-filter="number">Adet</th>
									<th style="width:15%" data-filter="string">Lokasyon / Personel</th>
									<th style="width:15%" data-filter="string">Açıklama</th>
									<th data-filter="date">Tarih</th>
									<th style="width:5%" class="text-center" data-filter="none">İşlem</th>
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

<!-- Floating Action Buttons -->
<div class="floating-action-bar" id="floatingActionBar">
	<button type="button" class="fab-btn fab-btn-zimmet" id="fabPersoneleZimmetle">
		<i class="bx bx-user-check fs-5"></i> Personele Zimmetle
	</button>
	<button type="button" class="fab-btn fab-btn-kaski" id="fabKaskiyeTeslim">
		<i class="bx bx-upload fs-5"></i> Kaskiye İade Et
	</button>
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

    // Floating Action Buttons - scroll ile göster/gizle
    var $fab = $("#floatingActionBar");
    var $headerBtns = $("#btnPersoneleZimmetle");
    var isDepoTab = false;

    function checkFloatingButtons() {
        // Sadece Depo sekmesi aktifken
        isDepoTab = $("#depo-tab").hasClass("active");
        if (!isDepoTab) {
            $fab.removeClass("visible");
            return;
        }
        // Header butonları viewport dışına çıktıysa floating göster
        var btnTop = $headerBtns.offset();
        if (btnTop) {
            var scrolledPast = (btnTop.top + $headerBtns.outerHeight()) < $(window).scrollTop();
            $fab.toggleClass("visible", scrolledPast);
        }
    }

    $(window).on("scroll", checkFloatingButtons);
    $('button[data-bs-toggle="tab"]').on('shown.bs.tab', checkFloatingButtons);

    // Floating butonları orijinal butonlara yönlendir
    $("#fabPersoneleZimmetle").on("click", function() { $("#btnPersoneleZimmetle").trigger("click"); });
    $("#fabKaskiyeTeslim").on("click", function() { $("#btnSayacKaskiyeTeslim").trigger("click"); });
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
						<h6 class="modal-title text-info mb-0 fw-bold">Sayaç İşlem Geçmişi</h6>
						<p class="text-muted small mb-0" id="gecmisDemirbasAdi" style="font-size: 0.7rem;">-</p>
					</div>
				</div>
				<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
			</div>
		</div>
	</div>
</div>

<!-- Hurda Sayaç İade Modal (Eksik Modal Eklendi - Kaldırıldı, çünkü yukarıda include ediliyor) -->

</script>
<!-- sayac-deposu.js vendor-scripts.php'den yükleniyor, burada tekrar yüklemeye gerek yok -->
