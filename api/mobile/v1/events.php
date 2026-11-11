<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\PersonelModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));

$personelModel = new PersonelModel();
$db = $personelModel->getDb();

$sql = "SELECT id, baslik, icerik, resim, hedef_sayfa, tarih, etkinlik_tarihi, alici_tipi, alici_ids
        FROM duyurular
        WHERE silinme_tarihi IS NULL 
          AND durum = 'Yayında'
          AND (? = 0 OR firma_id = ? OR firma_id IS NULL OR firma_id = 0)
          AND (alici_tipi = 'toplu' OR alici_tipi IS NULL OR alici_tipi = '' OR FIND_IN_SET(?, alici_ids))
        ORDER BY id DESC";

$stmt = $db->prepare($sql);
$stmt->execute([$firmaId, $firmaId, $personelId]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$toplam = count($rows);
$yaklasan = 0;
$duyurularSayisi = 0;
$gecmisSayisi = 0;

$items = [];
$bugun = new DateTime();
$bugun->setTime(0, 0, 0);

foreach ($rows as $d) {
    $tarihRaw = $d['tarih'] ? date('Y-m-d H:i:s', strtotime($d['tarih'])) : null;
    $tarihText = $d['tarih'] ? date('d.m.Y', strtotime($d['tarih'])) : null;
    $etkinlikTarihiRaw = $d['etkinlik_tarihi'] ? date('Y-m-d H:i:s', strtotime($d['etkinlik_tarihi'])) : null;
    $etkinlikTarihiText = $d['etkinlik_tarihi'] ? date('d.m.Y', strtotime($d['etkinlik_tarihi'])) : null;

    $isEtkinlik = !empty($d['etkinlik_tarihi']);
    $kalanGun = null;
    $kalanGunText = null;
    $gecmis = false;

    if ($isEtkinlik) {
        $etkinlikDateTime = new DateTime($d['etkinlik_tarihi']);
        $etkinlikDateTime->setTime(0, 0, 0);

        if ($etkinlikDateTime >= $bugun) {
            $diff = $bugun->diff($etkinlikDateTime);
            $kalanGun = (int) $diff->days;
            $kalanGunText = $kalanGun === 0 ? 'BUGÜN' : sprintf('%02d GÜN KALDI', $kalanGun);
            $yaklasan++;
        } else {
            $gecmis = true;
            $gecmisSayisi++;
            $kalanGunText = 'SÜRESİ DOLDU';
        }
    } else {
        $duyurularSayisi++;
    }

    $rawContent = (string) ($d['icerik'] ?? '');
    $cleanContent = trim(strip_tags($rawContent));

    $items[] = [
        'id' => (int) $d['id'],
        'baslik' => (string) ($d['baslik'] ?? ''),
        'icerik' => $rawContent,
        'ozet_icerik' => $cleanContent,
        'resim_url' => mobile_asset_url($d['resim'] ?? null),
        'tarih' => $tarihText,
        'tarih_raw' => $tarihRaw,
        'etkinlik_tarihi' => $etkinlikTarihiText,
        'etkinlik_tarihi_raw' => $etkinlikTarihiRaw,
        'kalan_gun' => $kalanGun,
        'kalan_gun_text' => $kalanGunText,
        'gecmis' => $gecmis,
        'hedef_sayfa' => !empty($d['hedef_sayfa']) ? (string) $d['hedef_sayfa'] : null,
        'tip' => $isEtkinlik ? 'etkinlik' : 'duyuru',
    ];
}

mobile_ok([
    'stats' => [
        'toplam' => $toplam,
        'yaklasan' => $yaklasan,
        'duyurular' => $duyurularSayisi,
        'gecmis' => $gecmisSayisi,
    ],
    'etkinlikler' => $items,
]);
