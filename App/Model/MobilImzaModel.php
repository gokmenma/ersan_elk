<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class MobilImzaModel extends Model
{
    protected $table = 'mobil_imza_islemleri';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function getSigners(int $ownerId, int $firmaId): array
    {
        $sql = $this->db->prepare("SELECT id, adi_soyadi, COALESCE(NULLIF(unvani, ''), gorevi, '') AS imza_unvani, mobil_imza_msisdn
            FROM users
            WHERE silinme_tarihi IS NULL
              AND durum = 'Aktif'
              AND evrak_imza_yetkisi = 1
              AND owner_id = :owner_id
              AND (firma_ids IS NULL OR firma_ids = '' OR FIND_IN_SET(:firma_id, REPLACE(firma_ids, ' ', '')) > 0)
            ORDER BY adi_soyadi ASC");
        $sql->execute(['owner_id' => $ownerId, 'firma_id' => (string) $firmaId]);
        $rows = $sql->fetchAll(PDO::FETCH_OBJ);
        foreach ($rows as $row) {
            $row->msisdn = $this->decryptMsisdn($row->mobil_imza_msisdn);
            unset($row->mobil_imza_msisdn);
        }
        return $rows;
    }

    public function getUserMsisdn(int $userId, int $ownerId): string
    {
        $sql = $this->db->prepare("SELECT mobil_imza_msisdn FROM users WHERE id = :id AND owner_id = :owner_id AND silinme_tarihi IS NULL LIMIT 1");
        $sql->execute(['id' => $userId, 'owner_id' => $ownerId]);
        return $this->decryptMsisdn($sql->fetchColumn() ?: null);
    }

    public function saveUserMsisdn(int $userId, int $ownerId, string $msisdn): bool
    {
        $sql = $this->db->prepare("UPDATE users SET mobil_imza_msisdn = :msisdn
            WHERE id = :id AND owner_id = :owner_id AND silinme_tarihi IS NULL AND evrak_imza_yetkisi = 1");
        $sql->execute([
            'msisdn' => $msisdn !== '' ? Security::encrypt($msisdn) : null,
            'id' => $userId,
            'owner_id' => $ownerId,
        ]);
        return $sql->rowCount() > 0 || $this->signerExists($userId, $ownerId);
    }

    public function getUserTcHash(int $userId): ?string
    {
        $sql = $this->db->prepare("SELECT p.tc_hash FROM users u INNER JOIN personel p ON p.id = u.personel_id WHERE u.id = :id LIMIT 1");
        $sql->execute(['id' => $userId]);
        $hash = $sql->fetchColumn();
        return $hash ? (string) $hash : null;
    }

    public function createTransaction(array $data): void
    {
        $this->db->prepare("UPDATE {$this->table} SET durum = 'iptal', hata_mesaji = 'Yeni mobil imza isteği başlatıldı.'
            WHERE firma_id = :firma_id AND evrak_id = :evrak_id AND kullanici_id = :kullanici_id AND durum = 'bekliyor'")
            ->execute(['firma_id' => $data['firma_id'], 'evrak_id' => $data['evrak_id'], 'kullanici_id' => $data['kullanici_id']]);

        $sql = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, evrak_id, kullanici_id, token, msisdn_maskeli, mssp_trans_id, kaynak_pdf_sha256, hazir_pdf_yolu,
             byte_range, imzali_oznitelikler, sertifika_ozeti, son_gecerlilik)
            VALUES (:firma_id, :evrak_id, :kullanici_id, :token, :msisdn_maskeli, :mssp_trans_id, :kaynak_pdf_sha256, :hazir_pdf_yolu,
             :byte_range, :imzali_oznitelikler, :sertifika_ozeti, :son_gecerlilik)");
        $sql->execute($data);
    }

    public function findTransaction(string $token, int $firmaId, int $userId): ?object
    {
        $sql = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE token = :token AND firma_id = :firma_id AND kullanici_id = :kullanici_id AND is_active = 1 AND deleted_at IS NULL
            LIMIT 1");
        $sql->execute(['token' => $token, 'firma_id' => $firmaId, 'kullanici_id' => $userId]);
        return $sql->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function claimTransaction(int $id): bool
    {
        $sql = $this->db->prepare("UPDATE {$this->table} SET durum = 'imzalandi' WHERE id = :id AND durum = 'bekliyor'");
        $sql->execute(['id' => $id]);
        return $sql->rowCount() === 1;
    }

    public function closeTransaction(int $id, string $status, ?string $message = null): void
    {
        $sql = $this->db->prepare("UPDATE {$this->table} SET durum = :durum, hata_mesaji = :mesaj WHERE id = :id");
        $sql->execute(['durum' => $status, 'mesaj' => $message !== null ? mb_substr($message, 0, 500) : null, 'id' => $id]);
    }

    public function expiredFiles(): array
    {
        $sql = $this->db->prepare("SELECT id, hazir_pdf_yolu FROM {$this->table}
            WHERE hazir_pdf_yolu <> '' AND (durum <> 'bekliyor' OR son_gecerlilik < NOW())
              AND olusturma_tarihi < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
            LIMIT 50");
        $sql->execute();
        return $sql->fetchAll(PDO::FETCH_OBJ);
    }

    public function clearFile(int $id): void
    {
        $sql = $this->db->prepare("UPDATE {$this->table} SET hazir_pdf_yolu = '',
            durum = IF(durum = 'bekliyor', 'zaman_asimi', durum) WHERE id = :id");
        $sql->execute(['id' => $id]);
    }

    private function signerExists(int $userId, int $ownerId): bool
    {
        $sql = $this->db->prepare("SELECT 1 FROM users WHERE id = :id AND owner_id = :owner_id AND silinme_tarihi IS NULL AND evrak_imza_yetkisi = 1 LIMIT 1");
        $sql->execute(['id' => $userId, 'owner_id' => $ownerId]);
        return (bool) $sql->fetchColumn();
    }

    private function decryptMsisdn(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $plain = Security::decrypt($value);
        return is_string($plain) ? $plain : '';
    }
}
