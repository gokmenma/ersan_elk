<?php

if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\BordroKapanisArsivModel;
use App\Model\SystemLogModel;
use App\Service\Gate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!Gate::allowsPolicy('page', 'bordro/list', '', 'GET')) {
    http_response_code(403);
    exit('Bu arşivi indirmek için bordro yetkiniz bulunmamaktadır.');
}
$firmaId = (int) ($_SESSION['firma_id'] ?? 0);
if ($firmaId <= 0) exit('Firma bilgisi bulunamadı.');

try {
    $model = new BordroKapanisArsivModel();
    if (($_GET['action'] ?? '') === 'periods') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'success', 'data' => $model->getExportPeriods($firmaId)], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $donemIds = array_values(array_unique(array_filter(
        array_map('intval', explode(',', (string) ($_GET['donem_ids'] ?? ''))),
        static fn (int $id): bool => $id > 0
    )));
    if (!$donemIds) {
        http_response_code(422);
        exit('Lütfen dışa aktarılacak en az bir bordro dönemi seçin.');
    }
    $documents = $model->getExportDocuments($firmaId, $donemIds);
    if (!$documents) exit('Seçilen dönemlerde dışa aktarılabilecek kapalı bordro kaydı bulunamadı.');

    $book = new Spreadsheet();
    $book->removeSheetByIndex(0);
    $safeRow = static function (array $row): array {
        return array_map(static function ($value) {
            if (is_string($value) && preg_match('/^[=+\-@]/u', $value)) return "'" . $value;
            return $value;
        }, $row);
    };
    $headers = [
        'Arşiv Özeti' => ['Dönem','Başlangıç','Bitiş','Arşiv Durumu','Sürüm','Kapanış Tarihi','Kapatan','Personel','Net Hakediş','Banka','Elden','Sodexo','Diğer','Manifest SHA-256'],
        'Maaş Bordroları' => ['Dönem ID','Dönem','Arşiv ID','Sürüm','Arşiv Durumu','Bordro Kayıt ID','Personel ID','Personel','Maaş Türü','Nominal Maaş','Maaş Günü','Fiilî Gün','Çalışan Gün','Brüt Maaş','SGK İşçi','İşsizlik İşçi','Gelir Vergisi','Damga Vergisi','SGK İşveren','İşsizlik İşveren','Toplam Maliyet','Kümülatif Matrah','Fazla Mesai Saat','Fazla Mesai Tutar','Net Hakediş','Kesinti','Prim/Ek Ödeme','Banka','Elden','Sodexo','Diğer','Dağıtım Manuel','Hesaplama Tarihi','Hesaplayan ID','Hesaplayan','Doküman SHA-256','Hash Durumu','Tam Snapshot Dosyası'],
        'Ek Ödeme Detayları' => ['Dönem','Sürüm','Personel','Kod','Etiket','Açıklama','Gün/Adet','Tutar','Net Etki','Banka Matrahına Ekle','Resmî Alacağa Dahil'],
        'Kesinti Detayları' => ['Dönem','Sürüm','Personel','Kod','Etiket','Açıklama','Tutar','Hesaplama Tipi','Banka Matrahından'],
        'Ödeme Mutabakatı' => ['Dönem','Sürüm','Personel','Net Maaş','İcra','Ödenecek Net','Banka','Elden','Sodexo','Diğer','Dağıtım Toplamı','Fark','Durum'],
        'Maaş Kaynakları' => ['Dönem','Sürüm','Personel','Kaynak ID','Başlangıç','Bitiş','Kesişim Başlangıç','Kesişim Bitiş','Maaş Türü','Maaş Tutarı','Snapshot Nominal Maaş'],
        'Doğrulama' => ['Dönem','Sürüm','Arşiv Durumu','Manifest SHA-256','Önceki Manifest SHA-256','Doküman SHA-256','Doküman Hash Durumu','Açıklama'],
    ];
    $sheets = [];
    foreach ($headers as $title => $cols) {
        $sheet = $book->createSheet();
        $sheet->setTitle($title);
        $sheet->fromArray($cols, null, 'A1');
        $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($cols));
        $sheet->getStyle("A1:{$last}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter("A1:{$last}1");
        foreach (range(1, count($cols)) as $i) $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        $sheets[$title] = ['sheet' => $sheet, 'row' => 2];
    }

    $summary = [];
    $recoveryDocuments = [];
    foreach ($documents as $doc) {
        $snapshot = json_decode((string) $doc['snapshot_json'], true) ?: [];
        $detay = $snapshot['hesaplama_detay'] ?? [];
        if (is_string($detay)) $detay = json_decode($detay, true) ?: [];
        $version = $doc['surum_no'] ?? '-';
        $key = $doc['donem_id'] . '|' . $version . '|' . $doc['archive_status'];
        $net = (float) ($snapshot['net_maas'] ?? 0);
        $banka = (float) ($snapshot['banka_odemesi'] ?? 0);
        $elden = (float) ($snapshot['elden_odeme'] ?? 0);
        $sodexo = (float) ($snapshot['sodexo_odemesi'] ?? 0);
        $diger = (float) ($snapshot['diger_odeme'] ?? 0);
        if (!isset($summary[$key])) $summary[$key] = ['doc'=>$doc,'count'=>0,'net'=>0,'banka'=>0,'elden'=>0,'sodexo'=>0,'diger'=>0];
        foreach (['net','banka','elden','sodexo','diger'] as $field) $summary[$key][$field] += $$field;
        $summary[$key]['count']++;

        $matrah = is_array($detay['matrahlar'] ?? null) ? $detay['matrahlar'] : [];
        $computedHash = hash('sha256', (string) $doc['snapshot_json']);
        $hashValid = !empty($doc['dokum_hash']) && hash_equals((string) $doc['dokum_hash'], $computedHash);
        $snapshotFile = 'geri_yukleme/bordrolar/donem_' . (int) $doc['donem_id'] . '_surum_' . ($doc['surum_no'] ?? 'legacy') . '_bordro_' . (int) $doc['bordro_personel_id'] . '_personel_' . (int) $doc['personel_id'] . '.json';
        $recoveryDocuments[] = [
            'path' => $snapshotFile,
            'content' => (string) $doc['snapshot_json'],
            'donem_id' => (int) $doc['donem_id'],
            'arsiv_id' => $doc['arsiv_id'] === null ? null : (int) $doc['arsiv_id'],
            'surum_no' => $doc['surum_no'] === null ? null : (int) $doc['surum_no'],
            'bordro_personel_id' => (int) $doc['bordro_personel_id'],
            'personel_id' => (int) $doc['personel_id'],
            'archive_status' => $doc['archive_status'],
            'stored_sha256' => $doc['dokum_hash'] ?: null,
            'computed_sha256' => $computedHash,
            'hash_valid' => $hashValid,
        ];
        $personRow = [(int)$doc['donem_id'],$doc['donem_adi'],$doc['arsiv_id']??'',$version,$doc['archive_status'],(int)$doc['bordro_personel_id'],(int)$doc['personel_id'],$doc['adi_soyadi'],$detay['maas_durumu'] ?? '',(float)($matrah['nominal_maas'] ?? 0),(int)($matrah['maas_hesap_gunu'] ?? $snapshot['calisan_gun'] ?? 0),(int)($matrah['fiili_calisma_gunu'] ?? 0),(int)($snapshot['calisan_gun'] ?? 0),(float)($snapshot['brut_maas'] ?? 0),(float)($snapshot['sgk_isci'] ?? 0),(float)($snapshot['issizlik_isci'] ?? 0),(float)($snapshot['gelir_vergisi'] ?? 0),(float)($snapshot['damga_vergisi'] ?? 0),(float)($snapshot['sgk_isveren'] ?? 0),(float)($snapshot['issizlik_isveren'] ?? 0),(float)($snapshot['toplam_maliyet'] ?? 0),(float)($snapshot['kumulatif_matrah'] ?? 0),(float)($snapshot['fazla_mesai_saat'] ?? 0),(float)($snapshot['fazla_mesai_tutar'] ?? 0),$net,(float)($snapshot['kesinti_tutar'] ?? 0),(float)($snapshot['prim_tutar'] ?? 0),$banka,$elden,$sodexo,$diger,(int)($snapshot['dagitim_manuel'] ?? 0),$snapshot['hesaplama_tarihi'] ?? '',$snapshot['hesaplayan_id'] ?? '',$snapshot['hesaplayan_ad_soyad'] ?? '',$doc['dokum_hash'] ?? '',empty($doc['dokum_hash'])?'Mühür Yok':($hashValid?'Geçerli':'GEÇERSİZ'),$snapshotFile];
        $sheets['Maaş Bordroları']['sheet']->fromArray($safeRow($personRow), null, 'A'.$sheets['Maaş Bordroları']['row']++);

        foreach (($detay['ek_odemeler'] ?? []) as $item) {
            $sheets['Ek Ödeme Detayları']['sheet']->fromArray($safeRow([$doc['donem_adi'],$version,$doc['adi_soyadi'],$item['kod'] ?? '',$item['etiket'] ?? '',$item['aciklama'] ?? '',$item['gun_sayisi'] ?? $item['adet'] ?? '',(float)($item['tutar'] ?? 0),(float)($item['net_etki'] ?? $item['tutar'] ?? 0),$item['banka_matrahina_ekle'] ?? '',$item['resmi_alacagina_dahil'] ?? '']), null, 'A'.$sheets['Ek Ödeme Detayları']['row']++);
        }
        foreach (($detay['kesintiler'] ?? []) as $item) {
            $sheets['Kesinti Detayları']['sheet']->fromArray($safeRow([$doc['donem_adi'],$version,$doc['adi_soyadi'],$item['kod'] ?? '',$item['etiket'] ?? '',$item['aciklama'] ?? '',(float)($item['tutar'] ?? 0),$item['hesaplama_tipi'] ?? '',$item['banka_matrahina_ekle'] ?? '']), null, 'A'.$sheets['Kesinti Detayları']['row']++);
        }
        $odeme = is_array($detay['odeme_dagilimi'] ?? null) ? $detay['odeme_dagilimi'] : [];
        $icra = (float)($odeme['icra_kesintisi'] ?? 0); $odenecek = round($net-$icra,2); $dagitim=round($banka+$elden+$sodexo+$diger,2); $fark=round($odenecek-$dagitim,2);
        $sheets['Ödeme Mutabakatı']['sheet']->fromArray($safeRow([$doc['donem_adi'],$version,$doc['adi_soyadi'],$net,$icra,$odenecek,$banka,$elden,$sodexo,$diger,$dagitim,$fark,abs($fark)<=0.01?'Mutabık':'Fark Var']), null, 'A'.$sheets['Ödeme Mutabakatı']['row']++);
        foreach (($detay['kaynaklar']['gorev_maas_gecmisi'] ?? []) as $src) {
            $sheets['Maaş Kaynakları']['sheet']->fromArray($safeRow([$doc['donem_adi'],$version,$doc['adi_soyadi'],$src['id'] ?? '',$src['baslangic'] ?? '',$src['bitis'] ?? '',$src['donem_kesisim_baslangic'] ?? '',$src['donem_kesisim_bitis'] ?? '',$src['maas_durumu'] ?? '',(float)($src['maas_tutari'] ?? 0),(float)($matrah['nominal_maas'] ?? 0)]), null, 'A'.$sheets['Maaş Kaynakları']['row']++);
        }
        $sheets['Doğrulama']['sheet']->fromArray($safeRow([$doc['donem_adi'],$version,$doc['archive_status'],$doc['manifest_hash'] ?? '',$doc['onceki_arsiv_hash'] ?? '',$doc['dokum_hash'] ?? '',empty($doc['dokum_hash'])?'Mühür Yok':($hashValid?'Geçerli':'GEÇERSİZ'),$doc['archive_status']==='Mühürlü Arşiv'?'Kapanış anındaki değişmez sürüm':'Arşiv sistemi öncesi mevcut kayıt; ödeme anı mühürü değildir']), null, 'A'.$sheets['Doğrulama']['row']++);
    }
    foreach ($summary as $item) {
        $d=$item['doc'];
        $sheets['Arşiv Özeti']['sheet']->fromArray($safeRow([$d['donem_adi'],$d['baslangic_tarihi'],$d['bitis_tarihi'],$d['archive_status'],$d['surum_no']??'-',$d['kapanis_tarihi']??'',$d['kapatan_adi']??'',$item['count'],$item['net'],$item['banka'],$item['elden'],$item['sodexo'],$item['diger'],$d['manifest_hash']??'']), null, 'A'.$sheets['Arşiv Özeti']['row']++);
    }
    foreach ($sheets as $entry) $entry['sheet']->setShowGridlines(false);
    $book->setActiveSheetIndex(0);

    $tmpDir = sys_get_temp_dir() . '/bordro_arsiv_' . bin2hex(random_bytes(6));
    if (!mkdir($tmpDir, 0700, true)) throw new RuntimeException('Geçici arşiv klasörü oluşturulamadı.');
    $xlsxName = 'Bordro_Arsiv_Indeksi.xlsx'; $xlsxPath = $tmpDir.'/'.$xlsxName;
    (new Xlsx($book))->save($xlsxPath); $book->disconnectWorksheets();
    $recoveryDir = $tmpDir . '/geri_yukleme/bordrolar';
    if (!mkdir($recoveryDir, 0700, true)) throw new RuntimeException('Geri yükleme veri klasörü oluşturulamadı.');
    $manifestDocuments = [];
    $sumLines = [hash_file('sha256',$xlsxPath).'  '.$xlsxName];
    foreach ($recoveryDocuments as $recovery) {
        $absolutePath = $tmpDir . '/' . $recovery['path'];
        if (file_put_contents($absolutePath, $recovery['content']) === false) throw new RuntimeException('Bordro geri yükleme dosyası yazılamadı.');
        unset($recovery['content']);
        $manifestDocuments[] = $recovery;
        $sumLines[] = hash_file('sha256', $absolutePath) . '  ' . $recovery['path'];
    }
    $recoveryManifest = [
        'schema' => 'bordro-export-recovery-v1',
        'generated_at' => date(DATE_ATOM),
        'firma_id' => $firmaId,
        'selected_period_ids' => $donemIds,
        'document_count' => count($manifestDocuments),
        'warning' => 'Geri yükleme yalnız yetkili yönetici tarafından hash ve firma/dönem doğrulaması sonrasında, transaction içinde yapılmalıdır.',
        'documents' => $manifestDocuments,
    ];
    $manifestPath = $tmpDir . '/geri_yukleme/manifest.json';
    $manifestJson = json_encode($recoveryManifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($manifestJson === false || file_put_contents($manifestPath, $manifestJson) === false) throw new RuntimeException('Geri yükleme manifesti oluşturulamadı.');
    $readmePath = $tmpDir . '/geri_yukleme/README.txt';
    file_put_contents($readmePath, "Bu klasör seçilen bordroların tam snapshot verilerini içerir.\nExcel raporlama içindir; geri yüklemede manifest.json ve JSON dosyaları esas alınır.\nHash doğrulaması yapılmadan ve mevcut kayıtlar yedeklenmeden veri tabanına aktarım yapılmamalıdır.\n");
    $sumLines[] = hash_file('sha256', $manifestPath) . '  geri_yukleme/manifest.json';
    $sumLines[] = hash_file('sha256', $readmePath) . '  geri_yukleme/README.txt';
    file_put_contents($tmpDir.'/SHA256SUMS.txt', implode("\n", $sumLines) . "\n");
    $zipPath = $tmpDir.'/Bordro_Arsivi_'.date('Ymd_His').'.zip';
    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CREATE)!==true) throw new RuntimeException('ZIP arşivi oluşturulamadı.');
    $zip->addFile($xlsxPath,$xlsxName);
    $zip->addFile($tmpDir.'/SHA256SUMS.txt','SHA256SUMS.txt');
    $zip->addFile($manifestPath, 'geri_yukleme/manifest.json');
    $zip->addFile($readmePath, 'geri_yukleme/README.txt');
    foreach ($recoveryDocuments as $recovery) $zip->addFile($tmpDir . '/' . $recovery['path'], $recovery['path']);
    $zip->close();
    (new SystemLogModel())->logAction((int)($_SESSION['user_id']??$_SESSION['id']??0),'Bordro Arşivi Dışa Aktarma',count($donemIds).' dönemden '.count($documents).' bordro dokümanı arşiv Excel dosyasına aktarıldı.',SystemLogModel::LEVEL_IMPORTANT);
    header('Content-Type: application/zip'); header('Content-Disposition: attachment; filename="'.basename($zipPath).'"'); header('Content-Length: '.filesize($zipPath));
    readfile($zipPath);
    @unlink($xlsxPath); @unlink($tmpDir.'/SHA256SUMS.txt'); @unlink($manifestPath); @unlink($readmePath);
    foreach ($recoveryDocuments as $recovery) @unlink($tmpDir . '/' . $recovery['path']);
    @rmdir($recoveryDir); @rmdir($tmpDir . '/geri_yukleme'); @unlink($zipPath); @rmdir($tmpDir); exit;
} catch (Throwable $e) {
    http_response_code(500); exit('Bordro arşivi oluşturulamadı: '.$e->getMessage());
}
