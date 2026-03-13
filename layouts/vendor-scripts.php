<!-- Required Vendor Scripts -->

<!-- jquery -->


<?php

use App\Helper\Helper;

$page = $_GET['p'] ?? 'home';


?>
<script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.js"></script>
<script src="assets/libs/imask/imask.min.js"></script>




<!-- Sayfalara Özel Scriptler -->
<!--***************************************-->

<?php if ($page == 'home' || $page == 'demirbas/list' || $page == 'personel/performans-raporu' || $page == 'arac-takip/arac-performans') { ?>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <?php if ($page == 'home') { ?>
        <script src="assets/js/pages/allchart.js"></script>
    <?php } ?>
<?php } ?>





<?php if ($page == 'personel/list') { ?>
    <script src="views/personel/js/list.js"></script>
<?php } ?>

<?php if ($page == 'personel/manage') { ?>
    <script src="views/personel/js/manage.js?v=<?php echo time(); ?>"></script>
    <script src="views/personel/js/izin.js?v=<?php echo time(); ?>"></script>
<?php } ?>




<?php if ($page == 'gelir-gider/list') { ?>
    <script src="views/gelir-gider/js/gelir-gider.js?v=<?php echo filemtime("views/gelir-gider/js/gelir-gider.js"); ?>"></script>
<?php } ?>





<!-- Gelir gider türü tanımlama -->
<?php if ($page == 'tanimlamalar/gelir-gider-turu') { ?>
    <script src="views/tanimlamalar/js/gelir-gider-turu.js"></script>
<?php } ?>


<!--***************************************-->


<!-- Rehber Listesi -->
<?php if ($page == 'rehber/list') { ?>
    <script type="module" src="views/rehber/js/rehber.js"></script>
<?php } ?>


<!-- Kullacı Sayfası -->
<?php if ($page == 'kullanici/list') { ?>
    <script src="views/kullanici/js/user.js"></script>
<?php } ?>

<!-- Kullanıcı Grupları Sayfası -->
<?php if ($page == 'kullanici-gruplari/list') { ?>
    <script src="views/kullanici-gruplari/js/list.js"></script>
<?php } ?>

<?php if ($page == 'kullanici-gruplari/duzenle') { ?>
    <script src="views/kullanici-gruplari/js/duzenle.js"></script>
<?php } ?>


<!-- Sms Gönder -->
<?php if ($page == 'mail-sms/sms-gonder') { ?>
    <script src="views/mail-sms/js/sms.js"></script>
<?php } ?>

<?php
if ($page == "slider/duzenle" || $page == "evrak-takip/giden-evrak" || $page == "mail-sms/mail-gonder") {
    // echo '<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>';
    echo '<script src="assets/libs/summernote/summernote-lite.min.js"></script>';
    echo '<script src="assets/libs//summernote/lang/summernote-tr-TR.min.js"></script>';
    echo '<script src="./assets/js/summernote-init.js"></script>';
    echo '<script src="assets/libs/summernote/summernote-table-styles.js"></script>';

}

if ($page == "slider/list" || $page == "slider/duzenle") {
    echo '<script src="views/slider/js/slider.js"></script>';
}

if ($page == "mail-sms/mail-gonder") {
    echo '<script src="views/mail-sms/js/mail.js"></script>';
}


// parametreler.js artık parametreler.php içinde inline olarak tanımlıdır
// Harici JS kaldırıldı - çift handler çakışması tutar alanının kaydedilmemesine neden oluyordu

if ($page == "hakedisler/index") {
    echo '<script src="views/hakedisler/js/sozlesmeler.js?v=' . time() . '"></script>';
}
if ($page == "hakedisler/sozlesme-detay") {
    echo '<script src="views/hakedisler/js/sozlesme-detay.js?v=' . time() . '"></script>';
}
if ($page == "hakedisler/hakedis-detay") {
    echo '<script src="views/hakedisler/js/hakedis-detay.js?v=' . time() . '"></script>';
}

?>

