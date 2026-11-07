<?php

namespace App\Service;

use App\Helper\Security;
use App\Model\IkBasvuruModel;
use App\Model\IkIlanModel;
use App\Model\PersonelKarsilamaModel;
use App\Model\SystemLogModel;
use DateTime;
use DomainException;
use RuntimeException;
use Throwable;

class IkBasvuruKabulService
{
    private const SAATLIK_DENEME_SINIRI = 20;

    private string $kokDizin;

    public function __construct()
    {
        $this->kokDizin = dirname(__DIR__, 2);
    }

    public function kaydet(array $post, array $files, string $ip, ?int $firmaKisiti = null): array
    {
        $kayitliDosya = null;
        $kayitliFoto = null;
        $firmaId = 0;
        $basvuruId = 0;

        try {
            $Ilan = new IkIlanModel();
            $Basvuru = new IkBasvuruModel();

            $ilan = $Ilan->tokenIleBul(strtolower(trim((string) ($post['i'] ?? ''))));
            if (!$ilan || ($firmaKisiti !== null && (int) $ilan->firma_id !== $firmaKisiti)) {
                throw new DomainException('Başvuru bağlantısı geçersiz veya kapatılmış.', 404);
            }
            $ilanId = (int) $ilan->id;
            $firmaId = (int) $ilan->firma_id;

            if ($Basvuru->denemeSayisi($ip, 60) >= self::SAATLIK_DENEME_SINIRI) {
                throw new DomainException('Çok fazla deneme yapıldı. Lütfen bir süre sonra tekrar deneyin.', 429);
            }
            $Basvuru->denemeKaydet($ilanId, $ip, 'deneme');

            $metin = static fn(string $alan, int $limit): string => trim(mb_substr(str_replace("\r", '', (string) ($post[$alan] ?? '')), 0, $limit, 'UTF-8'));

            $adSoyad = $metin('ad_soyad', 150);
            $telefon = PersonelKarsilamaModel::telefonNormalize($metin('telefon', 20));
            $email = $metin('email', 190);
            $sehir = $metin('sehir', 100);
            $egitim = $metin('egitim', 150);
            $deneyimOzet = $metin('deneyim_ozet', 3000);

            if (mb_strlen($adSoyad, 'UTF-8') < 5 || !preg_match('/\s/u', $adSoyad)) {
                throw new DomainException('Lütfen adınızı ve soyadınızı eksiksiz yazın.', 422);
            }
            if ($telefon === '' || strlen($telefon) !== 12 || strncmp($telefon, '905', 3) !== 0) {
                throw new DomainException('Geçerli bir cep telefonu numarası girin.', 422);
            }
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new DomainException('Geçerli bir e-posta adresi girin.', 422);
            }
            if ($sehir === '' || $egitim === '' || $deneyimOzet === '') {
                throw new DomainException('Zorunlu alanları doldurun.', 422);
            }
            if (($post['kvkk'] ?? '') !== '1') {
                throw new DomainException('Aydınlatma metnini onaylamanız gerekir.', 422);
            }

            $dogumTarihi = null;
            $dt = trim((string) ($post['dogum_tarihi'] ?? ''));
            if ($dt !== '') {
                $d = DateTime::createFromFormat('Y-m-d', $dt);
                if (!$d || $d->format('Y-m-d') !== $dt || $d > new DateTime('-15 years') || $d < new DateTime('-90 years')) {
                    throw new DomainException('Doğum tarihi geçersiz.', 422);
                }
                $dogumTarihi = $dt;
            }

            $deneyimYili = ($post['deneyim_yili'] ?? '') !== '' ? round((float) $post['deneyim_yili'], 1) : null;
            if ($deneyimYili !== null && ($deneyimYili < 0 || $deneyimYili > 60)) {
                throw new DomainException('Deneyim süresi geçersiz.', 422);
            }
            $maas = ($post['maas_beklentisi'] ?? '') !== '' ? round((float) $post['maas_beklentisi'], 2) : null;
            if ($maas !== null && ($maas < 0 || $maas > 10000000)) {
                throw new DomainException('Maaş beklentisi geçersiz.', 422);
            }

            $sorular = json_decode((string) ($ilan->sorular ?? ''), true);
            $sorular = is_array($sorular) ? array_values(array_filter(array_map('strval', $sorular), static fn($s) => trim($s) !== '')) : [];
            $cevaplar = [];
            $gelenCevaplar = is_array($post['soru'] ?? null) ? $post['soru'] : [];
            foreach ($sorular as $i => $soru) {
                $cevap = trim(mb_substr(str_replace("\r", '', (string) ($gelenCevaplar[$i] ?? '')), 0, 1500, 'UTF-8'));
                if ($cevap === '') {
                    throw new DomainException('Tüm pozisyon sorularını yanıtlayın.', 422);
                }
                $cevaplar[$soru] = $cevap;
            }

            if ($Basvuru->telefonVarMi($ilanId, $telefon)) {
                throw new DomainException('Bu telefon numarasıyla bu ilana daha önce başvuru yapılmış.', 409);
            }

            $cvMetin = '';
            $cvMime = null;
            if (isset($files['cv']) && (int) $files['cv']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $islenmis = GuvenliBelgeYuklemeService::isle($files['cv']);
                } catch (DomainException $ex) {
                    throw new DomainException($ex->getMessage(), 422);
                }
                if ($islenmis['mime'] !== 'application/pdf') {
                    throw new DomainException('CV yalnızca PDF olabilir.', 422);
                }
                try {
                    $cvMetin = trim((new \Smalot\PdfParser\Parser())->parseContent($islenmis['veri'])->getText());
                    $cvMetin = mb_substr(preg_replace('/\s+/u', ' ', $cvMetin) ?? '', 0, 60000, 'UTF-8');
                } catch (Throwable $ex) {
                    error_log('IkBasvuruKabulService CV metni okunamadı: ' . $ex->getMessage());
                    $cvMetin = '';
                }

                $kayitliDosya = $this->sifreliKaydet($firmaId, $islenmis['veri'], 'CV');
                $cvMime = $islenmis['mime'];
            }

