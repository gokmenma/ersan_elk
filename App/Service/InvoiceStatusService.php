<?php
namespace App\Service;

final class InvoiceStatusService
{
    public static function response(string $code): ?string
    {
        return match (strtoupper(trim($code))) {
            'ACCEPT', 'ACCEPTED', 'KABUL' => 'KABUL', 'REJECT', 'REJECTED', 'RED' => 'RED', default => null,
        };
    }

    public static function map(array $status): array
    {
        $raw = strtoupper(trim($status['status'] ?? $status['status_code'] ?? ''));
        $gib = isset($status['gib_code']) ? (int)$status['gib_code'] : null;
        $local = 'BEKLIYOR';
        if (str_contains($raw, 'CANCEL') && str_contains($raw, 'SUCCEED')) $local = 'IPTAL';
        elseif (str_contains($raw, 'FAILED') || str_contains($raw, 'ERROR') || ($gib !== null && $gib >= 1100 && $gib < 1200 && $gib !== 1100)) $local = 'HATALI';
        elseif ($gib === 1300) $local = 'ONAYLANDI';
        elseif ($raw === 'LOAD - SUCCEED' || $raw === 'LOAD-SUCCEED') $local = 'TASLAK';
        elseif (str_contains($raw, 'SEND') && str_contains($raw, 'SUCCEED')) $local = 'GONDERILDI';
        $mapped = ['entegrator_durum_kodu' => $local, 'edm_durum' => $raw, 'gib_durum_kodu' => $gib,
            'gib_durum_aciklamasi' => ($status['gib_desc'] ?? '') ?: ($status['status_desc'] ?? ''),
            'zarf_id' => $status['envelope_id'] ?? null,
            'earsiv_rapor_durum' => $status['report_status'] ?? null, 'earsiv_rapor_aciklama' => $status['report_desc'] ?? null,
            'earsiv_iptal_rapor_durum' => $status['cancel_report_status'] ?? null, 'earsiv_iptal_rapor_aciklama' => $status['cancel_report_desc'] ?? null];
        $response = self::response($status['response_code'] ?? '');
        if ($response !== null) $mapped['ticari_yanit'] = $response;
        return $mapped;
    }
}
