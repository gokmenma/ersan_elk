<!-- Required Vendor Scripts -->

<!-- jquery -->


<?php

use App\Helper\Helper;

$page = $_GET['p'] ?? 'home';


?>



<!-- Select2 , Jquery Validate , iMask Scriptler -->
<!--***************************************-->
<?php if (
    $page == 'uye/duzenle' || $page == "sube/duzenle" ||
    $page == "gelir-gider/list" || $page == "temsilcilik/talep" ||
    $page == "tanimlamalar/gelir-gider-turu" ||
    $page == "demirbas/list" || "temsilcilik/duzenle" ||
    $page == "personel/manage"
) { ?>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.js"></script>
    <script src="assets/libs/imask/imask.min.js"></script>
<?php } ?>
<!--***************************************-->




<!-- Sayfalara Özel Scriptler -->
<!--***************************************-->

<?php if ($page == 'home') { ?>

    <!-- <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script> -->
    <!-- <script src="'assets/js/pages/allchart.js'); "></script> -->

<?php } ?>

<?php if ($page == 'uye/duzenle') { ?>
    <script type="module" src="views/uye/js/duzenle.js"></script>
    <script type="module" src="views/uye/js/finansal-islem.js"></script>
    <script type="module" src="views/uye/js/notes.js"></script>


<?php } ?>

<?php if ($page == 'uye/uyelik-talepleri' || $page == "home") { ?>
    <script src="views/uye/js/talep.js"></script>
<?php } ?>

<?php if ($page == 'uye/list') { ?>
    <script src="views/uye/js/list.js"></script>
<?php } ?>

<?php if ($page == 'personel/list') { ?>
    <script src="views/personel/js/list.js"></script>
<?php } ?>

<?php if ($page == 'personel/manage') { ?>
    <script src="views/personel/js/manage.js"></script>
    <script src="views/personel/js/izin.js"></script>
<?php } ?>


<?php if ($page == 'sube/duzenle') { ?>
    <script src="views/sube/js/duzenle.js"></script>
<?php } ?>

<?php if ($page == 'gelir-gider/list') { ?>
    <script src="views/gelir-gider/js/gelir-gider.js"></script>
<?php } ?>

<?php if ($page == 'temsilcilik/talep') { ?>
    <script src="views/temsilcilik/js/talep.js"></script>
<?php } ?>

<?php if ($page == 'temsilcilik/list' || $page == "temsilcilik/duzenle") { ?>
    <script src="views/temsilcilik/js/temsilcilik.js"></script>
    <script src="views/temsilcilik/js/atama.js"></script>
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
<!-- Rehber Listesi -->
<?php if ($page == 'evrak-takip/giden-evrak') { ?>
    <script src="views/evrak-takip/js/giden-evrak-ekler.js"></script>
    <script src="views/evrak-takip/js/giden-evrak.js"></script>
    <script src="views/evrak-takip/js/ilgi.js"></script>


<?php } ?>

<!-- Gelen Evrak -->
<?php if ($page == 'evrak-takip/gelen-evrak') { ?>
    <script src="views/evrak-takip/js/gelen-evrak.js"></script>
<?php } ?>

<?php if ($page == 'evrak-takip/list') { ?>
    <script src="views/evrak-takip/js/list.js"></script>

<?php } ?>

<!-- Kullacı Sayfası -->
<?php if ($page == 'kullanici/list') { ?>
    <script src="views/kullanici/js/user.js"></script>
<?php } ?>

<!-- Kullanıcı Grupları Sayfası -->
<?php if ($page == 'kullanici-gruplari/duzenle') { ?>
    <script src="views/kullanici-gruplari/js/duzenle.js"></script>
<?php } ?>


<!-- Sms Gönder -->
<?php if ($page == 'mail-sms/sms-gonder') { ?>
    <script src="views/mail-sms/js/sms.js"></script>
<?php } ?>

<?php
if ($page == "slider/duzenle" || $page == "evrak-takip/giden-evrak") {
    // echo '<script src="https://cdn.jsdelivr.net/npm/summernote@0.9.0/dist/summernote-lite.min.js"></script>';
    echo '<script src="assets/libs/summernote/summernote-lite.min.js"></script>';
    echo '<script src="assets/libs//summernote/lang/summernote-tr-TR.min.js"></script>';
    echo '<script src="./assets/js/summernote-init.js"></script>';
    echo '<script src="assets/libs/summernote/summernote-table-styles.js"></script>';

}

if ($page == "slider/list" || $page == "slider/duzenle") {
    echo '<script src="views/slider/js/slider.js"></script>';
}

if ($page == "kasa/duzenle" || $page == "kasa/list") {
    echo '<script src="views/kasa/js/kasa.js"></script>';
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
    $page == "bordro/list" || $page == "demirbas/list" || $page == "puantaj/list"

) { ?>

    <!-- Datatable init js -->
    <?php require_once "datatable-scripts.php"; ?>
<?php } ?>




<!-- Required Vendor Scripts -->

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

<script src="assets/js/jquery.inputmask.js"></script>

<script src="assets/js/page.init.js"></script>

<!-- App -General js -->
<script src="assets/js/app_module.js"></script>

<!-- Functions and declarations-->
<script src="assets/js/app.js"></script>