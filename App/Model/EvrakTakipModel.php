<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class EvrakTakipModel extends Model
{
    protected $table = 'evrak_takip';

    public function __construct()
    {
        parent::__construct($this->table);
        $this->checkSchema();
    }

    private function checkSchema()
    {
        try {
            $cols = $this->db->query("SHOW COLUMNS FROM {$this->table}")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('tutar', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN tutar decimal(10,2) DEFAULT NULL AFTER ilgili_personel_id");
            }
            if (!in_array('plaka', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN plaka varchar(50) DEFAULT NULL AFTER tutar");
            }
            if (!in_array('ceza_tutari', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN ceza_tutari decimal(10,2) DEFAULT NULL AFTER tutar");
            }
            if (!in_array('ceza_hedef_tipi', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN ceza_hedef_tipi varchar(20) NOT NULL DEFAULT 'arac' AFTER ceza_tutari");
            }
            if (!in_array('ceza_personel_id', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN ceza_personel_id int DEFAULT NULL AFTER ceza_hedef_tipi");
            }
            if (!in_array('imza_kimin_adina_json', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN imza_kimin_adina_json text DEFAULT NULL AFTER imza_kullanici_ids");
            }
        } catch (\Exception $e) {
            // Silent catch to prevent errors on strict environments
        }
    }

    /**
     * Firma bazlı tüm evrakları getirir
     */
    public function all()
    {
        $sql = $this->db->prepare("
            SELECT et.*, 
                   p1.adi_soyadi as personel_adi,
                   p2.adi_soyadi as ilgili_personel_adi
            FROM {$this->table} et
            LEFT JOIN personel p1 ON et.personel_id = p1.id
            LEFT JOIN personel p2 ON et.ilgili_personel_id = p2.id
            WHERE et.firma_id = :firma_id 
            AND et.silinme_tarihi IS NULL
            ORDER BY et.tarih DESC, et.id DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Tek evrak detayı
     */
    public function getById($id)
    {
        $sql = $this->db->prepare("
            SELECT et.*, 
                   p1.adi_soyadi as personel_adi,
                   p2.adi_soyadi as ilgili_personel_adi
            FROM {$this->table} et
            LEFT JOIN personel p1 ON et.personel_id = p1.id
            LEFT JOIN personel p2 ON et.ilgili_personel_id = p2.id
            WHERE et.id = :id 
            AND et.firma_id = :firma_id
            AND et.silinme_tarihi IS NULL
        ");
        $sql->execute([
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    public function getPersonelName(int $personelId): string
    {
        if ($personelId <= 0) {
            return '';
        }

        $sql = $this->db->prepare("
            SELECT adi_soyadi
            FROM personel
            WHERE id = :id
              AND firma_id = :firma_id
              AND silinme_tarihi IS NULL
            LIMIT 1
        ");
        $sql->execute([
            'id' => $personelId,
            'firma_id' => $_SESSION['firma_id'],
        ]);

        return (string) ($sql->fetchColumn() ?: '');
    }

    public function getActiveVehicles(): array
    {
        $sql = $this->db->prepare("
            SELECT id, plaka, marka, model
            FROM araclar
            WHERE firma_id = :firma_id
              AND silinme_tarihi IS NULL
            ORDER BY plaka ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function findVehicleAssignment(string $plaka, string $tarih): object|false
    {
        $sql = $this->db->prepare("
            SELECT az.personel_id, p.adi_soyadi
            FROM arac_zimmetleri az
            INNER JOIN araclar a ON az.arac_id = a.id
            INNER JOIN personel p ON az.personel_id = p.id
            WHERE REPLACE(a.plaka, ' ', '') = REPLACE(:plaka, ' ', '')
              AND az.firma_id = :firma_id
              AND az.silinme_tarihi IS NULL
              AND az.zimmet_tarihi <= :tarih
              AND (az.iade_tarihi IS NULL OR az.iade_tarihi >= :tarih)
              AND az.durum != 'iptal'
            ORDER BY az.id DESC
            LIMIT 1
        ");
        $sql->execute([
            'plaka' => $plaka,
            'tarih' => $tarih,
            'firma_id' => $_SESSION['firma_id'],
        ]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    public function getSigningUsers(): array
    {
        $sql = $this->db->prepare("
            SELECT id, adi_soyadi, COALESCE(NULLIF(unvani, ''), gorevi, '') AS imza_unvani, telefon, email_adresi
            FROM users
            WHERE silinme_tarihi IS NULL
              AND durum = 'Aktif'
              AND owner_id = :owner_id
              AND (firma_ids IS NULL OR firma_ids = '' OR FIND_IN_SET(:firma_id, REPLACE(firma_ids, ' ', '')) > 0)
            ORDER BY adi_soyadi ASC
        ");
        $sql->execute([
            'owner_id' => (int) ($_SESSION['owner_id'] ?? 0),
            'firma_id' => (string) $_SESSION['firma_id'],
        ]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getSigningUsersByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        $ids = array_slice($ids, 0, 3);
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = $this->db->prepare("
            SELECT id, adi_soyadi, COALESCE(NULLIF(unvani, ''), gorevi, '') AS imza_unvani, telefon, email_adresi
            FROM users
            WHERE id IN ({$placeholders})
              AND silinme_tarihi IS NULL
              AND owner_id = ?
              AND (firma_ids IS NULL OR firma_ids = '' OR FIND_IN_SET(?, REPLACE(firma_ids, ' ', '')) > 0)
        ");
        $sql->execute(array_merge($ids, [
            (int) ($_SESSION['owner_id'] ?? 0),
            (string) $_SESSION['firma_id'],
        ]));
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row->id] = $row;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    public function getAttachments(int $evrakId): array
    {
        $sql = $this->db->prepare("
            SELECT id, dosya_adi, dosya_yolu, mime_tipi, dosya_boyutu, sira, olusturma_tarihi
            FROM evrak_takip_ekleri
            WHERE evrak_id = :evrak_id
              AND firma_id = :firma_id
              AND silinme_tarihi IS NULL
            ORDER BY sira ASC, id ASC
        ");
        $sql->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function saveAttachment(int $evrakId, array $attachment): int
    {
        if (!$this->getById($evrakId)) {
            throw new \RuntimeException('Evrak bulunamadı.');
        }
        $sql = $this->db->prepare("
            INSERT INTO evrak_takip_ekleri
                (evrak_id, firma_id, dosya_adi, dosya_yolu, mime_tipi, dosya_boyutu, sira)
            VALUES
                (:evrak_id, :firma_id, :dosya_adi, :dosya_yolu, :mime_tipi, :dosya_boyutu, :sira)
        ");
        $sql->execute([
            'evrak_id' => $evrakId,
            'firma_id' => $_SESSION['firma_id'],
            'dosya_adi' => $attachment['dosya_adi'],
            'dosya_yolu' => $attachment['dosya_yolu'],
            'mime_tipi' => $attachment['mime_tipi'] ?? null,
            'dosya_boyutu' => (int) ($attachment['dosya_boyutu'] ?? 0),
            'sira' => (int) ($attachment['sira'] ?? 0),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function syncAttachmentState(int $evrakId, array $orderedIds, array $removedIds): void
    {
        if (!$this->getById($evrakId)) {
            throw new \RuntimeException('Evrak bulunamadı.');
        }
        $removedIds = array_values(array_unique(array_filter(array_map('intval', $removedIds), fn($id) => $id > 0)));
        if ($removedIds !== []) {
            $placeholders = implode(',', array_fill(0, count($removedIds), '?'));
            $sql = $this->db->prepare("UPDATE evrak_takip_ekleri SET silinme_tarihi = NOW() WHERE id IN ({$placeholders}) AND evrak_id = ? AND firma_id = ?");
            $sql->execute(array_merge($removedIds, [$evrakId, (int) $_SESSION['firma_id']]));
        }

        $position = 1;
        $seen = [];
        foreach ($orderedIds as $attachmentId) {
            $attachmentId = (int) $attachmentId;
            if ($attachmentId <= 0 || isset($seen[$attachmentId])) {
                $position++;
                continue;
            }
            $seen[$attachmentId] = true;
            $sql = $this->db->prepare("UPDATE evrak_takip_ekleri SET sira = :sira WHERE id = :id AND evrak_id = :evrak_id AND firma_id = :firma_id AND silinme_tarihi IS NULL");
            $sql->execute(['sira' => $position++, 'id' => $attachmentId, 'evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
        }
    }

    /**
     * Evrak istatistikleri
     */
    public function getStats()
    {
        $sql = $this->db->prepare("
            SELECT 
                COUNT(*) as toplam_evrak,
                SUM(CASE WHEN evrak_tipi = 'gelen' THEN 1 ELSE 0 END) as gelen_evrak,
                SUM(CASE WHEN evrak_tipi = 'giden' THEN 1 ELSE 0 END) as giden_evrak,
                SUM(CASE WHEN evrak_tipi = 'gelen' AND (cevap_verildi_mi = 0 OR cevap_verildi_mi IS NULL) THEN 1 ELSE 0 END) as cevap_bekleyen
            FROM {$this->table}
            WHERE firma_id = :firma_id
            AND silinme_tarihi IS NULL
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * En yüksek evrak numarasını getirir
     */
    public function getMaxEvrakNo($tip)
    {
        $sql = $this->db->prepare("
            SELECT MAX(
                CASE
                    WHEN evrak_no REGEXP '^[0-9]+$' THEN CAST(evrak_no AS UNSIGNED)
                    ELSE 0
                END
            ) as max_no
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND evrak_tipi = :evrak_tipi
            AND silinme_tarihi IS NULL
        ");
        $sql->execute([
            'firma_id' => $_SESSION['firma_id'],
            'evrak_tipi' => $tip
        ]);
        $row = $sql->fetch(PDO::FETCH_OBJ);
        return ((int) ($row->max_no ?? 0)) + 1;
    }

    /**
     * Benzersiz evrak konularını getirir
     */
    public function getDistinctKonular()
    {
        $sql = $this->db->prepare("
            SELECT DISTINCT konu 
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND silinme_tarihi IS NULL
            AND konu IS NOT NULL
            AND konu != ''
            ORDER BY konu ASC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * İlişkilendirme için tüm gelen evrakları getirir
     */
    public function getGelenEvraklar()
    {
        $sql = $this->db->prepare("
            SELECT id, evrak_no, konu, kurum_adi, tarih 
            FROM {$this->table} 
            WHERE firma_id = :firma_id 
            AND evrak_tipi = 'gelen'
            AND silinme_tarihi IS NULL
            ORDER BY tarih DESC
        ");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    /**
     * Evrakı cevaplandı olarak işaretler
     */
    public function markAsReplied($id, $tarih)
    {
        $sql = $this->db->prepare("
            UPDATE {$this->table} 
            SET cevap_verildi_mi = 1, 
                cevap_tarihi = :cevap_tarihi 
            WHERE id = :id 
            AND firma_id = :firma_id
        ");
        return $sql->execute([
            'cevap_tarihi' => $tarih,
            'id' => $id,
            'firma_id' => $_SESSION['firma_id']
        ]);
    }
}
