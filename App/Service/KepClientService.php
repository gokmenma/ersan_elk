<?php

namespace App\Service;

use Exception;
use Throwable;

/**
 * PTT KEP (Kayıtlı Elektronik Posta) SOAP Web Servis İstemcisi
 * WSDL: https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl
 */
class KepClientService
{
    public const DEFAULT_WSDL = 'https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl';
    public const TEST_WSDL = 'https://eyazisma.hs01.kep.tr/KepEYazismaV1.1/KepEYazismaCOREWSDL.php?wsdl';

    private string $kepHesap;
    private string $tcno;
    private string $parola;
    private string $sifre;
    private string $wsdlUrl;
    private string $ortam;
    private ?MtomSoapClient $client = null;

    public function __construct(
        string $kepHesap,
        string $tcno,
        string $parola,
        string $sifre,
        string $wsdlUrl = self::DEFAULT_WSDL,
        string $ortam = 'prod'
    ) {
        $this->kepHesap = trim($kepHesap);
        $this->tcno = trim($tcno);
        $this->parola = trim($parola);
        $this->sifre = trim($sifre);
        $this->wsdlUrl = !empty($wsdlUrl) ? trim($wsdlUrl) : self::DEFAULT_WSDL;
        $this->ortam = in_array($ortam, ['prod', 'test'], true) ? $ortam : 'prod';
    }

    /**
     * SoapClient bağlantısını oluşturur veya mevcut bağlantıyı döner
     */
    private function getSoapClient(): MtomSoapClient
    {
        if ($this->client === null) {
            $options = [
                'trace' => 1,
                'exceptions' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 20,
            ];

            try {
                $this->client = new MtomSoapClient($this->wsdlUrl, $options);
            } catch (Throwable $e) {
                error_log("KepClientService SoapClient init error: " . $e->getMessage());
                throw new Exception("PTT KEP Web Servisine bağlanılamadı: " . $e->getMessage());
            }
        }

        return $this->client;
    }

    /**
     * eyKepHesapGirisP standart giriş parametresini üretir
     */
    private function getAuthParams(): array
    {
        return [
            'kepHesap' => $this->kepHesap,
            'tcno' => $this->tcno,
            'parola' => $this->parola,
            'sifre' => $this->sifre,
        ];
    }

