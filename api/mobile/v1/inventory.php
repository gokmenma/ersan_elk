<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\Security;
use App\Model\AracZimmetModel;
use App\Model\DemirbasHareketModel;
use App\Model\DemirbasZimmetModel;
use App\Model\TalepModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));

$input = mobile_input();
$action = (string) ($input['action'] ?? 'get_zimmetler');

try {
    switch ($action) {
        case 'get_movements': {
            $zimmetId = (int) ($input['zimmet_id'] ?? 0);
            $type = (string) ($input['type'] ?? 'demirbas');

            if ($zimmetId <= 0) {
                mobile_fail('Geçersiz zimmet ID.');
            }

            if ($type === 'arac') {
                $AracZimmetModel = new AracZimmetModel();
                $zimmet = $AracZimmetModel->find($zimmetId);

                if (!$zimmet || (int) $zimmet->personel_id !== $personelId) {
                    mobile_fail('Yetkisiz erişim veya araç zimmeti bulunamadı.');
                }

                $movements = [];
                $movements[] = [
                    'id' => 'z_' . $zimmet->id,
                    'tip' => 'zimmet',
                    'tip_text' => 'Zimmet Teslimi',
                    'miktar' => 1,
                    'tarih' => $zimmet->zimmet_tarihi ? date('d.m.Y', strtotime($zimmet->zimmet_tarihi)) : '-',
                    'aciklama' => 'Araç zimmet teslimi yapıldı. Başlangıç KM: ' . ($zimmet->baslangic_km ?? '-'),
                    'islem_yapan' => 'Sistem Yöneticisi',
                ];

                if ($zimmet->durum === 'iade_edildi' && $zimmet->iade_tarihi) {
                    $movements[] = [
                        'id' => 'i_' . $zimmet->id,
                        'tip' => 'iade',
                        'tip_text' => 'İade Edildi',
                        'miktar' => 1,
                        'tarih' => date('d.m.Y', strtotime($zimmet->iade_tarihi)),
                        'aciklama' => 'Araç iade alındı. İade KM: ' . ($zimmet->iade_km ?? '-'),
                        'islem_yapan' => 'Sistem Yöneticisi',
                    ];
                }

                mobile_ok(['movements' => $movements]);
            } else {
                $ZimmetModel = new DemirbasZimmetModel();
                $zimmet = $ZimmetModel->find($zimmetId);

                if (!$zimmet || (int) $zimmet->personel_id !== $personelId) {
                    mobile_fail('Yetkisiz erişim veya demirbaş zimmeti bulunamadı.');
                }

                $HareketModel = new DemirbasHareketModel();
                $rawHareketler = $HareketModel->getZimmetHareketleri($zimmetId);

                $tipMap = [
                    'zimmet' => 'Zimmet Teslimi',
                    'iade' => 'İade',
                    'sarf' => 'Sarf',
                    'kayip' => 'Kayıp',
                    'servis' => 'Servise Gönderim',
                    'bakim' => 'Bakım',
                ];

                $movements = array_map(function ($h) use ($tipMap) {
                    return [
                        'id' => (string) $h->id,
                        'tip' => (string) $h->hareket_tipi,
                        'tip_text' => $tipMap[$h->hareket_tipi] ?? ucfirst((string) $h->hareket_tipi),
                        'miktar' => (int) ($h->miktar ?? 1),
                        'tarih' => $h->tarih ? date('d.m.Y', strtotime($h->tarih)) : '-',
                        'aciklama' => (string) ($h->aciklama ?? ''),
                        'islem_yapan' => (string) ($h->islem_yapan_adi ?? 'Yönetici'),
                    ];
                }, $rawHareketler);

                mobile_ok(['movements' => $movements]);
            }
            break;
        }

        case 'create_request': {
            $assetId = (int) ($input['asset_id'] ?? 0);
            $assetType = (string) ($input['asset_type'] ?? 'demirbas');
            $assetName = trim((string) ($input['asset_name'] ?? ''));
            $kategori = trim((string) ($input['kategori'] ?? 'ariza'));
            $aciklama = trim((string) ($input['aciklama'] ?? ''));

            if (empty($aciklama)) {
                mobile_fail('Lütfen talep açıklamasını belirtin.');
            }

            $kategoriTextMap = [
                'ariza' => 'Arıza Bildirimi',
                'servis' => 'Servis Talebi',
                'bakim' => 'Bakım Talebi',
                'diger' => 'Zimmet Talebi',
            ];

            $baslik = ($kategoriTextMap[$kategori] ?? 'Zimmet Talebi') . ': ' . ($assetName ?: 'Demirbaş');
            $tamAciklama = "Zimmet: {$assetName} (Tür: {$assetType}, ID: {$assetId})\nTalep Türü: " . ($kategoriTextMap[$kategori] ?? $kategori) . "\n\nAçıklama:\n" . $aciklama;

            $TalepModel = new TalepModel();
            $refNo = $TalepModel->generateRefNo();

            $insertData = [
                'personel_id' => $personelId,
                'ref_no' => $refNo,
                'kategori' => 'ariza_onarim',
                'oncelik' => $kategori === 'ariza' ? 'yuksek' : 'orta',
                'konum' => 'Zimmetli Ekipman (' . ($assetName ?: 'Saha') . ')',
                'aciklama' => $tamAciklama,
                'durum' => 'yeni',
            ];

            $newId = $TalepModel->add($insertData);
            if (!$newId) {
                mobile_fail('Talep oluşturulamadı, lütfen tekrar deneyin.');
            }

            mobile_ok([
                'message' => 'Talebiniz başarıyla iletildi.',
                'ref_no' => $refNo,
                'id' => (int) $newId,
            ]);
            break;
        }

        case 'get_zimmetler':
        default: {
            $ZimmetModel = new DemirbasZimmetModel();
            $rawZimmetler = $ZimmetModel->getByPersonel($personelId);

            $AracZimmetModel = new AracZimmetModel();
            $rawAracZimmetler = $AracZimmetModel->getByPersonel($personelId);

            $durumMap = [
                'teslim' => ['text' => 'Zimmetli', 'tone' => 'amber'],
                'iade' => ['text' => 'İade Edildi', 'tone' => 'emerald'],
                'kayip' => ['text' => 'Kayıp', 'tone' => 'rose'],
                'arizali' => ['text' => 'Arızalı', 'tone' => 'slate'],
            ];

            $items = [];

            // 1. Demirbaş Zimmetleri
            foreach ($rawZimmetler as $item) {
                $durum = $durumMap[$item->durum] ?? ['text' => (string) $item->durum, 'tone' => 'primary'];
                $items[] = [
                    'id' => (int) $item->id,
                    'type' => 'demirbas',
                    'encrypted_id' => Security::encrypt($item->id),
                    'demirbas_adi' => (string) ($item->demirbas_adi ?? 'Demirbaş'),
                    'demirbas_no' => (string) ($item->demirbas_no ?? '-'),
                    'kategori' => (string) ($item->kategori_adi ?? 'Genel'),
                    'marka_model' => trim(((string) ($item->marka ?? '')) . ' ' . ((string) ($item->model ?? ''))),
                    'seri_no' => (string) ($item->seri_no ?? ''),
                    'miktar' => (int) ($item->teslim_miktar ?? 1),
                    'teslim_tarihi' => $item->teslim_tarihi ? date('d.m.Y', strtotime($item->teslim_tarihi)) : '-',
                    'durum' => (string) ($item->durum ?? 'teslim'),
                    'durum_text' => $durum['text'],
                    'durum_tone' => $durum['tone'],
                    'aciklama' => (string) ($item->aciklama ?? ''),
                ];
            }

            // 2. Araç Zimmetleri
            foreach ($rawAracZimmetler as $item) {
                $isAktif = ($item->durum === 'aktif');
                $items[] = [
                    'id' => (int) $item->id,
                    'type' => 'arac',
                    'encrypted_id' => Security::encrypt($item->id),
                    'demirbas_adi' => (string) ($item->plaka ?? 'Araç'),
                    'demirbas_no' => 'ARAÇ',
                    'kategori' => 'Taşıt',
                    'marka_model' => trim(((string) ($item->marka ?? '')) . ' ' . ((string) ($item->model ?? ''))),
                    'seri_no' => (string) ($item->plaka ?? ''),
                    'miktar' => 1,
                    'teslim_tarihi' => $item->zimmet_tarihi ? date('d.m.Y', strtotime($item->zimmet_tarihi)) : '-',
                    'durum' => $isAktif ? 'teslim' : 'iade',
                    'durum_text' => $isAktif ? 'Zimmetli' : 'İade Edildi',
                    'durum_tone' => $isAktif ? 'amber' : 'emerald',
                    'aciklama' => 'Zimmetli şirket aracı',
                ];
            }

            // Tarihe göre sırala
            usort($items, function ($a, $b) {
                return strtotime($b['teslim_tarihi']) - strtotime($a['teslim_tarihi']);
            });

            // Kategori istatistikleri (Aktif/Teslim edilmiş olanlar üzerinden)
            $aktifler = array_filter($items, fn($z) => $z['durum'] === 'teslim');

            $araclar = array_filter($aktifler, fn($z) => $z['type'] === 'arac');
            $aracSayisi = count($araclar);

            $techTerms = ['telefon', 'tablet', 'yazıcı', 'yazici', 'bilgisayar', 'laptop', 'ekran', 'monitör', 'terminal', 'el terminali'];
            $techItems = array_filter($aktifler, function ($z) use ($techTerms) {
                if ($z['type'] !== 'demirbas') return false;
                $kat = mb_strtolower($z['kategori']);
                $ad = mb_strtolower($z['demirbas_adi']);
                foreach ($techTerms as $term) {
                    if (strpos($kat, $term) !== false || strpos($ad, $term) !== false) return true;
                }
                return false;
            });
            $techSayisi = count($techItems);

            $sayaclar = array_filter($aktifler, function ($z) {
                $kat = mb_strtolower($z['kategori']);
                return strpos($kat, 'sayaç') !== false || strpos($kat, 'sayac') !== false;
            });
            $sayacSayisi = count($sayaclar);

            $sayacSifir = 0;
            $sayacHurda = 0;
            foreach ($sayaclar as $s) {
                $ad = mb_strtolower($s['demirbas_adi']);
                $qty = (int) $s['miktar'];
                if (strpos($ad, 'hurda') !== false) {
                    $sayacHurda += $qty;
                } else {
                    $sayacSifir += $qty;
                }
            }

            $aparatlar = array_filter($aktifler, function ($z) {
                $kat = mb_strtolower($z['kategori']);
                return strpos($kat, 'aparat') !== false;
            });
            $aparatTotalQuantity = 0;
            $aparatDetail = [];
            foreach ($aparatlar as $ap) {
                $qty = (int) $ap['miktar'];
                $aparatTotalQuantity += $qty;
                $aparatDetail[] = [
                    'id' => $ap['id'],
                    'demirbas_adi' => $ap['demirbas_adi'],
                    'marka_model' => $ap['marka_model'] ?: 'Model Belirtilmemiş',
                    'miktar' => $qty,
                ];
            }

            $stats = [
                'toplam_zimmet' => count($items),
                'toplam_aktif' => count($aktifler),
                'toplam_iade' => count($items) - count($aktifler),
                'arac_sayisi' => $aracSayisi,
                'tech_sayisi' => $techSayisi,
                'sayac_sayisi' => $sayacSayisi,
                'sayac_sifir' => $sayacSifir,
                'sayac_hurda' => $sayacHurda,
                'aparat_sayisi' => $aparatTotalQuantity,
                'aparat_detay' => $aparatDetail,
            ];

            mobile_ok([
                'stats' => $stats,
                'zimmetler' => $items,
            ]);
            break;
        }
    }
} catch (\Throwable $e) {
    error_log('Mobil inventory.php hata: ' . $e->getMessage());
    mobile_fail('Zimmet bilgileri alınırken hata oluştu: ' . $e->getMessage(), 500);
}
