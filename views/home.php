<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Security;




//********GELİR-GİDER ÖZET***************


?>

<div class="container-fluid">

    <!-- start page title -->
    <?php
    $maintitle = 'Ana Sayfa';
    $title = '';
    ?>
    <!-- end page title -->

    <div class="row">
        <div class="col-md-3">

            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-success mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bx-user'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Aktif Personel Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $personel_sayisi ?? 0; ?></h3>


                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>

        </div>

        <div class="col-md-3">

            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-danger mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bx-user'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Pasif Personel Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $pasif_personel_sayisi ?? 0; ?></h3>


                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>
        </div>
        <div class="col-md-3">

            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-primary mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bxs-user-account'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Toplam Personel Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $toplam_personel_sayisi ?? 0; ?></h3>


                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>
        </div>


        <div class="col-md-3">

            <div class="card widget-flat">
                <div class="card-body">
                    <div class="float-end ">
                        <span class="badge badge-warning mr-1 font-size-36 opacity-75 border-radius-12 float-end">
                            <i class='bx bx-user'></i> </span>
                    </div>
                    <h5 class="text-muted mt-0">Bekleyen Personel Talep Sayısı</h5>
                    <h3 class="mt-3 mb-3"><?php echo $personel_talep_sayisi ?? 0; ?></h3>


                    <p class="mb-0 text-muted">
                        <span class="text-nowrap">Tümünü Gör</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Üyelik taleplerini listele -->

    <div class="card">
        <div class="card-header">
            <h5> Arıza/İzin/Avans Talepleri</h5>
        </div>
        <div class="card-body">

            

        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5>İş İstatistikleri</h5>
            <span>Aylar bazında iş istatistikleri</span>
        </div>
        <div class="card-body">
            <div class="row">

                <div class="col-md-4">

                    <div id="chart"></div>
                </div>
                <div class="col-md-4">

                    <div id="chart2"></div>
                </div>
                <div class="col-md-4">

                    <div id="chart3"></div>
                </div>
            </div>
        </div>
    </div>
    <script src="assets/libs/apexcharts/apexcharts.min.js "></script>
    <script>
    var months = <?php echo json_encode($months); ?>;
    var totals = <?php echo json_encode($totals); ?>;

    var options = {
        chart: {
            type: 'line'
        },
        series: [{
            name: 'Üye Sayısı',
            data: totals
        }],
        xaxis: {
            categories: months
        }
    }

    var chart = new ApexCharts(document.querySelector("#chart"), options);

    chart.render();
    </script>

    <script>
    var options = {
        series: [{
            name: 'Gelir',
            data: [44, 55, 57, 56, 61, 58, 63, 60, 66,85,96,85]
        }, {
            name: 'Gider',
            data: [76, 85, 101, 98, 87, 105, 91, 114, 94,
                78, 77,25
            ]
        }],
        chart: {
            type: 'bar',
            height: 350
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '75%',
                borderRadius: 4,
                borderRadiusApplication: 'end'
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem','Ağu', 'Eyl', 'Eki',
                'Kas', 'Ara'
            ],
        },
        yaxis: {
            title: {
                text: '$ (thousands)'
            }
        },
        colors: ['#7AC6D2', '#E16A54', '#9C27B0'],
        fill: {
            opacity: 1
        },

        tooltip: {
            y: {
                formatter: function(val) {
                    return "$ " + val + " thousands"
                }
            }
        }
    };

    var chart = new ApexCharts(document.querySelector("#chart2"), options);
    chart.render();
    </script>

    <script>

    var gelir = <?php echo json_encode($toplam_gelir); ?>;
    var gider = <?php echo json_encode($toplam_gider); ?>;
    var bakiye = <?php echo json_encode($toplam_bakiye); ?>;



     var options = {
          series: [gelir, gider, bakiye],
          chart: {
          type: 'polarArea',
        },
        labels: ['Gider','Gelir','Kasa' ],
        stroke: {
          colors: ['#fff']
        },
        fill: {
          opacity: 0.8
        },
        responsive: [{
          breakpoint: 480,
          options: {
            chart: {
              width: 200
            },
            legend: {
              position: 'bottom'
            }
          }
        }]
        };

        var chart = new ApexCharts(document.querySelector("#chart3"), options);
        chart.render();
      
    </script>