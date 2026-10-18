<?php

namespace App\Service;

use Mpdf\Mpdf;

class CariEkstreExportService
{
    public function createPdf(object $cari, array $hareketler): string
    {
        $toplamBorc = isset($cari->toplam_borc) ? (float) $cari->toplam_borc : $this->sum($hareketler, 'borc');
        $toplamAlacak = isset($cari->toplam_alacak) ? (float) $cari->toplam_alacak : $this->sum($hareketler, 'alacak');
        $bakiye = isset($cari->bakiye) ? (float) $cari->bakiye : $toplamAlacak - $toplamBorc;
        $e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $fmt = static fn($value): string => number_format((float) $value, 2, ',', '.');
        $rows = '';

        foreach ($hareketler as $hareket) {
            $yuruyen = (float) ($hareket->yuruyen_bakiye ?? 0);
            $ekleyen = $hareket->ekleyen_adi ?: (!empty($hareket->fatura_id) ? 'e-Fatura Entegrasyonu' : '-');
            $rows .= '<tr><td>' . $e(date('d.m.Y H:i', strtotime((string) $hareket->islem_tarihi))) . '</td>'
                . '<td>' . $e($hareket->belge_no ?: '-') . '</td><td>' . $e($hareket->aciklama ?: '-') . '</td>'
                . '<td class="text-right">' . ((float) $hareket->borc > 0 ? $fmt($hareket->borc) : '-') . '</td>'
                . '<td class="text-right">' . ((float) $hareket->alacak > 0 ? $fmt($hareket->alacak) : '-') . '</td>'
                . '<td class="text-right bold">' . $fmt(abs($yuruyen)) . ' ' . ($yuruyen < 0 ? '(B)' : ($yuruyen > 0 ? '(A)' : '')) . '</td>'
                . '<td>' . $e($ekleyen) . '</td></tr>';
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7" class="empty">Hareket bulunmamaktadır.</td></tr>';
        }

        $note = !empty($cari->notlar) ? '<div class="note"><strong>Cari Notu:</strong><br>' . nl2br($e($cari->notlar)) . '</div>' : '';
        $direction = $bakiye < 0 ? '(B)' : ($bakiye > 0 ? '(A)' : '');
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
            body{font-family:"DejaVu Sans",sans-serif;font-size:9px;color:#273244}.header{text-align:center;border-bottom:2px solid #135bec;padding-bottom:8px;margin-bottom:14px}.header h1{color:#135bec;margin:0;font-size:17px}.header p{margin:4px 0 0;color:#64748b}.info{width:100%;margin-bottom:14px;border-collapse:collapse}.info td{padding:5px;vertical-align:top}.right{text-align:right}.note{background:#fffbeb;border:1px solid #fef3c7;padding:8px;margin-bottom:12px}table.movements{width:100%;border-collapse:collapse}.movements th{background:#135bec;color:#fff;padding:7px;text-align:left}.movements td{padding:6px;border-bottom:1px solid #e2e8f0}.text-right{text-align:right}.bold{font-weight:bold}.empty{text-align:center;padding:18px!important}.footer{position:fixed;bottom:0;width:100%;text-align:center;font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;padding-top:4px}
        </style></head><body><div class="header"><h1>HESAP EKSTRESİ</h1><p>' . $e(date('d.m.Y H:i')) . ' tarihinde oluşturuldu</p></div>
        <table class="info"><tr><td style="width:60%"><strong>Müşteri Bilgileri:</strong><br>' . $e($cari->CariAdi ?? '-') . '<br>Tel: ' . $e($cari->Telefon ?: '-') . '<br>Adres: ' . nl2br($e($cari->Adres ?: '-')) . '</td><td class="right" style="width:40%"><strong>Durum:</strong><br>Toplam Borç: ' . $fmt($toplamBorc) . ' ₺<br>Toplam Alacak: ' . $fmt($toplamAlacak) . ' ₺<br><strong style="font-size:13px">Cari Bakiye: ' . $fmt(abs($bakiye)) . ' ₺ ' . $direction . '</strong></td></tr></table>'
        . $note . '<table class="movements"><thead><tr><th>Tarih</th><th>Belge No</th><th>Açıklama</th><th class="text-right">Borç</th><th class="text-right">Alacak</th><th class="text-right">Bakiye</th><th>Ekleyen</th></tr></thead><tbody>' . $rows . '</tbody></table><div class="footer">Ersan Elektrik Cari Yönetim Sistemi - ' . date('Y') . ' | Sayfa: {PAGENO}/{nbpg}</div></body></html>';

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 15,
            'tempDir' => $this->getWritableTempDir(),
        ]);
        $mpdf->SetTitle('Hesap Ekstresi - ' . ($cari->CariAdi ?? 'Cari'));
        $mpdf->WriteHTML($html);
        return $mpdf->Output('', 'S');
    }

    public function safeFileName(string $name, int $id): string
    {
        $name = preg_replace('/[^\pL\pN._-]+/u', '_', trim($name)) ?: 'Cari';
        $name = trim($name, '._-');
        return sprintf('%04d_%s.pdf', $id, mb_substr($name ?: 'Cari', 0, 90, 'UTF-8'));
    }

    private function sum(array $rows, string $field): float
    {
        return array_reduce($rows, static fn(float $sum, object $row): float => $sum + (float) ($row->{$field} ?? 0), 0.0);
    }

    private function getWritableTempDir(): string
    {
        $runtimeId = function_exists('posix_geteuid') ? (string) posix_geteuid() : substr(hash('sha256', PHP_BINARY), 0, 12);
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . 'ersan-cari-mpdf-'
            . $runtimeId;

        if (!is_dir($tempDir) && !mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            throw new \RuntimeException('PDF geçici çalışma dizini oluşturulamadı.');
        }
        if (!is_writable($tempDir)) {
            throw new \RuntimeException('PDF geçici çalışma dizini yazılabilir değil.');
        }

        return $tempDir;
    }
}
