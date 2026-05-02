<?php
require 'Autoloader.php';
$m = new App\Model\BordroPersonelModel();
$pdo = (new App\Core\Db())->getConnection();
$donem = (object)['baslangic_tarihi' => '2026-04-01', 'bitis_tarihi' => '2026-04-30'];
$stmt = $pdo->prepare("SELECT bp.*, p.adi_soyadi, p.ise_giris_tarihi, p.isten_cikis_tarihi, p.maas_durumu, p.maas_tutari, p.yemek_yardimi_dahil, p.es_yardimi_dahil, p.sgk_yapilan_firma, 0 as gorev_gecmisi_var, bp.kesinti_tutar as guncel_toplam_kesinti, JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.odeme_dagilimi.icra_kesintisi')) as hd_icra_kesintisi, JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.ucretsiz_izin_gunu')) as hd_ucretsiz_izin_gunu, JSON_UNQUOTE(JSON_EXTRACT(bp.hesaplama_detay, '$.matrahlar.maas_hesap_gunu')) as hd_maas_hesap_gunu FROM bordro_personel bp JOIN personel p ON p.id = bp.personel_id WHERE bp.id = ?");
$stmt->execute([1338]);
$p = $stmt->fetch(PDO::FETCH_OBJ);
$h = $m->hesaplaOrtakGosterimDegerleri($p, $donem, 28075.5);
echo json_encode($h, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
