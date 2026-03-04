<?php

namespace App\Model;

use App\Model\Model;
use PDO;

/**
 * Personel Hareketleri Model
 * Saha personelinin görev giriş-çıkış konum takibi
 */
class PersonelHareketleriModel extends Model
{
    protected $table = 'personel_hareketleri';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Personelin bugün açık (bitirilmemiş) görevi var mı kontrol eder
     * Eğer görev başlangıç günü geçmişse veya aynı gün 23:50'yi geçmişse otomatik sonlandırır
     * @param int $personel_id
     * @return object|null Açık görev varsa BASLA kaydını döner, yoksa null
     */
    public function getAcikGorev($personel_id)
    {
        // Son BASLA kaydını bul (bitirilmemiş)
        $sql = "SELECT ph.* 
                FROM {$this->table} ph
                WHERE ph.personel_id = :personel_id 
                AND ph.islem_tipi = 'BASLA'
                AND ph.silinme_tarihi IS NULL
                AND NOT EXISTS (
                    SELECT 1 FROM {$this->table} ph2 
                    WHERE ph2.personel_id = ph.personel_id 
                    AND ph2.islem_tipi = 'BITIR' 
                    AND ph2.zaman > ph.zaman
                    AND ph2.silinme_tarihi IS NULL
                )
                ORDER BY ph.zaman DESC
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':personel_id' => $personel_id]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$result) {
            return null;
        }

        // Otomatik sonlandırma kontrolü (23:50 kuralı)
        $baslangicZamani = new \DateTime($result->zaman);
        $baslangicTarihi = $baslangicZamani->format('Y-m-d');
        $bugun = date('Y-m-d');
        $simdikiSaat = date('H:i');

        // Başlangıç günü bugünden önceyse VEYA bugün ama saat 23:50'yi geçmişse
        if ($baslangicTarihi < $bugun || ($baslangicTarihi === $bugun && $simdikiSaat >= '23:50')) {
            // Otomatik sonlandır - 23:50 olarak kaydet
            $this->otomatikSonlandir($personel_id, $result->id, $baslangicTarihi);
            return null;
        }

        return $result;
    }

    /**
     * Görevi otomatik olarak 23:50'de sonlandırır
     * @param int $personel_id
     * @param int $basla_id Başlangıç kaydının ID'si
     * @param string $tarih Y-m-d formatında tarih
     * @return bool
     */
    public function otomatikSonlandir($personel_id, $basla_id, $tarih)
    {
        // Bitiş zamanı - 23:50 olarak
        $bitisZamani = $tarih . ' 23:50:00';

        // Önce bu tarihte zaten BITIR kaydı var mı kontrol et
        $checkSql = "SELECT id FROM {$this->table} 
                     WHERE personel_id = :personel_id 
                     AND islem_tipi = 'BITIR'
                     AND DATE(zaman) = :tarih
                     AND silinme_tarihi IS NULL
                     LIMIT 1";

        $checkStmt = $this->db->prepare($checkSql);
        $checkStmt->execute([':personel_id' => $personel_id, ':tarih' => $tarih]);

        if ($checkStmt->fetch()) {
            return true; // Zaten sonlandırılmış
        }

        // Başlangıç kaydından firma_id'yi al
        $firmaStmt = $this->db->prepare("SELECT firma_id FROM {$this->table} WHERE id = :id");
        $firmaStmt->execute([':id' => $basla_id]);
        $firma = $firmaStmt->fetch(\PDO::FETCH_OBJ);
        $firma_id = $firma ? $firma->firma_id : null;

        // aciklama kolonu varsa onu kullan, yoksa basit insert yap
        try {
            $sql = "INSERT INTO {$this->table} 
                    (personel_id, islem_tipi, zaman, aciklama, firma_id)
                    VALUES (:personel_id, 'BITIR', :bitis_zamani, 'Otomatik sonlandırıldı (23:50 kuralı)', :firma_id)";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':personel_id' => $personel_id,
                ':bitis_zamani' => $bitisZamani,
                ':firma_id' => $firma_id
            ]);
        } catch (\PDOException $e) {
            // aciklama kolonu yoksa kolonsuz dene
            if (strpos($e->getMessage(), 'aciklama') !== false) {
                $sql = "INSERT INTO {$this->table} 
                        (personel_id, islem_tipi, zaman, firma_id)
                        VALUES (:personel_id, 'BITIR', :bitis_zamani, :firma_id)";

                $stmt = $this->db->prepare($sql);
                $result = $stmt->execute([
                    ':personel_id' => $personel_id,
                    ':bitis_zamani' => $bitisZamani,
                    ':firma_id' => $firma_id
                ]);
            } else {
                throw $e;
            }
        }

        if ($result) {
            // Log kaydı
            error_log("[" . date('Y-m-d H:i:s') . "] Personel ID: {$personel_id} için görev otomatik sonlandırıldı. Tarih: {$tarih}");
        }

        return $result;
    }

    /**
     * Tüm açık görevleri otomatik sonlandırır (Cron job için)
     * @return array Sonlandırılan görevlerin listesi
     */
    public function tumAcikGorevleriSonlandir()
    {
        // 23:50'yi geçmiş tüm açık görevleri bul
        $sql = "SELECT ph.* 
                FROM {$this->table} ph
                WHERE ph.islem_tipi = 'BASLA'
                AND ph.silinme_tarihi IS NULL
                AND (
                    DATE(ph.zaman) < CURDATE() 
                    OR (DATE(ph.zaman) = CURDATE() AND TIME(NOW()) >= '23:50:00')
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {$this->table} ph2 
                    WHERE ph2.personel_id = ph.personel_id 
                    AND ph2.islem_tipi = 'BITIR' 
                    AND ph2.zaman > ph.zaman
                    AND ph2.silinme_tarihi IS NULL
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $acikGorevler = $stmt->fetchAll(PDO::FETCH_OBJ);

        $sonlandirilanlar = [];

        foreach ($acikGorevler as $gorev) {
            $baslangicTarihi = date('Y-m-d', strtotime($gorev->zaman));
            $sonuc = $this->otomatikSonlandir($gorev->personel_id, $gorev->id, $baslangicTarihi);

            if ($sonuc) {
                $sonlandirilanlar[] = [
                    'personel_id' => $gorev->personel_id,
                    'baslangic' => $gorev->zaman,
                    'tarih' => $baslangicTarihi
                ];
            }
        }

        return $sonlandirilanlar;
    }

    /**
     * Göreve başlama işlemi kaydet
     * @param array $data [personel_id, konum_enlem, konum_boylam, konum_hassasiyeti, cihaz_bilgisi, ip_adresi, firma_id]
     * @return int|false Eklenen kayıt ID'si veya hata durumunda false
     */
    public function baslaGorev($data)
    {
        // Zaten açık görev var mı kontrol et
        $acikGorev = $this->getAcikGorev($data['personel_id']);
        if ($acikGorev) {
            return false; // Zaten başlamış bir görev var
        }

        $sql = "INSERT INTO {$this->table} 
                (personel_id, islem_tipi, zaman, konum_enlem, konum_boylam, konum_hassasiyeti, cihaz_bilgisi, ip_adresi, firma_id)
                VALUES 
                (:personel_id, 'BASLA', NOW(), :konum_enlem, :konum_boylam, :konum_hassasiyeti, :cihaz_bilgisi, :ip_adresi, :firma_id)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':personel_id' => $data['personel_id'],
            ':konum_enlem' => $data['konum_enlem'],
            ':konum_boylam' => $data['konum_boylam'],
            ':konum_hassasiyeti' => $data['konum_hassasiyeti'] ?? null,
            ':cihaz_bilgisi' => $data['cihaz_bilgisi'] ?? null,
            ':ip_adresi' => $data['ip_adresi'] ?? null,
            ':firma_id' => $data['firma_id'] ?? null
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Görevi bitirme işlemi kaydet
     * @param array $data [personel_id, konum_enlem, konum_boylam, konum_hassasiyeti, cihaz_bilgisi, ip_adresi, firma_id]
     * @return int|false Eklenen kayıt ID'si veya hata durumunda false
     */
    public function bitirGorev($data)
    {
        // Açık görev var mı kontrol et
        $acikGorev = $this->getAcikGorev($data['personel_id']);
        if (!$acikGorev) {
            return false; // Bitirilecek görev yok
        }

        $sql = "INSERT INTO {$this->table} 
                (personel_id, islem_tipi, zaman, konum_enlem, konum_boylam, konum_hassasiyeti, cihaz_bilgisi, ip_adresi, firma_id)
                VALUES 
                (:personel_id, 'BITIR', NOW(), :konum_enlem, :konum_boylam, :konum_hassasiyeti, :cihaz_bilgisi, :ip_adresi, :firma_id)";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':personel_id' => $data['personel_id'],
            ':konum_enlem' => $data['konum_enlem'],
            ':konum_boylam' => $data['konum_boylam'],
            ':konum_hassasiyeti' => $data['konum_hassasiyeti'] ?? null,
            ':cihaz_bilgisi' => $data['cihaz_bilgisi'] ?? null,
            ':ip_adresi' => $data['ip_adresi'] ?? null,
            ':firma_id' => $data['firma_id'] ?? null
        ]);

        if ($result) {
            return $this->db->lastInsertId();
        }

        return false;
    }

    /**
     * Personelin günlük çalışma özetini getirir
     * @param int $personel_id
     * @param string $tarih Y-m-d formatında tarih (varsayılan bugün)
     * @return object Günlük özet bilgileri
     */
    public function getGunlukOzet($personel_id, $tarih = null)
    {
        if (!$tarih) {
            $tarih = date('Y-m-d');
        }

        $sql = "SELECT 
                    ph.*,
                    (SELECT ph2.zaman FROM {$this->table} ph2 
                     WHERE ph2.personel_id = ph.personel_id 
                     AND ph2.islem_tipi = 'BITIR' 
                     AND ph2.zaman > ph.zaman 
                     AND DATE(ph2.zaman) = :tarih
                     AND ph2.silinme_tarihi IS NULL
                     ORDER BY ph2.zaman ASC LIMIT 1) as bitis_zamani
                FROM {$this->table} ph
                WHERE ph.personel_id = :personel_id 
                AND ph.islem_tipi = 'BASLA'
                AND DATE(ph.zaman) = :tarih2
                AND ph.silinme_tarihi IS NULL
                ORDER BY ph.zaman ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':personel_id' => $personel_id,
            ':tarih' => $tarih,
            ':tarih2' => $tarih
        ]);

        $hareketler = $stmt->fetchAll(PDO::FETCH_OBJ);

        $toplam_dakika = 0;
        $baslangic = null;
        $bitis = null;

        foreach ($hareketler as $hareket) {
            if ($hareket->bitis_zamani) {
                $start = new \DateTime($hareket->zaman);
                $end = new \DateTime($hareket->bitis_zamani);
                $diff = $start->diff($end);
                $toplam_dakika += ($diff->h * 60) + $diff->i;

                if (!$baslangic) {
                    $baslangic = $hareket->zaman;
                }
                $bitis = $hareket->bitis_zamani;
            }
        }

        return (object) [
            'tarih' => $tarih,
            'toplam_dakika' => $toplam_dakika,
            'toplam_saat' => round($toplam_dakika / 60, 2),
            'baslangic' => $baslangic,
            'bitis' => $bitis,
            'hareket_sayisi' => count($hareketler)
        ];
    }

    /**
     * Personelin son 7 günlük hareketlerini getirir
     * @param int $personel_id
     * @return array
     */
    public function getHaftaOzet($personel_id)
    {
        $sql = "SELECT 
                    DATE(zaman) as tarih,
                    islem_tipi,
                    zaman,
                    konum_enlem,
                    konum_boylam
                FROM {$this->table}
                WHERE personel_id = :personel_id
                AND zaman >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                AND silinme_tarihi IS NULL
                ORDER BY zaman DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':personel_id' => $personel_id]);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tüm personellerin bugünkü durumunu getirir (Admin için)
     * @param int|null $firma_id
     * @return array
     */
    public function getTumPersonelDurumu($firma_id = null, $tarih = null)
    {
        if (!$tarih) {
            $tarih = date('Y-m-d');
        }

        $sql = "SELECT 
                    p.id as personel_id,
                    p.adi_soyadi,
                    p.resim_yolu as foto,
                    (SELECT ph.zaman FROM personel_hareketleri ph 
                     WHERE ph.personel_id = p.id AND ph.islem_tipi = 'BASLA' 
                     AND DATE(ph.zaman) = :tarih AND ph.silinme_tarihi IS NULL
                     ORDER BY ph.zaman ASC LIMIT 1) as son_baslama,
                    (SELECT ph.zaman FROM personel_hareketleri ph 
                     WHERE ph.personel_id = p.id AND ph.islem_tipi = 'BITIR' 
                     AND DATE(ph.zaman) = :tarih2 AND ph.silinme_tarihi IS NULL
                     ORDER BY ph.zaman DESC LIMIT 1) as son_bitis,
                    (SELECT ph.konum_enlem FROM personel_hareketleri ph 
                     WHERE ph.personel_id = p.id AND ph.silinme_tarihi IS NULL
                     ORDER BY ph.zaman DESC LIMIT 1) as son_enlem,
                    (SELECT ph.konum_boylam FROM personel_hareketleri ph 
                     WHERE ph.personel_id = p.id AND ph.silinme_tarihi IS NULL
                     ORDER BY ph.zaman DESC LIMIT 1) as son_boylam
                FROM personel p
                WHERE p.silinme_tarihi IS NULL
                AND p.aktif_mi = 1
                AND p.saha_takibi = 1";

        $params = [':tarih' => $tarih, ':tarih2' => $tarih];

        if ($firma_id) {
            $sql .= " AND p.firma_id = :firma_id";
            $params[':firma_id'] = $firma_id;
        }

        $sql .= " ORDER BY p.adi_soyadi ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $personeller = $stmt->fetchAll(PDO::FETCH_OBJ);

        // Her personel için aktif görev durumunu belirle
        foreach ($personeller as &$personel) {
            if ($personel->son_baslama && (!$personel->son_bitis || $personel->son_baslama > $personel->son_bitis)) {
                $personel->durum = 'aktif';
                $personel->durum_text = 'Görevde';
            } elseif ($personel->son_bitis) {
                $personel->durum = 'bitti';
                $personel->durum_text = 'Görevi Tamamladı';
            } else {
                $personel->durum = 'baslamadi';
                $personel->durum_text = 'Henüz Başlamadı';
            }
        }

        return $personeller;
    }

    /**
     * Tarih aralığına göre hareket raporunu getirir
     * @param int|null $personel_id (null ise tümü)
     * @param string $baslangic_tarihi Y-m-d
     * @param string $bitis_tarihi Y-m-d
     * @param int|null $firma_id
     * @return array
     */
    public function getRapor($personel_id = null, $baslangic_tarihi = null, $bitis_tarihi = null, $firma_id = null)
    {
        $sql = "SELECT 
                    ph.*,
                    p.adi_soyadi,
                    p.resim_yolu as foto
                FROM {$this->table} ph
                INNER JOIN personel p ON ph.personel_id = p.id
                WHERE ph.silinme_tarihi IS NULL";

        $params = [];

        if ($personel_id) {
            $sql .= " AND ph.personel_id = :personel_id";
            $params[':personel_id'] = $personel_id;
        }

        if ($baslangic_tarihi) {
            $sql .= " AND DATE(ph.zaman) >= :baslangic";
            $params[':baslangic'] = $baslangic_tarihi;
        }

        if ($bitis_tarihi) {
            $sql .= " AND DATE(ph.zaman) <= :bitis";
            $params[':bitis'] = $bitis_tarihi;
        }

        if ($firma_id) {
            $sql .= " AND ph.firma_id = :firma_id";
            $params[':firma_id'] = $firma_id;
        }

        $sql .= " ORDER BY ph.zaman DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Belirli bir tarihteki geç kalan personel sayısını getirir
     * @param int|null $firma_id
     * @param string|null $tarih
     * @param string $limit_saat
     * @return int
     */
    public function getGecKalanlarCount($firma_id = null, $tarih = null, $limit_saat = '08:30')
    {
        $gun = $tarih ?? date('Y-m-d');
        $now = new \DateTime();

        try {
            $limit_time = new \DateTime($gun . ' ' . $limit_saat);
        } catch (\Exception $e) {
            $limit_time = new \DateTime($gun . ' 08:30');
            $limit_saat = '08:30';
        }

        $countNotStartedAsLate = $now > $limit_time ? 1 : 0;

        $sql = "SELECT
                    SUM(
                        CASE
                            WHEN hareket.first_start IS NOT NULL
                                 AND TIME(hareket.first_start) > :limit_saat THEN 1
                            WHEN hareket.first_start IS NULL
                                 AND :count_not_started = 1 THEN 1
                            ELSE 0
                        END
                    ) AS gec_kalan
                FROM personel p
                LEFT JOIN (
                    SELECT
                        ph.personel_id,
                        MIN(CASE WHEN ph.islem_tipi = 'BASLA' THEN ph.zaman END) AS first_start
                    FROM {$this->table} ph
                    WHERE ph.silinme_tarihi IS NULL
                      AND ph.zaman >= :gun_start
                      AND ph.zaman < :gun_end
                    GROUP BY ph.personel_id
                ) hareket ON hareket.personel_id = p.id
                WHERE p.silinme_tarihi IS NULL
                  AND p.aktif_mi = 1
                  AND p.saha_takibi = 1";

        $params = [
            ':limit_saat' => $limit_saat,
            ':count_not_started' => $countNotStartedAsLate,
            ':gun_start' => $gun . ' 00:00:00',
            ':gun_end' => date('Y-m-d 00:00:00', strtotime($gun . ' +1 day')),
        ];

        if ($firma_id) {
            $sql .= " AND p.firma_id = :firma_id";
            $params[':firma_id'] = $firma_id;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_OBJ);

        return (int) ($result->gec_kalan ?? 0);
    }
}
