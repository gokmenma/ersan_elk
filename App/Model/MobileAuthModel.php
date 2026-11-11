<?php

namespace App\Model;

use PDO;

class MobileAuthModel extends PersonelModel
{
    public function findForLogin(string $identifier): ?object
    {
        $identifier = trim($identifier);
        $digits = preg_replace('/\D/', '', $identifier) ?? '';
        $phone = strlen($digits) >= 10 ? substr($digits, -10) : $digits;
        $tcHash = hash('sha256', $digits);

        $stmt = $this->db->prepare(
            "SELECT * FROM {$this->table}
             WHERE (tc_hash = :tc_hash OR cep_telefonu = :telefon OR cep_telefonu = :telefon_with_zero)
               AND silinme_tarihi IS NULL
               AND aktif_mi <> 0
             LIMIT 1"
        );
        $stmt->execute([
            'tc_hash' => $tcHash,
            'telefon' => $phone,
            'telefon_with_zero' => '0' . $phone,
        ]);

        $personel = $stmt->fetch(PDO::FETCH_OBJ);

        return $personel ? $this->decryptFields($personel) : null;
    }

    public function isPasif(?object $personel): bool
    {
        if (!$personel) {
            return true;
        }

        return !empty($personel->silinme_tarihi)
            || (int) ($personel->aktif_mi ?? 0) === 0
            || (!empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi !== '0000-00-00');
    }
}
