<?php

namespace App\Service;

use App\Helper\RichTextSanitizer;
use App\Model\PersonelIcralariModel;
use App\Model\PersonelModel;
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
        if (mb_strlen($instruction, 'UTF-8') > 4000) {
            throw new Exception('Talimat en fazla 4000 karakter olabilir.');
        }
        $hasFile = !empty($file['tmp_name']) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;
        if (!$hasFile && $instruction === '') {
            throw new Exception('Gelen evrakı yükleyin veya ne yazılacağını belirtin.');
        }
        $okuyucu = new AiBelgeOkuyucuService();
        [$apiKey, $model] = $okuyucu->ayarlar();

        $belgeIcerigi = null;
        $analiz = [];
        if ($hasFile) {
            $mime = $okuyucu->dogrula($file);
            $belgeIcerigi = fn(string $prompt): array => $okuyucu->kullaniciIcerigi($prompt, $file, $mime);
            $analiz = $this->belgeyiAnalizEt($okuyucu, $apiKey, $model, $belgeIcerigi);
        }

        $kayit = $this->sirketKayitlari($analiz);
        $prompt = $this->cevapPromptu($analiz, $kayit, $instruction);
        $userContent = $belgeIcerigi ? $belgeIcerigi($prompt) : [['type' => 'text', 'text' => $prompt]];

        $result = $okuyucu->jsonIste(
            $apiKey,
            $model,
            'Sen Türkiye’de resmî, akademik ve hukuki yazışma hazırlayan uzman bir evrak asistanısın. Yalnızca kaynak belgede, "ŞİRKET KAYITLARI" bölümünde ve kullanıcı talimatında bulunan olgularla çalışırsın. Bu kaynaklarda bulunmayan hiçbir olguyu (işten ayrılma, tutar, tarih, maaş, kesinti, sayı) uydurmaz, tahmin etmezsin; bilinmeyen bilgi için köşeli parantezli yer tutucu ([MAAŞ TUTARI] gibi) kullanırsın. Hukuki kaynak ve madde numarasını yalnızca belgede geçiyorsa anarsın. Yalnızca geçerli JSON üretirsin.',
            $userContent,
            0.1
        );
        $institution = trim((string) ($result['kurum_adi'] ?? ''));
        if ($institution === '') {
            $institution = trim((string) ($analiz['gonderen_kurum'] ?? ''));
        }
        $address = trim((string) ($result['muhatap_adres'] ?? ''));
        $looksLikeInstitution = preg_match('/\b(dairesi|müdürlüğü|başkanlığı|bakanlığı|belediyesi|rektörlüğü|mahkemesi|kurumu|şirketi)\b/iu', $address) === 1;
        $looksLikePostalAddress = preg_match('/\b(cadde(?:si)?|sokak|mah(?:allesi)?|bulvar(?:ı)?|no\s*[:.]?|kat\s*[:.]?|apt\.?|posta kodu)\b|\d+/iu', $address) === 1;
        if ($address !== '' && $looksLikeInstitution && !$looksLikePostalAddress) {
            if (mb_strlen($address, 'UTF-8') > mb_strlen($institution, 'UTF-8')) {
                $institution = $address;
            }
            $address = '';
        }
        $html = RichTextSanitizer::sanitize((string) ($result['aciklama_html'] ?? ''));
        $uyarilar = array_merge($kayit['uyarilar'], $this->kuralUyarilari(strip_tags($html), (string) $kayit['senaryo']));
        if (preg_match('/\[[^\]]{2,60}\]/u', strip_tags($html)) === 1) {
            $uyarilar[] = 'Metinde köşeli parantezle işaretli alanlar var; şirket kayıtlarında bulunamayan bilgileri kaydetmeden önce doldurunuz.';
        }
        return [
            'konu' => trim((string) ($result['konu'] ?? '')),
            'kurum_adi' => $institution,
            'muhatap_alt_birim' => trim((string) ($result['muhatap_alt_birim'] ?? '')),
            'muhatap_adres' => $address,
            'ilgiler' => $this->satirlar($result['ilgiler'] ?? ''),
            'aciklama_html' => $html,
            'analiz' => [
                'evrak_turu' => (string) ($analiz['evrak_turu_adi'] ?? ($analiz['evrak_turu'] ?? '')),
                'dosya_no' => (string) ($analiz['dosya_no'] ?? ''),
                'personel' => $kayit['personel_adi'],
                'calisma_durumu' => $kayit['calisma_durumu'],
            ],
            'uyarilar' => array_values(array_unique($uyarilar)),
        ];
    }

    private function belgeyiAnalizEt(AiBelgeOkuyucuService $okuyucu, string $apiKey, string $model, callable $icerik): array
    {
        $prompt = "Yüklenen gelen resmî evrakı oku ve yalnızca belgede açıkça yazan bilgileri çıkar; belgede olmayan alanı boş bırak. "
            . "Şu anahtarlarla JSON döndür: "
            . "evrak_turu (maas_haciz | haciz_ihbari | bilgi_belge_talebi | tebligat | ihtar | diger), "
            . "evrak_turu_adi (belgedeki başlık, ör. Maaş Haciz Müzekkeresi), gonderen_kurum (tam resmî ad), gonderen_alt_birim, tarih, dosya_no (esas/dosya/sayı numarası), "
            . "alacakli, borclu_adi, borclu_tc (11 haneli, yalnızca rakam), borc_miktari (belgedeki metin), "
            . "haciz_orani (belgede kesinti oranı yazıyorsa, ör. 1/4), "
            . "talepler (belgenin muhataptan istediği her işlem için kısa madde dizisi), cevap_suresi (belgede süre varsa), "
            . "muhatap_firma (belgede muhatap olarak geçen şirket adı).";
        try {
            $sonuc = $okuyucu->jsonIste(
                $apiKey,
                $model,
                'Sen resmî yazıları yapılandırılmış veriye dönüştüren bir belge okuyucusun. Yalnızca belgede yazanı çıkarırsın, tahmin etmezsin. Yalnızca geçerli JSON üretirsin.',
                $icerik($prompt),
                0
            );
        } catch (\Throwable $e) {
            error_log('Evrak AI analiz hatası: ' . $e->getMessage());
            return [];
        }
        $sonuc['borclu_tc'] = preg_replace('/\D+/', '', (string) ($sonuc['borclu_tc'] ?? ''));
        return $sonuc;
    }

    private function sirketKayitlari(array $analiz): array
    {
        $kayit = ['metin' => '', 'personel_adi' => '', 'calisma_durumu' => '', 'senaryo' => 'kayit_yok', 'uyarilar' => []];
        $tc = (string) ($analiz['borclu_tc'] ?? '');
        $ad = trim((string) ($analiz['borclu_adi'] ?? ''));
        if ($tc === '' && $ad === '') {
            return $kayit;
        }

        $personel = null;
        try {
            $personelModel = new PersonelModel();
            if (strlen($tc) === 11) {
                $bulunan = $personelModel->findByTc($tc);
                $personel = $bulunan[0] ?? null;
            }
            if (!$personel && $ad !== '') {
                $bulunan = $personelModel->findByAdiSoyadi($ad);
                $personel = $bulunan ? $personelModel->find((int) $bulunan->id) : null;
            }
        } catch (\Throwable $e) {
            error_log('Evrak AI personel arama hatası: ' . $e->getMessage());
            $personel = null;
        }

        if (!$personel) {
            $kayit['metin'] = "Belgede adı geçen kişi ({$ad}) şirket personel kayıtlarında BULUNAMADI. Kişinin şirketimizde çalıştığını veya çalışmadığını belirtme; yalnızca kayıtlarda eşleşme bulunamadığını bildir.";
            $kayit['uyarilar'][] = 'Belgedeki kişi personel kayıtlarında bulunamadı; çalışma durumu yazıya dahil edilmedi.';
            return $kayit;
        }

        $cikis = trim((string) ($personel->isten_cikis_tarihi ?? ''));
        $aktif = $cikis === '' || $cikis === '0000-00-00';
        $giris = trim((string) ($personel->ise_giris_tarihi ?? ''));
        $belgeTs = $this->belgeTarihi((string) ($analiz['tarih'] ?? ''));
        $cikisTs = $aktif ? false : strtotime($cikis);
        if ($aktif) {
            $senaryo = 'calisiyor';
        } elseif ($belgeTs === null || $cikisTs === false) {
            $senaryo = 'ayrildi_tarih_belirsiz';
        } elseif ($cikisTs < $belgeTs) {
            $senaryo = 'muzekkere_oncesi_ayrildi';
        } else {
            $senaryo = 'muzekkere_sonrasi_ayrildi';
        }
        $kayit['senaryo'] = $senaryo;

        $durumSatiri = match ($senaryo) {
            'calisiyor' => 'Çalışma durumu: ŞİRKETİMİZDE HALEN ÇALIŞMAKTADIR (işten ayrılmamıştır).',
            'muzekkere_oncesi_ayrildi' => 'Çalışma durumu: İŞTEN AYRILMIŞTIR; işten çıkış tarihi ' . $this->tarih($cikis) . ', müzekkere tarihinden (' . date('d.m.Y', (int) $belgeTs) . ') ÖNCEDİR. Müzekkere tarihinde şirket çalışanı değildir; bu müzekkere kapsamında maaşından kesinti YAPILMAMIŞTIR ve YAPILMAYACAKTIR. "Kesinti yapılacaktır", "hesaba yatırılacaktır" gibi gelecek zamanlı taahhüt YAZMA.',
            'muzekkere_sonrasi_ayrildi' => 'Çalışma durumu: İŞTEN AYRILMIŞTIR; işten çıkış tarihi ' . $this->tarih($cikis) . ', müzekkere tarihinden (' . date('d.m.Y', (int) $belgeTs) . ') SONRADIR. Müzekkere tarihinden ayrılışa kadar geçen sürede kesinti yapılıp yapılmadığı yalnızca aşağıdaki icra kaydından alınır; kayıtta yoksa tutarı [KESİLEN TUTAR] yer tutucusuyla bırak.',
            default => 'Çalışma durumu: İŞTEN AYRILMIŞTIR, işten çıkış tarihi ' . $this->tarih($cikis) . '. Müzekkere tarihi okunamadığından ayrılışın müzekkereden önce mi sonra mı olduğu belirsizdir; bunu yazıda kesinleştirme, [MÜZEKKERE TARİHİ] yer tutucusu kullan.',
        };
        $satirlar = [
            'Personel: ' . $personel->adi_soyadi,
            'Görevi: ' . (trim((string) ($personel->gorev ?? '')) ?: 'kayıtlı değil'),
            'İşe giriş tarihi: ' . $this->tarih($giris),
            $durumSatiri,
        ];
        if (!$aktif) {
            $satirlar[] = 'Ayrılış tarihi itibarıyla personele ait ödenmemiş ücret/izin/kıdem/ihbar alacağı bilgisi bu kayıtlardan alınmamıştır; haciz bu alacaklara da uygulanacağından yazıda "[AYRILIŞ ALACAĞI DURUMU]" yer tutucusuyla alacak olup olmadığını belirt.';
        }

        $dosyaNorm = preg_replace('/\s+/', '', trim((string) ($analiz['dosya_no'] ?? '')));
        $icralar = (new PersonelIcralariModel())->getPersonelIcralariWithKesintiler((int) $personel->id);
        $eslesen = null;
        foreach ($icralar as $icra) {
            if ($dosyaNorm !== '' && preg_replace('/\s+/', '', (string) $icra->dosya_no) === $dosyaNorm) {
                $eslesen = $icra;
                break;
            }
        }
        if ($eslesen) {
            $satirlar[] = sprintf(
                'Bu dosya şirket kayıtlarında mevcut: durum=%s, toplam borç=%s TL, bugüne kadar kesilen=%s TL, kalan=%s TL.',
                (string) $eslesen->durum,
                number_format((float) $eslesen->toplam_borc, 2, ',', '.'),
                number_format((float) $eslesen->toplam_kesilen, 2, ',', '.'),
                number_format((float) $eslesen->kalan_tutar, 2, ',', '.')
            );
        } else {
            $satirlar[] = 'Bu dosya numarası için şirket kayıtlarında henüz icra kaydı yoktur; kesinti yapıldığını belirtme.';
        }
        $digerleri = array_filter($icralar, fn($i) => $i !== $eslesen && in_array($i->durum, ['devam_ediyor', 'bekliyor'], true));
        if ($digerleri !== []) {
            $satirlar[] = 'Aynı personelin sırada bekleyen/devam eden başka ' . count($digerleri) . ' icra dosyası vardır (ayrıntı verme; yalnızca sıra nedeniyle kesintinin gecikebileceğini belirtebilirsin).';
        }
        $satirlar[] = 'Aylık maaş/ücret tutarı bu kayıtlardan alınmamıştır; belge maaş tutarının bildirilmesini istiyorsa [AYLIK NET MAAŞ TUTARI] yer tutucusunu kullan, istemiyorsa maaş tutarından hiç söz etme.';

        $kayit['metin'] = implode("\n", $satirlar);
        $kayit['personel_adi'] = (string) $personel->adi_soyadi;
        $kayit['calisma_durumu'] = $aktif ? 'Çalışıyor' : 'Ayrılmış (' . $this->tarih($cikis) . ')';
        if (!$aktif) {
            $kayit['uyarilar'][] = 'Personel kayıtlarda işten ayrılmış görünüyor; ayrılış tarihine göre yazıyı kontrol ediniz.';
        }
        return $kayit;
    }

    private function cevapPromptu(array $analiz, array $kayit, string $instruction): string
    {
        $turRehberi = [
            'maas_haciz' => 'Bu bir maaş haczi müzekkeresidir. Cevap, ŞİRKET KAYITLARI’ndaki çalışma durumuna göre yazılır: (a) halen çalışıyorsa müzekkerenin işleme alındığını, kesintinin personelin net ücretinin belgedeki oranı (genellikle dörtte biri; oran borç tutarına değil ücrete uygulanır) üzerinden yapılıp dosya numarası belirtilerek icra dairesinin hesabına yatırılacağını bildir; (b) müzekkere tarihinden önce ayrılmışsa müzekkere tarihinde şirket çalışanı olmadığını, bu nedenle kesinti yapılmadığını ve yapılmayacağını, ayrılış tarihini ve ayrılışta ödenmemiş alacak olup olmadığını (haciz bu alacaklara da uygulanır) açıkça bildir; (c) müzekkere sonrası ayrılmışsa ayrılış tarihini, o tarihe kadar yapılan kesinti ve yatırımları ile ayrılışta kalan alacağa ilişkin durumu bildir. Belge maaş tutarını soruyorsa tutarı yer tutucuyla belirt, sormuyorsa maaş tutarından söz etme. Kanun maddesini yalnızca belgede geçiyorsa an ve “İİK m. 83 ve 355” gibi her maddeyi ayrı yaz; “83/355” biçiminde bitişik yazma. Ayrılmış kişiye “eski personelimiz” de.',
            'haciz_ihbari' => 'Bu bir haciz ihbarnamesidir. Cevap, şirketin borçlu ile ilgili borcu/alacağı olup olmadığını kayıtlara göre belirtmelidir.',
            'bilgi_belge_talebi' => 'Bu bir bilgi/belge talebidir. Cevap, istenen her bilgiyi ayrı madde olarak yanıtlamalı, kayıtlarda bulunmayanı yer tutucuyla işaretlemelidir.',
        ];
        $rehber = $turRehberi[$analiz['evrak_turu'] ?? ''] ?? 'Belgenin talebini tespit et ve her talebe sırayla cevap ver.';

        $prompt = '';
        if ($analiz !== []) {
            $prompt .= "BELGE ÖZETİ (otomatik okundu):\n" . json_encode($analiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
        }
        $prompt .= "ŞİRKET KAYITLARI:\n" . ($kayit['metin'] !== '' ? $kayit['metin'] : 'Şirket kayıtlarından bu belgeyle eşleşen bilgi bulunamadı.') . "\n\n";
        $prompt .= "KULLANICI TALİMATI:\n" . ($instruction !== '' ? $instruction : 'Ek talimat yok; belgenin talebine ve şirket kayıtlarına uygun cevabı hazırla.') . "\n\n";
        $prompt .= "GÖREV: Gelen evrakı (varsa ekteki belge metni/görüntüsü) dikkatle incele ve ona verilecek resmî cevap yazısını hazırla. {$rehber}\n"
            . "KESİN KURALLAR:\n"
            . "1) Yazı, belgedeki gönderen kuruma, dosya numarasına ve talebe doğrudan atıf yapmalı; genel geçer, belgeyle ilgisiz cümle kurma.\n"
            . "2) 'ŞİRKET KAYITLARI' ile çelişen veya orada bulunmayan hiçbir olguyu yazma; çalışma durumu, ayrılış, maaş, kesinti, tutar ve tarihleri yalnızca belge, kayıtlar veya kullanıcı talimatından al. Kullanıcı talimatı kayıtlarla çelişirse talimatı esas al.\n"
            . "3) Bilinmeyen zorunlu bilgi için köşeli parantezli yer tutucu kullan.\n"
            . "4) Yazı giriş-gelişme-sonuç mantığında, birbiriyle bağlantılı en az üç ayrı paragraftan oluşmalı; 'Giriş', 'Gelişme', 'Sonuç' başlıkları yazılmamalı.\n"
            . "5) Giriş paragrafı zorunlu olarak 'İlgi yazı ile' sözleriyle başlamalı ve belgenin tarihine, dosya numarasına ve kişisine atıf yapmalı.\n"
            . "6) Son paragraf yalnızca 'Gereğini arz ederim.' cümlesiyle bitmeli; 'Gereğini bilgilerinize arz ederim' gibi iki kalıbı karıştırma.\n"
            . "7) kurum_adi cevabın gönderileceği kurumun belgede yazan tam resmî adıdır (ör. icra dairesinin adı); alt birim ve adres ayrı alanlara yazılır, kurum adını adres alanına koyma.\n"
            . "8) konu kısa ve belgeye özgü olmalı (ör. 'Arif Sezer Yılmaz Maaş Haczi Hakkında'); ilgiler alanına belgenin tarih ve sayısını yaz.\n"
            . "9) aciklama_html yalnızca p, br, strong, b, em, i, u, ul, ol, li ve table/tr/td/th etiketlerini kullanabilir.\n"
            . "10) Bu yönergelerdeki koşullu ifadeleri ('belge ... talep ediyorsa', 'varsa') yazıya kopyalama; yazıda her cümle kesin ve tamamlanmış olmalı. Yer tutucuyu cümle içinde anlatma ('... olarak belirtilmiştir' yazma); yer tutucu doğrudan olgunun yerine yazılır.\n"
            . "11) Gelecekte bildirim vaat etme ('bilgilendireceğiz' yazma); bildirilmesi gereken bilgiyi bu yazıda ver.\n"
            . "12) Kanun atıflarında her maddeyi ayrı yaz ('İİK m. 83 ve 355'); '83/355' gibi bitişik yazma. Haciz oranı borca değil maaşa/ücrete uygulanır.\n"
            . "13) Gövde paragraflarını numaralı veya düz paragraf olarak, 'Sayın' veya hitap kalıbı kullanmadan, resmî ve edilgen anlatımla yaz; personelden 'personelimiz' değil, ayrılmışsa 'eski personelimiz' diye söz et.\n"
            . "Şu anahtarlarla JSON nesnesi döndür: konu, kurum_adi, muhatap_alt_birim, muhatap_adres, ilgiler, aciklama_html.";
        return $prompt;
    }

    private function belgeTarihi(string $tarih): ?int
    {
        $tarih = trim($tarih);
        if (preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $tarih, $m) === 1 && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return mktime(0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3]);
        }
        if (preg_match('/(\d{4})-(\d{2})-(\d{2})/', $tarih, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return mktime(0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
        }
        return null;
    }

    private function kuralUyarilari(string $metin, string $senaryo): array
    {
        $uyarilar = [];
        if (preg_match('/\d+\s*\/\s*355/u', $metin) === 1) {
            $uyarilar[] = 'Kanun atfı "83/355" biçiminde yazılmış; "İİK m. 83 ve 355" biçimine çevriniz.';
        }
        if (preg_match('/borç(?:\s+miktarının|un)\s+(?:1\/4|dörtte\s+biri)/iu', $metin) === 1) {
            $uyarilar[] = 'Kesinti oranı borca göre yazılmış; haciz borcun değil maaşın (net ücretin) dörtte biri üzerinden yapılır, düzeltiniz.';
        }
        if (preg_match('/(?:talep\s+ediyorsa|istiyorsa|ise\s+bu\s+tutar|olarak\s+belirtilmiştir)/iu', $metin) === 1) {
            $uyarilar[] = 'Metinde koşullu veya talimat kaynaklı bir ifade kalmış olabilir; resmî yazıda kesin ifade kullanınız.';
        }
        if (preg_match('/Gereğini\s+bilgilerinize/iu', $metin) === 1) {
            $uyarilar[] = 'Kapanış "Gereğini arz ederim." biçiminde olmalıdır.';
        }
        if (in_array($senaryo, ['muzekkere_oncesi_ayrildi', 'ayrildi_tarih_belirsiz'], true)
            && preg_match('/kesinti\s+yap(?:ıl)?acak|yatırılacak|bilgilendireceğiz/iu', $metin) === 1) {
            $uyarilar[] = 'Personel işten ayrılmış görünüyor ancak metinde gelecek zamanlı kesinti/bilgilendirme taahhüdü var; kontrol ediniz.';
        }
        return $uyarilar;
    }

    private function tarih(string $tarih): string
    {
        $zaman = $tarih !== '' ? strtotime($tarih) : false;
        return $zaman ? date('d.m.Y', $zaman) : 'kayıtlı değil';
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
