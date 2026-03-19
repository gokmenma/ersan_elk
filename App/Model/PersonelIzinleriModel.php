<?php

namespace App\Model;

use App\Model\Model;
use PDO;

use App\Helper\Security;

class PersonelIzinleriModel extends Model
{
    protected $table = 'personel_izinleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Personelin izinlerini, onay durumu ve onaylayan bilgileriyle birlikte getirir.
     *
     * @param int $personel_id
     * @return array
     */
    public function getPersonelIzinleri($personel_id)
    {
        $sql = "SELECT 
                    pi.*,
                    io.id as onay_kayit_id,
                    io.onay_durumu as onay_durumu_text,
                    io.onay_tarihi,
                    io.aciklama as onay_aciklama,
                    u.adi_soyadi as onaylayan_adi_soyadi,
                    t.tur_adi as izin_tipi_adi,
                    t.yetkili_onayina_tabi,
                    t.ucretli_mi
                FROM $this->table as pi
                LEFT JOIN izin_onaylari as io ON io.izin_id = pi.id
                LEFT JOIN users as u ON u.id = io.onaylayan_id
                LEFT JOIN tanimlamalar as t ON t.id = pi.izin_tipi_id
                WHERE pi.personel_id = ? AND pi.silinme_tarihi IS NULL
                AND t.kisa_kod NOT IN ('X', 'x')
                ORDER BY pi.id DESC, io.id ASC";

        $query = $this->db->prepare($sql);
        $query->execute([$personel_id]);
        $rows = $query->fetchAll(PDO::FETCH_OBJ);

        $result = [];
        foreach ($rows as $row) {
            if (!isset($result[$row->id])) {
                $row->onaylar = [];
                $row->son_durum = $row->onay_durumu; // Ana tablodaki durumu varsayılan yap
                $result[$row->id] = $row;
            }

            if ($row->onay_kayit_id) {
                $onay = new \stdClass();
                $onay->adi = $row->onaylayan_adi_soyadi;
                $onay->tarih = $row->onay_tarihi;
                $onay->durum = $row->onay_durumu_text;
                $onay->aciklama = $row->onay_aciklama;

                $result[$row->id]->onaylar[] = $onay;
                $result[$row->id]->son_durum = $row->onay_durumu_text;
            }
        }

        return array_values($result);
    }

