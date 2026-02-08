<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helper\Security;
use App\Helper\Helper;

class SgkViziteService
{
    private $serviceUrl = 'https://uyg.sgk.gov.tr/Ws_Vizite/services/ViziteGonder';
    private $kullaniciAdi;
    private $isyeriKodu;
    private $wsSifre;
    private $wsToken;
    private $tokenExpiresAt;
    private $activeUserKey;

    /**
     * Sınıfı başlatır.
     * Parametreler verilirse onları kullanır, verilmezse session'dan okumaya çalışır.
     *
     * @param string|null $kullaniciAdi Opsiyonel. Verilmezse session'dan alınır.
     * @param string|null $isyeriKodu   Opsiyonel. Verilmezse session'dan alınır.
     * @param string|null $wsSifre      Opsiyonel. Verilmezse session'dan alınır (şifrelenmemiş hali).
     * @throws Exception Gerekli bilgiler bulunamazsa hata fırlatır.
     */
    public function __construct(string $kullaniciAdi = null, string $isyeriKodu = null, string $wsSifre = null)
    {
        if (php_sapi_name() !== 'cli') { // 'cli' = Command Line Interface (Komut Satırı)
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        }

        //Eğer dışarıdan parametre gelirse onları kullan değilse session'dan al

        if ($kullaniciAdi === null) {
            $kullaniciAdi = $_SESSION['kullaniciAdi'] ?? null;
        }

        if ($isyeriKodu === null) {
            $isyeriKodu = $_SESSION['isyeriKodu'] ?? null;
        }

        if ($wsSifre === null) {
            $wsSifre = Security::decrypt($_SESSION['wsSifre'] ?? null);
        }


        // 1. Parametreleri ata:
        $this->kullaniciAdi = $kullaniciAdi;
        $this->isyeriKodu = $isyeriKodu;
        $this->wsSifre = $wsSifre;
        $this->wsToken = null;
        $this->tokenExpiresAt = null;



        //  3. Son kontrol: Tüm kontrollerden sonra bile bilgiler hala eksikse hata ver
        if (!$this->kullaniciAdi || !$this->isyeriKodu || !$this->wsSifre) {
            throw new Exception("SGK Servisi için gerekli kimlik bilgileri eksik veya bulunamadı.");
        }
    }

