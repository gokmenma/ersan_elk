<?php

namespace App\Service;

use App\Helper\RichTextSanitizer;
use Exception;

final class EvrakAiTaslakService
{
    public function reviseSelection(string $selectedText, string $instruction, string $documentContext = ''): string
    {
        $selectedText = trim($selectedText);
        $instruction = trim($instruction);
        if ($selectedText === '') {
            throw new Exception('Düzenlenecek metni seçiniz.');
        }
        if ($instruction === '') {
            throw new Exception('Seçili metnin nasıl düzenleneceğini yazınız.');
        }
        if (mb_strlen($selectedText, 'UTF-8') > 12000 || mb_strlen($instruction, 'UTF-8') > 2000) {
            throw new Exception('Seçili metin veya düzenleme talimatı çok uzun.');
        }

        $okuyucu = new AiBelgeOkuyucuService();
        [$apiKey, $model] = $okuyucu->ayarlar();
        $context = mb_substr(trim(strip_tags($documentContext)), 0, 20000, 'UTF-8');
        $prompt = "Aşağıdaki seçili resmî yazı bölümünü kullanıcının talimatına göre yeniden düzenle. "
            . "Yalnızca seçili bölümün yerine konulacak metni üret. Kişi ve kurum adlarını, tarihleri, tutarları, esas ve dosya numaralarını kullanıcı açıkça istemedikçe değiştirme veya uydurma. "
            . "Metni resmî, akademik, hukuki, açık ve dil bilgisi bakımından doğru hâle getir; belgenin genel bağlamıyla anlam bağlantısını koru. "
            . "HTML çıktısında yalnızca p, br, strong, b, em, i, u, ul, ol ve li etiketlerini kullan. {\"duzenlenmis_html\":\"...\"} biçiminde JSON döndür.\n\n"
            . "Kullanıcı talimatı:\n{$instruction}\n\nSeçili metin:\n{$selectedText}"
            . ($context !== '' ? "\n\nBelgenin genel bağlamı:\n{$context}" : '');
        $result = $okuyucu->jsonIste(
            $apiKey,
            $model,
            'Sen resmî, akademik ve hukuki Türkçe metinleri düzenleyen uzman bir yazışma editörüsün. Verilen olguları değiştirmez ve yalnızca geçerli JSON üretirsin.',
            [['type' => 'text', 'text' => $prompt]],
            0.15
        );
        $html = RichTextSanitizer::sanitize((string) ($result['duzenlenmis_html'] ?? ''));
        if (trim(strip_tags($html)) === '') {
            throw new Exception('Yapay zekâ düzenlenmiş bir metin döndürmedi.');
        }
        return $html;
    }

    public function create(array $file = [], string $instruction = ''): array
    {
        $instruction = trim($instruction);
        if ($instruction === '') {
            throw new Exception('Yapay zekâya ne yapmak istediğinizi yazınız.');
        }
        if (mb_strlen($instruction, 'UTF-8') > 4000) {
            throw new Exception('Talimat en fazla 4000 karakter olabilir.');
        }
        $okuyucu = new AiBelgeOkuyucuService();
        [$apiKey, $model] = $okuyucu->ayarlar();

        $prompt = "Kullanıcının vermek istediği cevap veya yapmak istediği işlem:\n{$instruction}\n\n"
            . "Yüklenen gelen evrakı incele. Gönderen kurumu, evrak türünü, tarih/sayı bilgilerini, konuyu ve bizden istenen talebi tespit et. Bu evraka verilecek resmî cevap yazısını hazırla. "
            . "Yazı giriş-gelişme-sonuç mantığında, birbiriyle bağlantılı en az üç ayrı paragraftan oluşmalıdır; ancak metinde 'Giriş', 'Gelişme' ve 'Sonuç' başlıkları bulunmamalıdır. "
            . "GİRİŞ PARAGRAFI zorunlu olarak 'İlgi yazı ile' sözleriyle başlamalıdır. "
            . "GELİŞME BÖLÜMÜNDE şirketimiz/kurumumuz kayıtlarındaki mevcut durum, yapılan inceleme, somut bulgular ve kullanıcının verdiği bilgiler açıklanmalıdır. "
            . "SONUÇ PARAGRAFI uygun kapanış cümlesiyle bitmelidir. "
            . "MUHATAP ALANLARINI ŞU KESİN KURALLARLA DOLDUR: kurum_adi, cevabın gönderileceği kurum veya kişinin belgede yazan tam resmî adıdır. "
            . "aciklama_html yalnızca p, br, strong, b, em, i, u, ul, ol, li ve table/tr/td/th etiketlerini kullanabilir. "
            . "Şu anahtarlarla JSON nesnesi döndür: konu, kurum_adi, muhatap_alt_birim, muhatap_adres, ilgiler, aciklama_html.";

        if (!empty($file['tmp_name']) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $mime = $okuyucu->dogrula($file);
            $userContent = $okuyucu->kullaniciIcerigi($prompt, $file, $mime);
        } else {
            $userContent = [['type' => 'text', 'text' => $prompt]];
        }

        $result = $okuyucu->jsonIste(
            $apiKey,
            $model,
            'Sen Türkiye’de resmî, akademik ve hukuki yazışma hazırlayan uzman bir evrak asistanısın. Belge türünü veya gönderen kurumu varsaymaz, yalnızca kaynak belgede ve kullanıcı talimatında bulunan olgularla çalışır, hukuki kaynak ve madde numarası uydurmazsın. Yalnızca geçerli JSON üretirsin.',
            $userContent
        );
        $institution = trim((string) ($result['kurum_adi'] ?? ''));
        $address = trim((string) ($result['muhatap_adres'] ?? ''));
        $looksLikeInstitution = preg_match('/\b(dairesi|müdürlüğü|başkanlığı|bakanlığı|belediyesi|rektörlüğü|mahkemesi|kurumu|şirketi)\b/iu', $address) === 1;
        $looksLikePostalAddress = preg_match('/\b(cadde(?:si)?|sokak|mah(?:allesi)?|bulvar(?:ı)?|no\s*[:.]?|kat\s*[:.]?|apt\.?|posta kodu)\b|\d+/iu', $address) === 1;
        if ($address !== '' && $looksLikeInstitution && !$looksLikePostalAddress) {
            if (mb_strlen($address, 'UTF-8') > mb_strlen($institution, 'UTF-8')) {
                $institution = $address;
            }
            $address = '';
        }
        return [
            'konu' => trim((string) ($result['konu'] ?? '')),
            'kurum_adi' => $institution,
            'muhatap_alt_birim' => trim((string) ($result['muhatap_alt_birim'] ?? '')),
            'muhatap_adres' => $address,
            'ilgiler' => $this->satirlar($result['ilgiler'] ?? ''),
            'aciklama_html' => RichTextSanitizer::sanitize((string) ($result['aciklama_html'] ?? '')),
        ];
    }

    private function satirlar($deger): string
    {
        if (!is_array($deger)) {
            return trim((string) $deger);
        }

        $satirlar = [];
        foreach ($deger as $satir) {
            if (is_array($satir)) {
                $satir = implode(' ', array_filter($satir, 'is_scalar'));
            }
            $satir = trim((string) $satir);
            if ($satir !== '') {
                $satirlar[] = $satir;
            }
        }
        return implode("\n", $satirlar);
    }
}
