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
        $rawBaseTotal = '0.00000000';
        $vatBases = [];
        $vatGroupMeta = [];
        if (empty($lines)) {
            return ['header' => $totals, 'lines' => [], 'vat_groups' => []];
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
            $rawGross = bcmul($qty, $price, 8);
            $gross = self::money($rawGross);
            $line['iskonto_tutari'] = self::money(bcdiv(bcmul($gross, $discount, 8), '100', 8));
            // Birim fiyat hassasiyetini fatura toplamına kadar koru. Satır gösterimi
            // iki hanelidir; fatura matrahı ve vergi toplamı ise ham matrahların
            // KDV oranına göre birleştirilmesinden sonra para hassasiyetine yuvarlanır.
            $rawBase = bcsub($rawGross, $line['iskonto_tutari'], 8);
            $base = self::money($rawBase);
            $line['kdv_tutari'] = self::money(bcdiv(bcmul($rawBase, $vat, 8), '100', 8));
            $line['tevkifat_tutari'] = self::money(bcdiv(bcmul($line['kdv_tutari'], $withholding, 8), '100', 8));
            $line['satir_toplami'] = bcsub(bcadd($base, $line['kdv_tutari'], 2), $line['tevkifat_tutari'], 2);
            $line['miktar'] = $qty;
            $line['birim_fiyat'] = $price;
            $rawBaseTotal = bcadd($rawBaseTotal, $rawBase, 8);
            $vatKey = implode('|', [$vat, (string)($line['istisna_kodu'] ?? ''), (string)($line['istisna_aciklama'] ?? '')]);
            $vatBases[$vatKey] = bcadd($vatBases[$vatKey] ?? '0.00000000', $rawBase, 8);
            $vatGroupMeta[$vatKey] = [
                'kdv_orani' => $vat,
                'istisna_kodu' => $line['istisna_kodu'] ?? null,
                'istisna_aciklama' => $line['istisna_aciklama'] ?? null,
            ];
            foreach (['satir_toplami' => $gross, 'iskonto_toplami' => $line['iskonto_tutari'], 'tevkifat_tutari' => $line['tevkifat_tutari']] as $key => $value) {
                $totals[$key] = bcadd($totals[$key], $value, 2);
            }
        }
        unset($line);

        $totals['kdv_matrahi'] = self::money($rawBaseTotal);
        $vatGroups = [];
        foreach ($vatBases as $vatKey => $rawVatBase) {
            $meta = $vatGroupMeta[$vatKey];
            $groupVat = self::money(bcdiv(bcmul($rawVatBase, $meta['kdv_orani'], 8), '100', 8));
            $totals['hesaplanan_kdv'] = bcadd($totals['hesaplanan_kdv'], $groupVat, 2);
            $vatGroups[] = $meta + [
                'matrah' => self::money($rawVatBase),
                'kdv_tutari' => $groupVat,
            ];
        }
        $totals['odenecek_tutar'] = bcsub(
            bcadd($totals['kdv_matrahi'], $totals['hesaplanan_kdv'], 2),
            $totals['tevkifat_tutari'],
            2
        );
        return ['header' => $totals, 'lines' => $lines, 'vat_groups' => $vatGroups];
    }
}
