<?php

namespace App\Service;

use App\Helper\EmailTemplateHelper;
use App\Helper\Security;
use App\Model\BildirimModel;
use App\Model\UserModel;
use App\Model\UserNotificationPreferenceModel;

final class EvrakOnayBildirimService
{
    private BildirimModel $bildirim;
    private UserModel $users;

    public function __construct()
    {
        $this->bildirim = new BildirimModel();
        $this->users = new UserModel();
    }

    public function imzaSirasiGeldi(object $evrak, int $signerId, int $sira, int $toplam): void
    {
        $baslik = 'İmzanızı Bekleyen Evrak';
        $ozet = 'Giden evrak elektronik imza onayınıza sunuldu' . ($toplam > 1 ? ' (' . $sira . '. imza)' : '') . '.';
        $this->gonder($signerId, $evrak, $baslik, $ozet, 'edit-3', 'warning', 'Evrakı İncele ve İmzala');
    }

    public function onayTamamlandi(object $evrak): void
    {
        $this->gonder(
            (int) ($evrak->olusturan_kullanici_id ?? 0),
            $evrak,
            'Evrak E-İmza ile Onaylandı',
            'Hazırladığınız giden evrakın tüm imzaları tamamlandı ve evrak elektronik imzalı hâle geldi.',
            'check-circle',
            'success',
            'Evrakı Görüntüle'
        );
    }

    public function evrakIadeEdildi(object $evrak, string $iadeEden, string $gerekce): void
    {
        $this->gonder(
            (int) ($evrak->olusturan_kullanici_id ?? 0),
            $evrak,
            'Evrak Düzeltilmek Üzere İade Edildi',
            $iadeEden . ' evrakı imzalamadan iade etti. Gerekçe: ' . $gerekce,
            'corner-up-left',
            'danger',
            'Evrakı Düzelt'
        );
    }

    private function gonder(int $userId, object $evrak, string $baslik, string $ozet, string $ikon, string $renk, string $butonMetni): void
    {
        if ($userId <= 0) {
            return;
        }

        $link = 'index.php?p=evrak-takip/giden-evrak&id=' . Security::encrypt((int) $evrak->id);
        $konu = trim((string) ($evrak->konu ?? '')) ?: 'Giden Evrak';
        $mesaj = $ozet . ' (Konu: ' . $konu . ', Sayı: ' . (string) ($evrak->evrak_no ?? '-') . ')';

        try {
            $this->bildirim->createNotification(
                $userId,
                $baslik,
                $mesaj,
                $link,
                $ikon,
                $renk,
                UserNotificationPreferenceModel::TYPE_DOCUMENT
            );
        } catch (\Throwable $e) {
            error_log('Evrak onay bildirimi oluşturulamadı: ' . $e->getMessage());
        }

        $user = $this->users->find($userId);
        $email = trim((string) ($user->email_adresi ?? ''));
        if ($email === '' || ($user->durum ?? '') !== 'Aktif') {
            return;
        }

        $escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $satir = static fn(string $etiket, string $deger): string =>
            "<tr><td style='padding: 8px 0; color: #64748B; font-size: 13px; width: 35%; border-bottom: 1px solid #EDF2F7;'>{$etiket}</td>"
            . "<td style='padding: 8px 0; color: #0F172A; font-weight: 600; font-size: 14px; border-bottom: 1px solid #EDF2F7;'>{$deger}</td></tr>";

        $icerik = "<p style='color: #020617; font-size: 16px; margin-bottom: 24px;'>Merhaba <b>" . $escape($user->adi_soyadi ?? '') . "</b>,</p>"
            . "<p style='color: #475569; margin-bottom: 24px;'>" . $escape($ozet) . "</p>"
            . "<div style='background-color: #F8FAFC; border: 1px solid #E2E8F0; border-radius: 8px; padding: 24px; margin-bottom: 24px;'>"
            . "<table style='width: 100%; border-collapse: collapse;'>"
            . $satir('KONU', $escape($konu))
            . $satir('SAYI', $escape($evrak->evrak_no ?? '-'))
            . $satir('TARİH', !empty($evrak->tarih) ? date('d.m.Y', strtotime((string) $evrak->tarih)) : '-')
            . $satir('MUHATAP', $escape($evrak->kurum_adi ?? '-'))
            . '</table></div>';

        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $butonUrl = $host !== '' ? 'https://' . $host . '/' . $link : null;

        try {
            $html = EmailTemplateHelper::getTemplate($baslik, $icerik, $butonUrl ? $butonMetni : null, $butonUrl);
            MailGonderService::gonder([$email], $baslik . ': ' . $konu, $html);
        } catch (\Throwable $e) {
            error_log('Evrak onay maili gönderilemedi: ' . $e->getMessage());
        }
    }
}
