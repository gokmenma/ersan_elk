<?php

namespace App\Model;

use App\Model\Model;

class MesajLogModel extends Model
{
    protected $table = 'mesaj_log';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function logEmail($firmaId, $sender, $recipients, $subject, $message, $attachments, $status)
    {
        $data = [
            'firma_id' => $firmaId,
            'type' => 'email',
            'sender' => $sender,
            'recipients' => is_array($recipients) ? json_encode($recipients, JSON_UNESCAPED_UNICODE) : $recipients,
            'subject' => $subject,
            'message' => $message,
            'attachments' => is_array($attachments) ? json_encode($attachments, JSON_UNESCAPED_UNICODE) : $attachments,
            'status' => $status
        ];
        return $this->saveWithAttr($data);
    }

    public function logSms($firmaId, $sender, $recipients, $message, $status)
    {
        $data = [
            'firma_id' => $firmaId,
            'type' => 'sms',
            'sender' => $sender,
            'recipients' => is_array($recipients) ? json_encode($recipients, JSON_UNESCAPED_UNICODE) : $recipients,
            'subject' => null, // SMS'te konu yok
            'message' => $message,
            'attachments' => null,
            'status' => $status
        ];
        return $this->saveWithAttr($data);
    }

    public function logPush($firmaId, $title, $body, $recipients, $payload, $status)
    {
        $data = [
            'firma_id' => $firmaId,
            'type' => 'push',
            'sender' => $_SESSION['user_name'] ?? 'Sistem',
            'recipients' => is_array($recipients) ? json_encode($recipients, JSON_UNESCAPED_UNICODE) : $recipients,
            'subject' => $title,
            'message' => $body,
            'attachments' => is_array($payload) ? json_encode($payload, JSON_UNESCAPED_UNICODE) : $payload,
            'status' => $status
        ];
        return $this->saveWithAttr($data);
    }

