<?php

namespace App\Model;

use App\Model\Model;
use PDO;

class KepMesajModel extends Model
{
    protected $table = 'kep_mesajlar';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    /**
     * Giden KEP iletisini kaydeder
     */
    public function saveOutgoing(int $firmId, array $data): int
    {
        $sql = "INSERT INTO {$this->table} (
                    firm_id, evrak_id, kep_id, org_mesaj_id, yon,
                    gonderen_kep, alici_kep, alici_unvan, konu, icerik,
                    paket_turu, durum, hata_mesaji, delil_sayisi, son_delil_durumu,
                    yerel_dosya_yolu, ham_paket_yolu, created_at
                ) VALUES (
                    :firm_id, :evrak_id, :kep_id, :org_mesaj_id, 'giden',
                    :gonderen_kep, :alici_kep, :alici_unvan, :konu, :icerik,
                    :paket_turu, :durum, :hata_mesaji, :delil_sayisi, :son_delil_durumu,
                    :yerel_dosya_yolu, :ham_paket_yolu, NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'firm_id' => $firmId,
            'evrak_id' => $data['evrak_id'] ?? null,
            'kep_id' => $data['kep_id'],
            'org_mesaj_id' => $data['org_mesaj_id'] ?? null,
            'gonderen_kep' => $data['gonderen_kep'],
            'alici_kep' => $data['alici_kep'],
            'alici_unvan' => $data['alici_unvan'] ?? null,
            'konu' => $data['konu'] ?? '',
            'icerik' => $data['icerik'] ?? null,
            'paket_turu' => $data['paket_turu'] ?? 'Standart',
            'durum' => $data['durum'] ?? 'gonderildi',
            'hata_mesaji' => $data['hata_mesaji'] ?? null,
            'delil_sayisi' => (int) ($data['delil_sayisi'] ?? 0),
            'son_delil_durumu' => $data['son_delil_durumu'] ?? null,
            'yerel_dosya_yolu' => $data['yerel_dosya_yolu'] ?? null,
            'ham_paket_yolu' => $data['ham_paket_yolu'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * KepId üzerinden mesaj kaydını getirir
     */
    public function getByKepId(int $firmId, string $kepId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE firm_id = :firm_id AND kep_id = :kep_id AND deleted_at IS NULL LIMIT 1");
        $stmt->execute(['firm_id' => $firmId, 'kep_id' => trim($kepId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Evrak ID üzerinden giden KEP kaydını getirir
     */
    public function getSentEvrakIdMap(int $firmId, array $evrakIds): array
    {
        $evrakIds = array_values(array_unique(array_filter(array_map('intval', $evrakIds))));
        if ($evrakIds === []) {
            return [];
        }
        $in = implode(',', array_fill(0, count($evrakIds), '?'));
        $stmt = $this->db->prepare("SELECT DISTINCT evrak_id FROM {$this->table} WHERE firm_id = ? AND yon = 'giden' AND deleted_at IS NULL AND evrak_id IN ({$in})");
        $stmt->execute(array_merge([$firmId], $evrakIds));
        return array_fill_keys(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)), true);
    }

    public function getByEvrakId(int $firmId, int $evrakId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE firm_id = :firm_id AND evrak_id = :evrak_id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute(['firm_id' => $firmId, 'evrak_id' => $evrakId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Mesaj durumunu günceller
     */
    public function updateStatus(int $id, string $status, ?string $hata = null): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET durum = :durum, hata_mesaji = :hata, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'durum' => $status, 'hata' => $hata]);
    }

    /**
     * Delil özetini günceller
     */
    public function updateDelilSummary(int $id, int $delilSayisi, string $sonDelilDurumu): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET delil_sayisi = :delil_sayisi, son_delil_durumu = :son_delil_durumu, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'delil_sayisi' => $delilSayisi, 'son_delil_durumu' => $sonDelilDurumu]);
    }

    /**
     * Yerel dosya yollarını günceller
     */
    public function updateLocalFiles(int $id, ?string $dosyaYolu, ?string $hamPaketYolu): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET yerel_dosya_yolu = :yerel_dosya_yolu, ham_paket_yolu = :ham_paket_yolu, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'yerel_dosya_yolu' => $dosyaYolu, 'ham_paket_yolu' => $hamPaketYolu]);
    }

    public function saveIncoming(int $firmId, array $data): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table} (
                    firm_id, evrak_id, kep_id, kep_sira_no, org_mesaj_id, yon,
                    gonderen_kep, alici_kep, alici_unvan, konu, icerik,
                    paket_turu, durum, yerel_dosya_yolu, ham_paket_yolu, created_at
                ) VALUES (
                    :firm_id, NULL, :kep_id, :kep_sira_no, :org_mesaj_id, 'gelen',
                    :gonderen_kep, :alici_kep, :alici_unvan, :konu, :icerik,
                    'Standart', :durum, :yerel_dosya_yolu, :ham_paket_yolu, NOW()
                )");
        $stmt->execute([
            'firm_id' => $firmId,
            'kep_id' => $data['kep_id'],
            'kep_sira_no' => (int) ($data['kep_sira_no'] ?? 0),
            'org_mesaj_id' => $data['org_mesaj_id'] ?? null,
            'gonderen_kep' => (string) ($data['gonderen_kep'] ?? ''),
            'alici_kep' => (string) ($data['alici_kep'] ?? ''),
            'alici_unvan' => $data['alici_unvan'] ?? null,
            'konu' => (string) ($data['konu'] ?? ''),
            'icerik' => $data['icerik'] ?? null,
            'durum' => $data['durum'] ?? 'alindi',
            'yerel_dosya_yolu' => $data['yerel_dosya_yolu'] ?? null,
            'ham_paket_yolu' => $data['ham_paket_yolu'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function linkToEvrak(int $id, int $evrakId): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET evrak_id = :evrak_id, updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id, 'evrak_id' => $evrakId]);
    }

    public function markAcknowledged(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET durum = 'alindi_onaylandi', updated_at = NOW() WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function getImportedKepIds(int $firmId, array $kepIds): array
    {
        $kepIds = array_values(array_unique(array_filter(array_map('strval', $kepIds))));
        if (!$kepIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($kepIds), '?'));
        $stmt = $this->db->prepare("SELECT kep_id FROM {$this->table} WHERE firm_id = ? AND yon = 'gelen' AND deleted_at IS NULL AND kep_id IN ({$placeholders})");
        $stmt->execute(array_merge([$firmId], $kepIds));
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
