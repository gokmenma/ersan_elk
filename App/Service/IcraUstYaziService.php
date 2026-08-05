<?php

namespace App\Service;

use App\Helper\RichTextSanitizer;
use App\Helper\Security;
use App\Helper\TurkceEk;
use App\Model\PersonelIcralariModel;
use InvalidArgumentException;

final class IcraUstYaziService
{
    private const DURUM_ETIKETLERI = [
        'bekliyor' => 'Bekliyor',
        'devam_ediyor' => 'Devam Ediyor',
        'fekki_geldi' => 'Fekki Geldi',
        'kesinti_bitti' => 'Kesinti Bitti',
        'bitti' => 'Tamamlandı',
        'durduruldu' => 'Durduruldu',
    ];

    private const ORAN_METINLERI = [
        '50' => "1/2'sinin",
        '33' => "1/3'ünün",
        '25' => "1/4'ünün",
        '20' => "1/5'inin",
        '10' => "1/10'unun",
    ];

    private PersonelIcralariModel $model;

    public function __construct()
    {
        $this->model = new PersonelIcralariModel();
    }

    public function icrasiOlanPersoneller(): array
    {
        $secenekler = ['' => 'Personel seçiniz...'];
        foreach ($this->model->getIcrasiOlanPersoneller((int) ($_SESSION['firma_id'] ?? 0)) as $personel) {
            $secenekler[(int) $personel->id] = $personel->adi_soyadi . ' (' . (int) $personel->icra_adedi . ' dosya)';
        }
        return $secenekler;
    }

    public function personelIcralari(int $personelId): array
    {
        if ($personelId < 1) {
            throw new InvalidArgumentException('Personel seçiniz.');
        }
        if (!isset($this->icrasiOlanPersoneller()[$personelId])) {
            throw new InvalidArgumentException('Bu personel için icra kaydı bulunamadı.');
        }

        $liste = [];
        foreach ($this->model->getPersonelIcralariWithKesintiler($personelId) as $icra) {
            $liste[] = [
                'id' => Security::encrypt((int) $icra->id),
                'sira' => (int) $icra->sira,
                'icra_dairesi' => (string) ($icra->icra_dairesi ?? ''),
                'dosya_no' => (string) ($icra->dosya_no ?? ''),
                'alacakli' => (string) ($icra->alacakli ?? ''),
                'toplam_borc' => $this->para($icra->toplam_borc),
                'kalan_tutar' => $this->para($icra->kalan_tutar),
                'durum' => (string) $icra->durum,
                'durum_etiketi' => self::DURUM_ETIKETLERI[$icra->durum] ?? (string) $icra->durum,
            ];
        }
        return $liste;
    }

    public function build(int $tetikleyenIcraId): array
    {
        if ($tetikleyenIcraId < 1) {
            throw new InvalidArgumentException('İcra dosyası seçiniz.');
        }

        $tetikleyen = $this->model->getIcraWithPersonel($tetikleyenIcraId);
        if (!$tetikleyen) {
            throw new InvalidArgumentException('İcra dosyası bulunamadı.');
        }

        $firmaId = (int) ($_SESSION['firma_id'] ?? 0);
        if ((int) $tetikleyen->firma_id > 0 && (int) $tetikleyen->firma_id !== $firmaId) {
            throw new InvalidArgumentException('Bu icra dosyası için yetkiniz yok.');
        }

        $tumIcralar = $this->model->getPersonelIcralariWithKesintiler((int) $tetikleyen->personel_id);
        $devamEdenler = [];
        $bekleyenler = [];
        foreach ($tumIcralar as $icra) {
            if ((int) $icra->id === $tetikleyenIcraId) {
                continue;
            }
            if ($icra->durum === 'devam_ediyor') {
                $devamEdenler[] = $icra;
            } elseif ($icra->durum === 'bekliyor') {
                $bekleyenler[] = $icra;
            }
        }

        $paragraflar = [$this->girisParagrafi($tetikleyen)];
        if ($devamEdenler !== []) {
            $paragraflar[] = $this->devamEdenlerParagrafi($devamEdenler);
        }
        if ($bekleyenler !== []) {
            $paragraflar[] = $this->bekleyenlerParagrafi($bekleyenler);
        }
        $paragraflar[] = $this->sonucParagrafi($tetikleyen, $devamEdenler !== [] || $bekleyenler !== []);
        $paragraflar[] = 'Gereğini bilgilerinize arz ederim.';

        $govde = '';
        foreach ($paragraflar as $paragraf) {
            $govde .= '<p>' . $paragraf . '</p>';
        }

        $daire = trim((string) ($tetikleyen->icra_dairesi ?? ''));

        return [
            'konu' => 'İcra Kesintisi Hakkında',
            'kurum_adi' => $daire !== '' ? TurkceEk::ekle($daire, 'yonelme') : '',
            'muhatap_alt_birim' => '',
            'muhatap_adres' => '',
            'ilgiler' => $this->ilgiSatiri($tetikleyen),
            'aciklama_html' => RichTextSanitizer::sanitize($govde),
            'personel_id' => (int) $tetikleyen->personel_id,
            'personel_adi' => (string) $tetikleyen->adi_soyadi,
        ];
    }

