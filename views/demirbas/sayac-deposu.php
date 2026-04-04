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

	<div class="d-flex justify-content-end mb-3">
		<ul class="nav nav-pills" id="sayacDepoTab" role="tablist">
			<li class="nav-item" role="presentation">
				<button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sayaclarPane" type="button">Sayaçlar</button>
			</li>
			<li class="nav-item" role="presentation">
				<button class="nav-link" data-bs-toggle="tab" data-bs-target="#sayacHareketPane" type="button">Hareketler</button>
			</li>
		</ul>
	</div>

	<div class="card">
		<div class="card-body">
			<div class="tab-content">
				<div class="tab-pane fade show active" id="sayaclarPane" role="tabpanel">
					<div class="row g-3 mb-3">
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Depodaki Toplam Yeni</small><h4 id="sayacCardYeniDepo" class="mb-0"><?php echo $depoOzet->yeni_depoda; ?></h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Depodaki Toplam Hurda</small><h4 id="sayacCardHurdaDepo" class="mb-0"><?php echo $depoOzet->hurda_depoda; ?></h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Zimmetli Yeni</small><h4 id="sayacCardYeniPersonel" class="mb-0"><?php echo $depoOzet->yeni_personelde; ?></h4></div></div></div>
						<div class="col-md-3"><div class="card border h-100"><div class="card-body py-2"><small class="text-muted">Zimmetli Hurda</small><h4 id="sayacCardHurdaPersonel" class="mb-0"><?php echo $depoOzet->hurda_personelde; ?></h4></div></div></div>
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
