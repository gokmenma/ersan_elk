<?php

namespace App\Model;

use PDO;

class DosyaPaylasimModel extends Model
{
    protected $table = 'dosya_paylasimlari';

    public function __construct()
    {
        parent::__construct('dosya_paylasimlari');
    }

    /**
     * @return array{id: int, token: string}
     */
    public function olustur(int $firmaId, int $dosyaId, int $gunSayisi, ?string $sifre, ?int $indirmeLimiti, int $kullaniciId): array
    {
        $token = bin2hex(random_bytes(24));
        $bitis = (new \DateTime())->modify('+' . max(1, $gunSayisi) . ' days')->format('Y-m-d H:i:s');
        $sifreHash = ($sifre !== null && $sifre !== '') ? password_hash($sifre, PASSWORD_DEFAULT) : null;

        $stmt = $this->db->prepare(
            "INSERT INTO dosya_paylasimlari
                (firma_id, dosya_id, token_hash, bitis_tarihi, sifre_hash, indirme_limiti, olusturan_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$firmaId, $dosyaId, hash('sha256', $token), $bitis, $sifreHash, $indirmeLimiti, $kullaniciId]);
        return ['id' => (int) $this->db->lastInsertId(), 'token' => $token];
    }

    public function listele(int $dosyaId, int $firmaId): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.id, p.bitis_tarihi, p.indirme_limiti, p.indirme_sayisi, p.iptal_tarihi, p.created_at,
                    (p.sifre_hash IS NOT NULL) AS sifreli, u.adi_soyadi AS olusturan_adi
             FROM dosya_paylasimlari p
             LEFT JOIN users u ON u.id = p.olusturan_id
             WHERE p.dosya_id = ? AND p.firma_id = ?
             ORDER BY p.id DESC LIMIT 50"
        );
        $stmt->execute([$dosyaId, $firmaId]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    public function iptalEt(int $id, int $firmaId): ?object
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM dosya_paylasimlari WHERE id = ? AND firma_id = ? LIMIT 1"
        );
        $stmt->execute([$id, $firmaId]);
        $paylasim = $stmt->fetch(PDO::FETCH_OBJ);
        if (!$paylasim) {
            return null;
        }
        $stmt = $this->db->prepare(
            "UPDATE dosya_paylasimlari SET iptal_tarihi = NOW() WHERE id = ? AND firma_id = ? AND iptal_tarihi IS NULL"
        );
        $stmt->execute([$id, $firmaId]);
        return $paylasim;
    }

    public function tokenIleBul(string $token): ?object
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT p.*, d.orijinal_ad, d.dosya_adi, d.mime_tipi, d.boyut, d.uzanti, d.firma_id AS dosya_firma_id
             FROM dosya_paylasimlari p
             INNER JOIN dosyalar d ON d.id = p.dosya_id AND d.firma_id = p.firma_id
             WHERE p.token_hash = ? AND d.is_active = 1 AND d.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([hash('sha256', $token)]);
        return $stmt->fetch(PDO::FETCH_OBJ) ?: null;
    }

    public function gecerliMi(object $paylasim): ?string
    {
        if ($paylasim->iptal_tarihi !== null) {
            return 'Bu bağlantı iptal edilmiş.';
        }
        if (strtotime((string) $paylasim->bitis_tarihi) < time()) {
            return 'Bu bağlantının süresi dolmuş.';
        }
        if ($paylasim->indirme_limiti !== null && (int) $paylasim->indirme_sayisi >= (int) $paylasim->indirme_limiti) {
            return 'Bu bağlantının indirme limiti dolmuş.';
        }
        return null;
    }

    public function indirmeSayisiArtir(int $id): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE dosya_paylasimlari SET indirme_sayisi = indirme_sayisi + 1
             WHERE id = ? AND iptal_tarihi IS NULL AND (indirme_limiti IS NULL OR indirme_sayisi < indirme_limiti)"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public const IP_LIMITI = 20;
    public const BAGLANTI_LIMITI = 10;
    public const PENCERE_DAKIKA = 10;

    public function basarisizDenemeKaydet(string $ip, string $token): void
    {
        $stmt = $this->db->prepare("INSERT INTO dosya_paylasim_denemeleri (ip, token_hash, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([substr($ip, 0, 45), hash('sha256', $token)]);
        if (random_int(1, 50) === 1) {
            $this->db->exec("DELETE FROM dosya_paylasim_denemeleri WHERE created_at < (NOW() - INTERVAL 1 DAY)");
        }
    }

    public function ipEngelliMi(string $ip): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM dosya_paylasim_denemeleri WHERE ip = ? AND created_at >= (NOW() - INTERVAL " . self::PENCERE_DAKIKA . " MINUTE)"
        );
        $stmt->execute([substr($ip, 0, 45)]);
        return (int) $stmt->fetchColumn() >= self::IP_LIMITI;
    }

    public function baglantiEngelliMi(string $token): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM dosya_paylasim_denemeleri WHERE token_hash = ? AND created_at >= (NOW() - INTERVAL " . self::PENCERE_DAKIKA . " MINUTE)"
        );
        $stmt->execute([hash('sha256', $token)]);
        return (int) $stmt->fetchColumn() >= self::BAGLANTI_LIMITI;
    }
}
