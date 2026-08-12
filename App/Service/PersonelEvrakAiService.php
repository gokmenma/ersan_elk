<?php

namespace App\Service;

use Exception;

/**
 * Personel belgelerini yalnızca bu sunucudaki Tesseract ile okur.
 * Ağ bağlantısı ve harici yapay zekâ servisi kullanmaz.
 */
final class PersonelEvrakAiService
{
    private const IZINLI_MIME = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];
    private const EVRAK_TURLERI = [
        'ehliyet', 'ikametgah', 'adli_sicil_kaydi', 'nufus_kayit_ornegi',
        'gizlilik_taahhutnamesi', 'sozlesme', 'kimlik', 'diploma', 'cv',
        'saglik_raporu', 'sertifika', 'diger'
    ];
    private const ETIKETLER = [
        'tc_kimlik_no' => 'T.C. Kimlik No', 'adi_soyadi' => 'Ad Soyad',
        'dogum_tarihi' => 'Doğum Tarihi', 'cinsiyet' => 'Cinsiyet',
        'medeni_durum' => 'Medeni Durum', 'kan_grubu' => 'Kan Grubu',
        'anne_adi' => 'Anne Adı', 'baba_adi' => 'Baba Adı',
        'dogum_yeri_il' => 'Doğum Yeri İl', 'dogum_yeri_ilce' => 'Doğum Yeri İlçe',
        'ehliyet_sinifi' => 'Ehliyet Sınıfı', 'cep_telefonu' => 'Cep Telefonu',
        'cep_telefonu_2' => '2. Cep Telefonu', 'email_adresi' => 'E-posta', 'adres' => 'Adres',
    ];

    public function analiz(array $files): array
    {
        $belgeler = $this->dosyalariDuzenle($files);
        if ($belgeler === []) {
            throw new Exception('Analiz edilecek en az bir belge seçiniz.');
        }
        if (count($belgeler) > 6) {
            throw new Exception('Tek seferde en fazla 6 belge okunabilir.');
        }
        $tessdata = dirname(__DIR__, 2) . '/storage/tessdata';
        if (!is_file($tessdata . '/tur.traineddata') || !is_file($tessdata . '/eng.traineddata')) {
            throw new Exception('Yerel OCR dil dosyaları bulunamadı.');
        }

        $alanlar = [];
        $gorulenAlanDegerleri = [];
        $belgeSonuclari = [];
        $uyarilar = [];
        $toplamBoyut = 0;
        foreach ($belgeler as $index => $belge) {
            $toplamBoyut += (int) ($belge['size'] ?? 0);
            if ($toplamBoyut > 30 * 1024 * 1024) {
                throw new Exception('Belgelerin toplam boyutu 30 MB sınırını geçemez.');
            }
            $mime = $this->dogrula($belge);
            $ocr = $this->yerelOcr($belge['tmp_name'], $mime, $tessdata);
            $metin = $ocr['metin'];
            $ocrGuveni = $ocr['guven'];
            $tur = $this->belgeTurunuBul($metin);
            $sira = $index + 1;
            $belgeSonuclari[] = [
                'sira' => $sira,
                'evrak_turu' => $tur,
                'evrak_adi' => $this->evrakAdi($tur),
                'guven' => $tur === 'diger' ? max(35, $ocrGuveni - 25) : min(98, $ocrGuveni + 5),
            ];
            foreach ($this->alanlariCikar($metin, $tur) as $alan => $deger) {
                $normalizeDeger = mb_strtolower(preg_replace('/\s+/u', ' ', trim($deger)) ?: trim($deger), 'UTF-8');
                $tekilAnahtar = $alan . '|' . $normalizeDeger;
                if ($deger === '' || isset($gorulenAlanDegerleri[$tekilAnahtar])) {
                    continue;
                }
                $gorulenAlanDegerleri[$tekilAnahtar] = true;
                $alanlar[] = [
                    'alan' => $alan,
                    'etiket' => self::ETIKETLER[$alan],
                    'deger' => $deger,
                    'kaynak' => 'Belge ' . $sira,
                    'guven' => $this->alanGuveni($alan, $deger, $ocrGuveni),
                ];
            }
            if (mb_strlen($metin, 'UTF-8') < 20) {
                $uyarilar[] = 'Belge ' . $sira . ' yeterince okunamadı. Daha net veya düz çekilmiş bir belge deneyin.';
            }
        }
        return ['alanlar' => $alanlar, 'belgeler' => $belgeSonuclari, 'uyarilar' => $uyarilar];
    }

    private function yerelOcr(string $path, string $mime, string $tessdata): array
    {
        $ramTempRoot = '/dev/shm';
        if (!is_dir($ramTempRoot) || !is_writable($ramTempRoot)) {
            throw new Exception('Güvenli RAM tabanlı OCR çalışma alanı kullanılamıyor.');
        }
        $tempDir = $ramTempRoot . '/personel_ocr_' . bin2hex(random_bytes(8));
        if (!mkdir($tempDir, 0700, true)) {
            throw new Exception('OCR için geçici çalışma alanı oluşturulamadı.');
        }
        $images = [];
        try {
            // PHP'nin yükleme geçici dosyasını mümkün olan en erken anda RAM alanına taşı.
            $ramInput = $tempDir . '/girdi';
            if (!move_uploaded_file($path, $ramInput)) {
                throw new Exception('Belge güvenli RAM çalışma alanına taşınamadı.');
            }
            $path = $ramInput;
            if ($mime === 'application/pdf') {
                $prefix = $tempDir . '/sayfa';
                $command = '/usr/bin/env -u LD_LIBRARY_PATH -u LD_PRELOAD /usr/bin/pdftoppm -f 1 -l 4 -jpeg -r 200 '
                    . escapeshellarg($path) . ' ' . escapeshellarg($prefix) . ' 2>&1';
                exec($command, $output, $exitCode);
                $images = glob($prefix . '-*.jpg') ?: [];
                if ($exitCode !== 0 || $images === []) {
                    throw new Exception('PDF sayfaları yerel OCR için hazırlanamadı.');
                }
            } elseif (in_array($mime, ['image/heic', 'image/heif'], true)) {
                $heicJpeg = $tempDir . '/heic.jpg';
                $command = '/usr/bin/env -u LD_LIBRARY_PATH -u LD_PRELOAD /usr/bin/magick '
                    . escapeshellarg($path) . '[0] -auto-orient -quality 92 ' . escapeshellarg($heicJpeg) . ' 2>&1';
                exec($command, $heicOutput, $heicExitCode);
                if ($heicExitCode !== 0 || !is_file($heicJpeg)) {
                    throw new Exception('HEIC belgesi yerel OCR için dönüştürülemedi.');
                }
                $images = [$heicJpeg];
            } else {
                $images = [$path];
            }

            $metinler = [];
            $guvenler = [];
            foreach ($images as $i => $image) {
                $outputBase = $tempDir . '/ocr_' . $i;
                // TSV tek çalışmada hem metni hem gerçek kelime güvenini verir.
                $tsvCommand = '/usr/bin/env -u LD_LIBRARY_PATH -u LD_PRELOAD /usr/bin/tesseract '
                    . escapeshellarg($image) . ' ' . escapeshellarg($outputBase)
                    . ' -l tur+eng --tessdata-dir ' . escapeshellarg($tessdata)
                    . ' --psm 6 ' . escapeshellarg('/usr/share/tessdata/configs/tsv') . ' 2>&1';
                exec($tsvCommand, $tsvOutput, $tsvExitCode);
                $tsvFile = $outputBase . '.tsv';
                $sayfaSatirlari = [];
                if ($tsvExitCode === 0 && is_file($tsvFile)) {
                    foreach (array_slice(file($tsvFile, FILE_IGNORE_NEW_LINES) ?: [], 1) as $row) {
                        $columns = explode("\t", $row, 12);
                        $confidence = isset($columns[10]) ? (float) $columns[10] : -1;
                        $word = trim((string) ($columns[11] ?? ''));
                        if ($word === '' || $confidence < 0) continue;
                        $guvenler[] = $confidence;
                        $satirAnahtari = ($columns[2] ?? 0) . ':' . ($columns[3] ?? 0) . ':' . ($columns[4] ?? 0);
                        $sayfaSatirlari[$satirAnahtari][] = $word;
                    }
                }
                $normalText = trim(implode("\n", array_map(static fn($kelimeler) => implode(' ', $kelimeler), $sayfaSatirlari)));
                if ($normalText !== '') $metinler[] = $normalText;

                $lowerText = mb_strtolower($normalText, 'UTF-8');
                $ehliyetAlaniEksik = (str_contains($lowerText, 'sürücü') || str_contains($lowerText, 'driving'))
                    && !preg_match('/\b9\s*[.。,：:;|\-]?\s*[A-GM8]/iu', $normalText);
                $adresBelgesi = str_contains($lowerText, 'yerleşim yeri') || str_contains($lowerText, 'ikametgah') || str_contains($lowerText, 'ikametgâh');
                $seyrekModGerekli = mb_strlen($normalText, 'UTF-8') < 80 || $ehliyetAlaniEksik || $adresBelgesi;

                // Seyrek mod yalnızca kart alanı eksikse veya tablo adresi varsa devreye girer.
                if ($seyrekModGerekli) {
                    $sparseBase = $tempDir . '/ocr_sparse_' . $i;
                    $sparseCommand = '/usr/bin/env -u LD_LIBRARY_PATH -u LD_PRELOAD /usr/bin/tesseract '
                        . escapeshellarg($image) . ' ' . escapeshellarg($sparseBase)
                        . ' -l tur+eng --tessdata-dir ' . escapeshellarg($tessdata) . ' --psm 11 2>&1';
                    exec($sparseCommand, $sparseOutput, $sparseExitCode);
                    $sparseFile = $sparseBase . '.txt';
                    $ekSatirlar = [];
                    if ($sparseExitCode === 0 && is_file($sparseFile)) {
                        foreach (preg_split('/\R/u', (string) file_get_contents($sparseFile)) ?: [] as $satir) {
                            $satir = trim($satir);
                            if ($satir !== '' && !str_contains($normalText, $satir)) $ekSatirlar[] = $satir;
                        }
                    }
                    if ($ekSatirlar !== []) $metinler[] = implode("\n", $ekSatirlar);
                }
            }
            $metin = trim(implode("\n", $metinler));
            if ($metin === '') {
                throw new Exception('Belgeden yerel OCR ile metin okunamadı.');
            }
            $ortalamaGuven = $guvenler === [] ? 50 : (int) round(array_sum($guvenler) / count($guvenler));
            return [
                'metin' => preg_replace('/[ \t]+/u', ' ', $metin) ?: $metin,
                'guven' => max(1, min(99, $ortalamaGuven)),
            ];
        } finally {
            foreach (glob($tempDir . '/*') ?: [] as $tempFile) {
                @unlink($tempFile);
            }
            @rmdir($tempDir);
        }
    }

    private function alanlariCikar(string $metin, string $tur): array
    {
        $sonuc = [];
        if (preg_match('/\b[1-9][0-9]{10}\b/', preg_replace('/(?<=\d)\s+(?=\d)/', '', $metin) ?: $metin, $m)) {
            $sonuc['tc_kimlik_no'] = $m[0];
        }
        $sonuc += $this->etiketliDegerler($metin, [
            'adi_soyadi' => ['adı soyadı', 'ad soyad', 'surname.*name', 'soyadı.*adı'],
            'anne_adi' => ['anne adı', 'ana adı'], 'baba_adi' => ['baba adı'],
            'dogum_tarihi' => ['doğum tarihi', 'date of birth'],
            'dogum_yeri_il' => ['doğum yeri', 'place of birth'],
            'medeni_durum' => ['medeni hali', 'medeni durum'],
            'kan_grubu' => ['kan grubu'], 'email_adresi' => ['e-?posta', 'email'],
            'cep_telefonu' => ['cep telefonu', 'telefon(?:u)?', 'gsm', 'mobile'],
        ]);
        foreach ($sonuc as $alan => $deger) {
            $sonuc[$alan] = $this->temizle($deger);
        }
        if (!empty($sonuc['dogum_tarihi']) && preg_match('/(\d{1,2})[.\/\-](\d{1,2})[.\/\-](\d{4})/', $sonuc['dogum_tarihi'], $m)) {
            $sonuc['dogum_tarihi'] = sprintf('%02d.%02d.%04d', $m[1], $m[2], $m[3]);
        }
        if (!empty($sonuc['cep_telefonu']) && preg_match('/(?:\+?90\s*)?0?5\d{2}[\s.-]*\d{3}[\s.-]*\d{2}[\s.-]*\d{2}/', $sonuc['cep_telefonu'], $m)) {
            $telefon = preg_replace('/\D/', '', $m[0]);
            if (str_starts_with($telefon, '90') && strlen($telefon) === 12) $telefon = substr($telefon, 2);
            if (strlen($telefon) === 10 && str_starts_with($telefon, '5')) $telefon = '0' . $telefon;
            $sonuc['cep_telefonu'] = $telefon;
        } else {
            unset($sonuc['cep_telefonu']);
        }
        if (preg_match('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', $metin, $m)) {
            $sonuc['email_adresi'] = $m[0];
        }
        if ($tur === 'ehliyet' && preg_match('/(?:\b9|sınıf(?:ı)?|class)\s*[.。,：:;|\-]?\s*([A-GM8])/iu', $metin, $m)) {
            // Kartın 9. alanında birden fazla sınıf yazsa da personel formuna ilk sınıf harfini aktar.
            $ilkSinif = mb_strtoupper($m[1], 'UTF-8');
            $sonuc['ehliyet_sinifi'] = $ilkSinif === '8' ? 'B' : $ilkSinif;
        }
        $lower = mb_strtolower($metin, 'UTF-8');
        if (str_contains($lower, 'kadın') || str_contains($lower, 'female')) $sonuc['cinsiyet'] = 'Kadın';
        elseif (str_contains($lower, 'erkek') || str_contains($lower, 'male')) $sonuc['cinsiyet'] = 'Erkek';
        if (preg_match('/\b(evli|bekar|bekâr)\b/iu', $metin, $m)) $sonuc['medeni_durum'] = mb_strtolower($m[1], 'UTF-8') === 'evli' ? 'Evli' : 'Bekar';
        if ($tur === 'ikametgah') {
            $adres = $this->adresCikar($metin);
            if ($adres !== '') $sonuc['adres'] = $adres;
        }
        return array_intersect_key($sonuc, self::ETIKETLER);
    }

    private function etiketliDegerler(string $metin, array $etiketler): array
    {
        $sonuc = [];
        foreach ($etiketler as $alan => $alternatifler) {
            foreach ($alternatifler as $etiket) {
                if (preg_match('/(?:' . $etiket . ')\s*[:：]?\s*([^\r\n]{2,100})/iu', $metin, $m)) {
                    $sonuc[$alan] = $m[1];
                    break;
                }
            }
        }
        return $sonuc;
    }

    private function adresCikar(string $metin): string
    {
        $adaylar = [];
        $devamSatirlari = [];
        foreach (preg_split('/\R/u', $metin) ?: [] as $hamSatir) {
            $hamSatir = trim($hamSatir);
            if (preg_match('/^\d{1,4}\s+[A-ZÇĞİÖŞÜ][A-ZÇĞİÖŞÜ\s.-]{3,}(?:\/\s*[A-ZÇĞİÖŞÜ][A-ZÇĞİÖŞÜ\s.-]{2,})?$/u', $hamSatir)) {
                $devamSatirlari[] = $hamSatir;
            }
        }
        if (preg_match('/(?:yerleşim yeri adresi|adres)\s*[:：]?\s*([\s\S]{10,350}?)(?:\n\s*\n|belge no|düzenleme tarihi|açıklama)/iu', $metin, $m)) {
            $adres = $this->adresiNormalizeEt(preg_replace('/\s*\n\s*/u', ' ', $m[1]) ?: $m[1]);
            $adres = $this->adresDevaminiEkle($adres, $devamSatirlari);
            if ($this->adresGecerliMi($adres)) $adaylar[] = $adres;
        }
        $satirlar = preg_split('/\R/u', $metin) ?: [];
        foreach ($satirlar as $index => $line) {
            if (preg_match('/\b(MAH\.?|MAHALLESİ|CAD\.?|SOK\.?|SK\.?|NO\s*:)/iu', $line)) {
                $birlesik = $line;
                // Tablo hücresindeki adres, sonraki satırda ilçe/il ile devam edebilir.
                for ($offset = 1; $offset <= 2; $offset++) {
                    $devam = trim((string) ($satirlar[$index + $offset] ?? ''));
                    if ($devam === '' || preg_match('/belge no|düzenleme tarihi|açıklama|imza/iu', $devam)) break;
                    if (preg_match('/\d|\/|İLÇE|MAHALLE|KAPI|[A-ZÇĞİÖŞÜ]{4,}/u', $devam)) $birlesik .= ' ' . $devam;
                }
                $adres = $this->adresiNormalizeEt($birlesik);
                $adres = $this->adresDevaminiEkle($adres, $devamSatirlari);
                if ($this->adresGecerliMi($adres)) $adaylar[] = $adres;
            }
        }
        if ($adaylar === []) return '';
        usort($adaylar, fn($a, $b) => $this->adresPuani($b) <=> $this->adresPuani($a));
        return mb_substr($adaylar[0], 0, 500, 'UTF-8');
    }

    private function adresiNormalizeEt(string $adres): string
    {
        $adres = $this->temizle($adres);
        // Yerleşim yeri belgesindeki tablo başlıklarını ve adres kayıt numarasını ayıkla.
        $adres = preg_replace('/\b(?:adres tipi|adres türü|adres no|yerleşim yeri(?: adresi)?|yurtiçi)\b\s*[:|;-]*/iu', ' ', $adres) ?: $adres;
        $adres = preg_replace('/^\s*adres\s*[:|;-]\s*/iu', '', $adres) ?: $adres;
        // Yerleşim yeri belgesinin adresin hemen ardından gelen hukuki açıklamasını alma.
        $adres = preg_split('/(?:İŞBU YERLEŞİM YERİ|işbu yerleşim yeri|DİĞER ADRES BELGESİ|diğer adres belgesi|KİŞİNİN AİLE KÜTÜĞÜNDEKİ|kişinin aile kütüğündeki|KAYITLARI ESAS ALINARAK|kayıtları esas alınarak|ESAS ALINARAK|esas alınarak)/u', $adres, 2)[0] ?? $adres;
        if (preg_match('/\b\d{8,12}\b\s*[|;,-]*\s*((?:[\p{L}0-9ÇĞİÖŞÜçğıöşü.\/\- ]+)(?:MAH\.?|MAHALLESİ)[\s\S]*)/iu', $adres, $m)) {
            $adres = $m[1];
        } elseif (preg_match('/\b\d{8,12}\b\s*[|;,-]*\s*(.+)$/u', $adres, $m) && preg_match('/\b(?:MAH\.?|MAHALLESİ|CAD\.?|SK\.?|SOK\.?)/iu', $m[1])) {
            $adres = $m[1];
        }
        $adres = preg_replace('/\s*\|\s*/u', ' ', $adres) ?: $adres;
        return $this->temizle($adres);
    }

    private function adresDevaminiEkle(string $adres, array $devamSatirlari): string
    {
        if (preg_match('/(?:İÇ\s+)?KAPI\s+NO\s*:?[\s]*$/iu', $adres)) {
            foreach ($devamSatirlari as $devam) {
                if (!str_contains($adres, $devam)) return $this->temizle($adres . ' ' . $devam);
            }
        }
        return $adres;
    }

    private function adresPuani(string $adres): int
    {
        $puan = mb_strlen($adres, 'UTF-8');
        foreach (['MAH', 'SK', 'SOK', 'CAD', 'NO', 'KAPI', '/'] as $isaret) {
            if (mb_stripos($adres, $isaret, 0, 'UTF-8') !== false) $puan += 25;
        }
        if (preg_match('/(?:İÇ\s+)?KAPI\s+NO\s*:?\s*\d+/iu', $adres)) $puan += 60;
        if (preg_match('/İŞBU|işbu|AİLE KÜTÜĞÜ|aile kütüğü|ESAS ALINARAK|esas alınarak/u', $adres)) $puan -= 250;
        return $puan;
    }

    private function adresGecerliMi(string $adres): bool
    {
        $lower = mb_strtolower($adres, 'UTF-8');
        if (mb_strlen($adres, 'UTF-8') < 12 || preg_match('/kimlik\s*no|t\.c\.?\s*kimlik|doğum tarihi/iu', $lower)) return false;
        return (bool) preg_match('/\b(mah\.?|mahallesi|cad\.?|caddesi|sok\.?|sokağı|sk\.?|bulvarı|no\s*:|köyü)\b/iu', $adres);
    }

    private function alanGuveni(string $alan, string $deger, int $ocrGuveni): int
    {
        $puan = $ocrGuveni;
        if ($alan === 'tc_kimlik_no') $puan += $this->tcDogrula($deger) ? 10 : -35;
        elseif ($alan === 'dogum_tarihi') $puan += preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $deger) ? 7 : -20;
        elseif ($alan === 'email_adresi') $puan += filter_var($deger, FILTER_VALIDATE_EMAIL) ? 8 : -25;
        elseif (in_array($alan, ['cep_telefonu', 'cep_telefonu_2'], true)) $puan += preg_match('/^05\d{9}$/', $deger) ? 8 : -25;
        elseif ($alan === 'adres') $puan += $this->adresGecerliMi($deger) ? 3 : -30;
        elseif ($alan === 'ehliyet_sinifi') $puan += 20;
        return max(10, min(99, $puan));
    }

    private function tcDogrula(string $tc): bool
    {
        if (!preg_match('/^[1-9]\d{10}$/', $tc)) return false;
        $d = array_map('intval', str_split($tc));
        $onuncu = ((($d[0]+$d[2]+$d[4]+$d[6]+$d[8]) * 7) - ($d[1]+$d[3]+$d[5]+$d[7])) % 10;
        if ($onuncu < 0) $onuncu += 10;
        return $d[9] === $onuncu && $d[10] === array_sum(array_slice($d, 0, 10)) % 10;
    }

    private function belgeTurunuBul(string $metin): string
    {
        $text = mb_strtolower($metin, 'UTF-8');
        $kurallar = [
            'ehliyet' => ['sürücü belgesi', 'driving licence', 'driving license'],
            'ikametgah' => ['yerleşim yeri', 'ikametgah', 'ikametgâh'],
            'adli_sicil_kaydi' => ['adli sicil'], 'nufus_kayit_ornegi' => ['nüfus kayıt örneği', 'aile nüfus'],
            'saglik_raporu' => ['sağlık raporu', 'hekim raporu'], 'kimlik' => ['kimlik kartı', 'identity card', 'türkiye cumhuriyeti kimlik'],
            'diploma' => ['diploma', 'mezuniyet belgesi'], 'cv' => ['özgeçmiş', 'curriculum vitae'],
            'sertifika' => ['sertifika', 'certificate'], 'sozlesme' => ['iş sözleşmesi', 'hizmet sözleşmesi'],
            'gizlilik_taahhutnamesi' => ['gizlilik taahhütnamesi', 'gizlilik sözleşmesi'],
        ];
        foreach ($kurallar as $tur => $ifadeler) {
            foreach ($ifadeler as $ifade) if (str_contains($text, $ifade)) return $tur;
        }
        return 'diger';
    }

    private function evrakAdi(string $tur): string
    {
        $adlar = ['ehliyet'=>'Ehliyet','ikametgah'=>'İkametgah','adli_sicil_kaydi'=>'Adli Sicil Kaydı','nufus_kayit_ornegi'=>'Nüfus Kayıt Örneği','gizlilik_taahhutnamesi'=>'Gizlilik Taahhütnamesi','sozlesme'=>'Sözleşme','kimlik'=>'Kimlik','diploma'=>'Diploma','cv'=>'CV','saglik_raporu'=>'Sağlık Raporu','sertifika'=>'Sertifika','diger'=>'Diğer Personel Evrakı'];
        return $adlar[$tur] ?? 'Personel Evrakı';
    }

    private function dogrula(array $file): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) ($file['tmp_name'] ?? ''))) {
            throw new Exception('Belge dosyası yüklenemedi.');
        }
        if ((int) ($file['size'] ?? 0) > 10 * 1024 * 1024) throw new Exception('Belge en fazla 10 MB olabilir.');
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($mime, self::IZINLI_MIME, true)) throw new Exception('Yalnızca PDF, JPG, PNG, WEBP veya HEIC yükleyebilirsiniz.');
        return $mime;
    }

    private function dosyalariDuzenle(array $files): array
    {
        if (!isset($files['name'])) return [];
        if (!is_array($files['name'])) return [$files];
        $result = [];
        foreach ($files['name'] as $i => $name) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) continue;
            $result[] = ['name'=>$name,'type'=>$files['type'][$i]??'','tmp_name'=>$files['tmp_name'][$i]??'','error'=>$files['error'][$i]??UPLOAD_ERR_NO_FILE,'size'=>$files['size'][$i]??0];
        }
        return $result;
    }

    private function temizle(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/^[\s:;|_-]+|[\s:;|_-]+$/u', '', $value) ?: $value) ?: $value);
    }
}
