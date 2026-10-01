<?php
namespace App\Helper;

use DomainException;

final class BordroYayinGuvenlik
{
    public static function csrfDogrula(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') throw new DomainException('Bu işlem POST gerektirir.');
        $beklenen = $_SESSION['csrf_token'] ?? '';
        $gelen = $_POST['csrf_token'] ?? '';
        if (!is_string($gelen) || $beklenen === '' || !hash_equals($beklenen, $gelen)) {
            http_response_code(403);
            throw new DomainException('Güvenlik doğrulaması başarısız. Sayfayı yenileyin.');
        }
    }

    public static function id(mixed $token): int
    {
        if (!is_string($token) || $token === '' || strlen($token) > 2048) throw new DomainException('Geçersiz kayıt.');
        $id = Security::decrypt($token);
        if (!is_scalar($id) || !ctype_digit((string) $id) || (int) $id <= 0) throw new DomainException('Geçersiz kayıt.');
        return (int) $id;
    }
}
