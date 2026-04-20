<?php

namespace App\Model;

use App\Model\Model;
use App\Model\BordroParametreModel;
use PDO;

class BordroPersonelModel extends Model
{
    protected $table = 'bordro_personel';
    protected $primaryKey = 'id';
    public $icra_uyarilari = [];
    public $gorev_gecmisi_eksik = [];

    /** @var BordroParametreModel|null Tekil örnek — aynı istek içinde yeniden kullanılır */
    private ?BordroParametreModel $cachedParametreModel = null;
    /** @var array|null Genel ayarlar map (parametre_kodu => float) — dönem tarihine göre cache */
    private ?array $genelAyarlarCache = null;
    /** @var array|null Tüm bordro parametreleri (kod => nesne) — dönem tarihine göre cache */
    private ?array $parametrelerCache = null;
    /** @var array|null Personel ek ödemeler cache (personel_id => array) */
    private ?array $ekOdemelerCache = null;
    private array $isTuruIdMapCache = [];
    private array $isTuruUcretCache = [];
    private array $settingsCache = [];
    private ?array $ucretsizIzinTurIdsCache = null;
    /** @var PersonelModel|null Personel model cache */
    private ?PersonelModel $personelModelCache = null;
    /** @var TanimlamalarModel|null Tanımlamalar model cache */
    private ?TanimlamalarModel $tanimlamalarModelCache = null;
    /** @var bool Toplu hesaplama modu aktif mi? */
    public bool $batchMode = false;





    /**
     * Parametre cache'ini kullanarak getByKod() işlevi görür.
     * hesaplaMaas() döngüsünde tekrar eden DB sorgularını önler.
     */
    private function getParametreCached(string $kod, string $tarih): ?object
    {
        if ($this->parametrelerCache !== null) {
            return $this->parametrelerCache[$kod] ?? null;
        }
        // Cache dolmamışsa (standalone çağrı) doğrudan sorgula
        $model = $this->cachedParametreModel ?? new BordroParametreModel();
        $result = $model->getByKod($kod, $tarih);
        return $result ?: null;
    }

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function isValidDateValue($date): bool
    {
        return !empty($date) && $date !== '0000-00-00';
    }

    private function getAktifTakvimGunSayisi($donemBaslangic, $donemBitis, $iseGirisTarihi = null, $istenCikisTarihi = null): int
    {
        $donemBasTs = strtotime($donemBaslangic);
        $donemBitTs = strtotime($donemBitis);

        if ($donemBasTs === false || $donemBitTs === false || $donemBitTs < $donemBasTs) {
            return 0;
        }

        $aktifBasTs = $donemBasTs;
        $aktifBitTs = $donemBitTs;

        if ($this->isValidDateValue($iseGirisTarihi)) {
            $iseGirisTs = strtotime($iseGirisTarihi);
            if ($iseGirisTs !== false && $iseGirisTs > $aktifBasTs) {
                $aktifBasTs = $iseGirisTs;
            }
        }

        if ($this->isValidDateValue($istenCikisTarihi)) {
            $istenCikisTs = strtotime($istenCikisTarihi);
            if ($istenCikisTs !== false && $istenCikisTs < $aktifBitTs) {
                $aktifBitTs = $istenCikisTs;
            }
        }

        if ($aktifBitTs < $aktifBasTs) {
            return 0;
        }

        return (int) round(($aktifBitTs - $aktifBasTs) / 86400) + 1;
    }

    private function getMaasHesapGunu(int $aktifTakvimGun, int $donemTakvimGun, int $eksikGunToplami): int
    {
        if ($aktifTakvimGun <= 0) {
            return 0;
        }

        // Eğer personel ayın tamamında aktifse (giriş çıkışı yoksa dönemi kapsıyorsa)
        // SSK günü 30'dur. 
        if ($aktifTakvimGun >= $donemTakvimGun) {
            // Eğer eksik gün yoksa 30 gün kabul edilir
            if ($eksikGunToplami == 0) {
                return 30;
            }
            
            // USER REQ: Gün hesaplaması yapılırken "ay günü - ücretsiz izin günü" baz alınmalıdır.
            // Örn: Mart (31 gün) - 5 gün eksik = 26 gün.
            // Ancak hakediş hesaplanırken bu gün sayısı "Maaş / 30" birim ücreti ile çarpılacaktır.
            return max(0, $donemTakvimGun - $eksikGunToplami);
        }

        // Kıst dönem (Ay ortası giriş/çıkış) ise aktif gün sayısından düşeriz
        return max(0, $aktifTakvimGun - $eksikGunToplami);
    }

    private function hasMaasaDahilSosyalYardim(object $kayit): bool
    {
        return intval($kayit->yemek_yardimi_dahil ?? 0) === 1
            || intval($kayit->es_yardimi_dahil ?? 0) === 1;
    }

    private function getYemekYardimiGunlukLimit(object $kayit): float
    {
        $limit = floatval($kayit->yemek_yardimi_tutari ?? 0);

        if ($limit <= 0 && !empty($kayit->yemek_yardimi_parametre_id)) {
            if ($this->cachedParametreModel === null) {
                $this->cachedParametreModel = new BordroParametreModel();
            }

            $paramYemek = $this->cachedParametreModel->find($kayit->yemek_yardimi_parametre_id);
            $limit = floatval($paramYemek->varsayilan_tutar ?? 0);
        }

        return $limit;
    }

    private function getEsYardimiAylikLimit(object $kayit): float
    {
        $limit = floatval($kayit->es_yardimi_tutari ?? 0);

        if ($limit <= 0 && !empty($kayit->es_yardimi_parametre_id)) {
            if ($this->cachedParametreModel === null) {
                $this->cachedParametreModel = new BordroParametreModel();
            }

            $paramEs = $this->cachedParametreModel->find($kayit->es_yardimi_parametre_id);
            $limit = floatval($paramEs->varsayilan_tutar ?? 0);
        }

        return $limit;
    }

    private function hesaplaMaasaDahilYardimDagilimi(object $kayit, float $asgariUcretNet, int $maasHesapGunu, int $fiiliGunSayisi): array
    {
        $sonuc = [
            'aktif' => false,
            'asgari_hakedis' => 0.0,
            'hedef_net_hakedis' => 0.0,
            'fark_tutari' => 0.0,
            'yemek_gunluk_ham' => 0.0,
            'yemek_gunluk' => 0.0,
            'yemek_toplam' => 0.0,
            'es_toplam' => 0.0,
            'toplam' => 0.0,
            'kalan_fark' => 0.0,
            'fiili_gun' => max(0, $fiiliGunSayisi),
        ];

        if (!$this->hasMaasaDahilSosyalYardim($kayit) || $maasHesapGunu <= 0) {
            return $sonuc;
        }

        $sonuc['aktif'] = true;
        $sonuc['asgari_hakedis'] = round(($asgariUcretNet / 30) * $maasHesapGunu, 2);
        $hedefMaasTutari = floatval($kayit->hedef_net_maas_tutari ?? 0);
        if ($hedefMaasTutari <= 0) {
            $hedefMaasTutari = floatval($kayit->hd_nominal_maas ?? 0);
        }
        if ($hedefMaasTutari <= 0) {
            $hedefMaasTutari = floatval($kayit->gg_maas_tutari ?? 0);
        }
        if ($hedefMaasTutari <= 0) {
            $hedefMaasTutari = floatval($kayit->maas_tutari ?? 0);
        }
        $sonuc['hedef_net_hakedis'] = round(($hedefMaasTutari / 30) * $maasHesapGunu, 2);
        $sonuc['fark_tutari'] = max(0, round($sonuc['hedef_net_hakedis'] - $sonuc['asgari_hakedis'], 2));

        $kalanFark = $sonuc['fark_tutari'];
        $fiiliGun = max(0, $sonuc['fiili_gun']);

        if (intval($kayit->yemek_yardimi_dahil ?? 0) === 1 && $kalanFark > 0 && $fiiliGun > 0) {
            $sonuc['yemek_gunluk_ham'] = $kalanFark / $fiiliGun;
            $gunlukYemek = ceil($sonuc['yemek_gunluk_ham']);
            $yemekLimit = $this->getYemekYardimiGunlukLimit($kayit);

            if ($yemekLimit > 0 && $gunlukYemek > $yemekLimit) {
                $gunlukYemek = $yemekLimit;
            }

            $sonuc['yemek_gunluk'] = $gunlukYemek;
            $sonuc['yemek_toplam'] = round($gunlukYemek * $fiiliGun, 2);
            $kalanFark = max(0, round($kalanFark - $sonuc['yemek_toplam'], 2));
        }

        if (intval($kayit->es_yardimi_dahil ?? 0) === 1 && $kalanFark > 0) {
            $esLimit = $this->getEsYardimiAylikLimit($kayit);
            $sonuc['es_toplam'] = round($esLimit > 0 ? min($kalanFark, $esLimit) : $kalanFark, 2);
            $kalanFark = max(0, round($kalanFark - $sonuc['es_toplam'], 2));
        }

        $sonuc['toplam'] = round($sonuc['yemek_toplam'] + $sonuc['es_toplam'], 2);
        $sonuc['kalan_fark'] = $kalanFark;

        return $sonuc;
    }

    /**
     * Liste ve Detay ekranında ortak kullanılacak gösterim hesapları.
     * Böylece iki ekranın çalışma günü ve ödeme dağılımı mantığı tek kaynaktan yürür.
     */
    public function hesaplaOrtakGosterimDegerleri(object $p, ?object $donemBilgi, float $asgariUcretNet): array
    {
        $donemBaslangic = $donemBilgi->baslangic_tarihi ?? date('Y-m-01');
        $donemBitis = $donemBilgi->bitis_tarihi ?? date('Y-m-t');

        $donemBasTs = strtotime($donemBaslangic);
        $donemBitTs = strtotime($donemBitis);
        $donemTakvimGunu = ($donemBasTs !== false && $donemBitTs !== false)
            ? ((int) round(($donemBitTs - $donemBasTs) / 86400) + 1)
            : 30;

        $rawEkOdeme = floatval($p->guncel_toplam_ek_odeme ?? 0);

        if (!empty($p->gorev_gecmisi_var)) {
            $maasDurumu = $p->gg_maas_durumu ?? '';
            $fallbackMaasTutari = floatval($p->gg_maas_tutari ?? 0);
        } else {
            $maasDurumu = $p->maas_durumu ?? 'Brüt';
            $fallbackMaasTutari = floatval($p->maas_tutari ?? 0);
        }

        if (isset($p->hd_nominal_maas) && $p->hd_nominal_maas !== null && floatval($p->hd_nominal_maas) > 0) {
            $maasTutari = floatval($p->hd_nominal_maas);
        } else {
            $maasTutari = $fallbackMaasTutari;
        }

        // USER REQ: Görüntüleme için kendi maaşı (33.000) kalsın
        // Ancak hakediş hesabı için taban maaşı (asgari) ayrıca belirleyelim
        $hesaplamayaEsasMaas = $maasTutari;
        if ($this->hasMaasaDahilSosyalYardim($p)) {
            $hesaplamayaEsasMaas = $asgariUcretNet;
        }

        $toplamKesinti = floatval($p->guncel_toplam_kesinti ?? $p->kesinti_tutar ?? 0);
        $icraKesintisi = floatval($p->hd_icra_kesintisi ?? 0);
        $kesintiHaricIcra = $toplamKesinti - $icraKesintisi;

        $ucretsizIzinGunu = 0;
        if (isset($p->hd_ucretsiz_izin_gunu) && $p->hd_ucretsiz_izin_gunu !== null) {
            $ucretsizIzinGunu = intval($p->hd_ucretsiz_izin_gunu);
        } elseif (
            isset($p->hd_ucretsiz_izin_dusumu, $p->hd_nominal_maas)
            && $p->hd_ucretsiz_izin_dusumu !== null
            && $p->hd_nominal_maas !== null
            && floatval($p->hd_nominal_maas) > 0
        ) {
            $ucretsizIzinGunu = (int) round(floatval($p->hd_ucretsiz_izin_dusumu) / (floatval($p->hd_nominal_maas) / 30));
        }

        $raporGunu = 0;
        if (isset($p->hd_rapor_gunu) && $p->hd_rapor_gunu !== null) {
            $raporGunu = intval($p->hd_rapor_gunu);
        }

        $aktifTakvimGun = $this->getAktifTakvimGunSayisi(
            $donemBaslangic,
            $donemBitis,
            $p->ise_giris_tarihi ?? null,
            $p->isten_cikis_tarihi ?? null
        );

        if (!empty($p->gorev_gecmisi_var) && isset($p->gg_toplam_gun)) {
            $ggToplamGun = intval($p->gg_toplam_gun);
            if ($ggToplamGun > 0) {
                $aktifTakvimGun = min($aktifTakvimGun, $ggToplamGun);
            }
        }

        $calismaGunu = $this->getMaasHesapGunu($aktifTakvimGun, $donemTakvimGunu, $ucretsizIzinGunu + $raporGunu);

        $isNet = (stripos($maasDurumu, 'Net') !== false);
        $isBrut = (stripos($maasDurumu, 'Brüt') !== false || stripos($maasDurumu, 'Brut') !== false);
        $isPrimUsulu = (stripos($maasDurumu, 'Prim') !== false);

        if ($isPrimUsulu) {
            $toplamAlacagi = floatval($p->brut_maas ?? 0) + $rawEkOdeme;
        } elseif ($isNet || $isBrut) {
            $toplamAlacagi = (($maasTutari / 30) * $calismaGunu) + $rawEkOdeme;
        } else {
            $toplamAlacagi = $maasTutari + $rawEkOdeme;
        }

        // Ödeme yöntemi bazlı ek ödemeleri hesapla (Banka/Sodexo/Elden ayrımı için)
        // Bu veriler modalda/listede güncel dağılımı göstermek için gereklidir.
        if ($this->ekOdemelerCache !== null) {
            $ekOdemelerList = $this->ekOdemelerCache[$p->personel_id] ?? [];
        } else {
            $ekOdemelerQuery = $this->db->prepare("SELECT tur, tutar, aciklama FROM personel_ek_odemeler WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL AND durum = 'onaylandi'");
            $ekOdemelerQuery->execute([$p->personel_id, $p->donem_id]);
            $ekOdemelerList = $ekOdemelerQuery->fetchAll(PDO::FETCH_OBJ);
        }

        // USER REQ: Ek ödemeleri (yemek dahil) listeden tekrar topla (Görünüm tutarlılığı için)
        $rawEkOdeme = 0;
        $yontemliBankaEki = 0;
        $yontemliSodexoEki = 0;
        $mealAllowanceDeduction = 0;
        $spouseAllowanceDeduction = 0;
        $includedAllowanceDeduction = 0;

        foreach ($ekOdemelerList as $eo) {
            // MÜKERRER KONTROL: Veritabanındaki 'Maaşa Dahil Dengeleme' kayıtlarını toplama dahil etme (Çünkü aşağıda anlık hesaplanacak)
            if (stripos($eo->aciklama ?? '', 'Maaşa Dahil Dengeleme') !== false) {
                continue;
            }

            if ($this->hasMaasaDahilSosyalYardim($p)) {
                $eoTurLower = mb_strtolower((string) ($eo->tur ?? ''), 'UTF-8');
                $isDahilYemek = intval($p->yemek_yardimi_dahil ?? 0) === 1
                    && ($eoTurLower === 'yemek_yardimi_tum' || $eoTurLower === 'yemek' || strpos($eoTurLower, 'yemek') !== false);
                $isDahilEs = intval($p->es_yardimi_dahil ?? 0) === 1
                    && ($eoTurLower === 'es_yardimi' || strpos($eoTurLower, 'es_yardimi') !== false || strpos($eoTurLower, 'aile') !== false);

                if ($isDahilYemek || $isDahilEs) {
                    continue;
                }
            }

            $tutar = floatval($eo->tutar);
            $rawEkOdeme += $tutar;

            $param = $this->getParametreCached($eo->tur, $donemBaslangic);
            if ($param) {
                $isPrimUsuluLocal = (stripos($maasDurumu, 'Prim') !== false);
                $defaultYontem = $isPrimUsuluLocal ? 'elden' : 'banka';
                $yontem = $param->odeme_yontemi ?? $defaultYontem;

                if ($yontem === 'banka') {
                    $yontemliBankaEki += $tutar;
                } elseif ($yontem === 'sodexo') {
                    $yontemliSodexoEki += $tutar;
                }
            }
        }

        if ($this->hasMaasaDahilSosyalYardim($p)) {
            $fiiliGunSayisi = 25;
            if (isset($p->hd_fiili_calisma_gunu) && intval($p->hd_fiili_calisma_gunu) > 0) {
                $fiiliGunSayisi = intval($p->hd_fiili_calisma_gunu);
            }

            $dahilDagilim = $this->hesaplaMaasaDahilYardimDagilimi($p, $asgariUcretNet, $calismaGunu, $fiiliGunSayisi);
            $mealAllowanceDeduction = floatval($dahilDagilim['yemek_toplam'] ?? 0);
            $spouseAllowanceDeduction = floatval($dahilDagilim['es_toplam'] ?? 0);
            $includedAllowanceDeduction = floatval($dahilDagilim['toplam'] ?? 0);

            if ($includedAllowanceDeduction > 0) {
                $rawEkOdeme += $includedAllowanceDeduction;
                $yontemliBankaEki += $includedAllowanceDeduction;
            }
        }

        // Toplam Alacağı yeniden hesapla (Base + Güncel Ek Ödemeler)
        if ($isPrimUsulu) {
            $toplamAlacagi = floatval($p->brut_maas ?? 0) + $rawEkOdeme;
        } elseif ($isNet || $isBrut) {
            $toplamAlacagi = (($hesaplamayaEsasMaas / 30) * $calismaGunu) + $rawEkOdeme;
        } else {
            $toplamAlacagi = $hesaplamayaEsasMaas + $rawEkOdeme;
        }

        // USER REQ: Yemek Yardımı Maaşa Dahil - Listede toplam hakedişin düşmemesi için çıkarma işlemini kaldırdık.
        // Toplam hakediş zaten (Taban + Yemek) olarak 31.900 TL'ye ulaşacaktır.

        $netAlacagi = $toplamAlacagi - $kesintiHaricIcra;
        
        // USER REQ: Maaşa Dahil ise banka ödemesi zorlamasını burada da yansıt (Modal için)
        if ($this->hasMaasaDahilSosyalYardim($p)) {
             $netAlacagi = $toplamAlacagi; // İcra kesintisi net alacaktan düşer ama modalda toplam görünüyor
        }
        
        $netMaasGercek = max(0, $netAlacagi - $icraKesintisi);

        if (isset($p->dagitim_manuel) && intval($p->dagitim_manuel) === 1) {
            $bankaOdemesi = floatval($p->banka_odemesi ?? 0);
            $sodexoOdemesi = floatval($p->sodexo_odemesi ?? 0);
            $digerOdeme = floatval($p->diger_odeme ?? 0);
        } else {
            $sodexoOdemesi = floatval($p->sodexo_odemesi ?? 0);
            $digerOdeme = floatval($p->diger_odeme ?? 0);

            if ($calismaGunu >= 30) {
                $asgariUcretYatacak = $asgariUcretNet;
            } else {
                $asgariUcretYatacak = ($asgariUcretNet / 30) * $calismaGunu;
            }

            if (($p->sgk_yapilan_firma ?? "") === "İŞKUR") {
                $asgariUcretYatacak = 0;
            }

            // USER REQ: Prim Usulü personelde asgari ücret ek ödemelerin üzerine eklenmez (mükerrerlik olmaması için).
            // Onun yerine asgari ücret banka ödemesi için bir "taban" (minimum) olarak kullanılır.
            $isPrimUsulu = (stripos($maasDurumu, 'Prim') !== false);
            if ($isPrimUsulu) {
                $bankaBaz = max($asgariUcretYatacak, $yontemliBankaEki);
            } else {
                $bankaBaz = $asgariUcretYatacak + $yontemliBankaEki;
            }

            // Sodexo ek ödemesini de ekle
            $sodexoOdemesi += $yontemliSodexoEki;

            $bankaMax = max(0, $netAlacagi - $sodexoOdemesi);
            $bankaBaz = min($bankaBaz, $bankaMax);
            
            // USER REQ: Yemek Yardımı Maaşa Dahil ise banka ödemesini düzelt
            if ($this->hasMaasaDahilSosyalYardim($p)) {
                // $netAlacagi zaten (Base + Yemek) toplamıdır. Üzerine tekrar eklemeyin.
                $bankaOdemesi = max(0, $netAlacagi - $sodexoOdemesi - $icraKesintisi - ($p->diger_odeme ?? 0));
            } else {
                $bankaOdemesi = max(0, $bankaBaz - $icraKesintisi);
            }
        }

        $eldenOdeme = max(0, $netMaasGercek - $bankaOdemesi - $sodexoOdemesi - $digerOdeme);

        return [
            'maasDurumu' => $maasDurumu,
            'maasTutari' => $maasTutari,
            'rawEkOdeme' => $rawEkOdeme,
            'ucretsizIzinGunu' => $ucretsizIzinGunu,
            'calismaGunu' => $calismaGunu,
            'kesintiHaricIcra' => $kesintiHaricIcra,
            'icraKesintisi' => $icraKesintisi,
            'toplamAlacagi' => $toplamAlacagi,
            'netAlacagi' => $netAlacagi,
            'netMaasGercek' => $netMaasGercek,
            'bankaOdemesi' => $bankaOdemesi,
            'sodexoOdemesi' => $sodexoOdemesi,
            'digerOdeme' => $digerOdeme,
            'eldenOdeme' => $eldenOdeme,
            'mealAllowanceDeduction' => $mealAllowanceDeduction,
            'spouseAllowanceDeduction' => $spouseAllowanceDeduction,
            'includedAllowanceDeduction' => $includedAllowanceDeduction
        ];
    }

    /**
     * Belirli bir dönemdeki tüm personelleri getirir
     */
    public function getPersonellerByDonem($donem_id, $ids = [])
    {
        $firma_id = $_SESSION['firma_id'] ?? 0;
        $idFilter = "";

        // Dönem tarihlerini al (görev geçmişi filtrelemesi için)
        $donemSql = $this->db->prepare("SELECT baslangic_tarihi, bitis_tarihi FROM bordro_donemi WHERE id = ?");
        $donemSql->execute([$donem_id]);
        $donemDates = $donemSql->fetch(PDO::FETCH_OBJ);
        $donemBitis = $donemDates->bitis_tarihi ?? date('Y-m-t');
        $donemBaslangic = $donemDates->baslangic_tarihi ?? date('Y-m-01');

        if ($this->parametrelerCache === null) {
            $parametreModel = $this->cachedParametreModel ?? new BordroParametreModel();
            $this->parametrelerCache = $parametreModel->getAllParametrelerMap($donemBaslangic);
        }

        if ($this->ekOdemelerCache === null) {
            $this->ekOdemelerCache = [];
            $eoQuery = $this->db->prepare("SELECT personel_id, tur, tutar, aciklama FROM personel_ek_odemeler WHERE donem_id = ? AND silinme_tarihi IS NULL AND durum = 'onaylandi'");
            $eoQuery->execute([$donem_id]);
            $eoRows = $eoQuery->fetchAll(PDO::FETCH_OBJ);
            foreach ($eoRows as $row) {
                $this->ekOdemelerCache[$row->personel_id][] = $row;
            }
        }

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $idFilter = " AND bp.id IN ($placeholders)";
        }

