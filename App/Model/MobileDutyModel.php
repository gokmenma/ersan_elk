<?php
namespace App\Model;

use PDO;

/** Uses the same attendance ledger as the PWA; serializes each employee's transitions. */
class MobileDutyModel extends PersonelHareketleriModel
{
    public function getAcikGorev($personelId)
    {
        $stmt = $this->db->prepare("SELECT ph.* FROM personel_hareketleri ph
            WHERE ph.personel_id = :personel AND ph.islem_tipi = 'BASLA'
            AND ph.silinme_tarihi IS NULL AND NOT EXISTS (
                SELECT 1 FROM personel_hareketleri ending
                WHERE ending.personel_id = ph.personel_id AND ending.islem_tipi = 'BITIR'
                AND ending.silinme_tarihi IS NULL
                AND (ending.zaman > ph.zaman OR (ending.zaman = ph.zaman AND ending.id > ph.id)))
            ORDER BY ph.zaman DESC, ph.id DESC LIMIT 1");
        $stmt->execute(['personel' => $personelId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function transition(string $action, array $data): bool
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM personel WHERE id = :id AND firma_id = :firma FOR UPDATE');
            $lock->execute(['id' => $data['personel_id'], 'firma' => $data['firma_id']]);
            if (!$lock->fetchColumn()) { throw new \RuntimeException('Employee unavailable'); }
            $active = $this->getAcikGorev($data['personel_id']);
            // Repeated taps/retries must not create a second ledger entry.
            if (($action === 'start' && $active) || ($action === 'finish' && !$active)) {
                $this->db->commit();
                return false;
            }
            $id = $action === 'start' ? $this->baslaGorev($data) : $this->bitirGorev($data);
            if (!$id) { throw new \RuntimeException('Attendance write failed'); }
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }
}
