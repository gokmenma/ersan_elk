<?php

namespace App\Model;

use PDO;

class IhbarModel extends Model
{
    protected $table = 'ihbarlar';

    public function __construct()
    {
        parent::__construct('ihbarlar');
    }

    private function firmaId()
    {
        return (int) ($_SESSION['firma_id'] ?? 1);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO ihbarlar
            (firma_id, ilce, mahalle, telefon, komsu_abone_no, aciklama, konum_link, konum_lat, konum_lng, konum_dogruluk, durum, bildiren_personel_id, olusturan_user_id, created_at)
            VALUES (:firma_id, :ilce, :mahalle, :telefon, :komsu_abone_no, :aciklama, :konum_link, :konum_lat, :konum_lng, :konum_dogruluk, 'yeni', :bildiren_personel_id, :olusturan_user_id, NOW())");

        $stmt->execute([
            ':firma_id' => $this->firmaId(),
            ':ilce' => $data['ilce'] ?? null,
            ':mahalle' => $data['mahalle'] ?? null,
            ':telefon' => $data['telefon'] ?? null,
            ':komsu_abone_no' => $data['komsu_abone_no'] ?? null,
            ':aciklama' => $data['aciklama'] ?? null,
            ':konum_link' => $data['konum_link'] ?? null,
            ':konum_lat' => $data['konum_lat'] ?? null,
            ':konum_lng' => $data['konum_lng'] ?? null,
            ':konum_dogruluk' => $data['konum_dogruluk'] ?? null,
            ':bildiren_personel_id' => $data['bildiren_personel_id'] ?? null,
            ':olusturan_user_id' => $data['olusturan_user_id'] ?? null,
        ]);

        $ihbarId = (int) $this->db->lastInsertId();

        $olusturanAciklama = !empty($data['bildiren_personel_id'])
            ? 'İhbar personel tarafından oluşturuldu.'
            : 'İhbar manuel olarak oluşturuldu.';

        $this->addTarihce(
            $ihbarId,
            'olusturuldu',
            $olusturanAciklama,
            !empty($data['bildiren_personel_id']) ? 'personel' : 'user',
            $data['bildiren_personel_id'] ?? ($data['olusturan_user_id'] ?? 0)
        );

        return $ihbarId;
    }

    /**
     * Personelin kendi bildirdiği ihbarı, henüz hiçbir işlem yapılmamışsa (durum='yeni') günceller.
     */
    public function updateByBildiren(int $ihbarId, int $personelId, array $data): void
    {
        $stmt = $this->db->prepare("SELECT durum, bildiren_personel_id FROM ihbarlar WHERE id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$ihbarId]);
        $row = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$row) {
            throw new \Exception('Kayıt bulunamadı.');
        }

        if ((int) $row->bildiren_personel_id !== $personelId) {
            throw new \Exception('Bu ihbarı güncelleme yetkiniz yok.');
        }

        if ($row->durum !== 'yeni') {
            throw new \Exception('Bu ihbar için işlem başladığından artık güncellenemez.');
        }

        $upd = $this->db->prepare("UPDATE ihbarlar SET ilce = ?, mahalle = ?, telefon = ?, komsu_abone_no = ?, aciklama = ?, konum_link = ?, konum_lat = ?, konum_lng = ?, konum_dogruluk = ? WHERE id = ?");
        $upd->execute([
            $data['ilce'] ?? null,
            $data['mahalle'] ?? null,
            $data['telefon'] ?? null,
            $data['komsu_abone_no'] ?? null,
            $data['aciklama'] ?? null,
            $data['konum_link'] ?? null,
            $data['konum_lat'] ?? null,
            $data['konum_lng'] ?? null,
            $data['konum_dogruluk'] ?? null,
            $ihbarId,
        ]);

        $this->addTarihce($ihbarId, 'not', 'İhbar bilgileri personel tarafından güncellendi.', 'personel', $personelId);
    }

