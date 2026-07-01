<?php

namespace App\Helper;

class Validator
{
    public static function tcKimlik(string $tc): bool
    {
        if (strlen($tc) !== 11 || $tc[0] === '0') {
            return false;
        }
        if (!ctype_digit($tc)) {
            return false;
        }
        $digits = array_map('intval', str_split($tc));

        $d10 = (($digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8]) * 7
            - ($digits[1] + $digits[3] + $digits[5] + $digits[7])) % 10;

        $d11 = ($digits[0] + $digits[1] + $digits[2] + $digits[3] + $digits[4]
            + $digits[5] + $digits[6] + $digits[7] + $digits[8] + $digits[9]) % 10;

        return $digits[9] === $d10 && $digits[10] === $d11;
    }

    public static function iban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban));

        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$/', $iban)) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric, 9) as $chunk) {
            $remainder = (int)(($remainder . $chunk) % 97);
        }

        return $remainder === 1;
    }
}
