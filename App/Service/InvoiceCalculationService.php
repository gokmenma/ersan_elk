<?php
namespace App\Service;

/** Decimal strings, four decimal quantity/price and half-up money rounding. */
final class InvoiceCalculationService
{
    public static function decimal(mixed $value, int $scale = 4): string
    {
        $value = trim((string)($value ?? ''));
        if ($value === '' || !is_numeric($value)) {
            return '0.' . str_repeat('0', $scale);
        }
        if (!preg_match('/^\d{1,12}(?:\.\d{1,6})?$/D', $value)) {
            if (is_numeric($value) && (float)$value >= 0) {
                return number_format((float)$value, $scale, '.', '');
            }
            throw new \InvalidArgumentException('Geçersiz veya negatif parasal değer.');
        }
        return bcadd($value, '0', $scale);
    }

    public static function money(string $value): string
    {
        return bcadd(bcadd($value, bccomp($value, '0', 8) < 0 ? '-0.005' : '0.005', 8), '0', 2);
    }

    public function calculate(array $lines): array
    {
        $totals = array_fill_keys(['satir_toplami', 'iskonto_toplami', 'kdv_matrahi', 'hesaplanan_kdv', 'tevkifat_tutari', 'odenecek_tutar'], '0.00');
        if (empty($lines)) {
            return ['header' => $totals, 'lines' => []];
        }
        foreach ($lines as &$line) {
            $qty = self::decimal($line['miktar'] ?? '1');
            $price = self::decimal($line['birim_fiyat'] ?? '0');
            $discount = self::decimal($line['iskonto_orani'] ?? '0');
            $vat = self::decimal($line['kdv_orani'] ?? '20');
            $withholding = self::decimal($line['tevkifat_orani'] ?? '0');
            if (bccomp($qty, '0', 4) < 0 || bccomp($discount, '100', 4) > 0 || bccomp($vat, '100', 4) > 0 || bccomp($withholding, '100', 4) > 0) {
                throw new \InvalidArgumentException('Miktar veya vergi oranı geçersiz.');
            }
            $gross = self::money(bcmul($qty, $price, 8));
            $line['iskonto_tutari'] = self::money(bcdiv(bcmul($gross, $discount, 8), '100', 8));
            $base = bcsub($gross, $line['iskonto_tutari'], 2);
            $line['kdv_tutari'] = self::money(bcdiv(bcmul($base, $vat, 8), '100', 8));
            $line['tevkifat_tutari'] = self::money(bcdiv(bcmul($line['kdv_tutari'], $withholding, 8), '100', 8));
            $line['satir_toplami'] = bcsub(bcadd($base, $line['kdv_tutari'], 2), $line['tevkifat_tutari'], 2);
            $line['miktar'] = $qty;
            $line['birim_fiyat'] = $price;
            foreach (['satir_toplami' => $gross, 'iskonto_toplami' => $line['iskonto_tutari'], 'kdv_matrahi' => $base, 'hesaplanan_kdv' => $line['kdv_tutari'], 'tevkifat_tutari' => $line['tevkifat_tutari'], 'odenecek_tutar' => $line['satir_toplami']] as $key => $value) {
                $totals[$key] = bcadd($totals[$key], $value, 2);
            }
        }
        unset($line);
        return ['header' => $totals, 'lines' => $lines];
    }
}
