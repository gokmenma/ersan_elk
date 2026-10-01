<?php
// Yalnız yayınlanan değişmez resmî dökümün PDF'i. Ham bordro ID kabul edilmez.
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 3) . '/bootstrap.php';
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
try {
    $pid = (int) ($_SESSION['personel_id'] ?? 0);
    if (!$pid) { http_response_code(401); exit('Oturum açmanız gerekiyor.'); }
    $p = (new \App\Model\PersonelModel())->find($pid);
    if (!$p || !empty($p->silinme_tarihi) || !(int) $p->aktif_mi || (!empty($p->isten_cikis_tarihi) && $p->isten_cikis_tarihi !== '0000-00-00')) {
        http_response_code(403); exit('Yetkisiz erişim.');
    }
    $id = \App\Helper\BordroYayinGuvenlik::id($_GET['token'] ?? null);
    $d = (new \App\Model\BordroYayinModel())->detay((int) $p->firma_id, $id, $pid);
    $i = $d['icerik'];
    $esc = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    $para = static fn($v) => number_format($v / 100, 2, ',', '.') . ' ₺';
    $html = '<h1>Resmî Alacak Dökümü</h1><p>' . $esc($i['personel']) . ' — ' . $esc($i['donem']) . '</p>';
    $html .= '<p>' . $esc($i['departman']) . ' / ' . $esc($i['gorev']) . '</p><p>' . $esc($i['baslangic']) . ' – ' . $esc($i['bitis']) . '</p>';
    $html .= '<p>Çalışma günü: ' . (int) $i['calisma_gun'] . ' / Fiilî gün: ' . (int) $i['fiili_gun'] . '</p><table width="100%">';
    foreach ($i['kalemler'] as $k) $html .= '<tr><td>' . $esc($k['etiket']) . '</td><td align="right">' . $esc($para($k['kurus'])) . '</td></tr>';
    $html .= '<tr><td><b>Bankadan ödenecek net tutar</b></td><td align="right"><b>' . $esc($para($i['banka_net_kurus'])) . '</b></td></tr></table>';
    $html .= '<p>Sürüm: ' . (int) $d['surum'] . ' / Yayın: ' . $esc($d['yayin_tarihi']) . ' / Durum: ' . $esc($d['durum']) . '</p>';
    if ($d['beyan_tarihi']) $html .= '<p>' . $esc($d['beyan_metni']) . '<br>Beyan tarihi: ' . $esc($d['beyan_tarihi']) . '</p>';
    $html .= '<p style="font-size:9px">İçerik SHA-256: ' . $esc($d['icerik_hash']) . '</p>';
    $uid = function_exists('posix_geteuid') ? (string) posix_geteuid() : substr(hash('sha256', __DIR__), 0, 16);
    $tempDir = sys_get_temp_dir() . '/ersan-bordro-pdf-' . $uid;
    if (!is_dir($tempDir) && !mkdir($tempDir, 0700, true) && !is_dir($tempDir)) throw new \RuntimeException('PDF geçici dizini oluşturulamadı.');
    $pdf = new \Mpdf\Mpdf(['tempDir' => $tempDir, 'default_font' => 'dejavusans']);
    $pdf->WriteHTML($html);
    $pdf->Output('resmi-bordro-v' . $d['surum'] . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
} catch (\DomainException $e) {
    http_response_code(404); echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (\Throwable $e) {
    error_log('Bordro yayın PDF: ' . $e->getMessage());
    http_response_code(500); echo 'PDF oluşturulamadı.';
}
