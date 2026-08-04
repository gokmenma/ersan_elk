<?php

namespace App\Model;

use PDO;

class AparatTransferModel extends Model
{
    protected $table = 'aparat_transfer';

    const DURUMLAR = [
        'beklemede' => 'Beklemede',
        'onaylandi' => 'Onaylandı',
        'reddedildi' => 'Reddedildi',
        'iptal' => 'İptal',
    ];

    public function __construct()
    {
        parent::__construct($this->table);
    }

    private function firmaId(): int
    {
        return (int) ($_SESSION['firma_id'] ?? 0);
    }

    public function ekle(array $veri): int
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, client_uuid, veren_ekip_id, alan_ekip_id, aparat_tip_id, adet,
             olusturan_personel_id, olusturan_user_id, aciklama)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $this->firmaId(),
            $veri['client_uuid'] ?: null,
            (int) $veri['veren_ekip_id'],
            (int) $veri['alan_ekip_id'],
            (int) $veri['aparat_tip_id'],
            (int) $veri['adet'],
            $veri['olusturan_personel_id'] ?? null,
            $veri['olusturan_user_id'] ?? null,
            $veri['aciklama'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function getir(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT tr.*, t.ad AS aparat_adi,
                                           veren.tur_adi AS veren_ekip_adi,
                                           alan.tur_adi AS alan_ekip_adi
                                    FROM {$this->table} tr
                                    LEFT JOIN aparat_tipleri t ON t.id = tr.aparat_tip_id
                                    LEFT JOIN tanimlamalar veren ON veren.id = tr.veren_ekip_id
                                    LEFT JOIN tanimlamalar alan ON alan.id = tr.alan_ekip_id
                                    WHERE tr.id = ? AND tr.firma_id = ? AND tr.silinme_tarihi IS NULL");
        $stmt->execute([$id, $this->firmaId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function clientUuidIleBul(string $uuid): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
                                    WHERE firma_id = ? AND client_uuid = ? AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId(), $uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listele(array $filtre = [], int $limit = 300): array
    {
        $kosul = 'tr.firma_id = ? AND tr.silinme_tarihi IS NULL';
        $parametreler = [$this->firmaId()];

        if (!empty($filtre['durum'])) {
            $kosul .= ' AND tr.durum = ?';
            $parametreler[] = $filtre['durum'];
        }
        if (!empty($filtre['ekip_id'])) {
            $kosul .= ' AND (tr.veren_ekip_id = ? OR tr.alan_ekip_id = ?)';
            $parametreler[] = (int) $filtre['ekip_id'];
            $parametreler[] = (int) $filtre['ekip_id'];
        }
        if (!empty($filtre['alan_ekip_id'])) {
            $kosul .= ' AND tr.alan_ekip_id = ?';
            $parametreler[] = (int) $filtre['alan_ekip_id'];
        }
        if (!empty($filtre['baslangic'])) {
            $kosul .= ' AND tr.tarih >= ?';
            $parametreler[] = $filtre['baslangic'] . ' 00:00:00';
        }
        if (!empty($filtre['bitis'])) {
            $kosul .= ' AND tr.tarih <= ?';
            $parametreler[] = $filtre['bitis'] . ' 23:59:59';
        }

        $sql = "SELECT tr.*, t.ad AS aparat_adi, t.kod AS aparat_kod,
                       veren.tur_adi AS veren_ekip_adi, alan.tur_adi AS alan_ekip_adi,
                       op.adi_soyadi AS olusturan_adi, onp.adi_soyadi AS onaylayan_adi
                FROM {$this->table} tr
                LEFT JOIN aparat_tipleri t ON t.id = tr.aparat_tip_id
                LEFT JOIN tanimlamalar veren ON veren.id = tr.veren_ekip_id
                LEFT JOIN tanimlamalar alan ON alan.id = tr.alan_ekip_id
                LEFT JOIN personel op ON op.id = tr.olusturan_personel_id
                LEFT JOIN personel onp ON onp.id = tr.onaylayan_personel_id
                WHERE {$kosul}
                ORDER BY tr.id DESC
                LIMIT {$limit}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametreler);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function bekleyenSayisi(int $alanEkipId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table}
            WHERE firma_id = ? AND alan_ekip_id = ? AND durum = 'beklemede' AND silinme_tarihi IS NULL");
        $stmt->execute([$this->firmaId(), $alanEkipId]);
        return (int) $stmt->fetchColumn();
    }

    public function durumGuncelle(int $id, string $durum, array $veri = []): bool
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET durum = ?, onaylanan_adet = ?, onaylayan_personel_id = ?, onaylayan_user_id = ?,
                red_nedeni = ?, onay_tarihi = NOW()
            WHERE id = ? AND firma_id = ? AND durum = 'beklemede'");

        $stmt->execute([
            $durum,
            $veri['onaylanan_adet'] ?? null,
            $veri['onaylayan_personel_id'] ?? null,
            $veri['onaylayan_user_id'] ?? null,
            $veri['red_nedeni'] ?? null,
            $id,
            $this->firmaId(),
        ]);

        return $stmt->rowCount() > 0;
    }
}