    /**
     * Yönetici, hatalı/eksik girilmiş ihbar bilgilerini durumdan bağımsız olarak düzeltebilir.
     */
    public function updateByYonetici(int $ihbarId, array $data, int $userId): void
    {
        $stmt = $this->db->prepare("SELECT id FROM ihbarlar WHERE id = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$ihbarId]);
        if (!$stmt->fetch(PDO::FETCH_OBJ)) {
            throw new \Exception('Kayıt bulunamadı.');
        }

        $upd = $this->db->prepare("UPDATE ihbarlar SET ilce = ?, mahalle = ?, telefon = ?, komsu_abone_no = ?, aciklama = ?, konum_link = ?, konum_lat = ?, konum_lng = ?, konum_dogruluk = ? WHERE id = ?");
        $upd->execute([
            $data['ilce'] ?? null,
            $data['mahalle'] ?? null,
            $data['telefon'] ?? null,
            $data['komsu_abone_no'] ?? null,
            $data['aciklama'] ?? null,
            $data['konum_link'] ?? null,
            $data['konum_lat'] ?? null,
            $data['konum_lng'] ?? null,
            $data['konum_dogruluk'] ?? null,
            $ihbarId,
        ]);

        $this->addTarihce($ihbarId, 'not', 'İhbar bilgileri yönetici tarafından düzenlendi.', 'user', $userId);
    }

    public function addFotograf(int $ihbarId, string $dosyaYolu): void
    {
        $stmt = $this->db->prepare("INSERT INTO ihbar_fotograflari (ihbar_id, dosya_yolu, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$ihbarId, $dosyaYolu]);
    }

    public function getFotograflar(int $ihbarId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM ihbar_fotograflari WHERE ihbar_id = ? ORDER BY id ASC");
        $stmt->execute([$ihbarId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function addTarihce(int $ihbarId, string $tip, string $aciklama, string $ekleyenTip, int $ekleyenId): void
    {
        $stmt = $this->db->prepare("INSERT INTO ihbar_tarihce (ihbar_id, tip, aciklama, ekleyen_tip, ekleyen_id, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$ihbarId, $tip, $aciklama, $ekleyenTip, $ekleyenId]);
    }

    public function getTarihce(int $ihbarId): array
    {
        $stmt = $this->db->prepare("SELECT t.*,
                CASE WHEN t.ekleyen_tip = 'personel' THEN p.adi_soyadi ELSE u.adi_soyadi END AS ekleyen_adi
            FROM ihbar_tarihce t
            LEFT JOIN personel p ON (t.ekleyen_tip = 'personel' AND p.id = t.ekleyen_id)
            LEFT JOIN users u ON (t.ekleyen_tip = 'user' AND u.id = t.ekleyen_id)
            WHERE t.ihbar_id = ?
            ORDER BY t.created_at ASC, t.id ASC");
        $stmt->execute([$ihbarId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function assignTeam(int $ihbarId, array $personelIds, int $atayanUserId): void
    {
        $personelIds = array_values(array_unique(array_filter(array_map('intval', $personelIds))));
        if (empty($personelIds)) {
            throw new \Exception('En az bir personel seçmelisiniz.');
        }

        $placeholders = implode(',', array_fill(0, count($personelIds), '?'));
        $kontrol = $this->db->prepare("SELECT COUNT(*) FROM personel
            WHERE id IN ({$placeholders})
              AND firma_id = ?
              AND aktif_mi = 1
              AND silinme_tarihi IS NULL
              AND departman LIKE ?");
        $kontrol->execute(array_merge($personelIds, [$this->firmaId(), '%Kaçak%']));
        if ((int) $kontrol->fetchColumn() !== count($personelIds)) {
            throw new \Exception('İhbarlar yalnızca aktif Kaçak Kontrol personeline yönlendirilebilir.');
        }

        $stmt = $this->db->prepare("INSERT INTO ihbar_atamalar (ihbar_id, personel_id, atayan_user_id, created_at) VALUES (?, ?, ?, NOW())");
        foreach ($personelIds as $personelId) {
            $stmt->execute([$ihbarId, $personelId, $atayanUserId]);
        }

        $upd = $this->db->prepare("UPDATE ihbarlar SET durum = 'yonlendirildi' WHERE id = ? AND durum NOT IN ('olumlu', 'olumsuz')");
        $upd->execute([$ihbarId]);

        $isimler = $this->getDb()->prepare("SELECT adi_soyadi FROM personel WHERE id IN (" . implode(',', array_fill(0, count($personelIds), '?')) . ")");
        $isimler->execute($personelIds);
        $adlar = implode(', ', array_column($isimler->fetchAll(PDO::FETCH_OBJ), 'adi_soyadi'));

        $this->addTarihce($ihbarId, 'yonlendirildi', "İhbar şu personele yönlendirildi: {$adlar}", 'user', $atayanUserId);
    }

    /** Personel ihbarını, aktif saha personellerinin son GPS kaydına göre en yakına yönlendirir. */
    public function autoAssignNearest(int $ihbarId, float $lat, float $lng, int $bildirenPersonelId): ?int
    {
        $sql = "SELECT p.id,
                    (6371 * ACOS(LEAST(1, GREATEST(-1,
                        COS(RADIANS(:lat)) * COS(RADIANS(ph.konum_enlem))
                        * COS(RADIANS(ph.konum_boylam) - RADIANS(:lng))
                        + SIN(RADIANS(:lat2)) * SIN(RADIANS(ph.konum_enlem))
                    )))) AS mesafe_km
                FROM personel p
                INNER JOIN personel_hareketleri ph ON ph.id = (
                    SELECT ph2.id FROM personel_hareketleri ph2
                    WHERE ph2.personel_id = p.id AND ph2.silinme_tarihi IS NULL
                    ORDER BY ph2.zaman DESC, ph2.id DESC LIMIT 1
                )
                WHERE p.firma_id = :firma_id
                  AND p.aktif_mi = 1
                  AND p.saha_takibi = 1
                  AND p.departman LIKE :departman
                  AND p.silinme_tarihi IS NULL
                  AND (p.isten_cikis_tarihi IS NULL OR p.isten_cikis_tarihi = '0000-00-00')
                  AND ph.islem_tipi = 'BASLA'
                  AND ph.zaman >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                  AND p.id <> :bildiren_id
                ORDER BY mesafe_km ASC
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':lat' => $lat,
            ':lng' => $lng,
            ':lat2' => $lat,
            ':firma_id' => $this->firmaId(),
            ':bildiren_id' => $bildirenPersonelId,
            ':departman' => '%Kaçak%',
        ]);
        $personelId = (int) ($stmt->fetchColumn() ?: 0);
        if ($personelId <= 0) {
            return null;
        }

        $insert = $this->db->prepare(
            "INSERT INTO ihbar_atamalar (ihbar_id, personel_id, atayan_user_id, created_at) VALUES (?, ?, NULL, NOW())"
        );
        $insert->execute([$ihbarId, $personelId]);
        $update = $this->db->prepare("UPDATE ihbarlar SET durum = 'yonlendirildi' WHERE id = ? AND durum = 'yeni'");
        $update->execute([$ihbarId]);

        $this->addTarihce(
            $ihbarId,
            'yonlendirildi',
            'İhbar, güncel saha konumuna göre en yakın personele otomatik yönlendirildi.',
            'personel',
            $bildirenPersonelId
        );
        return $personelId;
    }

    public function getAtananPersonelIds(int $ihbarId): array
    {
        $stmt = $this->db->prepare("SELECT personel_id FROM ihbar_atamalar WHERE ihbar_id = ?");
        $stmt->execute([$ihbarId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function addNote(int $ihbarId, string $aciklama, string $ekleyenTip, int $ekleyenId): void
    {
        $this->addTarihce($ihbarId, 'not', $aciklama, $ekleyenTip, $ekleyenId);

        if ($ekleyenTip === 'personel') {
            $upd = $this->db->prepare("UPDATE ihbarlar SET durum = 'islemde' WHERE id = ? AND durum = 'yonlendirildi'");
            $upd->execute([$ihbarId]);
        }
    }

    public function closeSonuc(int $ihbarId, string $durum, ?string $tutanakNo, ?string $sebep, string $ekleyenTip, int $ekleyenId): void
    {
        if (!in_array($durum, ['olumlu', 'olumsuz'], true)) {
            throw new \Exception('Geçersiz sonuç durumu.');
        }

        if ($durum === 'olumlu' && empty($tutanakNo)) {
            throw new \Exception('Olumlu sonuç için tutanak numarası girilmelidir.');
        }

        if ($durum === 'olumsuz' && empty($sebep)) {
            throw new \Exception('Olumsuz sonuç için işlem sebebi girilmelidir.');
        }

        // Mevcut ihbar durumunu kontrol et
        $checkStmt = $this->db->prepare("SELECT durum, tutanak_no, olumsuz_sebep FROM ihbarlar WHERE id = ? AND silinme_tarihi IS NULL");
        $checkStmt->execute([$ihbarId]);
        $eskiIhbar = $checkStmt->fetch(PDO::FETCH_OBJ);

        if (!$eskiIhbar) {
            throw new \Exception('İhbar kaydı bulunamadı.');
        }

        $eskiDurum = $eskiIhbar->durum;
        $eskiTutanak = $eskiIhbar->tutanak_no;
        $eskiSebep = $eskiIhbar->olumsuz_sebep;

        $yeniTutanak = $durum === 'olumlu' ? $tutanakNo : null;
        $yeniSebep = $durum === 'olumsuz' ? $sebep : null;

        $stmt = $this->db->prepare("UPDATE ihbarlar SET durum = ?, tutanak_no = ?, olumsuz_sebep = ? WHERE id = ?");
        $stmt->execute([
            $durum,
            $yeniTutanak,
            $yeniSebep,
            $ihbarId
        ]);

        if (in_array($eskiDurum, ['olumlu', 'olumsuz'], true)) {
            $eskiEtiket = ($eskiDurum === 'olumlu')
                ? "Olumlu (Tutanak No: {$eskiTutanak})"
                : "Olumsuz (Sebep: {$eskiSebep})";

            $yeniEtiket = ($durum === 'olumlu')
                ? "Olumlu (Tutanak No: {$yeniTutanak})"
                : "Olumsuz (Sebep: {$yeniSebep})";

            $aciklama = "İhbar sonucu güncellendi. Eski: {$eskiEtiket} ➔ Yeni: {$yeniEtiket}";
        } else {
            $aciklama = $durum === 'olumlu'
                ? "İhbar olumlu olarak sonuçlandırıldı. Tutanak No: {$yeniTutanak}"
                : "İhbar olumsuz olarak sonuçlandırıldı. Sebep: {$yeniSebep}";
        }

        $this->addTarihce($ihbarId, 'durum_degisti', $aciklama, $ekleyenTip, $ekleyenId);
    }

    public function cancelResult(int $ihbarId, int $userId): void
    {
        $check = $this->db->prepare(
            "SELECT durum FROM ihbarlar WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL"
        );
        $check->execute([$ihbarId, $this->firmaId()]);
        $durum = $check->fetchColumn();
        if (!in_array($durum, ['olumlu', 'olumsuz'], true)) {
            throw new \Exception('Yalnızca sonuçlanmış bir ihbarın sonucu iptal edilebilir.');
        }

        $atama = $this->db->prepare("SELECT COUNT(*) FROM ihbar_atamalar WHERE ihbar_id = ?");
        $atama->execute([$ihbarId]);
        $yeniDurum = (int) $atama->fetchColumn() > 0 ? 'yonlendirildi' : 'yeni';

        $update = $this->db->prepare(
            "UPDATE ihbarlar SET durum = ?, tutanak_no = NULL, olumsuz_sebep = NULL WHERE id = ? AND firma_id = ?"
        );
        $update->execute([$yeniDurum, $ihbarId, $this->firmaId()]);
        $this->addTarihce($ihbarId, 'durum_degisti', 'İhbar sonucu iptal edildi; kayıt yeniden işlem bekliyor.', 'user', $userId);
    }

    public function getById(int $id)
    {
        $stmt = $this->db->prepare("SELECT i.*,
                bp.adi_soyadi AS bildiren_personel_adi,
                ou.adi_soyadi AS olusturan_user_adi
            FROM ihbarlar i
            LEFT JOIN personel bp ON bp.id = i.bildiren_personel_id
            LEFT JOIN users ou ON ou.id = i.olusturan_user_id
            WHERE i.id = ? AND i.silinme_tarihi IS NULL");
        $stmt->execute([$id]);
        $ihbar = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$ihbar) {
            return null;
        }

        $atamaStmt = $this->db->prepare("SELECT a.personel_id, p.adi_soyadi
            FROM ihbar_atamalar a
            JOIN personel p ON p.id = a.personel_id
            WHERE a.ihbar_id = ?
            ORDER BY a.created_at ASC");
        $atamaStmt->execute([$id]);

        $ihbar->atanan_ekip = $atamaStmt->fetchAll(PDO::FETCH_OBJ);
        $ihbar->fotograflar = $this->getFotograflar($id);
        $ihbar->tarihce = $this->getTarihce($id);

        return $ihbar;
    }

    /**
     * Masaüstü Dashboard - tüm ihbarları listeler (atanan ekip isimleri dahil)
     */
    public function getAllForDashboard(?string $baslangic = null, ?string $bitis = null): array
    {
        $sql = "SELECT i.*,
                bp.adi_soyadi AS bildiren_personel_adi,
                ou.adi_soyadi AS olusturan_user_adi,
                (SELECT GROUP_CONCAT(p.adi_soyadi SEPARATOR ', ')
                    FROM ihbar_atamalar a
                    JOIN personel p ON p.id = a.personel_id
                    WHERE a.ihbar_id = i.id) AS atanan_ekip_adi,
                (SELECT COUNT(*) FROM ihbar_fotograflari f WHERE f.ihbar_id = i.id) AS foto_sayisi
            FROM ihbarlar i
            LEFT JOIN personel bp ON bp.id = i.bildiren_personel_id
            LEFT JOIN users ou ON ou.id = i.olusturan_user_id
            WHERE i.silinme_tarihi IS NULL AND i.firma_id = ?";

        $params = [$this->firmaId()];
        if ($baslangic !== null && $baslangic !== '') {
            $sql .= " AND DATE(i.created_at) >= ?";
            $params[] = $baslangic;
        }
        if ($bitis !== null && $bitis !== '') {
            $sql .= " AND DATE(i.created_at) <= ?";
            $params[] = $bitis;
        }

        $sql .= "
            ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function softDeleteForDashboard(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE ihbarlar
             SET silinme_tarihi = NOW()
             WHERE id = ? AND firma_id = ? AND silinme_tarihi IS NULL"
        );
        $stmt->execute([$id, $this->firmaId()]);
        return $stmt->rowCount() > 0;
    }

    public function getPersonelinIhbarlari(int $personelId): array
    {
        $stmt = $this->db->prepare("SELECT i.*,
                (SELECT GROUP_CONCAT(p.adi_soyadi SEPARATOR ', ')
                    FROM ihbar_atamalar a
                    JOIN personel p ON p.id = a.personel_id
                    WHERE a.ihbar_id = i.id) AS atanan_ekip_adi
            FROM ihbarlar i
            WHERE i.bildiren_personel_id = ? AND i.silinme_tarihi IS NULL
            ORDER BY i.created_at DESC");
        $stmt->execute([$personelId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getPersoneleAtananIhbarlar(int $personelId): array
    {
        $stmt = $this->db->prepare("SELECT DISTINCT i.*
            FROM ihbarlar i
            JOIN ihbar_atamalar a ON a.ihbar_id = i.id
            WHERE a.personel_id = ? AND i.silinme_tarihi IS NULL
            ORDER BY i.created_at DESC");
        $stmt->execute([$personelId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function getYonlendirilecekPersonelListesi(): array
    {
        $stmt = $this->db->prepare("SELECT id, adi_soyadi, departman
            FROM personel
            WHERE firma_id = ?
              AND aktif_mi = 1
              AND silinme_tarihi IS NULL
              AND departman LIKE ?
              AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00')
            ORDER BY adi_soyadi ASC");
        $stmt->execute([$this->firmaId(), '%Kaçak%']);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
