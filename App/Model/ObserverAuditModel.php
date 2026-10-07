<?php

namespace App\Model;

use App\Core\Db;
use PDO;

class ObserverAuditModel extends Model
{
    public function __construct()
    {
        // Gözlem audit kayıtları, uygulamanın READ ONLY bağlantısından ayrıdır.
        // Ana Model constructor'ı çağrılmaz; aksi halde stop isteği merkezi POST
        // korumasına takılıp asıl Superadmin oturumuna dönemez.
        $this->db = Db::createIsolatedConnection();
    }

    public function log(int $actorId, int $targetId, int $firmaId, string $event, string $resource = '', string $action = '', array $context = []): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO observer_session_logs
                 (actor_user_id, target_user_id, firma_id, event_type, resource, action_name, ip_address, context_json)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $actorId,
                $targetId,
                $firmaId ?: null,
                $event,
                $resource ?: null,
                $action ?: null,
                $_SERVER['REMOTE_ADDR'] ?? null,
                $context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ]);
        } catch (\Throwable $e) {
            error_log('Gözlem modu audit kaydı yazılamadı: ' . $e->getMessage());
        }
    }
}