    private function girisParagrafi(object $icra): string
    {
        $ad = trim((string) $icra->adi_soyadi);
        $gorev = trim((string) ($icra->gorev ?? ''));
        $kisi = $gorev !== '' ? $gorev . ' ' . $ad : $ad;
        $kisiIlgi = $this->kacis(TurkceEk::ekle($kisi, 'ilgi', false, "'"));

        $alacakli = trim((string) ($icra->alacakli ?? ''));
        $alacakliKismi = '';
        if ($alacakli !== '') {
            $alacakliKismi = $this->kacis(TurkceEk::ekle($alacakli, 'yonelme', null, "'")) . ' olan ';
        }

        $borc = $this->para($icra->toplam_borc);
        $kesintiTipi = (string) ($icra->kesinti_tipi ?? 'tutar');
        $oran = (float) ($icra->kesinti_orani ?? 0);

        if ($kesintiTipi !== 'tutar' && $oran > 0) {
            return sprintf(
                'İlgi yazı ile personelimiz %s %s%s TL borcu nedeniyle maaşının %s haczedildiği bildirilmektedir.',
                $kisiIlgi,
                $alacakliKismi,
                $borc,
                $this->oranMetni($oran, $kesintiTipi)
            );
        }

        $aylik = (float) ($icra->aylik_kesinti_tutari ?? 0);
        if ($aylik > 0) {
            return sprintf(
                'İlgi yazı ile personelimiz %s %s%s TL borcu nedeniyle maaşından aylık %s TL kesinti yapılması bildirilmektedir.',
                $kisiIlgi,
                $alacakliKismi,
                $borc,
                $this->para($aylik)
            );
        }

        return sprintf(
            'İlgi yazı ile personelimiz %s %s%s TL borcu nedeniyle maaşından icra kesintisi yapılması bildirilmektedir.',
            $kisiIlgi,
            $alacakliKismi,
            $borc
        );
    }

    private function devamEdenlerParagrafi(array $icralar): string
    {
        if (count($icralar) === 1) {
            $icra = $icralar[0];
            return sprintf(
                "Söz konusu personelin halen devam etmekte olan %s %s sayılı dosyasına %s TL icra kesintisi olup, kalan borç miktarı %s TL'dir.",
                $this->dosyaIlgi($icra),
                $this->kacis((string) $icra->dosya_no),
                $this->para($icra->toplam_kesilen),
                $this->para($icra->kalan_tutar)
            );
        }

        $satirlar = [];
        foreach ($icralar as $sira => $icra) {
            $satirlar[] = sprintf(
                '%d-) %s %s sayılı dosyasına %s TL kesinti yapılmış olup kalan borç miktarı %s TL,',
                $sira + 1,
                $this->dosyaIlgi($icra),
                $this->kacis((string) $icra->dosya_no),
                $this->para($icra->toplam_kesilen),
                $this->para($icra->kalan_tutar)
            );
        }

        return 'Söz konusu personelin halen devam etmekte olan;<br>'
            . implode('<br>', $satirlar)
            . '<br>şeklinde icra kesintileri bulunmaktadır.';
    }

    private function bekleyenlerParagrafi(array $icralar): string
    {
        $satirlar = [];
        foreach ($icralar as $sira => $icra) {
            $satirlar[] = sprintf(
                '%d-) %s %s sayılı dosyasına %s TL,',
                $sira + 1,
                $this->dosyaIlgi($icra),
                $this->kacis((string) $icra->dosya_no),
                $this->para($icra->toplam_borc)
            );
        }

        return 'Bunun dışında ilgilinin;<br>'
            . implode('<br>', $satirlar)
            . '<br>bildirilen ve idaremizce takip sırasına alınan borcu bulunmaktadır.';
    }

    private function sonucParagrafi(object $icra, bool $oncekiDosyaVar): string
    {
        $dosyaNo = $this->kacis((string) $icra->dosya_no);
        if ($oncekiDosyaVar) {
            return sprintf(
                'İcra müdürlüğünce gönderilen %s sayılı icra kesintisine, ilgili dosyalardaki kesintiler tamamlandıktan sonra başlanılabileceği hususunda;',
                $dosyaNo
            );
        }

        return sprintf(
            'İcra müdürlüğünce gönderilen %s sayılı icra kesintisine ilgili mevzuat hükümleri çerçevesinde başlanılacağı hususunda;',
            $dosyaNo
        );
    }

    private function ilgiSatiri(object $icra): string
    {
        $daire = trim((string) ($icra->icra_dairesi ?? ''));
        $dosyaNo = trim((string) ($icra->dosya_no ?? ''));
        if ($daire === '' && $dosyaNo === '') {
            return '';
        }
        if ($daire === '') {
            return $dosyaNo . ' sayılı icra kesinti müzekkeresi.';
        }
        if ($dosyaNo === '') {
            return $daire . ' tarafından gönderilen icra kesinti müzekkeresi.';
        }
        return TurkceEk::ekle($daire, 'ilgi', null, "'") . ' ' . $dosyaNo . ' sayılı icra kesinti müzekkeresi.';
    }

    private function dosyaIlgi(object $icra): string
    {
        $daire = trim((string) ($icra->icra_dairesi ?? ''));
        if ($daire === '') {
            return 'ilgili icra dairesinin';
        }
        return $this->kacis(TurkceEk::ekle($daire, 'ilgi', null, "'"));
    }

    private function oranMetni(float $oran, string $kesintiTipi): string
    {
        $anahtar = (string) (int) round($oran);
        $metin = self::ORAN_METINLERI[$anahtar] ?? ('%' . rtrim(rtrim(number_format($oran, 2, ',', '.'), '0'), ',') . ' oranındaki kısmının');
        if ($kesintiTipi === 'asgari_yuzde') {
            return 'net asgari ücret üzerinden ' . $metin;
        }
        return $metin;
    }

    private function para($tutar): string
    {
        return number_format((float) $tutar, 2, ',', '.');
    }

    private function kacis(string $metin): string
    {
        return htmlspecialchars($metin, ENT_QUOTES, 'UTF-8');
    }
}
