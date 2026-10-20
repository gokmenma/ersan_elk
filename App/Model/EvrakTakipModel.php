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
            if (!in_array('ust_yazi_gerekli_degil', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN ust_yazi_gerekli_degil tinyint(1) NOT NULL DEFAULT 0 AFTER aciklama");
            }
            if (!in_array('son_tarih', $cols)) {
                $this->db->exec("ALTER TABLE {$this->table} ADD COLUMN son_tarih date DEFAULT NULL AFTER tarih");
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
     * DataTables sunucu taraflı evrak listesi.
     * Arama, kolon filtreleri, sıralama ve sayfalama veritabanında uygulanır.
     */
    public function ajaxList(array $params, int $firmaId, int $userId): array
    {
        $draw = max(0, (int) ($params['draw'] ?? 0));
        $start = max(0, (int) ($params['start'] ?? 0));
        $length = min(100, max(10, (int) ($params['length'] ?? 10)));
        $bindings = ['firma_id' => $firmaId];
        $where = ['et.firma_id = :firma_id', 'et.silinme_tarihi IS NULL'];

        $globalSearch = trim((string) ($params['search']['value'] ?? ''));
        if ($globalSearch !== '') {
            $where[] = '(et.evrak_no LIKE :global_search OR et.konu LIKE :global_search OR et.kurum_adi LIKE :global_search OR p1.adi_soyadi LIKE :global_search OR p2.adi_soyadi LIKE :global_search OR u.adi_soyadi LIKE :global_search OR u.user_name LIKE :global_search)';
            $bindings['global_search'] = '%' . $globalSearch . '%';
        }

        $columnMap = [1 => 'et.evrak_tipi', 3 => 'et.tarih', 4 => 'et.konu', 5 => 'et.kurum_adi', 6 => "COALESCE(NULLIF(u.adi_soyadi, ''), u.user_name)", 7 => 'p1.adi_soyadi', 8 => 'p2.adi_soyadi', 9 => 'et.cevap_verildi_mi', 10 => 'et.onay_durumu'];
        foreach ((array) ($params['columns'] ?? []) as $index => $column) {
            $rawValue = trim((string) ($column['search']['value'] ?? ''));
            if ($rawValue === '' || !isset($columnMap[$index])) continue;
            $mode = 'contains';
            $value = $rawValue;
            if (preg_match('/^([a-z_]+):(.*)$/s', $rawValue, $matches)) {
                $mode = $matches[1];
                $value = trim($matches[2]);
            }
            $key = 'column_' . $index;
            if ($mode === 'multi' && in_array((int) $index, [1, 9, 10], true)) {
                $values = array_values(array_filter(explode('|', $value), static fn(string $item): bool => $item !== ''));
                if ($values === []) continue;
                if ((int) $index === 9) {
                    $replyClauses = [];
                    if (in_array('EVET', $values, true)) $replyClauses[] = 'et.cevap_verildi_mi = 1';
                    if (in_array('BEKLEMEDE', $values, true)) $replyClauses[] = '(et.cevap_verildi_mi = 0 OR et.cevap_verildi_mi IS NULL)';
                    if ($replyClauses) $where[] = '(' . implode(' OR ', $replyClauses) . ')';
                } else {
                    $statusMap = ['ONAYLI' => 'onaylandi', 'ONAYDA' => 'onay_bekliyor', 'İMZANIZDA' => 'onay_bekliyor', 'TASLAK' => 'taslak'];
                    if ((int) $index === 10) $values = array_values(array_unique(array_map(static fn(string $item): string => $statusMap[mb_strtoupper($item, 'UTF-8')] ?? $item, $values)));
                    $placeholders = [];
                    foreach ($values as $valueIndex => $selectedValue) {
                        $bindingKey = $key . '_' . $valueIndex;
                        $placeholders[] = ':' . $bindingKey;
                        $bindings[$bindingKey] = $selectedValue;
                    }
                    $where[] = $columnMap[$index] . ' IN (' . implode(',', $placeholders) . ')';
                }
            } elseif ((int) $index === 3) {
                $dateValues = explode('|', $value);
                $normalizeDate = static function (string $date): string {
                    $parts = preg_split('/[.\/\-]/', trim($date));
                    return count($parts) === 3 && strlen((string) $parts[0]) <= 2
                        ? sprintf('%04d-%02d-%02d', (int) $parts[2], (int) $parts[1], (int) $parts[0])
                        : trim($date);
                };
                if ($mode === 'null' || $mode === 'not_null') {
                    $where[] = $mode === 'null' ? 'et.tarih IS NULL' : 'et.tarih IS NOT NULL';
                } elseif ($mode === 'between' && count($dateValues) === 2) {
                    $where[] = 'et.tarih BETWEEN :' . $key . '_from AND :' . $key . '_to';
                    $bindings[$key . '_from'] = $normalizeDate($dateValues[0]);
                    $bindings[$key . '_to'] = $normalizeDate($dateValues[1]);
                } else {
                    $operator = $mode === 'before' ? '<' : ($mode === 'after' ? '>' : '=');
                    $where[] = 'et.tarih ' . $operator . ' :' . $key;
                    $bindings[$key] = $normalizeDate($value);
                }
            } elseif ((int) $index === 9) {
                $where[] = $value === 'EVET' ? 'et.cevap_verildi_mi = 1' : '(et.cevap_verildi_mi = 0 OR et.cevap_verildi_mi IS NULL)';
            } elseif ((int) $index === 10) {
                $statusMap = ['ONAYLI' => 'onaylandi', 'ONAYDA' => 'onay_bekliyor', 'İMZANIZDA' => 'onay_bekliyor', 'TASLAK' => 'taslak'];
                $where[] = 'et.onay_durumu = :' . $key;
                $bindings[$key] = $statusMap[mb_strtoupper($value, 'UTF-8')] ?? $value;
            } else {
                if ($mode === 'null' || $mode === 'not_null') {
                    $where[] = $mode === 'null'
                        ? '(' . $columnMap[$index] . " IS NULL OR " . $columnMap[$index] . " = '')"
                        : '(' . $columnMap[$index] . " IS NOT NULL AND " . $columnMap[$index] . " <> '')";
                } else {
                    $operator = in_array($mode, ['not_contains', 'not_equals'], true) ? 'NOT LIKE' : 'LIKE';
                    $where[] = $columnMap[$index] . ' ' . $operator . ' :' . $key;
                    $bindings[$key] = match ($mode) {
                        'equals', 'not_equals' => $value,
                        'starts_with' => $value . '%',
                        'ends_with' => '%' . $value,
                        default => '%' . $value . '%',
                    };
                }
            }
        }

        $quickFilter = (string) ($params['quick_filter'] ?? 'all');
        if (in_array($quickFilter, ['gelen', 'giden'], true)) {
            $where[] = 'et.evrak_tipi = :quick_type';
            $bindings['quick_type'] = $quickFilter;
        } elseif ($quickFilter === 'cevap_bekleyen') {
            $where[] = "et.evrak_tipi = 'gelen' AND (et.cevap_verildi_mi = 0 OR et.cevap_verildi_mi IS NULL)";
        }
        if (!empty($params['signature_only'])) {
            $where[] = "EXISTS (SELECT 1 FROM evrak_takip_onaylari mine WHERE mine.evrak_id = et.id AND mine.firma_id = et.firma_id AND mine.kullanici_id = :current_user AND mine.durum = 'bekliyor' AND mine.sira = (SELECT MIN(next_o.sira) FROM evrak_takip_onaylari next_o WHERE next_o.evrak_id = et.id AND next_o.firma_id = et.firma_id AND next_o.durum = 'bekliyor'))";
            $bindings['current_user'] = $userId;
        }

        $from = " FROM {$this->table} et
            LEFT JOIN personel p1 ON p1.id = et.personel_id AND p1.firma_id = et.firma_id
            LEFT JOIN personel p2 ON p2.id = et.ilgili_personel_id AND p2.firma_id = et.firma_id
            LEFT JOIN users u ON u.id = et.olusturan_kullanici_id AND u.silinme_tarihi IS NULL";
        $whereSql = ' WHERE ' . implode(' AND ', $where);
        $totalStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE firma_id = :firma_id AND silinme_tarihi IS NULL");
        $totalStmt->execute(['firma_id' => $firmaId]);
        $total = (int) $totalStmt->fetchColumn();
        $filteredStmt = $this->db->prepare('SELECT COUNT(*)' . $from . $whereSql);
        $filteredStmt->execute($bindings);
        $filtered = (int) $filteredStmt->fetchColumn();

        $orderColumn = (int) ($params['order'][0]['column'] ?? 3);
        $orderDirection = strtolower((string) ($params['order'][0]['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';
        $orderMap = [1 => 'et.evrak_tipi', 3 => 'et.tarih', 4 => 'et.konu', 5 => 'et.kurum_adi', 6 => "COALESCE(NULLIF(u.adi_soyadi, ''), u.user_name)", 7 => 'p1.adi_soyadi', 8 => 'p2.adi_soyadi', 9 => 'et.cevap_verildi_mi', 10 => 'et.onay_durumu'];
        $orderBy = $orderMap[$orderColumn] ?? 'et.tarih';
        $sql = "SELECT et.*, p1.adi_soyadi AS personel_adi, p2.adi_soyadi AS ilgili_personel_adi,
                    COALESCE(NULLIF(u.adi_soyadi, ''), NULLIF(u.user_name, '')) AS islem_yapan_adi" . $from . $whereSql
            . " ORDER BY {$orderBy} {$orderDirection}, et.id DESC LIMIT :start, :length";
        $stmt = $this->db->prepare($sql);
        foreach ($bindings as $key => $value) $stmt->bindValue(':' . $key, $value);
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
        $stmt->execute();
        return ['draw' => $draw, 'recordsTotal' => $total, 'recordsFiltered' => $filtered, 'rows' => $stmt->fetchAll(PDO::FETCH_OBJ), 'start' => $start];
    }

    public function countPendingSignatureForUser(int $userId): int
    {
        $sql = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} et
            WHERE et.firma_id = :firma_id AND et.silinme_tarihi IS NULL AND et.onay_durumu = 'onay_bekliyor'
              AND EXISTS (SELECT 1 FROM evrak_takip_onaylari mine
                WHERE mine.evrak_id = et.id AND mine.firma_id = et.firma_id AND mine.kullanici_id = :user_id
                  AND mine.durum = 'bekliyor' AND mine.sira = (SELECT MIN(next_o.sira) FROM evrak_takip_onaylari next_o
                    WHERE next_o.evrak_id = et.id AND next_o.firma_id = et.firma_id AND next_o.durum = 'bekliyor'))");
        $sql->execute(['firma_id' => (int) $_SESSION['firma_id'], 'user_id' => $userId]);
        return (int) $sql->fetchColumn();
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
              AND a.firma_id = :firma_id
              AND p.firma_id = :firma_id
              AND az.silinme_tarihi IS NULL
              AND a.silinme_tarihi IS NULL
              AND p.silinme_tarihi IS NULL
              AND az.zimmet_tarihi <= :tarih
              AND (az.iade_tarihi IS NULL OR az.iade_tarihi = '0000-00-00' OR az.iade_tarihi >= :tarih)
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
        throw new \RuntimeException('Sanal onay devre dışıdır. Evrak akıllı kart ile PAdES olarak imzalanmalıdır.');
    }

    public function approveWithPades(int $evrakId, int $userId, string $ipAddress, string $pdfPath, string $pdfSha256, array $certificate): array
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
            $approve = $this->db->prepare("UPDATE evrak_takip_onaylari SET durum = 'onaylandi', onay_tarihi = NOW(), ip_adresi = :ip,
                imza_turu = 'PAdES-BES', sertifika_ozne = :subject, sertifika_veren = :issuer,
                sertifika_seri_no = :serial, sertifika_parmak_izi = :fingerprint,
                sertifika_gecerlilik_baslangic = :valid_from, sertifika_gecerlilik_bitis = :valid_to,
                imzali_pdf_sha256 = :pdf_sha256, imza_dogrulama_durumu = 'dogrulandi'
                WHERE evrak_id = :evrak_id AND firma_id = :firma_id AND kullanici_id = :kullanici_id AND durum = 'bekliyor'");
            $approve->execute([
                'ip' => mb_substr($ipAddress, 0, 45), 'subject' => $certificate['subject'], 'issuer' => $certificate['issuer'],
                'serial' => $certificate['serial'], 'fingerprint' => $certificate['fingerprint'],
                'valid_from' => $certificate['valid_from'], 'valid_to' => $certificate['valid_to'], 'pdf_sha256' => $pdfSha256,
                'evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id'], 'kullanici_id' => $userId,
            ]);
            if ($approve->rowCount() !== 1) {
                throw new \RuntimeException('Bu evrak için bekleyen imza yetkiniz bulunmuyor.');
            }
            $count = $this->db->prepare("SELECT COUNT(*) toplam, SUM(durum = 'onaylandi') onaylanan FROM evrak_takip_onaylari WHERE evrak_id = :evrak_id AND firma_id = :firma_id");
            $count->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $state = $count->fetch(PDO::FETCH_ASSOC);
            $completed = (int) $state['toplam'] > 0 && (int) $state['toplam'] === (int) $state['onaylanan'];
            $update = $this->db->prepare("UPDATE {$this->table} SET onay_durumu = :durum,
                e_imza_onay_tarihi = IF(:tamamlandi, NOW(), NULL), e_imza_belge_ozeti = :ozet,
                pades_pdf_yolu = :pdf_yolu, pades_pdf_sha256 = :pdf_sha256
                WHERE id = :id AND firma_id = :firma_id");
            $update->execute([
                'durum' => $completed ? 'onaylandi' : 'onay_bekliyor',
                'tamamlandi' => $completed ? 1 : 0,
                'ozet' => self::belgeOzeti($evrak),
                'pdf_yolu' => $pdfPath,
                'pdf_sha256' => $pdfSha256,
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

    public function getApprovalSummaryMap(int $userId, array $evrakIds = []): array
    {
        $evrakIds = array_values(array_unique(array_filter(array_map('intval', $evrakIds))));
        $idFilter = $evrakIds ? ' AND o.evrak_id IN (' . implode(',', array_fill(0, count($evrakIds), '?')) . ')' : '';
        $sql = $this->db->prepare("SELECT o.evrak_id,
                COUNT(*) AS toplam,
                SUM(o.durum = 'onaylandi') AS onaylanan,
                MIN(CASE WHEN o.durum = 'bekliyor' THEN o.sira END) AS siradaki_sira,
                MIN(CASE WHEN o.durum = 'bekliyor' AND o.kullanici_id = ? THEN o.sira END) AS benim_sira
            FROM evrak_takip_onaylari o
            WHERE o.firma_id = ?{$idFilter}
            GROUP BY o.evrak_id");
        $sql->execute(array_merge([$userId, (int) $_SESSION['firma_id']], $evrakIds));
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
            WHERE o.firma_id = ? AND o.durum = 'bekliyor'{$idFilter}
            ORDER BY o.evrak_id ASC, o.sira ASC, o.id ASC");
        $siradakiler->execute(array_merge([(int) $_SESSION['firma_id']], $evrakIds));
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

            $this->db->commit();
            return [
                'total' => count($signers),
                'signers' => array_map(fn($signer) => (string) $signer->adi_soyadi, $signers),
                'otomatik_imza' => false,
                'completed' => false,
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
            $reset = $this->db->prepare("UPDATE evrak_takip_onaylari SET durum = 'bekliyor', onay_tarihi = NULL, ip_adresi = NULL,
                imza_turu = NULL, sertifika_ozne = NULL, sertifika_veren = NULL, sertifika_seri_no = NULL,
                sertifika_parmak_izi = NULL, sertifika_gecerlilik_baslangic = NULL, sertifika_gecerlilik_bitis = NULL,
                imzali_pdf_sha256 = NULL, imza_dogrulama_durumu = NULL
                WHERE evrak_id = :evrak_id AND firma_id = :firma_id");
            $reset->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $update = $this->db->prepare("UPDATE {$this->table}
                SET onay_durumu = 'taslak', e_imza_onay_tarihi = NULL, e_imza_belge_ozeti = NULL,
                    pades_pdf_yolu = NULL, pades_pdf_sha256 = NULL,
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

    public function getApprovalDetailMap(array $evrakIds = []): array
    {
        $evrakIds = array_values(array_unique(array_filter(array_map('intval', $evrakIds))));
        $idFilter = $evrakIds ? ' AND o.evrak_id IN (' . implode(',', array_fill(0, count($evrakIds), '?')) . ')' : '';
        $sql = $this->db->prepare("SELECT o.evrak_id, o.kullanici_id, o.sira, o.durum, o.olusturma_tarihi, o.onay_tarihi,
                o.imza_turu, o.sertifika_ozne, o.sertifika_veren, o.sertifika_seri_no,
                o.sertifika_parmak_izi, o.imza_dogrulama_durumu, u.adi_soyadi,
                COALESCE(NULLIF(u.unvani, ''), u.gorevi, '') AS imza_unvani
            FROM evrak_takip_onaylari o
            INNER JOIN users u ON u.id = o.kullanici_id
            WHERE o.firma_id = ?{$idFilter}
            ORDER BY o.evrak_id ASC, o.sira ASC, o.id ASC");
        $sql->execute(array_merge([(int) $_SESSION['firma_id']], $evrakIds));
        $map = [];
        foreach ($sql->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['evrak_id']][] = $row;
        }
        return $map;
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
            $reset = $this->db->prepare("UPDATE evrak_takip_onaylari SET durum = 'bekliyor', onay_tarihi = NULL, ip_adresi = NULL,
                imza_turu = NULL, sertifika_ozne = NULL, sertifika_veren = NULL, sertifika_seri_no = NULL,
                sertifika_parmak_izi = NULL, sertifika_gecerlilik_baslangic = NULL, sertifika_gecerlilik_bitis = NULL,
                imzali_pdf_sha256 = NULL, imza_dogrulama_durumu = NULL
                WHERE evrak_id = :evrak_id AND firma_id = :firma_id");
            $reset->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
            $update = $this->db->prepare("UPDATE {$this->table} SET onay_durumu = 'taslak', e_imza_onay_tarihi = NULL,
                e_imza_belge_ozeti = NULL, pades_pdf_yolu = NULL, pades_pdf_sha256 = NULL
                WHERE id = :id AND firma_id = :firma_id");
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
            SELECT ee.id, ee.dosya_adi, ee.dosya_yolu, ee.mime_tipi, ee.dosya_boyutu,
                   ee.sira, ee.olusturma_tarihi, ee.yukleyen_kullanici_id,
                   COALESCE(u.adi_soyadi, u.user_name, 'Bilinmeyen Kullanıcı') AS yukleyen_kullanici
            FROM evrak_takip_ekleri ee
            LEFT JOIN users u ON u.id = ee.yukleyen_kullanici_id
            WHERE ee.evrak_id = :evrak_id
              AND ee.firma_id = :firma_id
              AND ee.silinme_tarihi IS NULL
            ORDER BY ee.sira ASC, ee.id ASC
        ");
        $sql->execute(['evrak_id' => $evrakId, 'firma_id' => $_SESSION['firma_id']]);
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function getAttachmentById(int $attachmentId): object|false
    {
        $sql = $this->db->prepare("SELECT ee.id, ee.dosya_adi, ee.dosya_yolu, ee.mime_tipi, ee.dosya_boyutu
            FROM evrak_takip_ekleri ee
            INNER JOIN {$this->table} et ON et.id = ee.evrak_id AND et.firma_id = ee.firma_id
            WHERE ee.id = :id AND ee.firma_id = :firma_id AND ee.silinme_tarihi IS NULL
              AND et.silinme_tarihi IS NULL LIMIT 1");
        $sql->execute(['id' => $attachmentId, 'firma_id' => (int) ($_SESSION['firma_id'] ?? 0)]);
        return $sql->fetch(PDO::FETCH_OBJ);
    }

    /**
     * Liste ekranında N+1 sorgu oluşturmadan aktif evrak eklerini getirir.
     *
     * @return array<int, array<int, object>> Evrak ID'sine göre gruplanmış ekler
     */
    public function getAttachmentMap(array $evrakIds = []): array
    {
        $evrakIds = array_values(array_unique(array_filter(array_map('intval', $evrakIds))));
        $idFilter = $evrakIds ? ' AND ee.evrak_id IN (' . implode(',', array_fill(0, count($evrakIds), '?')) . ')' : '';
        $sql = $this->db->prepare("
            SELECT ee.evrak_id, ee.id, ee.dosya_adi, ee.dosya_yolu,
                   ee.mime_tipi, ee.dosya_boyutu, ee.sira
            FROM evrak_takip_ekleri ee
            INNER JOIN {$this->table} et
                    ON et.id = ee.evrak_id
                   AND et.firma_id = ee.firma_id
                   AND et.silinme_tarihi IS NULL
            WHERE ee.firma_id = ?
              AND ee.silinme_tarihi IS NULL
              {$idFilter}
            ORDER BY ee.evrak_id ASC, ee.sira ASC, ee.id ASC
        ");
        $sql->execute(array_merge([(int) $_SESSION['firma_id']], $evrakIds));

        $attachmentMap = [];
        foreach ($sql->fetchAll(PDO::FETCH_OBJ) as $attachment) {
            $attachmentMap[(int) $attachment->evrak_id][] = $attachment;
        }

        return $attachmentMap;
    }

    public function saveAttachment(int $evrakId, array $attachment): int
    {
        if (!$this->getById($evrakId)) {
            throw new \RuntimeException('Evrak bulunamadı.');
        }
        $sql = $this->db->prepare("
            INSERT INTO evrak_takip_ekleri
                (evrak_id, firma_id, dosya_adi, dosya_yolu, mime_tipi, dosya_boyutu, sira, yukleyen_kullanici_id)
            VALUES
                (:evrak_id, :firma_id, :dosya_adi, :dosya_yolu, :mime_tipi, :dosya_boyutu, :sira, :yukleyen_kullanici_id)
        ");
        $sql->execute([
            'evrak_id' => $evrakId,
            'firma_id' => $_SESSION['firma_id'],
            'dosya_adi' => $attachment['dosya_adi'],
            'dosya_yolu' => $attachment['dosya_yolu'],
            'mime_tipi' => $attachment['mime_tipi'] ?? null,
            'dosya_boyutu' => (int) ($attachment['dosya_boyutu'] ?? 0),
            'sira' => (int) ($attachment['sira'] ?? 0),
            'yukleyen_kullanici_id' => (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0) ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function createFromUets(int $firmaId, array $data): int
    {
        $sql = $this->db->prepare("
            INSERT INTO evrak_takip
                (firma_id, evrak_tipi, kaynak_tipi, uets_mesaj_id, tarih, evrak_no, konu, kurum_adi,
                 aciklama, dosya_yolu, onay_durumu, olusturma_tarihi)
            VALUES
                (:firma_id, 'gelen', 'uets', :uets_mesaj_id, :tarih, :evrak_no, :konu, :kurum_adi,
                 :aciklama, :dosya_yolu, 'onaylandi', NOW())
        ");
        $sql->execute([
            'firma_id' => $firmaId,
            'uets_mesaj_id' => (string) $data['uets_mesaj_id'],
            'tarih' => (string) $data['tarih'],
            'evrak_no' => (string) $data['evrak_no'],
            'konu' => (string) $data['konu'],
            'kurum_adi' => (string) $data['kurum_adi'],
            'aciklama' => (string) $data['aciklama'],
            'dosya_yolu' => $data['dosya_yolu'] ?: null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createStandaloneKep(int $firmaId, int $userId, array $data): int
    {
        $evrakNo = !empty($data['evrak_no']) ? (string) $data['evrak_no'] : (string) $this->getMaxEvrakNo('giden');

        $stmt = $this->db->prepare("
            INSERT INTO evrak_takip
                (firma_id, evrak_tipi, gonderim_kanali, kaynak_tipi, kep_mesaj_id, tarih, evrak_no, konu, kurum_adi,
                 aciklama, ust_yazi_gerekli_degil, alici_kep, kep_alicilar, kep_paket_turu, kep_ileti_tipi,
                 kep_ileti_detayi, kep_mesaj_metni, onay_durumu, e_imza_onay_tarihi, olusturan_kullanici_id, olusturma_tarihi)
            VALUES
                (:firma_id, 'giden', 'kep', 'kep', :kep_mesaj_id, CURDATE(), :evrak_no, :konu, :kurum_adi,
                 :aciklama, 1, :alici_kep, :kep_alicilar, 'Standart', :kep_ileti_tipi,
                 :kep_ileti_detayi, :kep_mesaj_metni, 'onaylandi', NOW(), :olusturan_kullanici_id, NOW())
        ");
        $stmt->execute([
            'firma_id' => $firmaId,
            'kep_mesaj_id' => (string) $data['kep_id'],
            'evrak_no' => mb_substr($evrakNo, 0, 100),
            'konu' => (string) $data['konu'],
            'kurum_adi' => mb_substr((string) $data['alici_kep'], 0, 255),
            'aciklama' => (string) $data['icerik'],
            'alici_kep' => mb_substr((string) $data['alici_kep'], 0, 255),
            'kep_alicilar' => (string) $data['alici_kep'],
            'kep_ileti_tipi' => (string) ($data['ileti_tipi'] ?? 'standart'),
            'kep_ileti_detayi' => mb_substr((string) ($data['ileti_detayi'] ?? ''), 0, 500),
            'kep_mesaj_metni' => (string) $data['icerik'],
            'olusturan_kullanici_id' => $userId ?: null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findPossibleUetsDuplicate(int $firmaId, string $uetsMessageId, string $tarih, string $konu, string $kurumAdi): ?object
    {
        $sql = $this->db->prepare("
            SELECT id, evrak_no, tarih, konu, kurum_adi, kaynak_tipi, uets_mesaj_id
            FROM evrak_takip
            WHERE firma_id = :firma_id
              AND evrak_tipi = 'gelen'
              AND silinme_tarihi IS NULL
              AND (
                    uets_mesaj_id = :uets_mesaj_id
                    OR (
                        tarih = :tarih
                        AND LOWER(TRIM(konu)) = LOWER(TRIM(:konu))
                        AND LOWER(TRIM(kurum_adi)) = LOWER(TRIM(:kurum_adi))
                    )
              )
            ORDER BY (uets_mesaj_id = :uets_message_order) DESC, id DESC
            LIMIT 1
        ");
        $sql->execute([
            'firma_id' => $firmaId,
            'uets_mesaj_id' => $uetsMessageId,
            'tarih' => $tarih,
            'konu' => $konu,
            'kurum_adi' => $kurumAdi,
            'uets_message_order' => $uetsMessageId,
        ]);

        $row = $sql->fetch(PDO::FETCH_OBJ);
        return $row ?: null;
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

    /**
     * Takvim ve dashboard alarmları için son tarihi olan gelen evrakları getirir
     */
    public function getTakvimEvraklar(int $firmaId, ?string $baslangic = null, ?string $bitis = null): array
    {
        $sql = "SELECT et.id, et.evrak_no, et.konu, et.kurum_adi, et.tarih, et.son_tarih, et.cevap_verildi_mi,
                       p.adi_soyadi as personel_adi
                FROM {$this->table} et
                LEFT JOIN personel p ON et.personel_id = p.id
                WHERE et.firma_id = :firma_id 
                  AND et.silinme_tarihi IS NULL 
                  AND et.evrak_tipi = 'gelen'
                  AND et.son_tarih IS NOT NULL";
        
        $params = [':firma_id' => $firmaId];
        
        if ($baslangic && $bitis) {
            $sql .= " AND et.son_tarih BETWEEN :baslangic AND :bitis";
            $params[':baslangic'] = $baslangic;
            $params[':bitis'] = $bitis;
        } elseif ($baslangic) {
            $sql .= " AND et.son_tarih >= :baslangic";
            $params[':baslangic'] = $baslangic;
        }
        
        $sql .= " ORDER BY et.son_tarih ASC, et.id ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }
}
