<?php include 'layouts/session.php'; ?>
<?php include 'layouts/main.php'; ?>

<head>

    <title><?php echo $language["Chartjs"]; ?> | Dason - Admin & Dashboard Template</title>

    <?php include 'layouts/head.php'; ?>

    <?php include 'layouts/head-style.php'; ?>

</head>

<?php include 'layouts/body.php'; ?>

<!-- Begin page -->
<div id="layout-wrapper">

    <?php include 'layouts/menu.php'; ?>

    <!-- ============================================================== -->
    <!-- Start right Content here -->
    <!-- ============================================================== -->
    <div class="main-content">

        <div class="page-content">
            <div class="container-fluid">

                <!-- start page title -->
                <?php
                $maintitle = "Charts";
                $title = "Chartjs";
                ?>
                <?php include 'layouts/breadcrumb.php'; ?>
                <!-- end page title -->


                <div class="row">
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Line Chart</h4>
                            </div>
                            <div class="card-body">

                                <canvas id="lineChart" height="300" data-colors='["--vz-primary-rgb, 0.2", "--bs-primary", "rgba(235, 239, 242, 0.2)", "--bs-success"]'></canvas>

                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Bar Chart</h4>
                            </div>
                            <div class="card-body">

                                <canvas id="bar" height="300" data-colors='["--bs-primary"]'></canvas>

                            </div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->


                <div class="row">
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Pie Chart</h4>
                            </div>
                            <div class="card-body">

                                <canvas id="pie" height="260" data-colors='["--bs-success","--bs-light"]'></canvas>

                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Donut Chart</h4>
                            </div>
                            <div class="card-body">

                                <canvas id="doughnut" height="260" data-colors='["--bs-primary","--bs-light"]'></canvas>

                            </div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->

                <div class="row">
                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Polar Chart</h4>
                            </div>
                            <div class="card-body">

                                <canvas id="polarArea" height="300" data-colors='["--bs-primary","--bs-success","--bs-warning","--bs-danger"]'> </canvas>

                            </div>
                        </div>
                    </div> <!-- end col -->

                    <div class="col-xl-6">
                        <div class="card">
                            <div class="card-header">
                                <h4 class="card-title mb-0">Radar Chart</h4>
                            </div>
                            <div class="card-body">
                                <canvas id="radar" height="300" data-colors='["rgba(52, 195, 143, 0.2)", "--bs-success", "rgba(28, 132, 238, 0.2)", "--bs-primary"]'></canvas>
                            </div>
                        </div>
                    </div> <!-- end col -->
                </div> <!-- end row -->



            </div> <!-- container-fluid -->
        </div>
        <!-- End Page-content -->

        <?php include 'layouts/footer.php'; ?>
    </div>
    <!-- end main content-->

</div>
<!-- END layout-wrapper -->

<?php include 'layouts/right-sidebar.php'; ?>

<?php include 'layouts/vendor-scripts.php'; ?>

<!-- Chart JS -->
<script src="assets/libs/chart.js/Chart.bundle.min.js"></script>
<!-- chartjs init -->
<script src="assets/js/pages/chartjs.init.js"></script>
<script src="assets/js/pages/allchart.js"></script>
</body>

</html>