<?php
require_once dirname(__DIR__, 2) . '/Autoloader.php';

use App\Model\DemirbasModel;
use App\Model\TanimlamalarModel;

$Demirbas = new DemirbasModel();
$Tanimlamalar = new TanimlamalarModel();

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
					<ul class="nav nav-pills" id="sayacDepoTab" role="tablist">
						<li class="nav-item" role="presentation">
							<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sayaclarPane" type="button">
								<i class="bx bx-tachometer me-1"></i> Sayaçlar
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

					<div class="table-responsive mb-4">
						<table id="sayacTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:3%"></th>
									<th class="text-center" style="width:5%">Sıra</th>
									<th style="width:8%" class="text-center">D.No</th>
									<th style="width:20%">Sayaç Adı</th>
									<th style="width:15%">Marka/Model</th>
									<th style="width:15%">Seri No</th>
									<th style="width:10%" class="text-center">Stok</th>
									<th style="width:10%" class="text-center">Durum</th>
									<th style="width:10%">Edinme Tarihi</th>
									<th style="width:5%" class="text-center">İşlemler</th>
								</tr>
							</thead>
							<tbody></tbody>
						</table>
					</div>
				</div>

				<div class="tab-pane fade" id="sayacHareketPane" role="tabpanel">
					<div class="table-responsive">
						<table id="sayacZimmetTable" class="table table-demirbas table-hover table-bordered nowrap w-100">
							<thead class="table-light">
								<tr>
									<th class="text-center" style="width:3%"></th>
									<th class="text-center" style="width:5%">ID</th>
									<th style="width:12%">Kategori</th>
									<th style="width:20%">Sayaç</th>
									<th style="width:15%">Marka/Model</th>
									<th style="width:18%">Personel</th>
									<th style="width:8%" class="text-center">Miktar</th>
									<th>Tarih</th>
									<th style="width:10%" class="text-center">Durum</th>
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

