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
            (firma_id, ilce, mahalle, telefon, komsu_abone_no, aciklama, konum_lat, konum_lng, konum_dogruluk, durum, bildiren_personel_id, olusturan_user_id, created_at)
            VALUES (:firma_id, :ilce, :mahalle, :telefon, :komsu_abone_no, :aciklama, :konum_lat, :konum_lng, :konum_dogruluk, 'yeni', :bildiren_personel_id, :olusturan_user_id, NOW())");

        $stmt->execute([
            ':firma_id' => $this->firmaId(),
            ':ilce' => $data['ilce'] ?? null,
            ':mahalle' => $data['mahalle'] ?? null,
            ':telefon' => $data['telefon'] ?? null,
            ':komsu_abone_no' => $data['komsu_abone_no'] ?? null,
            ':aciklama' => $data['aciklama'] ?? null,
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

        $upd = $this->db->prepare("UPDATE ihbarlar SET ilce = ?, mahalle = ?, telefon = ?, komsu_abone_no = ?, aciklama = ?, konum_lat = ?, konum_lng = ?, konum_dogruluk = ? WHERE id = ?");
        $upd->execute([
            $data['ilce'] ?? null,
            $data['mahalle'] ?? null,
            $data['telefon'] ?? null,
            $data['komsu_abone_no'] ?? null,
            $data['aciklama'] ?? null,
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

        $upd = $this->db->prepare("UPDATE ihbarlar SET ilce = ?, mahalle = ?, telefon = ?, komsu_abone_no = ?, aciklama = ?, konum_lat = ?, konum_lng = ?, konum_dogruluk = ? WHERE id = ?");
        $upd->execute([
            $data['ilce'] ?? null,
            $data['mahalle'] ?? null,
            $data['telefon'] ?? null,
            $data['komsu_abone_no'] ?? null,
            $data['aciklama'] ?? null,
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

        $stmt = $this->db->prepare("UPDATE ihbarlar SET durum = ?, tutanak_no = ?, olumsuz_sebep = ? WHERE id = ?");
        $stmt->execute([
            $durum,
            $durum === 'olumlu' ? $tutanakNo : null,
            $durum === 'olumsuz' ? $sebep : null,
            $ihbarId
        ]);

        $aciklama = $durum === 'olumlu'
            ? "İhbar olumlu olarak sonuçlandırıldı. Tutanak No: {$tutanakNo}"
            : "İhbar olumsuz olarak sonuçlandırıldı. Sebep: {$sebep}";

        $this->addTarihce($ihbarId, 'durum_degisti', $aciklama, $ekleyenTip, $ekleyenId);
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
    public function getAllForDashboard(): array
    {
        $sql = "SELECT i.*,
                bp.adi_soyadi AS bildiren_personel_adi,
                ou.adi_soyadi AS olusturan_user_adi,
                (SELECT GROUP_CONCAT(p.adi_soyadi SEPARATOR ', ')
                    FROM ihbar_atamalar a
                    JOIN personel p ON p.id = a.personel_id
                    WHERE a.ihbar_id = i.id) AS atanan_ekip_adi
            FROM ihbarlar i
            LEFT JOIN personel bp ON bp.id = i.bildiren_personel_id
            LEFT JOIN users ou ON ou.id = i.olusturan_user_id
            WHERE i.silinme_tarihi IS NULL AND i.firma_id = ?
            ORDER BY i.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$this->firmaId()]);
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
            WHERE aktif_mi = 1
              AND (isten_cikis_tarihi IS NULL OR isten_cikis_tarihi = '0000-00-00')
            ORDER BY adi_soyadi ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
