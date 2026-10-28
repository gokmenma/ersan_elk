<?php
namespace App\Model;

use App\Model\Model;
use App\Service\Gate;
use Exception;
use PDO;

class EvrakSevkModel extends Model
{
    public const PERMISSION = 'evrak-takip/uets-goruntule';

    protected $table = 'evrak_sevk';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public static function isRestricted(object|array|null $evrak): bool
    {
        if ($evrak === null) {
            return false;
        }
        $row = (array) $evrak;
        $uets = ($row['kaynak_tipi'] ?? '') === 'uets' || !empty($row['uets_mesaj_id']);
        return ($row['evrak_tipi'] ?? '') === 'gelen' && $uets;
    }

    public static function currentUserId(): int
    {
        return (int) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? 0);
    }

    public function hasFullAccess(): bool
    {
        if (PHP_SAPI === 'cli') {
            return true;
        }
        return Gate::allows(self::PERMISSION);
    }

    public function canView(object|array|null $evrak, ?int $userId = null): bool
    {
        if (!self::isRestricted($evrak)) {
            return true;
        }
        if ($this->hasFullAccess()) {
            return true;
        }
        $userId ??= self::currentUserId();
        $row = (array) $evrak;
        return $userId > 0 && $this->hasActiveSevk((int) ($row['id'] ?? 0), $userId);
    }

    public function visibilitySql(string $alias, array &$bindings): string
    {
        if ($this->hasFullAccess()) {
            return '1 = 1';
        }
        $bindings['uets_gorunurluk_user'] = self::currentUserId();
        return "(NOT ({$alias}.evrak_tipi = 'gelen' AND (COALESCE({$alias}.kaynak_tipi, '') = 'uets' OR COALESCE({$alias}.uets_mesaj_id, '') <> ''))
            OR EXISTS (SELECT 1 FROM evrak_sevk sv
                       WHERE sv.evrak_id = {$alias}.id AND sv.firma_id = {$alias}.firma_id
                         AND sv.alan_user_id = :uets_gorunurluk_user AND sv.is_active = 1 AND sv.deleted_at IS NULL))";
    }

    public function canViewFilePath(string $relativePath, int $firmaId): bool
    {
        if ($this->hasFullAccess()) {
            return true;
        }
        $path = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($path === '') {
            return true;
        }
        try {
            foreach ($this->evraklarByFile($path, $firmaId) as $evrak) {
                if (!$this->canView($evrak)) {
                    return false;
                }
            }
            return true;
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::canViewFilePath Error: ' . $e->getMessage());
            return false;
        }
    }

    public function recordFileOpen(string $relativePath, int $firmaId, int $userId, string $fileName): void
    {
        $path = ltrim(str_replace('\\', '/', $relativePath), '/');
        try {
            foreach ($this->evraklarByFile($path, $firmaId) as $evrak) {
                if (!self::isRestricted($evrak)) {
                    continue;
                }
                $this->recordHistory($firmaId, (int) $evrak->id, 'dosya_acildi', $userId, null, mb_substr($fileName, 0, 200));
                $this->markSeen($firmaId, (int) $evrak->id, $userId);
            }
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::recordFileOpen Error: ' . $e->getMessage());
        }
    }

    private function evraklarByFile(string $path, int $firmaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT et.id, et.evrak_tipi, et.kaynak_tipi, et.uets_mesaj_id
             FROM evrak_takip et
             LEFT JOIN evrak_takip_ekleri ee ON ee.evrak_id = et.id AND ee.firma_id = et.firma_id AND ee.silinme_tarihi IS NULL
             LEFT JOIN uets_mesajlar um ON um.evrak_id = et.id AND um.firm_id = et.firma_id
             WHERE et.firma_id = :firma_id AND et.silinme_tarihi IS NULL
               AND (et.dosya_yolu = :p1 OR ee.dosya_yolu = :p2 OR um.yerel_dosya_yolu = :p3 OR um.ham_eyp_yolu = :p4)"
        );
        $stmt->execute(['firma_id' => $firmaId, 'p1' => $path, 'p2' => $path, 'p3' => $path, 'p4' => $path]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public const UNREAD_WINDOW_DAYS = 30;
    public const REMINDER_MIN_AGE_HOURS = 2;
    public const REMINDER_INTERVAL_HOURS = 6;
    private const REMINDER_TITLE = 'UETS Evrakı Sevk Bekliyor';

    public function unforwardedUets(int $firmaId, string $sinceDate, int $limit = 3): array
    {
        $empty = ['count' => 0, 'items' => []];
        if ($firmaId <= 0 || strtotime($sinceDate) === false) {
            return $empty;
        }
        $from = "FROM evrak_takip et
                 WHERE et.firma_id = :firma_id AND et.silinme_tarihi IS NULL
                   AND et.evrak_tipi = 'gelen'
                   AND (COALESCE(et.kaynak_tipi, '') = 'uets' OR COALESCE(et.uets_mesaj_id, '') <> '')
                   AND COALESCE(et.cevap_verildi_mi, 0) = 0
                   AND et.olusturma_tarihi >= :since
                   AND et.olusturma_tarihi <= DATE_SUB(NOW(), INTERVAL " . self::REMINDER_MIN_AGE_HOURS . " HOUR)
                   AND NOT EXISTS (SELECT 1 FROM evrak_sevk sv
                                   WHERE sv.evrak_id = et.id AND sv.firma_id = et.firma_id AND sv.is_active = 1 AND sv.deleted_at IS NULL)";
        $params = ['firma_id' => $firmaId, 'since' => date('Y-m-d 00:00:00', strtotime($sinceDate))];
        try {
            $count = $this->db->prepare("SELECT COUNT(*) " . $from);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            if ($total === 0 || $limit <= 0) {
                return ['count' => $total, 'items' => []];
            }
            $list = $this->db->prepare(
                "SELECT et.id, et.konu, et.kurum_adi " . $from . " ORDER BY et.olusturma_tarihi DESC, et.id DESC LIMIT " . max(1, min(20, $limit))
            );
            $list->execute($params);
            return ['count' => $total, 'items' => $list->fetchAll(PDO::FETCH_OBJ)];
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::unforwardedUets Error: ' . $e->getMessage());
            return $empty;
        }
    }

    public function remindUnforwarded(int $firmaId, string $sinceDate): int
    {
        $pending = $this->unforwardedUets($firmaId, $sinceDate, 3);
        if ($pending['count'] === 0) {
            return 0;
        }

        $subjects = array_map(
            static fn(object $row): string => trim((string) ($row->kurum_adi ?: '-')) . ' - ' . mb_substr((string) ($row->konu ?: 'Belirtilmedi'), 0, 60),
            $pending['items']
        );
        $message = $pending['count'] . ' adet UETS tebligat evrakı henüz hiçbir personele sevk edilmedi: ' . implode('; ', $subjects)
            . ($pending['count'] > count($subjects) ? ' ve diğerleri' : '') . '.';

        $notifier = new BildirimModel();
        $recent = $this->db->prepare(
            "SELECT 1 FROM bildirimler WHERE user_id = :user_id AND title = :title
               AND created_at >= DATE_SUB(NOW(), INTERVAL " . self::REMINDER_INTERVAL_HOURS . " HOUR) LIMIT 1"
        );
        $sent = 0;
        foreach ($notifier->userIdsByPermissionForFirm($firmaId, self::PERMISSION) as $userId) {
            $recent->execute(['user_id' => $userId, 'title' => self::REMINDER_TITLE]);
            if ($recent->fetchColumn()) {
                continue;
            }
            if ($notifier->createNotificationWithPush($userId, self::REMINDER_TITLE, $message, 'evrak-takip/list', 'envelope', 'danger')) {
                $sent++;
            }
        }

        if ($sent > 0) {
            try {
                (new SystemLogModel())->logActionForFirm(
                    $firmaId,
                    0,
                    'UETS_SEVK_HATIRLATMA',
                    "Sevk edilmemiş {$pending['count']} UETS evrakı için {$sent} kullanıcıya hatırlatma bildirimi gönderildi."
                );
            } catch (\Throwable $e) {
                error_log('EvrakSevkModel hatırlatma log hatası: ' . $e->getMessage());
            }
        }
        return $sent;
    }

    public function unreadUets(int $firmaId, int $userId, int $limit = 5): array
    {
        $empty = ['count' => 0, 'items' => []];
        if ($firmaId <= 0 || $userId <= 0) {
            return $empty;
        }
        $bindings = [];
        $visibility = $this->visibilitySql('et', $bindings);
        $from = "FROM evrak_takip et
                 WHERE et.firma_id = :firma_id AND et.silinme_tarihi IS NULL
                   AND et.evrak_tipi = 'gelen'
                   AND (COALESCE(et.kaynak_tipi, '') = 'uets' OR COALESCE(et.uets_mesaj_id, '') <> '')
                   AND et.olusturma_tarihi >= DATE_SUB(NOW(), INTERVAL " . self::UNREAD_WINDOW_DAYS . " DAY)
                   AND {$visibility}
                   AND NOT EXISTS (SELECT 1 FROM evrak_tarihce h
                                   WHERE h.evrak_id = et.id AND h.firma_id = et.firma_id
                                     AND h.user_id = :okuyan_user AND h.islem IN ('goruntuleme', 'dosya_acildi'))";
        $params = ['firma_id' => $firmaId, 'okuyan_user' => $userId] + $bindings;
        try {
            $count = $this->db->prepare("SELECT COUNT(*) " . $from);
            $count->execute($params);
            $total = (int) $count->fetchColumn();
            if ($total === 0 || $limit <= 0) {
                return ['count' => $total, 'items' => []];
            }
            $list = $this->db->prepare(
                "SELECT et.id, et.evrak_no, et.konu, et.kurum_adi, et.tarih, et.olusturma_tarihi " . $from
                . " ORDER BY et.olusturma_tarihi DESC, et.id DESC LIMIT " . max(1, min(50, $limit))
            );
            $list->execute($params);
            return ['count' => $total, 'items' => $list->fetchAll(PDO::FETCH_OBJ)];
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::unreadUets Error: ' . $e->getMessage());
            return $empty;
        }
    }

    public function readEvrakIds(int $firmaId, int $userId, array $evrakIds): array
    {
        $evrakIds = array_values(array_unique(array_filter(array_map('intval', $evrakIds))));
        if ($evrakIds === [] || $userId <= 0) {
            return [];
        }
        $placeholders = [];
        $params = ['firma_id' => $firmaId, 'user_id' => $userId];
        foreach ($evrakIds as $i => $id) {
            $placeholders[] = ':e' . $i;
            $params['e' . $i] = $id;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT DISTINCT evrak_id FROM evrak_tarihce
                 WHERE firma_id = :firma_id AND user_id = :user_id AND islem IN ('goruntuleme', 'dosya_acildi')
                   AND evrak_id IN (" . implode(',', $placeholders) . ")"
            );
            $stmt->execute($params);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::readEvrakIds Error: ' . $e->getMessage());
            return [];
        }
    }

    public function canViewMesaj(?array $mesaj): bool
    {
        if ($mesaj === null) {
            return false;
        }
        if (($mesaj['type'] ?? 'incoming') === 'outgoing' || ($mesaj['yon'] ?? '') === 'giden') {
            return true;
        }
        if ($this->hasFullAccess()) {
            return true;
        }
        $evrakId = (int) ($mesaj['evrak_id'] ?? 0);
        if ($evrakId <= 0) {
            return false;
        }
        $stmt = $this->db->prepare(
            "SELECT id, evrak_tipi, kaynak_tipi, uets_mesaj_id FROM evrak_takip WHERE id = :id AND firma_id = :firma_id LIMIT 1"
        );
        $stmt->execute(['id' => $evrakId, 'firma_id' => (int) ($_SESSION['firma_id'] ?? 0)]);
        $evrak = $stmt->fetch(PDO::FETCH_OBJ);
        return $evrak ? $this->canView($evrak) : false;
    }

    public function hasActiveSevk(int $evrakId, int $userId): bool
    {
        if ($evrakId <= 0 || $userId <= 0) {
            return false;
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT 1 FROM evrak_sevk
                 WHERE evrak_id = :evrak_id AND alan_user_id = :user_id AND is_active = 1 AND deleted_at IS NULL LIMIT 1"
            );
            $stmt->execute(['evrak_id' => $evrakId, 'user_id' => $userId]);
            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::hasActiveSevk Error: ' . $e->getMessage());
            return false;
        }
    }

    public function recipients(int $firmaId, int $ownerId, int $excludeUserId): array
    {
        $stmt = $this->db->prepare(
            "SELECT id, COALESCE(NULLIF(adi_soyadi, ''), user_name) AS adi, COALESCE(NULLIF(unvani, ''), gorevi, '') AS unvan
             FROM users
             WHERE silinme_tarihi IS NULL AND durum = 'Aktif' AND owner_id = :owner_id AND id <> :exclude_id
               AND (firma_ids IS NULL OR firma_ids = '' OR FIND_IN_SET(:firma_id, REPLACE(firma_ids, ' ', '')) > 0)
             ORDER BY adi ASC"
        );
        $stmt->execute(['owner_id' => $ownerId, 'exclude_id' => $excludeUserId, 'firma_id' => (string) $firmaId]);
        return array_values(array_filter(
            $stmt->fetchAll(PDO::FETCH_OBJ),
            fn(object $user): bool => $this->userCanOpenEvrakTakip((int) $user->id)
        ));
    }

    public function userCanOpenEvrakTakip(int $userId): bool
    {
        $roleModel = new UserRoleAssignmentModel();
        $roleIds = $roleModel->activeRoleIdsForUser($userId);
        if (!$roleIds) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM user_roles WHERE id IN ({$placeholders}) AND (superadmin = 1 OR role_type = 'superadmin')"
        );
        $stmt->execute($roleIds);
        if ((int) $stmt->fetchColumn() > 0) {
            return true;
        }
        $keys = (new PermissionsModel())->getPermissionsForUser($userId);
        return in_array('evrak-takip/list', $keys, true);
    }

    public function sevkEt(object $evrak, int $fromUserId, int $toUserId, string $note, int $ownerId): int
    {
        $evrakId = (int) $evrak->id;
        $firmaId = (int) $evrak->firma_id;
        if (!self::isRestricted($evrak)) {
            throw new Exception('Yalnızca UETS ile gelen evraklar sevk edilebilir.');
        }
        if (!$this->canView($evrak, $fromUserId)) {
            throw new Exception('Bu evrakı sevk etme yetkiniz yok.');
        }
        if ($toUserId <= 0 || $toUserId === $fromUserId) {
            throw new Exception('Geçerli bir alıcı personel seçiniz.');
        }
        $validRecipients = array_map(static fn(object $u): int => (int) $u->id, $this->recipients($firmaId, $ownerId, $fromUserId));
        if (!in_array($toUserId, $validRecipients, true)) {
            throw new Exception('Seçilen personel bu evrakı alabilecek yetkide değil.');
        }
        if ($this->hasActiveSevk($evrakId, $toUserId)) {
            throw new Exception('Evrak bu personele zaten sevk edilmiş.');
        }

        $note = mb_substr(trim($note), 0, 500);
        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare(
                "INSERT INTO evrak_sevk (firma_id, evrak_id, sevk_eden_user_id, alan_user_id, sevk_notu, durum, is_active, created_at)
                 VALUES (:firma_id, :evrak_id, :from_id, :to_id, :note, 'bekliyor', 1, NOW())"
            );
            $insert->execute([
                'firma_id' => $firmaId, 'evrak_id' => $evrakId, 'from_id' => $fromUserId,
                'to_id' => $toUserId, 'note' => $note !== '' ? $note : null,
            ]);
            $sevkId = (int) $this->db->lastInsertId();

            $forwarded = $this->db->prepare(
                "UPDATE evrak_sevk SET durum = 'iletildi', iletildi_at = NOW()
                 WHERE evrak_id = :evrak_id AND alan_user_id = :from_id AND is_active = 1 AND deleted_at IS NULL AND durum <> 'iletildi'"
            );
            $forwarded->execute(['evrak_id' => $evrakId, 'from_id' => $fromUserId]);

            $this->recordHistory($firmaId, $evrakId, 'sevk', $fromUserId, $toUserId, $note);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->notifyRecipient($evrak, $fromUserId, $toUserId);
        try {
            (new SystemLogModel())->logActionForFirm(
                $firmaId,
                $fromUserId,
                'UETS_SEVK',
                "UETS evrakı sevk edildi. Evrak ID: {$evrakId}, Alan Kullanıcı ID: {$toUserId}."
            );
        } catch (\Throwable $e) {
            error_log('EvrakSevkModel log hatası: ' . $e->getMessage());
        }
        return $sevkId;
    }

    public function markSeen(int $firmaId, int $evrakId, int $userId): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE evrak_sevk SET durum = 'goruldu', goruldu_at = NOW()
                 WHERE evrak_id = :evrak_id AND alan_user_id = :user_id AND durum = 'bekliyor' AND is_active = 1 AND deleted_at IS NULL"
            );
            $stmt->execute(['evrak_id' => $evrakId, 'user_id' => $userId]);
            if ($stmt->rowCount() > 0) {
                $this->recordHistory($firmaId, $evrakId, 'sevk_goruldu', $userId, null, null);
            }
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::markSeen Error: ' . $e->getMessage());
        }
    }

    public function recordHistory(int $firmaId, int $evrakId, string $islem, int $userId, ?int $targetUserId, ?string $description): void
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO evrak_tarihce (firma_id, evrak_id, islem, user_id, hedef_user_id, aciklama, ip_adresi, user_agent, created_at)
                 VALUES (:firma_id, :evrak_id, :islem, :user_id, :hedef, :aciklama, :ip, :ua, NOW())"
            );
            $stmt->execute([
                'firma_id' => $firmaId,
                'evrak_id' => $evrakId,
                'islem' => mb_substr($islem, 0, 40),
                'user_id' => max(0, $userId),
                'hedef' => $targetUserId,
                'aciklama' => $description !== null && $description !== '' ? mb_substr($description, 0, 500) : null,
                'ip' => mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
                'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null,
            ]);
        } catch (\PDOException $e) {
            error_log('EvrakSevkModel::recordHistory Error: ' . $e->getMessage());
        }
    }

    public function sevkChain(int $firmaId, int $evrakId): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.sevk_notu, s.durum, s.created_at, s.goruldu_at, s.iletildi_at,
                    COALESCE(NULLIF(f.adi_soyadi, ''), f.user_name) AS sevk_eden,
                    COALESCE(NULLIF(t.adi_soyadi, ''), t.user_name) AS alan
             FROM evrak_sevk s
             LEFT JOIN users f ON f.id = s.sevk_eden_user_id
             LEFT JOIN users t ON t.id = s.alan_user_id
             WHERE s.firma_id = :firma_id AND s.evrak_id = :evrak_id AND s.is_active = 1 AND s.deleted_at IS NULL
             ORDER BY s.created_at ASC, s.id ASC"
        );
        $stmt->execute(['firma_id' => $firmaId, 'evrak_id' => $evrakId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function auditTrail(int $firmaId, int $evrakId, int $limit = 500): array
    {
        $stmt = $this->db->prepare(
            "SELECT h.id, h.islem, h.aciklama, h.ip_adresi, h.created_at,
                    COALESCE(NULLIF(u.adi_soyadi, ''), u.user_name) AS islem_yapan,
                    COALESCE(NULLIF(t.adi_soyadi, ''), t.user_name) AS hedef
             FROM evrak_tarihce h
             LEFT JOIN users u ON u.id = h.user_id
             LEFT JOIN users t ON t.id = h.hedef_user_id
             WHERE h.firma_id = :firma_id AND h.evrak_id = :evrak_id
             ORDER BY h.created_at DESC, h.id DESC
             LIMIT :lim"
        );
        $stmt->bindValue(':firma_id', $firmaId, PDO::PARAM_INT);
        $stmt->bindValue(':evrak_id', $evrakId, PDO::PARAM_INT);
        $stmt->bindValue(':lim', max(1, min(1000, $limit)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    private function notifyRecipient(object $evrak, int $fromUserId, int $toUserId): void
    {
        try {
            $from = (new UserModel())->find($fromUserId);
            $fromName = trim((string) ($from->adi_soyadi ?? $from->user_name ?? 'Bir kullanıcı'));
            (new BildirimModel())->createNotification(
                $toUserId,
                'UETS Evrakı Size Sevk Edildi',
                $fromName . ' tarafından size bir UETS tebligat evrakı sevk edildi: ' . mb_substr((string) ($evrak->konu ?? ''), 0, 120),
                'evrak-takip/list',
                'envelope',
                'warning'
            );
        } catch (\Throwable $e) {
            error_log('EvrakSevkModel bildirim hatası: ' . $e->getMessage());
        }
    }
}
