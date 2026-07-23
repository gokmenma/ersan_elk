<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class AiAgentModel extends Model
{
    protected $table = 'ai_agent_logs';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * AI Sorgu logunu kaydeder
     */
    public function logQuery($firmaId, $userId, $module, $prompt, $response, $promptTokens = 0, $completionTokens = 0, $totalTokens = 0, $costEstimate = 0.0, $executionTimeMs = 0, $modelUsed = null, $status = 'success')
    {
        try {
            $sql = $this->db->prepare("
                INSERT INTO ai_agent_logs 
                (firma_id, user_id, module, prompt, response, prompt_tokens, completion_tokens, total_tokens, cost_estimate, execution_time_ms, model_used, status, created_at)
                VALUES 
                (:firma_id, :user_id, :module, :prompt, :response, :prompt_tokens, :completion_tokens, :total_tokens, :cost_estimate, :execution_time_ms, :model_used, :status, NOW())
            ");

            return $sql->execute([
                'firma_id'          => $firmaId,
                'user_id'           => $userId,
                'module'            => $module,
                'prompt'            => $prompt,
                'response'          => $response,
                'prompt_tokens'     => (int) $promptTokens,
                'completion_tokens' => (int) $completionTokens,
                'total_tokens'      => (int) $totalTokens,
                'cost_estimate'     => (float) $costEstimate,
                'execution_time_ms' => (int) $executionTimeMs,
                'model_used'        => $modelUsed,
                'status'            => $status
            ]);
        } catch (\PDOException $e) {
            error_log("AiAgentModel::logQuery Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Önbellekten yanıtı getirir (süresi dolmamışsa)
     */
    public function getCachedResponse($firmaId, $module, $cacheKey)
    {
        try {
            $sql = $this->db->prepare("
                SELECT response FROM ai_agent_cache 
                WHERE firma_id = :firma_id 
                AND module = :module 
                AND cache_key = :cache_key 
                AND expires_at > NOW()
                LIMIT 1
            ");
            $sql->execute([
                'firma_id'  => $firmaId,
                'module'    => $module,
                'cache_key' => $cacheKey
            ]);

            $result = $sql->fetch(PDO::FETCH_OBJ);
            return $result ? $result->response : null;
        } catch (\PDOException $e) {
            error_log("AiAgentModel::getCachedResponse Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Önbelleğe yanıt kaydeder
     */
    public function setCachedResponse($firmaId, $module, $cacheKey, $response, $ttlSeconds = 3600)
    {
        try {
            $sql = $this->db->prepare("
                INSERT INTO ai_agent_cache (firma_id, module, cache_key, response, expires_at, created_at)
                VALUES (:firma_id, :module, :cache_key, :response, DATE_ADD(NOW(), INTERVAL :ttl SECOND), NOW())
                ON DUPLICATE KEY UPDATE 
                response = VALUES(response),
                expires_at = VALUES(expires_at),
                created_at = NOW()
            ");

            return $sql->execute([
                'firma_id'  => $firmaId,
                'module'    => $module,
                'cache_key' => $cacheKey,
                'response'  => $response,
                'ttl'       => (int) $ttlSeconds
            ]);
        } catch (\PDOException $e) {
            error_log("AiAgentModel::setCachedResponse Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Firma bazlı son AI loglarını getirir
     */
    public function getLogs($firmaId, $limit = 50)
    {
        try {
            $sql = $this->db->prepare("
                SELECT l.*, u.ad_soyad as user_name
                FROM ai_agent_logs l
                LEFT JOIN kullanicilar u ON l.user_id = u.id
                WHERE l.firma_id = :firma_id
                ORDER BY l.id DESC
                LIMIT :limit
            ");
            $sql->bindValue(':firma_id', (int) $firmaId, PDO::PARAM_INT);
            $sql->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
            $sql->execute();

            return $sql->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log("AiAgentModel::getLogs Error: " . $e->getMessage());
            return [];
        }
    }
}
