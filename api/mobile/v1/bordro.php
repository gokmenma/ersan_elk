<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\BordroYayinGuvenlik;
use App\Model\BordroYayinModel;
use App\Service\BordroYayinIcerikService;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;
$firmaId = (int) ($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));

$input = mobile_input();
$action = (string) ($input['action'] ?? 'list');

$yuvarlamaMi = static fn(array $k): bool => str_contains(
    mb_strtolower((string) ($k['etiket'] ?? ''), 'UTF-8'),
    'yemek günlük yuvarlama farkı'
);

$gorunenKalemler = static function (array $kalemler) use ($yuvarlamaMi): array {
    $kalemler = array_map(static fn($k): array => ['etiket' => (string) ($k['etiket'] ?? ''), 'kurus' => (int) ($k['kurus'] ?? 0)], $kalemler);
    $yuvarlama = 0;
    $gorunen = [];
    foreach ($kalemler as $k) {
        if ($yuvarlamaMi($k)) {
            $yuvarlama += $k['kurus'];
        } else {
            $gorunen[] = $k;
        }
    }
    if ($yuvarlama !== 0) {
        $bulundu = false;
        foreach ($gorunen as &$k) {
            if (mb_strtolower($k['etiket'], 'UTF-8') === 'yemek yardımı') {
                $k['kurus'] += $yuvarlama;
                $bulundu = true;
                break;
            }
        }
        unset($k);
        if (!$bulundu) {
            $gorunen[] = ['etiket' => 'Yemek yardımı', 'kurus' => $yuvarlama];
        }
    }

    return $gorunen;
};

$ozetle = static function (array $d) use ($gorunenKalemler): array {
    $i = $d['icerik'] ?? [];
    $kalemler = $gorunenKalemler((array) ($i['kalemler'] ?? []));
    $pusulaMi = ($i['icerik_turu'] ?? '') === 'ucret_hesap_pusulasi';
    $indirim = $i['resmi_pusula']['pusula']['detay']['indirimler'] ?? [];

    $gelirler = array_values(array_filter($kalemler, static fn($k): bool => $k['kurus'] > 0));
    $giderler = [];
    foreach ((array) ($i['yasal_kesintiler'] ?? []) as $k) {
        $giderler[] = ['etiket' => (string) ($k['etiket'] ?? ''), 'kurus' => (int) ($k['kurus'] ?? 0)];
    }
    foreach ($kalemler as $k) {
        if ($k['kurus'] < 0) {
            $giderler[] = ['etiket' => $k['etiket'], 'kurus' => abs($k['kurus'])];
        }
    }

    $talepler = array_map(static fn($t): array => [
        'token' => (string) $t['token'],
        'konu' => (string) ($t['konu'] ?? 'diger'),
        'mesaj' => (string) ($t['mesaj'] ?? ''),
        'durum' => (string) ($t['durum'] ?? ''),
        'tarih' => (string) ($t['tarih'] ?? ''),
        'yanitlar' => array_map(static fn($y): array => [
            'mesaj' => (string) ($y['mesaj'] ?? ''),
            'tarih' => (string) ($y['tarih'] ?? ''),
            'kullanici' => (string) ($y['kullanici'] ?? 'Yetkili'),
        ], (array) ($t['yanitlar'] ?? [])),
        'ekler' => array_map(static fn($e): array => [
            'dosya_adi' => (string) ($e['dosya_adi'] ?? ''),
        ], (array) ($t['ekler'] ?? [])),
    ], (array) ($d['talepler'] ?? []));

    return [
        'token' => (string) $d['token'],
        'pusula_no' => (string) ($d['pusula_no'] ?? ''),
        'surum' => $d['surum'],
        'durum' => (string) $d['durum'],
        'islem_durumu' => (string) $d['islem_durumu'],
        'yayin_tarihi' => (string) ($d['yayin_tarihi'] ?? ''),
        'beyan_tarihi' => $d['beyan_tarihi'] ?: null,
        'beyan_metni' => (string) $d['beyan_metni'],
        'beyan_metin_surumu' => (int) $d['beyan_metin_surumu'],
        'beyan_alinir' => in_array($d['durum'], ['yayinda', 'test'], true),
        'icerik' => [
            'donem' => (string) ($i['donem'] ?? ''),
            'baslangic' => (string) ($i['baslangic'] ?? ''),
            'bitis' => (string) ($i['bitis'] ?? ''),
            'personel' => (string) ($i['personel'] ?? ''),
            'departman' => (string) ($i['departman'] ?? ''),
            'gorev' => (string) ($i['gorev'] ?? ''),
            'calisma_gun' => (float) ($i['calisma_gun'] ?? 0),
            'fiili_gun' => (float) ($i['fiili_gun'] ?? 0),
            'pusula_detayi' => $pusulaMi,
            'banka_net_kurus' => (int) ($i['banka_net_kurus'] ?? 0),
            'kalemler' => $kalemler,
            'gelirler' => $gelirler,
            'kesintiler' => $giderler,
            'ozet' => $pusulaMi ? [
                'kazanc_toplami_kurus' => (int) ($i['kazanc_toplami_kurus'] ?? 0),
                'yasal_kesinti_toplami_kurus' => (int) ($i['yasal_kesinti_toplami_kurus'] ?? 0),
                'ozel_kesinti_toplami_kurus' => (int) ($i['ozel_kesinti_toplami_kurus'] ?? 0),
                'toplam_kesinti_kurus' => (int) ($i['toplam_kesinti_kurus'] ?? 0),
                'asgari_ucret_istisna_gv_kurus' => (int) ($i['vergi_indirimleri']['asgari_ucret_istisna_gv_kurus']
                    ?? round((float) ($indirim['asgari_ucret_istisna_gv'] ?? 0) * 100)),
                'asgari_ucret_istisna_dv_kurus' => (int) ($i['vergi_indirimleri']['asgari_ucret_istisna_dv_kurus']
                    ?? round((float) ($indirim['asgari_ucret_istisna_dv'] ?? 0) * 100)),
            ] : null,
            'matrahlar' => $pusulaMi ? [
                'sgk_matrahi_kurus' => (int) ($i['resmi_matrahlar']['sgk_matrahi_kurus'] ?? 0),
                'gelir_vergisi_matrahi_kurus' => (int) ($i['resmi_matrahlar']['gelir_vergisi_matrahi_kurus'] ?? 0),
                'onceki_kumulatif_kurus' => (int) ($i['resmi_matrahlar']['onceki_kumulatif_kurus'] ?? 0),
                'yeni_kumulatif_kurus' => (int) ($i['resmi_matrahlar']['yeni_kumulatif_kurus'] ?? 0),
            ] : null,
            'gunler' => $pusulaMi ? array_map(
                static fn($v): float => (float) $v,
                (array) ($i['calisma_ve_izin_gunleri'] ?? [])
            ) : null,
        ],
        'talepler' => $talepler,
    ];
};