        $sql = $this->db->prepare("
            SELECT bp.id, bp.donem_id, bp.personel_id, bp.brut_maas, bp.net_maas,
                   bp.kesinti_tutar, bp.prim_tutar, bp.hesaplama_tarihi,
                   bp.hesaplayan_id, bp.hesaplayan_ad_soyad,
                   bp.banka_odemesi, bp.sodexo_odemesi, bp.diger_odeme, bp.elden_odeme, bp.dagitim_manuel,
                   bp.calisan_gun, bp.aciklama, bp.hesaplama_detay,
                   bp.sgk_isci, bp.issizlik_isci, bp.gelir_vergisi, bp.damga_vergisi,
                   bp.sgk_isveren, bp.issizlik_isveren, bp.toplam_maliyet,
                   p.adi_soyadi, p.tc_kimlik_no, p.departman, p.gorev, 
                   p.ise_giris_tarihi, p.isten_cikis_tarihi, p.maas_tutari, p.maas_durumu,
                   p.cep_telefonu, p.resim_yolu, p.sgk_yapilan_firma, 
                   p.yemek_yardimi_dahil, p.yemek_yardimi_tutari, p.yemek_yardimi_parametre_id,
                   p.es_yardimi_dahil, p.es_yardimi_tutari, p.es_yardimi_parametre_id,
                   t_all.ekip_adi, t_all.ekip_bolge,
                   gg.maas_tutari as gg_maas_tutari,
                   gg.maas_durumu as gg_maas_durumu,
                   gg.gorev as gg_gorev,
                   gg_days.toplam_gun as gg_toplam_gun,
                   CASE WHEN gg.personel_id IS NOT NULL THEN 1 ELSE 0 END as gorev_gecmisi_var,
                   COALESCE(pk_agg.toplam_kesinti, 0) as guncel_toplam_kesinti,
                   COALESCE(eo_agg.toplam_ek_odeme, 0) as guncel_toplam_ek_odeme,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.odeme_dagilimi.icra_kesintisi')) as hd_icra_kesintisi,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.fiili_calisma_gunu')) as hd_fiili_calisma_gunu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.ucretsiz_izin_gunu')) as hd_ucretsiz_izin_gunu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.ucretsiz_izin_dusumu')) as hd_ucretsiz_izin_dusumu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.nominal_maas')) as hd_nominal_maas,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.ucretli_izin_gunu')) as hd_ucretli_izin_gunu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.rapor_gunu')) as hd_rapor_gunu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.maas_hesap_gunu')) as hd_maas_hesap_gunu
            FROM {$this->table} bp
            STRAIGHT_JOIN personel p ON bp.personel_id = p.id
            LEFT JOIN (
                SELECT pg.personel_id, 
                       GROUP_CONCAT(DISTINCT t.tur_adi SEPARATOR ', ') as ekip_adi,
                       GROUP_CONCAT(DISTINCT t.ekip_bolge SEPARATOR ', ') as ekip_bolge
                FROM personel_ekip_gecmisi pg
                JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                WHERE pg.firma_id = ? 
                  AND pg.baslangic_tarihi <= CURDATE() 
                  AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
                GROUP BY pg.personel_id
            ) t_all ON p.id = t_all.personel_id
            LEFT JOIN (
                SELECT pgg.personel_id, pgg.maas_tutari, pgg.maas_durumu, pgg.gorev
                FROM personel_gorev_gecmisi pgg
                INNER JOIN (
                    SELECT personel_id, MAX(baslangic_tarihi) AS latest_start
                    FROM personel_gorev_gecmisi
                    WHERE baslangic_tarihi <= ?
                      AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?)
                    GROUP BY personel_id
                ) gg_latest ON pgg.personel_id = gg_latest.personel_id
                           AND pgg.baslangic_tarihi = gg_latest.latest_start
            ) gg ON p.id = gg.personel_id
            LEFT JOIN (
                SELECT pgg.personel_id, 
                       SUM(DATEDIFF(LEAST(COALESCE(pgg.bitis_tarihi, ?), ?), GREATEST(pgg.baslangic_tarihi, ?)) + 1) as toplam_gun
                FROM personel_gorev_gecmisi pgg
                WHERE pgg.baslangic_tarihi <= ? AND (pgg.bitis_tarihi IS NULL OR pgg.bitis_tarihi >= ?)
                GROUP BY pgg.personel_id
            ) gg_days ON p.id = gg_days.personel_id
            LEFT JOIN (
                SELECT personel_id, SUM(tutar) as toplam_kesinti
                FROM personel_kesintileri 
                WHERE donem_id = ? AND silinme_tarihi IS NULL AND tekrar_tipi = 'tek_sefer'
                GROUP BY personel_id
            ) pk_agg ON bp.personel_id = pk_agg.personel_id
            LEFT JOIN (
                SELECT personel_id, SUM(tutar) as toplam_ek_odeme
                FROM personel_ek_odemeler 
                WHERE donem_id = ? AND silinme_tarihi IS NULL AND tekrar_tipi = 'tek_sefer'
                GROUP BY personel_id
            ) eo_agg ON bp.personel_id = eo_agg.personel_id
            WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL $idFilter
            ORDER BY p.adi_soyadi ASC
        ");

        // Parametreleri yeniden düzenle
        $sqlParams = [
            $firma_id,
            $donemBitis,       // gg inner: baslangic_tarihi <= ?
            $donemBaslangic,   // gg inner: bitis_tarihi >= ?
            $donemBitis,       // gg_days: LEAST(COALESCE(pgg.bitis_tarihi, ?), ?)
            $donemBitis,       // gg_days: ... , ?)
            $donemBaslangic,   // gg_days: GREATEST(..., ?)
            $donemBitis,       // gg_days WHERE: baslangic_tarihi <= ?
            $donemBaslangic,   // gg_days WHERE: bitis_tarihi >= ?
            $donem_id,
            $donem_id,
            $donem_id
        ];

        if (!empty($ids)) {
            $sqlParams = array_merge($sqlParams, $ids);
        }

        $sql->execute($sqlParams);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Döneme personel ekler
     * Personel işe başlama ve bitiş tarihlerine göre döneme dahil edilir:
     * - İşe giriş tarihi dönem başlangıcından önce veya dönem içinde olmalı
     * - İşten çıkış tarihi null olmalı veya dönem başlangıcından sonra olmalı
     */
    public function addPersonellerToDonem($donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // Önce mevcut dönemdeki tüm personelleri al (soft-deleted dahil)
        $sqlExisting = $this->db->prepare("SELECT personel_id, silinme_tarihi FROM {$this->table} WHERE donem_id = ?");
        $sqlExisting->execute([$donem_id]);
        $existingData = $sqlExisting->fetchAll(PDO::FETCH_ASSOC);

        $existingIds = array_column($existingData, 'personel_id');
        $softDeletedIds = [];
        foreach ($existingData as $row) {
            if ($row['silinme_tarihi'] !== null) {
                $softDeletedIds[] = $row['personel_id'];
            }
        }

        /** Firma id'yi Session'dan al */
        $firma_id = $_SESSION['firma_id'];

        // Maaş hesaplanmayan (aktif_mi = 2 veya maas_durumu = 'Maaş Hesaplanmayan') veya artık uygun olmayan personelleri dönemden çıkar (soft delete)
        // Sadece aktif_mi = 2 ise, maaş durumu 'Maaş Hesaplanmayan' ise veya tarihleri uymuyorsa çıkarılır. aktif_mi = 0 (pasif) olsa bile çıkış tarihi uygunsa kalır.
        $sqlRemove = $this->db->prepare("
        UPDATE {$this->table} bp
        INNER JOIN personel p ON bp.personel_id = p.id
        SET bp.silinme_tarihi = NOW()
        WHERE bp.donem_id = ? 
        AND bp.silinme_tarihi IS NULL
        AND (
            p.aktif_mi = 2 
            OR p.maas_durumu = 'Maaş Hesaplanmayan'
            OR (p.ise_giris_tarihi IS NOT NULL AND p.ise_giris_tarihi != '0000-00-00' AND p.ise_giris_tarihi > ?)
            OR (p.isten_cikis_tarihi IS NOT NULL AND p.isten_cikis_tarihi != '' AND p.isten_cikis_tarihi != '0000-00-00' AND p.isten_cikis_tarihi < ?)
            OR (p.aktif_mi = 0 AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '' OR p.isten_cikis_tarihi = '0000-00-00'))
        )
    ");
        $sqlRemove->execute([$donem_id, $bitis_tarihi, $baslangic_tarihi]);

        // Uygun personelleri bul (aktif_mi = 1 olanlar veya çıkış tarihi döneme uyan aktif_mi = 0 olanlar)
        // İşten çıkış tarihi: NULL, '0000-00-00', boş string veya dönem başlangıcından büyük/eşit olanlar
        $sql = $this->db->prepare("
        SELECT id, adi_soyadi, ise_giris_tarihi, isten_cikis_tarihi, aktif_mi
        FROM personel 
        WHERE firma_id = :firma_id
        AND aktif_mi != 2
        AND (maas_durumu IS NULL OR maas_durumu != 'Maaş Hesaplanmayan')
        AND (
            ise_giris_tarihi IS NULL 
            OR ise_giris_tarihi = ''
            OR ise_giris_tarihi = '0000-00-00'
            OR ise_giris_tarihi <= :bitis_tarihi
        )
        AND (
            (aktif_mi = 1 AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '' OR isten_cikis_tarihi = '0000-00-00' OR isten_cikis_tarihi >= :baslangic_tarihi))
            OR
            (aktif_mi = 0 AND isten_cikis_tarihi IS NOT NULL AND isten_cikis_tarihi != '' AND isten_cikis_tarihi != '0000-00-00' AND isten_cikis_tarihi >= :baslangic_tarihi)
        )
    ");
        $sql->bindParam(':baslangic_tarihi', $baslangic_tarihi);
        $sql->bindParam(':bitis_tarihi', $bitis_tarihi);
        $sql->bindParam(':firma_id', $firma_id);

        $sql->execute();
        $uygunPersoneller = $sql->fetchAll(PDO::FETCH_OBJ);

        $eklenenSayisi = 0;

        foreach ($uygunPersoneller as $personel) {
            // Zaten eklenmişse
            if (in_array($personel->id, $existingIds)) {
                // Eğer soft-deleted ise geri getir (ancak sadece hala uygunsa)
                if (in_array($personel->id, $softDeletedIds)) {
                    $restoreSql = $this->db->prepare("UPDATE {$this->table} SET silinme_tarihi = NULL, olusturma_tarihi = NOW() WHERE donem_id = ? AND personel_id = ?");
                    $restoreSql->execute([$donem_id, $personel->id]);
                    $eklenenSayisi++;
                }
                continue;
            }

            // Döneme ekle
            $insertSql = $this->db->prepare("
                INSERT INTO {$this->table} (donem_id, personel_id, olusturma_tarihi)
                VALUES (:donem_id, :personel_id, NOW())
            ");
            $insertSql->bindParam(':donem_id', $donem_id, PDO::PARAM_INT);
            $insertSql->bindParam(':personel_id', $personel->id, PDO::PARAM_INT);
            $insertSql->execute();
            $eklenenSayisi++;
        }

        return $eklenenSayisi;
    }

    /**
     * Personeli dönemden çıkarır (soft delete)
     */
    public function removeFromDonem($id)
    {
        return $this->softDelete($id);
    }

    /**
     * Belirli bir personelin bordro kaydını günceller
     */
    public function updateBordro($id, $data)
    {
        $setClause = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            $setClause[] = "$key = :$key";
            $params[":$key"] = $value;
        }

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET " . implode(', ', $setClause) . "
            WHERE id = :id
        ");

        return $sql->execute($params);
    }

    /**
     * Bordro hesaplama verilerini kaydeder
     */
    /**
     * Bordro hesaplama verilerini kaydeder
     */
    public function saveBordroHesaplama($id, $hesaplamaData)
    {
        $sql = $this->db->prepare("
        UPDATE {$this->table} 
        SET brut_maas = :brut_maas,
            sgk_isci = :sgk_isci,
            issizlik_isci = :issizlik_isci,
            gelir_vergisi = :gelir_vergisi,
            damga_vergisi = :damga_vergisi,
            net_maas = :net_maas,
            sgk_isveren = :sgk_isveren,
            issizlik_isveren = :issizlik_isveren,
            toplam_maliyet = :toplam_maliyet,
            kesinti_tutar = :kesinti_tutar,
            prim_tutar = :prim_tutar,
            fazla_mesai_tutar = :fazla_mesai_tutar,
            calisan_gun = :calisan_gun,
            kumulatif_matrah = :kumulatif_matrah,
            sodexo_odemesi = :sodexo_odemesi,
            banka_odemesi = :banka_odemesi,
            elden_odeme = :elden_odeme,
            hesaplama_detay = :hesaplama_detay,
            hesaplama_tarihi = NOW(),
            hesaplayan_id = :hesaplayan_id,
            hesaplayan_ad_soyad = :hesaplayan_ad_soyad
        WHERE id = :id
    ");

        $sql->bindParam(':id', $id, PDO::PARAM_INT);
        $sql->bindParam(':hesaplayan_id', $hesaplamaData['hesaplayan_id']);
        $sql->bindParam(':hesaplayan_ad_soyad', $hesaplamaData['hesaplayan_ad_soyad']);
        $sql->bindParam(':brut_maas', $hesaplamaData['brut_maas']);
        $sql->bindParam(':sgk_isci', $hesaplamaData['sgk_isci']);
        $sql->bindParam(':issizlik_isci', $hesaplamaData['issizlik_isci']);
        $sql->bindParam(':gelir_vergisi', $hesaplamaData['gelir_vergisi']);
        $sql->bindParam(':damga_vergisi', $hesaplamaData['damga_vergisi']);
        $sql->bindParam(':net_maas', $hesaplamaData['net_maas']);
        $sql->bindParam(':sgk_isveren', $hesaplamaData['sgk_isveren']);
        $sql->bindParam(':issizlik_isveren', $hesaplamaData['issizlik_isveren']);
        $sql->bindParam(':toplam_maliyet', $hesaplamaData['toplam_maliyet']);
        $sql->bindParam(':kesinti_tutar', $hesaplamaData['toplam_kesinti']);
        $sql->bindParam(':prim_tutar', $hesaplamaData['toplam_ek_odeme']);
        $mesaiTutar = $hesaplamaData['fazla_mesai_tutar'] ?? 0;
        $sql->bindParam(':fazla_mesai_tutar', $mesaiTutar);
        $calisanGun = $hesaplamaData['calisan_gun'] ?? 30;
        $sql->bindParam(':calisan_gun', $calisanGun);
        $kumulatif = $hesaplamaData['kumulatif_matrah'] ?? 0;
        $sql->bindParam(':kumulatif_matrah', $kumulatif);
        $sodexoOdemesi = $hesaplamaData['sodexo_odemesi'] ?? 0;
        $sql->bindParam(':sodexo_odemesi', $sodexoOdemesi);
        $bankaOdemesi = $hesaplamaData['banka_odemesi'] ?? 0;
        $sql->bindParam(':banka_odemesi', $bankaOdemesi);
        $eldenOdeme = $hesaplamaData['elden_odeme'] ?? 0;
        $sql->bindParam(':elden_odeme', $eldenOdeme);
        $hesaplamaDetay = $hesaplamaData['hesaplama_detay'] ?? null;
        $sql->bindParam(':hesaplama_detay', $hesaplamaDetay);

        return $sql->execute();
    }

    /**
     * Personelin dönemdeki toplam kesintilerini getirir
     */
    public function getDonemKesintileri($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT SUM(tutar) as toplam 
            FROM personel_kesintileri 
            WHERE personel_id = ? AND donem_id = ? AND tekrar_tipi = 'tek_sefer' AND silinme_tarihi IS NULL AND (durum = 'onaylandi' OR tur = 'icra')
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ)->toplam ?? 0;
    }

    /**
     * Personelin dönemdeki toplam ek ödemelerini getirir
     */
    public function getDonemEkOdemeleri($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT SUM(tutar) as toplam 
            FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND (tekrar_tipi IN ('tek_sefer', 'profil_bazli') OR tekrar_tipi IS NULL) AND silinme_tarihi IS NULL AND durum = 'onaylandi'
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ)->toplam ?? 0;
    }
    /**
     * Personelin bordrolarını getirir
     */
    public function getPersonelBordrolari($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT bp.*, bd.donem_adi, bd.baslangic_tarihi, bd.kapali_mi
            FROM {$this->table} bp
            LEFT JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? 
            AND bp.silinme_tarihi IS NULL
            ORDER BY bp.id DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin toplam kazanç bilgilerini getirir (Dashboard için)
     * Tüm dönemlerdeki net maaşların toplamı
     */
    public function getPersonelFinansalOzet($personel_id)
    {
        // 1. Toplam Hakediş: Personel görsün veya kapalı olan dönemlerdeki (Net Maaşlar + O dönemde mahsup edilen avanslar)
        // 2. Alınan Ödeme: Bugüne kadar onaylanmış avanslar + Kapatılmış dönemlerdeki (yani bankaya yatan) maaş ödemeleri
        // 3. Kalan Bakiye: Toplam Hakediş - Alınan Ödeme

        // Hakediş: Personel görsün veya kapalı olan dönemlerdeki banka ödemesi toplamı
        $sqlNet = $this->db->prepare("
            SELECT SUM(IF(bp.banka_odemesi > 0, bp.banka_odemesi, bp.net_maas)) as toplam_net
            FROM {$this->table} bp
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL AND (bd.personel_gorsun = 1 OR bd.kapali_mi = 1)
        ");
        $sqlNet->execute([$personel_id]);
        $toplam_net = $sqlNet->fetch(PDO::FETCH_OBJ)->toplam_net ?? 0;

        // Personel görsün veya kapalı olanlarda mahsup edilen avanslar
        $sqlAvansKesinti = $this->db->prepare("
            SELECT SUM(pk.tutar) as toplam_kesinti_avans
            FROM personel_kesintileri pk
            INNER JOIN bordro_donemi bd ON pk.donem_id = bd.id
            WHERE pk.personel_id = ? AND pk.tur = 'avans' AND pk.tekrar_tipi = 'tek_sefer' AND (bd.personel_gorsun = 1 OR bd.kapali_mi = 1) AND pk.silinme_tarihi IS NULL
        ");
        $sqlAvansKesinti->execute([$personel_id]);
        $toplam_avans_kesinti = $sqlAvansKesinti->fetch(PDO::FETCH_OBJ)->toplam_kesinti_avans ?? 0;

        // Toplam Hakediş
        $toplam_hakedis = $toplam_net + $toplam_avans_kesinti;

        // Onaylanmış tüm avanslar
        $sqlAvans = $this->db->prepare("
            SELECT SUM(tutar) as toplam_avans 
            FROM personel_avanslari 
            WHERE personel_id = ? AND durum = 'onaylandi' AND silinme_tarihi IS NULL
        ");
        $sqlAvans->execute([$personel_id]);
        $alinan_odeme = $sqlAvans->fetch(PDO::FETCH_OBJ)->toplam_avans ?? 0;

        // Kapatılmış dönemlerdeki banka ödemesi toplamı (Gerçekte ödenen maaşlar)
        $sqlOdenenMaas = $this->db->prepare("
            SELECT SUM(IF(bp.banka_odemesi > 0, bp.banka_odemesi, bp.net_maas)) as toplam_odenen
            FROM {$this->table} bp
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL AND bd.kapali_mi = 1
        ");
        $sqlOdenenMaas->execute([$personel_id]);
        $toplam_odenen_maas = $sqlOdenenMaas->fetch(PDO::FETCH_OBJ)->toplam_odenen ?? 0;

        // Toplam Alınan Ödeme
        $alinan_odeme += $toplam_odenen_maas;

        // Son personel görsün 1 olan dönemin adını bul (ID'ye göre son eklenen)
        $sqlSonDonem = $this->db->prepare("
            SELECT bd.donem_adi, bd.baslangic_tarihi 
            FROM {$this->table} bp
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL AND bd.personel_gorsun = 1
            ORDER BY bd.id DESC LIMIT 1
        ");
        $sqlSonDonem->execute([$personel_id]);
        $son_donem = $sqlSonDonem->fetch(PDO::FETCH_OBJ);
        
        $son_donem_adi = null;
        if ($son_donem) {
            if (!empty($son_donem->donem_adi)) {
                $son_donem_adi = $son_donem->donem_adi;
            } else {
                $tarih = strtotime($son_donem->baslangic_tarihi);
                $aylar = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
                $son_donem_adi = $aylar[date('n', $tarih) - 1] . ' ' . date('Y', $tarih);
            }
        }

        return (object) [
            'toplam_hakedis' => $toplam_hakedis,
            'alinan_odeme' => $alinan_odeme,
            'son_donem_adi' => $son_donem_adi
        ];
    }

    /**
     * Personele kesinti ekler
     */
    /**
     * Personele kesinti ekler
     * @param int $personel_id Personel ID
     * @param int $donem_id Dönem ID
     * @param string $aciklama Kesinti açıklaması
     * @param float $tutar Kesinti tutarı
     * @param string $tur Kesinti türü
     * @param string $durum Onay durumu (beklemede, onaylandi, reddedildi) - varsayılan: beklemede
     */
    public function addKesinti($personel_id, $donem_id, $aciklama, $tutar, $tur = 'diger', $durum = 'beklemede', $icra_id = null, $tarih = null)
    {
        $tarih = $tarih ?: date('Y-m-d');
        $sql = $this->db->prepare("
            INSERT INTO personel_kesintileri (personel_id, donem_id, aciklama, tutar, tur, durum, icra_id, tarih, olusturma_tarihi)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $sql->execute([$personel_id, $donem_id, $aciklama, $tutar, $tur, $durum, $icra_id, $tarih]);
    }

    /**
     * Personele ek ödeme ekler
     */
    public function addEkOdeme($personel_id, $donem_id, $aciklama, $tutar, $tur = 'diger', $tarih = null)
    {
        $tarih = $tarih ?: date('Y-m-d');
        $sql = $this->db->prepare("
            INSERT INTO personel_ek_odemeler (personel_id, donem_id, aciklama, tutar, tur, tarih, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        return $sql->execute([$personel_id, $donem_id, $aciklama, $tutar, $tur, $tarih]);
    }

    /**
     * Personelin sürekli kesintilerini dönem için otomatik oluşturur
     * Bordro hesaplaması yapılmadan önce çağrılmalıdır
     * 
     * @param int $personel_id Personel ID
     * @param int $donem_id Dönem ID
     * @param string $donem YYYY-MM formatında dönem
     * @param float $brutMaas Personelin brüt maaşı (oran hesabı için)
     * @param float $netMaas Personelin net maaşı (oran hesabı için)
     * @return int Oluşturulan kayıt sayısı
     */
    public function olusturSurekliKesintiler($personel_id, $donem_id, $donem, $brutMaas = 0, $netMaas = 0)
    {
        $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();

        // Aktif sürekli kesintileri getir
        $surekliKesintiler = $PersonelKesintileriModel->getAktifSurekliKesintiler($personel_id, $donem);

        $olusturulanSayisi = 0;

        foreach ($surekliKesintiler as $kesinti) {
            // Tutarı hesapla
            $tutar = 0;
            $hesaplamaTipi = $kesinti->hesaplama_tipi ?? 'sabit';

            if ($hesaplamaTipi === 'sabit') {
                $tutar = floatval($kesinti->tutar ?? 0);

                // Taksitli ise tutarı böl
                if (($kesinti->tekrar_tipi ?? '') === 'taksitli' && intval($kesinti->taksit_sayisi ?? 1) > 1) {
                    $taksitSayisi = intval($kesinti->taksit_sayisi);
                    $birimTutar = round($tutar / $taksitSayisi, 2);

                    // Hangi taksitte olduğumuzu bulalım (küsürat farkı için son taksit kontrolü)
                    if (!empty($kesinti->start_donem_date)) {
                        $d1 = new \DateTime(date('Y-m-01', strtotime($kesinti->start_donem_date)));
                        $d2 = new \DateTime(date('Y-m-01', strtotime($donem . '-01')));
                        $interval = $d1->diff($d2);
                        $diffMonths = ($interval->y * 12) + $interval->m;

                        if ($diffMonths + 1 >= $taksitSayisi) {
                            // Son taksit (veya sonrası ise güvenlik amacıyla kalanı al)
                            $tutar = $tutar - ($birimTutar * ($taksitSayisi - 1));
                        } else {
                            $tutar = $birimTutar;
                        }
                        
                        $kesinti->aciklama .= " (" . ($diffMonths + 1) . "/" . $taksitSayisi . ". Taksit)";
                    } else {
                        $tutar = $birimTutar;
                    }
                }
            } elseif ($hesaplamaTipi === 'oran_net' && $netMaas > 0) {
                $oran = floatval($kesinti->oran ?? 0);
                $tutar = $netMaas * ($oran / 100);
            } elseif ($hesaplamaTipi === 'oran_brut' && $brutMaas > 0) {
                $oran = floatval($kesinti->oran ?? 0);
                $tutar = $brutMaas * ($oran / 100);
            } elseif ($hesaplamaTipi === 'aylik_gun_kesinti') {
                // Gün bazlı kesintiler için aylık tutarı olduğu gibi kaydet
                // Gerçek hesaplama hesaplaMaas fonksiyonunda çalışma gününe göre yapılacak
                $tutar = floatval($kesinti->tutar ?? 0);
            } else {
                // Diğer durumlar için varsayılan tutarı kullan
                $tutar = floatval($kesinti->tutar ?? 0);
            }

            // Dönem için kesinti oluştur
            $result = $PersonelKesintileriModel->olusturDonemKesintisi($kesinti, $donem_id, round($tutar, 2));

            if ($result) {
                $olusturulanSayisi++;
            }
        }

        return $olusturulanSayisi;
    }

    /**
     * Personelin sürekli ek ödemelerini dönem için otomatik oluşturur
     * Bordro hesaplaması yapılmadan önce çağrılmalıdır
     * 
     * @param int $personel_id Personel ID
     * @param int $donem_id Dönem ID
     * @param string $donem YYYY-MM formatında dönem
     * @param float $brutMaas Personelin brüt maaşı (oran hesabı için)
     * @param float $netMaas Personelin net maaşı (oran hesabı için)
     * @return int Oluşturulan kayıt sayısı
     */
    public function olusturSurekliEkOdemeler($personel_id, $donem_id, $donem, $brutMaas = 0, $netMaas = 0)
    {
        $PersonelEkOdemelerModel = new \App\Model\PersonelEkOdemelerModel();

        // Aktif sürekli ek ödemeleri getir
        $surekliOdemeler = $PersonelEkOdemelerModel->getAktifSurekliOdemeler($personel_id, $donem);

        $olusturulanSayisi = 0;

        foreach ($surekliOdemeler as $odeme) {
            // Tutarı hesapla
            $tutar = 0;
            $hesaplamaTipi = $odeme->hesaplama_tipi ?? 'sabit';

            if ($hesaplamaTipi === 'sabit') {
                $tutar = floatval($odeme->tutar ?? 0);
            } elseif ($hesaplamaTipi === 'oran_net' && $netMaas > 0) {
                $oran = floatval($odeme->oran ?? 0);
                $tutar = $netMaas * ($oran / 100);
            } elseif ($hesaplamaTipi === 'oran_brut' && $brutMaas > 0) {
                $oran = floatval($odeme->oran ?? 0);
                $tutar = $brutMaas * ($oran / 100);
            } elseif (in_array($hesaplamaTipi, ['aylik_gun_brut', 'aylik_gun_net', 'gunluk_brut', 'gunluk_net', 'gunluk_kismi_muaf'])) {
                // Gün bazlı hesaplamalar için aylık tutarı olduğu gibi kaydet
                // Gerçek hesaplama hesaplaMaas fonksiyonunda çalışma gününe göre yapılacak
                $tutar = floatval($odeme->tutar ?? 0);
            } else {
                // Diğer durumlar için varsayılan tutarı kullan
                $tutar = floatval($odeme->tutar ?? 0);
            }

            // USER REQ: Yemek Yardımı Maaşa Dahil ise, parametrik yemek yardımını oluşturma
            // Artik engellemiyoruz, bordroda gorunmesi isteniyor.
            // Yeni Mantık: Yemek Tutarı = (Hedef Net - Asgari Net) farkı kadar olacak.
            $isYemekParam = (
                ($odeme->parametre_kodu ?? '') === 'yemek_yardimi_tum' || 
                ($odeme->parametre_kodu ?? '') === 'yemek' || 
                ($odeme->parametre_id ?? 0) == 35 ||
                strpos(mb_strtolower($odeme->parametre_adi ?? '', 'UTF-8'), 'yemek') !== false ||
                strpos(mb_strtolower($odeme->tur ?? '', 'UTF-8'), 'yemek') !== false
            );
            
            if ($isYemekParam) {
                $pSql = $this->db->prepare("SELECT yemek_yardimi_dahil, maas_tutari, yemek_yardimi_tutari, yemek_yardimi_parametre_id FROM personel WHERE id = ?");
                $pSql->execute([$personel_id]);
                $pRec = $pSql->fetch(PDO::FETCH_OBJ);
                
                if ($pRec && intval($pRec->yemek_yardimi_dahil) == 1) {
                    // Sistemdeki en güncel asgari ücreti al (Parametrelerden)
                    if ($this->cachedParametreModel === null) {
                        $this->cachedParametreModel = new \App\Model\BordroParametreModel();
                    }
                    $asgariNetVal = $this->cachedParametreModel->getGenelAyar('asgari_ucret_net') ?? 28075.50;
                    $hedefNet = floatval($pRec->maas_tutari);

                    // Fiili çalışma gününü al (Eğer fonksiyonda hesaplanmamışsa burada hesapla)
                    if (!isset($fiiliGun)) {
                        $donemBaslangic = $donem . '-01';
                        $donemBitis = date('Y-m-t', strtotime($donemBaslangic));
                        $fiiliGun = $this->getFiiliCalismaGunuSayisi($personel_id, $donemBaslangic, $donemBitis);
                    }
                    
                    // Günlük farkı bul (Aylık fark / fiiliGun) - hesaplaMaas ile uyumlu
                    $aylikFark = max(0, $hedefNet - $asgariNetVal);
                    $gunlukFark = $fiiliGun > 0 ? ceil($aylikFark / $fiiliGun) : 0;
                    
                    // USER REQ: Günlük yemek ücreti manuel veya parametredeki tutarı geçmemeli
                    $gunlukLimit = floatval($pRec->yemek_yardimi_tutari ?? 0);
                    if ($gunlukLimit <= 0 && !empty($pRec->yemek_yardimi_parametre_id)) {
                        $paramYemek = $this->cachedParametreModel->find($pRec->yemek_yardimi_parametre_id);
                        $gunlukLimit = floatval($paramYemek->varsayilan_tutar ?? 0);
                    }

                    if ($gunlukLimit > 0 && $gunlukFark > $gunlukLimit) {
                        $gunlukFark = $gunlukLimit;
                    }
                    
                    // Toplam tutar = Fiili Gün * Günlük Fark
                    $tutar = $fiiliGun * $gunlukFark;
                    $odeme->aciklama = "Maaşa Dahil Dengeleme (" . $fiiliGun . " Gün x " . number_format($gunlukFark, 2, ',', '.') . " ₺)";
                }
            }

            // Dönem için ek ödeme oluştur
            $result = $PersonelEkOdemelerModel->olusturDonemOdemesi($odeme, $donem_id, round($tutar, 2));

            if ($result) {
                $olusturulanSayisi++;
            }
        }

        return $olusturulanSayisi;
    }

    /**
     * Dönemdeki tüm personeller için sürekli kesinti/ek ödemeleri otomatik oluşturur
     * @param int $donem_id Dönem ID
     * @return array ['kesinti' => int, 'ek_odeme' => int] Oluşturulan kayıt sayıları
     */
    public function olusturDonemSurekliKayitlar($donem_id)
    {
        // Dönem bilgisini çek
        $sql = $this->db->prepare("SELECT baslangic_tarihi FROM bordro_donemi WHERE id = ?");
        $sql->execute([$donem_id]);
        $donemBilgi = $sql->fetch(PDO::FETCH_OBJ);

        if (!$donemBilgi) {
            return ['kesinti' => 0, 'ek_odeme' => 0];
        }

        $donem = date('Y-m', strtotime($donemBilgi->baslangic_tarihi));

        // Dönemdeki personelleri getir
        $personeller = $this->getPersonellerByDonem($donem_id);

        $toplamKesinti = 0;
        $toplamEkOdeme = 0;

        foreach ($personeller as $personel) {
            // Brüt ve net maaşı personel kaydından al
            $brutMaas = floatval($personel->maas_tutari ?? 0);
            $netMaas = floatval($personel->net_maas ?? 0);

            // Net maaş yoksa brütten tahmin et (yaklaşık %70)
            if ($netMaas <= 0 && $brutMaas > 0) {
                $netMaas = $brutMaas * 0.70;
            }

            $toplamKesinti += $this->olusturSurekliKesintiler(
                $personel->personel_id,
                $donem_id,
                $donem,
                $brutMaas,
                $netMaas
            );

            $toplamEkOdeme += $this->olusturSurekliEkOdemeler(
                $personel->personel_id,
                $donem_id,
                $donem,
                $brutMaas,
                $netMaas
            );
        }
        return ['kesinti' => $toplamKesinti, 'ek_odeme' => $toplamEkOdeme];
    }

    /**
     * İş emri sonucuna göre iş türü id map'i döner (trimlenmiş anahtar kullanır).
     */
    private function getIsTuruIdMapBySonuc($firmaId)
    {
        if (isset($this->isTuruIdMapCache[$firmaId])) {
            return $this->isTuruIdMapCache[$firmaId];
        }


        $sql = $this->db->prepare("\n            SELECT MAX(id) as id, TRIM(is_emri_sonucu) as is_emri_sonucu\n            FROM tanimlamalar\n            WHERE grup = 'is_turu'\n            AND firma_id = ?\n            AND silinme_tarihi IS NULL\n            AND is_emri_sonucu IS NOT NULL\n            AND is_emri_sonucu != ''\n            GROUP BY TRIM(is_emri_sonucu)\n        ");
        $sql->execute([$firmaId]);
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);

        $map = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row->is_emri_sonucu ?? ''));
            if ($key === '') {
                continue;
            }
            $map[$key] = intval($row->id);
        }
        $this->isTuruIdMapCache[$firmaId] = $map;
        return $map;

    }

    /**
     * Verilen satır için tarih bazlı birim ücreti çözer.
     */
    private function resolveIsTuruBirimUcret($TanimlamalarModel, $isTuruId, $isEmriSonucu, $tarih, $isAracli, $firmaId, &$ucretCache, $isTuruIdMap, $isOkuma = false)
    {
        $resolvedIsTuruId = intval($isTuruId);
        $sonucKey = trim((string) $isEmriSonucu);

        if ($resolvedIsTuruId <= 0 && $sonucKey !== '' && isset($isTuruIdMap[$sonucKey])) {
            $resolvedIsTuruId = intval($isTuruIdMap[$sonucKey]);
        }

        if ($resolvedIsTuruId <= 0) {
            return 0.0;
        }

        $cacheKey = $resolvedIsTuruId . '|' . $tarih . '|' . ($isAracli ? '1' : '0') . '|' . ($isOkuma ? '1' : '0');
        if (!array_key_exists($cacheKey, $this->isTuruUcretCache)) {

            $this->isTuruUcretCache[$cacheKey] = floatval($TanimlamalarModel->getIsTuruUcretiByTarih(
                $resolvedIsTuruId,
                $tarih,
                $isAracli,
                $firmaId,
                $isOkuma
            ));
        }

        return floatval($this->isTuruUcretCache[$cacheKey]);
    }

    /**
     * Personelin puantaj (yapılan işler) verilerine göre ek ödemelerini oluşturur
     * 
     * Hesaplama mantığı:
     * - is_emri_sonucu bazında gruplama yapılır
     * - Sadece birim ücreti > 0 olan iş sonuçları hesaplanır
     * - Yeni normalizasyon (is_emri_sonucu_id) ve eski string alanları desteklenir
     */
    public function olusturPuantajOdemeleri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // 1. Personelin ekip kodunu bul
        if ($this->personelModelCache === null) {
            $this->personelModelCache = new \App\Model\PersonelModel();
        }
        $PersonelModel = $this->personelModelCache;

        $personel = $PersonelModel->find($personel_id);

        if (!$personel) {
            return;
        }

        // 2. Önceki puantaj kaynaklı ek ödemeleri temizle (duplicate önlemek için)
        // Açıklamada "[Puantaj]" etiketi olanları siliyoruz
        $deleteSql = $this->db->prepare("
            DELETE FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND aciklama LIKE '[Puantaj]%'
        ");
        $deleteSql->execute([$personel_id, $donem_id]);

        // 3. Araç kullanım durumunu ve departmanı belirle
        if ($this->tanimlamalarModelCache === null) {
            $this->tanimlamalarModelCache = new \App\Model\TanimlamalarModel();
        }
        $TanimlamalarModel = $this->tanimlamalarModelCache;

        $isAracli = (isset($personel->arac_kullanim) && $personel->arac_kullanim === 'Kendi Aracı');
        $isOkuma = (isset($personel->departman) && stripos($personel->departman, 'Okuma') !== false);
        $firmaId = intval($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));
        $isTuruIdMap = $this->getIsTuruIdMapBySonuc($firmaId);
        $ucretCache = [];

        // 4. Yapılan işleri is_emri_sonucu bazında grupla
        // Hem yeni (is_emri_sonucu_id) hem eski (is_emri_sonucu string) alanları destekle
        $sql = $this->db->prepare("
            SELECT 
                DATE(t.tarih) as is_tarihi,
                t.is_emri_sonucu_id,
                COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu,
                COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
                tn.rapor_sekmesi,
                SUM(t.sonuclanmis) as adet
            FROM yapilan_isler t
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
            WHERE t.personel_id = ? 
            AND t.firma_id = ?
            AND t.tarih BETWEEN ? AND ?
            AND (t.is_emri_sonucu_id > 0 OR (t.is_emri_sonucu IS NOT NULL AND t.is_emri_sonucu != ''))
            AND t.silinme_tarihi IS NULL
            GROUP BY DATE(t.tarih), t.is_emri_sonucu_id, COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu), COALESCE(tn.tur_adi, t.is_emri_tipi), tn.rapor_sekmesi
        ");
        $sql->execute([$personel_id, $personel->firma_id, $baslangic_tarihi, $bitis_tarihi]);
        $yapilanIsler = $sql->fetchAll(PDO::FETCH_OBJ);

        if (empty($yapilanIsler)) {
            return;
        }

        foreach ($yapilanIsler as $is) {
            $is->birim_ucret = $this->resolveIsTuruBirimUcret(
                $TanimlamalarModel,
                intval($is->is_emri_sonucu_id ?? 0),
                $is->is_emri_sonucu ?? '',
                $is->is_tarihi ?? $baslangic_tarihi,
                $isAracli,
                $firmaId,
                $ucretCache,
                $isTuruIdMap,
                $isOkuma
            );
        }

        // 5. Manuel Düşüm İşlemi
        $manuelDusumTotal = 0;
        $gecerliIsler = [];

        // "Manuel Düşüm" bul ve diğerlerinden ayır
        foreach ($yapilanIsler as $is) {
            if ($is->is_emri_sonucu === 'Manuel Düşüm') {
                // "Manuel Düşüm" ler eksi (-) olarak kaydedildiği için abs() alıyoruz
                $manuelDusumTotal += abs(floatval($is->adet));
            } else {
                $gecerliIsler[] = $is;
            }
        }

        // Eğer düşülecek sayı varsa, işlerin sayılarından düş
        if ($manuelDusumTotal > 0) {
            // Ayarlardan hangi kalemden düşüleceğini al
            $SettingsModel = new \App\Model\SettingsModel();
            $firmaId = $personel->firma_id ?? $_SESSION['firma_id'] ?? 0;
            $reportSettings = $SettingsModel->getAllSettingsAsKeyValue($firmaId);
            $dusulecekIsTuru = $reportSettings['dusulecek_is_turu'] ?? 'Ödeme Yaptırıldı';

            // 1. Önce seçilen iş türünden düşmeye çalış
            foreach ($gecerliIsler as &$is) {
                if ($is->is_emri_sonucu === $dusulecekIsTuru) {
                    $mevcutAdet = floatval($is->adet);
                    if ($mevcutAdet > 0) {
                        if ($mevcutAdet >= $manuelDusumTotal) {
                            $is->adet = $mevcutAdet - $manuelDusumTotal;
                            $manuelDusumTotal = 0;
                        } else {
                            $manuelDusumTotal -= $mevcutAdet;
                            $is->adet = 0;
                        }
                    }
                    break;
                }
            }
            unset($is);

            // 2. Eğer hala düşülecek sayı kalmışsa (veya seçilen tür bulunamadıysa), 
            // en yüksek adeti olandan başlayarak düş (Sıralama yaparak)
            if ($manuelDusumTotal > 0) {
                usort($gecerliIsler, function ($a, $b) {
                    return floatval($b->adet) <=> floatval($a->adet);
                });

                foreach ($gecerliIsler as &$is) {
                    if ($manuelDusumTotal <= 0)
                        break;

                    // Sadece ücretlendirilen işlerden düş
                    if (floatval($is->birim_ucret ?? 0) <= 0)
                        continue;

                    $mevcutAdet = floatval($is->adet);
                    if ($mevcutAdet > 0) {
                        if ($mevcutAdet >= $manuelDusumTotal) {
                            $is->adet = $mevcutAdet - $manuelDusumTotal;
                            $manuelDusumTotal = 0;
                        } else {
                            $manuelDusumTotal -= $mevcutAdet;
                            $is->adet = 0;
                        }
                    }
                }
                unset($is); // Referans hatasını önlemek için
            }
        }

        // 6. is_emri_sonucu bazlı hesapla
        foreach ($gecerliIsler as $is) {
            $isEmriSonucu = $is->is_emri_sonucu;
            $adet = floatval($is->adet);

            $birimUcret = floatval($is->birim_ucret ?? 0);
            if ($birimUcret <= 0 || $adet <= 0 || empty($isEmriSonucu)) {
                continue;
            }

            // "Sayaç Değişimi" ve "Endeks Okuma" tipindeki işleri genel puantajdan hariç tut 
            // (olusturSayacDegisimOdemeleri ve EndeksOkumaModel üzerinden ayrıca hesaplanıyor)
            $isEmriTipi = $is->is_emri_tipi ?? '';
            $isEmriSonucu = $is->is_emri_sonucu ?? '';
            $raporSekmesi = $is->rapor_sekmesi ?? '';
            
            if ($raporSekmesi === 'sokme_takma' || 
                $raporSekmesi === 'endeks_okuma' ||
                stripos($isEmriTipi, 'Sayaç Değişimi') !== false || 
                stripos($isEmriSonucu, 'Sayaç Değişimi') !== false ||
                stripos($isEmriTipi, 'Endeks Okuma') !== false ||
                stripos($isEmriSonucu, 'Endeks Okuma') !== false) {
                continue;
            }

            if ($adet > 0) {
                $toplamTutar = round($adet * $birimUcret, 2);
                // Açıklama formatı: [Puantaj] Sonuç (Adet x Birim ₺)
                $aciklama = "[Puantaj] $isEmriSonucu (" . round($adet) . " Adet x " . number_format($birimUcret, 2, ',', '.') . " ₺)";

                // Aynı DB bağlantısı üzerinden doğrudan INSERT yap
                $insertSql = $this->db->prepare("
                    INSERT INTO personel_ek_odemeler 
                    (personel_id, donem_id, tur, aciklama, tutar, tekrar_tipi, durum, aktif, created_at)
                    VALUES (?, ?, 'prim', ?, ?, 'tek_sefer', 'onaylandi', 1, NOW())
                ");
                $insertSql->execute([$personel_id, $donem_id, $aciklama, $toplamTutar]);
            }
        }
    }

    /**
     * Personelin sayaç değişim verilerine göre ek ödemelerini oluşturur
     * 
     * İş Kuralı:
     * - sayac_degisim tablosundaki verileri baz alır
     * - is_emri_sonucuna göre ücretlendirme yapılır
     */
    public function olusturSayacDegisimOdemeleri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // 1. Personel bilgilerini al
        $PersonelModel = new \App\Model\PersonelModel();
        $personel = $PersonelModel->find($personel_id);
        if (!$personel) return;

        // Sadece [Sayaç] ile başlayanları temizle. [Puantaj] olanlar (SKA vb.) yukarıda olusturPuantajOdemeleri tarafından oluşturuldu.
        $this->db->prepare("
            DELETE FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? 
            AND aciklama LIKE '[Sayaç]%'
        ")->execute([$personel_id, $donem_id]);

        // 3. Tanımlamalar tablosundan ücretli iş türlerini al
        $TanimlamalarModel = new \App\Model\TanimlamalarModel();
        $isAracli = (isset($personel->arac_kullanim) && $personel->arac_kullanim === 'Kendi Aracı');
        $isOkuma = (isset($personel->departman) && stripos($personel->departman, 'Okuma') !== false);
        $firmaId = intval($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));
        $isTuruIdMap = $this->getIsTuruIdMapBySonuc($firmaId);
        $ucretCache = [];

        // 4. Sayaç değişimlerini is_emri_sonucu bazında paylaştırmalı grupla
        // Not: is_sayisi kolonu int olduğu için, aynı islem_id kökünü paylaşan personel sayısına göre 1/n ağırlık hesaplanır.
        $firmaId = intval($personel->firma_id ?? ($_SESSION['firma_id'] ?? 0));
        $sql = $this->db->prepare("
            SELECT 
                DATE(t.tarih) as is_tarihi,
                t.isemri_sonucu,
                ROUND(SUM(CASE WHEN pay.personel_sayisi > 0 THEN 1.0 / pay.personel_sayisi ELSE 0 END), 4) as adet
            FROM sayac_degisim t
            JOIN (
                SELECT 
                    tarih,
                    SUBSTRING_INDEX(islem_id, '_', 1) as ortak_islem_id,
                    COUNT(*) as personel_sayisi
                FROM sayac_degisim
                WHERE firma_id = ?
                AND tarih BETWEEN ? AND ?
                AND silinme_tarihi IS NULL
                GROUP BY tarih, SUBSTRING_INDEX(islem_id, '_', 1)
            ) pay ON pay.tarih = t.tarih
                AND pay.ortak_islem_id = SUBSTRING_INDEX(t.islem_id, '_', 1)
            WHERE t.firma_id = ?
            AND t.personel_id = ? 
            AND t.tarih BETWEEN ? AND ?
            AND t.silinme_tarihi IS NULL
            GROUP BY DATE(t.tarih), t.isemri_sonucu
        ");
        $sql->execute([$firmaId, $baslangic_tarihi, $bitis_tarihi, $firmaId, $personel_id, $baslangic_tarihi, $bitis_tarihi]);
        $veriler = $sql->fetchAll(PDO::FETCH_OBJ);

        // 5. Kaydet
        foreach ($veriler as $v) {
            $isemriSonucu = trim((string) ($v->isemri_sonucu ?? ''));
            $adet = floatval($v->adet);

            if ($adet <= 0 || $isemriSonucu === '') {
                continue;
            }

            $birimUcret = $this->resolveIsTuruBirimUcret(
                $TanimlamalarModel,
                0,
                $isemriSonucu,
                $v->is_tarihi ?? $baslangic_tarihi,
                $isAracli,
                $firmaId,
                $ucretCache,
                $isTuruIdMap,
                $isOkuma
            );

            if ($birimUcret <= 0) {
                continue;
            }

            $toplamTutar = round($adet * $birimUcret, 2);
            $adetText = (abs($adet - round($adet)) < 0.0001)
                ? number_format($adet, 0, ',', '.')
                : number_format($adet, 2, ',', '.');
            $aciklama = "[Sayaç] $isemriSonucu (" . $adetText . " Adet x " . number_format($birimUcret, 2, ',', '.') . " ₺)";

            $this->db->prepare("
                INSERT INTO personel_ek_odemeler 
                (personel_id, donem_id, tur, aciklama, tutar, tekrar_tipi, durum, aktif, created_at)
                VALUES (?, ?, 'prim', ?, ?, 'tek_sefer', 'onaylandi', 1, NOW())
            ")->execute([$personel_id, $donem_id, $aciklama, $toplamTutar]);
        }
    }



    /**
     * Personelin nöbetlerini dönem için ek ödeme olarak oluşturur
     * 
     * Nöbet tipleri (nobet_tipi):
     * - standart: Hafta İçi
     * - hafta_sonu: Hafta Sonu
     * - resmi_tatil: Resmi Tatil
     * - ozel: Özel
     */
    public function olusturNobetOdemeleri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // 1. Önceki nöbet kaynaklı ek ödemeleri temizle (duplicate önlemek için)
        // [Puantaj] ve [Nöbet] gibi otomatik etiketli olanları siliyoruz (Soft delete yapılmış olsa bile garanti için)
        $this->db->prepare("
            DELETE FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND aciklama LIKE '[Nöbet]%'
        ")->execute([$personel_id, $donem_id]);

        // 2. Dönem içindeki onaylanmış nöbetleri çek
        $sql = $this->db->prepare("
            SELECT nobet_tipi, COUNT(*) as adet
            FROM nobetler
            WHERE personel_id = ? 
            AND nobet_tarihi BETWEEN ? AND ?
            AND silinme_tarihi IS NULL
            AND yonetici_onayi = 1
            AND (durum IS NULL OR durum NOT IN ('talep_edildi', 'reddedildi', 'iptal'))
            GROUP BY nobet_tipi
        ");
        $sql->execute([$personel_id, $baslangic_tarihi, $bitis_tarihi]);
        $nobetGruplari = $sql->fetchAll(PDO::FETCH_OBJ);

        if (empty($nobetGruplari)) {
            return;
        }

        // 3. Ücretleri BordroParametreModel'den al
        $haftaIciParam  = $this->getParametreCached('hafta_ici_nobet', $baslangic_tarihi);
        $haftaSonuParam = $this->getParametreCached('hafta_sonu_nobet', $baslangic_tarihi);

        $nobetUcretleri = [
            'standart' => floatval($haftaIciParam->varsayilan_tutar ?? 0),
            'hafta_sonu' => floatval($haftaSonuParam->varsayilan_tutar ?? 0),
            'resmi_tatil' => floatval($haftaSonuParam->varsayilan_tutar ?? 0),
            'ozel' => floatval($haftaSonuParam->varsayilan_tutar ?? 0)
        ];

        foreach ($nobetGruplari as $nobet) {
            $tip = $nobet->nobet_tipi ?: 'standart';
            $adet = intval($nobet->adet);
            $birimUcret = floatval($nobetUcretleri[$tip] ?? 0);

            if ($adet > 0 && $birimUcret > 0) {
                $toplamTutar = round($adet * $birimUcret, 2);
                $tipEtiketi = match ($tip) {
                    'standart' => 'Hafta İçi',
                    'hafta_sonu' => 'Hafta Sonu',
                    'resmi_tatil' => 'Resmi Tatil',
                    'ozel' => 'Özel',
                    default => 'Nöbet'
                };

                $aciklama = "[Nöbet] $tipEtiketi ($adet Adet x " . number_format($birimUcret, 2, ',', '.') . " ₺)";

                $paramId = ($tip === 'standart') ? ($haftaIciParam->id ?? null) : ($haftaSonuParam->id ?? null);
                $paramKod = ($tip === 'standart') ? 'hafta_ici_nobet' : 'hafta_sonu_nobet';

                // Aynı DB bağlantısı ($this->db) üzerinden doğrudan INSERT yap
                // PersonelEkOdemelerModel ayrı bağlantı kullandığı için hesaplama sırasında
                // kayıtlar görünemeyebiliyordu
                $insertSql = $this->db->prepare("
                    INSERT INTO personel_ek_odemeler 
                    (personel_id, donem_id, tur, parametre_id, aciklama, tutar, tekrar_tipi, durum, aktif, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'tek_sefer', 'onaylandi', 1, NOW())
                ");
                $insertSql->execute([$personel_id, $donem_id, $paramKod, $paramId, $aciklama, $toplamTutar]);
            }
        }
    }

    /**
     * Personelin kaçak kontrol primlerini hesaplar ve ek ödeme olarak oluşturur
     * 
     * İş Kuralı:
     * - Personel ay içinde 260'tan fazla kaçak kontrol işlemi yaparsa
     * - 260'ı aşan her işlem için bordro_genel_ayarlar'dan alınan kacak_kontrol_primi tutarı kadar prim hak eder
     * - Prim personel_ek_odemeler tablosuna kaydedilir
     * 
     * @param int $personel_id Personel ID
     * @param int $donem_id Bordro dönem ID
     * @param string $baslangic_tarihi Dönem başlangıç tarihi (Y-m-d)
     * @param string $bitis_tarihi Dönem bitiş tarihi (Y-m-d)
     * @return array ['adet' => int, 'prim' => float] Hesaplanan prim bilgisi
     */
    public function olusturKacakKontrolPrimleri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        $sonuc = ['toplam_islem' => 0, 'brut_prim' => 0, 'muaf_limit' => 0, 'net_prim' => 0];

        // 1. Önceki kaçak kontrol primlerini temizle (duplicate önlemek için)
        $deleteSql = $this->db->prepare("
            DELETE FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND aciklama LIKE '[Kaçak Kontrol]%'
        ");
        $deleteSql->execute([$personel_id, $donem_id]);

        // 2. Dönem tarihini al (parametre çekimi için)
        $donemTarihi = $baslangic_tarihi;

        // 3. Bordro parametrelerinden kacak_kontrol_primi ayarlarını al
        $kacakParam = $this->getParametreCached('kacak_kontrol_primi', $donemTarihi);

        if (!$kacakParam) {
            return $sonuc; // Parametre tanımlı değilse çık
        }

        // Birim tutar (varsayılan_tutar) ve aylık muaf limit
        $birimTutar = floatval($kacakParam->varsayilan_tutar ?? 0);
        $aylikMuafLimit = floatval($kacakParam->aylik_muaf_limit ?? 0);

        if ($birimTutar <= 0) {
            return $sonuc; // Birim tutar tanımlı değilse çık
        }

        $sonuc['muaf_limit'] = $aylikMuafLimit;

        // 4. Personelin dönem içindeki kaçak kontrol işlemlerini hesapla
        $sql = $this->db->prepare("
            SELECT id, personel_ids, sayi 
            FROM kacak_kontrol 
            WHERE tarih BETWEEN ? AND ? 
            AND silinme_tarihi IS NULL
            AND personel_ids IS NOT NULL
        ");
        $sql->execute([$baslangic_tarihi, $bitis_tarihi]);
        $kayitlar = $sql->fetchAll(PDO::FETCH_OBJ);

        $toplamIslem = 0;

        foreach ($kayitlar as $kayit) {
            // Bu kayıtta personel var mı kontrol et
            $personelIds = array_filter(array_map('trim', explode(',', $kayit->personel_ids)));

            if (empty($personelIds)) {
                continue;
            }

            // Bu personel kayıtta var mı?
            if (in_array($personel_id, $personelIds)) {
                // Kayıttaki sayının tamamını bu personele ekle
                $toplamIslem += floatval($kayit->sayi);
            }
        }

        $sonuc['toplam_islem'] = round($toplamIslem, 2);

        // 5. Brüt prim hesapla (toplam işlem × birim tutar)
        $brutPrim = $toplamIslem * $birimTutar;
        $sonuc['brut_prim'] = round($brutPrim, 2);

        // 6. Net prim hesapla (brüt prim - aylık muaf limit)
        $netPrim = $brutPrim - $aylikMuafLimit;

        // Negatif olamaz
        if ($netPrim <= 0) {
            return $sonuc; // Muaf limitin altında, prim yok
        }

        $netPrim = round($netPrim, 2);
        $sonuc['net_prim'] = $netPrim;

        // 7. Ek ödeme oluştur
        // Muaf işlem sayısını hesapla: Muaf tutar / varsayılan tutar
        $muafIslem = ($birimTutar > 0) ? round($aylikMuafLimit / $birimTutar) : 0;

        $aciklama = "[Kaçak Kontrol] (" . round($toplamIslem) . " işlem Toplam)(" . $muafIslem . " işlem Muaf)";

        // Aynı DB bağlantısı üzerinden doğrudan INSERT yap
        $insertSql = $this->db->prepare("
            INSERT INTO personel_ek_odemeler 
            (personel_id, donem_id, tur, aciklama, tutar, tekrar_tipi, durum, aktif, created_at)
            VALUES (?, ?, 'prim', ?, ?, 'tek_sefer', 'onaylandi', 1, NOW())
        ");
        $insertSql->execute([$personel_id, $donem_id, $aciklama, $netPrim]);

        return $sonuc;
    }

    /**
     * Personelin onaylanmış avanslarını dönem için kesinti olarak oluşturur
     */
    public function olusturAvansKesintileri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // Onaylanmış tüm avansları alıp hedef döneme kod tarafında eşliyoruz.
        // Bu sayede hem "ayın 14'ü" kuralı hem de taksitli avanslar doğru dönemlere dağıtılır.
        $sql = $this->db->prepare("
            SELECT id, tutar, talep_tarihi, aciklama, odeme_sekli
            FROM personel_avanslari
            WHERE personel_id = ?
              AND durum = 'onaylandi'
              AND silinme_tarihi IS NULL
            ORDER BY talep_tarihi ASC, id ASC
        ");
        $sql->execute([$personel_id]);
        $avanslar = $sql->fetchAll(PDO::FETCH_OBJ);

        $toplamAvans = 0;
        $donemAy = date('Y-m', strtotime($baslangic_tarihi));

        foreach ($avanslar as $avans) {
            $tarih = date('d.m.Y', strtotime($avans->talep_tarihi));
            $talepTarihi = date('Y-m-d', strtotime($avans->talep_tarihi));
            $talepGunu = (int) date('d', strtotime($talepTarihi));

            // 14 veya öncesi talep -> bir önceki ay döneminden başlat
            $hedefBaslangicAy = $talepGunu <= 14
                ? date('Y-m', strtotime($talepTarihi . ' -1 month'))
                : date('Y-m', strtotime($talepTarihi));

            $odemeSekli = strtolower(trim((string) ($avans->odeme_sekli ?? 'tek')));
            if ($odemeSekli === '3') {
                $taksitSayisi = 3;
            } elseif ($odemeSekli === '2' || $odemeSekli === 'taksit') {
                $taksitSayisi = 2;
            } else {
                $taksitSayisi = 1;
            }

            $toplamTutar = floatval($avans->tutar);
            $parcaTutar = $taksitSayisi > 1 ? round($toplamTutar / $taksitSayisi, 2) : round($toplamTutar, 2);

            for ($i = 0; $i < $taksitSayisi; $i++) {
                $taksitAy = date('Y-m', strtotime($hedefBaslangicAy . '-01 +' . $i . ' month'));
                if ($taksitAy !== $donemAy) {
                    continue;
                }

                $taksitNo = $i + 1;
                $tutar = ($taksitNo === $taksitSayisi)
                    ? round($toplamTutar - ($parcaTutar * ($taksitSayisi - 1)), 2)
                    : $parcaTutar;

                $aciklamaPattern = "[Avans] #{$avans->id}/{$taksitNo}-{$taksitSayisi} - %";

                // Bu taksit için dönemde kayıt var mı kontrol et,
                // varsa (soft-delete edilmiş de olabilir) geri getir.
                $mevcutKontrol = $this->db->prepare("
                    SELECT id, durum FROM personel_kesintileri
                    WHERE personel_id = ? AND donem_id = ? AND tur = 'avans' 
                    AND aciklama LIKE ?
                    ORDER BY id DESC LIMIT 1
                ");
                $mevcutKontrol->execute([$personel_id, $donem_id, $aciklamaPattern]);
                $mevcut = $mevcutKontrol->fetch();

                if ($mevcut) {
                    $restoreSql = $this->db->prepare("
                        UPDATE personel_kesintileri 
                        SET silinme_tarihi = NULL, tutar = ?, durum = 'onaylandi', updated_at = NOW()
                        WHERE id = ?
                    ");
                    $restoreSql->execute([$tutar, $mevcut['id']]);
                    $toplamAvans += $tutar;
                    continue;
                }

                $kullaniciAciklama = trim((string) ($avans->aciklama ?? ''));
                $kullaniciAciklama = $kullaniciAciklama !== '' ? $kullaniciAciklama : 'Avans Talebi';
                $aciklama = "[Avans] #{$avans->id}/{$taksitNo}-{$taksitSayisi} - {$tarih} - {$kullaniciAciklama}";
                $this->addKesinti($personel_id, $donem_id, $aciklama, $tutar, 'avans', 'onaylandi');
                $toplamAvans += $tutar;
            }
        }

        return $toplamAvans;
    }

    /**
     * Personelin aktif icra dosyalarını dönem için kesinti olarak oluşturur
     * 
     * İş Kuralı:
     * 1. Durumu 'devam_ediyor' olan icra dosyaları sıra numarasına göre alınır
     * 2. Toplam kesinti tutarı (oran bazlı, örn. maaşın %25'i) hesaplanır
     * 3. Bu tutar sırasıyla icra dosyalarına dağıtılır:
     *    - Önce 1. sıradaki icranın kalan borcuna kadar kesilir
     *    - Kalan tutar varsa 2. sıradaki icraya aktarılır
     *    - Bu şekilde devam eder
     * 
     * Örnek: Maaşın %25'i = 7.500 TL, 1. icra kalan borç = 5.000 TL, 2. icra = 10.000 TL
     *   → 1. icraya 5.000 TL kesilir (borç biter)
     *   → Kalan 2.500 TL, 2. icraya kesilir
     */
    /**
     * Personel profilinde seçili olan (yemek, eş yardımı vb.) ödemeleri dönem için oluşturur
     */
    public function olusturProfilBazliOdemeler($personel_id, $donem_id, $baslangic_tarihi)
    {
        $PersonelModel = new \App\Model\PersonelModel();
        $personel = $PersonelModel->find($personel_id);

        if (!$personel) return;

        // 1. Yemek Yardımı
        if (!empty($personel->yemek_yardimi_aliyor) && !empty($personel->yemek_yardimi_parametre_id)) {
            // Force refresh yemek_yardimi_dahil status from DB to be sure
            $pSql = $this->db->prepare("SELECT yemek_yardimi_dahil, maas_tutari FROM personel WHERE id = ?");
            $pSql->execute([$personel_id]);
            $pFresh = $pSql->fetch(PDO::FETCH_OBJ);
            $yemekYardimiDahil = intval($pFresh->yemek_yardimi_dahil ?? $personel->yemek_yardimi_dahil ?? 0);
            $hedefNetMaas = floatval($pFresh->maas_tutari ?? $personel->maas_tutari ?? 0);

            $param = $this->cachedParametreModel->find($personel->yemek_yardimi_parametre_id);
            if ($param) {
                // Eğer personelde manuel yemek tutarı girilmişse (0'dan büyükse) onu kullan, yoksa parametredeki varsayılanı kullan
                $tutar = (floatval($personel->yemek_yardimi_tutari ?? 0) > 0) 
                    ? floatval($personel->yemek_yardimi_tutari) 
                    : floatval($param->varsayilan_tutar ?? 0);

                // USER REQ: Yemek Yardımı Maaşa Dahil ise dengeleme tutarını hesapla
                $aciklama = "[Yemek Yardımı] " . ($param->etiket ?? 'Yemek Yardımı');
                if ($yemekYardimiDahil == 1) {
                    $asgariNetVal = $this->cachedParametreModel->getGenelAyar('asgari_ucret_net') ?? 28075.50;
                    $hedefNet = $hedefNetMaas;
                    
                    // Çalışılan gün sayısını al
                    $donemTarihi = $baslangic_tarihi ?? date('Y-m-01');
                    $donemBitis = date('Y-m-t', strtotime($donemTarihi));
                    $fiiliGun = $this->getFiiliCalismaGunuSayisi($personel_id, $donemTarihi, $donemBitis);

                    // Günlük farkı bul (Aylık fark / fiiliGun) - hesaplaMaas ile uyumlu
                    $aylikFark = max(0, $hedefNet - $asgariNetVal);
                    $gunlukFark = $fiiliGun > 0 ? ceil($aylikFark / $fiiliGun) : 0;
                    
                    // USER REQ: Günlük yemek ücreti manuel veya parametredeki tutarı geçmemeli
                    $gunlukLimit = (floatval($personel->yemek_yardimi_tutari ?? 0) > 0) 
                        ? floatval($personel->yemek_yardimi_tutari) 
                        : floatval($param->varsayilan_tutar ?? 0);
                    
                    if ($gunlukLimit > 0 && $gunlukFark > $gunlukLimit) {
                        $gunlukFark = $gunlukLimit;
                    }
                    
                    $tutar = $fiiliGun * $gunlukFark;
                    $aciklama = "[Yemek Yardımı] Maaşa Dahil Dengeleme (" . $fiiliGun . " Gün x " . number_format($gunlukFark, 2, ',', '.') . " ₺)";
                }

                if (($tutar > 0 || $param->hesaplama_tipi === 'aylik_fiili_gun_net') && $yemekYardimiDahil !== 1) {
                    $this->db->prepare("
                        INSERT INTO personel_ek_odemeler 
                        (personel_id, donem_id, tur, parametre_id, aciklama, tutar, tekrar_tipi, durum, aktif, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'profil_bazli', 'onaylandi', 1, NOW())
                    ")->execute([$personel_id, $donem_id, $param->kod, $param->id, $aciklama, round($tutar, 2)]);
                }
            }
        }

        // 2. Eş Yardımı
        if (!empty($personel->es_yardimi_aliyor) && !empty($personel->es_yardimi_parametre_id)) {
            $param = $this->cachedParametreModel->find($personel->es_yardimi_parametre_id);
            if ($param) {
                $tutar = floatval($param->varsayilan_tutar ?? 0);
                if ($tutar > 0) {
                    $aciklama = "[Eş Yardımı] " . ($param->etiket ?? 'Eş Yardımı');
                    $this->db->prepare("
                        INSERT INTO personel_ek_odemeler 
                        (personel_id, donem_id, tur, parametre_id, aciklama, tutar, tekrar_tipi, durum, aktif, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, 'tek_sefer', 'onaylandi', 1, NOW())
                    ")->execute([$personel_id, $donem_id, $param->kod, $param->id, $aciklama, $tutar]);
                }
            }
        }
    }

    /**
     * Puantaj (personel_izinleri + tanimlamalar) sisteminden fiili çalışma gününü alır.
     */
    public function getFiiliCalismaGunuSayisi($personel_id, $baslangic_tarihi, $bitis_tarihi)
    {
        if ($this->personelModelCache === null) {
            $this->personelModelCache = new \App\Model\PersonelModel();
        }
        $PersonelModel = $this->personelModelCache;

        $personel = $PersonelModel->find($personel_id);
        if (!$personel) return 0;

        $p_giris = strtotime($personel->ise_giris_tarihi ?: '1970-01-01');
        $p_cikis = strtotime($personel->isten_cikis_tarihi ?: '2099-12-31');

        $sql = $this->db->prepare("
            SELECT pi.baslangic_tarihi, pi.bitis_tarihi, t.normal_mesai_sayilir
            FROM personel_izinleri pi
            JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.personel_id = ? 
            AND pi.onay_durumu = 'Onaylandı'
            AND pi.silinme_tarihi IS NULL
            AND (
                (pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ?)
            )
        ");
        $sql->execute([$personel_id, $bitis_tarihi, $baslangic_tarihi]);
        $izinler = $sql->fetchAll(PDO::FETCH_OBJ);

        $gunluk_durum = [];
        foreach ($izinler as $izin) {
            $cur = strtotime($izin->baslangic_tarihi);
            $end = strtotime($izin->bitis_tarihi);
            while ($cur <= $end) {
                $date_str = date('Y-m-d', $cur);
                if ($date_str >= $baslangic_tarihi && $date_str <= $bitis_tarihi) {
                    $gunluk_durum[$date_str] = intval($izin->normal_mesai_sayilir);
                }
                $cur = strtotime("+1 day", $cur);
            }
        }

        $fiili_gun_sayisi = 0;
        $cur = strtotime($baslangic_tarihi);
        $end = strtotime($bitis_tarihi);

        while ($cur <= $end) {
            $date_str = date('Y-m-d', $cur);
            if ($cur >= $p_giris && $cur <= $p_cikis) {
                if (isset($gunluk_durum[$date_str])) {
                    if ($gunluk_durum[$date_str] === 1) {
                        $fiili_gun_sayisi++;
                    }
                } else {
                    $isSunday = date('w', $cur) == 0;
                    if (!$isSunday) {
                        $fiili_gun_sayisi++;
                    }
                }
            }
            $cur = strtotime("+1 day", $cur);
        }

        return $fiili_gun_sayisi;
    }

    public function olusturIcraKesintileri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // İcra parametresini bul
        $param = $this->getParametreCached('icra', $bitis_tarihi);
        $paramId = $param ? $param->id : null;

        // Hesaplama tipi ve oran bilgisini al
        $hTip = $param ? $param->hesaplama_tipi : 'sabit';
        if ($hTip === 'oran_bazli_net' || $hTip === 'oran_net')
            $hTip = 'asgari_oran_net';
        if ($hTip === 'oran_bazli_brut')
            $hTip = 'oran_brut';
        $oran = $param ? floatval($param->oran ?? 0) : 0;

        // Aktif icra dosyalarını SIRA NUMARASINA GÖRE getir (devam_ediyor olanlar)
        $sql = $this->db->prepare("
            SELECT id, icra_dairesi, dosya_no, aylik_kesinti_tutari, baslangic_tarihi, toplam_borc, sira, kesinti_tipi, kesinti_orani
            FROM personel_icralari
            WHERE personel_id = ? 
            AND durum = 'devam_ediyor'
            AND silinme_tarihi IS NULL
            AND (baslangic_tarihi IS NULL OR baslangic_tarihi <= ?)
            AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?)
            ORDER BY sira ASC, id ASC
        ");
        $sql->execute([$personel_id, $bitis_tarihi, $baslangic_tarihi]);
        $icralar = $sql->fetchAll(PDO::FETCH_OBJ);

        if (empty($icralar)) {
            return 0;
        }

        $olusturulanSayisi = 0;

        // Her icra için kalan borç bilgisini hesapla
        $icraKalanBorclar = [];
        foreach ($icralar as $icra) {
            $sqlKesilen = $this->db->prepare("
                SELECT SUM(tutar) as toplam 
                FROM personel_kesintileri 
                WHERE icra_id = ? AND donem_id != ? AND silinme_tarihi IS NULL AND durum = 'onaylandi'
            ");
            $sqlKesilen->execute([$icra->id, $donem_id]);
            $resKesilen = $sqlKesilen->fetch(PDO::FETCH_OBJ);
            $oncekiKesilen = $resKesilen ? floatval($resKesilen->toplam ?? 0) : 0;
            $kalanBorc = max(0, floatval($icra->toplam_borc) - $oncekiKesilen);

            $icraKalanBorclar[$icra->id] = $kalanBorc;
        }

        foreach ($icralar as $icra) {
            $kalanBorc = $icraKalanBorclar[$icra->id];

            // Borç bitmişse icrayı atla ve varsa bu dönem kaydını temizle
            if ($kalanBorc <= 0) {
                $this->db->prepare("UPDATE personel_kesintileri SET silinme_tarihi = NOW() WHERE personel_id = ? AND donem_id = ? AND icra_id = ? AND silinme_tarihi IS NULL")
                    ->execute([$personel_id, $donem_id, $icra->id]);
                continue;
            }

            // Bireysel ayarlar var mı?
            $bizHTip = $icra->kesinti_tipi ?? 'tutar';
            $bizOran = floatval($icra->kesinti_orani ?? 0);

            if ($bizHTip === 'net_yuzde') {
                $finalHTip = 'oran_net';
                $finalOran = $bizOran;
            } elseif ($bizHTip === 'asgari_yuzde') {
                $finalHTip = 'asgari_oran_net';
                $finalOran = $bizOran;
            } else {
                $finalHTip = 'sabit';
                $finalOran = 0;
            }

            // Sabit tutarlı kesintilerde başlangıç tutarı
            $tutar = floatval($icra->aylik_kesinti_tutari);

            if ($finalHTip === 'oran_net' || $finalHTip === 'asgari_oran_net') {
                $tutar = 0; // Placeholder, hesaplaMaas içinde güncellenecek
            } else {
                // Sabit tutarlı kesintilerde kalan borç kontrolü yap
                if ($tutar <= 0)
                    continue;

                if ($tutar > $kalanBorc) {
                    $tutar = $kalanBorc;
                }
            }

            $aciklama = "[İcra] " . $icra->icra_dairesi . " (" . $icra->dosya_no . ")";

            // Bu icra dosyası için bu dönemde zaten bir kesinti var mı kontrol et
            $mevcutKontrol = $this->db->prepare("
                SELECT id, durum FROM personel_kesintileri
                WHERE personel_id = ? AND donem_id = ? AND tur = 'icra' AND icra_id = ? AND silinme_tarihi IS NULL
            ");
            $mevcutKontrol->execute([$personel_id, $donem_id, $icra->id]);
            $mevcut = $mevcutKontrol->fetch(PDO::FETCH_OBJ);

            if ($mevcut) {
                // Kayıt var, tutarı ve açıklamasını güncelle
                $this->db->prepare("UPDATE personel_kesintileri SET tutar = ?, aciklama = ?, durum = 'onaylandi', parametre_id = ?, hesaplama_tipi = ?, oran = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$tutar, $aciklama, $paramId, $finalHTip, $finalOran, $mevcut->id]);
            } else {
                // Kayıt yok, oluştur
                $sqlAdd = $this->db->prepare("
                    INSERT INTO personel_kesintileri (personel_id, donem_id, aciklama, tutar, tur, durum, icra_id, parametre_id, hesaplama_tipi, oran, olusturma_tarihi)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $sqlAdd->execute([$personel_id, $donem_id, $aciklama, $tutar, 'icra', 'onaylandi', $icra->id, $paramId, $finalHTip, $finalOran]);
                $olusturulanSayisi++;
            }
        }

        return $olusturulanSayisi;
    }

    /**
     * Personelin ücretsiz izin kesintilerini dönem için otomatik oluşturur
     * Bordro hesaplaması yapılmadan önce çağrılmalıdır
     * 
     * Hesaplama:
     * - Günlük ücret = Brüt maaş / 30 (sabit)
     * - Kesinti = Günlük ücret × Ücretsiz izin gün sayısı (dönem içinde)
     * 
     * NOT: izin_tipi alanı hem metin ("Mazeret İzni") hem de ID (5) olarak saklanabiliyor.
     * Bu fonksiyon her iki formatı da destekler.
     * 
     * @param int $personel_id Personel ID
     * @param int $donem_id Dönem ID
     * @param string $donem_baslangic Dönem başlangıç tarihi (Y-m-d)
     * @param string $donem_bitis Dönem bitiş tarihi (Y-m-d)
     * @param float $brutMaas Personelin brüt maaşı
     * @return array ['toplam_gun' => int, 'toplam_kesinti' => float, 'izin_detaylari' => array]
     */
    public function olusturUcretsizIzinKesintileri($personel_id, $donem_id, $donem_baslangic, $donem_bitis, $brutMaas)
    {
        $sonuc = [
            'toplam_gun' => 0,
            'toplam_kesinti' => 0,
            'izin_detaylari' => []
        ];

        // Günlük ücreti hesapla (brüt maaş / 30)
        // NOT: Prim Usülü gibi maaş türlerinde brüt maaş 0 olabilir
        // Bu durumda kesinti hesaplanmaz ama izin gün sayısı yine de döndürülmeli
        $gunlukUcret = $brutMaas / 30;

        // NOT: Mevcut kayıtları silmiyoruz, her izin türü için kontrol edip güncelliyoruz
        // Bu sayede onay durumu korunuyor

        // Dönem tarihlerini DateTime objelerine çevir
        $donemBaslangicDate = new \DateTime($donem_baslangic);
        $donemBitisDate = new \DateTime($donem_bitis);

        // Ücretsiz izin türlerini bul (tanimlamalar tablosundan ucretli_mi = 0 olanlar)
        $izinTurleriSql = $this->db->prepare("
            SELECT id, tur_adi FROM tanimlamalar 
            WHERE grup = 'izin_turu' AND ucretli_mi = 0 AND silinme_tarihi IS NULL
        ");
        $izinTurleriSql->execute();
        $ucretsizIzinTurleri = $izinTurleriSql->fetchAll(PDO::FETCH_OBJ);

        if (empty($ucretsizIzinTurleri)) {
            return $sonuc;
        }

        // Ücretsiz izin türlerinin ID'lerini ve adlarını bir map'e al
        $izinTuruIds = [];
        $izinTuruAdlari = [];  // ID => tur_adi
        $izinTuruAdlariReverse = []; // tur_adi => tur_adi (metin kontrolü için)

        foreach ($ucretsizIzinTurleri as $tur) {
            $izinTuruIds[] = $tur->id;
            $izinTuruAdlari[$tur->id] = $tur->tur_adi;
            $izinTuruAdlariReverse[strtolower($tur->tur_adi)] = $tur->tur_adi;
        }

        // Personelin onaylanmış izinlerini çek (dönemle kesişen)
        // Hem ID hem de metin bazlı izin türlerini kontrol ediyoruz
        $idPlaceholders = implode(',', array_fill(0, count($izinTuruIds), '?'));

        // Metin bazlı izin türü adlarını da placeholder olarak hazırla
        $izinTuruAdlariArray = array_values($izinTuruAdlari);
        $textPlaceholders = implode(',', array_fill(0, count($izinTuruAdlariArray), '?'));

        $izinSql = $this->db->prepare("
            SELECT pi.id, pi.izin_tipi_id, pi.baslangic_tarihi, pi.bitis_tarihi
            FROM personel_izinleri pi
            WHERE pi.personel_id = ?
            AND pi.onay_durumu = 'Onaylandı'
            AND (
                pi.izin_tipi_id IN ($idPlaceholders) 
            )
            AND pi.baslangic_tarihi <= ?
            AND pi.bitis_tarihi >= ?
            AND pi.silinme_tarihi IS NULL
        ");

        // Parametreleri birleştir: personel_id + ID'ler + tarihler
        $params = array_merge(
            [$personel_id],
            $izinTuruIds,
            [$donem_bitis, $donem_baslangic]
        );
        $izinSql->execute($params);
        $izinler = $izinSql->fetchAll(PDO::FETCH_OBJ);

        if (empty($izinler)) {
            return $sonuc;
        }

        $toplamIzinGunu = 0;
        $izinDetaylari = [];

        foreach ($izinler as $izin) {
            // İzin başlangıç ve bitiş tarihlerini al
            $izinBaslangic = new \DateTime($izin->baslangic_tarihi);
            $izinBitis = new \DateTime($izin->bitis_tarihi);

            // Dönemle kesişen tarihleri bul
            $kesisimBaslangic = max($izinBaslangic, $donemBaslangicDate);
            $kesisimBitis = min($izinBitis, $donemBitisDate);

            // Gün sayısını hesapla (başlangıç ve bitiş dahil)
            if ($kesisimBaslangic <= $kesisimBitis) {
                $gunSayisi = $kesisimBaslangic->diff($kesisimBitis)->days + 1;
                $toplamIzinGunu += $gunSayisi;

                // İzin türü adını belirle
                $izinTuruAdi = 'Ücretsiz İzin';
                if (isset($izinTuruAdlari[$izin->izin_tipi_id])) {
                    $izinTuruAdi = $izinTuruAdlari[$izin->izin_tipi_id];
                }

                $izinDetaylari[] = [
                    'izin_id' => $izin->id,
                    'izin_turu' => $izinTuruAdi,
                    'izin_baslangic' => $izin->baslangic_tarihi,
                    'izin_bitis' => $izin->bitis_tarihi,
                    'donem_icinde_gun' => $gunSayisi
                ];
            }
        }

        if ($toplamIzinGunu <= 0) {
            return $sonuc;
        }

        // Toplam kesintiyi hesapla
        $toplamKesinti = round($gunlukUcret * $toplamIzinGunu, 2);

        // İzin türlerine göre grupla ve açıklama oluştur
        $izinGruplari = [];
        foreach ($izinDetaylari as $detay) {
            $turAdi = $detay['izin_turu'];
            if (!isset($izinGruplari[$turAdi])) {
                $izinGruplari[$turAdi] = 0;
            }
            $izinGruplari[$turAdi] += $detay['donem_icinde_gun'];
        }

        // Her izin türü için ayrı kesinti kaydı oluştur veya güncelle
        // Sadece günlük ücret > 0 ise kesinti kaydı oluştur (Prim Usülü için atla)
        if ($gunlukUcret > 0) {
            foreach ($izinGruplari as $turAdi => $gunSayisi) {
                $kesinti = round($gunlukUcret * $gunSayisi, 2);
                $aciklama = "[Ücretsiz İzin] $turAdi ($gunSayisi gün x " . number_format($gunlukUcret, 2, ',', '.') . " ₺)";
                $aciklamaPattern = "[Ücretsiz İzin] $turAdi (%";

                // Bu izin türü için mevcut aktif kesinti var mı kontrol et
                // Soft-delete yapılan kayıtları dikkate alma (yeniden oluşturulacak)
                $mevcutKontrol = $this->db->prepare("
                    SELECT id FROM personel_kesintileri
                    WHERE personel_id = ? AND donem_id = ? AND tur = 'izin_kesinti' 
                    AND aciklama LIKE ?
                    AND silinme_tarihi IS NULL
                ");
                $mevcutKontrol->execute([$personel_id, $donem_id, $aciklamaPattern]);
                $mevcut = $mevcutKontrol->fetch(PDO::FETCH_OBJ);

                if ($mevcut) {
                    // Mevcut aktif kayıt var, sadece tutarı ve açıklamayı güncelle (durumu koru)
                    $updateSql = $this->db->prepare("
                        UPDATE personel_kesintileri 
                        SET tutar = ?, aciklama = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateSql->execute([$kesinti, $aciklama, $mevcut->id]);
                } else {
                    // Mevcut kayıt yok, yeni oluştur
                    $this->addKesinti($personel_id, $donem_id, $aciklama, $kesinti, 'izin_kesinti', 'onaylandi');
                }
            }
        } // if ($gunlukUcret > 0)
        $sonuc['toplam_gun'] = $toplamIzinGunu;
        $sonuc['toplam_kesinti'] = $toplamKesinti;
        $sonuc['izin_detaylari'] = $izinDetaylari;

        return $sonuc;
    }

    /**
     * Personelin ücretli izin günlerini dönem için hesaplar
     */
    public function getUcretliIzinGunu($personel_id, $donem_baslangic, $donem_bitis)
    {
        // Ücretli izin türlerini bul (tanimlamalar tablosundan ucretli_mi = 1 olanlar)
        $izinTurleriSql = $this->db->prepare("
            SELECT id FROM tanimlamalar 
            WHERE grup = 'izin_turu' 
            AND ucretli_mi = 1 
            AND kisa_kod NOT IN ('X', 'HT', 'GT')
            AND silinme_tarihi IS NULL
        ");
        $izinTurleriSql->execute();
        $ucretliIzinTurIds = $izinTurleriSql->fetchAll(PDO::FETCH_COLUMN);

        if (empty($ucretliIzinTurIds)) {
            return 0;
        }

        $idPlaceholders = implode(',', array_fill(0, count($ucretliIzinTurIds), '?'));
        $izinSql = $this->db->prepare("
            SELECT baslangic_tarihi, bitis_tarihi
            FROM personel_izinleri
            WHERE personel_id = ?
            AND onay_durumu = 'Onaylandı'
            AND izin_tipi_id IN ($idPlaceholders)
            AND baslangic_tarihi <= ?
            AND bitis_tarihi >= ?
            AND silinme_tarihi IS NULL
        ");

        $params = array_merge([$personel_id], $ucretliIzinTurIds, [$donem_bitis, $donem_baslangic]);
        $izinSql->execute($params);
        $izinler = $izinSql->fetchAll(PDO::FETCH_OBJ);

        $toplamGun = 0;
        $donemBaslangicDate = new \DateTime($donem_baslangic);
        $donemBitisDate = new \DateTime($donem_bitis);

        foreach ($izinler as $izin) {
            $izinBaslangic = new \DateTime($izin->baslangic_tarihi);
            $izinBitis = new \DateTime($izin->bitis_tarihi);

            $kesisimBaslangic = max($izinBaslangic, $donemBaslangicDate);
            $kesisimBitis = min($izinBitis, $donemBitisDate);

            if ($kesisimBaslangic <= $kesisimBitis) {
                // diff()->days gives days difference. For 1 day (same start/end), it's 0. So we add 1.
                $interval = $kesisimBaslangic->diff($kesisimBitis);
                $toplamGun += $interval->days + 1;
            }
        }

        return $toplamGun;
    }

    /**
     * Kisa koda gore gun sayisini hesaplar (HT, X, GT, RP vb.)
     */
    public function getGunSayisiByKisaKod($personel_id, $donem_baslangic, $donem_bitis, $kisa_kod)
    {
        $sql = $this->db->prepare("
            SELECT pi.baslangic_tarihi, pi.bitis_tarihi
            FROM personel_izinleri pi
            JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.personel_id = ?
            AND pi.onay_durumu = 'Onaylandı'
            AND pi.silinme_tarihi IS NULL
            AND LOWER(t.kisa_kod) = LOWER(?)
            AND pi.baslangic_tarihi <= ?
            AND pi.bitis_tarihi >= ?
        ");
        $sql->execute([$personel_id, $kisa_kod, $donem_bitis, $donem_baslangic]);
        $izinler = $sql->fetchAll(PDO::FETCH_OBJ);

        $toplamGun = 0;
        $donemBaslangicDate = new \DateTime($donem_baslangic);
        $donemBitisDate = new \DateTime($donem_bitis);

        foreach ($izinler as $izin) {
            try {
                $izinBaslangic = new \DateTime($izin->baslangic_tarihi);
                $izinBitis = new \DateTime($izin->bitis_tarihi);

                $kesisimBaslangic = max($izinBaslangic, $donemBaslangicDate);
                $kesisimBitis = min($izinBitis, $donemBitisDate);

                if ($kesisimBaslangic <= $kesisimBitis) {
                    $interval = $kesisimBaslangic->diff($kesisimBitis);
                    $toplamGun += $interval->days + 1;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        return $toplamGun;
    }

    /**
     * Personelin ücretsiz izin günlerini dönem için doğrudan hesaplar
     */
    public function getUcretsizIzinGunuDirekt($personel_id, $donem_baslangic, $donem_bitis)
    {
        // Ücretsiz izin türlerini bul (tanimlamalar tablosundan ucretli_mi = 0 olanlar)
        $izinTurleriSql = $this->db->prepare("
            SELECT id FROM tanimlamalar 
            WHERE grup = 'izin_turu' 
            AND ucretli_mi = 0 
            AND kisa_kod NOT IN ('RP')
            AND silinme_tarihi IS NULL
        ");
        if ($this->ucretsizIzinTurIdsCache === null) {
            $izinTurleriSql->execute();
            $this->ucretsizIzinTurIdsCache = $izinTurleriSql->fetchAll(PDO::FETCH_COLUMN) ?: [];
        }
        $ucretsizIzinTurIds = $this->ucretsizIzinTurIdsCache;




        if (empty($ucretsizIzinTurIds)) {
            return 0;
        }

        $idPlaceholders = implode(',', array_fill(0, count($ucretsizIzinTurIds), '?'));
        $izinSql = $this->db->prepare("
            SELECT baslangic_tarihi, bitis_tarihi
            FROM personel_izinleri
            WHERE personel_id = ?
            AND onay_durumu = 'Onaylandı'
            AND izin_tipi_id IN ($idPlaceholders)
            AND baslangic_tarihi <= ?
            AND bitis_tarihi >= ?
            AND silinme_tarihi IS NULL
        ");

        $params = array_merge([$personel_id], $ucretsizIzinTurIds, [$donem_bitis, $donem_baslangic]);
        $izinSql->execute($params);
        $izinler = $izinSql->fetchAll(PDO::FETCH_OBJ);

        $toplamGun = 0;
        $donemBaslangicDate = new \DateTime($donem_baslangic);
        $donemBitisDate = new \DateTime($donem_bitis);

        foreach ($izinler as $izin) {
            $izinBaslangic = new \DateTime($izin->baslangic_tarihi);
            $izinBitis = new \DateTime($izin->bitis_tarihi);

            $kesisimBaslangic = max($izinBaslangic, $donemBaslangicDate);
            $kesisimBitis = min($izinBitis, $donemBitisDate);

            if ($kesisimBaslangic <= $kesisimBitis) {
                $toplamGun += $kesisimBaslangic->diff($kesisimBitis)->days + 1;
            }
        }

        return $toplamGun;
    }

    /**
     * Personelin BES kesintisini oluşturur
     */
    public function olusturBesKesintisi($personel_id, $donem_id, $sgkMatrahi, $donemTarihi)
    {
        // Parametreyi getir
        $besParam = $this->getParametreCached('bes_kesinti', $donemTarihi);

        $oran = 3; // Varsayılan %3
        if ($besParam && isset($besParam->oran) && $besParam->oran > 0) {
            $oran = floatval($besParam->oran);
        }

        // Kesinti tutarını hesapla
        $tutar = $sgkMatrahi * ($oran / 100);

        if ($tutar > 0) {
            $aciklama = "[BES] Bireysel Emeklilik Kesintisi (%$oran)";

            // Mevcut BES kesintisi var mı kontrol et
            // Soft-delete yapılan kayıtları dikkate alma (yeniden oluşturulacak)
            $mevcutKontrol = $this->db->prepare("
                SELECT id FROM personel_kesintileri
                WHERE personel_id = ? AND donem_id = ? AND aciklama LIKE '[BES]%'
                AND silinme_tarihi IS NULL
            ");
            $mevcutKontrol->execute([$personel_id, $donem_id]);
            $mevcut = $mevcutKontrol->fetch(PDO::FETCH_OBJ);

            if ($mevcut) {
                // Mevcut aktif kayıt var, sadece tutarı güncelle (durumu koru)
                $updateSql = $this->db->prepare("
                    UPDATE personel_kesintileri 
                    SET tutar = ?, aciklama = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateSql->execute([round($tutar, 2), $aciklama, $mevcut->id]);
            } else {
                // Mevcut kayıt yok, yeni oluştur
                $this->addKesinti($personel_id, $donem_id, $aciklama, round($tutar, 2), 'bes_kesinti', 'onaylandi');
            }
        }
    }

    /**
     * Personelin dönem içinde kaç gün çalıştığını hesaplar
     * Puantaj (yapilan_isler) sisteminden gerçek çalışma gününü alır
     * 
     * @param int $personel_id Personel ID
     * @param string $baslangic_tarihi Dönem başlangıç (Y-m-d)
     * @param string $bitis_tarihi Dönem bitiş (Y-m-d)
     * @return int Çalışma günü sayısı
     */
    public function getCalismaGunuSayisi($personel_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // Personelin ekip kodunu al
        if ($this->personelModelCache === null) {
            $this->personelModelCache = new \App\Model\PersonelModel();
        }
        $PersonelModel = $this->personelModelCache;

        $personel = $PersonelModel->find($personel_id);

        if (!$personel) {
            return 0;
        }

        $dates = [];

        // 1. Yapılan işlerden gelen tarihler
        if (!empty($personel->ekip_no)) {
            $sql = $this->db->prepare("
                SELECT DISTINCT DATE(tarih) as gun
                FROM yapilan_isler 
                WHERE ekip_kodu = ? 
                AND DATE(tarih) BETWEEN ? AND ?
                AND silinme_tarihi IS NULL
            ");
            $sql->execute([$personel->ekip_no, $baslangic_tarihi, $bitis_tarihi]);
            while ($row = $sql->fetch(PDO::FETCH_ASSOC)) {
                $dates[$row['gun']] = true;
            }
        }

        // 2. Puantaj (personel_izinleri) 'X' kodlu kayıtlardan gelen tarihler
        $sqlX = $this->db->prepare("
            SELECT pi.baslangic_tarihi, pi.bitis_tarihi
            FROM personel_izinleri pi
            JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.personel_id = ?
            AND pi.onay_durumu = 'Onaylandı'
            AND pi.silinme_tarihi IS NULL
            AND LOWER(t.kisa_kod) = 'x'
            AND pi.baslangic_tarihi <= ?
            AND pi.bitis_tarihi >= ?
        ");
        $sqlX->execute([$personel_id, $bitis_tarihi, $baslangic_tarihi]);
        $izinlerX = $sqlX->fetchAll(PDO::FETCH_OBJ);

        $donemBas = strtotime($baslangic_tarihi);
        $donemBit = strtotime($bitis_tarihi);

        foreach ($izinlerX as $izin) {
            $cur = strtotime($izin->baslangic_tarihi);
            $end = strtotime($izin->bitis_tarihi);
            while ($cur <= $end) {
                if ($cur >= $donemBas && $cur <= $donemBit) {
                    $dates[date('Y-m-d', $cur)] = true;
                }
                $cur = strtotime("+1 day", $cur);
            }
        }

        return count($dates);
    }

    /**
     * Tek bir personelin maaşını hesaplar ve günceller
     * Parametrelere dayalı gelişmiş hesaplama
     */
    /**
     * Toplu hesaplama öncesi seçilen personellerin otomatik verilerini tek seferde temizler.
     * Bu işlem N+1 delete sorununu çözer.
     */
    public function bulkDeleteAutoGeneratedRecords(array $bordroPersonelIds, int $donemId)
    {
        if (empty($bordroPersonelIds)) return;

        $placeholders = implode(',', array_fill(0, count($bordroPersonelIds), '?'));
        
        // Önce personel_id listesini alalım (bazı tablolar personel_id kullanıyor)
        $sqlP = $this->db->prepare("SELECT DISTINCT personel_id FROM bordro_personel WHERE id IN ($placeholders)");
        $sqlP->execute($bordroPersonelIds);
        $personelIds = $sqlP->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($personelIds)) return;
        $pPlaceholders = implode(',', array_fill(0, count($personelIds), '?'));

        // 1) Otomatik KESİNTİLERİ TEMİZLE
        $this->db->prepare("
            DELETE FROM personel_kesintileri 
            WHERE donem_id = ? 
            AND personel_id IN ($pPlaceholders)
            AND (
                ana_kesinti_id IS NOT NULL
                OR tur = 'icra'
                OR (tur = 'avans' AND (aciklama LIKE '[Avans]%' OR aciklama LIKE 'Avans - %'))
                OR aciklama LIKE '[BES]%'
            )
        ")->execute(array_merge([$donemId], $personelIds));

        // 2) Otomatik EK ÖDEMELERİ TEMİZLE
        $this->db->prepare("
            DELETE FROM personel_ek_odemeler 
            WHERE donem_id = ? 
            AND personel_id IN ($pPlaceholders)
            AND (
                ana_odeme_id IS NOT NULL
                OR aciklama LIKE '[Puantaj]%'
                OR aciklama LIKE '[Sayaç]%'
                OR aciklama LIKE '[Nöbet]%'
                OR aciklama LIKE '[Kaçak Kontrol]%'
                OR tur IN ('yemek_yardimi', 'es_yardimi', 'YY', 'EY')
                OR aciklama LIKE '[Yemek Yardımı]%'
                OR aciklama LIKE '[Eş Yardımı]%'
                OR tekrar_tipi = 'profil_bazli'
                OR (aciklama LIKE '(%' AND aciklama LIKE '%Fiili Gün x%')
                OR aciklama LIKE '%Maaşa Dahil Dengeleme%'
            )
        ")->execute(array_merge([$donemId], $personelIds));
    }

    /**
     * Verilen verileri toplu olarak veritabanına ekler.
     */
    public function bulkInsert(string $table, array $columns, array $values)
    {
        if (empty($values)) return;

        $colString = implode(',', $columns);
        $rowPlaceholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $allPlaceholders = implode(',', array_fill(0, count($values), $rowPlaceholders));

        $flatValues = [];
        foreach ($values as $row) {
            foreach ($columns as $col) {
                $flatValues[] = $row[$col] ?? null;
            }
        }

        $sql = "INSERT INTO $table ($colString) VALUES $allPlaceholders";
        $this->db->prepare($sql)->execute($flatValues);
    }

    public function hesaplaMaas($bordro_personel_id, $hesaplayan_id = null, $hesaplayan_ad_soyad = null)

    {
        // Enjektör: Eğer hesaplayan bilgisi gelmemişse oturumdan al
        if ($hesaplayan_id === null) {
            $hesaplayan_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
        }
        if ($hesaplayan_ad_soyad === null) {
            $hesaplayan_ad_soyad = $_SESSION['user_full_name'] ?? ($_SESSION['user']->adi_soyadi ?? 'Sistem');
        }

        // BordroParametreModel'i tekil örnek olarak kullan (aynı istek boyunca yeniden kullanılır)
        if ($this->cachedParametreModel === null) {
            $this->cachedParametreModel = new BordroParametreModel();
        }
        $parametreModel = $this->cachedParametreModel;

        // Bordro kaydını ve personel detaylarını çek
        $sql = $this->db->prepare("
            SELECT bp.*, p.maas_tutari, p.maas_durumu, p.bes_kesintisi_varmi, p.sodexo, p.sgk_yapilan_firma, p.ise_giris_tarihi, p.isten_cikis_tarihi, 
                   p.yemek_yardimi_dahil, p.yemek_yardimi_tutari, p.yemek_yardimi_parametre_id,
                   p.es_yardimi_dahil, p.es_yardimi_tutari, p.es_yardimi_parametre_id,
                   bd.baslangic_tarihi, bd.bitis_tarihi
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.id = ?
        ");
        $sql->execute([$bordro_personel_id]);
        $kayit = $sql->fetch(PDO::FETCH_OBJ);

        if (!$kayit)
            return false;

        // Dönem tarihi - parametreleri bu tarihe göre çek
        $donemTarihi = $kayit->baslangic_tarihi ?? date('Y-m-d');
        $donemBitis = $kayit->bitis_tarihi ?? date('Y-m-t');

        // ========== ÜCRETSİZ İZİN VE RApOR HAKEDİŞLERİNİ BAŞTAN HESAPLA ==========
        // SGK uyumlu gün hesabı (30/31 gün kuralları) için bu veriler scaling öncesi gerekli.
        $ucretsizIzinGunu = $this->getUcretsizIzinGunuDirekt($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);
        $raporGunu = $this->getGunSayisiByKisaKod($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi, 'RP');
        $ucretliIzinGunu = $this->getUcretliIzinGunu($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);
        $genelTatilGunu = $this->getGunSayisiByKisaKod($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi, 'GT');

        // Genel ayarlar ve bordro parametrelerini tek sorguda yükle (ilk personelde), sonrakiler cache'i kullanır
        if ($this->genelAyarlarCache === null) {
            $this->genelAyarlarCache = $parametreModel->getAllGenelAyarlarMap($donemTarihi);
            $this->parametrelerCache = $parametreModel->getAllParametrelerMap($donemTarihi);
        }
        $genelAyarlarMap = $this->genelAyarlarCache;
        $parametrelerMap = $this->parametrelerCache;

        // ---------------- GEÇİŞ SÜRECİ STRATEJİSİ VE PARÇALI MAAŞ HESAPLAMASI ----------------
        // Yeni görev (maaş) geçmişi tablosundan DÖNEM İÇİNDE GEÇERLİ OLAN TÜM KAYITLARI alıyoruz
        $sqlGecmis = $this->db->prepare("
            SELECT maas_durumu, maas_tutari, baslangic_tarihi, bitis_tarihi
            FROM personel_gorev_gecmisi 
            WHERE personel_id = ? 
            AND baslangic_tarihi <= ? 
            AND (bitis_tarihi IS NULL OR bitis_tarihi >= ?)
            ORDER BY baslangic_tarihi ASC
        ");
        $sqlGecmis->execute([$kayit->personel_id, $donemBitis, $donemTarihi]);
        $gecmisKayitlar = $sqlGecmis->fetchAll(\PDO::FETCH_OBJ);

        $agirlikliHedefNet = 0;
        $agirlikliAsgariNet = 0;
        $agirlikliBrutMaas = 0;
        $nominalBrutMaas = floatval($kayit->maas_tutari ?? 0);
        $toplamGecerliGun = 0;
        $isNetMaas = false;
        $isPrimUsulu = false;

        // Maaş durumu değişkenlerini baştan tanımlayalım (Hata almamak için)
        $maasDurumuRaw = $kayit->maas_durumu ?? 'brüt';
        $maasDurumu = mb_strtolower(trim($maasDurumuRaw), 'UTF-8');

        if (count($gecmisKayitlar) > 0) {
            // Dönem içinde birden fazla geçmiş kaydı varsa ağırlıklı hesaplama yap
            $donemBaslangicTs = strtotime($donemTarihi);
            $donemBitisTs = strtotime($donemBitis);

            foreach ($gecmisKayitlar as $idx => $g) {
                $gBaslangic = strtotime($g->baslangic_tarihi);
                $gBitis = empty($g->bitis_tarihi) ? $donemBitisTs : strtotime($g->bitis_tarihi);

                // Dönemle kesişen tarih aralığını bul
                $hesapBaslangic = max($donemBaslangicTs, $gBaslangic);
                $hesapBitis = min($donemBitisTs, $gBitis);

                // Kesişen gün sayısını hesapla (+1 dahil etmek için)
                if ($hesapBitis >= $hesapBaslangic) {
                    $gecerliGun = round(($hesapBitis - $hesapBaslangic) / (60 * 60 * 24)) + 1;
                    $toplamGecerliGun += $gecerliGun;

                    // MAAŞA DAHİL MANTIĞI: Baz Maaş Asgari Ücret, Hedef Maaş Sözleşmedir
                    if ($this->hasMaasaDahilSosyalYardim($kayit)) {
                        $asgariNetVal = $this->genelAyarlarCache['asgari_ucret_net'] ?? 28075.50;
                        $gunlukAsgari = $asgariNetVal / 30;
                        $gunlukSozlesme = floatval($g->maas_tutari) / 30;
                        
                        $agirlikliAsgariNet += ($gunlukAsgari * $gecerliGun);
                        $agirlikliHedefNet += ($gunlukSozlesme * $gecerliGun);
                        
                        // Sistem hakedişi asgari ücret üzerinden yürütsün
                        $agirlikliBrutMaas += ($gunlukAsgari * $gecerliGun);
                    } else {
                        $gunlukTutar = floatval($g->maas_tutari) / 30;
                        $agirlikliBrutMaas += ($gunlukTutar * $gecerliGun);
                    }

                    // Bu kaydın durumunu kaydet (Son döngüdeki geçerli olacak)
                    $maasDurumuRaw = $g->maas_durumu;
                    $maasDurumu = mb_strtolower(trim($maasDurumuRaw), 'UTF-8');

                    $isNetMaas = (stripos($maasDurumuRaw, 'net') !== false);
                    $isPrimUsulu = (stripos($maasDurumuRaw, 'Prim') !== false || stripos($maasDurumu, 'prim') !== false);
                }
            }

            if ($this->hasMaasaDahilSosyalYardim($kayit)) {
                // Tavan ve Baz tutarları kesinleştir
                $kayit->hedef_net_maas_tutari = ($toplamGecerliGun > 0) ? ($agirlikliHedefNet / $toplamGecerliGun * 30) : 0;
                $kayit->hesaplama_baz_maas = ($toplamGecerliGun > 0) ? ($agirlikliAsgariNet / $toplamGecerliGun * 30) : 0;
                
                $isNetMaas = true; 
                $maasDurumu = 'net'; 
                $kayit->maas_durumu = 'net';
            } else {
                $nominalBrutMaas = ($toplamGecerliGun > 0) ? ($agirlikliBrutMaas / $toplamGecerliGun * 30) : $agirlikliBrutMaas;
                $kayit->maas_tutari = round($nominalBrutMaas, 2);
                $kayit->hedef_net_maas_tutari = 0;
            }
        } else {
            $nominalBrutMaas = floatval($kayit->maas_tutari ?? 0);
            
            if ($this->hasMaasaDahilSosyalYardim($kayit)) {
                $kayit->hedef_net_maas_tutari = $nominalBrutMaas;
                $kayit->hesaplama_baz_maas = $genelAyarlarMap['asgari_ucret_net'] ?? 28075.50;
                $isNetMaas = true;
                $maasDurumu = 'net';
                $kayit->maas_durumu = 'net';
            } else {
                $kayit->hedef_net_maas_tutari = 0;
            }
        }
        // ------------------------------------------------------------------------------

        // Prim Usülü için esnek karşılaştırma (Türkçe karakter encoding sorunları için son kontrol)
        $isPrimUsulu = (stripos($maasDurumuRaw, 'Prim') !== false || stripos($maasDurumu, 'prim') !== false);

        // Dönem tarihi - parametreleri bu tarihe göre çek
        $donemTarihi = $kayit->baslangic_tarihi ?? date('Y-m-d');
        $donemAy = date('n', strtotime($donemTarihi));
        $donemYil = date('Y', strtotime($donemTarihi));
        $donem = date('Y-m', strtotime($donemTarihi));

        // Brüt maaş (sürekli kayıtların oran hesabı için önce al)
        $brutMaas = floatval($kayit->maas_tutari ?? 0);

        // Net maaş veya Prim Usülü ise 0 olabilir, asgari ücrete çevirme
        // Sadece brüt maaş ve 0 ise asgari ücret kullan
        if ($brutMaas <= 0 && !$isNetMaas && !$isPrimUsulu) {
            $brutMaas = $genelAyarlarMap['asgari_ucret_brut'] ?? 33030.00;
        }


        // Net maaş tahmini (sürekli kayıtların oran hesabı için - brütün %70'i)
        $netMaasTahmini = $brutMaas * 0.70;

        // ========== OTOMATİK KESİNTİ/EK ÖDEMELERİ TEMİZLE VE YENİDEN OLUŞTUR ==========
        // Önce tüm otomatik oluşturulan kayıtları soft-delete yap
        // Sonra fonksiyonlar güncel verilere göre yeniden oluşturacak
        // Manuel eklenen kayıtlar (kullanıcının elle eklediği) korunur

        // 1) Otomatik oluşturulan KESİNTİLERİ TEMİZLE
        $this->db->prepare("
            DELETE FROM personel_kesintileri 
            WHERE personel_id = ? AND donem_id = ? 
            AND (
                ana_kesinti_id IS NOT NULL
                OR tur = 'icra'
                OR (tur = 'avans' AND (aciklama LIKE '[Avans]%' OR aciklama LIKE 'Avans - %'))
                OR aciklama LIKE '[BES]%'
            )
        ")->execute([$kayit->personel_id, $kayit->donem_id]);

        // 2) Otomatik oluşturulan EK ÖDEMELERİ TEMİZLE (HARD DELETE)
        // Soft-delete (UPDATE) yerine DELETE kullanarak mükerrerleşmeyi ve ID kalabalığını önlüyoruz.
        $this->db->prepare("
            DELETE FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? 
            AND (
                ana_odeme_id IS NOT NULL
                OR aciklama LIKE '[Puantaj]%'
                OR aciklama LIKE '[Sayaç]%'
                OR aciklama LIKE '[Nöbet]%'
                OR aciklama LIKE '[Kaçak Kontrol]%'
                OR tur LIKE '%yemek%'
                OR tur LIKE '%YY%'
                OR tur = 'yemek_yardimi_tum'
                OR aciklama LIKE '[Yemek Yardımı]%'
                OR aciklama LIKE '[Yemek Yard%m%]%'
                OR aciklama LIKE '[Eş Yardımı]%'
                OR aciklama LIKE '[E% Yard%m%]%'
                OR tekrar_tipi = 'profil_bazli'
                OR parametre_id IN (35)
                OR (aciklama LIKE '(%' AND aciklama LIKE '%Fiili Gün x%')
            )
        ")->execute([$kayit->personel_id, $kayit->donem_id]);

        // ========== SÜREKLİ KESİNTİ VE EK ÖDEMELERİ DÖNEME AKTAR ==========
        // Bu işlem, aktif sürekli kayıtları bordro dönemine tek seferlik olarak ekler
        $this->olusturSurekliKesintiler($kayit->personel_id, $kayit->donem_id, $donem, $brutMaas, $netMaasTahmini);
        
        $this->olusturSurekliEkOdemeler($kayit->personel_id, $kayit->donem_id, $donem, $brutMaas, $netMaasTahmini);
        $this->olusturProfilBazliOdemeler($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi);

        // Puantaj (Yapılan İşler) Hesaplaması
        $this->olusturPuantajOdemeleri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // Sayaç Değişim Hesaplaması
        $this->olusturSayacDegisimOdemeleri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // ========== NÖBET ÖDEMELERİ ==========
        // Dönem içindeki onaylanmış nöbetleri bulup ek ödeme olarak ekle
        $this->olusturNobetOdemeleri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // ========== KAÇAK KONTROL PRİMLERİ ==========
        // Personelin dönem içinde 260'ı aşan kaçak kontrol işlemleri için prim hesapla
        $this->olusturKacakKontrolPrimleri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // ========== AVANS KESİNTİLERİ ==========
        // Dönem içindeki onaylanmış avansları bulup kesinti olarak ekle
        $this->olusturAvansKesintileri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // ========== İCRA KESİNTİLERİ ==========
        // Aktif icra dosyalarını bulup kesinti olarak ekle
        $this->olusturIcraKesintileri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // (Leave/Report/Holiday calculations are now at the top of the function for scaling logic)

        // ========== ÇALIŞMA GÜNÜ HESAPLAMASI ==========
        // Mantık:
        // 1) Önce personelin dönem içindeki aktif takvim gününü bul
        // 2) Eksik gün yoksa ve dönemin tamamını kapsıyorsa 30 gün kabul et
        // 3) Eksik gün varsa aktif takvim gününden düş
        $donemBasTs = strtotime($kayit->baslangic_tarihi);
        $donemBitTs = strtotime($kayit->bitis_tarihi);
        $aydakiGunSayisi = (int) round(($donemBitTs - $donemBasTs) / 86400) + 1;
        $aktifTakvimGun = $this->getAktifTakvimGunSayisi(
            $kayit->baslangic_tarihi,
            $kayit->bitis_tarihi,
            $kayit->ise_giris_tarihi ?? null,
            $kayit->isten_cikis_tarihi ?? null
        );

        // USER REQ: Maaş hesaplaması görev geçmişi kapsamına göre olmalı (Örn: Geçmiş 1 günlük ise 1 gün ödenmeli)
        if (count($gecmisKayitlar) > 0) {
            $workingHistoryCoverage = $toplamGecerliGun;
            $aktifTakvimGun = min($aktifTakvimGun, $workingHistoryCoverage);
        }

        $gunlukBase = $aktifTakvimGun;

        // Puantajdan (yapılan işler + X kodları) fiili çalışma gününü al
        $puantajGunSayisiRaw = $this->getCalismaGunuSayisi($kayit->personel_id, $donemTarihi, $donemBitis);
        $normGun = $puantajGunSayisiRaw;

        // USER REQ: Hak edilen hafta tatili (6 güne 1 gün) eklenmelidir. 
        // Ama kullanıcı "HT olanları say" diyor. Önce puantajdaki gerçek kayıtları sayalım.
        $haftaTatiliGunu = $this->getGunSayisiByKisaKod($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi, 'HT');

        $manualHT = false;
        if ($haftaTatiliGunu > 0) {
            $manualHT = true;
            $puantajGunSayisi = $normGun + $haftaTatiliGunu;
        } else {
            // Hiç HT girilmemişse otomatik hesapla (Takvim üzerindeki aktif pazar günleri kadar)
            // Bu sayede puantajı kaydedilmemiş sabit maaşlı personellerin de listesinde HT'leri doğru görünür.
            $aktifStart = max(strtotime($kayit->baslangic_tarihi), strtotime($kayit->ise_giris_tarihi ?? $kayit->baslangic_tarihi));
            if (!empty($kayit->isten_cikis_tarihi) && $kayit->isten_cikis_tarihi != '0000-00-00') {
                $aktifEnd = min(strtotime($kayit->bitis_tarihi), strtotime($kayit->isten_cikis_tarihi));
            } else {
                $aktifEnd = strtotime($kayit->bitis_tarihi);
            }
            
            $haftaTatiliGunu = 0;
            if ($aktifStart <= $aktifEnd) {
                $cur = $aktifStart;
                while ($cur <= $aktifEnd) {
                    if (date('w', $cur) == 0) { // 0 = Pazar
                        $haftaTatiliGunu++;
                    }
                    $cur = strtotime("+1 day", $cur);
                }
            }
            $puantajGunSayisi = $normGun + $haftaTatiliGunu;
        }

        // Genel Tatil ve Ücretli İzinleri de ekleyelim
        $puantajGunSayisi += $genelTatilGunu + $ucretliIzinGunu;

        // Not: Eski doğrusal oranlama mantığı kaldırıldı (30/31 vb.). 
        // Bunun yerine getMaasHesapGunu içindeki SGK uyumlu (31-1=30) mantık ve 
        // aşağıdaki normalization bloğu kullanılıyor.

        // Maaş günü: ücretsiz izin ve rapor günleri düşülür.
        // Eksik gün yoksa tam dönem için 30, eksik varsa aktif takvim gününden düş.
        $maasEksikGunToplami = $ucretsizIzinGunu + $raporGunu;
        $maasHesapGunu = $this->getMaasHesapGunu($aktifTakvimGun, $aydakiGunSayisi, $maasEksikGunToplami);

        // PDF ve Gösterim için gün dağılımını optimize edelim (Toplam = maasHesapGunu olmalı)
        // Eğer puantaj verisi toplamı ssk gününden (maasHesapGunu) azsa, aradaki farkı normal gün sayalım (Sabit maaşlılar için)
        $mevcutToplam = $normGun + $haftaTatiliGunu + $genelTatilGunu + $ucretliIzinGunu;
        if ($mevcutToplam < $maasHesapGunu) {
            $normGun += ($maasHesapGunu - $mevcutToplam);
        } elseif ($mevcutToplam > $maasHesapGunu) {
            // Eğer fazlaysa (manuel giriş hatası vb) normal günden düşelim
            $normGun = max(0, $normGun - ($mevcutToplam - $maasHesapGunu));
        }

        // Ücretsiz izin günü varsa brüt maaşı düşür (Günlük ücret × izin günü kadar)
        $bazAlinacakTutar = floatval($nominalBrutMaas);
        
        // Maaşa Dahil kontrolü (Hakedişi asgari ücrete sabitleyip üzerini yemekle tamamlayacağız)
        if ($this->hasMaasaDahilSosyalYardim($kayit)) {
             $kayit->hedef_net_maas_tutari = $nominalBrutMaas; 
             $bazAlinacakTutar = $genelAyarlarMap['asgari_ucret_net'] ?? 28075.50;
        } elseif (isset($kayit->hesaplama_baz_maas) && $kayit->hesaplama_baz_maas > 0) {
             $bazAlinacakTutar = floatval($kayit->hesaplama_baz_maas);
        }

        if ($isNetMaas || $maasDurumu === 'brüt') {
            // Net veya Brüt maaş tipi: toplam alacağı = (maaş / 30) * gün
            $fiiliCalismaGunuTemp = $maasHesapGunu;

            if ($fiiliCalismaGunuTemp < 0)
                $fiiliCalismaGunuTemp = 0;
            $brutMaas = round(($bazAlinacakTutar / 30) * $fiiliCalismaGunuTemp, 2);
            $ucretsizIzinDusumu = $bazAlinacakTutar - $brutMaas; // Sadece bilgi amaçlı
            if ($ucretsizIzinDusumu < 0)
                $ucretsizIzinDusumu = 0;
        } else {
            $fiiliCalismaGunuTemp = $maasHesapGunu;

            if ($fiiliCalismaGunuTemp < 0)
                $fiiliCalismaGunuTemp = 0;

            if ($fiiliCalismaGunuTemp < 30 && $bazAlinacakTutar > 0) {
                $calismadigiGunler = 30 - $fiiliCalismaGunuTemp;
                $gunlukUcretHesap = $bazAlinacakTutar / 30;
                $ucretsizIzinDusumu = round($gunlukUcretHesap * $calismadigiGunler, 2);
                $brutMaas = max(0, $bazAlinacakTutar - $ucretsizIzinDusumu);
            } else {
                $ucretsizIzinDusumu = 0;
                $brutMaas = $bazAlinacakTutar;
            }
        }

        // Çalışma günü sayısı (aylık varsayılan 26 gün) - BES hesabı için gerekli
        $calismaGunuSayisi = $genelAyarlarMap['calisma_gunu_sayisi'] ?? 26;

        // ========== BES KESİNTİSİ ==========
        if (!$isNetMaas && isset($kayit->bes_kesintisi_varmi) && $kayit->bes_kesintisi_varmi === 'Evet') {
            // SGK Matrahını tahmin et (Ek ödemelerden gelen SGK matrahı ile)
            $tempEkOdemeler = $this->getDonemEkOdemeleriListe($kayit->personel_id, $kayit->donem_id);
            $tempSgkMatrahEkleri = 0;

            foreach ($tempEkOdemeler as $odeme) {
                $param = $parametrelerMap[$odeme->tur] ?? null;
                if ($param && $param->sgk_matrahi_dahil) {
                    $tutar = floatval($odeme->tutar);
                    // Muafiyet hesabı
                    $muafLimit = 0;
                    if ($param->hesaplama_tipi === 'kismi_muaf') {
                        if ($param->muaf_limit_tipi === 'gunluk') {
                            $muafLimit = floatval($param->gunluk_muaf_limit) * $calismaGunuSayisi;
                        } elseif ($param->muaf_limit_tipi === 'aylik') {
                            $muafLimit = floatval($param->aylik_muaf_limit);
                        }
                        $vergiliKisim = max(0, $tutar - $muafLimit);
                        $tempSgkMatrahEkleri += $vergiliKisim;
                    } elseif ($param->hesaplama_tipi === 'brut') {
                        $tempSgkMatrahEkleri += $tutar;
                    }
                }
            }

            $tempCalisanBrut = max(0, $brutMaas); // $brutMaas zaten ücretsiz izin düşülmüş halde
            $tempSgkMatrahi = $tempCalisanBrut + $tempSgkMatrahEkleri;

            $this->olusturBesKesintisi($kayit->personel_id, $kayit->donem_id, $tempSgkMatrahi, $donemTarihi);
        }



        // Genel ayarları çek

        if ($isNetMaas || $isPrimUsulu) {
            // Net ve Prim Usülü için vergi/SGK yok
            $sgkIsciOrani = 0;
            $issizlikIsciOrani = 0;
            $sgkIsverenOrani = 0;
            $issizlikIsverenOrani = 0;
            $damgaVergisiOrani = 0;
        } else {
            $sgkIsciOrani = ($genelAyarlarMap['sgk_isci_orani'] ?? 14) / 100;
            $issizlikIsciOrani = ($genelAyarlarMap['issizlik_isci_orani'] ?? 1) / 100;
            $sgkIsverenOrani = ($genelAyarlarMap['sgk_isveren_orani'] ?? 20.5) / 100;
            $issizlikIsverenOrani = ($genelAyarlarMap['issizlik_isveren_orani'] ?? 2) / 100;
            $damgaVergisiOrani = ($genelAyarlarMap['damga_vergisi_orani'] ?? 0.759) / 100;
        }

        // Ek Ödemeler ve Kesintileri detaylı çek (sürekli kayıtlar da artık dahil)
        $ekOdemeler = $this->getDonemEkOdemeleriListe($kayit->personel_id, $kayit->donem_id);
        $kesintiler = $this->getDonemKesintileriListe($kayit->personel_id, $kayit->donem_id);

        // Hesaplama için değişkenler
        $brutEkOdemeler = 0;       // Brüt maaşa eklenecek (SGK + Vergi hesaplanacak)
        $netEkOdemeler = 0;        // Direct net'e eklenecek
        $vergiliMatrahEkleri = 0;  // Sadece gelir vergisi matrahına eklenecek
        $sgkMatrahEkleri = 0;      // SGK matrahına eklenecek
        $toplamMesaiTutar = 0;     // Özel olarak mesai tutarını ayır
        $toplamKesinti = 0;        // Net'ten düşülecek kesintiler

        // JSON detay için diziler
        $ekOdemeDetaylari = [];
        $yontemliOdemeler = [
            'banka' => 0,
            'elden' => 0,
            'sodexo' => 0,
            'diger' => 0
        ];
        $kesintiDetaylari = [];
        $mealAllowanceDeduction = 0; // USER REQ: Maaşa dahil yemek yardımını ana hakedişten düşmek için

        // Her ek ödemeyi parametresine göre işle
        foreach ($ekOdemeler as $odeme) {
            $tutar = floatval($odeme->tutar);
            $parametre = $parametrelerMap[$odeme->tur] ?? null;

            if ($this->hasMaasaDahilSosyalYardim($kayit)) {
                $odemeTurLower = mb_strtolower((string) ($odeme->tur ?? ''), 'UTF-8');
                $isDahilYemek = intval($kayit->yemek_yardimi_dahil ?? 0) === 1
                    && ($odemeTurLower === 'yemek_yardimi_tum' || $odemeTurLower === 'yemek' || strpos($odemeTurLower, 'yemek') !== false);
                $isDahilEs = intval($kayit->es_yardimi_dahil ?? 0) === 1
                    && ($odemeTurLower === 'es_yardimi' || strpos($odemeTurLower, 'es_yardimi') !== false || strpos($odemeTurLower, 'aile') !== false);

                if ($isDahilYemek || $isDahilEs) {
                    continue;
                }
            }

            // MÜKERRER HESAPLAMA KONTROLÜ: Eğer açıklama içinde zaten hesaplanmış bir tutar deseni varsa (örn: "30 Gün x 700 TL"),
            // bir sonraki hesaplamada compounding (katlanma) olmaması için baz ücreti açıklamadan geri kazanıyoruz.
            if (!empty($odeme->aciklama) && preg_match('/\((?:[\d.,]+) (?:Fiili )?Gün x ([\d.,]+)/u', $odeme->aciklama, $matches)) {
                $baseFromLabel = \App\Helper\Helper::formattedMoneyToNumber($matches[1]);
                if ($baseFromLabel > 0) {
                    $tutar = floatval($baseFromLabel);
                    // use log if needed
                }
            }

            // Detay kaydı
            $detay = [
                'id' => $odeme->id ?? 0,
                'kod' => $odeme->tur,
                'tutar' => $tutar,
                'aciklama' => $odeme->aciklama ?? null
            ];

            if ($odeme->tur === 'mesai') {
                $toplamMesaiTutar += $tutar;
            }

            // USER REQ: Yemek Yardımı Maaşa Dahil ise, bu tutarı ana hakedişten düşmek üzere biriktir
            if (!empty($kayit->yemek_yardimi_dahil) && $kayit->yemek_yardimi_dahil == 1) {
                $isYemek = (
                    $odeme->tur === 'yemek_yardimi_tum' || 
                    $odeme->tur === 'yemek' || 
                    strpos(mb_strtolower($odeme->tur ?? '', 'UTF-8'), 'yemek') !== false
                );
                if ($isYemek) {
                    $mealAllowanceDeduction += $tutar;
                }
            }

            if (!$parametre) {
                // Parametre bulunamadıysa varsayılan olarak net ekle
                $netEkOdemeler += $tutar;
                $detay['etiket'] = $odeme->tur;
                $detay['hesaplama_tipi'] = 'net';
                $detay['net_etki'] = $tutar;
                $ekOdemeDetaylari[] = $detay;
                continue;
            }

            $detay['etiket'] = $parametre->etiket;
            $detay['hesaplama_tipi'] = $parametre->hesaplama_tipi;
            $detay['sgk_dahil'] = (bool) $parametre->sgk_matrahi_dahil;
            $detay['gv_dahil'] = (bool) $parametre->gelir_vergisi_dahil;

            switch ($parametre->hesaplama_tipi) {
                case 'brut':
                    // Brüt: Tüm vergi/SGK hesaplamalarına dahil
                    $brutEkOdemeler += $tutar;
                    if ($parametre->sgk_matrahi_dahil) {
                        $sgkMatrahEkleri += $tutar;
                    }
                    if ($parametre->gelir_vergisi_dahil) {
                        $vergiliMatrahEkleri += $tutar;
                    }
                    $detay['net_etki'] = $tutar; // Brütten kesinti yapılacak
                    break;

                case 'gunluk_brut':
                case 'gunluk_net':
                case 'gunluk_kismi_muaf':
                case 'aylik_gun_brut':
                case 'aylik_gun_net':
                    // Gün sayısını hesapla
                    $gunSayisi = 0;
                    if ($parametre->gun_sayisi_otomatik) {
                        // Puantajdan otomatik hesapla
                        $gunSayisi = $this->getCalismaGunuSayisi(
                            $kayit->personel_id,
                            $kayit->baslangic_tarihi,
                            $kayit->bitis_tarihi
                        );
                        
                        // FALLBACK: Puantaj verisi yoksa (ekip_no boş veya veri girilmemiş)
                        // personelin maaş hesap gününü kullan (dönem için hesaplanan gerçek çalışma günü)
                        if ($gunSayisi <= 0) {
                            $gunSayisi = $maasHesapGunu;
                            $detay['gun_kaynak'] = 'maas_hesap_gunu (puantaj verisi yok)';
                        } else {
                            $detay['gun_kaynak'] = 'puantaj';
                        }
                    } else {
                        // Manuel/Sabit gün sayısı - ama izinleri düş
                        $varsayilanGun = intval($parametre->varsayilan_gun_sayisi ?? 30);
                        $loopUcretliIzin = $this->getUcretliIzinGunu(
                            $kayit->personel_id,
                            $kayit->baslangic_tarihi,
                            $kayit->bitis_tarihi
                        );
                        // Ücretsiz izin gün sayısını da al
                        $loopUcretsizIzin = $this->getUcretsizIzinGunuDirekt(
                            $kayit->personel_id,
                            $kayit->baslangic_tarihi,
                            $kayit->bitis_tarihi
                        );
                        $gunSayisi = max(0, $varsayilanGun - $loopUcretliIzin - $loopUcretsizIzin);
                        $detay['gun_kaynak'] = 'manuel';
                    }

                    // Toplam tutarı hesapla
                    $toplamTutar = 0;
                    if (strpos($parametre->hesaplama_tipi, 'gunluk_') === 0) {
                        // Günlük bazlı: Tutar = Günlük Tutar × Gün Sayısı
                        $gunlukTutar = floatval($parametre->gunluk_tutar);
                        $toplamTutar = $gunlukTutar * $gunSayisi;
                        $detay['gunluk_tutar'] = $gunlukTutar;
                    } else {
                        // Aylık (Çalışılan Gün) bazlı: Tutar = Günlük Tutar * Gün Sayısı
                        // Burada $tutar, personelin ek ödemesinde tanımlı olan günlük tutardır (UI açıklamasına uyum)
                        $toplamTutar = $tutar * $gunSayisi;
                        $detay['aylik_tutar'] = $tutar;
                    }

                    $detay['gun_sayisi'] = $gunSayisi;
                    $detay['hesaplanan_tutar'] = round($toplamTutar, 2);
                    
                    // Hesaplanan toplam tutarı personel_ek_odemeler tablosuna geri yaz
                    // (UI'da doğru görünmesi için)
                    if (isset($odeme->id) && $odeme->id > 0) {
                        $yeniAciklama = '(' . $gunSayisi . ' Gün x ' . number_format($tutar, 2, ',', '.') . ' ₺)';
                        $this->db->prepare(
                            "UPDATE personel_ek_odemeler SET tutar = ?, aciklama = ? WHERE id = ?"
                        )->execute([
                            round($toplamTutar, 2),
                            $yeniAciklama,
                            $odeme->id
                        ]);
                    }

                    // Hesaplama tipine göre işle (prefix'leri kaldırarak)
                    $temelTip = str_replace(['gunluk_', 'aylik_gun_'], '', $parametre->hesaplama_tipi);

                    if ($temelTip === 'brut') {
                        $brutEkOdemeler += $toplamTutar;
                        if ($parametre->sgk_matrahi_dahil) {
                            $sgkMatrahEkleri += $toplamTutar;
                        }
                        if ($parametre->gelir_vergisi_dahil) {
                            $vergiliMatrahEkleri += $toplamTutar;
                        }
                        $detay['net_etki'] = $toplamTutar;
                    } elseif ($temelTip === 'net') {
                        $netEkOdemeler += $toplamTutar;
                        $detay['net_etki'] = $toplamTutar;
                    } elseif ($temelTip === 'kismi_muaf') {
                        // Kısmi muaf mantığı
                        $muafLimit = 0;
                        if ($parametre->muaf_limit_tipi === 'gunluk') {
                            $muafLimit = floatval($parametre->gunluk_muaf_limit) * $gunSayisi;
                        } elseif ($parametre->muaf_limit_tipi === 'aylik') {
                            $muafLimit = floatval($parametre->aylik_muaf_limit);
                        }

                        $muafKisim = min($toplamTutar, $muafLimit);
                        $vergiliKisim = max(0, $toplamTutar - $muafLimit);

                        $netEkOdemeler += $muafKisim;
                        $brutEkOdemeler += $vergiliKisim; // Vergili kısım brüt olarak eklenmelidir

                        if ($vergiliKisim > 0) {
                            if ($parametre->sgk_matrahi_dahil) {
                                $sgkMatrahEkleri += $vergiliKisim;
                            }
                            if ($parametre->gelir_vergisi_dahil) {
                                $vergiliMatrahEkleri += $vergiliKisim;
                            }
                        }

                        $detay['muaf_kisim'] = round($muafKisim, 2);
                        $detay['vergili_kisim'] = round($vergiliKisim, 2);
                        $detay['net_etki'] = round($muafKisim + $vergiliKisim, 2); // Net etki toplam tutardır
                    }
                    break;

                case 'aylik_fiili_gun_net':
                    // Fiili Çalışılan Gün bazlı Net hesaplama (Bireysel Puantajdan)
                    $gunSayisi = $this->getFiiliCalismaGunuSayisi(
                        $kayit->personel_id,
                        $kayit->baslangic_tarihi,
                        $kayit->bitis_tarihi
                    );
                    
                    // FALLBACK: Eğer puantajdan fiili gün sıfır geliyorsa SSK (Normal) çalışma gününü baz al.
                    if ($gunSayisi <= 0) {
                        $gunSayisi = $normGun;
                        $detay['gun_kaynak'] = 'norm_gun (fallback)';
                    } else {
                        $detay['gun_kaynak'] = 'puantaj_bireysel';
                    }
                    
                    $detay['gun_sayisi'] = $gunSayisi;
                    
                    $toplamTutar = $tutar * $gunSayisi;
                    $detay['aylik_tutar'] = $tutar;
                    $detay['hesaplanan_tutar'] = round($toplamTutar, 2);

                    // Personel ek ödemeler tablosunu güncelle (UI açıklaması için)
                    if (isset($odeme->id) && $odeme->id > 0) {
                        $label = strpos($odeme->aciklama, ']') !== false ? explode(']', $odeme->aciklama)[0] . '] ' : '';
                        $yeniAciklama = $label . '(' . $gunSayisi . ' Fiili Gün x ' . number_format($tutar, 2, ',', '.') . ' ₺)';
                        $this->db->prepare(
                            "UPDATE personel_ek_odemeler SET tutar = ?, aciklama = ? WHERE id = ?"
                        )->execute([
                            round($toplamTutar, 2),
                            $yeniAciklama,
                            $odeme->id
                        ]);
                    }

                    $netEkOdemeler += $toplamTutar;
                    $detay['net_etki'] = $toplamTutar;
                    break;

                case 'kismi_muaf':
                    // Kısmi Muaf: Belirli limite kadar vergisiz
                    $muafLimit = 0;
                    if ($parametre->muaf_limit_tipi === 'gunluk') {
                        $muafLimit = floatval($parametre->gunluk_muaf_limit) * $calismaGunuSayisi;
                    } elseif ($parametre->muaf_limit_tipi === 'aylik') {
                        $muafLimit = floatval($parametre->aylik_muaf_limit);
                    }

                    $muafKisim = min($tutar, $muafLimit);
                    $vergiliKisim = max(0, $tutar - $muafLimit);

                    // Muaf kısım net'e direkt eklenir
                    $netEkOdemeler += $muafKisim;
                    // Vergili kısım brüt olarak eklenmelidir
                    $brutEkOdemeler += $vergiliKisim;

                    // Vergili kısım hesaplamalara dahil
                    if ($vergiliKisim > 0) {
                        if ($parametre->sgk_matrahi_dahil) {
                            $sgkMatrahEkleri += $vergiliKisim;
                        }
                        if ($parametre->gelir_vergisi_dahil) {
                            $vergiliMatrahEkleri += $vergiliKisim;
                        }
                    }

                    $detay['muaf_limit_tipi'] = $parametre->muaf_limit_tipi;
                    $detay['gunluk_limit'] = $parametre->gunluk_muaf_limit;
                    $detay['aylik_limit'] = $muafLimit;
                    $detay['muaf_kisim'] = round($muafKisim, 2);
                    $detay['vergili_kisim'] = round($vergiliKisim, 2);
                    $detay['net_etki'] = round($muafKisim + $vergiliKisim, 2);
                    break;
                case 'net':
                default:
                    // Net: Direkt net maaşa eklenir
                    $netEkOdemeler += $tutar;
                    $detay['net_etki'] = $tutar;

                    // Eğer önceden fiili gün açıklaması kalmışsa temizle
                    if (isset($odeme->id) && $odeme->id > 0 && strpos($odeme->aciklama, 'Fiili Gün') !== false) {
                        $yeniAciklama = $parametre->etiket . ' (Sabit)';
                        $this->db->prepare("UPDATE personel_ek_odemeler SET aciklama = ? WHERE id = ?")
                            ->execute([$yeniAciklama, $odeme->id]);
                    }
                    break;
            }

            // Ödeme yöntemine göre tutarı grupla (dağılım için)
            // Kısmi muafiyet/Brüt durumlarında "tutar" brüt olsa bile 
            // kullanıcı bu tutarın şu kanaldan ödenmesini istediği için 
            // dağılımda direkt bu tutar baz alınır.
            $ekOdemeTutari = isset($toplamTutar) ? $toplamTutar : $tutar;
            // USER REQ: Prim usulü personelde ek ödemeler varsayılan olarak Elden (Cash) kabul edilmelidir.
            $defaultYontem = $isPrimUsulu ? 'elden' : 'banka';
            $yontem = $parametre->odeme_yontemi ?? $defaultYontem;
            if (isset($yontemliOdemeler[$yontem])) {
                $yontemliOdemeler[$yontem] += $ekOdemeTutari;
            } else {
                $yontemliOdemeler['banka'] += $ekOdemeTutari;
            }

            unset($toplamTutar); // Bir sonraki döngü için temizle

            $ekOdemeDetaylari[] = $detay;
        }

        // Her kesintiyi işle
        // NOT: Ücretsiz izin kesintisi artık burada yok, doğrudan brüt maaştan düşüldü
        $digerKesintiler = 0;
        $toplamKesinti = 0;
        $oranliKesintiler = []; // Net üzerinden oranlı kesintiler (İcra vb.)

        foreach ($kesintiler as $kesinti) {
            $tutar = floatval($kesinti->tutar);
            $parametre = $parametrelerMap[$kesinti->tur] ?? null;
            $hesaplamaTipi = $kesinti->hesaplama_tipi ?? 'sabit';

            $detay = [
                'kod' => $kesinti->tur,
                'etiket' => $parametre ? $parametre->etiket : $kesinti->tur,
                'tutar' => $tutar,
                'aciklama' => $kesinti->aciklama ?? null,
                'hesaplama_tipi' => $hesaplamaTipi,
                'oran' => floatval($kesinti->oran ?? 0)
            ];

            // İcra veya oran bazlı kesinti ise şimdilik hakedişi bekleyeceğiz (Sıralı dağıtım için)
            if ($kesinti->tur === 'icra' || $hesaplamaTipi === 'oran_net' || $hesaplamaTipi === 'asgari_oran_net') {
                $oranliKesintiler[] = [
                    'kesinti' => $kesinti,
                    'detay_index' => count($kesintiDetaylari)
                ];
                $kesintiDetaylari[] = $detay;
                continue;
            }

            // Eğer aylık gün bazlı kesinti ise tutarı yeniden hesapla
            if ($parametre && $hesaplamaTipi === 'aylik_gun_kesinti') {
                $gunSayisi = 0;
                if ($parametre->gun_sayisi_otomatik) {
                    $gunSayisi = $this->getCalismaGunuSayisi($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);
                } else {
                    $gunSayisi = intval($parametre->varsayilan_gun_sayisi ?? 26);
                }
                $hesaplananTutar = $tutar * $gunSayisi;
                $tutar = $hesaplananTutar;
                $detay['tutar'] = round($tutar, 2);
                $detay['gun_sayisi'] = $gunSayisi;
                $detay['aylik_tutar'] = floatval($kesinti->tutar);
            }

            $toplamKesinti += $tutar;
            $digerKesintiler += $tutar;

            $kesintiDetaylari[] = $detay;
        }

        // USER REQ: Yemek Yardımı Maaşa Dahil dengelemesi
        // Yemek yardımı tutarını ana maaş hakedişinden düşüyoruz ki toplam hakediş (net hedef) değişmesin.
        if ($this->hasMaasaDahilSosyalYardim($kayit)) {
            $asgariNetNominal = floatval($genelAyarlarMap['asgari_ucret_net'] ?? 28075.50);
            $brutMaas = round(($asgariNetNominal / 30) * $maasHesapGunu, 2);
        } elseif ($mealAllowanceDeduction > 0) {
            $brutMaas = max(0, $brutMaas - $mealAllowanceDeduction);
        }

        // ========== HESAPLAMALAR ==========

        // Çalışılan brüt maaş = brüt maaş (ücretsiz izin düşümü zaten yukarıda yapıldı)
        // Yasal kesintiler bu tutar üzerinden hesaplanacak
        $calisanBrutMaas = $brutMaas;
        if ($calisanBrutMaas < 0) {
            $calisanBrutMaas = 0;
        }

        // SGK Matrahı = Çalışılan Brüt Maaş + SGK'ya dahil ek ödemeler
        $sgkMatrahi = $calisanBrutMaas + $sgkMatrahEkleri;

        // SGK İşçi Payı
        $sgkIsci = $sgkMatrahi * $sgkIsciOrani;
        $issizlikIsci = $sgkMatrahi * $issizlikIsciOrani;

        // Gelir Vergisi Matrahı = SGK Matrahı - SGK Kesintileri + Vergiye tabi ek ödemeler
        $gelirVergisiMatrahi = ($calisanBrutMaas - $sgkIsci - $issizlikIsci) + $vergiliMatrahEkleri;
        if ($gelirVergisiMatrahi < 0) {
            $gelirVergisiMatrahi = 0;
        }

        // Kümülatif Gelir Vergisi Hesaplaması
        // Önceki ayların matrahını getir
        $kumulatifMatrah = $this->getKumulatifMatrah($kayit->personel_id, $donemYil, $donemAy);
        $yeniKumulatifMatrah = $kumulatifMatrah + $gelirVergisiMatrahi;

        if ($isNetMaas || $isPrimUsulu) {
            $gelirVergisi = 0;
        } else {
            $gelirVergisi = $parametreModel->hesaplaGelirVergisi($yeniKumulatifMatrah, $gelirVergisiMatrahi, $donemYil);
        }

        // Damga Vergisi = Çalışılan brüt toplam üzerinden
        $damgaVergisiMatrahi = $calisanBrutMaas + $brutEkOdemeler;
        $damgaVergisi = $damgaVergisiMatrahi * $damgaVergisiOrani;

        // Toplam ek ödemeler (gösterim için)
        $toplamEkOdeme = $brutEkOdemeler + $netEkOdemeler;

        // ========== ORANLI KESİNTİLERİN HESAPLANMASI (NET ÜZERİNDEN) ==========
        // Oranlı kesintiler için temel net hakediş (icra vb. öncesi)
        if ($isNetMaas || $isPrimUsulu) {
            // Prim Usülü / Net: Hakediş = brüt maaş + toplam ek ödemeler - diğer kesintiler
            // Ücretsiz izin zaten brüt maaştan düşülmüş durumda
            $hakedisNet = $brutMaas + $toplamEkOdeme - $digerKesintiler;
        } else {
            // Brüt: Hakediş = brüt maaş - yasal kesintiler + ek ödemeler - diğer kesintiler
            // Ücretsiz izin zaten brüt maaştan düşülmüş durumda
            $hakedisNet = $brutMaas
                - $sgkIsci - $issizlikIsci - $gelirVergisi - $damgaVergisi
                + $netEkOdemeler
                + $brutEkOdemeler
                - $digerKesintiler;
        }

        // ========== ÖDEME DAĞILIMI ÖN HAZIRLIK ==========
        // USER REQ: İcra/Sodexo/Banka dağılımı için bordro çalışma günü (maasHesapGunu) baz alınmalıdır.
        // Puantaj verisi eksik olduğunda (sadece 5 gün iş girilmişse) ödemeler hatalı düşmektedir.
        // USER REQ: Fiili çalışma günü puantajdaki gerçek gündür (Örn: 25).
        // Baz maaş hakedişi ise SSK günüdür (Örn: 29).
        $fiiliCalismaGunu = $normGun; 
        if ($fiiliCalismaGunu <= 0) $fiiliCalismaGunu = $maasHesapGunu;

        // İcra Matrahı: personelin toplam hakedişi (alt sınır kontrolü için)
        $icraMatrahi = max(0, $hakedisNet);

        // Asgari ücret net tutarını al
        $asgariUcretNet = $genelAyarlarMap['asgari_ucret_net'] ?? 17002.12;

        // İcra bazlı oranlı kesintileri ayır ve sıra numarasına göre dağıt
        $icraOranliKesintiler = [];
        $digerOranliKesintiler = [];
        foreach ($oranliKesintiler as $item) {
            if ($item['kesinti']->tur === 'icra' && !empty($item['kesinti']->icra_id)) {
                $icraOranliKesintiler[] = $item;
            } else {
                $digerOranliKesintiler[] = $item;
            }
        }

        // Önce icra dışı oranlı kesintileri hesapla (değişiklik yok)
        foreach ($digerOranliKesintiler as $item) {
            $kesinti = $item['kesinti'];
            $index = $item['detay_index'];
            $oran = floatval($kesinti->oran ?? 0);
            $tutar = round($hakedisNet * ($oran / 100), 2);

            $toplamKesinti += $tutar;
            $digerKesintiler += $tutar;
            $kesintiDetaylari[$index]['tutar'] = $tutar;

            $this->db->prepare("UPDATE personel_kesintileri SET tutar = ?, updated_at = NOW() WHERE id = ?")
                ->execute([$tutar, $kesinti->id]);
        }

        // ========== İCRA HESAPLAMA HAZIRLIK ==========
        $icraMatrahi = max(0, $hakedisNet); // Stop-loss check for final distribution
        // $asgariUcretNet yukarıda zaten cache'den alındı

        // İcra bazlı kesintileri SIRA NUMARASINA GÖRE DAĞIT
        if (!empty($icraOranliKesintiler)) {
            // 1) İcra dosyası detaylarını topla
            $icraDetaylar = [];
            foreach ($icraOranliKesintiler as $item) {
                $kesinti = $item['kesinti'];
                $sqlIcra = $this->db->prepare("SELECT id, toplam_borc, icra_dairesi, dosya_no, sira, aylik_kesinti_tutari, kesinti_tipi, kesinti_orani FROM personel_icralari WHERE id = ?");
                $sqlIcra->execute([$kesinti->icra_id]);
                $icraData = $sqlIcra->fetch(PDO::FETCH_OBJ);
                if (!$icraData)
                    continue;

                // Kalan borcu hesapla (bu dönemdeki bu kesinti hariç)
                $sqlOnceki = $this->db->prepare("SELECT SUM(tutar) as toplam FROM personel_kesintileri WHERE icra_id = ? AND id != ? AND silinme_tarihi IS NULL AND durum = 'onaylandi'");
                $sqlOnceki->execute([$kesinti->icra_id, $kesinti->id]);
                $resOnceki = $sqlOnceki->fetch(PDO::FETCH_OBJ);
                $onceki = $resOnceki ? floatval($resOnceki->toplam ?? 0) : 0;
                $kalanBorc = max(0, floatval($icraData->toplam_borc) - $onceki);

                $icraDetaylar[] = [
                    'item' => $item,
                    'icraData' => $icraData,
                    'kalanBorc' => $kalanBorc,
                    'sira' => intval($icraData->sira ?? 999)
                ];
            }

            // 2) Sıra numarasına göre sırala
            usort($icraDetaylar, function ($a, $b) {
                if ($a['sira'] != $b['sira'])
                    return $a['sira'] - $b['sira'];
                return $a['icraData']->id - $b['icraData']->id;
            });

            // 3) İlk icranın kuralına göre toplam bütçeyi hesapla
            $firstKesinti = $icraDetaylar[0]['item']['kesinti'];
            $firstHTip = $firstKesinti->hesaplama_tipi ?? 'sabit';
            $firstOran = floatval($firstKesinti->oran ?? 0);

            // USER REQ: İcra baz tutarı hesaplanırken bordro çalışma gününü baz al (Asgari Ücret Net / 30 * Gün Sayısı)
            // USER REQ: Raporlu günlerin icra bazını düşürmemesi isteniyor. Bu nedenle sadece ücretsiz izinleri düşen bir gün sayısı hesaplıyoruz.
            $icraBazGunu = $this->getMaasHesapGunu($aktifTakvimGun, $aydakiGunSayisi, $ucretsizIzinGunu);
            $bankaYatacakBaz = ($asgariUcretNet / 30) * $icraBazGunu;

            // USER REQ: İcra baz tutarı, (Asgari Ücret / 30 * Gün) olmalıdır. 
            // Daha önce eklenen min() kontrolü, raporlu günlerde matrahı peşinen düşürdüğü için hatalı sonuç veriyordu.
            // Bütçe zaten aşağıda ($icraMatrahi) ile nihai olarak sınırlandırılmaktadır.
            $icraBazTutar = $bankaYatacakBaz;

            if ($firstHTip === 'asgari_oran_net' || $firstHTip === 'oran_net') {
                // Personelin alacağı, net asgari ücret bazından düşükse kesinti alacağın üzerinden hesaplanır.
                $oranKullan = ($firstOran > 0) ? $firstOran : 25;
                $icraKesintiBazi = min($icraBazTutar, $icraMatrahi);
                $toplamIcraBudget = round($icraKesintiBazi * ($oranKullan / 100), 2);
            } else {
                // Sabit tutar
                $sabitToplam = 0;
                foreach ($icraDetaylar as $d) {
                    $sabitToplam += floatval($d['icraData']->aylik_kesinti_tutari);
                }
                $toplamIcraBudget = $sabitToplam;
            }

            // Önemli: Kesinti toplamı personelin o anki alacağından ($icraMatrahi) fazla olamaz
            if ($toplamIcraBudget > $icraMatrahi) {
                $toplamIcraBudget = $icraMatrahi;
            }

            // 4) Bütçeyi sırasıyla dağıt
            //    - 1. icranın kalan borcu >= bütçe → bütçe kadar kes, bütçe biter
            //    - 1. icranın kalan borcu < bütçe → kalan borcun tamamını kes, artan bütçe 2. icraya geçer
            $kalanBudget = $toplamIcraBudget;

            foreach ($icraDetaylar as $detay) {
                $item = $detay['item'];
                $kesinti = $item['kesinti'];
                $index = $item['detay_index'];
                $icraData = $detay['icraData'];
                $kalanBorc = $detay['kalanBorc'];

                $tutar = 0;
                if ($kalanBudget > 0 && $kalanBorc > 0) {
                    $tutar = min($kalanBudget, $kalanBorc);
                    $kalanBudget -= $tutar;
                }
                $tutar = round($tutar, 2);

                // Borç bu kesintiyle bitiyorsa uyarı ekle
                if ($tutar >= $kalanBorc && $kalanBorc > 0) {
                    // Start: check if there are any pending/active icra files remaining
                    $sqlOther = $this->db->prepare("SELECT COUNT(*) as adet FROM personel_icralari WHERE personel_id = ? AND id != ? AND durum IN ('bekliyor', 'devam_ediyor') AND silinme_tarihi IS NULL");
                    $sqlOther->execute([$kayit->personel_id, $kesinti->icra_id]);
                    $hasNextIcra = ($sqlOther->fetch(PDO::FETCH_OBJ)->adet ?? 0) > 0;

                    if ($hasNextIcra) {
                        $alreadyAdded = false;
                        foreach ($this->icra_uyarilari as $uyari) {
                            if ($uyari['personel_id'] == $kayit->personel_id && $uyari['icra_id'] == $kesinti->icra_id) {
                                $alreadyAdded = true;
                                break;
                            }
                        }
                        if (!$alreadyAdded) {
                            $this->icra_uyarilari[] = [
                                'personel_id' => $kayit->personel_id,
                                'icra_id' => $kesinti->icra_id,
                                'dosya_no' => $icraData->dosya_no,
                                'icra_dairesi' => $icraData->icra_dairesi
                            ];
                        }
                    }
                }

                // Sonuçları güncelle
                $toplamKesinti += $tutar;
                $digerKesintiler += $tutar;
                $kesintiDetaylari[$index]['tutar'] = $tutar;

                // Veritabanındaki tutarı güncelle
                if ($tutar > 0) {
                    $this->db->prepare("UPDATE personel_kesintileri SET tutar = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$tutar, $kesinti->id]);
                } else {
                    // Tutar 0 ise bu kesintiyi bu dönem için iptal et (soft delete)
                    $this->db->prepare("UPDATE personel_kesintileri SET silinme_tarihi = NOW(), updated_at = NOW() WHERE id = ?")
                        ->execute([$kesinti->id]);
                }
            }
        }

        // İcra kesintisini bul (hesaplanmış detaylardan)
        $icraKesintisi = 0;
        foreach ($kesintiDetaylari as $kd) {
            if ($kd['kod'] === 'icra') {
                $icraKesintisi += floatval($kd['tutar']);
            }
        }

        $hesaplananYemekToplam = 0;
        $hesaplananEsToplam = 0;
        $toplamDahilYardim = 0;
        if ($this->hasMaasaDahilSosyalYardim($kayit)) {
            $asgariNetNominal = $genelAyarlarMap['asgari_ucret_net'] ?? 28075.50;
            $fiiliGunSayisi = ($fiiliCalismaGunu > 0) ? $fiiliCalismaGunu : 25;
            $dahilDagilim = $this->hesaplaMaasaDahilYardimDagilimi($kayit, floatval($asgariNetNominal), $maasHesapGunu, $fiiliGunSayisi);

            $hesaplananYemekToplam = floatval($dahilDagilim['yemek_toplam'] ?? 0);
            $hesaplananEsToplam = floatval($dahilDagilim['es_toplam'] ?? 0);
            $toplamDahilYardim = floatval($dahilDagilim['toplam'] ?? 0);

            if (intval($kayit->yemek_yardimi_dahil ?? 0) === 1) {
                $this->db->prepare("UPDATE personel_ek_odemeler SET silinme_tarihi = NOW(), updated_at = NOW() 
                                   WHERE personel_id = ? AND donem_id = ? 
                                   AND (tur = 'yemek_yardimi_tum' OR tur = 'yemek' OR tur LIKE '%yemek%') 
                                   AND silinme_tarihi IS NULL")
                    ->execute([$kayit->personel_id, $kayit->donem_id]);
            }

            if (intval($kayit->es_yardimi_dahil ?? 0) === 1) {
                $this->db->prepare("UPDATE personel_ek_odemeler SET silinme_tarihi = NOW(), updated_at = NOW() 
                                   WHERE personel_id = ? AND donem_id = ? 
                                   AND (tur = 'es_yardimi' OR tur LIKE '%es_yardimi%' OR tur LIKE '%aile%') 
                                   AND silinme_tarihi IS NULL")
                    ->execute([$kayit->personel_id, $kayit->donem_id]);
            }

            if ($toplamDahilYardim > 0) {
                $toplamEkOdeme += $toplamDahilYardim;
                $netEkOdemeler += $toplamDahilYardim;
            }

            $hesaplamaDetay['ozet']['dahil_yemek_yardimi'] = $hesaplananYemekToplam;
            $hesaplamaDetay['ozet']['dahil_yemek_gun'] = intval($dahilDagilim['fiili_gun'] ?? $fiiliGunSayisi);
            $hesaplamaDetay['ozet']['dahil_yemek_gunluk'] = floatval($dahilDagilim['yemek_gunluk'] ?? 0);
            $hesaplamaDetay['ozet']['dahil_es_yardimi'] = $hesaplananEsToplam;
            $hesaplamaDetay['ozet']['dahil_toplam_yardim'] = $toplamDahilYardim;
        }

        // Net Maaş Hesabı
        if ($isNetMaas || $isPrimUsulu) {
            // ========== NET VE PRİM USULÜ İÇİN BASİT HESAPLAMA ==========
            // Net Maaş = Maaş Tutarı + Toplam Ek Ödemeler - (Toplam Kesintiler - İcra)
            // İcra kesintisi net hakedişten sonra elden ödemeden düşülür
            // NOT: Ücretsiz izin zaten brüt maaştan düşülmüş durumda
            $netMaas = $brutMaas + $toplamEkOdeme - ($toplamKesinti - ($icraKesintisi > 0 ? $icraKesintisi : 0));
        } else {
            // ========== BRÜT MAAŞ İÇİN TAM HESAPLAMA ==========
            // Net = Brüt - Yasal Kesintiler + Net Ek Ödemeler + Brüt Ek Ödemeler - (Diğer Kesintiler - İcra)
            // NOT: Ücretsiz izin zaten brüt maaştan düşülmüş durumda, ayrıca çıkarmıyoruz
            $netMaas = $brutMaas
                - $sgkIsci - $issizlikIsci - $gelirVergisi - $damgaVergisi
                + $netEkOdemeler
                + $brutEkOdemeler
                - ($digerKesintiler - $icraKesintisi);
        }

        // İşveren Maliyetleri (çalışılan brüt üzerinden)
        $sgkIsveren = $sgkMatrahi * $sgkIsverenOrani;
        $issizlikIsveren = $sgkMatrahi * $issizlikIsverenOrani;
        $toplamMaliyet = $calisanBrutMaas + $sgkIsveren + $issizlikIsveren + $brutEkOdemeler + $netEkOdemeler;

        // ========== ÖDEME DAĞILIMI HESAPLAMA ==========
        if (isset($kayit->dagitim_manuel) && $kayit->dagitim_manuel == 1) {
            $sodexoOdemesi = floatval($kayit->sodexo_odemesi ?? 0);
            $bankaOdemesi = floatval($kayit->banka_odemesi ?? 0);
            $eldenOdeme = max(0, $netMaas - $bankaOdemesi - $sodexoOdemesi - $icraKesintisi - floatval($kayit->diger_odeme ?? 0));
        } else {
            // Sodexo tutarını fiili çalışma gününe göre oranla (30 gün üzerinden)
            // Eğer manuel olarak güncellenmişse veriyi olduğu gibi al
            if (isset($kayit->sodexo_manuel) && $kayit->sodexo_manuel == 1) {
                $sodexoOdemesi = floatval($kayit->sodexo_odemesi ?? 0);
            } else {
                $aylikSodexo = floatval($kayit->sodexo ?? 0);
                $sodexoOdemesi = (($aylikSodexo / 30) * $fiiliCalismaGunu) + ($yontemliOdemeler['sodexo'] ?? 0);
            }

            if ($isPrimUsulu) {
                // Prim Usülü hesaplama
                // Ödeme sıralaması:
                // 1. Önce Sodexo (çalışma gününe göre oranlanır - zaten yukarıda hesaplandı)
                // 2. Sonra Banka = Minimum asgari ücret neti (çalışma gününe oranlı)
                // 3. Sonra İcra kesintisi (elden ödemeden düşülür)
                // 4. En son Elden ödeme = Net maaş - Banka - Sodexo - İcra

                $toplamPrim = $netMaas; // Net maaş zaten prim toplamını içeriyor


                // Bankaya yatacak minimum tutar (asgari ücretin çalışma gününe oranı)
                if ($fiiliCalismaGunu >= 30) {
                    $bankaYatacakMinimum = $asgariUcretNet;
                } else {
                    $gunlukAsgariUcret = $asgariUcretNet / 30;
                    $bankaYatacakMinimum = $gunlukAsgariUcret * $fiiliCalismaGunu;
                }

                // 1. Önce Sodexo düşülür (zaten hesaplandı)
                // 2. Bankaya yatacak tutar = Minimum asgari ücret tutarı
                // USER REQ: Banka yöntemli ek ödemeleri de banka bazına ekle
                $bankaIcinMaksimum = max(0, $toplamPrim - $sodexoOdemesi);
                // USER REQ: Prim Usulü personelde asgari ücret ek ödemelerin üzerine eklenmez.
                // Asgari ücret bu personeller için bankaya yatacak minimum garantiyi ifade eder.
                if ($isPrimUsulu) {
                    $bankaBazVal = max($bankaYatacakMinimum, ($yontemliOdemeler['banka'] ?? 0));
                } else {
                    $bankaBazVal = $bankaYatacakMinimum + ($yontemliOdemeler['banka'] ?? 0);
                }
                $bankaBaz = min($bankaBazVal, $bankaIcinMaksimum);

                // Banka tutarı minimum asgari ücretin altına düşmemeli (yeterli bakiye varsa)
                if ($bankaBaz < $bankaYatacakMinimum && $bankaIcinMaksimum >= $bankaYatacakMinimum) {
                    $bankaBaz = $bankaYatacakMinimum;
                }

                // USER REQ: İcra tutarını bankadan düş (Maaşa dahil yemek yardımını banka bazına ekle)
                $bankaOdemesi = max(0, $bankaBaz - $icraKesintisi + ($yemekDahilGerekenTutar ?? 0));

                if (($kayit->sgk_yapilan_firma ?? '') === 'İŞKUR') {
                    $bankaOdemesi = 0;
                }

                // 3. Elden ödeme = Net maaş - Banka - Sodexo - İcra - Diğer Ödeme
                $eldenOdeme = max(0, $toplamPrim - $bankaOdemesi - $sodexoOdemesi - $icraKesintisi - ($kayit->diger_odeme ?? 0));
            } elseif ($isNetMaas) {
                // ========== NET MAAŞ HESAPLAMA ==========
                // Ödeme sıralaması:
                // 1. Önce Sodexo hesaplanır (çalışma gününe göre oranlanır)
                // 2. Sonra Bankaya yatacak tutar = Asgari ücret neti (günlük oranlı) - Bu tutar minimum garantidir
                // 3. Sonra İcra kesintisi (elden ödemeden düşülür, bankadan değil)
                // 4. En son Elden ödeme = Net maaş - Banka - Sodexo - İcra



                // NOT: Net maaş günlük oranlama artık gerekli değil.
                // $brutMaas zaten görev geçmişi kıst hesabını ve ücretsiz izin düşümünü içeriyor.
                // Net maaş yukarıda doğru şekilde hesaplandı.

                // Bankaya yatacak minimum tutar (asgari ücret netinden günlük oranlı)
                // Bu tutar asla asgari ücretin çalışma gününe oranının altına düşmemeli
                if ($fiiliCalismaGunu >= 30) {
                    // Tam maaş - Asgari ücret neti bankaya
                    $bankaYatacakMinimum = $asgariUcretNet;
                } else {
                    // Eksik gün - Asgari ücretin günlüğü × çalışma günü
                    $gunlukAsgariUcret = $asgariUcretNet / 30;
                    $bankaYatacakMinimum = $gunlukAsgariUcret * $fiiliCalismaGunu;
                }

                // 1. Önce Sodexo düşülür (zaten yukarıda hesaplandı)
                // 2. Bankaya yatacak tutar = Minimum banka tutarı (icra kesintisi bankadan düşülmez!)
                // USER REQ: Banka yöntemli ek ödemeleri de banka bazına ekle
                $bankaIcinMaksimum = max(0, $netMaas - $sodexoOdemesi);
                $bankaBazVal = $bankaYatacakMinimum + ($yontemliOdemeler['banka'] ?? 0) + ($yemekDahilGerekenTutar ?? 0);
                $bankaBaz = min($bankaBazVal, $bankaIcinMaksimum);

                // Banka tutarı minimum asgari ücretın altına düşmemeli (yeterli bakiye varsa)
                if ($bankaBaz < $bankaYatacakMinimum && $bankaIcinMaksimum >= $bankaYatacakMinimum) {
                    $bankaBaz = $bankaYatacakMinimum;
                }

                // USER REQ: İcra tutarını bankadan düş
                $bankaOdemesi = max(0, $bankaBaz - $icraKesintisi);

                if (($kayit->sgk_yapilan_firma ?? '') === 'İŞKUR') {
                    $bankaOdemesi = 0;
                }

                // 3. Elden ödeme = Net maaş - Banka - Sodexo - İcra - Diğer Ödeme
                $eldenOdeme = max(0, $netMaas - $bankaOdemesi - $sodexoOdemesi - $icraKesintisi - ($kayit->diger_odeme ?? 0));
            } else {
                // Normal hesaplama (Brüt)
                // Ödeme sıralaması:
                // 1. Önce Sodexo (çalışma gününe göre oranlanır - zaten yukarıda hesaplandı)
                // 2. Sonra Banka = Minimum asgari ücret neti (çalışma gününe oranlı) - Bu tutar minimum garantidir
                // 3. Sonra İcra kesintisi (elden ödemeden düşülür, bankadan değil)
                // 4. En son Elden ödeme = Net maaş - Banka - Sodexo - İcra


                // Bankaya yatacak minimum tutar (asgari ücretin çalışma gününe oranı)
                if ($fiiliCalismaGunu >= 30) {
                    $bankaYatacakMinimum = $asgariUcretNet;
                } else {
                    $gunlukAsgariUcret = $asgariUcretNet / 30;
                    $bankaYatacakMinimum = $gunlukAsgariUcret * $fiiliCalismaGunu;
                }

                // 1. Önce Sodexo düşülür (zaten hesaplandı)
                // 2. Bankaya yatacak tutar = Minimum asgari ücret tutarı
                // USER REQ: Banka yöntemli ek ödemeleri de banka bazına ekle
                $bankaIcinMaksimum = max(0, $netMaas - $sodexoOdemesi);
                $bankaBazVal = $bankaYatacakMinimum + ($yontemliOdemeler['banka'] ?? 0) + ($yemekDahilGerekenTutar ?? 0);
                $bankaBaz = min($bankaBazVal, $bankaIcinMaksimum);

                // Banka tutarı minimum asgari ücretin altına düşmemeli (yeterli bakiye varsa)
                if ($bankaBaz < $bankaYatacakMinimum && $bankaIcinMaksimum >= $bankaYatacakMinimum) {
                    $bankaBaz = $bankaYatacakMinimum;
                }

                // USER REQ: İcra tutarını bankadan düş
                $bankaOdemesi = max(0, $bankaBaz - $icraKesintisi);

                if (($kayit->sgk_yapilan_firma ?? '') === 'İŞKUR') {
                    $bankaOdemesi = 0;
                }

                // 3. Elden ödeme = Net maaş - Banka - Sodexo - İcra - Diğer Ödeme
                $eldenOdeme = max(0, $netMaas - $bankaOdemesi - $sodexoOdemesi - $icraKesintisi - ($kayit->diger_odeme ?? 0));
            }
        }

        // Hesaplama Snapshot (JSON)
        $hesaplamaDetay = [
            'hesaplama_tarihi' => date('Y-m-d H:i:s'),
            'maas_durumu' => $maasDurumuRaw,
            'is_net_maas' => $isNetMaas,
            'is_prim_usulu' => $isPrimUsulu,
            'donem' => [
                'id' => $kayit->donem_id,
                'baslangic' => $donemTarihi,
                'ay' => $donemAy,
                'yil' => $donemYil
            ],
            'parametreler' => [
                'sgk_isci_orani' => $sgkIsciOrani * 100,
                'issizlik_isci_orani' => $issizlikIsciOrani * 100,
                'sgk_isveren_orani' => $sgkIsverenOrani * 100,
                'issizlik_isveren_orani' => $issizlikIsverenOrani * 100,
                'damga_vergisi_orani' => $damgaVergisiOrani * 100,
                'calisma_gunu_sayisi' => $calismaGunuSayisi,
                'asgari_ucret_net' => $isNetMaas || $isPrimUsulu ? ($asgariUcretNet ?? 0) : 0,
                'fazla_mesai_tutar' => round($toplamMesaiTutar, 2)
            ],
            'matrahlar' => [
                'brut_maas' => round($brutMaas, 2),
                'nominal_maas' => round($nominalBrutMaas, 2),
                'ssk_gunu' => $maasHesapGunu,
                'normal_gun' => $normGun,
                'hafta_tatili_gunu' => $haftaTatiliGunu,
                'genel_tatil_gunu' => $genelTatilGunu,
                'rapor_gunu' => $raporGunu,
                'ucretsiz_izin_gunu' => $ucretsizIzinGunu,
                'ucretsiz_izin_dusumu' => round($ucretsizIzinDusumu, 2),
                'ucretli_izin_gunu' => $ucretliIzinGunu,
                'maas_hesap_gunu' => $maasHesapGunu,
                'fiili_calisma_gunu' => $fiiliCalismaGunu,
                'calisan_brut_maas' => round($calisanBrutMaas, 2),
                'sgk_matrahi' => round($sgkMatrahi, 2),
                'gelir_vergisi_matrahi' => round($gelirVergisiMatrahi, 2),
                'damga_vergisi_matrahi' => round($damgaVergisiMatrahi, 2),
                'onceki_kumulatif' => round($kumulatifMatrah, 2),
                'yeni_kumulatif' => round($yeniKumulatifMatrah, 2)
            ],
            'odeme_dagilimi' => [
                'icra_kesintisi' => round($icraKesintisi, 2),
                'banka_brut' => isset($bankaYatacakBrut) ? round($bankaYatacakBrut, 2) : 0,
                'banka_net' => round($bankaOdemesi, 2),
                'sodexo' => round($sodexoOdemesi, 2),
                'elden' => round($eldenOdeme, 2)
            ],
            'ek_odemeler' => $ekOdemeDetaylari,
            'kesintiler' => $kesintiDetaylari,
            'ozet' => [
                'brut_ek_odemeler' => round($brutEkOdemeler, 2),
                'net_ek_odemeler' => round($netEkOdemeler, 2),
                'sgk_matrah_ekleri' => round($sgkMatrahEkleri, 2),
                'vergili_matrah_ekleri' => round($vergiliMatrahEkleri, 2)
            ]
        ];

        // USER REQ: Maaşa dahil sosyal yardımlar varsa banka ve elden dağılımını zorla
        if ($this->hasMaasaDahilSosyalYardim($kayit)) {
            // Banka = Asgari Hakediş + Hesaplanan dahil yardımlar
            // Bu tutar icra veya diğer kesintiler varsa onlardan arındırılmalıdır
            $asgariNetNominal = $genelAyarlarMap['asgari_ucret_net'] ?? 28075.50;
            $asgariHakedis = round(($asgariNetNominal / 30) * $maasHesapGunu, 2);
            
            $bankaOdemesi = max(0, $asgariHakedis + $toplamDahilYardim - $icraKesintisi - ($kayit->diger_odeme ?? 0));
            $eldenOdeme = 0;
            
            // Listedeki gösterim değerlerini de hedef nete (veya yuvarlanmış yeni nete) sabitle
            $netAlacak = $asgariHakedis + $toplamDahilYardim;
            $toplamAlacak = $netAlacak + ($icraKesintisi ?? 0);
        }

        // Kaydet
        return $this->saveBordroHesaplama($bordro_personel_id, [
            'brut_maas' => round($brutMaas, 2),
            'sgk_isci' => round($sgkIsci, 2),
            'issizlik_isci' => round($issizlikIsci, 2),
            'gelir_vergisi' => round($gelirVergisi, 2),
            'damga_vergisi' => round($damgaVergisi, 2),
            'net_maas' => round($netMaas, 2),
            'sgk_isveren' => round($sgkIsveren, 2),
            'issizlik_isveren' => round($issizlikIsveren, 2),
            'toplam_maliyet' => round($toplamMaliyet, 2),
            'toplam_kesinti' => round($toplamKesinti, 2),
            'toplam_ek_odeme' => round($toplamEkOdeme, 2),
            'fazla_mesai_tutar' => round($toplamMesaiTutar, 2),
            'calisan_gun' => $maasHesapGunu,
            'sodexo_odemesi' => round($sodexoOdemesi, 2),
            'banka_odemesi' => round($bankaOdemesi, 2),
            'elden_odeme' => round($eldenOdeme, 2),
            'kumulatif_matrah' => round($yeniKumulatifMatrah, 2),
            'hesaplayan_id' => $hesaplayan_id,
            'hesaplayan_ad_soyad' => $hesaplayan_ad_soyad,
            'hesaplama_detay' => json_encode($hesaplamaDetay, JSON_UNESCAPED_UNICODE)
        ]);
    }

    /**
     * Personelin yılbaşından bu aya kadar kümülatif gelir vergisi matrahını getirir
     */
    private function getKumulatifMatrah($personel_id, $yil, $ay)
    {
        // Bu yılın Ocak'tan önceki aya kadar toplam gelir vergisi matrahı
        $sql = $this->db->prepare("
            SELECT COALESCE(SUM(bp.brut_maas - bp.sgk_isci - bp.issizlik_isci), 0) as toplam_matrah
            FROM {$this->table} bp
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ?
            AND YEAR(bd.baslangic_tarihi) = ?
            AND MONTH(bd.baslangic_tarihi) < ?
            AND bp.hesaplama_tarihi IS NOT NULL
        ");
        $sql->execute([$personel_id, $yil, $ay]);
        $result = $sql->fetch(PDO::FETCH_OBJ);

        return floatval($result->toplam_matrah ?? 0);
    }

    /**
     * Personel ID ve Dönem ID'ye göre maaş hesaplar
     */
    public function hesaplaMaasByPersonelDonem($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("SELECT id FROM {$this->table} WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL");
        $sql->execute([$personel_id, $donem_id]);
        $bp = $sql->fetch(PDO::FETCH_OBJ);

        if ($bp) {
            $hesaplayanId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
            $hesaplayanAdSoyad = $_SESSION['user_full_name'] ?? ($_SESSION['user']->adi_soyadi ?? 'Sistem');
            return $this->hesaplaMaas($bp->id, $hesaplayanId, $hesaplayanAdSoyad);
        }
        return false;
    }

    /**
     * Personelin dönemdeki kesintilerini türe göre detaylı getirir
     */
    public function getDonemKesintileriDetay($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT tur, SUM(tutar) as toplam_tutar, COUNT(*) as adet
            FROM personel_kesintileri 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
            GROUP BY tur
            ORDER BY toplam_tutar DESC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin dönemdeki ek ödemelerini türe göre detaylı getirir
     */
    public function getDonemEkOdemeleriDetay($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT tur, SUM(tutar) as toplam_tutar, COUNT(*) as adet
            FROM personel_ek_odemeler 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
            GROUP BY tur
            ORDER BY toplam_tutar DESC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin dönemdeki tüm kesinti kayıtlarını getirir (detaylı liste için)
     * Sadece onaylanmış kesintileri getirir (maaş hesaplaması için)
     */
    public function getDonemKesintileriListe($personel_id, $donem_id)
    {
        if (empty($personel_id) || empty($donem_id)) {
            return [];
        }

        $PersonelKesintileriModel = new \App\Model\PersonelKesintileriModel();
        return $PersonelKesintileriModel->getPersonelKesintileri($personel_id, [
            'filter_kesinti_mode' => 'donem',
            'filter_kesinti_donem' => $donem_id,
            'actual_only' => true
        ]);
    }

    /**
     * Personelin dönemdeki onay bekleyen kesinti sayısını ve toplam tutarını getirir
     * Silinmiş kesintiler hariç tutulur
     */
    public function getOnayBekleyenKesintiler($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as adet, COALESCE(SUM(tutar), 0) as toplam_tutar
            FROM personel_kesintileri
            WHERE personel_id = ? AND donem_id = ? 
              AND silinme_tarihi IS NULL
              AND durum = 'beklemede'
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Verilen bordro_personel id listesi için onay bekleyen kesintileri ve personel adlarını
     * tek sorguda döndürür. api.php N+1 sorununu gidermek için kullanılır.
     * @param int[] $bp_ids  bordro_personel.id değerleri
     * @param int   $donem_id
     * @return array  personel_id => {personel_id, adi_soyadi, onay_bekleyen_adet, onay_bekleyen_tutar}
     */
    public function getOnayBekleyenBatch(array $bp_ids, int $donem_id): array
    {
        if (empty($bp_ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($bp_ids), '?'));
        $sql = $this->db->prepare("
            SELECT bp.id AS bp_id, bp.personel_id, p.adi_soyadi,
                   COALESCE(pk.adet, 0)        AS onay_bekleyen_adet,
                   COALESCE(pk.toplam_tutar, 0) AS onay_bekleyen_tutar
            FROM bordro_personel bp
            INNER JOIN personel p ON bp.personel_id = p.id
            LEFT JOIN (
                SELECT personel_id, COUNT(*) AS adet, SUM(tutar) AS toplam_tutar
                FROM personel_kesintileri
                WHERE donem_id = ? AND silinme_tarihi IS NULL AND durum = 'beklemede'
                GROUP BY personel_id
            ) pk ON bp.personel_id = pk.personel_id
            WHERE bp.id IN ($placeholders)
        ");
        $sql->execute(array_merge([$donem_id], $bp_ids));
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);
        $result = [];
        foreach ($rows as $row) {
            $result[$row->bp_id] = $row;
        }
        return $result;
    }

    /**
     * Personelin dönemdeki tüm ek ödeme kayıtlarını getirir (detaylı liste için)
     */
    public function getDonemEkOdemeleriListe($personel_id, $donem_id)
    {
        if (empty($personel_id) || empty($donem_id)) {
            return [];
        }

        // USER REQ: Mükerrer hesaplamayı önlemek için; master sürekli ödemeler (ana_odeme_id NULL olanlar) yerine, 
        // bu dönem için halihazırda oluşturulmuş olan (aligned) kayıtları çekmeliyiz.
        // Böylece master kayıtlar (rate) güncel kalır, period kayıtları (amount) hesaplanır.
        $sql = $this->db->prepare("
            SELECT peo.*, bp.etiket as parametre_adi, bp.kod as parametre_kodu
            FROM personel_ek_odemeler peo
            LEFT JOIN bordro_parametreleri bp ON peo.parametre_id = bp.id
            WHERE peo.personel_id = ? 
              AND peo.donem_id = ? 
              AND peo.silinme_tarihi IS NULL
            ORDER BY peo.id ASC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Banka export için dönemdeki personellerin detaylı bilgilerini getirir
     * Personel tablosundan tüm gerekli alanları çeker
     */
    public function getPersonellerByDonemDetayli($donem_id, $ids = [])
    {
        $idFilter = "";
        $params = [$donem_id];

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $idFilter = " AND bp.id IN ($placeholders)";
            $params = array_merge($params, $ids);
        }

        $sql = $this->db->prepare("
            SELECT bp.*, 
                   bp.banka_odemesi,
                   bp.sodexo_odemesi,
                   bp.hesaplayan_ad_soyad,
                   p.sodexo_kart_no,
                   bp.net_maas,
                   p.adi_soyadi, 
                   p.tc_kimlik_no, 
                   p.anne_adi,
                   p.baba_adi,
                   p.dogum_tarihi,
                   p.dogum_yeri_il,
                   p.dogum_yeri_ilce,
                   p.adres,
                   p.cinsiyet,
                   p.cep_telefonu,
                   p.email_adresi,
                   p.iban_numarasi,
                   p.sgk_yapilan_firma,
                   p.yemek_yardimi_dahil,
                   p.yemek_yardimi_tutari,
                   p.yemek_yardimi_parametre_id,
                   p.es_yardimi_dahil,
                   p.es_yardimi_tutari,
                   p.es_yardimi_parametre_id
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL $idFilter
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