    /**
     * KEP hesabına ait bakiye/kontör miktarını sorgular (KontorSorgula)
     *
     * @return array [ 'miktar' => float, 'cins' => string, 'durum' => int, 'hataaciklama' => string ]
     */
    public function kontorSorgula(): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => $this->getAuthParams(),
            ];

            $res = $client->KontorSorgula($params);
            $ret = $res->return ?? null;

            if ($ret === null) {
                return [
                    'status' => 'error',
                    'miktar' => 0,
                    'cins' => '',
                    'message' => 'PTT KEP bakiye yanıtı alınamadı.',
                ];
            }

            $miktar = isset($ret->miktar) ? (float) $ret->miktar : 0.0;
            $cins = (string) ($ret->cins ?? '');

            // PTT bazı kimlik doğrulama hatalarını ayrı hata alanı yerine `cins` içinde döndürüyor.
            $normalizedUnit = mb_strtolower($cins, 'UTF-8');
            $authOrServiceError = $miktar <= 0 && (
                str_contains($normalizedUnit, 'şifre') || str_contains($normalizedUnit, 'sifre') ||
                str_contains($normalizedUnit, 'parola') || str_contains($normalizedUnit, 'yanlış') ||
                str_contains($normalizedUnit, 'yanlis') || str_contains($normalizedUnit, 'kontrol') ||
                str_contains($normalizedUnit, 'hata') || str_contains($normalizedUnit, 'yetki')
            );
            if ($authOrServiceError) {
                return [
                    'status' => 'error',
                    'miktar' => 0,
                    'cins' => $cins,
                    'message' => $cins !== '' ? $cins : 'PTT KEP kimlik doğrulaması başarısız.',
                ];
            }

            return [
                'status' => 'success',
                'miktar' => $miktar,
                'cins' => $cins !== '' ? $cins : 'Adet',
                'message' => "Bakiye: {$miktar} {$cins}",
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::kontorSorgula error: " . $e->getMessage());
            return [
                'status' => 'error',
                'miktar' => 0,
                'cins' => '',
                'message' => 'KEP bakiye sorgulama hatası: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Hedef KEP adresinin sistemde bulunup bulunmadığını ve ileti almaya uygunluğunu sorgular (RehberTopluKontrol)
     *
     * @param string $recipientAddress Hedef KEP adresi (Örn: alici@hs01.kep.tr)
     * @return array [ 'exist' => bool, 'suitable' => bool, 'durum' => int, 'hataaciklama' => string ]
     */
    public function rehberTopluKontrol(string $recipientAddress): array
    {
        $recipientAddress = trim($recipientAddress);
        if ($recipientAddress === '' || !filter_var($recipientAddress, FILTER_VALIDATE_EMAIL)) {
            return [
                'status' => 'error',
                'exist' => false,
                'suitable' => false,
                'message' => 'Geçersiz KEP e-posta adresi formatı.',
            ];
        }

        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'kepAddress' => $recipientAddress,
                ],
            ];

            $res = $client->RehberTopluKontrol($params);
            $durum = isset($res->durum) ? (int) $res->durum : -1;
            $hata = (string) ($res->hataaciklama ?? '');
            $sonuc = $res->RehberTopluSonuc ?? null;

            if ($durum === 0 && $sonuc !== null) {
                $exist = strtolower((string) ($sonuc->exist ?? '')) === 'true' || (string) ($sonuc->exist ?? '') === '1';
                $suitable = strtolower((string) ($sonuc->suitable ?? '')) === 'true' || (string) ($sonuc->suitable ?? '') === '1';
                $isTestAccount = strpos($this->kepHesap, 'testkep') !== false || strpos($this->wsdlUrl, 'test') !== false;

                $msg = '';
                if ($exist) {
                    $msg = $suitable ? 'KEP adresi PTT Rehberi\'nde kayıtlı ve iletiye uygun.' : 'KEP adresi kayıtlı ancak ileti almaya uygun değil.';
                } else {
                    // PTT Test ortamında veya rehber servisi izole ortamlarda RehberTopluKontrol exist=0 dönebilir.
                    // Adresin PTT altyapısında gerçekten geçerli olup olmadığını anlamak için anlık MimeYap kontrolü yapalım:
                    try {
                        $mimeTest = $client->MimeYap([
                            'param0' => array_merge(['kepHesap' => $this->getAuthParams()], [
                                'ePaketTur' => 'Standart',
                                'ePaketId' => 'CHK-' . bin2hex(random_bytes(4)),
                                'kime' => $recipientAddress,
                                'konu' => 'Kontrol',
                                'icerik' => 'Kontrol',
                                'eIcerikTur' => 'TEXT',
                                'ekler' => null,
                                'eOzetAlg' => 'SHA256',
                            ])
                        ]);
                        $mimeRet = $mimeTest->return ?? null;
                        $mimeDurum = isset($mimeRet->durum) ? (int) $mimeRet->durum : -1;
                        $mimeHata = trim((string) ($mimeRet->hataaciklama ?? ''));

                        if ($mimeDurum === 0) {
                            $exist = true;
                            $suitable = true;
                            $msg = 'KEP adresi PTT sisteminde doğrulandı ve ileti almaya uygun.';
                        } elseif ($mimeDurum === 17) {
                            $exist = false;
                            $suitable = false;
                            $msg = $isTestAccount
                                ? 'Alıcı KEP hesabı geçersiz (Test ortamında canlı @hs01.kep.tr hesapları kabul edilmez; @testkep.pttkep.gov.tr hesapları geçerlidir).'
                                : ($mimeHata !== '' ? $mimeHata : 'Alıcı KEP hesabı geçersiz veya bulunamadı.');
                        } else {
                            $msg = $mimeHata !== '' ? $mimeHata : 'KEP adresi sistemde bulunamadı.';
                        }
                    } catch (Throwable $e) {
                        $msg = 'KEP adresi PTT Rehberi\'nde bulunamadı veya rehbere kapalı.';
                    }
                }

                return [
                    'status' => 'success',
                    'exist' => $exist,
                    'suitable' => $suitable,
                    'is_test' => $isTestAccount,
                    'kep_address' => (string) ($sonuc->kepAddress ?? $recipientAddress),
                    'message' => $msg,
                ];
            }

            return [
                'status' => 'error',
                'exist' => false,
                'suitable' => false,
                'durum' => $durum,
                'message' => $hata !== '' ? $hata : 'Alıcı KEP adresi doğrulanamadı (Hata kodu: ' . $durum . ')',
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::rehberTopluKontrol error: " . $e->getMessage());
            return [
                'status' => 'error',
                'exist' => false,
                'suitable' => false,
                'message' => 'KEP rehber sorgulama hatası: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * PTT KEP üzerinde MIME paketi oluşturur (MimeYap)
     *
     * @param array $data [
     *   'ePaketTur' => 'Standart'|'EYazisma'|'ETebligat',
     *   'ePaketId'  => string,
     *   'kime'      => string (virgülle ayrılmış alıcılar),
     *   'konu'      => string,
     *   'icerik'    => string,
     *   'eIcerikTur'=> 'HTML'|'TEXT',
     *   'ekler'     => array of base64Binary,
     *   'eOzetAlg'  => 'SHA256'|'SHA512'
     * ]
     * @return array [ 'durum' => int, 'eHash' => string, 'mesajid' => string, 'hataaciklama' => string ]
     */
    public function mimeYap(array $data): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'ePaketTur' => $data['ePaketTur'] ?? 'Standart',
                    'ePaketId' => (string) ($data['ePaketId'] ?? ''),
                    'kime' => (string) ($data['kime'] ?? ''),
                    'konu' => (string) ($data['konu'] ?? ''),
                    'icerik' => (string) ($data['icerik'] ?? ''),
                    'eIcerikTur' => $data['eIcerikTur'] ?? 'HTML',
                    'ekler' => $data['ekler'] ?? [],
                    'eOzetAlg' => $data['eOzetAlg'] ?? 'SHA256',
                ],
            ];

            $res = $client->MimeYap($params);
            $ret = $res->return ?? null;

            if ($ret === null) {
                throw new Exception('PTT KEP MimeYap servisinden yanıt alınamadı.');
            }

            $durum = isset($ret->durum) ? (int) $ret->durum : -1;
            $hata = (string) ($ret->hataaciklama ?? '');
            $eHash = (string) ($ret->eHash ?? '');
            $mesajId = (string) ($ret->mesajid ?? '');

            return [
                'status' => $durum === 0 ? 'success' : 'error',
                'durum' => $durum,
                'eHash' => $eHash,
                'mesajid' => $mesajId,
                'message' => $durum === 0 ? 'MIME paketi oluşturuldu.' : ($hata ?: "MIME oluşturma hatası (Kod: {$durum})"),
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::mimeYap error: " . $e->getMessage());
            return [
                'status' => 'error',
                'durum' => -1,
                'message' => 'MimeYap hatası: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * İmzalanan MIME özetini (CAdES p7s) sunucuya göndererek S/MIME iletisi sevkini tamamlar (SmimeGonder)
     *
     * @param string $mesajId MimeYap'tan dönen mesajid
     * @param string $imzaBase64 CAdES-BES imza verisi (base64)
     * @return array [ 'durum' => int, 'hataaciklama' => string ]
     */
    public function smimeGonder(string $mesajId, string $imza): array
    {
        try {
            $client = $this->getSoapClient();
            $imzaData = $imza;
            if (preg_match('%^[a-zA-Z0-9/+\r\n]*={0,2}$%', trim($imza))) {
                $decoded = base64_decode(str_replace(["\r", "\n", ' '], '', $imza), true);
                if ($decoded !== false && strlen($decoded) > 0) {
                    $imzaData = $decoded;
                }
            }

            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'mesajid' => $mesajId,
                    'imza' => $imzaData,
                ],
            ];

            $res = $client->SmimeGonder($params);
            $ret = $res->return ?? null;

            if ($ret === null) {
                throw new Exception('PTT KEP SmimeGonder servisinden yanıt alınamadı.');
            }

            $durum = isset($ret->durum) ? (int) $ret->durum : -1;
            $hata = (string) ($ret->hataaciklama ?? '');

            return [
                'status' => $durum === 0 ? 'success' : 'error',
                'durum' => $durum,
                'message' => $durum === 0 ? 'KEP iletisi başarıyla gönderildi.' : ($hata ?: "İleti gönderim hatası (Kod: {$durum})"),
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::smimeGonder error: " . $e->getMessage());
            return [
                'status' => 'error',
                'durum' => -1,
                'message' => 'SmimeGonder hatası: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Hazırlanmış S/MIME paketini doğrudan KEP sistemine yükler (Yukle)
     *
     * @param string $ePaketBase64 Base64 kodlu S/MIME paketi
     * @param string $ePaketTur 'Standart'|'EYazisma'|'ETebligat'
     * @return array [ 'durum' => int, 'kepId' => string, 'hataaciklama' => string ]
     */
    public function yukle(string $ePaketBase64, string $ePaketTur = 'Standart'): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'ePaket' => $ePaketBase64,
                    'ePaketTur' => $ePaketTur,
                ],
            ];

            $res = $client->Yukle($params);
            $ret = $res->return ?? null;

            if ($ret === null) {
                throw new Exception('PTT KEP Yukle servisinden yanıt alınamadı.');
            }

            $durum = isset($ret->durum) ? (int) $ret->durum : -1;
            $hata = (string) ($ret->hataaciklama ?? '');
            $kepId = (string) ($ret->kepId ?? '');

            return [
                'status' => $durum === 0 ? 'success' : 'error',
                'durum' => $durum,
                'kepId' => $kepId,
                'message' => $durum === 0 ? 'KEP paketi sisteme yüklendi.' : ($hata ?: "Paket yükleme hatası (Kod: {$durum})"),
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::yukle error: " . $e->getMessage());
            return [
                'status' => 'error',
                'durum' => -1,
                'message' => 'Yukle hatası: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * KEP iletisine ait yasal delilleri sorgular (PaketDelilSorgula)
     *
     * @param string $kepId PTT KEP Mesaj ID numarası
     * @param int $kepSiraNo Varsa paket sıra numarası
     * @return array
     */
    public function paketDelilSorgula(string $kepId, int $kepSiraNo = 0): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'kepId' => $kepId,
                    'kepSiraNo' => $kepSiraNo,
                ],
            ];

            $res = $client->PaketDelilSorgula($params);
            $ret = $res->return ?? null;

            if ($ret === null) {
                return [
                    'status' => 'error',
                    'deliller' => [],
                    'message' => 'PTT KEP delil sorgulama yanıtı alınamadı.',
                ];
            }

            $durum = is_array($ret->durum ?? null) ? (int) ($ret->durum[0] ?? 0) : (int) ($ret->durum ?? 0);
            $hata = is_array($ret->hataaciklama ?? null) ? implode('; ', array_filter(array_map('strval', $ret->hataaciklama))) : (string) ($ret->hataaciklama ?? '');

            $delilList = [];
            if ($durum === 0 || !empty($ret->delilId)) {
                // SOAP dizileri tek eleman ise obje, çoklu ise dizi döner
                $delilIds = (array) ($ret->delilId ?? []);
                $delilAciklamalar = (array) ($ret->delilaciklama ?? $ret->delilAciklama ?? []);
                $delilTurIds = (array) ($ret->delilTurId ?? []);
                $tarihler = (array) ($ret->tarih ?? []);
                $kephsList = (array) ($ret->kephs ?? []);

                $count = count($delilIds);
                for ($i = 0; $i < $count; $i++) {
                    $turId = (int) ($delilTurIds[$i] ?? 0);
                    $delilList[] = [
                        'delil_id' => (string) ($delilIds[$i] ?? ''),
                        'delil_tur_id' => $turId,
                        'delil_aciklama' => (string) ($delilAciklamalar[$i] ?? self::getDelilTurAciklama($turId)),
                        'tarih' => (string) ($tarihler[$i] ?? date('Y-m-d H:i:s')),
                        'kephs' => (string) ($kephsList[$i] ?? ''),
                    ];
                }
            }

            return [
                'status' => $durum === 0 ? 'success' : 'error',
                'durum' => $durum,
                'deliller' => $delilList,
                'message' => $durum === 0 ? count($delilList) . ' adet yasal delil bulundu.' : ($hata ?: 'Delil bulunamadı.'),
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::paketDelilSorgula error: " . $e->getMessage());
            return [
                'status' => 'error',
                'deliller' => [],
                'message' => 'Delil sorgulama hatası: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * DelilId'sine ait XAdES-A ile imzalanmış XML formatındaki yasal delil dosyasını indirir (PaketDelilIndir)
     *
     * @param string $delilId Delil Kimliği
     * @return array [ 'durum' => int, 'eDelil' => base64Binary, 'hataaciklama' => string ]
     */
    public function paketDelilIndir(string $delilId): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'delilId' => trim($delilId),
                ],
            ];

            $res = $client->PaketDelilIndir($params);
            $ret = $res->return ?? null;

            if ($ret === null) {
                throw new Exception('PTT KEP delil indirme yanıtı alınamadı.');
            }

            $durum = isset($ret->durum) ? (int) $ret->durum : -1;
            $hata = (string) ($ret->hataaciklama ?? '');
            $rawEDelil = $ret->eDelil ?? '';
            $eDelil = is_object($rawEDelil) ? (string) ($rawEDelil->_ ?? '') : (string) $rawEDelil;

            return [
                'status' => $durum === 0 && !empty($eDelil) ? 'success' : 'error',
                'durum' => $durum,
                'eDelil' => $eDelil,
                'message' => $durum === 0 ? 'Delil dosyası başarıyla indirildi.' : ($hata ?: 'Delil indirilemedi.'),
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::paketDelilIndir error: " . $e->getMessage());
            return [
                'status' => 'error',
                'durum' => -1,
                'message' => 'PaketDelilIndir hatası: ' . $e->getMessage(),
            ];
        }
    }

    public function paketSorgula(string $baslangic, string $bitis, string $dizin = 'INBOX'): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'ilktarih' => date('c', strtotime($baslangic)),
                    'sontarih' => date('c', strtotime($bitis)),
                    'dizin' => $dizin,
                ],
            ];

            $res = $client->PaketSorgula3($params);
            $ret = $res->return ?? null;
            if ($ret === null) {
                return ['status' => 'error', 'paketler' => [], 'message' => 'PTT KEP paket sorgulama yanıtı alınamadı.'];
            }

            $durum = isset($ret->durum) ? (int) (is_array($ret->durum) ? ($ret->durum[0] ?? 0) : $ret->durum) : 0;
            $hata = is_array($ret->hataaciklama ?? null) ? implode('; ', array_filter(array_map('strval', $ret->hataaciklama))) : (string) ($ret->hataaciklama ?? '');
            if ($durum !== 0) {
                return ['status' => 'error', 'paketler' => [], 'message' => $hata ?: "Paket sorgulama hatası (Kod: {$durum})"];
            }

            $kepIds = (array) ($ret->kepId ?? []);
            $orgIds = (array) ($ret->OrgMesajId ?? []);
            $siralar = (array) ($ret->kepSiraNo ?? []);
            $from = (array) ($ret->from ?? []);
            $fromKep = (array) ($ret->fromKep ?? []);
            $konular = (array) ($ret->konu ?? []);
            $turler = (array) ($ret->tur ?? []);

            $paketler = [];
            foreach ($kepIds as $i => $kepId) {
                $kepId = trim((string) $kepId);
                if ($kepId === '') {
                    continue;
                }
                $paketler[] = [
                    'kep_id' => $kepId,
                    'org_mesaj_id' => (string) ($orgIds[$i] ?? ''),
                    'kep_sira_no' => (int) ($siralar[$i] ?? 0),
                    'gonderen' => (string) ($from[$i] ?? ''),
                    'gonderen_kep' => (string) ($fromKep[$i] ?? ''),
                    'konu' => (string) ($konular[$i] ?? ''),
                    'tur' => (string) ($turler[$i] ?? ''),
                ];
            }

            return ['status' => 'success', 'paketler' => $paketler, 'message' => count($paketler) . ' adet KEP iletisi bulundu.'];
        } catch (Throwable $e) {
            error_log("KepClientService::paketSorgula error: " . $e->getMessage());
            return ['status' => 'error', 'paketler' => [], 'message' => 'Paket sorgulama hatası: ' . $e->getMessage()];
        }
    }

    public function indir(string $kepId, int $kepSiraNo = 0, string $dizin = 'INBOX'): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'kepId' => $kepId,
                    'kepSiraNo' => $kepSiraNo,
                    'ePart' => 'ALL',
                    'dizin' => $dizin,
                ],
            ];

            $res = $client->Indir($params);
            $ret = $res->return ?? null;
            if ($ret === null) {
                throw new Exception('PTT KEP indirme yanıtı alınamadı.');
            }

            $durum = isset($ret->durum) ? (int) $ret->durum : -1;
            $hata = (string) ($ret->hataaciklama ?? '');
            $rawPaket = $ret->ePaket ?? '';
            if (is_array($rawPaket)) {
                $rawPaket = $rawPaket[0] ?? '';
            }
            $ePaket = is_object($rawPaket) ? (string) ($rawPaket->_ ?? '') : (string) $rawPaket;

            return [
                'status' => $durum === 0 && $ePaket !== '' ? 'success' : 'error',
                'durum' => $durum,
                'ePaket' => $ePaket,
                'message' => $durum === 0 ? 'KEP iletisi indirildi.' : ($hata ?: "İndirme hatası (Kod: {$durum})"),
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::indir error: " . $e->getMessage());
            return ['status' => 'error', 'durum' => -1, 'ePaket' => '', 'message' => 'Indir hatası: ' . $e->getMessage()];
        }
    }

    public function alindiOnay(string $kepId, int $kepSiraNo = 0, string $dizin = 'INBOX'): array
    {
        try {
            $client = $this->getSoapClient();
            $params = [
                'param0' => [
                    'kepHesap' => $this->getAuthParams(),
                    'kepId' => $kepId,
                    'kepSiraNo' => $kepSiraNo,
                    'dizin' => $dizin,
                ],
            ];

            $res = $client->AlindiOnay($params);
            $ret = $res->return ?? null;
            $durum = isset($ret->durum) ? (int) $ret->durum : -1;
            $hata = (string) ($ret->hataaciklama ?? '');

            return [
                'status' => $durum === 0 ? 'success' : 'error',
                'durum' => $durum,
                'message' => $durum === 0 ? 'Alındı onayı gönderildi.' : ($hata ?: "Alındı onayı hatası (Kod: {$durum})"),
            ];
        } catch (Throwable $e) {
            error_log("KepClientService::alindiOnay error: " . $e->getMessage());
            return ['status' => 'error', 'durum' => -1, 'message' => 'AlindiOnay hatası: ' . $e->getMessage()];
        }
    }

    /**
     * Ek 5'te yer alan DelilTurID değerlerine ait standart Türkçe açıklamalar
     */
    public static function getDelilTurAciklama(int $turId): string
    {
        return match ($turId) {
            10 => 'S/MIME paketi Kephs tarafından kabul edildi (Submission Acceptance)',
            11 => 'S/MIME paketi Kephs tarafından reddedildi (Submission Rejection)',
            20 => 'S/MIME paketi diğer Kephs tarafından kabul edildi (Relay Acceptance)',
            21 => 'S/MIME paketi diğer Kephs tarafından reddedildi (Relay Rejection)',
            31 => 'S/MIME paketi diğer Kephs\'ye gönderilemedi (Relay Failure)',
            40 => 'S/MIME paketi alıcı kullanıcının posta kutusuna bırakıldı (Delivery To Recipient)',
            41 => 'S/MIME paketi alıcı kullanıcının posta kutusuna bırakılamadı (Non Delivery To Recipient)',
            60 => 'S/MIME paketi alıcı kullanıcı tarafından okundu (Retrieval By Recipient)',
            default => "Yasal KEP Delili (Tür: {$turId})",
        };
    }
}
