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
            $sql .= " AND created_at >= :start_date";
            $params[':start_date'] = $filters['start_date'] . ' 00:00:00';
        }

        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= :end_date";
            $params[':end_date'] = $filters['end_date'] . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }
}
