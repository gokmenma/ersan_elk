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

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Belirli bir dönemdeki tüm personelleri getirir
     */
    public function getPersonellerByDonem($donem_id, $ids = [])
    {
        $firma_id = $_SESSION['firma_id'] ?? 0;
        $idFilter = "";
        $params = [$firma_id, $donem_id, $donem_id, $donem_id];

        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $idFilter = " AND bp.id IN ($placeholders)";
            $params = array_merge($params, $ids);
        }

        $sql = $this->db->prepare("
            SELECT bp.id, bp.donem_id, bp.personel_id, bp.brut_maas, bp.net_maas,
                   bp.kesinti_tutar, bp.prim_tutar, bp.hesaplama_tarihi,
                   bp.banka_odemesi, bp.sodexo_odemesi, bp.diger_odeme, bp.elden_odeme,
                   bp.calisan_gun, bp.aciklama,
                   p.adi_soyadi, p.tc_kimlik_no, p.departman, p.gorev, 
                   p.ise_giris_tarihi, p.isten_cikis_tarihi, p.maas_tutari, p.maas_durumu,
                   p.cep_telefonu, p.resim_yolu, p.sgk_yapilan_firma,
                   t_all.ekip_adi, t_all.ekip_bolge,
                   COALESCE(pk_agg.toplam_kesinti, 0) as guncel_toplam_kesinti,
                   COALESCE(eo_agg.toplam_ek_odeme, 0) as guncel_toplam_ek_odeme,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.odeme_dagilimi.icra_kesintisi')) as hd_icra_kesintisi,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.fiili_calisma_gunu')) as hd_fiili_calisma_gunu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.ucretsiz_izin_gunu')) as hd_ucretsiz_izin_gunu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.ucretsiz_izin_dusumu')) as hd_ucretsiz_izin_dusumu,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.nominal_maas')) as hd_nominal_maas,
                   JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.ucretli_izin_gunu')) as hd_ucretli_izin_gunu
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            LEFT JOIN (
                SELECT pg.personel_id, 
                       GROUP_CONCAT(DISTINCT t.tur_adi SEPARATOR ', ') as ekip_adi,
                       GROUP_CONCAT(DISTINCT t.ekip_bolge SEPARATOR ', ') as ekip_bolge
                FROM personel_ekip_gecmisi pg
                JOIN tanimlamalar t ON pg.ekip_kodu_id = t.id
                WHERE pg.baslangic_tarihi <= CURDATE() 
                AND (pg.bitis_tarihi IS NULL OR pg.bitis_tarihi >= CURDATE())
                AND pg.firma_id = ?
                GROUP BY pg.personel_id
            ) t_all ON p.id = t_all.personel_id
            LEFT JOIN (
                SELECT personel_id, donem_id, SUM(tutar) as toplam_kesinti
                FROM personel_kesintileri 
                WHERE donem_id = ? AND silinme_tarihi IS NULL AND (durum = 'onaylandi' OR tur = 'icra')
                GROUP BY personel_id, donem_id
            ) pk_agg ON bp.personel_id = pk_agg.personel_id AND bp.donem_id = pk_agg.donem_id
            LEFT JOIN (
                SELECT personel_id, donem_id, SUM(tutar) as toplam_ek_odeme
                FROM personel_ek_odemeler 
                WHERE donem_id = ? AND silinme_tarihi IS NULL AND durum = 'onaylandi'
                GROUP BY personel_id, donem_id
            ) eo_agg ON bp.personel_id = eo_agg.personel_id AND bp.donem_id = eo_agg.donem_id
            WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL $idFilter
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute($params);
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
            hesaplama_tarihi = NOW()
        WHERE id = :id
    ");

        $sql->bindParam(':id', $id, PDO::PARAM_INT);
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
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL AND durum = 'onaylandi'
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
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL AND durum = 'onaylandi'
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
        // 1. Toplam Hakediş: Kapatılmış dönemlerdeki (Net Maaşlar + O dönemde mahsup edilen avanslar)
        // 2. Alınan Ödeme: Personelin bugüne kadar aldığı tüm onaylanmış avanslar
        // 3. Kalan Bakiye: Toplam Hakediş - Alınan Ödeme

        // Kapatılmış dönemlerdeki net maaş toplamı (Burası personelin eline geçecek net tutardır)
        $sqlNet = $this->db->prepare("
            SELECT SUM(bp.net_maas) as toplam_net
            FROM {$this->table} bp
            INNER JOIN bordro_donemi bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL AND bd.kapali_mi = 1
        ");
        $sqlNet->execute([$personel_id]);
        $toplam_net = $sqlNet->fetch(PDO::FETCH_OBJ)->toplam_net ?? 0;

        // Kapatılmış dönemlerde maaştan düşülen (mahsup edilen) avanslar
        $sqlAvansKesinti = $this->db->prepare("
            SELECT SUM(pk.tutar) as toplam_kesinti_avans
            FROM personel_kesintileri pk
            INNER JOIN bordro_donemi bd ON pk.donem_id = bd.id
            WHERE pk.personel_id = ? AND pk.tur = 'avans' AND bd.kapali_mi = 1 AND pk.silinme_tarihi IS NULL
        ");
        $sqlAvansKesinti->execute([$personel_id]);
        $toplam_avans_kesinti = $sqlAvansKesinti->fetch(PDO::FETCH_OBJ)->toplam_kesinti_avans ?? 0;

        // Toplam Hakediş = Kapatılmış dönemlerin gerçek net kazancı (Kesintiler öncesi net)
        $toplam_hakedis = $toplam_net + $toplam_avans_kesinti;

        // Onaylanmış tüm avanslar (Personelin cebine giren toplam para)
        $sqlAvans = $this->db->prepare("
            SELECT SUM(tutar) as toplam_avans 
            FROM personel_avanslari 
            WHERE personel_id = ? AND durum = 'onaylandi' AND silinme_tarihi IS NULL
        ");
        $sqlAvans->execute([$personel_id]);
        $alinan_odeme = $sqlAvans->fetch(PDO::FETCH_OBJ)->toplam_avans ?? 0;

        return (object) [
            'toplam_hakedis' => $toplam_hakedis,
            'alinan_odeme' => $alinan_odeme
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
        $PersonelModel = new \App\Model\PersonelModel();
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

        // 3. Tanımlamalar tablosundan ücretli iş türlerini al
        $TanimlamalarModel = new \App\Model\TanimlamalarModel();
        $isTurleri = $TanimlamalarModel->getIsTurleri(); // grup = 'is_turu'

        // Araç kullanımı "Kendi Aracı" ise araçlı personel ücretini kullan
        $isAracli = (isset($personel->arac_kullanim) && $personel->arac_kullanim === 'Kendi Aracı');

        // is_emri_sonucu -> (tur_adi, birim_ucret) map'i oluştur
        $isEmriSonucuMap = [];
        foreach ($isTurleri as $tur) {
            // Araçlı personel için özel ücret alanı kontrolü
            $ucretAlani = $isAracli ? 'aracli_personel_is_turu_ucret' : 'is_turu_ucret';
            $birimUcret = floatval(\App\Helper\Helper::formattedMoneyToNumber($tur->$ucretAlani ?? 0));

            // Eğer araçlı personel ücreti 0 ise normal ücreti kullan (opsiyonel, ama genellikle istenir)
            if ($isAracli && $birimUcret <= 0) {
                $birimUcret = floatval(\App\Helper\Helper::formattedMoneyToNumber($tur->is_turu_ucret ?? 0));
            }

            if ($birimUcret > 0 && !empty($tur->is_emri_sonucu)) {
                $isEmriSonucuMap[$tur->is_emri_sonucu] = [
                    'tur_adi' => $tur->tur_adi,
                    'birim_ucret' => $birimUcret
                ];
            }
        }

        if (empty($isEmriSonucuMap)) {
            return; // Ücretli iş türü tanımlı değil
        }

        // 4. Yapılan işleri is_emri_sonucu bazında grupla
        // Hem yeni (is_emri_sonucu_id) hem eski (is_emri_sonucu string) alanları destekle
        $sql = $this->db->prepare("
            SELECT 
                COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu) as is_emri_sonucu,
                COALESCE(tn.tur_adi, t.is_emri_tipi) as is_emri_tipi,
                SUM(t.sonuclanmis) as adet
            FROM yapilan_isler t
            LEFT JOIN tanimlamalar tn ON t.is_emri_sonucu_id = tn.id
            WHERE t.personel_id = ? 
            AND t.tarih BETWEEN ? AND ?
            AND (t.is_emri_sonucu_id > 0 OR (t.is_emri_sonucu IS NOT NULL AND t.is_emri_sonucu != ''))
            AND t.silinme_tarihi IS NULL
            GROUP BY COALESCE(tn.is_emri_sonucu, t.is_emri_sonucu), COALESCE(tn.tur_adi, t.is_emri_tipi)
        ");
        $sql->execute([$personel_id, $baslangic_tarihi, $bitis_tarihi]);
        $yapilanIsler = $sql->fetchAll(PDO::FETCH_OBJ);

        if (empty($yapilanIsler)) {
            return;
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
                    if (!isset($isEmriSonucuMap[$is->is_emri_sonucu]))
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

            // Bu is_emri_sonucu için ücret tanımlı mı?
            if (!isset($isEmriSonucuMap[$isEmriSonucu])) {
                continue; // Ücret tanımlı değil, atla
            }

            $birimUcret = $isEmriSonucuMap[$isEmriSonucu]['birim_ucret'];

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
            AND (durum IS NULL OR durum NOT IN ('talep_edildi', 'reddedildi', 'iptal'))
            GROUP BY nobet_tipi
        ");
        $sql->execute([$personel_id, $baslangic_tarihi, $bitis_tarihi]);
        $nobetGruplari = $sql->fetchAll(PDO::FETCH_OBJ);

        if (empty($nobetGruplari)) {
            return;
        }

        // 3. Ücretleri BordroParametreModel'den al
        $paramModel = new \App\Model\BordroParametreModel();

        $haftaIciParam = $paramModel->getByKod('hafta_ici_nobet', $baslangic_tarihi);
        $haftaSonuParam = $paramModel->getByKod('hafta_sonu_nobet', $baslangic_tarihi);

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
        $parametreModel = new BordroParametreModel();
        $kacakParam = $parametreModel->getByKod('kacak_kontrol_primi', $donemTarihi);

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
        // Dönem içindeki onaylanmış avansları getir
        // NOT: talep_tarihi datetime formatında olduğu için DATE() fonksiyonu ile karşılaştırıyoruz
        // Aksi halde 2026-01-31 14:30:00 gibi bir değer, 2026-01-31 bitiş tarihinden büyük sayılır
        $sql = $this->db->prepare("
            SELECT id, tutar, talep_tarihi, aciklama
            FROM personel_avanslari
            WHERE personel_id = ? 
            AND durum = 'onaylandi'
            AND silinme_tarihi IS NULL
            AND DATE(talep_tarihi) BETWEEN ? AND ?
        ");
        $sql->execute([$personel_id, $baslangic_tarihi, $bitis_tarihi]);
        $avanslar = $sql->fetchAll(PDO::FETCH_OBJ);

        $toplamAvans = 0;

        foreach ($avanslar as $avans) {
            $tutar = floatval($avans->tutar);
            $tarih = date('d.m.Y', strtotime($avans->talep_tarihi));
            $aciklamaPattern = "[Avans] $tarih - %";


            // Bu avans için zaten kesinti var mı kontrol et
            // Maaş hesaplanırken soft-delete yapılan (ve daha önce onaylanmış olabilecek)
            // kayıtları bulup geri getiriyoruz ki onay durumu (durum=onaylandi) kaybolmasın.
            $mevcutKontrol = $this->db->prepare("
            SELECT id, durum FROM personel_kesintileri
            WHERE personel_id = ? AND donem_id = ? AND tur = 'avans' 
            AND aciklama LIKE ?
            ORDER BY id DESC LIMIT 1
        ");
            $mevcutKontrol->execute([$personel_id, $donem_id, $aciklamaPattern]);
            $mevcut = $mevcutKontrol->fetch();

            if ($mevcut) {
                // Mevcut kesinti var (muhtemelen az önce soft-delete edildi), geri getir
                $restoreSql = $this->db->prepare("
                UPDATE personel_kesintileri 
                SET silinme_tarihi = NULL, tutar = ? 
                WHERE id = ?
            ");
                $restoreSql->execute([$tutar, $mevcut['id']]);
                $toplamAvans += $tutar;
                continue;
            }

            // Mevcut kesinti hiç yok, yeni oluştur
            $aciklama = "[Avans] $tarih - " . ($avans->aciklama ?? 'Avans Talebi');
            // Avans zaten onaylı olduğu için kesintiyi de otomatik 'onaylandi' olarak oluşturabiliriz
            // veya mevcut yapıdaki gibi 'beklemede' bırakabiliriz. Kullanıcı "avans onaylı olduğu halde" 
            // dediği için bunu onaylandı olarak kaydetmek de mantıklı olabilir.
            // Şimdilik sadece geri getirme işlemi yapıldığı için eski onayları koruyacağız.
            // Yeni oluşturulacak ise "onaylandi" olarak oluşturulması daha mantıklı (çünkü avans onaylı).
            $this->addKesinti($personel_id, $donem_id, $aciklama, $tutar, 'avans', 'onaylandi');
            $toplamAvans += $tutar;
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
    public function olusturIcraKesintileri($personel_id, $donem_id, $baslangic_tarihi, $bitis_tarihi)
    {
        // İcra parametresini bul
        $paramModel = new BordroParametreModel();
        $param = $paramModel->getByKod('icra', $bitis_tarihi);
        $paramId = $param ? $param->id : null;

        // Hesaplama tipi ve oran bilgisini al
        $hTip = $param ? $param->hesaplama_tipi : 'sabit';
        if ($hTip === 'oran_bazli_net')
            $hTip = 'oran_net';
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
            ORDER BY sira ASC, id ASC
        ");
        $sql->execute([$personel_id, $bitis_tarihi]);
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

            // Geriye dönük uyumluluk: Eğer icra dosyasında ayar yoksa (veya sabit ise) 
            // ve küresel bir oran tanımı varsa, küresel olanı kullan? 
            // Hayır, kullanıcı bireysel ayar istiyorsa onu kullanalım. 
            // Ancak mevcut icralar 'tutar' olarak kaldı. Sabit modda aylık tutar kullanılır.

            $tutar = floatval($icra->aylik_kesinti_tutari);

            if ($finalHTip === 'oran_net' || $finalHTip === 'asgari_oran_net') {
                $tutar = 0; // Placeholder
            } else {
                // Sabit tutarlı kesintilerde kalan borç kontrolü yap
                if ($tutar <= 0)
                    continue;

                if ($kalanBorc <= 0) {
                    $tutar = 0;
                } elseif ($tutar > $kalanBorc) {
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
            WHERE grup = 'izin_turu' AND ucretli_mi = 1 AND silinme_tarihi IS NULL
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
                $toplamGun += $kesisimBaslangic->diff($kesisimBitis)->days + 1;
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
            WHERE grup = 'izin_turu' AND ucretli_mi = 0 AND silinme_tarihi IS NULL
        ");
        $izinTurleriSql->execute();
        $ucretsizIzinTurIds = $izinTurleriSql->fetchAll(PDO::FETCH_COLUMN);

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
        $parametreModel = new BordroParametreModel();
        $besParam = $parametreModel->getByKod('bes_kesinti', $donemTarihi);

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
        $PersonelModel = new \App\Model\PersonelModel();
        $personel = $PersonelModel->find($personel_id);

        if (!$personel || empty($personel->ekip_no)) {
            return 0;
        }

        // Dönemdeki çalışma günlerini say (DISTINCT tarih)
        $sql = $this->db->prepare("
            SELECT COUNT(DISTINCT DATE(tarih)) as gun_sayisi
            FROM yapilan_isler 
            WHERE ekip_kodu = ? 
            AND DATE(tarih) BETWEEN ? AND ?
            AND silinme_tarihi IS NULL
        ");
        $sql->execute([$personel->ekip_no, $baslangic_tarihi, $bitis_tarihi]);
        $sonuc = $sql->fetch(PDO::FETCH_OBJ);

        return intval($sonuc->gun_sayisi ?? 0);
    }

    /**
     * Tek bir personelin maaşını hesaplar ve günceller
     * Parametrelere dayalı gelişmiş hesaplama
     */
    public function hesaplaMaas($bordro_personel_id)
    {
        // BordroParametreModel'i kullan
        $parametreModel = new BordroParametreModel();

        // Bordro kaydını ve personel detaylarını çek
        $sql = $this->db->prepare("
            SELECT bp.*, p.maas_tutari, p.maas_durumu, p.bes_kesintisi_varmi, p.sodexo, p.sgk_yapilan_firma, p.ise_giris_tarihi, p.isten_cikis_tarihi, bd.baslangic_tarihi, bd.bitis_tarihi
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

        $agirlikliBrutMaas = 0;
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

                    // Şubatı 30'a tamamlama vs (ticari ay mantığı - opsiyonel)
                    if (date('m', $donemBitisTs) == '02' && date('d', $hesapBitis) >= 28 && empty($g->bitis_tarihi)) {
                        $gecerliGun = 30 - round(($hesapBaslangic - $donemBaslangicTs) / (60 * 60 * 24));
                    }

                    // Günlük fiyatını bul ve çarp
                    $gunlukTutar = floatval($g->maas_tutari) / 30;
                    $agirlikliBrutMaas += ($gunlukTutar * $gecerliGun);
                    $toplamGecerliGun += $gecerliGun;

                    // Bu kaydın durumunu kaydet (Son döngüdeki geçerli olacak)
                    $maasDurumuRaw = $g->maas_durumu;
                    $maasDurumu = mb_strtolower(trim($maasDurumuRaw), 'UTF-8');

                    $isNetMaas = (stripos($maasDurumuRaw, 'net') !== false);
                    $isPrimUsulu = (stripos($maasDurumuRaw, 'Prim') !== false || stripos($maasDurumu, 'prim') !== false);
                }
            }

            // Toplam geçerli gün 30'u geçemez
            if ($toplamGecerliGun > 30)
                $toplamGecerliGun = 30;

            // Nominal Brüt Maaş (Daily wage hesabı için oranlanmamış tam aylık tutar)
            // Eğer 30 günün tamamı kapsanmıyorsa (kıst çalışma), ağırlık üzerinden 30 güne tamamlıyoruz
            $nominalBrutMaas = ($toplamGecerliGun > 0) ? ($agirlikliBrutMaas / $toplamGecerliGun * 30) : $agirlikliBrutMaas;

            $kayit->maas_tutari = round($agirlikliBrutMaas, 2);
            $kayit->maas_durumu = $maasDurumuRaw;
        } else {
            // Hiç geçmiş yoksa eski fallback mantığıyla maaş durumunu bul
            // $maasDurumuRaw ve $maasDurumu zaten yukarıda tanımlandı
            $nominalBrutMaas = floatval($kayit->maas_tutari ?? 0);
            $isNetMaas = (stripos($maasDurumuRaw, 'net') !== false);
            $isPrimUsulu = (stripos($maasDurumuRaw, 'Prim') !== false || stripos($maasDurumu, 'prim') !== false);
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
            $brutMaas = $parametreModel->getGenelAyar('asgari_ucret_brut', $donemTarihi) ?? 33030.00;
        }


        // Net maaş tahmini (sürekli kayıtların oran hesabı için - brütün %70'i)
        $netMaasTahmini = $brutMaas * 0.70;

        // ========== OTOMATİK KESİNTİ/EK ÖDEMELERİ TEMİZLE VE YENİDEN OLUŞTUR ==========
        // Önce tüm otomatik oluşturulan kayıtları soft-delete yap
        // Sonra fonksiyonlar güncel verilere göre yeniden oluşturacak
        // Manuel eklenen kayıtlar (kullanıcının elle eklediği) korunur

        // 1) Otomatik oluşturulan KESİNTİLERİ soft-delete
        //    - Sürekli kesintiler (ana_kesinti_id NOT NULL)
        //    - Avans kesintileri (tur = 'avans' ve açıklama [Avans] ile başlayan)
        //    - İcra kesintileri (tur = 'icra')
        //    - BES kesintileri (açıklama [BES] ile başlayan)
        //    NOT: izin_kesinti artık oluşturulmaz, ücretsiz izin doğrudan brüt maaştan düşülür
        $this->db->prepare("
            UPDATE personel_kesintileri 
            SET silinme_tarihi = NOW() 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
            AND (
                ana_kesinti_id IS NOT NULL
                OR tur = 'icra'
                OR (tur = 'avans' AND aciklama LIKE '[Avans]%')
                OR aciklama LIKE '[BES]%'
            )
        ")->execute([$kayit->personel_id, $kayit->donem_id]);

        // 2) Otomatik oluşturulan EK ÖDEMELERİ soft-delete  
        //    - Sürekli ek ödemeler (ana_odeme_id NOT NULL)
        //    - Puantaj ödemeleri (açıklama [Puantaj] ile başlayan)
        //    - Nöbet ödemeleri (açıklama [Nöbet] ile başlayan)
        //    - Kaçak kontrol primleri (açıklama [Kaçak Kontrol] ile başlayan)
        $this->db->prepare("
        UPDATE personel_ek_odemeler 
        SET silinme_tarihi = NOW() 
        WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
        AND (
            ana_odeme_id IS NOT NULL
            OR aciklama LIKE '[Puantaj]%'
            OR aciklama LIKE '[Nöbet]%'
            OR aciklama LIKE '[Kaçak Kontrol]%'
        )
    ")->execute([$kayit->personel_id, $kayit->donem_id]);

        // ========== SÜREKLİ KESİNTİ VE EK ÖDEMELERİ DÖNEME AKTAR ==========
        // Bu işlem, aktif sürekli kayıtları bordro dönemine tek seferlik olarak ekler
        $this->olusturSurekliKesintiler($kayit->personel_id, $kayit->donem_id, $donem, $brutMaas, $netMaasTahmini);
        $this->olusturSurekliEkOdemeler($kayit->personel_id, $kayit->donem_id, $donem, $brutMaas, $netMaasTahmini);

        // Puantaj (Yapılan İşler) Hesaplaması
        $this->olusturPuantajOdemeleri($kayit->personel_id, $kayit->donem_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

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

        // ========== ÜCRETSİZ İZİN HESAPLAMASI ==========
        // Ücretsiz izinler artık ayrı kesinti olarak oluşturulmaz.
        // Bunun yerine doğrudan brüt maaştan düşülür: Brüt = Günlük Ücret × (30 - ücretsiz izin günü)
        // Bu sayede çift düşme problemi ortadan kalkar.
        // Kesintiler yalnızca avans, ceza, icra gibi gerçek kesintiler için kullanılır.
        $ucretsizIzinGunu = $this->getUcretsizIzinGunuDirekt($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // ========== ÜCRETLİ İZİN BİLGİSİ ==========
        $ucretliIzinGunu = $this->getUcretliIzinGunu($kayit->personel_id, $kayit->baslangic_tarihi, $kayit->bitis_tarihi);

        // ========== ÇALIŞMA GÜNÜ HESAPLAMASI ==========
        // Mantık:
        // 1) İşe giriş tarihi dönem içindeyse: ayın_gün_sayısı - giriş_günü + 1 (takvim günü)
        // 2) Tam çalıştı + ücretsiz izin yok: 30 gün (ticari ay)
        // 3) Tam çalıştı + ücretsiz izin var: ayın_gün_sayısı - ücretsiz_izin_günü
        $donemBasTs = strtotime($kayit->baslangic_tarihi);
        $donemBitTs = strtotime($kayit->bitis_tarihi);
        $aydakiGunSayisi = date('t', $donemBasTs);

        $iseGirisDoneмIcinde = false;
        $istenCikisDoneмIcinde = false;

        if (!empty($kayit->ise_giris_tarihi)) {
            $iseGirisTs = strtotime($kayit->ise_giris_tarihi);
            if ($iseGirisTs > $donemBasTs) {
                $iseGirisDoneмIcinde = true;
            }
        }

        if (!empty($kayit->isten_cikis_tarihi)) {
            $istenCikisTs = strtotime($kayit->isten_cikis_tarihi);
            if ($istenCikisTs >= $donemBasTs && $istenCikisTs < $donemBitTs) {
                $istenCikisDoneмIcinde = true;
            }
        }

        if ($iseGirisDoneмIcinde && $istenCikisDoneмIcinde) {
            // Hem giriş hem çıkış ay içinde
            $gunlukBase = date('j', $istenCikisTs) - date('j', $iseGirisTs) + 1;
        } elseif ($iseGirisDoneмIcinde) {
            // Sadece giriş ay içinde: ayın kalan günleri
            $gunlukBase = $aydakiGunSayisi - date('j', $iseGirisTs) + 1;
        } elseif ($istenCikisDoneмIcinde) {
            // Sadece çıkış ay içinde: ayın başından çıkış gününe kadar
            $gunlukBase = date('j', $istenCikisTs);
        } elseif ($ucretsizIzinGunu > 0 || $ucretliIzinGunu > 0) {
            // Tam ay çalıştı ama izin var: takvim günü kullan
            $gunlukBase = $aydakiGunSayisi;
        } else {
            // Tam ay, izin yok: ticari 30 gün
            $gunlukBase = 30;
        }
        if ($gunlukBase < 0)
            $gunlukBase = 0;

        // Ücretsiz izin günü varsa brüt maaşı düşür (Günlük ücret × izin günü kadar)
        if ($isNetMaas || $maasDurumu === 'brüt') {
            // Net veya Brüt maaş tipi: toplam alacağı = (maaş / 30) * gün
            $fiiliCalismaGunuTemp = $gunlukBase - $ucretsizIzinGunu - $ucretliIzinGunu;
            if ($fiiliCalismaGunuTemp < 0)
                $fiiliCalismaGunuTemp = 0;
            $brutMaas = round(($nominalBrutMaas / 30) * $fiiliCalismaGunuTemp, 2);
            $ucretsizIzinDusumu = $nominalBrutMaas - $brutMaas; // Sadece bilgi amaçlı
            if ($ucretsizIzinDusumu < 0)
                $ucretsizIzinDusumu = 0;
        } else {
            $fiiliCalismaGunuTemp = $gunlukBase - $ucretsizIzinGunu - $ucretliIzinGunu;
            if ($fiiliCalismaGunuTemp < 0)
                $fiiliCalismaGunuTemp = 0;

            if ($fiiliCalismaGunuTemp < 30 && $nominalBrutMaas > 0) {
                $calismadigiGunler = 30 - $fiiliCalismaGunuTemp;
                $gunlukUcretHesap = $nominalBrutMaas / 30;
                $ucretsizIzinDusumu = round($gunlukUcretHesap * $calismadigiGunler, 2);
                $brutMaas = max(0, $nominalBrutMaas - $ucretsizIzinDusumu);
            } else {
                $ucretsizIzinDusumu = 0;
            }
        }

        // Çalışma günü sayısı (aylık varsayılan 26 gün) - BES hesabı için gerekli
        $calismaGunuSayisi = $parametreModel->getGenelAyar('calisma_gunu_sayisi', $donemTarihi) ?? 26;

        // ========== BES KESİNTİSİ ==========
        if (!$isNetMaas && isset($kayit->bes_kesintisi_varmi) && $kayit->bes_kesintisi_varmi === 'Evet') {
            // SGK Matrahını tahmin et (Ek ödemelerden gelen SGK matrahı ile)
            $tempEkOdemeler = $this->getDonemEkOdemeleriListe($kayit->personel_id, $kayit->donem_id);
            $tempSgkMatrahEkleri = 0;

            foreach ($tempEkOdemeler as $odeme) {
                $param = $parametreModel->getByKod($odeme->tur, $donemTarihi);
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
            $sgkIsciOrani = ($parametreModel->getGenelAyar('sgk_isci_orani', $donemTarihi) ?? 14) / 100;
            $issizlikIsciOrani = ($parametreModel->getGenelAyar('issizlik_isci_orani', $donemTarihi) ?? 1) / 100;
            $sgkIsverenOrani = ($parametreModel->getGenelAyar('sgk_isveren_orani', $donemTarihi) ?? 20.5) / 100;
            $issizlikIsverenOrani = ($parametreModel->getGenelAyar('issizlik_isveren_orani', $donemTarihi) ?? 2) / 100;
            $damgaVergisiOrani = ($parametreModel->getGenelAyar('damga_vergisi_orani', $donemTarihi) ?? 0.759) / 100;
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
        $kesintiDetaylari = [];

        // Her ek ödemeyi parametresine göre işle
        foreach ($ekOdemeler as $odeme) {
            $tutar = floatval($odeme->tutar);
            $parametre = $parametreModel->getByKod($odeme->tur, $donemTarihi);

            // Detay kaydı
            $detay = [
                'kod' => $odeme->tur,
                'tutar' => $tutar,
                'aciklama' => $odeme->aciklama ?? null
            ];

            if ($odeme->tur === 'mesai') {
                $toplamMesaiTutar += $tutar;
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
                    } else {
                        // Manuel/Sabit gün sayısı - ama izinleri düş
                        $varsayilanGun = intval($parametre->varsayilan_gun_sayisi ?? 30);
                        $ucretliIzinGunu = $this->getUcretliIzinGunu(
                            $kayit->personel_id,
                            $kayit->baslangic_tarihi,
                            $kayit->bitis_tarihi
                        );
                        // Ücretsiz izin gün sayısını da al
                        $ucretsizIzinGunu = $this->getUcretsizIzinGunuDirekt(
                            $kayit->personel_id,
                            $kayit->baslangic_tarihi,
                            $kayit->bitis_tarihi
                        );
                        $gunSayisi = max(0, $varsayilanGun - $ucretliIzinGunu - $ucretsizIzinGunu);
                    }

                    // Toplam tutarı hesapla
                    $toplamTutar = 0;
                    if (strpos($parametre->hesaplama_tipi, 'gunluk_') === 0) {
                        // Günlük bazlı: Tutar = Günlük Tutar × Gün Sayısı
                        $gunlukTutar = floatval($parametre->gunluk_tutar);
                        $toplamTutar = $gunlukTutar * $gunSayisi;
                        $detay['gunluk_tutar'] = $gunlukTutar;
                    } else {
                        // Aylık (Çalışılan Gün) bazlı: Tutar = (Aylık Tutar / 30) * Gün Sayısı
                        // Burada $tutar, personelin ek ödemesinde tanımlı olan aylık tutardır
                        $toplamTutar = ($tutar / 30) * $gunSayisi;
                        $detay['aylik_tutar'] = $tutar;
                    }

                    $detay['gun_sayisi'] = $gunSayisi;
                    $detay['hesaplanan_tutar'] = round($toplamTutar, 2);

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
                    break;
            }

            $ekOdemeDetaylari[] = $detay;
        }

        // Her kesintiyi işle
        // NOT: Ücretsiz izin kesintisi artık burada yok, doğrudan brüt maaştan düşüldü
        $digerKesintiler = 0;
        $toplamKesinti = 0;
        $oranliKesintiler = []; // Net üzerinden oranlı kesintiler (İcra vb.)

        foreach ($kesintiler as $kesinti) {
            $tutar = floatval($kesinti->tutar);
            $parametre = $parametreModel->getByKod($kesinti->tur, $donemTarihi);
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
                $hesaplananTutar = ($tutar / 30) * $gunSayisi;
                $tutar = $hesaplananTutar;
                $detay['tutar'] = round($tutar, 2);
                $detay['gun_sayisi'] = $gunSayisi;
                $detay['aylik_tutar'] = floatval($kesinti->tutar);
            }

            $toplamKesinti += $tutar;
            $digerKesintiler += $tutar;

            $kesintiDetaylari[] = $detay;
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
            // Prim Usülü / Net: Hakediş = brüt maaş + toplam ek ödemeler
            // Ücretsiz izin zaten brüt maaştan düşülmüş durumda
            $hakedisNet = $brutMaas + $toplamEkOdeme;
        } else {
            // Brüt: Hakediş = brüt maaş - yasal kesintiler + ek ödemeler
            // Ücretsiz izin zaten brüt maaştan düşülmüş durumda
            $hakedisNet = $brutMaas
                - $sgkIsci - $issizlikIsci - $gelirVergisi - $damgaVergisi
                + $netEkOdemeler
                + $brutEkOdemeler;
        }

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

        // ========== ÖDEME DAĞILIMI ÖN HAZIRLIK ==========
        // Fiili çalışma günü (30 günden ücretsiz ve ücretli izinler düşülmüş)
        // NOT: Gün tüm maaş tipleri için düşer (banka/sodexo oranlaması için gerekli)
        // Prim Usülü'de fark: toplam alacağı gün bazlı düşmez, ama banka/sodexo düşer
        $fiiliCalismaGunu = $gunlukBase - $ucretsizIzinGunu - $ucretliIzinGunu;
        if ($fiiliCalismaGunu < 0)
            $fiiliCalismaGunu = 0;

        // Asgari ücret net tutarını al
        $asgariUcretNet = $parametreModel->getGenelAyar('asgari_ucret_net', $donemTarihi) ?? 17002.12;

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

            // Bankaya yatacak baz (asgari ücret bazlı hesaplama için)
            $bankaYatacakBaz = $asgariUcretNet;
            if ($fiiliCalismaGunu < 30) {
                $bankaYatacakBaz = ($asgariUcretNet / 30) * $fiiliCalismaGunu;
            }

            $toplamIcraBudget = 0;
            if ($firstHTip === 'asgari_oran_net') {
                // Net asgari ücretin yüzdesi (bankaya yatacak baz üzerinden)
                $oranKullan = ($firstOran > 0) ? $firstOran : 25;
                $toplamIcraBudget = round($bankaYatacakBaz * ($oranKullan / 100), 2);
            } elseif ($firstHTip === 'oran_net') {
                // Net ücretin (toplam alacağı) yüzdesi
                $oranKullan = ($firstOran > 0) ? $firstOran : 25;
                $toplamIcraBudget = round($hakedisNet * ($oranKullan / 100), 2);
            } else {
                // Sabit tutar: tüm icra dosyalarının aylik_kesinti_tutari toplamı
                foreach ($icraDetaylar as $d) {
                    $toplamIcraBudget += floatval($d['icraData']->aylik_kesinti_tutari);
                }
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
                $this->db->prepare("UPDATE personel_kesintileri SET tutar = ?, updated_at = NOW() WHERE id = ?")
                    ->execute([$tutar, $kesinti->id]);
            }
        }

        // İcra kesintisini bul (hesaplanmış detaylardan)
        $icraKesintisi = 0;
        foreach ($kesintiDetaylari as $kd) {
            if ($kd['kod'] === 'icra') {
                $icraKesintisi += floatval($kd['tutar']);
            }
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

        // Sodexo tutarını fiili çalışma gününe göre oranla (30 gün üzerinden)
        // Eğer manuel olarak güncellenmişse veriyi olduğu gibi al
        if (isset($kayit->sodexo_manuel) && $kayit->sodexo_manuel == 1) {
            $sodexoOdemesi = floatval($kayit->sodexo_odemesi ?? 0);
        } else {
            $aylikSodexo = floatval($kayit->sodexo ?? 0);
            $sodexoOdemesi = ($aylikSodexo / 30) * $fiiliCalismaGunu;
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
            // Ancak net maaş - sodexo'dan büyük olamaz
            $bankaIcinMaksimum = max(0, $toplamPrim - $sodexoOdemesi);
            $bankaBaz = min($bankaYatacakMinimum, $bankaIcinMaksimum);

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
            // Ancak net maaş - sodexo'dan büyük olamaz
            $bankaIcinMaksimum = max(0, $netMaas - $sodexoOdemesi);
            $bankaBaz = min($bankaYatacakMinimum, $bankaIcinMaksimum);

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
            // Ancak net maaş - sodexo'dan büyük olamaz
            $bankaIcinMaksimum = max(0, $netMaas - $sodexoOdemesi);
            $bankaBaz = min($bankaYatacakMinimum, $bankaIcinMaksimum);

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
                'ucretsiz_izin_gunu' => $ucretsizIzinGunu,
                'ucretsiz_izin_dusumu' => round($ucretsizIzinDusumu, 2),
                'ucretli_izin_gunu' => $ucretliIzinGunu,
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
            'calisan_gun' => $gunlukBase - $ucretsizIzinGunu,
            'sodexo_odemesi' => round($sodexoOdemesi, 2),
            'banka_odemesi' => round($bankaOdemesi, 2),
            'elden_odeme' => round($eldenOdeme, 2),
            'kumulatif_matrah' => round($yeniKumulatifMatrah, 2),
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
            return $this->hesaplaMaas($bp->id);
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
        $sql = $this->db->prepare("
            SELECT pk.*, pi.dosya_no, pi.icra_dairesi
            FROM personel_kesintileri pk
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            WHERE pk.personel_id = ? AND pk.donem_id = ? AND pk.silinme_tarihi IS NULL
              AND pk.durum = 'onaylandi'
            ORDER BY pk.olusturma_tarihi DESC
        ");
        $sql->execute([$personel_id, $donem_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
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
     * Personelin dönemdeki tüm ek ödeme kayıtlarını getirir (detaylı liste için)
     */
    public function getDonemEkOdemeleriListe($personel_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT peo.*,bd.kapali_mi,
            (SELECT etiket FROM bordro_parametreleri bp WHERE bp.kod = peo.tur AND firma_id = ? LIMIT 1) as etiket
            FROM personel_ek_odemeler peo
            LEFT JOIN bordro_donemi bd ON peo.donem_id = bd.id
            WHERE peo.personel_id = ? AND peo.donem_id = ? AND peo.silinme_tarihi IS NULL
            
            ORDER BY peo.created_at DESC
        ");
        $firma_id = $_SESSION["firma_id"] ?? 0;
        $sql->execute([$firma_id, $personel_id, $donem_id]);
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
                   p.sgk_yapilan_firma
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL $idFilter
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
