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

    public function resetApprovalWorkflow(int $evrakId, array $signerIds): void
    {
        $evrak = $this->getById($evrakId);
        if (!$evrak || ($evrak->onay_durumu ?? 'taslak') !== 'taslak') {
            throw new \RuntimeException('Onay süreci başlamış evrak değiştirilemez.');
        }
        $this->db->beginTransaction();
        try {
            $delete = $this->db->prepare('DELETE FROM evrak_takip_onaylari WHERE evrak_id = :evrak_id AND firma_id = :firma_id');
            $delete->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $insert = $this->db->prepare("INSERT INTO evrak_takip_onaylari (evrak_id, firma_id, kullanici_id, sira, durum) VALUES (:evrak_id, :firma_id, :kullanici_id, :sira, 'bekliyor')");
            $sira = 1;
            foreach (array_unique(array_map('intval', $signerIds)) as $signerId) {
                if ($signerId > 0) {
                    $insert->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id'], 'kullanici_id' => $signerId, 'sira' => $sira++]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getApprovalState(int $evrakId): array
    {
        $sql = $this->db->prepare("SELECT o.kullanici_id, o.sira, o.durum, o.onay_tarihi, u.adi_soyadi
            FROM evrak_takip_onaylari o
            INNER JOIN users u ON u.id = o.kullanici_id
            WHERE o.evrak_id = :evrak_id AND o.firma_id = :firma_id
            ORDER BY o.sira ASC, o.id ASC");
        $sql->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getNextPendingSigner(int $evrakId): ?object
    {
        $sql = $this->db->prepare("SELECT o.kullanici_id, o.sira, u.adi_soyadi, u.email_adresi,
                COALESCE(NULLIF(u.unvani, ''), u.gorevi, '') AS imza_unvani
            FROM evrak_takip_onaylari o
            INNER JOIN users u ON u.id = o.kullanici_id
            WHERE o.evrak_id = :evrak_id AND o.firma_id = :firma_id AND o.durum = 'bekliyor'
            ORDER BY o.sira ASC, o.id ASC
            LIMIT 1");
        $sql->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
        return $sql->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function approveWithWorkflow(int $evrakId, int $userId, string $ipAddress): array
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL FOR UPDATE");
            $lock->execute(['id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $evrak = $lock->fetch(PDO::FETCH_OBJ);
            if (!$evrak) {
                throw new \RuntimeException('Evrak bulunamadı.');
            }
            if (($evrak->onay_durumu ?? 'taslak') === 'onaylandi') {
                throw new \RuntimeException('Evrak daha önce onaylanmış.');
            }
            if (($evrak->onay_durumu ?? 'taslak') !== 'onay_bekliyor') {
                throw new \RuntimeException('Evrak henüz elektronik imza onayına sunulmamış.');
            }
            $sirada = $this->getNextPendingSigner($evrakId);
            if (!$sirada) {
                throw new \RuntimeException('Bu evrak için bekleyen imza bulunmuyor.');
            }
            if ((int) $sirada->kullanici_id !== $userId) {
                throw new \RuntimeException('İmza sırası sizde değil. Sırada ' . $sirada->adi_soyadi . ' bulunuyor.');
            }
            $approve = $this->db->prepare("UPDATE evrak_takip_onaylari SET durum = 'onaylandi', onay_tarihi = NOW(), ip_adresi = :ip WHERE evrak_id = :evrak_id AND firma_id = :firma_id AND kullanici_id = :kullanici_id AND durum = 'bekliyor'");
            $approve->execute(['ip' => mb_substr($ipAddress, 0, 45), 'evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id'], 'kullanici_id' => $userId]);
            if ($approve->rowCount() !== 1) {
                throw new \RuntimeException('Bu evrak için bekleyen imza yetkiniz bulunmuyor.');
            }
            $count = $this->db->prepare("SELECT COUNT(*) toplam, SUM(durum = 'onaylandi') onaylanan FROM evrak_takip_onaylari WHERE evrak_id = :evrak_id AND firma_id = :firma_id");
            $count->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $state = $count->fetch(PDO::FETCH_ASSOC);
            $completed = (int) $state['toplam'] > 0 && (int) $state['toplam'] === (int) $state['onaylanan'];
            $digest = self::belgeOzeti($evrak);
            $update = $this->db->prepare("UPDATE {$this->table} SET onay_durumu = :durum, e_imza_onay_tarihi = :tarih, e_imza_belge_ozeti = :ozet WHERE id = :id AND firma_id = :firma_id");
            $update->execute([
                'durum' => $completed ? 'onaylandi' : 'onay_bekliyor',
                'tarih' => $completed ? date('Y-m-d H:i:s') : null,
                'ozet' => $digest,
                'id' => $evrakId,
                'firma_id' => $_SESSION['firma_id'],
            ]);
            $this->db->commit();
            return ['completed' => $completed, 'approved' => (int) $state['onaylanan'], 'total' => (int) $state['toplam']];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function ensureApprovalRowsForDrafts(): void
    {
        $sql = $this->db->prepare("SELECT et.id, et.imza_kullanici_ids
            FROM {$this->table} et
            LEFT JOIN evrak_takip_onaylari o ON o.evrak_id = et.id AND o.firma_id = et.firma_id
            WHERE et.firma_id = :firma_id
              AND et.evrak_tipi = 'giden'
              AND et.onay_durumu = 'taslak'
              AND et.silinme_tarihi IS NULL
              AND et.imza_kullanici_ids IS NOT NULL
              AND et.imza_kullanici_ids NOT IN ('', '[]')
              AND o.id IS NULL");
        $sql->execute(['firma_id' => $_SESSION['firma_id']]);
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);
        if ($rows === []) {
            return;
        }
        $insert = $this->db->prepare("INSERT IGNORE INTO evrak_takip_onaylari (evrak_id, firma_id, kullanici_id, sira, durum) VALUES (:evrak_id, :firma_id, :kullanici_id, :sira, 'bekliyor')");
        foreach ($rows as $row) {
            $signerIds = json_decode((string) $row->imza_kullanici_ids, true) ?: [];
            $sira = 1;
            foreach ($this->getSigningUsersByIds($signerIds) as $signer) {
                $insert->execute([
                    'evrak_id' => (int) $row->id,
                    'firma_id' => $_SESSION['firma_id'],
                    'kullanici_id' => (int) $signer->id,
                    'sira' => $sira++,
                ]);
            }
        }
    }

    public function getApprovalSummaryMap(int $userId): array
    {
        $sql = $this->db->prepare("SELECT o.evrak_id,
                COUNT(*) AS toplam,
                SUM(o.durum = 'onaylandi') AS onaylanan,
                MIN(CASE WHEN o.durum = 'bekliyor' THEN o.sira END) AS siradaki_sira,
                MIN(CASE WHEN o.durum = 'bekliyor' AND o.kullanici_id = :kullanici_id THEN o.sira END) AS benim_sira
            FROM evrak_takip_onaylari o
            WHERE o.firma_id = :firma_id
            GROUP BY o.evrak_id");
        $sql->execute(['kullanici_id' => $userId, 'firma_id' => $_SESSION['firma_id']]);
        $map = [];
        foreach ($sql->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $benimSira = $row['benim_sira'] !== null ? (int) $row['benim_sira'] : null;
            $siradakiSira = $row['siradaki_sira'] !== null ? (int) $row['siradaki_sira'] : null;
            $map[(int) $row['evrak_id']] = [
                'toplam' => (int) $row['toplam'],
                'onaylanan' => (int) $row['onaylanan'],
                'bekleyen_imzam' => $benimSira !== null,
                'sira_bende' => $benimSira !== null && $benimSira === $siradakiSira,
            ];
        }

        $siradakiler = $this->db->prepare("SELECT o.evrak_id, o.sira, u.adi_soyadi
            FROM evrak_takip_onaylari o
            INNER JOIN users u ON u.id = o.kullanici_id
            WHERE o.firma_id = :firma_id AND o.durum = 'bekliyor'
            ORDER BY o.evrak_id ASC, o.sira ASC, o.id ASC");
        $siradakiler->execute(['firma_id' => $_SESSION['firma_id']]);
        foreach ($siradakiler->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $evrakId = (int) $row['evrak_id'];
            if (isset($map[$evrakId]) && !isset($map[$evrakId]['siradaki_kisi'])) {
                $map[$evrakId]['siradaki_kisi'] = (string) $row['adi_soyadi'];
            }
        }
        return $map;
    }

    public function submitForApproval(int $evrakId, int $userId = 0, string $ipAddress = ''): array
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL FOR UPDATE");
            $lock->execute(['id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $evrak = $lock->fetch(PDO::FETCH_OBJ);
            if (!$evrak) {
                throw new \RuntimeException('Evrak bulunamadı.');
            }
            if (($evrak->onay_durumu ?? 'taslak') !== 'taslak') {
                throw new \RuntimeException('Bu evrak zaten elektronik imza onayına sunulmuş.');
            }
            $signers = $this->getSigningUsersByIds(json_decode((string) ($evrak->imza_kullanici_ids ?? '[]'), true) ?: []);
            if ($signers === []) {
                throw new \RuntimeException('Onaya sunmak için evrakta en az bir imza atacak kişi tanımlı olmalıdır.');
            }
            $delete = $this->db->prepare('DELETE FROM evrak_takip_onaylari WHERE evrak_id = :evrak_id AND firma_id = :firma_id');
            $delete->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $insert = $this->db->prepare("INSERT INTO evrak_takip_onaylari (evrak_id, firma_id, kullanici_id, sira, durum) VALUES (:evrak_id, :firma_id, :kullanici_id, :sira, 'bekliyor')");
            $sira = 1;
            foreach ($signers as $signer) {
                $insert->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id'], 'kullanici_id' => (int) $signer->id, 'sira' => $sira++]);
            }
            $update = $this->db->prepare("UPDATE {$this->table} SET onay_durumu = 'onay_bekliyor', e_imza_onay_tarihi = NULL, e_imza_belge_ozeti = NULL, e_imza_iade_gerekcesi = NULL, e_imza_iade_kullanici_id = NULL, e_imza_iade_tarihi = NULL WHERE id = :id AND firma_id = :firma_id");
            $update->execute(['id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);

            $olusturanId = (int) ($evrak->olusturan_kullanici_id ?? 0);
            $otomatikImza = $userId > 0
                && $olusturanId === $userId
                && (int) $signers[0]->id === $userId;
            $tamamlandi = false;

            if ($otomatikImza) {
                $imzala = $this->db->prepare("UPDATE evrak_takip_onaylari SET durum = 'onaylandi', onay_tarihi = NOW(), ip_adresi = :ip WHERE evrak_id = :evrak_id AND firma_id = :firma_id AND kullanici_id = :kullanici_id");
                $imzala->execute([
                    'ip' => mb_substr($ipAddress, 0, 45),
                    'evrak_id' => $evrakId,
                    'firma_id' => $_SESSION['firma_id'],
                    'kullanici_id' => $userId,
                ]);
                $tamamlandi = count($signers) === 1;
                $tamamla = $this->db->prepare("UPDATE {$this->table} SET onay_durumu = :durum, e_imza_onay_tarihi = :tarih, e_imza_belge_ozeti = :ozet WHERE id = :id AND firma_id = :firma_id");
                $tamamla->execute([
                    'durum' => $tamamlandi ? 'onaylandi' : 'onay_bekliyor',
                    'tarih' => $tamamlandi ? date('Y-m-d H:i:s') : null,
                    'ozet' => self::belgeOzeti($evrak),
                    'id' => $evrakId,
                    'firma_id' => $_SESSION['firma_id'],
                ]);
            }

            $this->db->commit();
            return [
                'total' => count($signers),
                'signers' => array_map(fn($signer) => (string) $signer->adi_soyadi, $signers),
                'otomatik_imza' => $otomatikImza,
                'completed' => $tamamlandi,
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function rejectApproval(int $evrakId, int $userId, string $gerekce): array
    {
        $gerekce = trim($gerekce);
        if (mb_strlen($gerekce, 'UTF-8') < 5) {
            throw new \RuntimeException('İade gerekçesini en az 5 karakter olacak şekilde yazınız.');
        }
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL FOR UPDATE");
            $lock->execute(['id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $evrak = $lock->fetch(PDO::FETCH_OBJ);
            if (!$evrak) {
                throw new \RuntimeException('Evrak bulunamadı.');
            }
            if (($evrak->onay_durumu ?? 'taslak') !== 'onay_bekliyor') {
                throw new \RuntimeException('Yalnızca imza sürecinde olan evrak iade edilebilir.');
            }
            $sirada = $this->getNextPendingSigner($evrakId);
            if (!$sirada || (int) $sirada->kullanici_id !== $userId) {
                throw new \RuntimeException('Bu evrakı iade etme yetkiniz bulunmuyor.');
            }
            $reset = $this->db->prepare("UPDATE evrak_takip_onaylari SET durum = 'bekliyor', onay_tarihi = NULL, ip_adresi = NULL WHERE evrak_id = :evrak_id AND firma_id = :firma_id");
            $reset->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $update = $this->db->prepare("UPDATE {$this->table}
                SET onay_durumu = 'taslak', e_imza_onay_tarihi = NULL, e_imza_belge_ozeti = NULL,
                    e_imza_iade_gerekcesi = :gerekce, e_imza_iade_kullanici_id = :kullanici_id, e_imza_iade_tarihi = NOW()
                WHERE id = :id AND firma_id = :firma_id");
            $update->execute([
                'gerekce' => mb_substr($gerekce, 0, 2000, 'UTF-8'),
                'kullanici_id' => $userId,
                'id' => $evrakId,
                'firma_id' => $_SESSION['firma_id'],
            ]);
            $this->db->commit();
            return ['gerekce' => mb_substr($gerekce, 0, 2000, 'UTF-8')];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private static function belgeOzeti(object $evrak): string
    {
        return hash('sha256', implode('|', [
            $evrak->tarih ?? '',
            $evrak->evrak_no ?? '',
            $evrak->konu ?? '',
            $evrak->kurum_adi ?? '',
            $evrak->aciklama ?? '',
            $evrak->imza_kullanici_ids ?? '',
        ]));
    }

    public function canRevokeApproval(object $evrak, int $userId): bool
    {
        if (($evrak->onay_durumu ?? 'taslak') !== 'onay_bekliyor') {
            return false;
        }
        return $userId > 0 && (int) ($evrak->olusturan_kullanici_id ?? 0) === $userId;
    }

    public function revokeApproval(int $evrakId, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND firma_id = :firma_id AND silinme_tarihi IS NULL FOR UPDATE");
            $lock->execute(['id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $evrak = $lock->fetch(PDO::FETCH_OBJ);
            if (!$evrak) {
                throw new \RuntimeException('Evrak bulunamadı.');
            }
            if (($evrak->onay_durumu ?? 'taslak') === 'taslak') {
                throw new \RuntimeException('Bu evrakta geri alınacak bir imza süreci bulunmuyor.');
            }
            if (($evrak->onay_durumu ?? 'taslak') === 'onaylandi') {
                throw new \RuntimeException('İmza süreci tamamlanmış evrak geri alınamaz.');
            }
            if (!$this->canRevokeApproval($evrak, $userId)) {
                throw new \RuntimeException('Evrakı yalnızca onu oluşturan kullanıcı geri alabilir.');
            }
            $reset = $this->db->prepare("UPDATE evrak_takip_onaylari SET durum = 'bekliyor', onay_tarihi = NULL, ip_adresi = NULL WHERE evrak_id = :evrak_id AND firma_id = :firma_id");
            $reset->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $update = $this->db->prepare("UPDATE {$this->table} SET onay_durumu = 'taslak', e_imza_onay_tarihi = NULL, e_imza_belge_ozeti = NULL WHERE id = :id AND firma_id = :firma_id");
            $update->execute(['id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
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
