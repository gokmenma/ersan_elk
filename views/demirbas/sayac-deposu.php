<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

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

$depoOzet = (object) ['yeni_depoda' => 0, 'hurda_depoda' => 0, 'yeni_personelde' => 0, 'hurda_personelde' => 0];
if (!empty($sayacKatIds)) {
	$katPlaceholders = implode(',', array_fill(0, count($sayacKatIds), '?'));

	$sqlDepo = $Demirbas->db->prepare(" 
		SELECT
			COALESCE(SUM(CASE WHEN LOWER(durum) != 'hurda' AND LOWER(durum) != 'kaskiye teslim edildi' THEN kalan_miktar ELSE 0 END), 0) as yeni_depoda,
			COALESCE(SUM(CASE WHEN LOWER(durum) = 'hurda' THEN kalan_miktar ELSE 0 END), 0) as hurda_depoda
		FROM demirbas
		WHERE kategori_id IN ($katPlaceholders) AND firma_id = ? AND silinme_tarihi IS NULL
	");
	$paramArr = $sayacKatIds;
	$paramArr[] = $_SESSION['firma_id'];
	$sqlDepo->execute($paramArr);
	$depoResult = $sqlDepo->fetch(PDO::FETCH_OBJ);
	$depoOzet->yeni_depoda = (int) ($depoResult->yeni_depoda ?? 0);
	$depoOzet->hurda_depoda = (int) ($depoResult->hurda_depoda ?? 0);

	$sqlPersonelde = $Demirbas->db->prepare(" 
		SELECT
			COALESCE(SUM(CASE WHEN LOWER(d.durum) != 'hurda' AND LOWER(d.durum) != 'kaskiye teslim edildi' THEN z.teslim_miktar ELSE 0 END), 0) as yeni_personelde,
			COALESCE(SUM(CASE WHEN LOWER(d.durum) = 'hurda' THEN z.teslim_miktar ELSE 0 END), 0) as hurda_personelde
		FROM demirbas_zimmet z
		INNER JOIN demirbas d ON z.demirbas_id = d.id
		WHERE z.durum = 'teslim' AND d.kategori_id IN ($katPlaceholders) AND d.firma_id = ? AND z.silinme_tarihi IS NULL
	");
	$sqlPersonelde->execute($paramArr);
	$personeldeResult = $sqlPersonelde->fetch(PDO::FETCH_OBJ);
	$depoOzet->yeni_personelde = (int) ($personeldeResult->yeni_personelde ?? 0);
	$depoOzet->hurda_personelde = (int) ($personeldeResult->hurda_personelde ?? 0);
}

$maintitle = "Demirbaş";
$title = "Sayaç Deposu";
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
		.status-filter-group .btn-check:checked + .btn[for*="bosta"] { background: #10b981 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="zimmetli"] { background: #f59e0b !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="hurda"] { background: #ef4444 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="kaskiye"] { background: #06b6d4 !important; color: white !important; }
		.status-filter-group .btn-check:checked + .btn[for*="iade"] { background: #10b981 !important; color: white !important; }

		/* Expand Icon Animation */
		.transition-all {
			transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
		}

		.rotate-90 {
			transform: rotate(90deg);
			color: #1a73e8 !important;
		}

		#sayacPersonelTable tr.personel-day-row:hover {
			background-color: rgba(26, 115, 232, 0.04) !important;
			transition: background-color 0.1s ease;
		}

		#sayacPersonelTable tr.shown {
			background-color: rgba(26, 115, 232, 0.02) !important;
		}

		.expand-icon-btn {
			display: inline-block;
			pointer-events: none;
		}
		.status-filter-group .btn-check:checked + .btn[for*="teslim"] { background: #f59e0b !important; color: white !important; }
	</style>

	<div class="card">
		<div class="card-header bg-white">
			<div class="d-flex align-items-center">
				<!-- Sol: Sekmeler -->
				<div class="d-flex align-items-center">
					<div class="bg-white border rounded shadow-sm p-1">
						<ul class="nav nav-pills" id="sayacDepoTab" role="tablist">
							<li class="nav-item" role="presentation">
								<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sayaclarPane" type="button">
									<i class="bx bx-tachometer me-1"></i> Sayaçlar
								</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" data-bs-toggle="tab" data-bs-target="#sayacPersonelPane" type="button">
									<i class="bx bx-user me-1"></i> Personel Özeti
								</button>
							</li>
							<li class="nav-item" role="presentation">
								<button class="nav-link" data-bs-toggle="tab" data-bs-target="#sayacHareketPane" type="button">
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
							<li>
								<a class="dropdown-item py-2" href="javascript:void(0);" id="exportExcel">
									<i class="bx bx-spreadsheet me-2 text-success fs-5"></i> Excel'e Aktar
								</a>
							</li>
							<li><hr class="dropdown-divider"></li>
							<li>
								<a class="dropdown-item py-2 fw-bold" href="javascript:void(0);" id="btnHurdaSayacIade" style="color: #ef4444;">
									<i class="bx bx-recycle me-2 fs-5" style="color: #ef4444;"></i> Hurda Sayaç İade Al
								</a>
							</li>
						</ul>
					</div>

					<div class="vr mx-1" style="height: 25px; align-self: center;"></div>

					<button type="button" id="btnYeniSayac"
						class="btn btn-success btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1"
						data-bs-toggle="modal" data-bs-target="#demirbasModal">
						<i class="bx bx-plus-circle fs-5 me-1"></i> Yeni Sayaç
					</button>
					<button type="button" id="btnTopluKaskiyeTeslim"
						class="btn btn-info btn-sm px-3 py-2 fw-bold d-flex align-items-center shadow-sm ms-1">
						<i class="bx bx-buildings fs-5 me-1"></i> Toplu Kaskiye Teslim Et
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
					<h5 class="mt-2 mb-0">Sayaç Deposu Hazırlanıyor...</h5>
					<p class="text-muted small mb-0">Lütfen bekleyiniz...</p>
				</div>
			</div>

			<div class="tab-content">
				<div class="tab-pane fade show active" id="sayaclarPane" role="tabpanel">
					<!-- Sayaç Özet Kartları -->
					<div class="row g-3 mb-4">
						<!-- Depodaki Toplam Yeni -->
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
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Depodaki Toplam Yeni</p>
									<h4 id="sayacCardYeniDepo" class="mb-0 fw-bold"><?php echo $depoOzet->yeni_depoda; ?></h4>
								</div>
							</div>
						</div>
						<!-- Depodaki Toplam Hurda -->
						<div class="col-md-3">
							<div class="card border border-light shadow-none h-100 bordro-summary-card"
								style="--card-color: #f1b44c; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-2 px-3">
									<div class="icon-label-container">
										<div class="icon-box" style="background: rgba(241, 180, 76, 0.1); width: 32px; height: 32px;">
											<i class="bx bx-recycle fs-5 text-warning"></i>
										</div>
										<span class="text-muted small fw-bold" style="font-size: 0.55rem; opacity: 0.5;">HURDA DEPO</span>
									</div>
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Depodaki Toplam Hurda</p>
									<h4 id="sayacCardHurdaDepo" class="mb-0 fw-bold"><?php echo $depoOzet->hurda_depoda; ?></h4>
								</div>
							</div>
						</div>
						<!-- Zimmetli Yeni -->
						<div class="col-md-3">
							<div class="card border border-light shadow-none h-100 bordro-summary-card"
								style="--card-color: #34c38f; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-2 px-3">
									<div class="icon-label-container">
										<div class="icon-box" style="background: rgba(52, 195, 143, 0.1); width: 32px; height: 32px;">
											<i class="bx bx-user-check fs-5 text-success"></i>
										</div>
										<span class="text-muted small fw-bold" style="font-size: 0.55rem; opacity: 0.5;">ZİMMET</span>
									</div>
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Zimmetli Yeni</p>
									<h4 id="sayacCardYeniPersonel" class="mb-0 fw-bold"><?php echo $depoOzet->yeni_personelde; ?></h4>
								</div>
							</div>
						</div>
						<!-- Zimmetli Hurda -->
						<div class="col-md-3">
							<div class="card border border-light shadow-none h-100 bordro-summary-card"
								style="--card-color: #50a5f1; border-bottom: 3px solid var(--card-color) !important;">
								<div class="card-body p-2 px-3">
									<div class="icon-label-container">
										<div class="icon-box" style="background: rgba(80, 165, 241, 0.1); width: 32px; height: 32px;">
											<i class="bx bx-user-minus fs-5 text-info"></i>
										</div>
										<span class="text-muted small fw-bold" style="font-size: 0.55rem; opacity: 0.5;">HURDA ZİMMET</span>
									</div>
									<p class="text-muted mb-0 small fw-bold text-uppercase" style="letter-spacing: 0.5px; font-size: 0.65rem;">Zimmetli Hurda</p>
									<h4 id="sayacCardHurdaPersonel" class="mb-0 fw-bold"><?php echo $depoOzet->hurda_personelde; ?></h4>
								</div>
							</div>
						</div>
					</div>

					<!-- Sayaç Filtre Butonları -->
					<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
						<div class="status-filter-group d-flex align-items-center" role="group">
							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-all" value="" checked>
							<label class="btn btn-outline-primary fw-medium px-3 active" for="filter-all">
								<i class="bx bx-list-check me-1"></i> Tümü
							</label>

							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-bosta" value="bosta">
							<label class="btn btn-outline-success fw-medium px-3" for="filter-bosta">
								<i class="bx bx-package me-1"></i> Boşta
							</label>

							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-zimmetli" value="zimmetli">
							<label class="btn btn-outline-warning fw-medium px-3" for="filter-zimmetli">
								<i class="bx bx-user-check me-1"></i> Zimmetli
							</label>

							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-hurda" value="hurda">
							<label class="btn btn-outline-danger fw-medium px-3" for="filter-hurda">
								<i class="bx bx-recycle me-1"></i> Hurda
							</label>
							
							<input type="radio" class="btn-check" name="sayac-status-filter" id="filter-kaskiye" value="kaskiye">
							<label class="btn btn-outline-info fw-medium px-3" for="filter-kaskiye">
								<i class="bx bx-buildings me-1"></i> Kaskiye Teslim
							</label>
						</div>
					</div>

					<div class="table-responsive mb-4">
						<table id="sayacTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:3%"></th>
									<th class="text-center" style="width:5%" data-filter="string">Sıra</th>
									<th style="width:8%" class="text-center" data-filter="string">D.No</th>
									<th style="width:20%" data-filter="string">Sayaç Adı</th>
									<th style="width:15%" data-filter="string">Marka/Model</th>
									<th style="width:15%" data-filter="string">Seri No</th>
									<th style="width:10%" class="text-center" data-filter="select">Stok</th>
									<th style="width:10%" class="text-center" data-filter="select">Durum</th>
									<th style="width:10%" data-filter="date">Edinme Tarihi</th>
									<th style="width:5%" class="text-center">İşlemler</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<!-- Personel Özeti Sekmesi -->
				<div class="tab-pane fade" id="sayacPersonelPane" role="tabpanel">
					<div class="table-responsive mb-4 shadow-sm rounded border">
						<table id="sayacPersonelTable" class="table table-bordered table-hover nowrap w-100 mb-0">
							<thead class="table-light text-uppercase small fw-bold">
								<tr>
									<th style="width: 30px;"></th>
									<th style="width: 50px;">#</th>
									<th data-filter="date">Tarih</th>
									<th data-filter="string">Personel</th>
									<th class="text-center" data-filter="number">Alınan</th>
									<th class="text-center" data-filter="number">Taktığı</th>
									<th class="text-center" data-filter="number">İade Edilen</th>
									<th class="text-center" data-filter="number">Kayıp</th>
									<th class="text-center" data-filter="number">Günü Kalan</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>

				</div>

				<div class="tab-pane fade" id="sayacHareketPane" role="tabpanel">
					<!-- Hareket Filtre Butonları -->
					<div class="d-flex align-items-center justify-content-between mb-3 mt-2">
						<div class="status-filter-group d-flex align-items-center" role="group">
							<input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-all" value="" checked>
							<label class="btn btn-outline-primary fw-medium px-3 active" for="zimmet-filter-all">
								<i class="bx bx-list-check me-1"></i> Tümü
							</label>

							<input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-teslim" value="teslim">
							<label class="btn btn-outline-warning fw-medium px-3" for="zimmet-filter-teslim">
								<i class="bx bx-user-check me-1"></i> Zimmetli
							</label>

							<input type="radio" class="btn-check" name="zimmet-status-filter" id="zimmet-filter-iade" value="iade">
							<label class="btn btn-outline-success fw-medium px-3" for="zimmet-filter-iade">
								<i class="bx bx-undo me-1"></i> İade Alındı
							</label>
						</div>
					</div>

					<div class="table-responsive">
						<table id="sayacZimmetTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:3%"></th>
									<th class="text-center" style="width:5%" data-filter="string">ID</th>
									<th style="width:12%" data-filter="select">Kategori</th>
									<th style="width:20%" data-filter="string">Sayaç</th>
									<th style="width:15%" data-filter="string">Marka/Model</th>
									<th style="width:18%" data-filter="string">Personel</th>
									<th style="width:8%" class="text-center" data-filter="string">Miktar</th>
									<th data-filter="date">Tarih</th>
									<th style="width:10%" class="text-center" data-filter="select">Durum</th>
									<th style="width:5%" class="text-center">İşlemler</th>
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
</script>

<!-- Demirbaş Modal -->
<?php include_once "modal/general-modal.php" ?>

<!-- Zimmet Modal -->
<?php include_once "modal/zimmet-modal.php" ?>

<!-- Kaskiye Teslim Modal -->
<?php include_once "modal/kasiye-teslim-modal.php" ?>

<!-- İade Modal -->
<?php include_once "modal/iade-modal.php" ?>

<!-- Hurda Sayaç İade Modal -->
<?php include_once "modal/hurda-iade-modal.php" ?>

<!-- Kaskiye Teslim Modal -->
<?php include_once "modal/kasiye-teslim-modal.php" ?>

<!-- Demirbaş İşlem Geçmişi Modal -->
<div class="modal" id="demirbasGecmisModal" tabindex="-1" aria-hidden="true" style="z-index: 9999 !important;">
	<div class="modal-dialog modal-dialog-centered modal-xl">
		<div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
			<div class="modal-header bg-soft-info border-bottom">
				<div class="modal-title-section d-flex align-items-center">
					<div class="avatar-xs me-2 rounded bg-info bg-opacity-10 d-flex align-items-center justify-content-center"
						style="width: 32px; height: 32px;">
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

