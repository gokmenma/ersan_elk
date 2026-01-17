<?php

require_once dirname(__DIR__, 1) . '/Autoloader.php';

use App\Helper\Security;

use App\Model\PersonelModel;
use App\Model\AvansModel;
use App\Model\PersonelIzinleriModel;
use App\Model\TalepModel;

$personelModel = new PersonelModel();
$avansModel = new AvansModel();
$izinModel = new PersonelIzinleriModel();
$talepModel = new TalepModel();

// Personel Sayıları
$personel_sayisi = count($personelModel->where('aktif_mi', 1));
$pasif_personel_sayisi = count($personelModel->where('aktif_mi', 0));
$toplam_personel_sayisi = count($personelModel->all());

// Bekleyen Talepler
$db = $personelModel->getDb();

// Avanslar
$stmt = $db->prepare("SELECT count(*) as count FROM personel_avanslari WHERE durum = 'beklemede' AND silinme_tarihi IS NULL");
$stmt->execute();
$avans_count = $stmt->fetch(PDO::FETCH_OBJ)->count;

// İzinler
try {
    $stmt = $db->prepare("SELECT count(*) as count FROM personel_izinleri WHERE onay_durumu = 'beklemede'");
    $stmt->execute();
    $izin_count = $stmt->fetch(PDO::FETCH_OBJ)->count;
} catch (\Exception $e) {
    $izin_count = 0;
}

// Talepler
$stmt = $db->prepare("SELECT count(*) as count FROM personel_talepleri WHERE durum != 'cozuldu' AND deleted_at IS NULL");
$stmt->execute();
$talep_count = $stmt->fetch(PDO::FETCH_OBJ)->count;

$personel_talep_sayisi = $avans_count + $izin_count + $talep_count;

// Son Talepleri Listeleme
$recent_requests = [];

// Avanslar
$stmt = $db->prepare("SELECT 'Avans' as tip, id, personel_id, talep_tarihi as tarih, durum, tutar as detay FROM personel_avanslari WHERE durum = 'beklemede' AND silinme_tarihi IS NULL LIMIT 5");
$stmt->execute();
$avanslar = $stmt->fetchAll(PDO::FETCH_OBJ);

