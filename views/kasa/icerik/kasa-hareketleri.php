<?php

use App\Model\KasaHareketModel;

// Kullanıcı ve kasa yetki kontrolü
$kasaHareketModel = new KasaHareketModel();

$id = $_GET["id"] ?? 0 ;

// Kasa hareketlerini veritabanından çek
$hareketler = $kasaHareketModel->getKasaHareketByKasa($id)
?>

<div class="container-fluid">
    <div class="page-title">
        <h4 class="page-title">Kasa Hareket Kayıtları</h4>
    </div>
    
    <div class="card">
        <div class="card-body">
            <table id="hareketler-datatable" class="table dt-responsive nowrap" style="border-collapse: collapse; width: 100%;">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Açıklama</th>
                        <th>Miktar</th>
                        <th>Tür</th>
                        <th>İşlem Yapan</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($hareketler as $hareket): ?>
                    <tr>
                        <td><?= date('d.m.Y H:i', strtotime($hareket['tarih'])) ?></td>
                        <td><?= htmlspecialchars($hareket['aciklama']) ?></td>
                        <td class="<?= $hareket['tur'] === 'GELİR' ? 'text-success' : 'text-danger' ?>">
                            <?= number_format($hareket['miktar'], 2) ?> ₺
                        </td>
                        <td><?= $hareket['tur'] ?></td>
                        <td><?= htmlspecialchars($hareket['islem_yapan']) ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" data-toggle="modal" data-target="#detayModal" data-id="<?= $hareket['id'] ?>">
                                <i class="mdi mdi-eye-outline"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

