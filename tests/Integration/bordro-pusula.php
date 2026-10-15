<?php
/** Salt okunur regresyon: php tests/Integration/bordro-pusula.php FIRMA DONEM [PDF_YOLU] */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/Autoloader.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$firma = (int) ($argv[1] ?? 0); $donemId = (int) ($argv[2] ?? 0);
if ($firma <= 0 || $donemId <= 0) { fwrite(STDERR, "Firma ve dönem ID gereklidir.\n"); exit(1); }
$_SESSION['firma_id'] = $firma;
try {
    $model = new App\Model\BordroPusulaModel();
    $rapor = $model->rapor($firma, $donemId);
    $bordro = new App\Model\BordroPersonelModel();
    $liste = $bordro->getPersonellerByDonem($donemId);
    if (count($rapor['kayitlar']) !== count($liste)) throw new RuntimeException('Toplu raporda personel eksik.');
    $byId = [];
    foreach ($liste as $row) $byId[$row->id] = $row;
    foreach ($rapor['kayitlar'] as $item) {
        if ($item['hata']) throw new RuntimeException('Çıktı hazır değil: ' . $item['hata']);
        $row = $item['kayit']; $before = serialize($row);
        $p = $item['pusula']; $d = $p['detay'];
        $h = $bordro->hesaplaOrtakGosterimDegerleri($byId[$row->id], $rapor['donem'], (float) ($d['parametreler']['asgari_ucret_net'] ?? 0));
        $icerik = App\Service\BordroYayinIcerikService::olustur($byId[$row->id], $rapor['donem'], $h, $bordro->getMuhasebeOdemeOzeti($h));
        if ($icerik['banka_net_kurus'] !== $p['resmi']['banka_net_kurus'] || $icerik['kalemler'] !== $p['resmi']['kalemler']) throw new RuntimeException('Liste/yayın ve PDF farklı.');
        if ($before !== serialize($row)) throw new RuntimeException('Snapshot değiştirildi.');
        if ($p['kazanc_kurus'] - $p['yasal_kesinti_kurus'] - $p['ozel_kesinti_kurus'] !== (int) round($row->banka_odemesi * 100)) throw new RuntimeException('Net banka mutabakatı farklı.');
        if ($row->tc_kimlik_no && !preg_match('/^\d{11}$/D', $row->tc_kimlik_no)) throw new RuntimeException('Kimlik şifresi çözülemedi.');
    }
    $ilk = $rapor['kayitlar'][0] ?? null;
    if ($ilk && $model->rapor($firma, $donemId, (int) $ilk['kayit']->id)['kayitlar'][0]['pusula'] !== $ilk['pusula']) throw new RuntimeException('Tekil/toplu tutarsız.');
    try { $model->rapor(PHP_INT_MAX, $donemId); throw new RuntimeException('Firma izolasyonu yok.'); }
    catch (DomainException $e) { /* Başka firma dönemi reddedildi. */ }
    $pages = null;
    if (!empty($argv[3])) {
        $pdf = App\Service\BordroPusulaService::pdf();
        foreach ($rapor['kayitlar'] as $i => $item) {
            if ($i > 0) $pdf->AddPage();
            $pdf->WriteHTML(App\Service\BordroPusulaService::html($item['kayit'], $rapor['donem'], $rapor['firma'], $item['pusula']));
            if ($pdf->page !== $i + 1) throw new RuntimeException('Pusula sayfa taşması.');
        }
        $pages = $pdf->page;
        $pdf->Output($argv[3], 'F');
    }
    echo json_encode(['rows' => count($rapor['kayitlar']), 'pages' => $pages, 'snapshot_list_publish_pdf_match' => true, 'tenant_isolation' => true], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . PHP_EOL;
} catch (Throwable $e) { fwrite(STDERR, $e->getMessage() . PHP_EOL); exit(1); }
