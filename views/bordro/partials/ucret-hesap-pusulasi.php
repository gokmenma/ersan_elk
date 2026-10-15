<?php
/** @var object $row @var object $donem @var object $firma @var array $pusula */
$e = static fn($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$para = static fn($v): string => $v === null ? '—' : number_format((float) $v, 2, ',', '.');
$tarih = static fn($v): string => empty($v) || $v === '0000-00-00' ? '' : date('d/m/Y', strtotime($v));
$m = $pusula['detay']['matrahlar'];
$indirim = $pusula['detay']['indirimler'] ?? [];
$resmi = $pusula['resmi'];
$aylar = [1=>'OCAK','ŞUBAT','MART','NİSAN','MAYIS','HAZİRAN','TEMMUZ','AĞUSTOS','EYLÜL','EKİM','KASIM','ARALIK'];
$bas = strtotime($donem->baslangic_tarihi);
$bilgiSol = [
    'Ad Soyad' => $row->adi_soyadi, 'İşyeri' => $pusula['detay']['isyeri']['adi'] ?? $firma->firma_adi,
    'Görevi' => $pusula['gorev'], 'Dönem' => date('m/Y', $bas),
    'Adres' => $pusula['detay']['isyeri']['adres'] ?? '', 'Merkez Adres' => $firma->adres ?? '', 'Web Adresi' => $firma->web_sitesi ?? '',
];
$bilgiSag = [
    'Bordro Tür' => 'Maaş', 'İşyeri No' => $pusula['detay']['isyeri']['sicil_no'] ?? $firma->sgk_no ?? '',
    'Vergi Dairesi No' => $firma->vergi_no ?? '', 'Mersis No' => $firma->mersis_no ?? '',
    'Ticaret Sicil No' => $firma->ticaret_sicil_no ?? '', 'Vatandaş No' => $row->tc_kimlik_no ?? '',
    'SSK No' => $row->sgk_no ?? '', 'Giriş Tarihi' => $tarih($row->ise_giris_tarihi ?? null),
    'Çıkış Tarihi' => $tarih($row->isten_cikis_tarihi ?? null),
];
$gunler = [
    'SSK Gün' => $m['ssk_gunu'] ?? null, 'Normal Gün' => $m['normal_gun'] ?? null,
    'Hafta Tatili' => $m['hafta_tatili_gunu'] ?? null, 'Genel Tatil' => $m['genel_tatil_gunu'] ?? null,
    'Ücretli İzin' => $m['ucretli_izin_gunu'] ?? null, 'Rapor' => $m['rapor_gunu'] ?? null,
];
$eksik = (int) ($m['rapor_gunu'] ?? 0) + (int) ($m['ucretsiz_izin_gunu'] ?? 0);
$yasalGruplar = [
    'SİGORTA' => ['Matrah' => $m['sgk_matrahi'] ?? null, 'İşçi Prim Tutarı' => $row->sgk_isci ?? null, 'İşveren Prim Tutarı' => $row->sgk_isveren ?? null],
    'VERGİ' => ['Matrah' => $m['gelir_vergisi_matrahi'] ?? null, 'Gelir Vergisi' => $row->gelir_vergisi ?? null,
        'İndirim' => $indirim['indirim'] ?? null, 'Önceki Ay Matrah' => $m['onceki_kumulatif'] ?? null,
        'Yıl İçi Toplam' => $m['yeni_kumulatif'] ?? null, 'Damga Vergisi' => $row->damga_vergisi ?? null],
    'İŞSİZLİK' => ['İşçi Prim Tutarı' => $row->issizlik_isci ?? null, 'İşveren Prim Tutarı' => $row->issizlik_isveren ?? null],
];
?>
<style>
body { font-family: dejavusans; font-size: 8pt; color: #000; line-height: 1.15; }
table { border-collapse: collapse; width: 100%; }
td, th { padding: 2px 3px; vertical-align: top; font-weight: normal; }
.p-title { text-align: center; font-size: 11pt; font-weight: bold; margin-bottom: 7px; }
.p-info td { border: 0; padding: 1px 3px; }
.p-label { font-weight: bold; width: 30%; }
.p-grid td, .p-grid th { border: .25mm solid #000; }
.p-heading { text-align: center; font-weight: bold; }
.p-money { text-align: right; white-space: nowrap; }
.p-columns > tbody > tr > td { padding: 0; }
.p-note { font-size: 6.8pt; margin-top: 8px; color: #444; }
</style>
<div class="p-title">ÜCRET HESAP PUSULASI</div>
<table class="p-columns"><tr>
<?php foreach ([$bilgiSol, $bilgiSag] as $bilgiler): ?>
<td width="50%"><table class="p-info">
<?php foreach ($bilgiler as $etiket => $deger): ?>
<tr><td class="p-label"><?= $e($etiket) ?></td><td width="2%">:</td><td><?= $e($deger) ?></td></tr>
<?php endforeach; ?>
</table></td>
<?php endforeach; ?>
</tr></table>
<table class="p-grid" style="margin-top: 8px;">
<tr><th colspan="7" class="p-heading">ÇALIŞMA VE İZİN GÜNLERİ</th></tr>
<tr><td>Tür</td><?php foreach ($gunler as $etiket => $gun): ?><td align="center"><?= $e($etiket) ?></td><?php endforeach; ?></tr>
<tr><td>Gün Sayısı</td><?php foreach ($gunler as $gun): ?><td align="center"><?= $e($gun ?? '—') ?></td><?php endforeach; ?></tr>
<tr><td>Toplam Tutar</td><?php foreach (array_keys($gunler) as $i => $etiket): ?><td class="p-money"><?= $i === 0 ? $para($m['calisan_brut_maas'] ?? null) : $para(($pusula['detay']['gun_tutarlari'] ?? [])[$etiket] ?? null) ?></td><?php endforeach; ?></tr>
</table>
<div style="margin: 6px 0 2px;">Eksik Gün: <?= $eksik > 0 ? $e($eksik . ' (Rapor / Ücretsiz İzin)') : 'YOK' ?></div>
<table class="p-columns"><tr><td width="49%">
<table class="p-grid"><tr><th colspan="3" class="p-heading">YASAL KESİNTİLER</th></tr>
<?php foreach ($yasalGruplar as $grup => $kalemler): $ilk = true; foreach ($kalemler as $etiket => $tutar): ?>
<tr><?php if ($ilk): ?><td rowspan="<?= count($kalemler) ?>" width="28%" style="vertical-align: middle; text-align: center;"><?= $e($grup) ?></td><?php $ilk = false; endif; ?><td><?= $e($etiket) ?></td><td class="p-money"><?= $para($tutar) ?></td></tr>
<?php endforeach; endforeach; ?>
</table>
<table class="p-grid" style="margin-top: 14px;"><tr><th colspan="2" class="p-heading">ÖZET BİLGİLER</th></tr>
<?php foreach ([
    'Kazanç Toplam' => $pusula['kazanc_kurus'], 'Yasal Kesinti Toplamı' => $pusula['yasal_kesinti_kurus'],
    'Kesintiler Toplamı' => $pusula['yasal_kesinti_kurus'] + $pusula['ozel_kesinti_kurus'],
    'Özel Kesinti Toplamı' => $pusula['ozel_kesinti_kurus'],
] as $etiket => $kurus): ?><tr><td><?= $e($etiket) ?></td><td class="p-money"><?= $para($kurus / 100) ?></td></tr><?php endforeach; ?>
</table>
<table class="p-grid" style="margin-top: 14px;">
<tr><td>Asgari Ücret Gelir Vergisi</td><td class="p-money"><?= $para($indirim['asgari_ucret_istisna_gv'] ?? null) ?></td></tr>
<tr><td>Asgari Ücret Damga Vergisi</td><td class="p-money"><?= $para($indirim['asgari_ucret_istisna_dv'] ?? null) ?></td></tr>
<tr><td><b>Net Ödenen</b></td><td class="p-money"><b><?= $para($resmi['banka_net_kurus'] / 100) ?></b></td></tr>
</table>
</td><td width="2%"></td><td width="49%">
<table class="p-grid"><tr><th colspan="2" class="p-heading">EK KAZANÇLAR</th></tr>
<?php foreach ($resmi['kalemler'] as $kalem): if ($kalem['etiket'] === 'Resmî net ücret tabanı' || $kalem['kurus'] <= 0) continue; ?>
<tr><td><?= $e($kalem['etiket']) ?></td><td class="p-money"><?= $para($kalem['kurus'] / 100) ?> N</td></tr>
<?php endforeach; ?>
</table>
<?php $kesintiler = array_filter($resmi['kalemler'], static fn($k) => $k['kurus'] < 0); if ($kesintiler): ?>
<table class="p-grid" style="margin-top: 14px;"><tr><th colspan="2" class="p-heading">RESMÎ DÖKÜM DÜZELTME / KESİNTİLERİ</th></tr>
<?php foreach ($kesintiler as $kalem): ?><tr><td><?= $e($kalem['etiket']) ?></td><td class="p-money"><?= $para($kalem['kurus'] / 100) ?></td></tr><?php endforeach; ?>
</table>
<?php endif; ?>
</td></tr></table>
<p class="p-note">Kayıtlı bordro dökümüdür. Net ödenen, kayıtlı resmî banka tutarıdır. N: Net kazanç. —: Dönem kaydında bulunmayan bilgi.</p>
<div style="margin-top: 70px; font-size: 7.5pt;">
<?= $e(date('Y', $bas) . ' YILI ' . $aylar[(int) date('n', $bas)] . ' AYINA AİT, ADIMA TAHAKKUK EDEN YUKARIDA YAZILI RESMÎ GELİRLERE İLİŞKİN ÜCRET HESAP PUSULASINI TESLİM ALDIM.') ?>
</div>
<div style="margin-top: 20px; text-align: right;">Personel İmza: ____________________</div>
