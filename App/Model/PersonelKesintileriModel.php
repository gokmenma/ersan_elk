<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class PersonelKesintileriModel extends Model
{
    protected $table = 'personel_kesintileri';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin tüm kesintilerini getirir (listeleme için)
     * Sürekli kesintiler için ana_kesinti_id NULL olanları gösterir
     */
    public function getPersonelKesintileri($personel_id, $filters = [])
    {
        $where = "pk.personel_id = ? AND pk.silinme_tarihi IS NULL AND pk.ana_kesinti_id IS NULL";
        $params = [$personel_id];
        $mode = $filters['filter_kesinti_mode'] ?? 'donem';

        if ($mode === 'tarih') {
            if (!empty($filters['filter_kesinti_baslangic'])) {
                $baslangic = $filters['filter_kesinti_baslangic'];
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $baslangic)) {
                    $baslangic = \DateTime::createFromFormat('d.m.Y', $baslangic)->format('Y-m-d');
                }
                $where .= " AND pk.tarih >= ?";
                $params[] = $baslangic;
            }
            if (!empty($filters['filter_kesinti_bitis'])) {
                $bitis = $filters['filter_kesinti_bitis'];
                if (preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $bitis)) {
                    $bitis = \DateTime::createFromFormat('d.m.Y', $bitis)->format('Y-m-d');
                }
                $where .= " AND pk.tarih <= ?";
                $params[] = $bitis;
            }
        } elseif ($mode === 'donem') {
            if (!empty($filters['filter_kesinti_donem'])) {
                $donem_id = $filters['filter_kesinti_donem'];
                $where .= " AND (
                    (pk.tekrar_tipi = 'tek_sefer' AND pk.donem_id = ?) 
                    OR 
                    (pk.tekrar_tipi = 'surekli' AND EXISTS (
                        SELECT 1 FROM bordro_donemi bd2 
                        WHERE bd2.id = ? 
                        AND pk.baslangic_donemi <= bd2.bitis_tarihi 
                        AND (pk.bitis_donemi IS NULL OR pk.bitis_donemi >= bd2.baslangic_tarihi)
                    ))
                )";
                $params[] = $donem_id;
                $params[] = $donem_id;
            }
        } elseif ($mode === 'ay_yil') {
            if (!empty($filters['filter_kesinti_ay_yil'])) {
                $where .= " AND DATE_FORMAT(pk.tarih, '%Y-%m') = ?";
                $params[] = $filters['filter_kesinti_ay_yil'];
            }
        } elseif ($mode === 'yil') {
            if (!empty($filters['filter_kesinti_yil'])) {
                $where .= " AND YEAR(COALESCE(pk.tarih, pk.olusturma_tarihi)) = ?";
                $params[] = $filters['filter_kesinti_yil'];
            }
        }

        $sql = $this->db->prepare("
            SELECT pk.*, pi.dosya_no, pi.icra_dairesi, bp.etiket as parametre_adi, bp.kod as parametre_kodu, bd.donem_adi, bd.kapali_mi,
                                     COALESCE(pk.durum, 'beklemede') as durum,
                                     ky.adi_soyadi as kayit_yapan_ad_soyad
            FROM {$this->table} pk
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            LEFT JOIN bordro_parametreleri bp ON pk.parametre_id = bp.id
            LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
            LEFT JOIN personel ky ON pk.kayit_yapan = ky.id
            WHERE {$where}
            ORDER BY pk.tekrar_tipi DESC, pk.baslangic_donemi DESC, pk.donem_id DESC, pk.olusturma_tarihi DESC
        ");
        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Personelin aktif sürekli kesintilerini getirir
     * @param int $personel_id
     * @param string $donem YYYY-MM formatında dönem (dönem başlangıç tarihi olarak kullanılır)
     * @return array
     */
    public function getAktifSurekliKesintiler($personel_id, $donem)
    {
        // Dönemden tarih oluştur (ayın ilk günü)
        $donemTarih = $donem . '-01';

        $sql = $this->db->prepare("
            SELECT pk.*, bp.etiket as parametre_adi, bp.kod as parametre_kodu, bp.hesaplama_tipi as param_hesaplama_tipi
            FROM {$this->table} pk
            LEFT JOIN bordro_parametreleri bp ON pk.parametre_id = bp.id
            WHERE pk.personel_id = ? 
              AND pk.tekrar_tipi = 'surekli'
              AND pk.aktif = 1
              AND pk.silinme_tarihi IS NULL
              AND pk.ana_kesinti_id IS NULL
              AND pk.baslangic_donemi <= ?
              AND (pk.bitis_donemi IS NULL OR pk.bitis_donemi >= ?)
            ORDER BY pk.olusturma_tarihi ASC
        ");
        $sql->execute([$personel_id, $donemTarih, $donemTarih]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Dönem için sürekli kesintiden otomatik oluşturulan kayıt var mı kontrol eder
     */
    public function donemdeKaynakKayitVarMi($ana_kesinti_id, $donem_id)
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as adet 
            FROM {$this->table} 
            WHERE ana_kesinti_id = ? AND donem_id = ? AND silinme_tarihi IS NULL
        ");
        $sql->execute([$ana_kesinti_id, $donem_id]);
        return $sql->fetch(PDO::FETCH_OBJ)->adet > 0;
    }

    /**
     * Sürekli kesintiden dönem için otomatik kesinti oluşturur
     * @param object $surekliKesinti Sürekli kesinti kaydı
     * @param int $donem_id Dönem ID
     * @param float $tutar Hesaplanan tutar (oran bazlı ise hesaplanmış tutar)
     * @return int|string|bool Eklenen kayıt ID'si veya false
     */
    public function olusturDonemKesintisi($surekliKesinti, $donem_id, $tutar)
    {
        // Bu dönem için zaten kayıt var mı kontrol et
        if ($this->donemdeKaynakKayitVarMi($surekliKesinti->id, $donem_id)) {
            return false; // Zaten mevcut
        }

        $data = [
            'personel_id' => $surekliKesinti->personel_id,
            'donem_id' => $donem_id,
            'tur' => $surekliKesinti->tur,
            'tekrar_tipi' => 'tek_sefer', // Oluşturulan kayıt tek seferlik olarak işaretlenir
            'hesaplama_tipi' => $surekliKesinti->hesaplama_tipi,
            'tutar' => $tutar,
            'oran' => $surekliKesinti->oran,
            'aciklama' => $surekliKesinti->aciklama . ' (Otomatik)',
            'parametre_id' => $surekliKesinti->parametre_id,
            'icra_id' => $surekliKesinti->icra_id,
            'ana_kesinti_id' => $surekliKesinti->id, // Ana kayıt referansı
            'aktif' => 1
        ];

        return $this->saveWithAttr($data);
    }

    /**
     * Sürekli kesintiyi pasife alır (sonlandırır)
     */
    public function sonlandirSurekliKesinti($id, $bitis_donemi = null)
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
     * Kesinti detayını getirir
     */
    public function getKesinti($id)
    {
        $sql = $this->db->prepare("
            SELECT pk.*, pi.dosya_no, pi.icra_dairesi, bp.etiket as parametre_adi, bp.kod as parametre_kodu
            FROM {$this->table} pk
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            LEFT JOIN bordro_parametreleri bp ON pk.parametre_id = bp.id
            WHERE pk.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Kesinti günceller
     */
    public function updateKesinti($id, $data)
    {
        // Mevcut durumu kontrol et
        $mevcut = $this->getKesinti($id);
        if (!$mevcut) {
            throw new \Exception('Kesinti bulunamadı.');
        }

        // Eğer mevcut durum 'onaylandi' ve yeni durum 'beklemede' ise engelle
        if (
            isset($mevcut->durum) && $mevcut->durum === 'onaylandi' &&
            isset($data['durum']) && $data['durum'] === 'beklemede'
        ) {
            throw new \Exception('Onaylanmış bir kesinti tekrar beklemede durumuna alınamaz.');
        }

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
     * Ana kesintiden oluşturulan tüm dönem kayıtlarını getirir
     */
    public function getDonemKayitlari($ana_kesinti_id)
    {
        $sql = $this->db->prepare("
            SELECT pk.*, bd.donem_adi
            FROM {$this->table} pk
            LEFT JOIN bordro_donemi bd ON pk.donem_id = bd.id
            WHERE pk.ana_kesinti_id = ? AND pk.silinme_tarihi IS NULL
            ORDER BY pk.donem_id DESC
        ");
        $sql->execute([$ana_kesinti_id]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Raporlama için döneme ait kesintileri getirir
     */
    public function getDonemKesintileriRaporu($donem_id, $tur = null)
    {
        $params = [$donem_id];
        $turCondition = "";

        if (!empty($tur)) {
            $turCondition = " AND pk.tur = ? ";
            $params[] = $tur;
        }

        $sql = $this->db->prepare("
            SELECT pk.*, p.adi_soyadi, p.tc_kimlik_no, p.departman,
                   pi.dosya_no, pi.icra_dairesi, bp.etiket as parametre_adi
            FROM {$this->table} pk
            INNER JOIN personel p ON pk.personel_id = p.id
            LEFT JOIN personel_icralari pi ON pk.icra_id = pi.id
            LEFT JOIN bordro_parametreleri bp ON pk.parametre_id = bp.id
            WHERE pk.donem_id = ? 
              AND pk.silinme_tarihi IS NULL
              $turCondition
            ORDER BY p.adi_soyadi ASC, pk.tutar DESC
        ");

        $sql->execute($params);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }
}
