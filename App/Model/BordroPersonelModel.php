<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class BordroPersonelModel extends Model
{
    protected $table = 'bordro_personel';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Belirli bir dönemdeki tüm personelleri getirir
     */
    public function getPersonellerByDonem($donem_id)
    {
        $sql = $this->db->prepare("
            SELECT bp.*, p.adi_soyadi, p.tc_kimlik_no, p.departman, p.gorev, 
                   p.ise_giris_tarihi, p.isten_cikis_tarihi, p.maas_tutari,
                   p.cep_telefonu, p.resim_yolu
            FROM {$this->table} bp
            INNER JOIN personel p ON bp.personel_id = p.id
            WHERE bp.donem_id = ? AND bp.silinme_tarihi IS NULL
            ORDER BY p.adi_soyadi ASC
        ");
        $sql->execute([$donem_id]);
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
        // Önce mevcut dönemdeki personelleri al (tekrar eklemesini önlemek için)
        $existingPersoneller = $this->getPersonellerByDonem($donem_id);
        $existingIds = array_column(
            array_map(function ($p) {
                return (array) $p;
            }, $existingPersoneller),
            'personel_id'
        );

        // Uygun personelleri bul
        // İşten çıkış tarihi: NULL, '0000-00-00', boş string veya dönem başlangıcından büyük/eşit olanlar
        $sql = $this->db->prepare("
            SELECT id, adi_soyadi, ise_giris_tarihi, isten_cikis_tarihi 
            FROM personel 
            WHERE aktif_mi = 1
            AND (
                ise_giris_tarihi IS NULL 
                OR ise_giris_tarihi = ''
                OR ise_giris_tarihi = '0000-00-00'
                OR ise_giris_tarihi <= :bitis_tarihi
            )
            AND (
                isten_cikis_tarihi IS NULL 
                OR isten_cikis_tarihi = ''
                OR isten_cikis_tarihi = '0000-00-00'
                OR isten_cikis_tarihi >= :baslangic_tarihi
            )
        ");
        $sql->bindParam(':baslangic_tarihi', $baslangic_tarihi);
        $sql->bindParam(':bitis_tarihi', $bitis_tarihi);
        $sql->execute();
        $uygunPersoneller = $sql->fetchAll(PDO::FETCH_OBJ);

        $eklenenSayisi = 0;

        foreach ($uygunPersoneller as $personel) {
            // Zaten eklenmişse atla
            if (in_array($personel->id, $existingIds)) {
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
                toplam_kesinti = :toplam_kesinti,
                toplam_ek_odeme = :toplam_ek_odeme,
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
        $sql->bindParam(':toplam_kesinti', $hesaplamaData['toplam_kesinti']);
        $sql->bindParam(':toplam_ek_odeme', $hesaplamaData['toplam_ek_odeme']);

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
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
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
            FROM personel_ek_odemeleri 
            WHERE personel_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
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
            SELECT bp.*, bd.donem_adi, bd.yil, bd.ay
            FROM {$this->table} bp
            INNER JOIN bordro_donemleri bd ON bp.donem_id = bd.id
            WHERE bp.personel_id = ? AND bp.silinme_tarihi IS NULL
            ORDER BY bd.yil DESC, bd.ay DESC
        ");
        $sql->execute([$personel_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin toplam kazanç bilgilerini getirir (Dashboard için)
     */
    public function getPersonelFinansalOzet($personel_id)
    {
        $sql = $this->db->prepare("
            SELECT 
                SUM(net_maas) as toplam_hakedis,
                SUM(CASE WHEN durum = 'odendi' THEN net_maas ELSE 0 END) as alinan_odeme
            FROM {$this->table}
            WHERE personel_id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$personel_id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }
}
