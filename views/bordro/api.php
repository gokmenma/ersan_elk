<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once dirname(__DIR__, 2) . '/bootstrap.php';

use App\Model\BordroDonemModel;
use App\Model\BordroPersonelModel;
use App\Model\PersonelModel;
use App\Model\BordroParametreModel;
use App\Model\SystemLogModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Helper\Helper;
use App\Helper\Date;
use App\Helper\Security;

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';

    $BordroDonem = new BordroDonemModel();
    $BordroPersonel = new BordroPersonelModel();
    $BordroParametre = new BordroParametreModel();
    $SystemLog = new SystemLogModel();
    $userId = $_SESSION['user_id'] ?? 0;

    try {
        switch ($action) {

            // Yeni Dönem Oluştur
            case 'donem-ekle':
                $donem_adi = trim($_POST['donem_adi'] ?? '');
                $baslangic_tarihi = Date::Ymd($_POST['baslangic_tarihi'] ?? '');
                $bitis_tarihi = Date::Ymd($_POST['bitis_tarihi'] ?? '');

                // Validasyon
                if (empty($donem_adi)) {
                    throw new Exception('Dönem adı zorunludur.');
                }
                if (empty($baslangic_tarihi) || empty($bitis_tarihi)) {
                    throw new Exception('Başlangıç ve bitiş tarihleri zorunludur.');
                }
                if (strtotime($baslangic_tarihi) > strtotime($bitis_tarihi)) {
                    throw new Exception('Başlangıç tarihi bitiş tarihinden sonra olamaz.');
                }

                /**Dönem aralığı 31 günden fazla olamaz */
                if (strtotime($bitis_tarihi) >= strtotime($baslangic_tarihi . ' + 31 days')) {
                    throw new Exception('Dönem aralığı 31 günden fazla olamaz.');
                }


                // echo json_encode([
                //     "status"=>"success",
                //     "message"=>"Başlangıç Tarihi: ".$baslangic_tarihi." Bitiş Tarihi: ".$bitis_tarihi,
                // ]);exit;
                /** Aynı dönemde başka bir dönem varsa ekleme yapma */
                $donem = $BordroDonem->getDonemByDateRange($baslangic_tarihi, $bitis_tarihi);
                if ($donem) {
                    throw new Exception('Bu dönemde başka bir dönem var.');
                }


                // Dönemi oluştur
                $donemId = $BordroDonem->createDonem([
                    'donem_adi' => $donem_adi,
                    'firma_id' => $_SESSION["firma_id"],
                    'baslangic_tarihi' => $baslangic_tarihi,
                    'bitis_tarihi' => $bitis_tarihi
                ]);

                // Uygun personelleri döneme ekle
                $eklenenSayisi = $BordroPersonel->addPersonellerToDonem($donemId, $baslangic_tarihi, $bitis_tarihi);

                echo json_encode([
                    'status' => 'success',
                    'message' => "Dönem oluşturuldu ve $eklenenSayisi personel eklendi.",
                    'donem_id' => $donemId
                ]);

                $SystemLog->logAction($userId, 'Maaş Dönem Açma', "$donem_adi dönemi oluşturuldu.", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Dönem Güncelle
            case 'donem-guncelle':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $donem_adi = trim($_POST['donem_adi'] ?? '');

                if ($donem_id <= 0 || empty($donem_adi)) {
                    throw new Exception('Geçersiz dönem bilgileri.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                if ($donem->kapali_mi) {
                    throw new Exception('Kapalı dönemler güncellenemez.');
                }

                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET donem_adi = ? WHERE id = ?");
                if ($sql->execute([$donem_adi, $donem_id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Dönem adı başarıyla güncellendi.',
                        'donem_adi' => $donem_adi
                    ]);
                    $SystemLog->logAction($userId, 'Maaş Dönem Güncelleme', "Dönem adı güncellendi: $donem_adi (ID: $donem_id)", SystemLogModel::LEVEL_IMPORTANT);
                } else {
                    throw new Exception('Güncelleme işlemi başarısız.');
                }
                break;

            // Dönem Personel Görsün Güncelle
            case 'donem-personel-gorsun-guncelle':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $personel_gorsun = intval($_POST['personel_gorsun'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem bilgisi.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET personel_gorsun = ? WHERE id = ?");
                if ($sql->execute([$personel_gorsun, $donem_id])) {
                    $durumStr = $personel_gorsun == 1 ? 'Görünür' : 'Gizli';
                    echo json_encode([
                        'status' => 'success',
                        'message' => "Dönem personel için $durumStr yapıldı."
                    ]);
                    $SystemLog->logAction($userId, 'Maaş Dönem Görünürlük Güncelleme', "Dönem görünürlüğü güncellendi: $donem->donem_adi - Durum: $durumStr", SystemLogModel::LEVEL_IMPORTANT);
                } else {
                    throw new Exception('Güncelleme işlemi başarısız.');
                }
                break;

            // Personel Kesinti Listesi Getir
            case 'get-personel-kesinti-listesi':
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                $kesintiler = $BordroPersonel->getDonemKesintileriListe($personel_id, $donem_id);

                echo json_encode([
                    'status' => 'success',
                    'data' => $kesintiler
                ]);
                break;

            // Personel Ek Ödeme Listesi Getir
            case 'get-personel-ek-odeme-listesi':
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                $ekOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($personel_id, $donem_id);

                echo json_encode([
                    'status' => 'success',
                    'data' => $ekOdemeler
                ]);
                break;

            // Personel Kesinti Sil
            case 'personel-kesinti-sil':
                $id = intval($_POST['id'] ?? 0);
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt ID.');
                }

                // Dönem kapalı mı kontrolü
                if ($donem_id > 0) {
                    $donem = $BordroDonem->getDonemById($donem_id);
                    if ($donem && $donem->kapali_mi == 1) {
                        throw new Exception('Bu dönem kapatılmış. Kapalı dönemlerdeki kesintiler silinemez.');
                    }
                }

                // Log detaylarını almak için kaydı çekelim
                $kesintiBak = $BordroPersonel->getDb()->prepare("SELECT k.*, p.adi_soyadi FROM personel_kesintileri k JOIN personel p ON k.personel_id = p.id WHERE k.id = ?");
                $kesintiBak->execute([$id]);
                $kesintiInfo = $kesintiBak->fetch(PDO::FETCH_OBJ);

                $sql = $BordroPersonel->getDb()->prepare("UPDATE personel_kesintileri SET silinme_tarihi = NOW() WHERE id = ?");
                if ($sql->execute([$id])) {
                    // Maaş tekrar hesapla
                    if ($personel_id > 0 && $donem_id > 0) {
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                    }

                    if ($kesintiInfo) {
                        $logDetay = "{$kesintiInfo->adi_soyadi} isimli personelin " . number_format($kesintiInfo->tutar, 2, ',', '.') . " ₺ tutarındaki kesintisi ({$kesintiInfo->aciklama}) silindi.";
                        $SystemLog->logAction($userId, 'Bordro Kesinti Silindi', $logDetay, SystemLogModel::LEVEL_IMPORTANT);
                    }

                    echo json_encode(['status' => 'success', 'message' => 'Kesinti silindi.']);
                } else {
                    throw new Exception('Silme işlemi başarısız.');
                }
                break;

            // Personel Ek Ödeme Sil
            case 'personel-ek-odeme-sil':
                $id = intval($_POST['id'] ?? 0);
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt ID.');
                }

                // Dönem kapalı mı kontrolü
                if ($donem_id > 0) {
                    $donem = $BordroDonem->getDonemById($donem_id);
                    if ($donem && $donem->kapali_mi == 1) {
                        throw new Exception('Bu dönem kapatılmış. Kapalı dönemlerdeki ek ödemeler silinemez.');
                    }
                }

                // Log detaylarını almak için kaydı çekelim
                $odemeBak = $BordroPersonel->getDb()->prepare("SELECT eo.*, p.adi_soyadi FROM personel_ek_odemeler eo JOIN personel p ON eo.personel_id = p.id WHERE eo.id = ?");
                $odemeBak->execute([$id]);
                $odemeInfo = $odemeBak->fetch(PDO::FETCH_OBJ);

                $sql = $BordroPersonel->getDb()->prepare("UPDATE personel_ek_odemeler SET silinme_tarihi = NOW() WHERE id = ?");
                if ($sql->execute([$id])) {
                    // Maaş tekrar hesapla
                    if ($personel_id > 0 && $donem_id > 0) {
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                    }

                    if ($odemeInfo) {
                        $logDetay = "{$odemeInfo->adi_soyadi} isimli personelin " . number_format($odemeInfo->tutar, 2, ',', '.') . " ₺ tutarındaki ek ödemesi ({$odemeInfo->aciklama}) silindi.";
                        $SystemLog->logAction($userId, 'Bordro Ek Ödeme Silindi', $logDetay, SystemLogModel::LEVEL_IMPORTANT);
                    }

                    echo json_encode(['status' => 'success', 'message' => 'Ek ödeme silindi.']);
                } else {
                    throw new Exception('Silme işlemi başarısız.');
                }
                break;

            // Personelleri Güncelle (Yeni çalışanları ekle)
            case 'personel-guncelle':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                $eklenenSayisi = $BordroPersonel->addPersonellerToDonem(
                    $donem_id,
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi
                );

                echo json_encode([
                    'status' => 'success',
                    'message' => $eklenenSayisi > 0
                        ? "$eklenenSayisi yeni personel eklendi."
                        : 'Eklenecek yeni personel bulunamadı.'
                ]);
                break;

            // Personeli Dönemden Çıkar
            case 'personel-cikar':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                // Log detaylarını almak için kaydı çekelim
                $personelBak = $BordroPersonel->getDb()->prepare("
                    SELECT p.adi_soyadi, d.donem_adi 
                    FROM bordro_personel bp 
                    JOIN personel p ON bp.personel_id = p.id 
                    JOIN bordro_donemi d ON bp.donem_id = d.id 
                    WHERE bp.id = ?
                ");
                $personelBak->execute([$id]);
                $personelInfo = $personelBak->fetch(PDO::FETCH_OBJ);

                $BordroPersonel->removeFromDonem($id);

                if ($personelInfo) {
                    $logDetay = "{$personelInfo->adi_soyadi} isimli personel {$personelInfo->donem_adi} döneminden çıkarıldı.";
                    $SystemLog->logAction($userId, 'Bordro Personel Çıkarıldı', $logDetay, SystemLogModel::LEVEL_IMPORTANT);
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Personel dönemden çıkarıldı.'
                ]);
                break;

            // Maaş Hesapla
            case 'maas-hesapla':
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $personel_ids = $_POST['personel_ids'] ?? [];

                if ($donem_id <= 0 || empty($personel_ids)) {
                    throw new Exception('Hesaplama için dönem ve personel seçimi zorunludur.');
                }

                $hesaplananSayisi = 0;
                $hesaplananIds = []; // Başarıyla hesaplanan bp_id'leri topla
                $toplamOnayBekleyen = 0;
                $toplamOnayBekleyenTutar = 0;
                $onayBekleyenPersoneller = [];

                $Personel = new PersonelModel();

                $hesaplayanId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
                $hesaplayanAdSoyad = $_SESSION['user_full_name'] ?? ($_SESSION['user']->adi_soyadi ?? 'Sistem');

                foreach ($personel_ids as $bp_id) {
                    if ($BordroPersonel->hesaplaMaas(intval($bp_id), $hesaplayanId, $hesaplayanAdSoyad)) {
                        $hesaplananSayisi++;
                        $hesaplananIds[] = intval($bp_id);
                    }
                }

                // Onay bekleyen kesintileri tek sorguda toplu çek (N+1 önlemi)
                $onayBekleyenMap = $BordroPersonel->getOnayBekleyenBatch($hesaplananIds, $donem_id);
                foreach ($onayBekleyenMap as $bp_id => $row) {
                    if ($row->onay_bekleyen_adet > 0) {
                        $toplamOnayBekleyen += $row->onay_bekleyen_adet;
                        $toplamOnayBekleyenTutar += $row->onay_bekleyen_tutar;
                        $onayBekleyenPersoneller[] = [
                            'personel_id' => $row->personel_id,
                            'adi_soyadi' => $row->adi_soyadi,
                            'kesinti_adet' => $row->onay_bekleyen_adet,
                            'kesinti_tutar' => $row->onay_bekleyen_tutar
                        ];
                    }
                }

                $message = "$hesaplananSayisi personelin maaşı hesaplandı.";
                $warning = null;
                $warningDetails = null;

                if ($toplamOnayBekleyen > 0) {
                    $warning = "Dikkat: $toplamOnayBekleyen adet kesinti onay bekliyor (Toplam: " . number_format($toplamOnayBekleyenTutar, 2, ',', '.') . " TL). Onaylanmadan maaş hesaplamasına dahil edilmeyecek.";

                    // Personel detaylarını oluştur (tıklanabilir linkler ile - şifreli ID)
                    $detaylar = [];
                    foreach ($onayBekleyenPersoneller as $p) {
                        $encryptedId = Security::encrypt($p['personel_id']);
                        $personelLink = "index.php?p=personel%2Fmanage&id=" . urlencode($encryptedId) . "&tab=kesintiler";
                        $detaylar[] = "<li><a href='" . $personelLink . "' class='text-primary fw-bold'>" . htmlspecialchars($p['adi_soyadi']) . "</a>: " . $p['kesinti_adet'] . " kesinti (" . number_format($p['kesinti_tutar'], 2, ',', '.') . " TL)</li>";
                    }
                    $warningDetails = "<ul class='text-start mb-0 ps-3' style='list-style:none;'>" . implode('', $detaylar) . "</ul>";
                }

                // İcra uyarılarını işle
                if (!empty($BordroPersonel->icra_uyarilari)) {
                    $icraWarningText = "Bazı personellerin icra kesintileri tamamlandı. Bekleyen icraları başlatabilirsiniz.";
                    if ($warning) {
                        $warning .= "<br><br>" . $icraWarningText;
                    } else {
                        $warning = $icraWarningText;
                    }

                    $icraDetaylar = [];
                    foreach ($BordroPersonel->icra_uyarilari as $u) {
                        $pAdi = "Personel";
                        // İsmi bul
                        foreach ($onayBekleyenPersoneller as $obp) {
                            if ($obp['personel_id'] == $u['personel_id']) {
                                $pAdi = $obp['adi_soyadi'];
                                break;
                            }
                        }
                        if ($pAdi == "Personel") {
                            $pd = $Personel->find($u['personel_id']);
                            $pAdi = $pd ? $pd->adi_soyadi : "Personel #" . $u['personel_id'];
                        }

                        $encryptedId = Security::encrypt($u['personel_id']);
                        $personelLink = "index.php?p=personel%2Fmanage&id=" . urlencode($encryptedId) . "&tab=icralar";
                        $icraDetaylar[] = "<li><i class='text-success me-2' data-feather='check-circle' style='width:14px;height:14px;'></i><a href='" . $personelLink . "' class='text-primary fw-bold'>" . htmlspecialchars($pAdi) . "</a> icra dosyası (" . htmlspecialchars($u['dosya_no']) . ") tamamlanmıştır. Sıradaki icra kesintisine başlayınız.</li>";
                    }

                    $icraWarningDetails = "<ul class='text-start mb-0 ps-3' style='list-style:none;'>" . implode('', $icraDetaylar) . "</ul>";
                    if ($warningDetails) {
                        $warningDetails .= "<hr class='my-2'>" . $icraWarningDetails;
                    } else {
                        $warningDetails = $icraWarningDetails;
                    }
                }

                // Görev geçmişi eksik uyarılarını işle
                if (!empty($BordroPersonel->gorev_gecmisi_eksik)) {
                    $eksikSayisi = count($BordroPersonel->gorev_gecmisi_eksik);
                    $ggWarningText = "<i class='bx bx-info-circle me-1'></i><strong>$eksikSayisi personelin görev geçmişi kaydı bulunamadı.</strong> Bu personellerin maaşları personel tablosundaki mevcut verilerden hesaplandı. Doğru hesaplama için lütfen görev geçmişi tanımlayın.";
                    if ($warning) {
                        $warning .= "<br><br>" . $ggWarningText;
                    } else {
                        $warning = $ggWarningText;
                    }

                    $ggDetaylar = [];
                    foreach ($BordroPersonel->gorev_gecmisi_eksik as $gg) {
                        $pd = $Personel->find($gg['personel_id']);
                        $pAdi = $pd ? $pd->adi_soyadi : "Personel #" . $gg['personel_id'];
                        $encryptedId = Security::encrypt($gg['personel_id']);
                        $personelLink = "index.php?p=personel%2Fmanage&id=" . urlencode($encryptedId) . "&tab=gorev_gecmisi";
                        $ggDetaylar[] = "<li><i class='bx bx-error text-warning me-2'></i><a href='" . $personelLink . "' class='text-primary fw-bold'>" . htmlspecialchars($pAdi) . "</a> — Görev geçmişi tanımlı değil</li>";
                    }

                    $ggWarningDetails = "<div class='mt-2'><strong><i class='bx bx-history me-1'></i>Görev Geçmişi Eksik:</strong></div><ul class='text-start mb-0 ps-3 mt-1' style='list-style:none;'>" . implode('', $ggDetaylar) . "</ul>";
                    if ($warningDetails) {
                        $warningDetails .= "<hr class='my-2'>" . $ggWarningDetails;
                    } else {
                        $warningDetails = $ggWarningDetails;
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $message,
                    'warning' => $warning,
                    'warning_details' => $warningDetails,
                    'onay_bekleyen_adet' => $toplamOnayBekleyen,
                    'onay_bekleyen_tutar' => $toplamOnayBekleyenTutar,
                    'onay_bekleyen_personeller' => $onayBekleyenPersoneller,
                    'icra_uyarilari' => $BordroPersonel->icra_uyarilari,
                    'gorev_gecmisi_eksik' => $BordroPersonel->gorev_gecmisi_eksik
                ]);

                $SystemLog->logAction($userId, 'Maaş Hesaplama', "$hesaplananSayisi personelin maaşı hesaplandı.", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // İcra Kesinti Detayı
            case 'get-icra-detail':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt ID.');
                }

                $bp = $BordroPersonel->find($id);
                if (!$bp) {
                    throw new Exception('Kayıt bulunamadı.');
                }

                $kesintiler = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);

                $html = '<div class="table-responsive">
                    <table class="table table-sm table-hover table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Tarih</th>
                                <th>İcra Dairesi / Dosya No</th>
                                <th>Açıklama</th>
                                <th class="text-end" style="width: 120px;">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>';

                $toplam = 0;
                $icraSayisi = 0;
                foreach ($kesintiler as $k) {
                    if ($k->tur === 'icra') {
                        $tutar = floatval($k->tutar);
                        $toplam += $tutar;
                        $icraSayisi++;
                        $tarih = !empty($k->tarih) ? date('d.m.Y', strtotime($k->tarih)) : (!empty($k->olusturma_tarihi) ? date('d.m.Y', strtotime($k->olusturma_tarihi)) : '-');
                        $html .= '<tr>
                            <td>' . $tarih . '</td>
                            <td>' . htmlspecialchars(($k->icra_dairesi ?? '') . ' ' . ($k->dosya_no ?? '')) . '</td>
                            <td>' . htmlspecialchars($k->aciklama ?? '-') . '</td>
                            <td class="text-end fw-bold text-danger">' . number_format($tutar, 2, ',', '.') . ' ₺</td>
                        </tr>';
                    }
                }

                if ($icraSayisi === 0) {
                    $html .= '<tr><td colspan="3" class="text-center text-muted">İcra kesintisi bulunamadı.</td></tr>';
                }

                $html .= '</tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2" class="text-end">Toplam:</th>
                                <th class="text-end text-danger">' . number_format($toplam, 2, ',', '.') . ' ₺</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>';

                // Personel adını al
                $Personel = new PersonelModel();
                $personelData = $Personel->find($bp->personel_id);

                echo json_encode([
                    'status' => 'success',
                    'html' => $html,
                    'personel_ad' => $personelData ? $personelData->adi_soyadi : '-'
                ]);
                break;

            // Kesinti ve ek ödeme türü etiketleri
            case 'get-detail':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz kayıt.');
                }

                $bp = $BordroPersonel->find($id);
                if (!$bp) {
                    throw new Exception('Kayıt bulunamadı.');
                }

                // Dönem bilgilerini al (çalışma günü hesabı için)
                $donemBilgi = $BordroDonem->getDonemById($bp->donem_id);

                // Liste ile birebir aynı veri setini kullan (görev geçmişi + JSON_EXTRACT alanları dahil)
                $detayRows = $BordroPersonel->getPersonellerByDonem($bp->donem_id, [$bp->id]);
                if (!empty($detayRows)) {
                    $bp = $detayRows[0];
                }

                $Personel = new PersonelModel();
                $personel = $Personel->find($bp->personel_id);

                // Kesinti ve ek ödeme türü etiketleri
                $kesintiTurEtiketleri = [
                    'icra' => 'İcra',
                    'avans' => 'Avans',
                    'nafaka' => 'Nafaka',
                    'ceza' => 'Ceza',
                    'izin_kesinti' => 'Ücretsiz İzin',
                    'bes_kesinti' => 'BES Kesintisi',
                    'diger' => 'Diğer Kesinti'
                ];

                $ekOdemeTurEtiketleri = [
                    'prim' => 'Prim',
                    'mesai' => 'Fazla Mesai',
                    'ikramiye' => 'İkramiye',
                    'yol' => 'Yol Yardımı',
                    'yemek' => 'Yemek Yardımı',
                    'hafta_ici_nobet' => 'Hafta İçi Nöbet',
                    'hafta_sonu_nobet' => 'Hafta Sonu Nöbet',
                    'resmi_tatil_nobet' => 'Resmi Tatil Nöbeti',
                    'nobet_grubu' => 'Nöbet Ödemeleri',
                    'diger' => 'Diğer Ek Ödeme'
                ];

                // Detaylı ek ödemeleri çek
                $ekOdemelerDetay = $BordroPersonel->getDonemEkOdemeleriDetay($bp->personel_id, $bp->donem_id);

                // Toplamları hesapla
                $guncelKesinti = $BordroPersonel->getDonemKesintileri($bp->personel_id, $bp->donem_id);
                $guncelEkOdeme = $BordroPersonel->getDonemEkOdemeleri($bp->personel_id, $bp->donem_id);

                // Liste ve detayda aynı hesap fonksiyonunu kullan
                $donemBaslangicTarihi = $donemBilgi?->baslangic_tarihi ?? date('Y-m-01');
                $asgariUcretNet = $BordroParametre->getGenelAyar('asgari_ucret_net', $donemBaslangicTarihi) ?? 17002.12;
                $hesap = $BordroPersonel->hesaplaOrtakGosterimDegerleri($bp, $donemBilgi, floatval($asgariUcretNet));
                $guncelEkOdeme = floatval($hesap['rawEkOdeme']);

                $maasDurumuGosterim = $hesap['maasDurumu'] ?: ($personel->maas_durumu ?? '-');
                $nominalMaas = floatval($hesap['maasTutari']);
                $gunlukUcret = $nominalMaas / 30;
                $ucretsizIzinGunu = intval($hesap['ucretsizIzinGunu']);
                $calismaGunu = intval($hesap['calismaGunu']);

                // Tutarları ortak hesap sonucundan al
                $toplamAlacak = floatval($hesap['toplamAlacagi']);
                $netAlacak = floatval($hesap['netAlacagi']);
                $icraKesinti = floatval($hesap['icraKesintisi']);
                $netMaasHesap = floatval($hesap['netMaasGercek']);
                $bankaOdemeModal = floatval($hesap['bankaOdemesi']);
                $sodexoOdemeModal = floatval($hesap['sodexoOdemesi']);
                $digerOdemeModal = floatval($hesap['digerOdeme']);
                $eldenOdemeModal = floatval($hesap['eldenOdeme']);

                // HTML oluştur
                $html = '<div class="row">';

                // Personel Bilgileri
                $html .= '<div class="col-md-6">';
                $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-user me-1"></i>Personel Bilgileri</h6>';
                $html .= '<table class="table table-sm">';
                $html .= '<tr><td class="text-muted">Ad Soyad:</td><td class="fw-bold">' . htmlspecialchars($personel->adi_soyadi ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">TC Kimlik:</td><td>' . htmlspecialchars($personel->tc_kimlik_no ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Departman:</td><td>' . htmlspecialchars($personel->departman ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Görev:</td><td>' . htmlspecialchars($personel->gorev ?? '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">İşe Giriş:</td><td>' . ($personel->ise_giris_tarihi ? date('d.m.Y', strtotime($personel->ise_giris_tarihi)) : '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Araç Kullanım:</td><td>' . htmlspecialchars($personel->arac_kullanim ?? 'Yok') . '</td></tr>';
                $html .= '</table>';
                $html .= '</div>';

                // Maaş Özeti
                $html .= '<div class="col-md-6">';
                $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-money me-1"></i>Maaş Özeti</h6>';
                $html .= '<table class="table table-sm">';
                $html .= '<tr><td class="text-muted">' . $maasDurumuGosterim . ' Maaş:</td><td class="fw-bold text-primary">' . ($nominalMaas ? number_format($nominalMaas, 2, ',', '.') . ' ₺' : '-') . '</td></tr>';
                $html .= '<tr><td class="text-muted">Günlük Ücret:</td><td class="text-secondary">' . number_format($gunlukUcret, 2, ',', '.') . ' ₺ <small class="text-muted">(' . $maasDurumuGosterim . ' / 30)</small></td></tr>';
                $html .= '<tr><td class="text-muted">Çalışma Günü:</td><td class="' . ($ucretsizIzinGunu > 0 ? 'text-warning' : 'text-secondary') . '">' . $calismaGunu . ' gün' . ($ucretsizIzinGunu > 0 ? ' <small class="text-muted">(-' . $ucretsizIzinGunu . ' izin)</small>' : '') . '</td></tr>';

                // Ücretsiz izin veya net/brüt maaş ise, çalışılan brüt/net maaşı göster
                $calisanBrutMaas = $toplamAlacak - floatval($hesap['rawEkOdeme']);
                if ($ucretsizIzinGunu > 0 || in_array($maasDurumuGosterim, ['Net', 'Brüt'])) {
                    $descText = ($maasDurumuGosterim == 'Net' || $maasDurumuGosterim == 'Brüt') ? ' (Gün x Ücret)' : ' (SGK matrahı)';
                    $html .= '<tr class="table-warning"><td class="text-muted">Hakediş (Maaş):</td><td class="fw-bold text-warning">' . number_format($calisanBrutMaas, 2, ',', '.') . ' ₺ <small class="text-muted">' . $descText . '</small></td></tr>';
                } else {
                    $calisanBrutMaas = $nominalMaas; // Tam ay çalıştıysa hakediş = nominal maaş
                }

                // ============================================================
                // TUTAR HESAPLAMALARI
                // ============================================================
                // Ortak hesap fonksiyonundan gelen değerleri kullan
                $kesintiKayitlariOnce = $BordroPersonel->getDonemKesintileriListe($bp->personel_id, $bp->donem_id);
                $digerKesintilerToplam = 0;
                foreach ($kesintiKayitlariOnce as $kk) {
                    if ($kk->tur !== 'izin_kesinti') {
                        $digerKesintilerToplam += floatval($kk->tutar);
                    }
                }

                $kesintiTutarOzet = max(0, $toplamAlacak - $netAlacak);

                // ============================================================
                // HTML ÇIKTISI
                // ============================================================
                $html .= '<tr><td class="text-muted">Ek Ödeme:</td><td class="text-success fw-medium">+' . number_format($guncelEkOdeme, 2, ',', '.') . ' ₺</td></tr>';


                // Kesinti Tutarı (Yasal)
                $html .= '<tr><td class="text-muted">Kesinti Tutarı:</td><td class="text-danger fw-medium">' . ($kesintiTutarOzet > 0 ? '-' . number_format($kesintiTutarOzet, 2, ',', '.') . ' ₺' : '0,00 ₺') . '</td></tr>';

                // Net Alacağı
                $html .= '<tr><td class="text-muted fw-bold">Net Alacağı:</td><td class="fw-bold text-success">' . number_format($netAlacak, 2, ',', '.') . ' ₺</td></tr>';

                // İcra / Diğer Kesintiler
                if ($digerKesintilerToplam > 0) {
                    $html .= '<tr><td class="text-muted">İcra / Diğer Kesinti:</td><td class="text-danger fw-medium">-' . number_format($digerKesintilerToplam, 2, ',', '.') . ' ₺</td></tr>';
                }

                // Net Maaş (son tutar: Net Alacağı - İcra)
                $html .= '<tr class="table-success"><td class="fw-bold">Net Maaş:</td><td class="fw-bold text-success fs-5">' . number_format($netMaasHesap, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</table>';
                $html .= '</div>';

                $html .= '</div>'; // İlk row kapandı

                // Kesintiler ve Ek Ödemeler - Detaylı
                $html .= '<div class="row mt-4">';

                // YASAL KESİNTİLER
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card border-danger h-100">';
                $html .= '<div class="card-header bg-danger text-white py-2">';
                $html .= '<i class="bx bx-building me-1"></i> Yasal Kesintiler';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<table class="table table-sm mb-0">';
                $html .= '<tbody>';

                $toplamYasalKesinti = 0;

                if ($bp->sgk_isci > 0) {
                    $html .= '<tr><td class="ps-3">SGK İşçi (%14)</td><td class="text-end pe-3 text-danger">-' . number_format($bp->sgk_isci, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->sgk_isci);
                }
                if ($bp->issizlik_isci > 0) {
                    $html .= '<tr><td class="ps-3">İşsizlik İşçi (%1)</td><td class="text-end pe-3 text-danger">-' . number_format($bp->issizlik_isci, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->issizlik_isci);
                }
                if ($bp->gelir_vergisi > 0) {
                    $html .= '<tr><td class="ps-3">Gelir Vergisi</td><td class="text-end pe-3 text-danger">-' . number_format($bp->gelir_vergisi, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->gelir_vergisi);
                }
                if ($bp->damga_vergisi > 0) {
                    $html .= '<tr><td class="ps-3">Damga Vergisi</td><td class="text-end pe-3 text-danger">-' . number_format($bp->damga_vergisi, 2, ',', '.') . ' ₺</td></tr>';
                    $toplamYasalKesinti += floatval($bp->damga_vergisi);
                }

                $html .= '<tr class="table-light"><td class="ps-3 fw-bold">Toplam</td><td class="text-end pe-3 fw-bold text-danger">-' . number_format($toplamYasalKesinti, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                // DİĞER KESİNTİLER (avans, icra, nafaka vs.)
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card border-warning h-100">';
                $html .= '<div class="card-header bg-warning text-dark py-2">';
                $html .= '<i class="bx bx-minus-circle me-1"></i> Diğer Kesintiler';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<table class="table table-sm mb-0">';
                $html .= '<tbody>';

                // Onaylanmış kesintileri çek ve detaylı grupla
                // NOT: izin_kesinti artık oluşturulmaz, ücretsiz izin doğrudan brüt maaştan düşülür
                $kesintiKayitlari = $kesintiKayitlariOnce; // Yukarıda zaten çekildi, tekrar DB sorgusu yapmıyoruz
                $kesintilerGruplanmis = [];

                foreach ($kesintiKayitlari as $k) {
                    // Eski izin_kesinti kayıtlarını atla (artık oluşturulmaz)
                    if ($k->tur === 'izin_kesinti') {
                        continue;
                    }

                    $etiket = $kesintiTurEtiketleri[$k->tur] ?? ucfirst($k->tur);

                    if (!isset($kesintilerGruplanmis[$etiket])) {
                        $kesintilerGruplanmis[$etiket] = (object) [
                            'etiket' => $etiket,
                            'toplam_tutar' => 0,
                            'adet' => 0
                        ];
                    }
                    $kesintilerGruplanmis[$etiket]->toplam_tutar += floatval($k->tutar);
                    $kesintilerGruplanmis[$etiket]->adet++;
                }

                // Gruplanmış listeyi tutara göre azalan sırala
                uasort($kesintilerGruplanmis, function ($a, $b) {
                    return $b->toplam_tutar <=> $a->toplam_tutar;
                });

                // Kesinti toplamını yeniden hesapla (izin_kesinti hariç)
                $guncelKesintiGosterim = 0;
                foreach ($kesintilerGruplanmis as $kGrup) {
                    $guncelKesintiGosterim += $kGrup->toplam_tutar;
                }

                if (empty($kesintilerGruplanmis)) {
                    $html .= '<tr><td class="text-center text-muted py-3" colspan="2"><i class="bx bx-check-circle me-1"></i>Kesinti yok</td></tr>';
                } else {
                    foreach ($kesintilerGruplanmis as $kesinti) {
                        $adetStr = $kesinti->adet > 1 ? ' <small class="text-muted">(' . $kesinti->adet . ' adet)</small>' : '';
                        $html .= '<tr class="cursor-pointer bg-light-subtle" data-bs-toggle="collapse" data-bs-target=".kesinti-detail-' . md5($kesinti->etiket) . '">
                            <td class="ps-3">' . htmlspecialchars($kesinti->etiket) . $adetStr . ' <i class="bx bx-chevron-down small text-muted"></i></td>
                            <td class="text-end pe-3 text-danger">-' . number_format($kesinti->toplam_tutar, 2, ',', '.') . ' ₺</td>
                        </tr>';

                        foreach ($kesintiKayitlari as $k) {
                            $kEtiket = $kesintiTurEtiketleri[$k->tur] ?? ucfirst($k->tur);
                            if ($kEtiket === $kesinti->etiket && $k->tur !== 'izin_kesinti') {
                                $tarih = !empty($k->tarih) ? date('d.m.Y', strtotime($k->tarih)) : (!empty($k->olusturma_tarihi) ? date('d.m.Y', strtotime($k->olusturma_tarihi)) : '-');
                                $html .= '<tr class="collapse kesinti-detail-' . md5($kesinti->etiket) . '">
                                    <td class="ps-4 small">' . $tarih . ' - ' . htmlspecialchars($k->aciklama ?: '-') . '</td>
                                    <td class="text-end pe-3 small text-danger">-' . number_format($k->tutar, 2, ',', '.') . ' ₺</td>
                                </tr>';
                            }
                        }
                    }
                }

                $html .= '<tr class="table-light"><td class="ps-3 fw-bold">Toplam</td><td class="text-end pe-3 fw-bold text-danger">-' . number_format($guncelKesintiGosterim, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                // EK ÖDEMELER
                $html .= '<div class="col-md-4">';
                $html .= '<div class="card border-success h-100">';
                $html .= '<div class="card-header bg-success text-white py-2">';
                $html .= '<i class="bx bx-plus-circle me-1"></i> Ek Ödemeler';
                $html .= '</div>';
                $html .= '<div class="card-body p-0">';
                $html .= '<table class="table table-sm mb-0">';
                $html .= '<tbody>';

                // Puantaj dışı ek ödemeleri grupla
                $ekOdemelerNonPuantaj = [];
                $puantajOdemeler = [];
                $kacakKontrolOdemeler = [];

                // Tüm ek ödemeleri (listeli) al
                $tumEkOdemeler = $BordroPersonel->getDonemEkOdemeleriListe($bp->personel_id, $bp->donem_id);

                foreach ($tumEkOdemeler as $odeme) {
                    // Adet bilgisini açıklamadan çek (Örn: (30 Adet x 40,00 ₺))
                    $parsedAdet = 0;
                    if (preg_match('/\((\d+)\s*Adet/i', $odeme->aciklama ?? '', $adetMatch)) {
                        $parsedAdet = intval($adetMatch[1]);
                    }

                    if (strpos($odeme->aciklama ?? '', '[Puantaj]') === 0) {
                        // Puantaj ödemesi - ayrı göster
                        $puantajOdemeler[] = $odeme;
                    } elseif (strpos($odeme->aciklama ?? '', '[Kaçak Kontrol]') === 0) {
                        // Kaçak Kontrol ödemesi - ayrı göster
                        $kacakKontrolOdemeler[] = $odeme;
                    } else {
                        // Diğer ödemeler - grupla
                        $tur = $odeme->tur;
                        // Nöbet türlerini tek grupta topla
                        if (strpos($tur, 'nobet') !== false) {
                            $tur = 'nobet_grubu';
                        }

                        if (!isset($ekOdemelerNonPuantaj[$tur])) {
                            $ekOdemelerNonPuantaj[$tur] = ['toplam' => 0, 'adet' => 0, 'kayit_sayisi' => 0, 'items' => []];
                        }
                        $ekOdemelerNonPuantaj[$tur]['toplam'] += floatval($odeme->tutar);
                        $ekOdemelerNonPuantaj[$tur]['adet'] += $parsedAdet;
                        $ekOdemelerNonPuantaj[$tur]['kayit_sayisi']++;
                        $ekOdemelerNonPuantaj[$tur]['items'][] = $odeme;
                    }
                }

                if (empty($ekOdemelerNonPuantaj) && empty($puantajOdemeler) && empty($kacakKontrolOdemeler)) {
                    $html .= '<tr><td class="text-center text-muted py-3" colspan="2"><i class="bx bx-info-circle me-1"></i>Ek ödeme yok</td></tr>';
                } else {
                    // Önce normal ek ödemeleri göster
                    foreach ($ekOdemelerNonPuantaj as $tur => $data) {
                        $turEtiket = $ekOdemeTurEtiketleri[$tur] ?? ucfirst($tur);
                        $count = $data['adet'] > 0 ? $data['adet'] : ($data['kayit_sayisi'] > 0 ? $data['kayit_sayisi'] : 0);
                        $adetSubText = $count > 0 ? '<div class="text-muted fw-normal" style="font-size: 10px;">(Toplam ' . $count . ' Adet)</div>' : '';
                        $collapseId = "collapse-" . $tur;

                        $html .= '<tr class="cursor-pointer bg-light" data-bs-toggle="collapse" data-bs-target=".' . $collapseId . '" aria-expanded="false" style="border-top: 1px solid #e9ecef !important; border-bottom: 1px solid #e9ecef !important;">
                                    <td class="ps-3 pt-2 pb-2">
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-plus-circle me-2 text-primary fs-5"></i>
                                            <div>
                                                <div class="text-primary fw-bold" style="line-height: 1.1; font-size: 12px;">' . htmlspecialchars($turEtiket) . '</div>
                                                ' . $adetSubText . '
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3 align-middle">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <span class="text-success fw-bold">+' . number_format($data['toplam'], 2, ',', '.') . ' ₺</span>
                                            <i class="bx bx-chevron-down text-primary fs-5 transition-icon"></i>
                                        </div>
                                    </td>
                                  </tr>';

                        foreach ($data['items'] as $item) {
                            $itemAciklama = $item->aciklama ?? '';
                            $tarih = !empty($item->tarih) ? date('d.m.Y', strtotime($item->tarih)) : (!empty($item->created_at) ? date('d.m.Y', strtotime($item->created_at)) : '-');
                            if (preg_match('/^(.*?)\s*\((.*?)\)$/', $itemAciklama, $matches)) {
                                $anaMetin = $matches[1];
                                $detayMetin = $matches[2];
                                $html .= '<tr class="' . $collapseId . ' collapse"><td class="ps-4 py-2"><div class="fw-medium">' . $tarih . ' - ' . htmlspecialchars($anaMetin) . '</div><small class="text-muted">' . htmlspecialchars($detayMetin) . '</small></td><td class="text-end pe-3 text-success align-middle">+' . number_format($item->tutar, 2, ',', '.') . ' ₺</td></tr>';
                            } else {
                                $html .= '<tr class="' . $collapseId . ' collapse"><td class="ps-4 py-2 small">' . $tarih . ' - ' . htmlspecialchars($itemAciklama) . '</td><td class="text-end pe-3 text-success align-middle">+' . number_format($item->tutar, 2, ',', '.') . ' ₺</td></tr>';
                            }
                        }
                    }

                    // Kaçak Kontrol ödemelerini de aynı formatta göster
                    if (!empty($kacakKontrolOdemeler)) {
                        $toplamKacakTutar = 0;
                        foreach ($kacakKontrolOdemeler as $k)
                            $toplamKacakTutar += floatval($k->tutar);
                        $kacakCount = count($kacakKontrolOdemeler);
                        $adetSubText = '<div class="text-muted fw-normal" style="font-size: 10px;">(Toplam ' . $kacakCount . ' Adet)</div>';

                        $html .= '<tr class="cursor-pointer bg-light" data-bs-toggle="collapse" data-bs-target=".kacak-row" aria-expanded="false" style="border-top: 1px solid #e9ecef !important; border-bottom: 1px solid #e9ecef !important;">
                                    <td class="ps-3 pt-2 pb-2">
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-search-alt me-2 text-primary fs-5"></i>
                                            <div>
                                                <div class="text-primary fw-bold" style="line-height: 1.1; font-size: 12px;">Kaçak Kontrol Primi</div>
                                                ' . $adetSubText . '
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3 align-middle">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <span class="text-success fw-bold">+' . number_format($toplamKacakTutar, 2, ',', '.') . ' ₺</span>
                                            <i class="bx bx-chevron-down text-primary fs-5 transition-icon"></i>
                                        </div>
                                    </td>
                                  </tr>';

                        foreach ($kacakKontrolOdemeler as $kacak) {
                            $aciklama = str_replace('[Kaçak Kontrol] ', '', $kacak->aciklama ?? '');
                            $html .= '<tr class="kacak-row collapse"><td class="ps-4 py-2 small">' . htmlspecialchars($aciklama) . '</td><td class="text-end pe-3 text-success">+' . number_format($kacak->tutar, 2, ',', '.') . ' ₺</td></tr>';
                        }
                    }

                    // Puantaj ödemelerini gruplayarak göster
                    if (!empty($puantajOdemeler)) {
                        // Toplam adet ve tutar hesapla
                        $toplamPuantajAdet = 0;
                        $toplamPuantajTutar = 0;
                        foreach ($puantajOdemeler as $p) {
                            $pAciklama = str_replace('[Puantaj] ', '', $p->aciklama ?? '');
                            if (preg_match('/(\d+)\s*Adet/i', $pAciklama, $adetMatch)) {
                                $toplamPuantajAdet += intval($adetMatch[1]);
                            }
                            $toplamPuantajTutar += floatval($p->tutar);
                        }

                        $adetSubText = $toplamPuantajAdet > 0 ? '<div class="text-muted fw-normal" style="font-size: 10px;">(Toplam ' . number_format($toplamPuantajAdet, 0, ',', '.') . ' Adet)</div>' : '';
                        $puantajSectionClass = 'puantaj-section-' . $bp->id;

                        $html .= '<tr class="bg-light cursor-pointer" style="border-top: 1px solid #e9ecef !important; border-bottom: 1px solid #e9ecef !important;" data-bs-toggle="collapse" data-bs-target=".' . $puantajSectionClass . '" aria-expanded="true">
                                    <td class="ps-3 pt-2 pb-2">
                                        <div class="d-flex align-items-center">
                                            <i class="bx bx-briefcase me-2 text-primary fs-5"></i>
                                            <div>
                                                <div class="text-primary fw-bold" style="line-height: 1.1; font-size: 12px;">Puantaj Ödemeleri</div>
                                                ' . $adetSubText . '
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end pe-3 align-middle">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <span class="text-success fw-bold">+' . number_format($toplamPuantajTutar, 2, ',', '.') . ' ₺</span>
                                            <i class="bx bx-chevron-down text-muted"></i>
                                        </div>
                                    </td>
                                  </tr>';

                        $puantajGruplu = [];
                        foreach ($puantajOdemeler as $puantaj) {
                            $aciklama = str_replace('[Puantaj] ', '', $puantaj->aciklama ?? '');
                            $anaMetin = trim($aciklama);
                            $detayMetin = '';

                            if (preg_match('/^(.*?)\s*\((.*?)\)$/', $aciklama, $matches)) {
                                $anaMetin = trim($matches[1]);
                                $detayMetin = trim($matches[2]);
                            }

                            $adet = 0;
                            $birimFiyat = '';
                            if (preg_match('/(\d+)\s*Adet\s*x\s*([0-9\.,]+)\s*₺?/iu', $detayMetin, $detayMatch)) {
                                $adet = intval($detayMatch[1]);
                                $birimFiyat = trim($detayMatch[2]);
                            } elseif (preg_match('/(\d+)\s*Adet/iu', $aciklama, $adetMatch)) {
                                $adet = intval($adetMatch[1]);
                            }

                            $groupKey = mb_strtolower($anaMetin, 'UTF-8');
                            if (!isset($puantajGruplu[$groupKey])) {
                                $puantajGruplu[$groupKey] = [
                                    'ana' => $anaMetin,
                                    'adet' => 0,
                                    'tutar' => 0,
                                    'kayit_sayisi' => 0,
                                    'birim_fiyatlar' => [],
                                    'fiyat_kirilim' => []
                                ];
                            }

                            $puantajGruplu[$groupKey]['adet'] += $adet;
                            $puantajGruplu[$groupKey]['tutar'] += floatval($puantaj->tutar);
                            $puantajGruplu[$groupKey]['kayit_sayisi']++;
                            if ($birimFiyat !== '') {
                                $puantajGruplu[$groupKey]['birim_fiyatlar'][$birimFiyat] = true;
                            }

                            $fiyatKey = $birimFiyat !== '' ? $birimFiyat : '__unknown__';
                            if (!isset($puantajGruplu[$groupKey]['fiyat_kirilim'][$fiyatKey])) {
                                $puantajGruplu[$groupKey]['fiyat_kirilim'][$fiyatKey] = [
                                    'birim_fiyat' => $birimFiyat,
                                    'adet' => 0,
                                    'tutar' => 0,
                                    'kayit_sayisi' => 0
                                ];
                            }
                            $puantajGruplu[$groupKey]['fiyat_kirilim'][$fiyatKey]['adet'] += $adet;
                            $puantajGruplu[$groupKey]['fiyat_kirilim'][$fiyatKey]['tutar'] += floatval($puantaj->tutar);
                            $puantajGruplu[$groupKey]['fiyat_kirilim'][$fiyatKey]['kayit_sayisi']++;
                        }

                        uasort($puantajGruplu, function ($a, $b) {
                            return $b['tutar'] <=> $a['tutar'];
                        });

                        foreach ($puantajGruplu as $grup) {
                            $detayParts = [];
                            if ($grup['adet'] > 0) {
                                $detayParts[] = number_format($grup['adet'], 0, ',', '.') . ' Adet';
                            }

                            $birimFiyatlar = array_keys($grup['birim_fiyatlar']);
                            $hasMultiplePrice = count($birimFiyatlar) > 1;

                            if (count($birimFiyatlar) === 1) {
                                $detayParts[] = 'x ' . $birimFiyatlar[0] . ' ₺';
                            } elseif ($grup['kayit_sayisi'] > 1 && empty($detayParts)) {
                                $detayParts[] = $grup['kayit_sayisi'] . ' kayıt';
                            }

                            if ($hasMultiplePrice) {
                                $detayParts[] = '(birden fazla ücret tanımı)';
                            }

                            $detayText = !empty($detayParts) ? '<small class="text-muted">' . htmlspecialchars(implode(' ', $detayParts)) . '</small>' : '';

                            $detailClass = 'puantaj-detail-' . substr(md5($grup['ana']), 0, 12);

                            if ($hasMultiplePrice) {
                                $html .= '<tr class="collapse show cursor-pointer ' . $puantajSectionClass . '" data-bs-toggle="collapse" data-bs-target=".' . $detailClass . '" aria-expanded="false"><td class="ps-4 py-2"><div class="fw-medium">' . htmlspecialchars($grup['ana']) . '</div>' . $detayText . '</td><td class="text-end pe-3 text-success align-middle"><div class="d-flex justify-content-end align-items-center gap-2"><span>+' . number_format($grup['tutar'], 2, ',', '.') . ' ₺</span><i class="bx bx-chevron-down text-muted"></i></div></td></tr>';

                                $fiyatKirimlari = array_values($grup['fiyat_kirilim']);
                                usort($fiyatKirimlari, function ($a, $b) {
                                    return $b['tutar'] <=> $a['tutar'];
                                });

                                foreach ($fiyatKirimlari as $kirilim) {
                                    $detayEtiket = [];
                                    if ($kirilim['adet'] > 0) {
                                        $detayEtiket[] = number_format($kirilim['adet'], 0, ',', '.') . ' Adet';
                                    } elseif ($kirilim['kayit_sayisi'] > 0) {
                                        $detayEtiket[] = $kirilim['kayit_sayisi'] . ' kayıt';
                                    }

                                    if (!empty($kirilim['birim_fiyat'])) {
                                        $detayEtiket[] = 'x ' . $kirilim['birim_fiyat'] . ' ₺';
                                    }

                                    $html .= '<tr class="collapse ' . $detailClass . '"><td class="ps-5 py-2 small text-muted">' . htmlspecialchars(implode(' ', $detayEtiket)) . '</td><td class="text-end pe-3 small text-success">+' . number_format($kirilim['tutar'], 2, ',', '.') . ' ₺</td></tr>';
                                }
                            } else {
                                $html .= '<tr class="collapse show ' . $puantajSectionClass . '"><td class="ps-4 py-2"><div class="fw-medium">' . htmlspecialchars($grup['ana']) . '</div>' . $detayText . '</td><td class="text-end pe-3 text-success align-middle">+' . number_format($grup['tutar'], 2, ',', '.') . ' ₺</td></tr>';
                            }
                        }
                    }
                }

                $html .= '<tr class="table-light"><td class="ps-3 fw-bold">Toplam</td><td class="text-end pe-3 fw-bold text-success">+' . number_format($guncelEkOdeme, 2, ',', '.') . ' ₺</td></tr>';
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '</div>';
                $html .= '</div>';
                $html .= '</div>';

                $html .= '</div>'; // Kesinti/Ek ödeme row kapandı

                // İşveren Maliyetleri - Sadece Brüt maaş tipinde göster
                if (($personel->maas_durumu ?? '') == 'Brüt') {
                    $html .= '<div class="row mt-4">';
                    $html .= '<div class="col-12">';
                    $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-building me-1"></i>İşveren Maliyetleri</h6>';
                    $html .= '<div class="row text-center">';
                    $html .= '<div class="col-md-4"><div class="border rounded p-3"><small class="text-muted d-block">SGK İşveren (%20.5)</small><span class="fs-5 fw-bold text-warning">' . ($bp->sgk_isveren ? number_format($bp->sgk_isveren, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                    $html .= '<div class="col-md-4"><div class="border rounded p-3"><small class="text-muted d-block">İşsizlik İşveren (%2)</small><span class="fs-5 fw-bold text-warning">' . ($bp->issizlik_isveren ? number_format($bp->issizlik_isveren, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                    $html .= '<div class="col-md-4"><div class="border rounded p-3 bg text-white-primary bg-opacity-10"><small class="text-muted d-block">Toplam Maliyet</small><span class="fs-5 fw-bold text-primary">' . ($bp->toplam_maliyet ? number_format($bp->toplam_maliyet, 2, ',', '.') . ' ₺' : '-') . '</span></div></div>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                // Ödeme dağılımı: ortak hesap fonksiyonundan gelen değerleri kullan

                if ($bankaOdemeModal > 0 || $sodexoOdemeModal > 0 || $digerOdemeModal > 0 || $eldenOdemeModal > 0) {
                    $html .= '<div class="row mt-4">';
                    $html .= '<div class="col-12">';
                    $html .= '<h6 class="border-bottom pb-2 mb-3"><i class="bx bx-wallet me-1"></i>Ödeme Dağılımı</h6>';
                    $html .= '<div class="row text-center">';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">Banka</small><span class="fs-5 fw-bold text-primary">' . number_format($bankaOdemeModal, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">Sodexo</small><span class="fs-5 fw-bold text-info">' . number_format($sodexoOdemeModal, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3"><small class="text-muted d-block">Diğer</small><span class="fs-5 fw-bold text-secondary">' . number_format($digerOdemeModal, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '<div class="col-md-3"><div class="border rounded p-3 bg-warning bg-opacity-10"><small class="text-muted d-block">Elden</small><span class="fs-5 fw-bold text-warning">' . number_format($eldenOdemeModal, 2, ',', '.') . ' ₺</span></div></div>';
                    $html .= '</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                }

                if ($bp->hesaplama_tarihi) {
                    $html .= '<div class="text-muted small mt-3 text-end"><i class="bx bx-time me-1"></i>Son Hesaplama: ' . date('d.m.Y H:i', strtotime($bp->hesaplama_tarihi)) . '</div>';
                }

                echo json_encode([
                    'status' => 'success',
                    'html' => $html
                ]);
                break;

            // Dönemi Kapat
            case 'donem-kapat':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                // Dönem bilgilerini al
                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                // Döneme ait onaylanmamış avans ve izinleri kontrol et
                $warnings = [];

                // Onaylanmamış avansları kontrol et
                $avansQuery = $BordroDonem->getDb()->prepare("
                    SELECT COUNT(*) as count, SUM(tutar) as toplam 
                    FROM personel_avanslari pa
                    JOIN personel p ON pa.personel_id = p.id
                    WHERE pa.durum = 'beklemede' 
                    AND pa.silinme_tarihi IS NULL
                    AND p.firma_id = ?
                    AND pa.talep_tarihi BETWEEN ? AND ?
                ");
                $avansQuery->execute([
                    $_SESSION['firma_id'],
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi
                ]);
                $avansResult = $avansQuery->fetch(PDO::FETCH_OBJ);

                if ($avansResult && $avansResult->count > 0) {
                    $warnings[] = $avansResult->count . ' adet onaylanmamış avans talebi (' . number_format($avansResult->toplam, 2, ',', '.') . ' ₺)';
                }

                // Onaylanmamış izinleri kontrol et
                $izinQuery = $BordroDonem->getDb()->prepare("
                    SELECT COUNT(*) as count, SUM(DATEDIFF(bitis_tarihi, baslangic_tarihi) + 1) as toplam_gun
                    FROM personel_izinleri pi
                    JOIN personel p ON pi.personel_id = p.id
                    WHERE pi.onay_durumu = 'beklemede' 
                    AND pi.silinme_tarihi IS NULL
                    AND p.firma_id = ?
                    AND (
                        (pi.baslangic_tarihi BETWEEN ? AND ?)
                        OR (pi.bitis_tarihi BETWEEN ? AND ?)
                        OR (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)
                    )
                ");
                $izinQuery->execute([
                    $_SESSION['firma_id'],
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi,
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi,
                    $donem->baslangic_tarihi,
                    $donem->bitis_tarihi
                ]);
                $izinResult = $izinQuery->fetch(PDO::FETCH_OBJ);

                if ($izinResult && $izinResult->count > 0) {
                    $warnings[] = $izinResult->count . ' adet onaylanmamış izin talebi (' . intval($izinResult->toplam_gun) . ' gün)';
                }

                // Uyarı var mı kontrol et, force_close parametresi yoksa uyarı döndür
                $forceClose = isset($_POST['force_close']) && $_POST['force_close'] == '1';

                if (!empty($warnings) && !$forceClose) {
                    echo json_encode([
                        'status' => 'warning',
                        'message' => 'Bu döneme ait bekleyen talepler var:',
                        'warnings' => $warnings,
                        'donem_id' => $donem_id
                    ]);
                    break;
                }

                // Dönemi kapat
                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET kapali_mi = 1 WHERE id = ?");
                $sql->execute([$donem_id]);

                $message = 'Dönem kapatıldı. Artık bu dönemde değişiklik yapılamaz.';
                if (!empty($warnings)) {
                    $message .= ' (Uyarılar göz ardı edildi)';
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $message
                ]);

                $SystemLog->logAction($userId, 'Maaş Dönem Kapama', "Dönem kapatıldı (ID: $donem_id).", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Dönemi Aç
            case 'donem-ac':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                $sql = $BordroDonem->getDb()->prepare("UPDATE bordro_donemi SET kapali_mi = 0 WHERE id = ?");
                $sql->execute([$donem_id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Dönem açıldı. Artık bu dönemde değişiklik yapılabilir.'
                ]);

                $SystemLog->logAction($userId, 'Maaş Dönem Açma', "Dönem tekrar açıldı (ID: $donem_id).", SystemLogModel::LEVEL_IMPORTANT);
                break;

            // Ödeme Dağıt
            case 'odeme-dagit':
                $id = intval($_POST['id'] ?? 0);
                $banka = floatval($_POST['banka_odemesi'] ?? 0);
                $sodexo = floatval($_POST['sodexo_odemesi'] ?? 0);
                $diger = floatval($_POST['diger_odeme'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz personel.');
                }

                // Net maaşı al
                $sql = $BordroPersonel->getDb()->prepare("SELECT net_maas, kesinti_tutar FROM bordro_personel WHERE id = ?");
                $sql->execute([$id]);
                $data = $sql->fetch(PDO::FETCH_OBJ);

                if (!$data) {
                    throw new Exception('Bordro kaydı bulunamadı.');
                }

                $net = floatval($data->net_maas ?? 0);
                $kesinti_tutar = floatval($data->kesinti_tutar ?? 0);
                $toplam_alacak = $net + $kesinti_tutar;

                // İcra kesintisini JSON'dan al
                $icra = 0;
                $sqlDetay = $BordroPersonel->getDb()->prepare("SELECT hesaplama_detay FROM bordro_personel WHERE id = ?");
                $sqlDetay->execute([$id]);
                $detayRow = $sqlDetay->fetch(PDO::FETCH_OBJ);
                if ($detayRow && !empty($detayRow->hesaplama_detay)) {
                    $detayJson = json_decode($detayRow->hesaplama_detay, true);
                    $icra = floatval($detayJson['odeme_dagilimi']['icra_kesintisi'] ?? 0);
                }

                // Üst sınır kontrolü (%25)
                $maxSodexo = $toplam_alacak * 0.25;
                if ($sodexo > $maxSodexo + 0.01) { // Küçük kuruş farklarını tolore etmek için
                    throw new Exception('Sodexo tutarı toplam alacağın %25\'ini geçemez!');
                }

                $elden = max(0, $net - $banka - $sodexo - $icra - $diger);

                // Güncelle
                $sql = $BordroPersonel->getDb()->prepare("
                    UPDATE bordro_personel 
                    SET banka_odemesi = ?, sodexo_odemesi = ?, diger_odeme = ?, elden_odeme = ?, sodexo_manuel = 1, dagitim_manuel = 1
                    WHERE id = ?
                ");
                $sql->execute([$banka, $sodexo, $diger, $elden, $id]);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Ödeme dağılımı kaydedildi.'
                ]);
                break;

            // Ödeme Dağıtımı Sıfırla (Varsayılana Dön)
            case 'odeme-reset':
                $id = intval($_POST['id'] ?? 0);
                if ($id <= 0) {
                    throw new Exception('Geçersiz personel.');
                }

                // Manuel dağıtım bayraklarını kaldır
                $sql = $BordroPersonel->getDb()->prepare("
                    UPDATE bordro_personel 
                    SET dagitim_manuel = 0, sodexo_manuel = 0
                    WHERE id = ?
                ");
                $sql->execute([$id]);

                // Maaşı tekrar hesapla (Varsayılan dağılım mantığı çalışacak)
                $BordroPersonel->hesaplaMaas($id);

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Ödeme dağılımı varsayılana döndürüldü.'
                ]);
                break;

            // Personel Gelir Ekle / Güncelle
            case 'personel-gelir-ekle':
                $id = intval($_POST['id'] ?? 0); // ID varsa güncelleme
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $tutar = floatval($_POST['gelir_tutar'] ?? 0);
                $tur = trim($_POST['ek_odeme_tur'] ?? 'diger');
                $tarih = !empty($_POST['tarih']) ? $_POST['tarih'] : date('Y-m-d');

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                // Dönem kapalı mı kontrolü
                $donem = $BordroDonem->getDonemById($donem_id);
                if ($donem && $donem->kapali_mi == 1) {
                    throw new Exception('Bu dönem kapatılmış. Kapalı dönemlere ek ödeme eklenemez.');
                }

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

                if ($id > 0) {
                    // Güncelleme
                    $sql = $BordroPersonel->getDb()->prepare("
                        UPDATE personel_ek_odemeler 
                        SET aciklama = ?, tutar = ?, tur = ?, tarih = ?
                        WHERE id = ?
                    ");
                    if ($sql->execute([$aciklama, $tutar, $tur, $tarih, $id])) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Gelir başarıyla güncellendi ve maaş hesaplandı.'
                        ]);
                    } else {
                        throw new Exception('Gelir güncellenirken bir hata oluştu.');
                    }
                } else {
                    // Ekleme
                    if ($BordroPersonel->addEkOdeme($personel_id, $donem_id, $aciklama, $tutar, $tur, $tarih)) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Gelir başarıyla eklendi ve maaş güncellendi.'
                        ]);
                    } else {
                        throw new Exception('Gelir eklenirken bir hata oluştu.');
                    }
                }
                break;

            // Personel Kesinti Ekle / Güncelle
            case 'personel-kesinti-ekle':
                $id = intval($_POST['id'] ?? 0); // ID varsa güncelleme
                $personel_id = intval($_POST['personel_id'] ?? 0);
                $donem_id = intval($_POST['donem_id'] ?? 0);
                $aciklama = trim($_POST['aciklama'] ?? '');
                $tutar = floatval($_POST['kesinti_tutar'] ?? 0);
                $tur = trim($_POST['kesinti_tur'] ?? 'diger');
                $tarih = !empty($_POST['tarih']) ? $_POST['tarih'] : date('Y-m-d');

                if ($personel_id <= 0 || $donem_id <= 0) {
                    throw new Exception('Geçersiz personel veya dönem.');
                }

                // Dönem kapalı mı kontrolü
                $donem = $BordroDonem->getDonemById($donem_id);
                if ($donem && $donem->kapali_mi == 1) {
                    throw new Exception('Bu dönem kapatılmış. Kapalı dönemlere kesinti eklenemez.');
                }

                // Eğer tutar 0 ise ve gün girilmişse maaştan hesapla
                $gun = floatval($_POST['kesinti_gun'] ?? 0);
                if ($tutar <= 0 && $gun > 0) {
                    $stmt = $BordroPersonel->getDb()->prepare("SELECT maas_tutari FROM personel WHERE id = ?");
                    $stmt->execute([$personel_id]);
                    $maas = floatval($stmt->fetchColumn());

                    if ($maas > 0) {
                        $gunluk = $maas / 30;
                        $tutar = round($gunluk * $gun, 2);
                    }
                }

                if ($tutar <= 0) {
                    throw new Exception('Tutar 0\'dan büyük olmalıdır.');
                }

                if ($id > 0) {
                    // Güncelleme
                    $sql = $BordroPersonel->getDb()->prepare("
                        UPDATE personel_kesintileri 
                        SET aciklama = ?, tutar = ?, tur = ?, tarih = ?
                        WHERE id = ?
                    ");
                    if ($sql->execute([$aciklama, $tutar, $tur, $tarih, $id])) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Kesinti başarıyla güncellendi ve maaş hesaplandı.'
                        ]);
                    } else {
                        throw new Exception('Kesinti güncellenirken bir hata oluştu.');
                    }
                } else {
                    // Ekleme
                    if ($BordroPersonel->addKesinti($personel_id, $donem_id, $aciklama, $tutar, $tur, 'onaylandi', null, $tarih)) {
                        // Otomatik maaş hesapla
                        $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);

                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Kesinti başarıyla eklendi ve maaş güncellendi.'
                        ]);
                    } else {
                        throw new Exception('Kesinti eklenirken bir hata oluştu.');
                    }
                }
                break;

            // Parametre Ekle
            case 'add-parametre':
                $BordroParametre = new BordroParametreModel();

                $data = [
                    'kod' => trim($_POST['kod'] ?? ''),
                    'etiket' => trim($_POST['etiket'] ?? ''),
                    'kategori' => $_POST['kategori'] ?? 'gelir',
                    'hesaplama_tipi' => $_POST['hesaplama_tipi'] ?? 'net',
                    'gunluk_muaf_limit' => floatval($_POST['gunluk_muaf_limit'] ?? 0),
                    'aylik_muaf_limit' => floatval($_POST['aylik_muaf_limit'] ?? 0),
                    'muaf_limit_tipi' => $_POST['muaf_limit_tipi'] ?? 'yok',
                    'sgk_matrahi_dahil' => intval($_POST['sgk_matrahi_dahil'] ?? 0),
                    'gelir_vergisi_dahil' => intval($_POST['gelir_vergisi_dahil'] ?? 1),
                    'damga_vergisi_dahil' => intval($_POST['damga_vergisi_dahil'] ?? 0),
                    'gecerlilik_baslangic' => !empty($_POST['gecerlilik_baslangic']) ? $_POST['gecerlilik_baslangic'] : null,
                    'gecerlilik_bitis' => !empty($_POST['gecerlilik_bitis']) ? $_POST['gecerlilik_bitis'] : null,
                    'varsayilan_tutar' => floatval($_POST['varsayilan_tutar'] ?? 0),
                    'gunluk_tutar' => floatval($_POST['gunluk_tutar'] ?? 0),
                    'gun_sayisi_otomatik' => intval($_POST['gun_sayisi_otomatik'] ?? 0),
                    'varsayilan_gun_sayisi' => intval($_POST['varsayilan_gun_sayisi'] ?? 26),
                    'oran' => floatval($_POST['oran'] ?? 0),
                    'aciklama' => trim($_POST['aciklama'] ?? ''),
                    'sira' => intval($_POST['sira'] ?? 0),
                    'aktif' => 1
                ];

                if (empty($data['kod']) || empty($data['etiket'])) {
                    throw new Exception('Kod ve etiket zorunludur.');
                }

                if ($BordroParametre->addParametre($data)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Parametre başarıyla eklendi.'
                    ]);
                } else {
                    throw new Exception('Parametre eklenirken bir hata oluştu.');
                }
                break;

            // Parametre Güncelle
            case 'update-parametre':
                $BordroParametre = new BordroParametreModel();
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz parametre.');
                }

                $data = [
                    'kod' => trim($_POST['kod'] ?? ''),
                    'etiket' => trim($_POST['etiket'] ?? ''),
                    'kategori' => $_POST['kategori'] ?? 'gelir',
                    'hesaplama_tipi' => $_POST['hesaplama_tipi'] ?? 'net',
                    'gunluk_muaf_limit' => floatval($_POST['gunluk_muaf_limit'] ?? 0),
                    'aylik_muaf_limit' => floatval($_POST['aylik_muaf_limit'] ?? 0),
                    'muaf_limit_tipi' => $_POST['muaf_limit_tipi'] ?? 'yok',
                    'sgk_matrahi_dahil' => intval($_POST['sgk_matrahi_dahil'] ?? 0),
                    'gelir_vergisi_dahil' => intval($_POST['gelir_vergisi_dahil'] ?? 1),
                    'damga_vergisi_dahil' => intval($_POST['damga_vergisi_dahil'] ?? 0),
                    'gecerlilik_baslangic' => !empty($_POST['gecerlilik_baslangic']) ? $_POST['gecerlilik_baslangic'] : null,
                    'gecerlilik_bitis' => !empty($_POST['gecerlilik_bitis']) ? $_POST['gecerlilik_bitis'] : null,
                    'varsayilan_tutar' => floatval($_POST['varsayilan_tutar'] ?? 0),
                    'gunluk_tutar' => floatval($_POST['gunluk_tutar'] ?? 0),
                    'gun_sayisi_otomatik' => intval($_POST['gun_sayisi_otomatik'] ?? 0),
                    'varsayilan_gun_sayisi' => intval($_POST['varsayilan_gun_sayisi'] ?? 26),
                    'oran' => floatval($_POST['oran'] ?? 0),
                    'aciklama' => trim($_POST['aciklama'] ?? ''),
                    'sira' => intval($_POST['sira'] ?? 0)
                ];

                if ($BordroParametre->updateParametre($id, $data)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Parametre başarıyla güncellendi.'
                    ]);
                } else {
                    throw new Exception('Parametre güncellenirken bir hata oluştu.');
                }
                break;

            // Genel Ayar Ekle
            case 'add-genel-ayar':
                $BordroParametre = new BordroParametreModel();

                $parametre_kodu = trim($_POST['parametre_kodu'] ?? '');
                $parametre_adi = trim($_POST['parametre_adi'] ?? '');
                $deger = floatval(str_replace(['.', ','], ['', '.'], $_POST['deger'] ?? '0'));
                $gecerlilik_baslangic = $_POST['ayar_gecerlilik_baslangic'] ?? date('Y-m-d');
                $aciklama = trim($_POST['ayar_aciklama'] ?? '');

                if (empty($parametre_kodu) || empty($parametre_adi)) {
                    throw new Exception('Parametre kodu ve adı zorunludur.');
                }

                if ($BordroParametre->setGenelAyar($parametre_kodu, $parametre_adi, $deger, $gecerlilik_baslangic, null, $aciklama)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Genel ayar başarıyla eklendi.'
                    ]);
                } else {
                    throw new Exception('Genel ayar eklenirken bir hata oluştu.');
                }
                break;

            // Gelir/Kesinti türlerini getir (AJAX için)
            case 'get-gelir-turleri':
                $BordroParametre = new BordroParametreModel();
                $turler = $BordroParametre->getGelirTurleri();
                echo json_encode(['status' => 'success', 'data' => $turler]);
                break;

            case 'get-kesinti-turleri':
                $BordroParametre = new BordroParametreModel();
                $turler = $BordroParametre->getKesintiTurleri();
                echo json_encode(['status' => 'success', 'data' => $turler]);
                break;

            // Parametre sil (soft delete)
            case 'delete-parametre':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz parametre ID.');
                }

                $BordroParametre = new BordroParametreModel();

                // Soft delete - aktif = 0 yap
                if ($BordroParametre->updateParametre($id, ['aktif' => 0])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Parametre başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Parametre silinirken bir hata oluştu.');
                }
                break;

            // Genel Ayar güncelle
            case 'update-genel-ayar':
                $id = intval($_POST['id'] ?? 0);
                $parametre_kodu = trim($_POST['parametre_kodu'] ?? '');
                $parametre_adi = trim($_POST['parametre_adi'] ?? '');
                $deger = floatval(str_replace(['.', ','], ['', '.'], $_POST['deger'] ?? '0'));
                $gecerlilik_baslangic = $_POST['ayar_gecerlilik_baslangic'] ?? date('Y-m-d');
                $aciklama = trim($_POST['ayar_aciklama'] ?? '');

                if ($id <= 0) {
                    throw new Exception('Geçersiz ayar ID.');
                }

                $BordroParametre = new BordroParametreModel();

                $sql = $BordroParametre->getDb()->prepare("
                    UPDATE bordro_genel_ayarlar 
                    SET parametre_kodu = ?, parametre_adi = ?, deger = ?, 
                        gecerlilik_baslangic = ?, aciklama = ?
                    WHERE id = ?
                ");

                if ($sql->execute([$parametre_kodu, $parametre_adi, $deger, $gecerlilik_baslangic, $aciklama, $id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Ayar başarıyla güncellendi.'
                    ]);
                } else {
                    throw new Exception('Ayar güncellenirken bir hata oluştu.');
                }
                break;

            // Genel Ayar sil
            case 'delete-genel-ayar':
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz ayar ID.');
                }

                $BordroParametre = new BordroParametreModel();

                $sql = $BordroParametre->getDb()->prepare("
                    UPDATE bordro_genel_ayarlar SET aktif = 0 WHERE id = ?
                ");

                if ($sql->execute([$id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Ayar başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Ayar silinirken bir hata oluştu.');
                }
                break;

            // Genel Ayarları yeni döneme kopyala
            case 'copy-genel-ayarlar':
                $yeni_gecerlilik = $_POST['yeni_gecerlilik'] ?? '';
                $ayar_sec = $_POST['ayar_sec'] ?? [];
                $yeni_deger = $_POST['yeni_deger'] ?? [];
                $aciklama = trim($_POST['donem_aciklama'] ?? '');

                if (empty($yeni_gecerlilik)) {
                    throw new Exception('Yeni geçerlilik tarihi zorunludur.');
                }

                if (empty($ayar_sec)) {
                    throw new Exception('En az bir ayar seçmelisiniz.');
                }

                $BordroParametre = new BordroParametreModel();
                $db = $BordroParametre->getDb();

                // Mevcut ayarları getir
                $placeholders = implode(',', array_fill(0, count($ayar_sec), '?'));
                $sql = $db->prepare("SELECT * FROM bordro_genel_ayarlar WHERE id IN ($placeholders) AND aktif = 1");
                $sql->execute($ayar_sec);
                $mevcutAyarlar = $sql->fetchAll(PDO::FETCH_OBJ);

                $eklenen = 0;
                foreach ($mevcutAyarlar as $ayar) {
                    $yeniDeger = isset($yeni_deger[$ayar->id]) ? floatval($yeni_deger[$ayar->id]) : $ayar->deger;

                    $insertSql = $db->prepare("
                        INSERT INTO bordro_genel_ayarlar 
                        (parametre_kodu, parametre_adi, deger, gecerlilik_baslangic, aciklama, aktif)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");

                    if (
                        $insertSql->execute([
                            $ayar->parametre_kodu,
                            $ayar->parametre_adi,
                            $yeniDeger,
                            $yeni_gecerlilik,
                            $aciklama ?: $ayar->aciklama
                        ])
                    ) {
                        $eklenen++;
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => "$eklenen ayar yeni döneme kopyalandı."
                ]);
                break;

            // Vergi Dilimi Ekle
            case 'add-vergi-dilimi':
                $BordroParametre = new BordroParametreModel();

                $yil = intval($_POST['dilim_yili'] ?? date('Y'));
                $dilim_no = intval($_POST['dilim_no'] ?? 0);
                $alt_limit = floatval(str_replace(['.', ','], ['', '.'], $_POST['alt_limit'] ?? '0'));
                $ust_limit_raw = trim($_POST['ust_limit'] ?? '');
                $ust_limit = $ust_limit_raw !== '' ? floatval(str_replace(['.', ','], ['', '.'], $ust_limit_raw)) : null;
                $vergi_orani = floatval($_POST['vergi_orani'] ?? 0);
                $aciklama = trim($_POST['dilim_aciklama'] ?? '');

                if ($dilim_no <= 0 || $dilim_no > 10) {
                    throw new Exception('Dilim numarası 1-10 arasında olmalıdır.');
                }

                if ($vergi_orani < 0 || $vergi_orani > 100) {
                    throw new Exception('Vergi oranı 0-100 arasında olmalıdır.');
                }

                if ($BordroParametre->addVergiDilimi($yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Vergi dilimi başarıyla eklendi.'
                    ]);
                } else {
                    throw new Exception('Vergi dilimi eklenirken bir hata oluştu.');
                }
                break;

            // Vergi Dilimi Güncelle
            case 'update-vergi-dilimi':
                $BordroParametre = new BordroParametreModel();
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz dilim ID.');
                }

                $yil = intval($_POST['dilim_yili'] ?? date('Y'));
                $dilim_no = intval($_POST['dilim_no'] ?? 0);
                $alt_limit = floatval(str_replace(['.', ','], ['', '.'], $_POST['alt_limit'] ?? '0'));
                $ust_limit_raw = trim($_POST['ust_limit'] ?? '');
                $ust_limit = $ust_limit_raw !== '' ? floatval(str_replace(['.', ','], ['', '.'], $ust_limit_raw)) : null;
                $vergi_orani = floatval($_POST['vergi_orani'] ?? 0);
                $aciklama = trim($_POST['dilim_aciklama'] ?? '');

                $sql = $BordroParametre->getDb()->prepare("
                    UPDATE bordro_vergi_dilimleri 
                    SET yil = ?, dilim_no = ?, alt_limit = ?, ust_limit = ?, vergi_orani = ?, aciklama = ?
                    WHERE id = ?
                ");

                if ($sql->execute([$yil, $dilim_no, $alt_limit, $ust_limit, $vergi_orani, $aciklama, $id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Vergi dilimi başarıyla güncellendi.'
                    ]);
                } else {
                    throw new Exception('Vergi dilimi güncellenirken bir hata oluştu.');
                }
                break;

            // Vergi Dilimi Sil
            case 'delete-vergi-dilimi':
                $BordroParametre = new BordroParametreModel();
                $id = intval($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new Exception('Geçersiz dilim ID.');
                }

                $sql = $BordroParametre->getDb()->prepare("DELETE FROM bordro_vergi_dilimleri WHERE id = ?");

                if ($sql->execute([$id])) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Vergi dilimi başarıyla silindi.'
                    ]);
                } else {
                    throw new Exception('Vergi dilimi silinirken bir hata oluştu.');
                }
                break;

            // Dönem Sil (soft delete)
            case 'donem-sil':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                // Dönemin kapalı olup olmadığını kontrol et
                $donem = $BordroDonem->getDonemById($donem_id);
                if (!$donem) {
                    throw new Exception('Dönem bulunamadı.');
                }

                if ($donem->kapali_mi == 1) {
                    throw new Exception('Kapalı dönemler silinemez. Önce dönemi açmanız gerekir.');
                }

                // Soft delete uygula
                if ($BordroDonem->deleteDonem($donem_id)) {
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Dönem başarıyla silindi.'
                    ]);
                    unset($_SESSION['selectedDonemId']);
                } else {
                    throw new Exception('Dönem silinirken bir hata oluştu.');
                }
                break;

            // Sürekli kesinti ve ek ödemeleri döneme aktar
            case 'surekli-kayitlari-olustur':
                $donem_id = intval($_POST['donem_id'] ?? 0);

                if ($donem_id <= 0) {
                    throw new Exception('Geçersiz dönem.');
                }

                // Dönemdeki tüm personeller için sürekli kesinti/ek ödemeleri oluştur
                $sonuc = $BordroPersonel->olusturDonemSurekliKayitlar($donem_id);

                $mesaj = '';
                if ($sonuc['kesinti'] > 0 || $sonuc['ek_odeme'] > 0) {
                    $mesaj = "{$sonuc['kesinti']} kesinti ve {$sonuc['ek_odeme']} ek ödeme kaydı otomatik oluşturuldu.";
                } else {
                    $mesaj = 'Aktarılacak sürekli kesinti veya ek ödeme bulunamadı.';
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => $mesaj,
                    'data' => $sonuc
                ]);
                break;

            // Excel'den Gelir Yükle
            case 'gelir-ekle':
                try {
                    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                    if (file_exists($vendorAutoload)) {
                        require_once $vendorAutoload;
                    } else {
                        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
                    }

                    $donem_id = intval($_POST['donem_id'] ?? 0);
                    if ($donem_id <= 0) {
                        throw new Exception("Dönem seçilmelidir.");
                    }

                    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                        throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
                    }

                    $inputFileName = $_FILES['excel_file']['tmp_name'];
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (count($rows) < 3) {
                        throw new Exception("Excel dosyası boş veya veri içermiyor.");
                    }

                    // Parametreleri getir (etiket -> kod eşleştirmesi için)
                    $BordroParametre = new BordroParametreModel();
                    $parametreler = $BordroParametre->getGelirTurleri();
                    $paramMap = [];
                    foreach ($parametreler as $p) {
                        $paramMap[trim($p->etiket)] = $p->kod;
                    }

                    // Başlık satırından (Satır 2) kolonları eşleştir
                    $headers = $rows[1];
                    $colIndices = [];
                    foreach ($headers as $index => $header) {
                        $header = trim($header ?? '');
                        if (isset($paramMap[$header])) {
                            $colIndices[$paramMap[$header]] = $index;
                        }
                    }

                    $tcIndex = 1; // B kolonu (0-indexed: 1)
                    $Personel = new PersonelModel();
                    $eklenenSayisi = 0;

                    // Verileri işle (Satır 3'ten başla)
                    for ($i = 2; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $tcNo = trim($row[$tcIndex] ?? '');
                        if (empty($tcNo))
                            continue;

                        // Personeli bul
                        $personelData = $Personel->where('tc_kimlik_no', $tcNo);
                        if (empty($personelData))
                            continue;
                        $personel_id = $personelData[0]->id;

                        $rowHasData = false;
                        foreach ($colIndices as $kod => $index) {
                            $tutar = Helper::formattedMoneyToNumber($row[$index] ?? 0);
                            if ($tutar > 0) {
                                // Mevcut kaydı sil (aynı dönem ve aynı tür için mükerrer olmasın)
                                $BordroPersonel->getDb()->prepare("
                                    UPDATE personel_ek_odemeler 
                                    SET silinme_tarihi = NOW() 
                                    WHERE personel_id = ? AND donem_id = ? AND tur = ? AND silinme_tarihi IS NULL
                                ")->execute([$personel_id, $donem_id, $kod]);

                                // Yeni kaydı ekle
                                if ($BordroPersonel->addEkOdeme($personel_id, $donem_id, "Excel'den yüklendi", $tutar, $kod)) {
                                    $rowHasData = true;
                                }
                            }
                        }

                        if ($rowHasData) {
                            // Maaşı tekrar hesapla
                            $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                            $eklenenSayisi++;
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => "$eklenenSayisi personelin gelir kayıtları yüklendi ve maaşları hesaplandı."
                    ]);

                } catch (Exception $e) {
                    throw $e;
                }
                break;

            // Excel'den Kesinti Yükle
            case 'kesinti-ekle':
                try {
                    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                    if (file_exists($vendorAutoload)) {
                        require_once $vendorAutoload;
                    } else {
                        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
                    }

                    $donem_id = intval($_POST['donem_id'] ?? 0);
                    if ($donem_id <= 0) {
                        throw new Exception("Dönem seçilmelidir.");
                    }

                    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                        throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
                    }

                    $inputFileName = $_FILES['excel_file']['tmp_name'];
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (count($rows) < 3) {
                        throw new Exception("Excel dosyası boş veya veri içermiyor.");
                    }

                    // Parametreleri getir (etiket -> kod eşleştirmesi için)
                    $BordroParametre = new BordroParametreModel();
                    $parametreler = $BordroParametre->getKesintiTurleri();
                    $paramMap = [];
                    foreach ($parametreler as $p) {
                        $paramMap[trim($p->etiket)] = $p->kod;
                    }

                    // Başlık satırından (Satır 2) kolonları eşleştir
                    $headers = $rows[1];
                    $colIndices = [];
                    foreach ($headers as $index => $header) {
                        $header = trim($header ?? '');
                        if (isset($paramMap[$header])) {
                            $colIndices[$paramMap[$header]] = $index;
                        }
                    }

                    $tcIndex = 1; // B kolonu
                    $Personel = new PersonelModel();
                    $eklenenSayisi = 0;

                    // Verileri işle (Satır 3'ten başla)
                    for ($i = 2; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $tcNo = trim($row[$tcIndex] ?? '');
                        if (empty($tcNo))
                            continue;

                        // Personeli bul
                        $personelData = $Personel->where('tc_kimlik_no', $tcNo);
                        if (empty($personelData))
                            continue;
                        $personel_id = $personelData[0]->id;

                        $rowHasData = false;
                        foreach ($colIndices as $kod => $index) {
                            $tutar = Helper::formattedMoneyToNumber($row[$index] ?? 0);
                            if ($tutar > 0) {
                                // Mevcut kaydı sil
                                $BordroPersonel->getDb()->prepare("
                                    UPDATE personel_kesintileri 
                                    SET silinme_tarihi = NOW() 
                                    WHERE personel_id = ? AND donem_id = ? AND tur = ? AND silinme_tarihi IS NULL
                                ")->execute([$personel_id, $donem_id, $kod]);

                                // Yeni kaydı ekle
                                if ($BordroPersonel->addKesinti($personel_id, $donem_id, "Excel'den yüklendi", $tutar, $kod, 'onaylandi')) {
                                    $rowHasData = true;
                                }
                            }
                        }

                        if ($rowHasData) {
                            // Maaşı tekrar hesapla
                            $BordroPersonel->hesaplaMaasByPersonelDonem($personel_id, $donem_id);
                            $eklenenSayisi++;
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => "$eklenenSayisi personelin kesinti kayıtları yüklendi ve maaşları hesaplandı."
                    ]);

                } catch (Exception $e) {
                    throw $e;
                }
                break;

            // Excel'den Ödeme Dağıtımı Yükle
            case 'odeme-dagit-excel':
                try {
                    $vendorAutoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
                    if (file_exists($vendorAutoload)) {
                        require_once $vendorAutoload;
                    } else {
                        throw new Exception("Excel kütüphanesi (vendor/autoload.php) bulunamadı.");
                    }

                    $donem_id = intval($_POST['donem_id'] ?? 0);
                    if ($donem_id <= 0) {
                        throw new Exception("Dönem seçilmelidir.");
                    }

                    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] != 0) {
                        throw new Exception("Dosya yüklenemedi veya dosya seçilmedi.");
                    }

                    $inputFileName = $_FILES['excel_file']['tmp_name'];
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
                    $sheet = $spreadsheet->getActiveSheet();
                    $rows = $sheet->toArray();

                    if (count($rows) < 3) {
                        throw new Exception("Excel dosyası boş veya veri içermiyor.");
                    }

                    // Kolon eşleştirmeleri (Sabit kolonlar)
                    // A: Sıra, B: TC, C: Ad Soyad, D: Net Maaş, E: Banka, F: Sodexo, G: Diğer, H: Elden
                    $tcIndex = 1;
                    $bankaIndex = 4;
                    $sodexoIndex = 5;
                    $digerIndex = 6;

                    $Personel = new PersonelModel();
                    $guncellenenSayisi = 0;

                    // Verileri işle (Satır 3'ten başla)
                    for ($i = 2; $i < count($rows); $i++) {
                        $row = $rows[$i];
                        $tcNo = trim($row[$tcIndex] ?? '');
                        if (empty($tcNo))
                            continue;

                        // Personeli bul
                        $personelData = $Personel->where('tc_kimlik_no', $tcNo);
                        if (empty($personelData))
                            continue;
                        $personel_id = $personelData[0]->id;

                        // Bordro kaydını bul
                        $stmt = $BordroPersonel->getDb()->prepare("SELECT * FROM bordro_personel WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL");
                        $stmt->execute([$personel_id, $donem_id]);
                        $bp = $stmt->fetchAll(PDO::FETCH_OBJ);

                        if (empty($bp))
                            continue;
                        $bp_id = $bp[0]->id;

                        $banka = Helper::formattedMoneyToNumber($row[$bankaIndex] ?? 0);
                        $sodexo = Helper::formattedMoneyToNumber($row[$sodexoIndex] ?? 0);
                        $diger = Helper::formattedMoneyToNumber($row[$digerIndex] ?? 0);

                        $elden = Helper::formattedMoneyToNumber($row[7] ?? 0);

                        // Güncelle
                        $updateData = [
                            'banka_odemesi' => $banka,
                            'sodexo_odemesi' => $sodexo,
                            'diger_odeme' => $diger,
                            'elden_odeme' => $elden,
                            'sodexo_manuel' => 1,
                            'dagitim_manuel' => 1
                        ];

                        if ($BordroPersonel->updateBordro($bp_id, $updateData)) {
                            $guncellenenSayisi++;
                        }
                    }

                    echo json_encode([
                        'status' => 'success',
                        'message' => "$guncellenenSayisi personelin ödeme dağıtımları güncellendi."
                    ]);

                } catch (Exception $e) {
                    throw $e;
                }
                break;

            default:
                throw new Exception('Geçersiz işlem.');


        }

    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }

} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Geçersiz istek metodu.'
    ]);
}