    /**
     * Tüm SOAP isteklerini yapan merkezi cURL fonksiyonu. (Geliştirilmiş Versiyon)
     */
    private function sendRequest($methodName, $params)
    {
        $paramXml = '';
        foreach ($params as $key => $value) {
            $paramXml .= "<{$key}>" . htmlspecialchars($value, ENT_XML1, 'UTF-8') . "</{$key}>";
        }

        $xml_request = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://service.com">
<SOAP-ENV:Body>
    <ns1:{$methodName}>
        {$paramXml}
    </ns1:{$methodName}>
</SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

        // wsLogin için SOAPAction genellikle boş veya tırnak işaretidir. Diğerleri için metot adını kullanırız.
        $soapAction = ($methodName === 'wsLogin') ? '""' : "\"{$methodName}\"";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->serviceUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml_request);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'Content-Length: ' . strlen($xml_request),
            'SOAPAction: ' . $soapAction // Dinamik SOAPAction
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        // Zaman aşımını ayarlamak iyi bir pratiktir
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $responseXml = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Hatası: " . curl_error($ch));
        }
        curl_close($ch);

        // Cevap boşsa veya bir HTML hata sayfasıysa
        if (empty($responseXml) || strpos(trim($responseXml), '<') !== 0) {
            throw new Exception("SGK sunucusundan geçerli bir XML cevabı alınamadı. Gelen cevap: " . $responseXml);
        }

        // Gelen XML'i parse edip obje haline getirelim (Daha sağlam bir yöntemle)
        $responseXml = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $responseXml);
        libxml_use_internal_errors(true); // XML hatalarını yakalamak için
        $xml = simplexml_load_string($responseXml);

        if ($xml === false) {
            throw new Exception("Sunucudan gelen XML parse edilemedi.");
        }

        $body = $xml->xpath('//soapenvBody')[0];
        $responseNode = $body->children()->children(); // wsLoginReturn, raporAramaTarihileReturn vb.
        return $responseNode;
    }

    private function getValidToken()
    {
        $currentUserKey = $this->kullaniciAdi . '|' . $this->isyeriKodu;

        // Eğer kullanıcı değişmişse token sıfırlansın
        if ($this->activeUserKey !== $currentUserKey) {
            $this->wsToken = null;
            $this->tokenExpiresAt = 0;
        }
        if (!$this->wsToken || time() > $this->tokenExpiresAt) {
            //echo "Yeni token alınıyor...\n";
            $params = [
                'kullaniciAdi' => $this->kullaniciAdi,
                'isyeriKodu' => $this->isyeriKodu,
                'isyeriSifresi' => $this->wsSifre, // WSDL'ye göre doğru parametre adı
            ];

            $response = $this->sendRequest('wsLogin', $params);
            //var_dump($response); // Gelen ham cevabı kontrol etmek için

            // =============================================================
            // ===                KRİTİK DÜZELTME BURADA                 ===
            // =============================================================
            // Gelen cevabın doğru katmanına erişiyoruz: wsLoginReturn
            if (isset($response->wsLoginReturn->sonucKod) && $response->wsLoginReturn->sonucKod == '0') {
                // Token'ı ve açıklamayı da doğru yoldan alıyoruz
                $this->wsToken = (string) $response->wsLoginReturn->wsToken;
                $this->tokenExpiresAt = time() + (29 * 60);
                $this->activeUserKey = $currentUserKey;

                //echo "Token başarıyla alındı: " . $this->wsToken . "\n";
            } else {
                // Hata mesajını da doğru yoldan alıyoruz
                $hataMesaji = isset($response->wsLoginReturn->sonucAciklama)
                    ? (string) $response->wsLoginReturn->sonucAciklama
                    : 'Bilinmeyen Hata';
                throw new Exception("Login başarısız: " . $hataMesaji);
            }
            // =============================================================
        }
        return $this->wsToken;
    }

    /**
     * Verilen SGK bilgilerinin geçerli olup olmadığını wsLogin ile test eder.
     * Sadece doğrulama amaçlıdır, token'ı saklamaz.
     * @return object Dönen cevap objesi (sonucKod ve sonucAciklama içerir).
     * @throws Exception
     */
    public function bilgileriDogrula(
        string $kullaniciAdi,
        string $isyeriKodu,
        string $isyeriSifresi
    ) {

        //Dışarıdan parametre gelirse onları kullan
        $this->kullaniciAdi = $kullaniciAdi;
        $this->isyeriKodu = $isyeriKodu;
        $this->wsSifre = $isyeriSifresi;

        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'isyeriSifresi' => $this->wsSifre, // WSDL'ye göre doğru parametre adı
        ];

        $response = $this->sendRequest('wsLogin', $params);

        // wsLoginReturn objesini doğrudan döndür
        return $response->wsLoginReturn;
    }

    /**
     * METOT 24: OnayliRaporlarTarihile (MANUEL DÖNÜŞÜM VERSİYONU)
     * Belirtilen tarih aralığında onaylanmış raporları getirir.
     * @param DateTime $tarih1 Başlangıç tarihi
     * @param DateTime $tarih2 Bitiş tarihi
     * @return array Her zaman onaylanmış raporların bulunduğu saf bir PHP dizisi döndürür.
     * @throws Exception
     */
    public function onayliRaporlariGetir(DateTime $tarih1, DateTime $tarih2)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tarih1' => $tarih1->format('d.m.Y'),
            'tarih2' => $tarih2->format('d.m.Y'),
        ];

        $response = $this->sendRequest('onayliRaporlarTarihile', $params);
        //var_dump($response); // Gelen ham cevabı kontrol etmek için
        $returnNode = $response->onayliRaporlarTarihileReturn;
        $sonucDizisi = []; // Döndüreceğimiz temiz dizi

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {

            // Onaylı raporların bulunduğu ana objeye erişiyoruz.
            if (isset($returnNode->onayliRaporlarTarihleBeanArray->OnayliRaporlarTarihleBean)) {

                $raporlarObject = $returnNode->onayliRaporlarTarihleBeanArray->OnayliRaporlarTarihleBean;

                // =============================================================
                // ===             KESİN ÇÖZÜM: MANUEL DÖNGÜ                 ===
                // =============================================================
                // Gelen SimpleXMLElement'in üzerinde doğrudan foreach ile dönüyoruz.
                // Eğer tek bir rapor varsa, döngü bir kez çalışır.
                // Eğer birden çok rapor varsa, döngü her rapor için çalışır.
                foreach ($raporlarObject as $rapor) {

                    // 1. ADIM: Her bir rapor objesini standart bir PHP dizisine çevirip
                    // önce bir değişkene atıyoruz.
                    $yeniRaporDizisi = [
                        'TCKIMLIKNO' => (string)$rapor->TCKIMLIKNO,
                        'AD' => (string)$rapor->AD,
                        'SOYAD' => (string)$rapor->SOYAD,
                        'SIGORTALIADSOYAD' => (string)$rapor->AD . ' ' . (string)$rapor->SOYAD,
                        'VAKAADI' => (string)$rapor->VAKAADI,
                        'POLIKLINIKTAR' => (string)$rapor->POLIKLINIKTAR,
                        'ISBASKONTTAR' => (string)$rapor->ISBASKONTTAR,
                        // Diğer tüm gerekli alanları buraya ekleyin...
                        'MEDULARAPORID' => (string)$rapor->MEDULARAPORID,
                        'RAPORTAKIPNO' => (string)$rapor->RAPORTAKIPNO,
                        'RAPORSIRANO' => (string)$rapor->RAPORSIRANO,
                        'TESISKODU' => (string)$rapor->TESISKODU,
                        'BRANSKODU' => (string)$rapor->BRANSKODU,
                        'RAPORDURUMADI' => (string)$rapor->RAPORDURUMADI,
                        'YATRAPBASTAR' => (string)$rapor->YATRAPBASTAR,
                        'YATRAPBITTAR' => (string)$rapor->YATRAPBITTAR,
                        'ABASTAR' => (string)$rapor->ABASTAR,
                        'ABITTAR' => (string)$rapor->ABITTAR,
                        'VAKA' => (string)$rapor->VAKA,
                    ];

                    // 2. ADIM: Oluşturduğumuz bu yeni diziyi, MEDULARAPORID'sini anahtar 
                    // olarak kullanarak session'a kaydediyoruz.
                    $_SESSION['rapor_fisleri'][$yeniRaporDizisi['MEDULARAPORID']] = $yeniRaporDizisi;

                    // 3. ADIM: Aynı yeni diziyi, fonksiyonun döndüreceği sonuç listesine ekliyoruz.
                    $sonucDizisi[] = $yeniRaporDizisi;
                }
                // =============================================================
            }
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '503') {
            // Rapor bulunamadıysa boş dizi döner, bu bir hata değildir.
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama)
                ? (string)$returnNode->sonucAciklama
                : 'Bilinmeyen bir hata oluştu.';
            throw new Exception("Onaylı raporlar getirilemedi: " . $hataMesaji);
        }

        return $sonucDizisi; // Her zaman doğru yapıda ve sayıda eleman içeren PHP dizisini döndür
    }

    // 2. Detayları almak için bu fonksiyon (EKRANTARIHI'ni getirecek olan)
    public function onayliRaporDetayGetir(string $medulaRaporId)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'medulaRaporId' => $medulaRaporId,
        ];

        $response = $this->sendRequest('onayliRaporlarDetay', $params);
        $returnNode = $response->onayliRaporlarDetayReturn;

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {
            // Çıktınıza göre, detay verisi 'tarihSorguBean' içinde geliyor.
            if (isset($returnNode->tarihSorguBean->TarihSorguBean)) {
                $detayObject = $returnNode->tarihSorguBean->TarihSorguBean;
                // Gelen tek bir nesneyi standart PHP dizisine çeviriyoruz
                return json_decode(json_encode($detayObject), true);
            }
            return null; // Başarılı ama detay verisi yok
        } else {
            throw new Exception("Rapor detayı getirilemedi (ID: {$medulaRaporId})");
        }
    }

    // SgkViziteService.php içinde

    public function raporDetayGetir(string $medulaRaporId)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'medulaRaporId' => $medulaRaporId, // Anahtarı burada kullanıyoruz
        ];

        // 'onayliRaporlarDetay' kapısını çalıyoruz (SOAP operasyonunu çağırıyoruz)
        $response = $this->sendRequest('onayliRaporlarDetay', $params);
        var_dump($response);
        $returnNode = $response->onayliRaporlarDetayReturn;

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {
            // Kapı açıldıktan sonra içerideki 'tarihSorguBean' odasını arıyoruz.
            // Burası, 'struct ekran' yapısını içeren yerdir.
            if (isset($returnNode->tarihSorguBean->TarihSorguBean)) {
                $detayObject = $returnNode->tarihSorguBean->TarihSorguBean;
                // İçerideki tüm veriyi alıp standart bir PHP dizisine çeviriyoruz.
                return json_decode(json_encode($detayObject), true);
            }
            return null; // Oda boş olabilir.
        } else {
            throw new Exception("Kapı açılamadı (Rapor detayı getirilemedi)");
        }
    }

    /**
     * METOT 2: RaporAramaTarihile (Sunucu Filtresiz Versiyon)
     * Belirtilen tarihten önceki TÜM vaka türlerindeki 100 raporu getirir.
     * @param DateTime $tarih Sorgulanacak tarih (bu tarihten öncekiler listelenir).
     * @return array Her zaman raporların bulunduğu bir dizi döndürür.
     * @throws Exception
     */
    public function raporlariGetir(DateTime $tarih, $arsiv = true)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tarih' => $tarih->format('d.m.Y'),
            // Vaka parametresini buradan kaldırdık!
        ];

        $response = $this->sendRequest('raporAramaTarihile', $params);
        $returnNode = $response->raporAramaTarihileReturn;
        $sonucDizisi = [];

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {
            if (isset($returnNode->raporAramaTarihleBeanArray->RaporAramaTarihleBean)) {
                $raporlarObject = $returnNode->raporAramaTarihleBeanArray->RaporAramaTarihleBean;

                foreach ($raporlarObject as $rapor) {
                    if ($arsiv == false && (string) $rapor->ARSIV == '1') {
                        continue;
                    }
                    // Cevapta gelen 'VAKA' alanı vaka kodunu (1, 2, 3, 4) içerir.
                    $sonucDizisi[] = [
                        'TCKIMLIKNO' => (string) $rapor->TCKIMLIKNO,
                        'AD' => (string) $rapor->AD,
                        'SOYAD' => (string) $rapor->SOYAD,
                        'VAKA' => (string) $rapor->VAKA, // Vaka Kodunu alıyoruz
                        'VAKAADI' => (string) $rapor->VAKAADI, // Vaka Adını alıyoruz
                        'POLIKLINIKTAR' => (string) $rapor->POLIKLINIKTAR,
                        'ISBASKONTTAR' => (string) $rapor->ISBASKONTTAR,
                        'ABITTAR' => (string) $rapor->ABITTAR,
                        'MEDULARAPORID' => (string) $rapor->MEDULARAPORID,
                        'RAPORTAKIPNO' => (string) $rapor->RAPORTAKIPNO,
                        'ARSIV' => (string) $rapor->ARSIV, // <-- BU SATIRIN OLDUĞUNDAN EMİN OLUN
                        'SIGORTALIADSOYAD' => (string) $rapor->AD . ' ' . (string) $rapor->SOYAD,
                        'RAPORDURUMU' => (string) $rapor->RAPORDURUMU,
                        'EKRANTARIHI' => (string) $rapor->EKRANTARIHI,
                        'TESISKODU' => (string) $rapor->TESISKODU,
                        'BRANSKODU' => (string) $rapor->BRANSKODU,
                        'TESISADI' => (string) $rapor->TESISADI,
                        'BRANSADI' => (string) $rapor->BRANSADI,
                    ];
                }
            }
            ;
            //Helper::dd($sonucDizisi);
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '503') {
            // Rapor bulunamadı.
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama) ? (string) $returnNode->sonucAciklama : 'Bilinmeyen Hata';
            throw new Exception("Raporlar getirilemedi: " . $hataMesaji);
        }

        // var_dump($sonucDizisi);
        return $sonucDizisi;
    }


    /**
     * SADECE RaporAramaTarihleBean Verisini Döndürür.
     * Bu metot, ham SimpleXMLElement nesnesini veya dizisini döndürür.
     * Veriyi işlemek ve diziye çevirmek, bu metodu çağıran koda aittir.
     * 
     * @param DateTime $tarih Sorgulanacak tarih (bu tarihten öncekiler listelenir).
     * @return SimpleXMLElement|array|null Raporları içeren nesne/dizi veya kayıt yoksa null.
     * @throws Exception
     */
    public function raporAramaTarihleGetir(DateTime $tarih)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tarih' => $tarih->format('d.m.Y'),
        ];

        $response = $this->sendRequest('raporAramaTarihile', $params);
        $returnNode = $response->raporAramaTarihileReturn;

        // var_dump($returnNode); // Gelen ham cevabı kontrol etmek için

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {

            // Raporların bulunduğu yola erişip, ham nesneyi doğrudan döndürüyoruz.
            if (isset($returnNode->raporAramaTarihleBeanArray->RaporAramaTarihleBean)) {
                return $returnNode->raporAramaTarihleBeanArray->RaporAramaTarihleBean;
            } else {
                // Sonuç başarılı ama içinde rapor yoksa null döndür.
                return null;
            }
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '503') { // 503 = Kayıt yok
            return null; // Rapor bulunamadıysa null döndür.
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama)
                ? (string) $returnNode->sonucAciklama
                : 'Bilinmeyen bir hata oluştu.';
            throw new Exception("Raporlar getirilemedi: " . $hataMesaji);
        }
    }



    /**
     * METOT 25: RaporOkunduKapat
     * Görülen bir raporu "okundu" olarak işaretler ve bir sonraki sorguda gelmesini engeller.
     * @param string $medulaRaporId Kapatılacak raporun ID'si
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function raporuKapat($medulaRaporId)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'medulaRaporId' => $medulaRaporId,
        ];

        $response = $this->sendRequest('raporOkunduKapat', $params);

        // Cevabı doğrudan döndürelim, işleme mantığı ana betikte olsun.
        return $response->raporOkunduKapatReturn;
    }

    /**
     * METOT 10: OnaylIptal
     * Daha önce onaylanmış bir raporun onayını (çalışmazlık bildirimini) iptal eder.
     * @param string $medulaRaporId Onayı iptal edilecek raporun Medula Rapor ID'si.
     * @param string|null $bildirimId Onaylama işleminden dönen Bildirim ID'si (genellikle opsiyonel).
     * @return object Dönen cevap objesi.
     * @throws Exception
     */
    public function raporOnayIptalEt(string $medulaRaporId, ?string $bildirimId = null)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'medulaRaporId' => $medulaRaporId,
            'bildirimId' => $bildirimId ?? '' // Eğer null ise boş string gönder
        ];

        $response = $this->sendRequest('onaylIptal', $params);

        return $response->onaylIptalReturn;
    }

    public function iletisimBilgileriniGetir()
    {

        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
        ];

        $response = $this->sendRequest('isverenIletisimBilgileriGoruntu', $params);

        $returnNode = $response->isverenIletisimBilgileriGoruntuReturn;

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {
            //echo "İletişim bilgileri başarıyla alındı.\n";

            // =============================================================
            // ===                NİHAİ DÜZELTME BURADA                  ===
            // =============================================================
            // Gelen cevaptaki iki katmanlı yola erişiyoruz.
            // Önce 'isverenIletisimBilgiBean' (küçük i) var mı diye bakıyoruz.
            if (isset($returnNode->isverenIletisimBilgiBean)) {
                // Sonra onun içinde 'IsverenIletisimBilgiBean' (büyük I) var mı diye bakıyoruz.
                $bilgiObjesi = $returnNode->isverenIletisimBilgiBean->IsverenIletisimBilgiBean;

                // Bu objenin tek bir kayıt mı yoksa dizi mi olduğunu kontrol edelim.
                // Genellikle tek kayıt için dizi yapısı [0] kullanılır.
                if (isset($bilgiObjesi[0])) {
                    return $bilgiObjesi[0];
                } else {
                    return $bilgiObjesi;
                }
            } else {
                // Sonuç başarılı ama içinde bilgi yoksa null döndür.
                return null;
            }
            // =============================================================

        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '501') {
            echo "Kayıtlı iletişim bilgisi bulunamadı.\n";
            return null;
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama)
                ? (string) $returnNode->sonucAciklama
                : 'Bilinmeyen bir hata oluştu.';
            throw new Exception("İletişim bilgileri alınamadı: " . $hataMesaji);
        }
    }

    /**
     * METOT 6: IsverenIletisimBilgileri
     * İşverenin e-posta ve telefon bilgilerini günceller.
     * @param string $eposta Yeni e-posta adresi
     * @param string $cepTel Yeni cep telefonu numarası (10 haneli, başında 0 olmadan)
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function iletisimBilgileriniGuncelle(string $eposta, string $cepTel)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'eposta' => $eposta,
            'cepTel' => $cepTel
        ];

        $response = $this->sendRequest('isverenIletisimBilgileri', $params);

        return $response->isverenIletisimBilgileriReturn;
    }


    /**
     * METOT 21: PersonelimDegildir
     * Seçilen raporun mevcut işverenle ilişkili olmadığını bildirir.
     * @param string $medulaRaporId
     * @param string $tcKimlikNo
     * @param string $vakaKodu
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function personelimDegilBildir(string $medulaRaporId, string $tcKimlikNo, string $vakaKodu)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tckNo' => $tcKimlikNo,
            'vaka' => $vakaKodu,
            'medulaRaporId' => $medulaRaporId,
        ];

        $response = $this->sendRequest('personelimDegildir', $params);

        // Gelen cevabın kök objesini döndür
        return $response->personelimDegildirReturn;
    }


    /** METOT 17: RaporOnay
     * Bir rapora "Çalıştı" veya "Çalışmadı" bildirimini yapar.
     * @param string $medulaRaporId
     * @param string $tcKimlikNo
     * @param string $vakaKodu
     * @param string $nitelikDurumu "0" (Çalışmamıştır) veya "1" (Çalışmıştır)
     * @param DateTime $tarih İş başı tarihi
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function raporuOnayla(
        string $medulaRaporId,
        string $tcKimlikNo,
        string $vakaKodu,
        string $nitelikDurumu,
        DateTime $tarih
    ) {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tckNo' => $tcKimlikNo,
            'vaka' => $vakaKodu,
            'medulaRaporId' => $medulaRaporId,
            'nitelikDurumu' => $nitelikDurumu,
            'tarih' => $tarih->format('d.m.Y'),
        ];

        $response = $this->sendRequest('raporOnay', $params);

        return $response->raporOnayReturn;
    }

    /**
     * METOT 17: MahsuplastirmaOnayListesiSorguTarihle (NİHAİ VE DOĞRU VERSİYON)
     * Belirtilen tarih aralığında mahsuplaştırılacak raporları listeler.
     * @param DateTime $tarih1 Başlangıç tarihi
     * @param DateTime $tarih2 Bitiş tarihi
     * @return array Mahsuplaştırılacak raporların bulunduğu saf bir PHP dizisi.
     * @throws Exception
     */
    public function mahsuplastirilacakRaporlariGetir(DateTime $tarih1, DateTime $tarih2)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tarih' => $tarih1->format('d.m.Y'),
            'tarih2' => $tarih2->format('d.m.Y'),
        ];

        $response = $this->sendRequest('mahsuplastirmaOnayListesiSorguTarihle', $params);
        $returnNode = $response->mahsuplastirmaOnayListesiSorguTarihleReturn;
        $sonucDizisi = [];

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {

            // =============================================================
            // ===                DOĞRU YOL BURADA                       ===
            // =============================================================
            // Raporların bulunduğu doğru yola (makbuzRaporBean -> MakbuzRaporBean) erişiyoruz.
            if (isset($returnNode->makbuzRaporBean->MakbuzRaporBean)) {

                $raporlarObject = $returnNode->makbuzRaporBean->MakbuzRaporBean;

                // Manuel döngü ile en sağlam dönüşümü yapıyoruz
                // Gelen verinin tekil mi çoğul mu olduğunu bu şekilde anlarız.
                foreach ($raporlarObject as $rapor) {
                    $sonucDizisi[] = [
                        'id' => (string) $rapor->id,
                        'tcKimlikNo' => (string) $rapor->tcKimlikNo,
                        'adiSoyadi' => (string) $rapor->adiSoyadi,
                        'odenenTutar' => (string) $rapor->odenenTutar,
                        'odemeBasTar' => (string) $rapor->odemeBasTar,
                        'odemeBitTar' => (string) $rapor->odemeBitTar,
                        'vakaAdi' => (string) $rapor->vakaAdi
                    ];
                }
            }
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '503') {
            // Rapor bulunamadı.
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama)
                ? (string) $returnNode->sonucAciklama
                : 'Bilinmeyen bir hata oluştu.';
            throw new Exception("Mahsuplaştırılacak raporlar getirilemedi: " . $hataMesaji);
        }

        return $sonucDizisi;
    }

    /**
     * METOT 19: MahsuplastirmaOnay
     * Seçilen bir rapor için mahsuplaşma onayını SGK'ya bildirir.
     * @param array $rapor Raporun tüm bilgilerini içeren dizi
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function mahsuplasmayiOnayla(array $rapor)
    {
        // Vaka Adı'ndan Vaka Kodu'nu bulmamız gerekiyor.
        $vakaKodlari = ['İş Kazası' => '1', 'Meslek Hastalığı' => '2', 'Hastalık' => '3', 'Analık' => '4'];
        $vakaKodu = $vakaKodlari[$rapor['vakaAdi']] ?? '3'; // Bulamazsa varsayılan olarak Hastalık

        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tckNo' => $rapor['tcKimlikNo'],
            'vakaAdi' => $rapor['vakaAdi'],
            'id' => $rapor['id'],
            'odemeBasTar' => $rapor['odemeBasTar'],
            'odemeBitTar' => $rapor['odemeBitTar'],
            'odenenTutar' => $rapor['odenenTutar']
        ];

        $response = $this->sendRequest('mahsuplastirmaOnay', $params);

        return $response->mahsuplastirmaOnayReturn;
    }


    /**
     * METOT 20: MahsuplastirmaOnaylananOdemeListesiSorguTarihle
     * Belirtilen tarih aralığında mahsuplaşması onaylanmış ödemeleri listeler.
     * @param DateTime $tarih1 Başlangıç tarihi
     * @param DateTime $tarih2 Bitiş tarihi
     * @return array Onaylanmış mahsuplaşma kayıtlarının bulunduğu saf bir PHP dizisi.
     * @throws Exception
     */
    public function mahsuplasmisRaporlariGetir(DateTime $tarih1, DateTime $tarih2)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tarih' => $tarih1->format('d.m.Y'),
            'tarih2' => $tarih2->format('d.m.Y'),
        ];

        $response = $this->sendRequest('mahsuplastirmaOnaylananOdemeListesiSorguTarihle', $params);

        $returnNode = $response->mahsuplastirmaOnaylananOdemeListesiSorguTarihleReturn;
        $sonucDizisi = [];

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {

            // Dökümandaki dönüş objesi `mansuplastirmaOdemeTakipBean` olabilir. Bu tahminidir.
            if (isset($returnNode->mansuplastirmaOdemeTakipBean->MansuplastirmaOdemeTakipBean)) {

                $raporlarObject = $returnNode->mansuplastirmaOdemeTakipBean->MansuplastirmaOdemeTakipBean;

                // Manuel döngü ile en sağlam dönüşümü yapıyoruz
                foreach ($raporlarObject as $rapor) {
                    $sonucDizisi[] = [
                        'tcKimlikNo' => (string) $rapor->tcKimlikNo,
                        'adiSoyadi' => (string) $rapor->adiSoyadi,
                        'vakaAdi' => (string) $rapor->vakaAdi,
                        'odemeBasTar' => (string) $rapor->odemeBasTar,
                        'odemeBitTar' => (string) $rapor->odemeBitTar,
                        'odenenTutar' => (string) $rapor->odenenTutar,
                        'mahsuplasmaTar' => (string) $rapor->mahsuplasmaTar,
                        'durumStr' => (string) $rapor->durumStr // Makbuz Durumu
                    ];
                }
            }
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '503') {
            // Rapor bulunamadı.
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama)
                ? (string) $returnNode->sonucAciklama
                : 'Bilinmeyen bir hata oluştu.';
            throw new Exception("Mahsuplaşması onaylanmış raporlar getirilemedi: " . $hataMesaji);
        }

        return $sonucDizisi;
    }

    /**
     * METOT 22: IsverenPrimBorcunaMahsupEdilenOdemeListesiSorguTarihle
     * Kurum tarafından işverenin prim borcuna mahsup edilen ödemeleri listeler.
     * @param DateTime $tarih1 Başlangıç tarihi
     * @param DateTime $tarih2 Bitiş tarihi
     * @return array Mahsup edilmiş ödemelerin bulunduğu saf bir PHP dizisi.
     * @throws Exception
     */
    public function primBorcunaMahsupEdilenleriGetir(DateTime $tarih1, DateTime $tarih2)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tarih' => $tarih1->format('d.m.Y'),
            'tarih2' => $tarih2->format('d.m.Y'),
        ];

        $response = $this->sendRequest('isverenPrimBorcunaMahsupEdilenOdemeListesiSorguTarihle', $params);

        $returnNode = $response->isverenPrimBorcunaMahsupEdilenOdemeListesiSorguTarihleReturn;
        $sonucDizisi = [];

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {

            // Dökümandaki dönüş objesi `isverenPrimBorcunaMahsupEdilenOdemeBean` olabilir.
            if (isset($returnNode->isverenPrimBorcunaMahsupEdilenOdemeBean->IsverenPrimBorcunaMahsupEdilenOdemeBean)) {

                $kayitlarObject = $returnNode->isverenPrimBorcunaMahsupEdilenOdemeBean->IsverenPrimBorcunaMahsupEdilenOdemeBean;

                // Manuel döngü ile en sağlam dönüşümü yapıyoruz
                foreach ($kayitlarObject as $kayit) {
                    $sonucDizisi[] = [
                        'tcKimlikNo' => (string) $kayit->tcKimlikNo,
                        'adiSoyadi' => (string) $kayit->adiSoyadi,
                        'vakaAdi' => (string) $kayit->vakaAdi,
                        'odemeBasTar' => (string) $kayit->odemeBasTar,
                        'odemeBitTar' => (string) $kayit->odemeBitTar,
                        'odenenTutar' => (string) $kayit->odenenTutar,
                        'mahsuplasmaTar' => (string) $kayit->mahsuplasmaTar,
                        'tahsilat_tutar' => (string) $kayit->tahsilat_tutar,

                        'primTahsilatDonem' => (string) $kayit->primTahsilatDonem
                    ];
                }
            }
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '503') {
            // Kayıt yok.
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama)
                ? (string) $returnNode->sonucAciklama
                : 'Bilinmeyen bir hata oluştu.';
            throw new Exception("Prim borcuna mahsup edilen ödemeler getirilemedi: " . $hataMesaji);
        }

        return $sonucDizisi;
    }

    /**
     * METOT 11: ManuelCalismazlikBildirimiGiris
     * Manuel olarak çalışılmadığına dair bildirim yapar.
     * @param string $tcKimlikNo
     * @param DateTime $raporBaslangicTarihi
     * @param DateTime $iseBaslamaTarihi
     * @param string $nitelikDurumu "E" (Çalıştı) veya "H" (Çalışmadı)
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function manuelBildirimGir(string $tcKimlikNo, DateTime $raporBaslangicTarihi, DateTime $iseBaslamaTarihi, string $nitelikDurumu)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tckNo' => $tcKimlikNo,
            'raporBaslangicTarihi' => $raporBaslangicTarihi->format('d.m.Y'),
            'iseBaslamaTarihi' => $iseBaslamaTarihi->format('d.m.Y'),
            'nitelikDurumu' => $nitelikDurumu, // E veya H
        ];

        $response = $this->sendRequest('manuelCalismazlikBildirimiGiris', $params);

        return $response->manuelCalismazlikBildirimiGirisReturn;
    }

    /**
     * METOT 12: ManuelCalismazlikBildirimiGoruntu
     * Belirli bir kişi için daha önce yapılmış manuel bildirimleri listeler.
     * @param string $tcKimlikNo
     * @return array Manuel bildirimlerin bulunduğu saf bir PHP dizisi.
     * @throws Exception
     */
    public function manuelBildirimleriGetir(string $tcKimlikNo)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tckNo' => $tcKimlikNo,
        ];

        $response = $this->sendRequest('manuelCalismazlikBildirimiGoruntu', $params);
        $returnNode = $response->manuelCalismazlikBildirimiGoruntuReturn;
        $sonucDizisi = [];

        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {
            if (isset($returnNode->manuelCalismazlikBean->ManuelCalismazlikBean)) {
                $bildirimlerObject = $returnNode->manuelCalismazlikBean->ManuelCalismazlikBean;

                // Manuel döngü ile en sağlam dönüşümü yapıyoruz
                foreach ($bildirimlerObject as $bildirim) {
                    $sonucDizisi[] = [
                        'ID' => (string) $bildirim->ID,
                        'tcKimlikNo' => (string) $bildirim->tcKimlikNo,
                        'adi' => (string) $bildirim->adi,
                        'soyadi' => (string) $bildirim->soyadi,
                        'istenAyrTarih' => (string) $bildirim->istenAyrTarih,
                        'iseDonusTarih' => (string) $bildirim->iseDonusTarih,
                        'nitelikDurumu' => (string) $bildirim->nitelikDurumu, // E veya H
                        'islemTar' => (string) $bildirim->islemTar
                    ];
                }
            }
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '501') { // Kayıt Bulunamadı
            // Kayıt yoksa boş dizi döner, bu bir hata değildir.
        } else {
            $hataMesaji = isset($returnNode->sonucAciklama) ? (string) $returnNode->sonucAciklama : 'Bilinmeyen bir hata oluştu.';
            throw new Exception("Manuel bildirimler getirilemedi: " . $hataMesaji);
        }

        return $sonucDizisi;
    }

    /**
     * METOT 13: ManuelCalismazlikBildirimiSil
     * Daha önce yapılmış bir manuel bildirimi siler.
     * @param string $bildirimId Silinecek bildirimin ID'si
     * @param string $tcKimlikNo Bildirimin ait olduğu kişinin TC Kimlik Numarası
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function manuelBildirimSil(string $bildirimId, string $tcKimlikNo)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'id' => $bildirimId,
            'tckNo' => $tcKimlikNo,
        ];

        $response = $this->sendRequest('manuelCalismazlikBildirimiSil', $params);

        return $response->manuelCalismazlikBildirimiSilReturn;
    }

    /**
     * METOT 14: HasIsKazSorguTCile
     * TC Kimlik No ile iş kazası hastane provizyonlarını sorgular.
     * @param string $tcKimlikNo
     * @return array İş kazası kayıtlarının bulunduğu saf bir PHP dizisi.
     * @throws Exception
     */
    public function isKazasiGetirTcIle(string $tcKimlikNo)
    {
        $params = [
            'kullaniciAdi' => $this->kullaniciAdi,
            'isyeriKodu' => $this->isyeriKodu,
            'wsToken' => $this->getValidToken(),
            'tckNo' => $tcKimlikNo
        ];


        $response = $this->sendRequest('hasIsKazSorguTCile', $params);
        $returnNode = $response->hasIsKazSorguTCileReturn;

        return $this->isKazasiCevabiniDonustur($returnNode);
    }

    /**
     * METOT 15: HasIsKazSorguTarihle
     * Tarih aralığı ile iş kazası hastane provizyonlarını sorgular.
     * @param DateTime $tarih1
     * @param DateTime $tarih2
     * @return array İş kazası kayıtlarının bulunduğu saf bir PHP dizisi.
     * @throws Exception
     */
    public function isKazasiGetirTarihIle(DateTime $tarih1, DateTime $tarih2)
    {
        $params = ['kullaniciAdi' => $this->kullaniciAdi, 'isyeriKodu' => $this->isyeriKodu, 'wsToken' => $this->getValidToken(), 'tarih' => $tarih1->format('d.m.Y'), 'tarih2' => $tarih2->format('d.m.Y')];
        $response = $this->sendRequest('hasIsKazSorguTarihle', $params);
        $returnNode = $response->hasIsKazSorguTarihleReturn;
        return $this->isKazasiCevabiniDonustur($returnNode);
    }

    /**
     * METOT 16: HasIsKazSorguKapat
     * Bir iş kazası provizyonunu "okundu" olarak işaretler.
     * @param string $bildirimId Kapatılacak bildirimin ID'si
     * @return object Dönen cevap objesi
     * @throws Exception
     */
    public function isKazasiKapat(string $bildirimId)
    {
        $params = ['kullaniciAdi' => $this->kullaniciAdi, 'isyeriKodu' => $this->isyeriKodu, 'wsToken' => $this->getValidToken(), 'bildirimId' => $bildirimId];
        $response = $this->sendRequest('hasIsKazSorguKapat', $params);
        return $response->hasIsKazSorguKapatReturn;
    }

    /**
     * Yardımcı Fonksiyon: İş kazası metotlarından gelen cevabı standart bir diziye çevirir.
     * @param object $returnNode
     * @return array
     * @throws Exception
     */
    private function isKazasiCevabiniDonustur(object $returnNode): array
    {
        $sonucDizisi = [];
        if (isset($returnNode->sonucKod) && $returnNode->sonucKod == '0') {
            if (isset($returnNode->isKazasiHastaneBilgiBeanArray->IsKazasiHastaneBilgiBean)) {
                $kayitlarObject = $returnNode->isKazasiHastaneBilgiBeanArray->IsKazasiHastaneBilgiBean;
                foreach ($kayitlarObject as $kayit) {
                    $sonucDizisi[] = [
                        'BILDIRIMID' => (string) $kayit->BILDIRIMID,
                        'TCKIMLIKNO' => (string) $kayit->TCKIMLIKNO,
                        'CINSIYET' => (string) $kayit->CINSIYET,
                        'PROVIZYONTARIHI' => (string) $kayit->PROVIZYONTARIHI,
                        'TESISADI' => (string) $kayit->TESISADI,
                        'UNVANI' => (string) $kayit->UNVANI,
                        'ISLEMTUR' => (string) $kayit->ISLEMTUR,
                        'GIBSUBENO' => (string) $kayit->GIBSUBENO,
                        'ISKAZASITARIHI' => (string) $kayit->ISKAZASITARIHI,
                    ];
                }
            }
        } else if (isset($returnNode->sonucKod) && $returnNode->sonucKod != '503') { // 503 Kayıt yok hatası değilse
            throw new Exception($returnNode->sonucAciklama ?? 'İş kazası sorgulama başarısız.');
        }
        return $sonucDizisi;
    }

    /**
     * Arşivlenmiş (3 günden kısa süreli) raporları listeler.
     * Arka planda raporAramaTarihile'i (onay bekleyenler) çağırır ve sonuçları filtreler.
     *
     * @param DateTime $tarih1 Başlangıç tarihi
     * @param DateTime $tarih2 Bitiş tarihi
     * @return array Arşivlenmiş raporların bulunduğu saf bir PHP dizisi.
     * @throws Exception
     */
    public function arsivlenmisRaporlariGetir(DateTime $tarih1, DateTime $tarih2)
    {
        // SGK'nın bu tür bir metodu olmadığı için, geniş bir aralıktaki TÜM onay bekleyenleri
        // çekip içinden filtreleme yapmamız gerekir.
        // Tarih parametresi olarak geleceği veriyoruz ki hiçbirini kaçırmayalım.
        $tumBekleyenRaporlar = $this->raporlariGetir(new DateTime('tomorrow'));

        if (empty($tumBekleyenRaporlar)) {
            return [];
        }

        $arsivlenmisRaporlar = [];

        foreach ($tumBekleyenRaporlar as $rapor) {
            // Filtreleme için birden çok koşulu kontrol edelim
            $isArsiv = false;

            // 1. Koşul: SGK'dan doğrudan "ARSIV" bilgisi geliyor mu?
            if (isset($rapor['ARSIV']) && $rapor['ARSIV'] == '1') {
                $isArsiv = true;
            }


            // 2. Koşul: Rapor süresi 3 günden kısa mı? (Ek güvence)
            if (isset($rapor['POLIKLINIKTAR']) && isset($rapor['ISBASKONTTAR'])) {
                try {
                    $baslangic = new DateTime($rapor['POLIKLINIKTAR']);
                    $iseBasi = new DateTime($rapor['ISBASKONTTAR']);
                    if ($baslangic->diff($iseBasi)->days < 3) {
                        $isArsiv = true;
                    }
                } catch (Exception $e) { /* Geçersiz tarih, atla */
                }
            }

            // 3. Tarih Aralığı Kontrolü: Rapor, kullanıcının seçtiği tarih aralığında mı?
            if ($isArsiv) {
                try {
                    $raporTarihi = new DateTime($rapor['POLIKLINIKTAR']);
                    if ($raporTarihi >= $tarih1 && $raporTarihi <= $tarih2) {
                        $arsivlenmisRaporlar[] = $rapor;
                    }
                } catch (Exception $e) { /* Geçersiz tarih, atla */
                }
            }
        }

        return $arsivlenmisRaporlar;
    }
}
