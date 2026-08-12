<?php

namespace App\Service;

use Exception;

final class PersonelEvrakAiService
{
    private const EVRAK_TURLERI = [
        'ehliyet', 'ikametgah', 'adli_sicil_kaydi', 'nufus_kayit_ornegi',
        'gizlilik_taahhutnamesi', 'sozlesme', 'kimlik', 'diploma', 'cv',
        'saglik_raporu', 'sertifika', 'diger'
    ];

    private const ALANLAR = [
        'tc_kimlik_no' => 'T.C. Kimlik No',
        'adi_soyadi' => 'Ad Soyad',
        'dogum_tarihi' => 'Doğum Tarihi',
        'cinsiyet' => 'Cinsiyet',
        'medeni_durum' => 'Medeni Durum',
        'kan_grubu' => 'Kan Grubu',
        'anne_adi' => 'Anne Adı',
        'baba_adi' => 'Baba Adı',
        'dogum_yeri_il' => 'Doğum Yeri İl',
        'dogum_yeri_ilce' => 'Doğum Yeri İlçe',
        'ehliyet_sinifi' => 'Ehliyet Sınıfı',
        'cep_telefonu' => 'Cep Telefonu',
        'cep_telefonu_2' => '2. Cep Telefonu',
        'email_adresi' => 'E-posta',
        'adres' => 'Adres',
    ];

    public function analiz(array $files): array
    {
        $belgeler = $this->dosyalariDuzenle($files);
        if ($belgeler === []) {
            throw new Exception('Analiz edilecek en az bir belge seçiniz.');
        }
        if (count($belgeler) > 6) {
            throw new Exception('Tek seferde en fazla 6 belge analiz edilebilir.');
        }

        $okuyucu = new AiBelgeOkuyucuService();
        [$apiKey, $model] = $okuyucu->ayarlar();
        $icerik = [[
            'type' => 'text',
            'text' => "Yüklenen personel belgelerini birlikte incele. Her belgeyi ayrı kaynak kabul et.\n"
                . "Yalnızca belgede açıkça görülen bilgileri çıkar; tahmin etme. Çelişkide daha resmi/güncel belgeyi seç ve not düş.\n"
                . "İzin verilen alanlar: " . implode(', ', array_keys(self::ALANLAR)) . ".\n"
                . "dogum_tarihi d.m.Y, cinsiyet Erkek/Kadın, medeni_durum Evli/Bekar biçiminde olsun.\n"
                . "Her belgeyi ayrıca şu evrak türlerinden biriyle eşleştir: " . implode(', ', self::EVRAK_TURLERI) . ". Emin değilsen diger kullan.\n"
                . "JSON şeması: {\"alanlar\":{\"alan_adi\":{\"deger\":\"...\",\"kaynak\":\"Belge 1\",\"guven\":0-100}},\"belgeler\":[{\"sira\":1,\"evrak_turu\":\"kimlik\",\"evrak_adi\":\"Kimlik Kartı\",\"guven\":95}],\"uyarilar\":[\"...\"]}. Boş alanları ekleme."
        ]];

        $toplamBoyut = 0;
        foreach ($belgeler as $index => $belge) {
            $toplamBoyut += (int) ($belge['size'] ?? 0);
            if ($toplamBoyut > 30 * 1024 * 1024) {
                throw new Exception('Belgelerin toplam boyutu 30 MB sınırını geçemez.');
            }
            $mime = $okuyucu->dogrula($belge, 12);
            $parcalar = $okuyucu->kullaniciIcerigi('Belge ' . ($index + 1), $belge, $mime);
            foreach ($parcalar as $parca) {
                $icerik[] = $parca;
            }
        }

        $sonuc = $okuyucu->jsonIste(
            $apiKey,
            $model,
            'Sen Türkçe personel özlük belgelerinden yapılandırılmış veri çıkaran dikkatli bir asistansın. Verileri değiştirme, uydurma veya yorumlama.',
            $icerik,
            0.1
        );

        return $this->sonucuTemizle($sonuc);
    }

    private function dosyalariDuzenle(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return [$files];
        }
        $result = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $result[] = [
                'name' => $name,
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
        }
        return $result;
    }

    private function sonucuTemizle(array $sonuc): array
    {
        $alanlar = [];
        foreach (($sonuc['alanlar'] ?? []) as $ad => $bilgi) {
            if (!isset(self::ALANLAR[$ad]) || !is_array($bilgi)) {
                continue;
            }
            $deger = trim((string) ($bilgi['deger'] ?? ''));
            if ($deger === '') {
                continue;
            }
            $alanlar[$ad] = [
                'etiket' => self::ALANLAR[$ad],
                'deger' => mb_substr($deger, 0, $ad === 'adres' ? 500 : 150, 'UTF-8'),
                'kaynak' => mb_substr(trim((string) ($bilgi['kaynak'] ?? 'Belge')), 0, 100, 'UTF-8'),
                'guven' => max(0, min(100, (int) ($bilgi['guven'] ?? 0))),
            ];
        }
        $uyarilar = array_values(array_filter(array_map(
            static fn($uyari) => mb_substr(trim((string) $uyari), 0, 300, 'UTF-8'),
            is_array($sonuc['uyarilar'] ?? null) ? $sonuc['uyarilar'] : []
        )));
        $belgeler = [];
        foreach (($sonuc['belgeler'] ?? []) as $belge) {
            if (!is_array($belge)) {
                continue;
            }
            $sira = (int) ($belge['sira'] ?? 0);
            $tur = trim((string) ($belge['evrak_turu'] ?? 'diger'));
            if ($sira < 1 || !in_array($tur, self::EVRAK_TURLERI, true)) {
                continue;
            }
            $belgeler[$sira] = [
                'sira' => $sira,
                'evrak_turu' => $tur,
                'evrak_adi' => mb_substr(trim((string) ($belge['evrak_adi'] ?? 'Personel Evrakı')), 0, 150, 'UTF-8'),
                'guven' => max(0, min(100, (int) ($belge['guven'] ?? 0))),
            ];
        }
        ksort($belgeler);
        return ['alanlar' => $alanlar, 'belgeler' => array_values($belgeler), 'uyarilar' => array_slice($uyarilar, 0, 10)];
    }

}