// İzinler
try {
    $stmt = $db->prepare("SELECT 'İzin' as tip, id, personel_id, talep_tarihi as tarih, onay_durumu as durum, izin_tipi as detay FROM personel_izinleri WHERE onay_durumu = 'beklemede' LIMIT 5");
    $stmt->execute();
    $izinler = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (\Exception $e) {
    $izinler = [];
}

// Talepler
$stmt = $db->prepare("SELECT 'Talep' as tip, id, personel_id, olusturma_tarihi as tarih, durum, baslik as detay FROM personel_talepleri WHERE durum != 'cozuldu' AND deleted_at IS NULL LIMIT 5");
$stmt->execute();
$talepler = $stmt->fetchAll(PDO::FETCH_OBJ);

$all_requests = array_merge($avanslar, $izinler, $talepler);

// Tarihe göre sırala
usort($all_requests, function ($a, $b) {
    return strtotime($b->tarih) - strtotime($a->tarih);
});

$recent_requests = array_slice($all_requests, 0, 10);

// Personel bilgilerini çek
$personel_map = [];
if (!empty($recent_requests)) {
    $p_ids = array_unique(array_map(function ($r) {
        return $r->personel_id;
    }, $recent_requests));
    if (!empty($p_ids)) {
        $ids_str = implode(',', $p_ids);
        $stmt = $db->prepare("SELECT id, adi_soyadi, resim_yolu, departman FROM personel WHERE id IN ($ids_str)");
        $stmt->execute();
        $personels = $stmt->fetchAll(PDO::FETCH_OBJ);
        foreach ($personels as $p) {
            $personel_map[$p->id] = $p;
        }
    }
}

// Şu anda izinde olanlar
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT pi.*, p.adi_soyadi, p.resim_yolu, p.departman 
    FROM personel_izinleri pi 
    JOIN personel p ON pi.personel_id = p.id 
    WHERE pi.baslangic_tarihi <= ? AND pi.bitis_tarihi >= ? AND pi.onay_durumu = 'Onaylandı'
    ORDER BY pi.bitis_tarihi ASC
    LIMIT 10
");
$stmt->execute([$today, $today]);
$active_leaves = $stmt->fetchAll(PDO::FETCH_OBJ);

// Chart değişkenleri
if (!isset($months))
    $months = [];
if (!isset($totals))
    $totals = [];
if (!isset($toplam_gelir))
    $toplam_gelir = 0;
if (!isset($toplam_gider))
    $toplam_gider = 0;
if (!isset($toplam_bakiye))
    $toplam_bakiye = 0;

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

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Arıza/İzin/Avans Talepleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Personel</th>
                                    <th>Talep Tipi</th>
                                    <th>Detay</th>
                                    <th>Tarih</th>
                                    <th>İşlem</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_requests)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">Bekleyen talep bulunmamaktadır.</td>
                                        </tr>
                                <?php else: ?>
                                        <?php foreach ($recent_requests as $req):
                                            $personel = $personel_map[$req->personel_id] ?? null;
                                            $badgeClass = 'badge-warning';
                                            if ($req->tip == 'Avans')
                                                $badgeClass = 'badge-success';
                                            if ($req->tip == 'İzin')
                                                $badgeClass = 'badge-primary';
                                            if ($req->tip == 'Talep')
                                                $badgeClass = 'badge-info';
                                            ?>
                                                <tr>
                                                    <td>
                                                        <?php if ($personel): ?>
                                                                <div class="d-flex align-items-center">
                                                                    <div class="flex-shrink-0 me-3">
                                                                        <img src="<?php echo !empty($personel->resim_yolu) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>" alt="" class="avatar-xs rounded-circle">
                                                                    </div>
                                                                    <div class="flex-grow-1">
                                                                        <h5 class="font-size-14 mb-1"><?php echo $personel->adi_soyadi; ?></h5>
                                                                        <p class="text-muted mb-0 font-size-12"><?php echo $personel->departman; ?></p>
                                                                    </div>
                                                                </div>
                                                        <?php else: ?>
                                                                Personel #<?php echo $req->personel_id; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><span class="badge <?php echo $badgeClass; ?> font-size-12"><?php echo $req->tip; ?></span></td>
                                                    <td>
                                                        <?php
                                                        if ($req->tip == 'Avans')
                                                            echo number_format($req->detay, 2) . ' ₺';
                                                        elseif ($req->tip == 'İzin')
                                                            echo ucfirst($req->detay);
                                                        else
                                                            echo $req->detay;
                                                        ?>
                                                    </td>
                                                    <td><?php echo date('d.m.Y', strtotime($req->tarih)); ?></td>
                                                    <td>
                                                        <a href="index.php?p=<?php echo ($req->tip == 'Avans' || $req->tip == 'İzin') ? 'bordro/index' : 'talep/index'; ?>" class="btn btn-primary btn-sm btn-rounded waves-effect waves-light">İncele</a>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Şu Anda İzinde Olan Personeller</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-centered table-nowrap mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Personel</th>
                                    <th>İzin Tipi</th>
                                    <th>Bitiş Tarihi</th>
                                    <th>Kalan Süre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($active_leaves)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Şu anda izinde olan personel bulunmamaktadır.</td>
                                        </tr>
                                <?php else: ?>
                                        <?php foreach ($active_leaves as $leave):
                                            $bitis = new DateTime($leave->bitis_tarihi);
                                            $bugun = new DateTime();
                                            $kalan = $bugun->diff($bitis)->days;

                                            $badgeClass = 'badge-primary';
                                            if ($leave->izin_tipi == 'hastalik')
                                                $badgeClass = 'badge-danger';
                                            if ($leave->izin_tipi == 'mazeret')
                                                $badgeClass = 'badge-warning';
                                            ?>
                                                <tr>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <div class="flex-shrink-0 me-3">
                                                                <img src="<?php echo !empty($leave->resim_yolu) ? $leave->resim_yolu : 'assets/images/users/user-dummy-img.jpg'; ?>"
                                                                    alt="" class="avatar-xs rounded-circle">
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <h5 class="font-size-14 mb-1"><?php echo $leave->adi_soyadi; ?></h5>
                                                                <p class="text-muted mb-0 font-size-12"><?php echo $leave->departman; ?></p>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $badgeClass; ?> font-size-12">
                                                            <?php echo ucfirst($leave->izin_tipi); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('d.m.Y', strtotime($leave->bitis_tarihi)); ?></td>
                                                    <td>
                                                        <span class="badge badge-info"><?php echo $kalan; ?> Gün Kaldı</span>
                                                    </td>
                                                </tr>
                                        <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
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
                data: [44, 55, 57, 56, 61, 58, 63, 60, 66, 85, 96, 85]
            }, {
                name: 'Gider',
                data: [76, 85, 101, 98, 87, 105, 91, 114, 94,
                    78, 77, 25
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
                categories: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki',
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
                    formatter: function (val) {
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
            labels: ['Gider', 'Gelir', 'Kasa'],
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