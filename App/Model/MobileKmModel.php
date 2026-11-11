<?php
namespace App\Model;

class MobileKmModel extends AracKmBildirimModel
{
    public function getPersonelHistory($personelId, $limit = 20, ?string $sinceDate = null, ?int $aracId = null)
    {
        $limit = max(1, min(100, (int) $limit));
        $sinceSql = ($sinceDate !== null ? ' AND report.tarih >= :since' : '') . ($aracId !== null ? ' AND report.arac_id = :arac' : '');
        $stmt = $this->db->prepare("SELECT report.*, vehicle.plaka FROM arac_km_bildirimleri report
            LEFT JOIN araclar vehicle ON vehicle.id = report.arac_id
            WHERE report.personel_id = :personel AND report.firma_id = :firma
            AND report.silinme_tarihi IS NULL{$sinceSql}
            ORDER BY report.tarih DESC, report.olusturma_tarihi DESC, report.id DESC LIMIT {$limit}");
        $params = ['personel' => $personelId, 'firma' => $_SESSION['firma_id']];
        if ($sinceDate !== null) { $params['since'] = $sinceDate; }
        if ($aracId !== null) { $params['arac'] = $aracId; }
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_OBJ);
    }

    public function saveReport(array $data)
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM araclar WHERE id = :id AND firma_id = :firma FOR UPDATE');
            $lock->execute(['id' => $data['arac_id'], 'firma' => $data['firma_id']]);
            if (!$lock->fetchColumn()) throw new \DomainException('Araç bulunamadı.');
            $assignment = (new AracZimmetModel())->getAktifZimmetByPersonel($data['personel_id']);
            if (!$assignment || (int) $assignment->arac_id !== (int) $data['arac_id']) {
                throw new \DomainException('Araç zimmetiniz değişti. Ekranı yenileyin.');
            }
            $id = (int) ($data['id'] ?? 0);
            if ($id) {
                $record = $this->find($id);
                if (!$record || (int) $record->personel_id !== (int) $data['personel_id']
                    || (int) $record->firma_id !== (int) $data['firma_id']
                    || (int) $record->arac_id !== (int) $data['arac_id']
                    || $record->durum === 'onaylandi' || $record->silinme_tarihi !== null) {
                    throw new \DomainException('Bu kayıt artık değiştirilemez.');
                }
            }
            if ($this->checkDuplicate($data['arac_id'], $data['tarih'], $data['tur'], $id)) {
                throw new \DomainException('Bu tarih ve dönem için bildirim zaten mevcut.');
            }
            if ($data['bitis_km'] < $this->getLastKm($data['arac_id'], $data['tarih'], $data['tur'], $id)) {
                throw new \DomainException('KM önceki değerden düşük olamaz.');
            }
            $result = $this->saveWithAttr($data);
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }
    public function checkDuplicate($aracId, $tarih, $tur, $excludeId = 0)
    {
        $stmt = $this->db->prepare("SELECT id FROM arac_km_bildirimleri WHERE arac_id = :arac
            AND tarih = :tarih AND tur = :tur AND id <> :exclude
            AND durum <> 'reddedildi' AND silinme_tarihi IS NULL LIMIT 1");
        $stmt->execute(['arac' => $aracId, 'tarih' => $tarih, 'tur' => $tur, 'exclude' => $excludeId]);
        return $stmt->fetchColumn() !== false;
    }
}