    public function getLogs($filters = [])
    {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (!empty($filters['firma_id'])) {
            $sql .= " AND firma_id = :firma_id";
            $params[':firma_id'] = $filters['firma_id'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND type = :type";
            $params[':type'] = $filters['type'];
        }

        if (!empty($filters['start_date'])) {
            $startDate = $filters['start_date'];
            if (strpos($startDate, '.') !== false) {
                $parts = explode('.', $startDate);
                if (count($parts) == 3) {
                    $startDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
            }
            $sql .= " AND created_at >= :start_date";
            $params[':start_date'] = $startDate . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $endDate = $filters['end_date'];
            if (strpos($endDate, '.') !== false) {
                $parts = explode('.', $endDate);
                if (count($parts) == 3) {
                    $endDate = $parts[2] . '-' . $parts[1] . '-' . $parts[0];
                }
            }
            $sql .= " AND created_at <= :end_date";
            $params[':end_date'] = $endDate . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    private function parseDate($value, $fallback = null)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $fallback;
        }
        if (strpos($value, '.') !== false) {
            $parts = explode('.', $value);
            if (count($parts) === 3) {
                return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
            }
        }
        return $value;
    }

    private function listWhere(int $firmaId, array $params, array &$bind): string
    {
        $where = "firma_id = :firma_id";
        $bind[':firma_id'] = $firmaId;

        $type = $params['type'] ?? '';
        if (in_array($type, ['email', 'sms', 'push'], true)) {
            $where .= " AND type = :type";
            $bind[':type'] = $type;
        }

        $start = $this->parseDate($params['start_date'] ?? '');
        if ($start) {
            $where .= " AND created_at >= :start_date";
            $bind[':start_date'] = $start . ' 00:00:00';
        }
        $end = $this->parseDate($params['end_date'] ?? '');
        if ($end) {
            $where .= " AND created_at <= :end_date";
            $bind[':end_date'] = $end . ' 23:59:59';
        }
        return $where;
    }

    public function ajaxList(array $params, int $firma_id): array
    {
        $bind = [];
        $where = $this->listWhere($firma_id, $params, $bind);

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE firma_id = :firma_id");
        $stmt->execute([':firma_id' => $firma_id]);
        $total = (int) $stmt->fetchColumn();

        $search = trim((string) ($params['search']['value'] ?? ''));
        if ($search !== '') {
            $where .= " AND (sender LIKE :search OR recipients LIKE :search OR subject LIKE :search OR message LIKE :search)";
            $bind[':search'] = '%' . $search . '%';
        }

        $colMap = [
            0 => 'id',
            2 => 'sender',
            3 => 'recipients',
            4 => 'COALESCE(NULLIF(subject, \'\'), message)',
        ];
        foreach (($params['columns'] ?? []) as $i => $column) {
            $val = trim((string) ($column['search']['value'] ?? ''));
            if ($val === '' || !isset($colMap[(int) $i])) {
                continue;
            }
            $field = $colMap[(int) $i];
            $mode = 'contains';
            if (strpos($val, ':') !== false) {
                [$mode, $val] = explode(':', $val, 2);
            }
            $p = ':col_' . (int) $i;
            switch ($mode) {
                case 'multi':
                    $conds = [];
                    foreach (array_slice(explode('|', $val), 0, 20) as $k => $v) {
                        $conds[] = "$field LIKE {$p}_$k";
                        $bind["{$p}_$k"] = '%' . $v . '%';
                    }
                    if ($conds) {
                        $where .= " AND (" . implode(' OR ', $conds) . ")";
                    }
                    break;
                case 'equals':
                    $where .= " AND $field = $p";
                    $bind[$p] = $val;
                    break;
                case 'not_equals':
                    $where .= " AND $field != $p";
                    $bind[$p] = $val;
                    break;
                case 'not_contains':
                    $where .= " AND $field NOT LIKE $p";
                    $bind[$p] = '%' . $val . '%';
                    break;
                case 'starts_with':
                    $where .= " AND $field LIKE $p";
                    $bind[$p] = $val . '%';
                    break;
                case 'ends_with':
                    $where .= " AND $field LIKE $p";
                    $bind[$p] = '%' . $val;
                    break;
                case 'null':
                    $where .= " AND ($field IS NULL OR $field = '')";
                    break;
                case 'not_null':
                    $where .= " AND ($field IS NOT NULL AND $field != '')";
                    break;
                default:
                    $where .= " AND $field LIKE $p";
                    $bind[$p] = '%' . $val . '%';
            }
        }

        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE $where");
        $stmt->execute($bind);
        $filtered = (int) $stmt->fetchColumn();

        $sortable = [0 => 'id', 1 => 'type', 2 => 'sender', 3 => 'recipients', 5 => 'status', 6 => 'created_at'];
        $orderBy = 'id DESC';
        $order = $params['order'][0] ?? null;
        if ($order && isset($sortable[(int) ($order['column'] ?? -1)])) {
            $dir = strtolower($order['dir'] ?? '') === 'asc' ? 'ASC' : 'DESC';
            $orderBy = $sortable[(int) $order['column']] . " $dir, id DESC";
        }

        $length = (int) ($params['length'] ?? 25);
        $length = $length > 0 ? min($length, 500) : 25;
        $start = max(0, (int) ($params['start'] ?? 0));

        $stmt = $this->db->prepare(
            "SELECT id, type, sender, recipients, subject, LEFT(message, 300) AS message_preview, status, created_at
             FROM {$this->table} WHERE $where ORDER BY $orderBy LIMIT :start, :length"
        );
        foreach ($bind as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':start', $start, \PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, \PDO::PARAM_INT);
        $stmt->execute();

        return [
            'draw' => (int) ($params['draw'] ?? 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $stmt->fetchAll(\PDO::FETCH_OBJ),
        ];
    }

    public function summary(int $firma_id, array $params = []): object
    {
        $bind = [];
        $where = $this->listWhere($firma_id, array_merge($params, ['type' => '']), $bind);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS toplam,
                    SUM(type = 'email') AS email,
                    SUM(type = 'sms') AS sms,
                    SUM(type = 'push') AS push,
                    SUM(status = 'success') AS basarili,
                    SUM(status != 'success') AS basarisiz,
                    SUM(type = 'email' AND status != 'success') AS email_hata,
                    SUM(type = 'sms' AND status != 'success') AS sms_hata,
                    SUM(type = 'push' AND status != 'success') AS push_hata
             FROM {$this->table} WHERE $where"
        );
        $stmt->execute($bind);
        $row = $stmt->fetch(\PDO::FETCH_OBJ);
        foreach ($row as $k => $v) {
            $row->$k = (int) $v;
        }
        return $row;
    }

    public function getDetail(int $id, int $firma_id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = :id AND firma_id = :firma_id LIMIT 1");
        $stmt->execute([':id' => $id, ':firma_id' => $firma_id]);
        return $stmt->fetch(\PDO::FETCH_OBJ) ?: null;
    }
}
