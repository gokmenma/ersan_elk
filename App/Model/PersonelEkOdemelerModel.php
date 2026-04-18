<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelEkOdemelerModel extends Model
{
    protected $table = 'personel_ek_odemeler';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin tüm ek ödemelerini getirir (listeleme için)
     * Sürekli ödemeler için ana_odeme_id NULL olanları gösterir
     */
    public function getPersonelEkOdemeler($personel_id, $filters = [])
    {
        $actualOnly = $filters['actual_only'] ?? false;
        
        $where = "peo.personel_id = ? AND peo.silinme_tarihi IS NULL";
        if ($actualOnly) {
            // Sadece gerçek tutarları getir (tek seferlik kayıtlar)
            $where .= " AND peo.tekrar_tipi = 'tek_sefer'";
        } else {
            // UI görünümü: Ana tanımları getir
            $where .= " AND peo.ana_odeme_id IS NULL";
        }
        
        $params = [$personel_id];
        $mode = $filters['filter_ek_mode'] ?? 'donem';

        if ($mode === 'tarih') {
            // Başlangıç Tarihi Filtresi
            if (!empty($filters['filter_ek_baslangic'])) {
                $baslangic = $filters['filter_ek_baslangic'];
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $baslangic)) {
                    $baslangic = \DateTime::createFromFormat('d.m.Y', $baslangic)->format('Y-m-d');
                }
                $where .= " AND peo.tarih >= ?";
                $params[] = $baslangic;
            }

            // Bitiş Tarihi Filtresi
            if (!empty($filters['filter_ek_bitis'])) {
                $bitis = $filters['filter_ek_bitis'];
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $bitis)) {
                    $bitis = \DateTime::createFromFormat('d.m.Y', $bitis)->format('Y-m-d');
                }
                $where .= " AND peo.tarih <= ?";
                $params[] = $bitis;
            }
        } elseif ($mode === 'donem') {
            // Dönem Filtresi
            if (!empty($filters['filter_ek_donem'])) {
                $donem_id = $filters['filter_ek_donem'];
                
                if ($actualOnly) {
                    // Sadece o döneme ait kayıtları getir
                    $where .= " AND peo.donem_id = ?";
                    $params[] = $donem_id;
                } else {
                    // Dönem tarihlerini al
                    $donemQuery = $this->db->prepare("SELECT baslangic_tarihi, bitis_tarihi FROM bordro_donemi WHERE id = ?");
                    $donemQuery->execute([$donem_id]);
                    $donemInfo = $donemQuery->fetch(PDO::FETCH_OBJ);
                    $donemBas = $donemInfo->baslangic_tarihi ?? '2000-01-01';
                    $donemBit = $donemInfo->bitis_tarihi ?? '2099-12-31';

                    $where .= " AND (
                        (peo.tekrar_tipi = 'tek_sefer' AND peo.donem_id = ?) 
                        OR 
                        (peo.tekrar_tipi = 'surekli' AND EXISTS (
                            SELECT 1 FROM bordro_donemi bd2 
                            WHERE bd2.id = ? 
                            AND peo.baslangic_donemi <= ? 
                            AND (peo.bitis_donemi IS NULL OR peo.bitis_donemi >= ?)
                        ))
                    )";
                    $params[] = $donem_id;
                    $params[] = $donem_id;
                    $params[] = $donemBit;
                    $params[] = $donemBas;
                }
            }
        } elseif ($mode === 'ay_yil') {
            // Ay-Yıl Filtresi
            if (!empty($filters['filter_ek_ay_yil'])) {
                $where .= " AND DATE_FORMAT(peo.tarih, '%Y-%m') = ?";
                $params[] = $filters['filter_ek_ay_yil'];
            }
        } elseif ($mode === 'yil') {
            // Yıl Filtresi
            if (!empty($filters['filter_ek_yil'])) {
                $where .= " AND YEAR(COALESCE(peo.tarih, peo.created_at)) = ?";
                $params[] = $filters['filter_ek_yil'];
            }
        }

        $sql = $this->db->prepare("
            SELECT peo.*, bp.etiket as parametre_adi, bp.kod as parametre_kodu, bd.donem_adi, bd.kapali_mi,
                   p.adi_soyadi as kayit_yapan_ad_soyad
            FROM {$this->table} peo
            LEFT JOIN bordro_parametreleri bp ON peo.parametre_id = bp.id
            LEFT JOIN bordro_donemi bd ON peo.donem_id = bd.id
            LEFT JOIN personel p ON peo.kayit_yapan = p.id
            WHERE {$where}
            ORDER BY peo.tekrar_tipi DESC, peo.baslangic_donemi DESC, peo.donem_id DESC, peo.created_at DESC
        ");
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin aktif sürekli ek ödemelerini getirir
     * @param int $personel_id
     * @param string $donem YYYY-MM formatında dönem (dönem başlangıç tarihi olarak kullanılır)
     * @return array
     */
    public function getAktifSurekliOdemeler($personel_id, $donem)
    {
        // Dönemden tarih oluştur
        $donemBas = $donem . '-01';
        $donemBit = date('Y-m-t', strtotime($donemBas));

        $sql = $this->db->prepare("
            SELECT peo.*, bp.etiket as parametre_adi, bp.kod as parametre_kodu, bp.hesaplama_tipi as param_hesaplama_tipi
            FROM {$this->table} peo
            LEFT JOIN bordro_parametreleri bp ON peo.parametre_id = bp.id
            WHERE peo.personel_id = ? 
              AND peo.tekrar_tipi = 'surekli'
              AND peo.aktif = 1
              AND peo.silinme_tarihi IS NULL
              AND peo.ana_odeme_id IS NULL
              AND peo.baslangic_donemi <= ?
              AND (peo.bitis_donemi IS NULL OR peo.bitis_donemi >= ?)
            ORDER BY peo.created_at ASC
        ");
        $sql->execute([$personel_id, $donemBit, $donemBas]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Dönem için sürekli ödemeden otomatik oluşturulan kayıt var mı kontrol eder
     */
    public function donemdeKaynakKayitVarMi($ana_odeme_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as adet 
            FROM {$this->table} 
            WHERE ana_odeme_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$ana_odeme_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ)->adet > 0;
    }

    /**
     * Sürekli ödemeden dönem için otomatik ek ödeme oluşturur
     * @param object $surekliOdeme Sürekli ödeme kaydı
     * @param int $donem_id Dönem ID
     * @param float $tutar Hesaplanan tutar (oran bazlı ise hesaplanmış tutar)
     * @return int|string|bool Eklenen kayıt ID'si veya false
     */
    public function olusturDonemOdemesi($surekliOdeme, $donem_id, $tutar)
    {
        // Bu dönem için zaten kayıt var mı kontrol et (Silinmişler dahil)
        $sql = $this->db->prepare("
            SELECT id, durum, silinme_tarihi FROM {$this->table} 
            WHERE ana_odeme_id = ? AND donem_id = ?
            ORDER BY id DESC LIMIT 1
        ");
        $sql->execute([$surekliOdeme->id, $donem_id]);
        $mevcut = $sql->fetch(PDO::FETCH_OBJ);

        if ($mevcut) {
            // Kayıt varsa güncelle ve silinmişse geri getir
            $updateSql = "UPDATE {$this->table} SET 
                silinme_tarihi = NULL, 
                durum = 'onaylandi', 
                tutar = ?, 
                aciklama = ?,
                hesaplama_tipi = ?,
                tur = ?,
                updated_at = NOW() 
                WHERE id = ?";

            $updateStmt = $this->db->prepare($updateSql);
            $updateStmt->execute([$tutar, $surekliOdeme->aciklama . ' (Otomatik)', $surekliOdeme->hesaplama_tipi, $surekliOdeme->tur, $mevcut->id]);

            return true;
        }

        $data = [
            'personel_id' => $surekliOdeme->personel_id,
            'donem_id' => $donem_id,
            'tur' => $surekliOdeme->tur,
            'tekrar_tipi' => 'tek_sefer', // Oluşturulan kayıt tek seferlik olarak işaretlenir
            'hesaplama_tipi' => $surekliOdeme->hesaplama_tipi,
            'tutar' => $tutar,
            'oran' => $surekliOdeme->oran,
            'aciklama' => $surekliOdeme->aciklama . ' (Otomatik)',
            'parametre_id' => $surekliOdeme->parametre_id,
            'tarih' => $surekliOdeme->tarih,
            'ana_odeme_id' => $surekliOdeme->id, // Ana kayıt referansı
            'aktif' => 1
        ];

        return $this->saveWithAttr($data);
    }

    /**
     * Sürekli ödemeyi pasife alır (sonlandırır)
     */
    public function sonlandirSurekliOdeme($id, $bitis_donemi = null)
    {
        $bitis = $bitis_donemi ?? date('Y-m');
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET bitis_donemi = ?, aktif = 0, updated_at = NOW()
            WHERE id = ? AND tekrar_tipi = 'surekli'
        ");
        return $sql->execute([$bitis, $id]);
    }

    /**
     * Ek ödeme detayını getirir
     */
    public function getEkOdeme($id)
    {
        $sql = $this->db->prepare("
            SELECT peo.*, bp.etiket as parametre_adi, bp.kod as parametre_kodu
            FROM {$this->table} peo
            LEFT JOIN bordro_parametreleri bp ON peo.parametre_id = bp.id
            WHERE peo.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Ek ödeme günceller
     */
    public function updateEkOdeme($id, $data)
    {
        $sets = [];
        $params = [];

        foreach ($data as $key => $value) {
            $sets[] = "{$key} = ?";
            $params[] = $value;
        }

        $sets[] = "updated_at = NOW()";
        $params[] = $id;

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET " . implode(', ', $sets) . "
            WHERE id = ?
        ");
        return $sql->execute($params);
    }

    /**
     * Ana ödemeden oluşturulan tüm dönem kayıtlarını getirir
     */
    public function getDonemKayitlari($ana_odeme_id)
    {
        $sql = $this->db->prepare("
            SELECT peo.*, bd.donem_adi
            FROM {$this->table} peo
            LEFT JOIN bordro_donemi bd ON peo.donem_id = bd.id
            WHERE peo.ana_odeme_id = ? AND peo.silinme_tarihi IS NULL
            ORDER BY peo.donem_id DESC
        ");
        $sql->execute([$ana_odeme_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
    /**
     * Raporlama için döneme ait ek ödemeleri getirir
     */
    public function getDonemEkOdemelerRaporu($donem_id, $tur = null)
    {
        $params = [$donem_id];
        $turCondition = "";

        if (!empty($tur)) {
            $turCondition = " AND peo.tur = ? ";
            $params[] = $tur;
        }

        $sql = $this->db->prepare("
            SELECT peo.*, p.adi_soyadi, p.tc_kimlik_no, p.departman,
                   bp.etiket as parametre_adi
            FROM {$this->table} peo
            INNER JOIN personel p ON peo.personel_id = p.id
            LEFT JOIN bordro_parametreleri bp ON peo.parametre_id = bp.id
            WHERE peo.donem_id = ? 
              AND peo.silinme_tarihi IS NULL
              $turCondition
            ORDER BY p.adi_soyadi ASC, peo.tutar DESC
        ");

        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
