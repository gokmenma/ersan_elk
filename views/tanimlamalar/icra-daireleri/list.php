<?php
require_once dirname(__DIR__, 3) . '/Autoloader.php';
use App\Helper\Security;
use App\Helper\Form;
use App\Model\IcraDaireleriModel;

$Model = new IcraDaireleriModel();
$liste = $Model->all()->orderBy('daire_adi', 'ASC')->get();
?>

<div class="container-fluid">
    <?php
    $maintitle = "Tanımlamalar";
    $title = "İcra Dairesi Tanımlamaları";
    ?>
    <?php include 'layouts/breadcrumb.php'; ?>

    <style>
        .modal-content { background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); }
        .form-floating-custom .form-control:focus { box-shadow: 0 0 0 0.25rem rgba(85, 110, 230, 0.15); border-color: #556ee6; }
        .btn-primary { background: linear-gradient(135deg, #556ee6 0%, #3452e1 100%); border: none; font-weight: 600; padding: 10px 24px; box-shadow: 0 4px 15px rgba(85, 110, 230, 0.35); }
        .btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(85, 110, 230, 0.45); }
        .daire-adi-link { cursor: pointer; color: var(--bs-primary); transition: color 0.2s ease; }
        .daire-adi-link:hover { color: var(--bs-link-hover-color); text-decoration: underline; }
    </style>

    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="card-title mb-1 text-primary fw-bold">İcra Daireleri Listesi</h4>
                        <p class="text-muted mb-0 small">İcra işlemlerinde kullanılacak daire bilgilerini yönetin.</p>
                    </div>
                    <button type="button" id="btnEkle" class="btn btn-primary btn-rounded waves-effect waves-light shadow-sm">
                        <i data-feather="plus-circle" class="me-1"></i> Yeni İcra Dairesi Ekle
                    </button>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="datatable-icra" class="table table-hover align-middle mb-0 datatable" style="width:100%">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Daire Bilgisi</th>
                                    <th>Konum</th>
                                    <th>İletişim</th>
                                    <th>Vergi Bilgileri</th>
                                    <th class="text-center">Durum</th>
                                    <th class="text-end pe-4">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($liste as $item): 
                                    $enc_id = Security::encrypt($item->id);
                                    $durumBadge = $item->aktif ? '<span class="badge rounded-pill bg-success-subtle text-success">Aktif</span>' : '<span class="badge rounded-pill bg-danger-subtle text-danger">Pasif</span>';
                                ?>
                                <tr id="row_<?php echo $item->id; ?>">
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-xs me-3">
                                                <div class="avatar-title rounded-circle bg-primary-subtle text-primary fw-bold">
                                                    <?php echo mb_substr($item->daire_adi, 0, 1); ?>
                                                </div>
                                            </div>
                                            <div>
                                                <h5 class="font-size-14 mb-1 fw-semibold"><a href="javascript:void(0);" class="duzenle text-primary" data-id="<?php echo $enc_id; ?>"><?php echo $item->daire_adi; ?></a></h5>
                                                <p class="text-muted mb-0 font-size-12"><?php echo $item->daire_kodu; ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="mb-1 fw-medium text-dark"><?php echo $item->il; ?></p>
                                        <p class="text-muted mb-0 font-size-12"><?php echo $item->ilce; ?></p>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <span class="font-size-13 text-dark d-flex align-items-center"><i data-feather="phone" class="me-1 text-primary" style="width: 14px; height: 14px;"></i><?php echo $item->telefon; ?></span>
                                            <span class="font-size-12 text-muted d-flex align-items-center"><i data-feather="mail" class="me-1" style="width: 14px; height: 14px;"></i><?php echo $item->email; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <p class="mb-0 font-size-13 text-dark"><?php echo $item->vergi_dairesi; ?></p>
                                        <p class="text-muted mb-0 font-size-12">No: <?php echo $item->vergi_no; ?></p>
                                    </td>
                                    <td class="text-center">
                                        <?php echo $durumBadge; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="dropdown">
                                            <a href="#" class="dropdown-toggle card-drop" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i data-feather="more-horizontal" class="text-muted" style="width: 18px; height: 18px;"></i>
                                            </a>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 10px;">
                                                <li><a href="javascript:void(0);" class="dropdown-item duzenle py-2" data-id="<?php echo $enc_id; ?>"><i data-feather="edit" class="text-primary me-2" style="width: 16px; height: 16px;"></i> Düzenle</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a href="javascript:void(0);" class="dropdown-item sil py-2 text-danger" data-id="<?php echo $enc_id; ?>"><i data-feather="trash-2" class="me-2" style="width: 16px; height: 16px;"></i> Sil</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="modalIcra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 20px;">
            <div class="modal-header border-0 bg-primary py-4 px-4" style="border-radius: 20px 20px 0 0;">
                <h5 class="modal-title text-white fw-bold" id="modalTitle"><i data-feather="home" class="me-2"></i> İcra Dairesi Ekle</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formIcra" class="needs-validation" novalidate>
                    <input type="hidden" name="id" id="icra_id" value="0">
                    <input type="hidden" name="action" value="kaydet">
                    
                    <div class="row g-3">
                        <!-- Öncelikli / Zorunlu Alanlar -->
                        <div class="col-md-12">
                            <?= Form::FormFloatInput("text", "daire_adi", "", "Örn: Ankara 5. İcra Dairesi", "İcra Dairesi Adı", "home", "form-control shadow-none", true) ?>
                        </div>
                        
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "vergi_dairesi", "", "Vergi Dairesi", "Vergi Dairesi", "shield", "form-control shadow-none", true) ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "vergi_no", "", "Vergi No", "Vergi No", "credit-card", "form-control shadow-none", true) ?>
                        </div>

                        <div class="col-md-12">
                            <?= Form::FormFloatInput("text", "iban", "TR", "TR00...", "IBAN Numarası", "hash", "form-control shadow-none mask-iban", true) ?>
                        </div>

                        <!-- Diğer Bilgiler -->
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "daire_kodu", "", "Kod", "Daire Kodu", "hash", "form-control shadow-none") ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("email", "email", "", "E-posta", "E-posta", "mail", "form-control shadow-none") ?>
                        </div>

                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "il", "", "İl Seçiniz", "İl", "map-pin", "form-control shadow-none") ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "ilce", "", "İlçe Seçiniz", "İlçe", "compass", "form-control shadow-none") ?>
                        </div>
                        
                        <div class="col-12">
                            <?= Form::FormFloatTextarea("adres", "", "Tam Adres", "Adres", "map", "form-control shadow-none", false, "80px") ?>
                        </div>
                        
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "telefon", "", "Telefon", "Telefon", "phone", "form-control shadow-none") ?>
                        </div>
                        <div class="col-md-6">
                            <?= Form::FormFloatInput("text", "faks", "", "Faks", "Faks", "printer", "form-control shadow-none") ?>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <div class="form-check form-switch form-switch-lg p-0 ps-5 ms-2">
                                <input class="form-check-input" type="checkbox" id="aktif" name="aktif" checked style="cursor: pointer;">
                                <label class="form-check-label fw-medium text-dark ms-2" for="aktif" style="cursor: pointer;">Kullanılabilir / Aktif</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer border-0 p-4">
                <button type="button" class="btn btn-light btn-rounded px-4" data-bs-dismiss="modal">İptal</button>
                <button type="button" id="btnKaydet" class="btn btn-primary btn-rounded px-5 shadow">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<script src="views/tanimlamalar/icra-daireleri/js/script.js?v=<?php echo time(); ?>"></script>