try {
    $model = new BordroYayinModel();

    switch ($action) {
        case 'detail':
        case 'view': {
            $id = BordroYayinGuvenlik::id($input['token'] ?? null);
            $detay = $model->detay($firmaId, $id, $personelId);
            try {
                $model->personelIslem($firmaId, $id, $personelId, 'goruntule');
                $detay = $model->detay($firmaId, $id, $personelId);
            } catch (\DomainException $e) {
                error_log('Mobil bordro görüntüleme kaydı: ' . $e->getMessage());
            }
            mobile_ok(['bordro' => $ozetle($detay)]);
            break;
        }

        case 'beyan': {
            $id = BordroYayinGuvenlik::id($input['token'] ?? null);
            if ((int) ($input['beyan_metin_surumu'] ?? 0) !== BordroYayinIcerikService::METIN_SURUMU) {
                throw new \DomainException('Beyan metni güncellendi. Dökümü yeniden açın.');
            }
            if (($input['okudum'] ?? false) !== true && ($input['okudum'] ?? '') !== '1') {
                throw new \DomainException('Okuma beyanı kutusunu işaretleyin.');
            }
            $model->personelIslem($firmaId, $id, $personelId, 'beyan', '', [
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => 'ER-SAN Mobil ' . (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
                'session_hash' => hash('sha256', mobile_bearer_token()),
            ]);
            mobile_ok(['message' => 'Ücret pusulası onayınız kaydedildi.', 'bordro' => $ozetle($model->detay($firmaId, $id, $personelId))]);
            break;
        }

        case 'talep': {
            $id = BordroYayinGuvenlik::id($input['token'] ?? null);
            $model->personelIslem($firmaId, $id, $personelId, 'talep', (string) ($input['mesaj'] ?? ''), [
                'konu' => (string) ($input['konu'] ?? 'diger'),
            ]);
            try {
                (new \App\Model\BildirimModel())->broadcastByPermissionForFirm(
                    $firmaId,
                    'bordro/list',
                    'Yeni ücret pusulası itirazı',
                    (string) $personel->adi_soyadi . ' ücret pusulası için itiraz bildirdi.',
                    'index.php?p=bordro/list',
                    'message-square-error',
                    'danger'
                );
            } catch (\Throwable $e) {
                error_log('Mobil bordro itiraz yönetici bildirimi: ' . $e->getMessage());
            }
            mobile_ok(['message' => 'İtirazınız alınmıştır.', 'bordro' => $ozetle($model->detay($firmaId, $id, $personelId))]);
            break;
        }

        case 'list':
        default: {
            $liste = array_map(static fn($r): array => [
                'token' => (string) $r['token'],
                'pusula_no' => (string) ($r['pusula_no'] ?? ''),
                'donem' => (string) $r['donem'],
                'baslangic' => (string) ($r['baslangic'] ?? ''),
                'surum' => $r['surum'],
                'durum' => (string) $r['durum'],
                'islem_durumu' => (string) $r['islem_durumu'],
                'yayin_tarihi' => (string) ($r['yayin_tarihi'] ?? ''),
                'beyan_tarihi' => $r['beyan_tarihi'] ?: null,
                'banka_net_kurus' => (int) $r['banka_net_kurus'],
            ], $model->personelListe($firmaId, $personelId));

            mobile_ok(['bordrolar' => $liste]);
        }
    }
} catch (\DomainException $e) {
    mobile_fail($e->getMessage(), 422);
} catch (\Throwable $e) {
    error_log('Mobil bordro.php hata: ' . $e->getMessage());
    mobile_fail('Bordro işlemi tamamlanamadı. Lütfen yeniden deneyin.', 500);
}