            if (isset($files['foto']) && (int) $files['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                try {
                    $foto = GuvenliBelgeYuklemeService::isle($files['foto']);
                } catch (DomainException $ex) {
                    throw new DomainException('Vesikalık fotoğraf: ' . $ex->getMessage(), 422);
                }
                if ($foto['mime'] !== 'image/jpeg') {
                    throw new DomainException('Vesikalık fotoğraf JPG veya PNG olmalıdır.', 422);
                }
                $kayitliFoto = $this->sifreliKaydet($firmaId, $foto['veri'], 'Fotoğraf');
            }

            $basvuruId = $Basvuru->ekle([
                'firma_id' => $firmaId,
                'ilan_id' => $ilanId,
                'ad_soyad' => $adSoyad,
                'telefon' => $telefon,
                'email' => $email !== '' ? $email : null,
                'dogum_tarihi' => $dogumTarihi,
                'sehir' => $sehir,
                'egitim' => $egitim,
                'deneyim_yili' => $deneyimYili,
                'deneyim_ozet' => $deneyimOzet,
                'ehliyet' => $metin('ehliyet', 50) ?: null,
                'maas_beklentisi' => $maas,
                'ise_baslama' => $metin('ise_baslama', 100) ?: null,
                'cevaplar' => $cevaplar ? json_encode($cevaplar, JSON_UNESCAPED_UNICODE) : null,
                'cv_dosya' => $kayitliDosya,
                'cv_mime' => $cvMime,
                'cv_metin' => $cvMetin !== '' ? $cvMetin : null,
                'foto_dosya' => $kayitliFoto,
                'ip_adresi' => $ip,
            ]);
            $kayitliDosya = null;
            $kayitliFoto = null;

            try {
                $Basvuru->denemeKaydet($ilanId, $ip, 'basarili');
                $Basvuru->olayEkle($firmaId, $basvuruId, null, 'Başvuru alındı', 'Aday dijital başvuru formunu gönderdi.');
                (new SystemLogModel())->logActionForFirm($firmaId, 0, 'İşe Alım Başvurusu', 'Yeni başvuru alındı (İlan ID: ' . $ilanId . ', Başvuru ID: ' . $basvuruId . ')', SystemLogModel::LEVEL_INFO);
            } catch (Throwable $ex) {
                error_log('IkBasvuruKabulService kayıt sonrası işlem hatası (Başvuru ' . $basvuruId . '): ' . $ex->getMessage());
            }

