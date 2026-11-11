<?php

require_once __DIR__ . '/_bootstrap.php';

use App\Helper\Security;
use App\Model\DestekBiletModel;

$personel = mobile_require_auth();
$personelId = (int) $personel->id;

$input = mobile_input();
$action = (string) ($input['action'] ?? 'list');

$model = new DestekBiletModel();

$sahipTalep = static function (DestekBiletModel $model, mixed $token, int $personelId): object {
    $id = is_string($token) && $token !== '' && strlen($token) <= 2048 ? Security::decrypt($token) : null;
    if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0) {
        mobile_fail('Geçersiz talep.', 422);
    }

    $ticket = $model->getTicketDetails((int) $id);
    if (!$ticket || (int) $ticket->personel_id !== $personelId) {
        mobile_fail('Destek talebi bulunamadı.', 404);
    }

    return $ticket;
};

$biletOzeti = static fn(object $t): array => [
    'token' => Security::encrypt((int) $t->id),
    'ref_no' => (string) $t->ref_no,
    'konu' => (string) $t->konu,
    'kategori' => (string) ($t->kategori ?? 'Genel'),
    'oncelik' => (string) ($t->oncelik ?? 'orta'),
    'durum' => (string) $t->durum,
    'onay_durumu' => (string) ($t->onay_durumu ?? 'onaylandi'),
    'onay_notu' => (string) ($t->onay_notu ?? ''),
    'olusturma_tarihi' => (string) ($t->olusturma_tarihi ?? ''),
    'guncelleme_tarihi' => (string) ($t->guncelleme_tarihi ?? ''),
    'mesaj_sayisi' => (int) ($t->mesaj_sayisi ?? count((array) ($t->messages ?? []))),
];

$detayOlustur = static function (object $t) use ($model, $biletOzeti): array {
    $mesajlar = array_map(static fn($m): array => [
        'gonderen_tip' => (string) $m->gonderen_tip,
        'gonderen_adi' => $m->gonderen_tip === 'personel' ? 'Siz' : ((string) ($m->gonderen_adi ?: 'Destek Ekibi')),
        'mesaj' => (string) $m->mesaj,
        'tarih' => (string) ($m->olusturma_tarihi ?? ''),
        'ek_sayisi' => count((array) ($m->dosyalar ?? [])),
    ], (array) $t->messages);

    $kapali = ($t->durum ?? '') === 'kapali';
    $onayli = ($t->onay_durumu ?? 'onaylandi') === 'onaylandi';

    return $biletOzeti($t) + [
        'mesajlar' => $mesajlar,
        'yanitlayabilir' => $model->canRequesterReply((int) $t->id),
        'kapatilabilir' => !$kapali && $onayli,
    ];
};

try {
    switch ($action) {
        case 'detail': {
            $ticket = $sahipTalep($model, $input['token'] ?? null, $personelId);
            mobile_ok(['talep' => $detayOlustur($ticket)]);
            break;
        }

        case 'reply': {
            $ticket = $sahipTalep($model, $input['token'] ?? null, $personelId);
            $mesaj = trim(strip_tags((string) ($input['mesaj'] ?? '')));
            $id = (int) $ticket->id;

            if ($mesaj === '') {
                mobile_fail('Mesaj boş olamaz.', 422);
            }
            if (mb_strlen($mesaj) > 4000) {
                mobile_fail('Mesaj en fazla 4000 karakter olabilir.', 422);
            }
            if (($ticket->durum ?? '') === 'kapali') {
                mobile_fail('Bu talep kapatılmıştır. Yeni mesaj gönderemezsiniz.', 409);
            }
            if (($ticket->onay_durumu ?? 'onaylandi') !== 'onaylandi') {
                mobile_fail('Bu destek talebi henüz onaylanmadı. Onay sonrası mesajlaşabilirsiniz.', 409);
            }
            if (!$model->canRequesterReply($id)) {
                mobile_fail('Yeni mesaj göndermek için yönetici yanıtını beklemelisiniz.', 409);
            }

            $model->addMessage($id, 'personel', $personelId, $mesaj);
            mobile_ok(['message' => 'Mesajınız gönderildi.', 'talep' => $detayOlustur($model->getTicketDetails($id))]);
            break;
        }

        case 'close': {
            $ticket = $sahipTalep($model, $input['token'] ?? null, $personelId);
            $id = (int) $ticket->id;

            if (($ticket->durum ?? '') === 'kapali') {
                mobile_fail('Bu talep zaten kapalı.', 409);
            }
            if (($ticket->onay_durumu ?? 'onaylandi') !== 'onaylandi') {
                mobile_fail('Onaylanmamış talep kapatılamaz.', 409);
            }

            $model->updateStatus($id, 'kapali');
            mobile_ok(['message' => 'Talep kapatıldı.', 'talep' => $detayOlustur($model->getTicketDetails($id))]);
            break;
        }

        case 'list':
        default: {
            $talepler = array_map($biletOzeti, $model->getKendiBiletleri($personelId));
            $sayac = ['acik' => 0, 'yanitlanan' => 0, 'kapali' => 0];
            foreach ($talepler as $t) {
                if ($t['durum'] === 'kapali' || $t['onay_durumu'] === 'reddedildi') {
                    $sayac['kapali']++;
                } elseif ($t['durum'] === 'yanitlandi') {
                    $sayac['yanitlanan']++;
                } else {
                    $sayac['acik']++;
                }
            }
            mobile_ok(['talepler' => $talepler, 'sayac' => $sayac]);
        }
    }
} catch (\Throwable $e) {
    error_log('Mobil yardim.php hata: ' . $e->getMessage());
    mobile_fail('Destek işlemi tamamlanamadı. Lütfen yeniden deneyin.', 500);
}