    /**
     * Firma bazında bekleyen izin sayısını getirir
     */
    public function getBekleyenIzinSayisi()
    {
        $sql = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            WHERE pi.onay_durumu = 'beklemede' AND pi.silinme_tarihi IS NULL AND p.firma_id = ?
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ)->count ?? 0;
    }

    /**
     * Firma bazında bekleyen izin listesini getirir (dashboard için)
     */
    public function getBekleyenIzinlerForDashboard($limit = 5)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT 'İzin' as tip, pi.id, pi.personel_id, pi.talep_tarihi as tarih, pi.onay_durumu as durum, t.tur_adi as detay,
                   pi.baslangic_tarihi, pi.bitis_tarihi, pi.toplam_gun
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.onay_durumu = 'beklemede' AND pi.silinme_tarihi IS NULL AND p.firma_id = ? 
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Şu anda izindeki personelleri getirir
     */
    public function getAktifIzinler($limit = 10)
    {
        $limit = (int) $limit;
        $today = date('Y-m-d');
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi, p.resim_yolu, p.personel_resim_yolu, p.departman, t.tur_adi as izin_tipi_adi
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ? AND pi.onay_durumu = 'Onaylandı' AND pi.silinme_tarihi IS NULL AND p.firma_id = ?
            ORDER BY pi.bitis_tarihi ASC
            LIMIT {$limit}
        ");
        $sql->execute([$today, $today, $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tüm bekleyen izin taleplerini personel bilgileriyle getirir
     */
    public function getButunBekleyenIzinler()
    {
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi as requester_name, p.resim_yolu, p.personel_resim_yolu, p.departman, p.gorev, t.tur_adi as izin_tipi_adi
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.onay_durumu = 'beklemede' AND pi.silinme_tarihi IS NULL AND p.firma_id = ?
            ORDER BY pi.talep_tarihi DESC
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * İşlem yapılmış (onaylanmış veya reddedilmiş) izin taleplerini getirir
     */
    public function getIslenmisIzinler($limit = 50)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi as requester_name, p.resim_yolu, p.personel_resim_yolu, p.departman, p.gorev, t.tur_adi as izin_tipi_adi,
                   u.adi_soyadi as solver_name, io.onay_tarihi as islem_tarihi, io.aciklama as onay_aciklama
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            LEFT JOIN (
                SELECT io1.izin_id, io1.onaylayan_id, io1.onay_tarihi, io1.aciklama
                FROM izin_onaylari io1
                INNER JOIN (
                    SELECT MAX(id) as max_id FROM izin_onaylari GROUP BY izin_id
                ) io2 ON io1.id = io2.max_id
            ) io ON io.izin_id = pi.id
            LEFT JOIN users u ON io.onaylayan_id = u.id
            WHERE pi.onay_durumu IN ('Onaylandı', 'Reddedildi') AND pi.silinme_tarihi IS NULL AND p.firma_id = ?
            AND pi.id NOT IN (
                SELECT izin_id FROM izin_onaylari 
                WHERE aciklama LIKE 'Puantaj üzerinden%' 
                OR aciklama LIKE 'SGK Vizite%'
                OR aciklama LIKE 'Otomatik onaylandı%'
            )
            ORDER BY pi.talep_tarihi DESC
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Reddedilmiş izin taleplerini personel bilgileriyle getirir
     */
    public function getReddedilmisIzinler($limit = 50)
    {
        $limit = (int) $limit;
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi as requester_name, p.resim_yolu, p.personel_resim_yolu, p.departman, p.gorev, t.tur_adi as izin_tipi_adi
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.onay_durumu = 'Reddedildi' AND pi.silinme_tarihi IS NULL AND p.firma_id = ?
            ORDER BY pi.talep_tarihi DESC
            LIMIT {$limit}
        ");
        $sql->execute([$_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * İzin durumunu günceller (onay/ret)
     */
    public function updateDurum($id, $durum, $aciklama = null)
    {
        $onay_tarihi = in_array($durum, ['Onaylandı', 'Reddedildi']) ? date('Y-m-d H:i:s') : null;
        $onaylayan_id = $_SESSION['user_id'] ?? null;

        if ($onay_tarihi) {
            $this->addOnayKaydi($id, $onaylayan_id, $durum, $aciklama);
        }

        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET onay_durumu = ?
            WHERE id = ?
        ");
        return $sql->execute([$durum, $id]);
    }

    /**
     * İzin onay kaydı eklerken seviye_no'yu otomatik artırır
     */
    private function addOnayKaydi($izin_id, $onaylayan_id, $durum, $aciklama)
    {
        // Mevcut en yüksek seviye_no'yu bul
        $check = $this->db->prepare("SELECT MAX(seviye_no) as max_seviye FROM izin_onaylari WHERE izin_id = ?");
        $check->execute([$izin_id]);
        $row = $check->fetch(PDO::FETCH_OBJ);
        $next_seviye = ($row->max_seviye ?? 0) + 1;

        $sql = $this->db->prepare("
            INSERT INTO izin_onaylari (izin_id, onaylayan_id, onay_durumu, onay_tarihi, aciklama, seviye_no)
            VALUES (?, ?, ?, NOW(), ?, ?)
        ");
        return $sql->execute([$izin_id, $onaylayan_id, $durum, $aciklama, $next_seviye]);
    }

    /**
     * İzin detayını getirir
     */
    public function getIzinDetay($id)
    {
        $sql = $this->db->prepare("
            SELECT pi.*, p.adi_soyadi as requester_name, p.resim_yolu, p.personel_resim_yolu, p.departman, p.gorev, t.tur_adi as izin_tipi_adi
            FROM {$this->table} pi 
            JOIN personel p ON pi.personel_id = p.id 
            LEFT JOIN tanimlamalar t ON t.id = pi.izin_tipi_id
            WHERE pi.id = ?
        ");
        $sql->execute([$id]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * İzin gün sayısını hesaplar
     */
    public function hesaplaIzinGunu($baslangic, $bitis)
    {
        $start = new \DateTime($baslangic);
        $end = new \DateTime($bitis);
        return $start->diff($end)->days + 1;
    }

    /**
     * Personelin izin hakedişlerini hesaplar
     * @param int $personel_id
     * @return array
     */
    public function calculateLeaveEntitlement($personel_id)
    {
        // 1. Personel bilgilerini çek
        $sql = "SELECT ise_giris_tarihi, dogum_tarihi FROM personel WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personel_id]);
        $personel = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$personel) {
            return ['toplam_hakedis' => 0, 'kullanilan_izin' => 0, 'kalan_izin' => 0, 'detay' => []];
        }

        // 2. Tarihleri parse et
        $giris = $this->parseDate($personel->ise_giris_tarihi);
        $dogum = $this->parseDate($personel->dogum_tarihi);

        $toplam_hakedis = 0;
        $detay = [];

        if ($giris) {
            $bugun = new \DateTime();
            if ($giris <= $bugun) {
                $calisma_yili = (int) $giris->diff($bugun)->y;
                for ($i = 1; $i <= $calisma_yili; $i++) {
                    // Hizmet süresine göre temel hakediş
                    // a) Bir yıldan beş yıla kadar (beş yıl dahil) olanlara ondört günden,
                    if ($i >= 1 && $i <= 5) {
                        $hakedis = 14;
                    }
                    // b) Beş yıldan fazla onbeş yıldan az olanlara yirmi günden,
                    elseif ($i > 5 && $i < 15) {
                        $hakedis = 20;
                    }
                    // c) Onbeş yıl (dahil) ve daha fazla olanlara yirmialtı günden,
                    else {
                        $hakedis = 26;
                    }

                    // Yaş kontrolü (18 ve altı, 50 ve üstü -> en az 20 gün)
                    if ($dogum) {
                        $yil_sonu = (clone $giris)->modify("+$i years");
                        $yas = (int) $dogum->diff($yil_sonu)->y;
                        // onsekiz ve daha küçük yaştaki işçilerle elli ve daha yukarı yaştaki işçilere verilecek yıllık ücretli izin süresi yirmi günden az olamaz.
                        if (($yas <= 18 || $yas >= 50) && $hakedis < 20) {
                            $hakedis = 20;
                        }
                    }

                    // Yer altı işçisi kontrolü (Şimdilik pasif, alan eklenirse burası güncellenebilir)
                    // if ($yer_alti) $hakedis += 4;

                    $hakedis_tarihi = (clone $giris)->modify("+$i years")->format('Y-m-d');

                    $detay[] = [
                        'yil' => $i,
                        'hakedis_tarihi' => $hakedis_tarihi,
                        'hakedis_gun' => $hakedis
                    ];

                    $toplam_hakedis += $hakedis;
                }
            }
        }

        // 3. Kullanılan izinleri hesapla
        // Yıllık izne etkisi 'Dus' olan ve onaylanmış izinler
        // Sadece 'Yıllık İzin' türündeki izinleri dahil et (Babalık izni vb. hariç)
        $sql = "SELECT pi.toplam_gun, pi.yillik_izne_etki 
                FROM personel_izinleri as pi
                LEFT JOIN tanimlamalar as t ON t.id = pi.izin_tipi_id
                WHERE pi.personel_id = ? 
                AND pi.silinme_tarihi IS NULL 
                AND (LOWER(pi.onay_durumu) = 'onaylandı' OR LOWER(pi.onay_durumu) = 'onaylandi' OR LOWER(pi.onay_durumu) = 'kabuledildi')
                AND (t.tur_adi LIKE '%Yıllık%' OR t.tur_adi LIKE '%Yillik%')";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$personel_id]);
        $izinler = $stmt->fetchAll(PDO::FETCH_OBJ);

        $kullanilan_izin = 0;
        foreach ($izinler as $izin) {
            if (isset($izin->yillik_izne_etki) && ($izin->yillik_izne_etki == 'Dus' || $izin->yillik_izne_etki == 1)) {
                $kullanilan_izin += (float) $izin->toplam_gun;
            }
        }

        return [
            'toplam_hakedis' => $toplam_hakedis,
            'kullanilan_izin' => $kullanilan_izin,
            'kalan_izin' => max(0, $toplam_hakedis - $kullanilan_izin),
            'detay' => $detay
        ];
    }

    private function parseDate($value)
    {
        if (empty($value) || $value == '0000-00-00' || $value == '0000-00-00 00:00:00')
            return null;
        try {
            return new \DateTime($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}