            return [
                'basvuru_id' => $basvuruId,
                'firma_id' => $firmaId,
                'ilan' => $ilan,
                'ai_girdi' => [
                    'sehir' => $sehir,
                    'egitim' => $egitim,
                    'deneyim_yili' => $deneyimYili,
                    'deneyim_ozet' => $deneyimOzet,
                    'ehliyet' => $metin('ehliyet', 50),
                    'maas_beklentisi' => $maas,
                    'ise_baslama' => $metin('ise_baslama', 100),
                    'cevaplar' => $cevaplar,
                    'cv_metin' => $cvMetin,
                ],
                'ad_soyad' => $adSoyad,
            ];
        } catch (DomainException $ex) {
            $this->dosyalariTemizle($firmaId, [$kayitliDosya, $kayitliFoto]);
            throw $ex;
        } catch (Throwable $ex) {
            error_log('IkBasvuruKabulService hatası: ' . $ex->getMessage());
            $this->dosyalariTemizle($firmaId, [$kayitliDosya, $kayitliFoto]);
            throw new RuntimeException('Başvuru şu anda kaydedilemedi. Lütfen daha sonra tekrar deneyin.', 500);
        }
    }

    public function arkaPlanIsle(array $sonuc): void
    {
        $Basvuru = new IkBasvuruModel();
        $basvuruId = (int) $sonuc['basvuru_id'];
        $firmaId = (int) $sonuc['firma_id'];
        $ilan = $sonuc['ilan'];
        $bildirimSonuc = null;

        try {
            $ai = (new IkBasvuruAiService())->degerlendir($ilan, $sonuc['ai_girdi'], $firmaId);
            $Basvuru->aiSonucKaydet($basvuruId, $ai);
            $bildirimSonuc = $ai;
            $Basvuru->olayEkle($firmaId, $basvuruId, null, 'Yapay zekâ değerlendirmesi', 'Puan: ' . $ai['puan'] . ' / Etiket: ' . $ai['etiket']);
        } catch (Throwable $ex) {
            error_log('İK başvuru AI değerlendirme hatası (Başvuru ' . $basvuruId . '): ' . $ex->getMessage());
            $Basvuru->aiHataKaydet($basvuruId);
            $Basvuru->olayEkle($firmaId, $basvuruId, null, 'Yapay zekâ hatası', 'Değerlendirme tamamlanamadı; yeniden denenebilir.');
        }

        try {
            (new IkBasvuruBildirimService())->yeniBasvuru($firmaId, $basvuruId, (string) $sonuc['ad_soyad'], (string) $ilan->baslik, $bildirimSonuc);
        } catch (Throwable $ex) {
            error_log('İK başvuru bildirimi hatası (Başvuru ' . $basvuruId . '): ' . $ex->getMessage());
        }
    }

    private function sifreliKaydet(int $firmaId, string $veri, string $etiket): string
    {
        $dizin = $this->kokDizin . '/uploads/ik_basvuru/' . $firmaId . '/';
        if (!is_dir($dizin) && !@mkdir($dizin, 0733, true) && !is_dir($dizin)) {
            throw new RuntimeException($etiket . ' klasörü oluşturulamadı.');
        }
        $erisimDosyasi = $this->kokDizin . '/uploads/ik_basvuru/.htaccess';
        if (!is_file($erisimDosyasi)) {
            @file_put_contents($erisimDosyasi, "Require all denied\n");
        }
        $ad = bin2hex(random_bytes(16)) . '.enc';
        if (file_put_contents($dizin . $ad, Security::encryptFile($veri), LOCK_EX) === false) {
            throw new RuntimeException($etiket . ' kaydedilemedi.');
        }
        return $ad;
    }

    private function dosyalariTemizle(int $firmaId, array $dosyalar): void
    {
        foreach ($dosyalar as $dosya) {
            if ($dosya !== null && $firmaId > 0) {
                @unlink($this->kokDizin . '/uploads/ik_basvuru/' . $firmaId . '/' . $dosya);
            }
        }
    }
}