<!-- Datatable Scripts -->
<?php if (
    $page == "home" ||
    $page == "personel/manage" || $page == "personel/list" ||
    $page == "sube/duzenle" || $page == "sube/list" ||
    $page == "gelir-gider/list" ||
    $page == "temsilcilik/list" || $page == "temsilcilik/talep" || $page == "temsilcilik/duzenle" ||
    $page == "tanimlamalar/gelir-gider-turu" ||
    $page == "demirbas/list" || $page == "rehber/list" ||
    $page == "evrak-takip/list" || $page == "evrak-takip/giden-evrak" ||
    $page == "slider/list" ||
    $page == "kullanici/list" || $page == "kullanici-gruplari/list" ||
    $page == "mail-sms/sms-gonder" ||
    $page == "kasa/list" || $page == "gelir-gider/online-hesap-hareketleri" || $page == "tanimlamalar/ekip-kodu" ||
    $page == "bordro/list" || $page == "demirbas/list" || $page == "puantaj/veri-yukleme" ||
    $page == "tanimlamalar/is-turu" || $page == "mail-sms/list" || $page == "tanimlamalar/izin-turu" ||
    strpos($page, "tanimlamalar/") === 0 ||
    strpos($page, "puantaj/") === 0 ||
    $page == "mail-sms/mail-gonder" || $page == "arac-takip/list" || $page == "arac-takip/duzenle" ||
    $page == "personel-takip/list" || $page == "nobet/talepler" || $page == "talepler/list" ||
    $page == "tanimlamalar/unvan-ucret" ||
    $page == "gorev-bildirimler" ||
    $page == "hakedisler/index" ||
    $page == "hakedisler/sozlesme-detay" ||
    $page == "hakedisler/hakedis-detay" ||
    $page == "bordro/parametreler" ||
    $page == "duyuru/list" ||
    $page == "raporlar/list" || $page == "maliyet-raporu/list" || strpos($page, "bordro/raporlar/") === 0 ||
    $page == "personel/performans-raporu" || $page == "arac-takip/arac-performans" ||
    $page == "cari/list" || $page == "cari/hesap-hareketleri"


) { ?>

    <!-- Datatable init js -->
    <?php require_once "datatable-scripts.php"; ?>
<?php } ?>




<!-- Required Vendor Scripts -->
<script src="assets/libs/moment/min/moment-with-locales.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>


<!-- Bootstrap Bundle JS -->
<script src="assets/libs/bootstrap/js/bootstrap.bundle.min.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"
    onerror="(function(){var s=document.createElement('script');s.src='assets/libs/toastify/toastify.min.js';document.head.appendChild(s);})();"></script>
<!-- Menu Scripts -->
<script src="assets/libs/metismenu/metisMenu.min.js"></script>

<!-- Simplebar -->
<script src="assets/libs/simplebar/simplebar.min.js"></script>

<!-- Waves Animation -->
<script src="assets/libs/node-waves/waves.min.js"></script>

<!-- Feather Icons -->
<script src="assets/libs/feather-icons/feather.min.js"></script>
<!-- pace js -->
<script src="assets/libs/pace-js/pace.min.js"></script>

<!-- Flatpickr -->
<script src="assets/libs/flatpickr/flatpickr.min.js"></script>
<script src="assets/libs/flatpickr/l10n/tr.js"></script>
<?php if ($page == 'personel/performans-raporu' || $page == 'arac-takip/list' || $page == 'personel/manage' || $page == 'arac-takip/arac-performans') { ?>
<link rel="stylesheet" href="assets/libs/flatpickr/plugins/monthSelect/style.css">
<script src="assets/libs/flatpickr/plugins/monthSelect/index.js"></script>
<?php } ?>

<?php if ($page == 'personel/performans-raporu' || $page == 'arac-takip/arac-performans') { ?>
<script src="assets/libs/flatpickr/plugins/weekSelect/weekSelect.js"></script>
<?php } ?>

<script src="assets/js/jquery.inputmask.js"></script>


<!-- App -General js -->
<script src="assets/js/app_module.js?v=<?php echo filemtime('assets/js/app_module.js'); ?>"></script>

<script src="assets/js/page.init.js"></script>
<!-- Functions and declarations-->
<script src="assets/js/app.js?v=<?php echo filemtime('assets/js/app.js'); ?>"></script>

<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('./sw.js').then(function (registration) {
                console.log('ServiceWorker registration successful with scope: ', registration.scope);
            }, function (err) {
                console.log('ServiceWorker registration failed: ', err);
            });
        });
    }
</script>

<?php if ($page == 'gorevler/list') { ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script src="views/gorevler/js/gorevler.js?v=<?php echo time(); ?>"></script>
<?php } ?>

<?php include_once __DIR__ . '/destek-chat.php'; ?>