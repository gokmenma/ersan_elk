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
    $tarih = static function ($deger, bool $saat = false): string {
        $ts = strtotime((string) $deger);
        return $ts === false ? (string) $deger : date($saat ? 'd.m.Y H:i' : 'd.m.Y', $ts);
    };
    $durumEtiketi = match ((string) $d['durum']) {
        'yayinda' => 'Yayında', 'test' => 'Bildirimsiz test', 'revizyon' => 'Revizyon sürecinde', 'arsiv' => 'Arşiv',
        default => (string) $d['durum'],
    };
    $surum = (string) $d['surum'];
    $kalemler = '';
    foreach ($i['kalemler'] as $k) {
        $kalemler .= '<tr><td>' . $esc($k['etiket']) . '</td><td class="amount">' . $esc($para($k['kurus'])) . '</td></tr>';
    }
    $testBilgisi = $d['durum'] === 'test'
        ? '<div class="test-note"><b>TEST YAYINI</b><br>Bu döküm bildirimsiz kontrollü test kapsamında oluşturulmuştur.</div>'
        : '';
    $beyan = $d['beyan_tarihi']
        ? '<div class="declaration declaration-ok"><table><tr><td class="declaration-icon">✓</td><td><div class="declaration-title">Okuma beyanı kaydedildi</div><div class="declaration-text">' . $esc($d['beyan_metni']) . '</div><div class="declaration-date">Sunucu kayıt zamanı: ' . $esc($tarih($d['beyan_tarihi'], true)) . '</div></td></tr></table></div>'
        : '<div class="declaration"><div class="declaration-title">Okuma beyanı bekleniyor</div><div class="declaration-text">Bu döküm için henüz personel okuma beyanı kaydedilmemiştir.</div></div>';
    $html = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><style>
        @page { margin: 14mm 14mm 17mm; }
        body { font-family: dejavusans, sans-serif; color:#172033; font-size:10.5pt; }
        .header { background:#1f3a5f; color:#fff; padding:18px 20px; border-radius:10px; }
        .brand { font-size:9pt; letter-spacing:1.2px; color:#bcd0e8; font-weight:bold; }
        .title { font-size:22pt; font-weight:bold; margin-top:5px; }
        .subtitle { margin-top:4px; color:#dce8f5; font-size:9.5pt; }
        .test-note { margin-top:10px; padding:9px 12px; background:#fff6dc; color:#8a5a00; border:1px solid #f1cf78; border-radius:7px; font-size:9pt; }
        .person { margin-top:16px; width:100%; border-collapse:separate; border-spacing:0; }
        .person td { padding:13px 15px; background:#f5f7fb; border-top:1px solid #e3e9f1; border-bottom:1px solid #e3e9f1; }
        .person .name { font-size:14pt; font-weight:bold; color:#172033; }
        .muted { color:#66748a; font-size:9pt; }
        .right { text-align:right; }
        .net-card { margin-top:14px; padding:15px 18px; background:#edf8f4; border:1px solid #b9e5d5; border-radius:9px; }
        .net-label { color:#31715d; font-size:9pt; font-weight:bold; text-transform:uppercase; letter-spacing:.5px; }
        .net-value { color:#12654c; font-size:24pt; font-weight:bold; margin-top:3px; }
        .stats { width:100%; margin-top:12px; border-collapse:separate; border-spacing:7px 0; }
        .stats td { width:33.33%; background:#f7f9fc; border:1px solid #e5eaf1; padding:10px 12px; border-radius:7px; }
        .stat-label { color:#7b8799; font-size:8pt; text-transform:uppercase; font-weight:bold; }
        .stat-value { margin-top:3px; font-size:11pt; font-weight:bold; }
        .section-title { margin:20px 0 8px; font-size:11pt; font-weight:bold; color:#22344f; }
        .items { width:100%; border-collapse:collapse; border:1px solid #e2e8f0; }
        .items th { padding:9px 12px; background:#f1f5f9; color:#65748a; font-size:8.5pt; text-align:left; text-transform:uppercase; }
        .items td { padding:10px 12px; border-top:1px solid #e8edf3; }
        .items .amount { text-align:right; white-space:nowrap; font-weight:bold; }
        .items .total td { background:#1f3a5f; color:#fff; font-size:11pt; font-weight:bold; border:0; }
        .declaration { margin-top:16px; padding:13px 15px; background:#f7f9fc; border:1px solid #e1e7ef; border-radius:8px; }
        .declaration-ok { background:#edf8f4; border-color:#b9e5d5; }
        .declaration table { width:100%; border-collapse:collapse; }
        .declaration-icon { width:30px; color:#16805f; font-size:20pt; font-weight:bold; vertical-align:top; }
        .declaration-title { font-weight:bold; color:#26364f; }
        .declaration-text { margin-top:4px; color:#4f5f73; font-size:9pt; }
        .declaration-date { margin-top:5px; color:#748197; font-size:8pt; }
        .verify { margin-top:16px; padding-top:10px; border-top:1px solid #e0e6ee; color:#7b8799; font-size:7.5pt; }
        .hash { font-family: dejavusansmono, monospace; font-size:6.8pt; color:#536176; }
    </style></head><body>
        <div class="header"><div class="brand">PERSONEL PWA</div><div class="title">Resmî Alacak Dökümü</div><div class="subtitle">Yayınlanmış bordro sürümünün değişmez personel nüshası</div></div>
        ' . $testBilgisi . '
        <table class="person"><tr><td><div class="name">' . $esc($i['personel']) . '</div><div class="muted">' . $esc($i['departman']) . ' / ' . $esc($i['gorev']) . '</div></td><td class="right"><b>' . $esc($i['donem']) . '</b><div class="muted">' . $esc($tarih($i['baslangic'])) . ' – ' . $esc($tarih($i['bitis'])) . '</div></td></tr></table>
        <div class="net-card"><div class="net-label">Bankadan ödenecek net tutar</div><div class="net-value">' . $esc($para($i['banka_net_kurus'])) . '</div></div>
        <table class="stats"><tr><td><div class="stat-label">Çalışma günü</div><div class="stat-value">' . (int) $i['calisma_gun'] . ' gün</div></td><td><div class="stat-label">Fiilî gün</div><div class="stat-value">' . (int) $i['fiili_gun'] . ' gün</div></td><td><div class="stat-label">Yayın durumu</div><div class="stat-value">' . $esc($durumEtiketi) . '</div></td></tr></table>
        <div class="section-title">Resmî ödeme kalemleri</div><table class="items"><thead><tr><th>Açıklama</th><th class="right">Tutar</th></tr></thead><tbody>' . $kalemler . '<tr class="total"><td>Bankadan ödenecek net tutar</td><td class="amount">' . $esc($para($i['banka_net_kurus'])) . '</td></tr></tbody></table>
        ' . $beyan . '
        <div class="verify"><b>Belge bilgisi:</b> Sürüm ' . $esc($surum) . ' · Yayın zamanı ' . $esc($tarih($d['yayin_tarihi'], true)) . '<br><b>İçerik SHA-256:</b> <span class="hash">' . $esc($d['icerik_hash']) . '</span></div>
    </body></html>';
    $uid = function_exists('posix_geteuid') ? (string) posix_geteuid() : substr(hash('sha256', __DIR__), 0, 16);
    $tempDir = sys_get_temp_dir() . '/ersan-bordro-pdf-' . $uid;
    if (!is_dir($tempDir) && !mkdir($tempDir, 0700, true) && !is_dir($tempDir)) throw new \RuntimeException('PDF geçici dizini oluşturulamadı.');
    $pdf = new \Mpdf\Mpdf(['tempDir' => $tempDir, 'default_font' => 'dejavusans', 'format' => 'A4', 'margin_left' => 0, 'margin_right' => 0, 'margin_top' => 0, 'margin_bottom' => 0]);
    $pdf->SetTitle('Resmî Alacak Dökümü - ' . (string) $i['donem']);
    $pdf->SetAuthor('Personel PWA');
    $pdf->SetHTMLFooter('<div style="border-top:1px solid #dfe5ed;padding-top:6px;color:#8a95a5;font-size:8pt;text-align:center">Bu belge Personel PWA üzerinden oluşturulmuştur. &nbsp; | &nbsp; Sayfa {PAGENO} / {nbpg}</div>');
    $pdf->WriteHTML($html);
    $dosyaSurum = preg_replace('/[^A-Za-z0-9_-]/', '', $surum) ?: '1';
    $pdf->Output('resmi-bordro-' . $dosyaSurum . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
} catch (\DomainException $e) {
    http_response_code(404); echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
} catch (\Throwable $e) {
    error_log('Bordro yayın PDF: ' . $e->getMessage());
    http_response_code(500); echo 'PDF oluşturulamadı.';
}
