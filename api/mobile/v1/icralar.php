<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Model\PersonelIcralariModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;

$input = mobile_input();
$action = (string) ($input['action'] ?? 'list');

$durumMetin = [
    'bekliyor' => 'Bekliyor',
    'devam_ediyor' => 'Devam Ediyor',
    'fekki_geldi' => 'Fekki Geldi',
    'kesinti_bitti' => 'Kesinti Bitti',
    'bitti' => 'Bitti',
    'durduruldu' => 'Durduruldu',
];

try {
    $model = new PersonelIcralariModel();

    switch ($action) {
        case 'kesintiler': {
            $icraId = (int) ($input['icra_id'] ?? 0);
            $icra = $icraId > 0 ? $model->find($icraId) : null;
            if (!$icra || (int) $icra->personel_id !== $personelId || !empty($icra->silinme_tarihi)) {
                mobile_fail('İcra dosyası bulunamadı.', 404);
            }

            $kesintiler = array_map(static fn($k): array => [
                'id' => (int) $k->id,
                'donem_adi' => (string) ($k->donem_adi ?? ''),
                'tutar' => round((float) ($k->tutar ?? 0), 2),
                'aciklama' => (string) ($k->aciklama ?? ''),
                'tarih' => !empty($k->olusturma_tarihi) ? date('Y-m-d', strtotime($k->olusturma_tarihi)) : '',
            ], array_filter(
                $model->getIcraKesintileri($icraId),
                static fn($k): bool => ($k->durum ?? '') === 'onaylandi'
            ));

            mobile_ok(['kesintiler' => array_values($kesintiler)]);
            break;
        }

        case 'list':
        default: {
            $icralar = array_map(static function ($item) use ($durumMetin): array {
                $borc = (float) ($item->toplam_borc ?? 0);
                $kalan = max(0.0, (float) ($item->kalan_tutar ?? $borc));
                $durum = (string) ($item->durum ?? '');

                return [
                    'id' => (int) $item->id,
                    'dosya_no' => (string) ($item->dosya_no ?? ''),
                    'alacakli' => (string) ($item->alacakli ?? ''),
                    'icra_dairesi' => (string) ($item->icra_dairesi ?? ''),
                    'toplam_borc' => round($borc, 2),
                    'kalan_tutar' => round($kalan, 2),
                    'toplam_kesilen' => round((float) ($item->toplam_kesilen ?? 0), 2),
                    'kesinti_tipi' => (string) ($item->kesinti_tipi ?? 'tutar'),
                    'kesinti_orani' => (float) ($item->kesinti_orani ?? 0),
                    'aylik_kesinti_tutari' => round((float) ($item->aylik_kesinti_tutari ?? 0), 2),
                    'durum' => $durum,
                    'durum_text' => $durumMetin[$durum] ?? 'Devam Ediyor',
                    'baslangic' => (string) ($item->baslangic_tarihi ?? ''),
                    'bitis' => (string) ($item->bitis_tarihi ?? ''),
                ];
            }, $model->getPersonelIcralariWithKesintiler($personelId));

            mobile_ok(['icralar' => $icralar]);
        }
    }
} catch (\Throwable $e) {
    error_log('Mobil icralar.php hata: ' . $e->getMessage());
    mobile_fail('İcra bilgileri alınırken bir hata oluştu.', 500);
}
