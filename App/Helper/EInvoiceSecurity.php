<?php
namespace App\Helper;

use App\Service\Gate;

final class EInvoiceSecurity
{
    public static function checkPermission(string $action): bool
    {
        $perms = match ($action) {
            'list_giden', 'list_invoices', 'summary_stats', 'preview_html', 'download_xml', 'download_pdf', 'export_excel', 'invoice_history' 
                => ['efatura/giden-list', 'efatura/taslak-list', 'efatura/gelen-list', 'efatura/olustur'],
            'check_taxpayer', 'save_draft', 'calculate_invoice', 'delete_draft' 
                => ['efatura/olustur', 'efatura/taslak-list', 'efatura/giden-list'],
            'send_invoice', 'bulk_send_invoices', 'cancel_invoice' 
                => ['efatura/taslak-list', 'efatura/giden-list', 'efatura/olustur'],
            'sync_status', 'sync_outgoing_invoices', 'refresh_history' 
                => ['efatura/giden-list', 'efatura/taslak-list', 'efatura/olustur'],
            'sync_incoming_invoices', 'respond_commercial' 
                => ['efatura/gelen-list', 'efatura/giden-list'],
            'save_settings', 'connection_info', 'counter_info' 
                => ['efatura/ayarlar'],
            default => null,
        };

        if ($perms === null) {
            return false;
        }

        foreach ($perms as $perm) {
            if (Gate::allows($perm)) {
                return true;
            }
        }

        return false;
    }

    public static function permission(string $action): ?string
    {
        return match ($action) {
            'list_giden', 'list_invoices', 'summary_stats', 'preview_html', 'download_xml', 'download_pdf', 'export_excel', 'invoice_history' => 'efatura/giden-list',
            'check_taxpayer', 'save_draft', 'calculate_invoice', 'delete_draft' => 'efatura/olustur',
            'send_invoice', 'bulk_send_invoices' => 'efatura/taslak-list',
            'sync_status', 'sync_outgoing_invoices', 'refresh_history' => 'efatura/giden-list',
            'sync_incoming_invoices', 'respond_commercial' => 'efatura/gelen-list',
            'cancel_invoice' => 'efatura/giden-list',
            'save_settings', 'connection_info', 'counter_info' => 'efatura/ayarlar',
            default => null,
        };
    }

    public static function readOnly(string $action): bool
    {
        return in_array($action, ['list_giden','list_invoices','summary_stats','preview_html','download_xml','download_pdf','export_excel','invoice_history','calculate_invoice','check_taxpayer'], true);
    }

    public static function invoiceId(mixed $encrypted): int
    {
        if (!is_string($encrypted) || $encrypted === '' || is_numeric($encrypted)) throw new \InvalidArgumentException('Şifreli fatura kimliği gereklidir.');
        $value = Security::decrypt($encrypted);
        if (!is_string($value) || !ctype_digit($value) || (int)$value <= 0) throw new \InvalidArgumentException('Geçersiz fatura kimliği.');
        return (int)$value;
    }

    public static function validCsrf(mixed $token): bool
    {
        return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function redact(?string $payload): ?string
    {
        if ($payload === null) return null;
        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return preg_replace('/(<(?:\w+:)?(?:PASSWORD|SESSION_ID|GIB_PASS|GIB_USER)>).*?(<\/[^>]+>)/is', '$1[MASKED]$2', $payload);
        }
        $walk = function (array $data) use (&$walk): array {
            foreach ($data as $key => &$value) {
                if (preg_match('/password|passwd|session_id|gib_pass|gib_user|api_password|token|content|xsl/i', (string)$key)) $value = '[MASKED]';
                elseif (is_array($value)) $value = $walk($value);
            }
            return $data;
        };
        return json_encode(is_array($decoded) ? $walk($decoded) : $decoded, JSON_UNESCAPED_UNICODE);
    }
}
