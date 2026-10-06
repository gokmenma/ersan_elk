<?php
namespace App\Helper;

use App\Service\Gate;

final class EInvoiceSecurity
{
    public static function checkPermission(string $action): bool
    {
        $listType = $_REQUEST['list_type'] ?? 'giden';

        $perms = match ($action) {
            'dashboard_stats' => ['efatura/dashboard'],
            'list_invoices', 'summary_stats', 'export_excel', 'get_unique_values', 'get-unique-values' => match ($listType) {
                'gelen'  => ['efatura/gelen-list'],
                'taslak' => ['efatura/taslak-list'],
                default  => ['efatura/giden-list'],
            },
            'list_giden' => ['efatura/giden-list'],
            'preview_html', 'show_invoice', 'view_invoice', 'download_xml', 'download_pdf', 'invoice_history'
                => ['efatura/dashboard', 'efatura/giden-list', 'efatura/taslak-list', 'efatura/gelen-list'],
            'get_invoice_payment_info', 'get_kasa_list', 'list_note_templates', 'get_note_template', 'get_serials'
                => ['efatura/olustur', 'efatura/giden-list', 'efatura/taslak-list', 'efatura/ayarlar'],
            'check_taxpayer', 'save_draft', 'calculate_invoice', 'delete_draft', 'save_invoice_payment', 'delete_invoice_payment', 'save_note_template', 'delete_note_template', 'set_default_note_template'
                => ['efatura/olustur', 'efatura/taslak-list'],
            'list_cariler', 'get_cari', 'save_cari', 'delete_cari', 'search_cariler', 'summary_cariler'
                => ['efatura/cari-list', 'efatura/olustur'],
            'list_mal_hizmet', 'get_mal_hizmet', 'save_mal_hizmet', 'delete_mal_hizmet', 'search_mal_hizmet', 'summary_mal_hizmet'
                => ['efatura/mal-hizmet-list', 'efatura/olustur'],
            'send_invoice', 'bulk_send_invoices'
                => ['efatura/taslak-list', 'efatura/giden-list'],
            'cancel_invoice'
                => ['efatura/giden-list'],
            'sync_status', 'sync_outgoing_invoices', 'sync_job_start', 'sync_job_status', 'sync_job_resume', 'sync_job_cancel', 'refresh_history'
                => ['efatura/giden-list', 'efatura/taslak-list'],
            'sync_incoming_invoices', 'respond_commercial'
                => ['efatura/gelen-list'],
            'save_settings', 'counter_info', 'list_numarators', 'save_numarator', 'sync_serials'
                => ['efatura/ayarlar'],
            'connection_info'
                => ['efatura/ayarlar', 'efatura/olustur'],
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
            'dashboard_stats' => 'efatura/dashboard',
            'list_giden', 'list_invoices', 'summary_stats', 'preview_html', 'show_invoice', 'view_invoice', 'download_xml', 'download_pdf', 'export_excel', 'invoice_history', 'get-unique-values', 'get_unique_values', 'get_invoice_payment_info', 'get_kasa_list', 'get_serials' => 'efatura/giden-list',
            'list_cariler', 'get_cari', 'save_cari', 'delete_cari', 'search_cariler', 'summary_cariler' => 'efatura/cari-list',
            'list_mal_hizmet', 'get_mal_hizmet', 'save_mal_hizmet', 'delete_mal_hizmet', 'search_mal_hizmet', 'summary_mal_hizmet' => 'efatura/mal-hizmet-list',
            'check_taxpayer', 'save_draft', 'calculate_invoice', 'delete_draft', 'connection_info', 'save_invoice_payment', 'delete_invoice_payment', 'list_note_templates', 'get_note_template', 'save_note_template', 'delete_note_template', 'set_default_note_template' => 'efatura/olustur',
            'send_invoice', 'bulk_send_invoices' => 'efatura/taslak-list',
            'sync_status', 'sync_outgoing_invoices', 'sync_job_start', 'sync_job_status', 'sync_job_resume', 'sync_job_cancel', 'refresh_history' => 'efatura/giden-list',
            'sync_incoming_invoices', 'respond_commercial' => 'efatura/gelen-list',
            'cancel_invoice' => 'efatura/giden-list',
            'save_settings', 'counter_info', 'list_numarators', 'save_numarator', 'sync_serials' => 'efatura/ayarlar',
            default => null,
        };
    }

    public static function readOnly(string $action): bool
    {
        return in_array($action, [
            'dashboard_stats', 'list_giden','list_invoices','summary_stats','preview_html','show_invoice','view_invoice','download_xml','download_pdf','export_excel','invoice_history','calculate_invoice','check_taxpayer','connection_info','list_numarators','get-unique-values','get_unique_values','get_invoice_payment_info','get_kasa_list','list_note_templates','get_note_template','get_serials',
            'list_cariler','get_cari','search_cariler','summary_cariler',
            'list_mal_hizmet','get_mal_hizmet','search_mal_hizmet','summary_mal_hizmet'
        ], true);
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
