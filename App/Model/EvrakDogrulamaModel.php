<?php

namespace App\Model;

use App\Helper\Security;
use PDO;

class EvrakDogrulamaModel extends Model
{
    protected $table = 'evrak_dogrulama';

    public function __construct()
    {
        parent::__construct($this->table);
    }

    public function ensureForEvrak(int $firmaId, int $evrakId): array
    {
        $row = $this->findByEvrak($firmaId, $evrakId);
        if ($row) {
            return ['kod' => $row->dogrulama_kodu, 'pin' => (string) Security::decrypt($row->pin_sifreli)];
        }

        for ($i = 0; $i < 5; $i++) {
            $kod = 'EVR-' . strtoupper(bin2hex(random_bytes(8)));
            $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            try {
                $this->insertRecord($firmaId, $evrakId, $kod, $pin);
                return ['kod' => $kod, 'pin' => $pin];
            } catch (\PDOException $e) {
                if ($e->getCode() !== '23000') {
                    throw $e;
                }
                $existing = $this->findByEvrak($firmaId, $evrakId);
                if ($existing) {
                    return ['kod' => $existing->dogrulama_kodu, 'pin' => (string) Security::decrypt($existing->pin_sifreli)];
                }
            }
        }
        throw new \RuntimeException('Doğrulama kodu üretilemedi.');
    }

    public function insertRecord(int $firmaId, int $evrakId, string $kod, string $pin, ?string $sha256 = null): void
    {
        $stmt = $this->db->prepare("INSERT INTO {$this->table}
            (firma_id, evrak_id, dogrulama_kodu, pin_hash, pin_sifreli, pades_pdf_sha256)
            VALUES (:firma_id, :evrak_id, :kod, :pin_hash, :pin_sifreli, :sha256)");
        $stmt->execute([
            'firma_id' => $firmaId,
            'evrak_id' => $evrakId,
            'kod' => $kod,
            'pin_hash' => password_hash($pin, PASSWORD_DEFAULT),
            'pin_sifreli' => Security::encrypt($pin),
            'sha256' => $sha256,
        ]);
    }

    public function findByEvrak(int $firmaId, int $evrakId)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE firma_id = :firma_id AND evrak_id = :evrak_id AND deleted_at IS NULL AND is_active = 1");
        $stmt->execute(['firma_id' => $firmaId, 'evrak_id' => $evrakId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function findByKod(string $kod)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table}
            WHERE dogrulama_kodu = :kod AND deleted_at IS NULL AND is_active = 1");
        $stmt->execute(['kod' => $kod]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function verify(string $kod, string $pin)
    {
        $row = $this->findByKod($kod);
        if (!$row || !password_verify($pin, $row->pin_hash)) {
            return false;
        }
        return $row;
    }

    public function markVerified(int $id): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table}
            SET dogrulama_sayisi = dogrulama_sayisi + 1, son_dogrulama_at = NOW() WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function setPdfSha256(int $firmaId, int $evrakId, string $sha256): void
    {
        $stmt = $this->db->prepare("UPDATE {$this->table} SET pades_pdf_sha256 = :sha256
            WHERE firma_id = :firma_id AND evrak_id = :evrak_id AND deleted_at IS NULL");
        $stmt->execute(['sha256' => $sha256, 'firma_id' => $firmaId, 'evrak_id' => $evrakId]);
    }

    public function getEvrakOzet(int $firmaId, int $evrakId)
    {
        $stmt = $this->db->prepare("SELECT et.id, et.evrak_no, et.tarih, et.konu, et.kurum_adi, et.onay_durumu,
                et.e_imza_onay_tarihi, et.pades_pdf_yolu, et.pades_pdf_sha256,
                COALESCE(NULLIF(f.firma_unvan, ''), f.firma_adi) AS firma_adi
            FROM evrak_takip et
            LEFT JOIN firmalar f ON f.id = et.firma_id
            WHERE et.id = :evrak_id AND et.firma_id = :firma_id AND et.silinme_tarihi IS NULL");
        $stmt->execute(['evrak_id' => $evrakId, 'firma_id' => $firmaId]);
        return $stmt->fetch(PDO::FETCH_OBJ);
    }

    public function getImzalayanlar(int $firmaId, int $evrakId): array
    {
        $stmt = $this->db->prepare("SELECT COALESCE(NULLIF(u.adi_soyadi, ''), u.user_name) AS adi_soyadi,
                o.onay_tarihi, o.imza_turu, o.imza_dogrulama_durumu
            FROM evrak_takip_onaylari o
            LEFT JOIN users u ON u.id = o.kullanici_id
            WHERE o.firma_id = :firma_id AND o.evrak_id = :evrak_id AND o.durum = 'onaylandi'
            ORDER BY o.sira ASC, o.id ASC");
        $stmt->execute(['firma_id' => $firmaId, 'evrak_id' => $evrakId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function countRecentFailures(string $ip, int $minutes = 15): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM evrak_dogrulama_deneme
            WHERE ip_adresi = :ip AND basarili = 0 AND created_at >= (NOW() - INTERVAL {$minutes} MINUTE)");
        $stmt->execute(['ip' => $ip]);
        return (int) $stmt->fetchColumn();
    }

    public function logAttempt(string $ip, ?string $kod, bool $basarili): void
    {
        $stmt = $this->db->prepare("INSERT INTO evrak_dogrulama_deneme (ip_adresi, dogrulama_kodu, basarili)
            VALUES (:ip, :kod, :basarili)");
        $stmt->execute(['ip' => mb_substr($ip, 0, 45), 'kod' => $kod !== null ? mb_substr($kod, 0, 32) : null, 'basarili' => $basarili ? 1 : 0]);
    }
}
