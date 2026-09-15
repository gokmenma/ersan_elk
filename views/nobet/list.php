<?php
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;
use App\Model\SettingsModel;
use App\Service\Gate;

$Personel = new PersonelModel();
$Tanimlamalar = new TanimlamalarModel();
$Settings = new SettingsModel();

$settingsData = $Settings->getAllSettingsAsKeyValue($_SESSION['firma_id'] ?? null);
$canEditPast = ($settingsData['nobet_gecmis_islem'] ?? '0') === '1';
$showDeletedToStaff = ($settingsData['nobet_silinmis_goster'] ?? '0') === '1';
$hasSettingPermission = Gate::allows("nobet_onceki_gunlerde_islem_yapabilir");
$canApprove = Gate::allows("yonetici_onayi");

$personeller = $Personel->all(false, 'nobet');

// Departmanları doğrudan personel listesinden dinamik olarak al
$deptList = [];
foreach ($personeller as $p) {
    if (!empty($p->departman)) {
        $deptList[] = $p->departman;
    }
}
$uniqueDepts = array_unique($deptList);

// Departman Renk Haritası (Daha canlı ve çeşitli renkler)
$colors = [
    '#3b82f6',
    '#10b981',
    '#f59e0b',
    '#8b5cf6',
    '#ef4444',
    '#06b6d4',
    '#ec4899',
    '#f97316',
    '#6366f1',
    '#14b8a6',
    '#f43f5e',
    '#84cc16',
    '#eab308',
    '#d946ef',
    '#0ea5e9',
    '#4ade80',
    '#fbbf24',
    '#a78bfa',
    '#f87171',
    '#2dd4bf'
];

// Departman isimlerini agresif normalleştiren fonksiyon
function normalizeDeptName($name)
{
    if (!$name)
        return '';
    $name = trim($name);
    // Türkçe karakterleri ve ayraçları temizle
    $search = array('ç', 'ğ', 'ı', 'i', 'ö', 'ş', 'ü', 'Ç', 'Ğ', 'I', 'İ', 'Ö', 'Ş', 'Ü', '-', '_', ' ', '/', '.', ',');
    $replace = array('C', 'G', 'I', 'I', 'O', 'S', 'U', 'C', 'G', 'I', 'I', 'O', 'S', 'U', '', '', '', '', '', '');
    $name = str_replace($search, $replace, $name);
    return strtoupper($name);
}

$deptColorMap = [];
$deptOriginalNames = [];
$i = 0;
foreach ($uniqueDepts as $dName) {
    $normalizedName = normalizeDeptName($dName);
    if (!isset($deptColorMap[$normalizedName])) {
        $deptColorMap[$normalizedName] = $colors[$i % count($colors)];
        $deptOriginalNames[$normalizedName] = $dName;
        $i++;
    }
}
?>
<link rel="stylesheet" href="views/nobet/assets/style.css?v=<?php echo filemtime('views/nobet/assets/style.css'); ?>">
<style>
    .custom-context-menu {
        display: none;
        position: fixed;
        z-index: 10000;
        background: white;
        border: 1px solid #e2e8f0;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        border-radius: 10px;
        padding: 6px 0;
        min-width: 200px;
        animation: menuFadeIn 0.2s ease-out;
    }

    @keyframes menuFadeIn {
        from {
            opacity: 0;
            transform: translateY(-5px) scale(0.95);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .custom-context-menu .menu-item {
        padding: 10px 16px;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        transition: all 0.2s;
    }

    .custom-context-menu .menu-item:hover {
        background: #f9fafb;
        color: #4f46e5;
    }

    .custom-context-menu .menu-item i {
        font-size: 18px;
        width: 20px;
        text-align: center;
    }

    .custom-context-menu .menu-divider {
        height: 1px;
        background: #f1f5f9;
        margin: 6px 0;
    }

    .custom-context-menu .menu-header {
        padding: 6px 16px;
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .custom-context-menu .menu-item.active {
        background: #f1f5f9;
        color: #4f46e5;
        font-weight: 700;
    }

    .custom-context-menu .menu-item.active::after {
        content: '\eb7a';
        /* Boxicons bx-check icon code */
        font-family: 'boxicons' !important;
        margin-left: auto;
        font-size: 18px;
    }

    .fc-event-unapproved {
        opacity: 0.75 !important;
        border: 2px dashed rgba(0,0,0,0.3) !important;
    }

    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .hover-bg-white:hover {
        background-color: #ffffff !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .transition-all {
        transition: all 0.2s ease-in-out;
    }
</style>


<!-- Sayfa Başlığı -->
<?php
$maintitle = "Nöbet Yönetimi";
$title = 'Nöbet Planlama';
?>
<?php include 'layouts/breadcrumb.php'; ?>

<!-- Ana İçerik -->
<div class="nobet-container">

    <!-- Sol Panel - Personel Havuzu -->
    <div class="personel-pool">
        <div class="personel-pool-card">
            <?php
            $departments = [];
            if (isset($personeller)) {
                foreach ($personeller as $personel) {
                    if (!empty($personel->departman)) {
                        $departments[] = $personel->departman;
                    }
                }
            }
            $departments = array_unique($departments);
            sort($departments);
            ?>
            <div class="pool-header">
                <div>
                    <h5>Personel Havuzu</h5>
                    <div class="subtitle">Sürükle ve bırak ile nöbet ata</div>
                </div>
                <div class="d-flex gap-1">
                    <!-- Sıralama Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="dropdown" id="personel-sort-btn"
                            title="Sıralama">
                            <i class="bx bx-sort"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" id="personel-sort-dropdown"
                            style="font-size: 13px; min-width: 200px;">
                            <li>
                                <h6 class="dropdown-header">Sıralama Seçin</h6>
                            </li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-sort="az"><i
                                        class="bx bx-sort-a-z me-2"></i>A'dan Z'ye</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-sort="za"><i
                                        class="bx bx-sort-z-a me-2"></i>Z'den A'ya</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-sort="count_low"><i
                                        class="bx bx-trending-up me-2"></i>Nöbet Sayısına göre (En Düşük)</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-sort="count_high"><i
                                        class="bx bx-trending-down me-2"></i>Nöbet Sayısına göre (En Yüksek)</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-sort="dept"><i
                                        class="bx bx-buildings me-2"></i>Departmana Göre</a></li>
                        </ul>
                    </div>

                    <!-- Departman Filtre Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="dropdown" id="dept-filter-btn"
                            title="Departman Filtresi">
                            <i class="bx bx-filter-alt"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" id="dept-filter-dropdown"
                            style="font-size: 13px; min-width: 200px;">
                            <li>
                                <h6 class="dropdown-header">Departman Seçin</h6>
                            </li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-dept="all">Tüm
                                    Departmanlar</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <?php foreach ($departments as $dept): ?>
                                <li><a class="dropdown-item" href="javascript:void(0)"
                                        data-dept="<?php echo htmlspecialchars($dept); ?>"><?php echo htmlspecialchars($dept); ?></a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Personel Durumu Filtre Dropdown (Aktif / Ayrılan / Tümü) -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-light" data-bs-toggle="dropdown" id="status-filter-btn"
                            title="Personel Durumu (Aktif / Ayrılan)">
                            <i class="bx bx-user-check"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" id="status-filter-dropdown"
                            style="font-size: 13px; min-width: 210px;">
                            <li>
                                <h6 class="dropdown-header">Personel Durumu</h6>
                            </li>
                            <li><a class="dropdown-item active" href="javascript:void(0)" data-status="active"><i
                                        class="bx bx-user-check text-success me-2"></i>Aktif Personeller</a></li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-status="passive"><i
                                        class="bx bx-user-x text-danger me-2"></i>İşten Ayrılanlar</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item" href="javascript:void(0)" data-status="all"><i
                                        class="bx bx-group text-primary me-2"></i>Tümü (Aktif + Ayrılan)</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Filtreler -->
            <div class="pool-filters">
                <?php echo \App\Helper\Form::FormFloatInput('text', 'personel-search', '', 'İsim veya departman...', 'Personel Ara', 'search'); ?>
            </div>

            <!-- Personel Listesi -->
            <div class="personel-list" id="personel-container">
                <?php foreach ($personeller as $personel):
                    $deptName = $personel->departman ?? 'Diğer';
                    $normalizedDept = normalizeDeptName($deptName);
                    $rowColor = $deptColorMap[$normalizedDept] ?? '#6b7280';
                    $isAyrilmis = ($personel->aktif_mi == 0) || (!empty($personel->isten_cikis_tarihi) && $personel->isten_cikis_tarihi !== '0000-00-00' && $personel->isten_cikis_tarihi <= date('Y-m-d'));
                    ?>
                    <div class="personel-item fc-event <?php echo $isAyrilmis ? 'personel-ayrilmis' : ''; ?>"
                        data-id="<?php echo \App\Helper\Security::encrypt($personel->id); ?>"
                        data-raw-id="<?php echo $personel->id; ?>"
                        data-name="<?php echo htmlspecialchars($personel->adi_soyadi); ?>"
                        data-departman="<?php echo htmlspecialchars($deptName); ?>" 
                        data-is-active="<?php echo $isAyrilmis ? '0' : '1'; ?>"
                        data-color="<?php echo $rowColor; ?>"
                        style="--dept-color: <?php echo $rowColor; ?>; <?php echo $isAyrilmis ? 'opacity: 0.85;' : ''; ?>">
                        <div class="d-flex justify-content-between align-items-start w-100">
                            <div class="personel-name d-flex align-items-center gap-1">
                                <span><?php echo htmlspecialchars($personel->adi_soyadi); ?></span>
                                <?php if ($isAyrilmis): ?>
                                    <span class="badge bg-soft-danger text-danger px-1 py-0" style="font-size: 9px; font-weight: 600;">Ayrıldı</span>
                                <?php endif; ?>
                            </div>
                            <span class="nobet-count" data-personel-id="<?php echo $personel->id; ?>">0</span>
                        </div>
                        <div class="personel-dept">
                            <?php echo htmlspecialchars($personel->departman ?? 'Departman Belirtilmemiş'); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <style>
                #btn-departman-legend .bx-chevron-down {
                    transition: transform 0.3s ease;
                }

                #btn-departman-legend[aria-expanded="true"] .bx-chevron-down {
                    transform: rotate(180deg);
                }

                [data-bs-theme="dark"] #btn-departman-legend {
                    background: #27272a !important;
                    border-color: #3f3f46 !important;
                    color: #a1a1aa !important;
                }

                [data-bs-theme="dark"] .departman-isim-text {
                    color: #a1a1aa !important;
                }
            </style>
            <div class="p-3 border-top border-light">
                <button id="btn-departman-legend"
                    class="btn btn-sm w-100 d-flex justify-content-between align-items-center text-muted rounded-2"
                    type="button" data-bs-toggle="collapse" data-bs-target="#departmanLegend" aria-expanded="false"
                    aria-controls="departmanLegend"
                    style="font-size: 12px; font-weight: 600; background: #f8fafc; border: 1px solid #e2e8f0; transition: all 0.2s;">
                    <span class="d-flex align-items-center"><i class="bx bx-palette me-2"
                            style="font-size: 16px;"></i>Departman Renkleri</span>
                    <i class="bx bx-chevron-down" style="font-size: 18px;"></i>
                </button>
                <div class="collapse" id="departmanLegend">
                    <div class="d-flex flex-wrap gap-2 pt-3">
                        <?php foreach ($deptColorMap as $normName => $color): ?>
                            <div class="d-flex align-items-center gap-2" style="font-size: 11px; width: calc(50% - 4px);">
                                <div
                                    style="background: <?php echo $color; ?>; width: 10px; height: 10px; border-radius: 3px; flex-shrink: 0;">
                                </div>
                                <span class="text-truncate departman-isim-text" style="color: #4b5563;"
                                    title="<?php echo htmlspecialchars($deptOriginalNames[$normName]); ?>"><?php echo htmlspecialchars($deptOriginalNames[$normName]); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Sağ Panel - Takvim -->
    <div class="calendar-panel">
        <!-- İstatistik Kartları - Modern Dashboard Tasarımı -->
        <div class="row mb-4 g-3">
            <div class="col-xl-3 col-md-6">
                <div class="stat-card"
                    style="--card-color: #3b82f6; --card-rgb: 59, 130, 246; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body">
                        <div class="icon-label-container">
                            <div class="icon-box">
                                <i class="bx bx-calendar-check fs-4" style="color: var(--card-color);"></i>
                            </div>
                            <div class="stat-trend up">
                                <i class="bx bx-trending-up"></i> +12%
                            </div>
                        </div>
                        <p class="stat-label-main">TOPLAM NÖBET</p>
                        <h4 class="stat-value" id="stat-total">0</h4>
                        <p class="stat-sub">Aylık planlama özeti</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card"
                    style="--card-color: #10b981; --card-rgb: 16, 185, 129; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body">
                        <div class="icon-label-container">
                            <div class="icon-box">
                                <i class="bx bx-user-check fs-4" style="color: var(--card-color);"></i>
                            </div>
                            <div class="stat-trend neutral">
                                <i class="bx bx-minus"></i> Stabil
                            </div>
                        </div>
                        <p class="stat-label-main">BUGÜN NÖBETÇİ</p>
                        <h4 class="stat-value" id="stat-today">-</h4>
                        <p class="stat-sub">Güncel görevli personel</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card"
                    style="--card-color: #f59e0b; --card-rgb: 245, 158, 11; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body">
                        <div class="icon-label-container">
                            <div class="icon-box">
                                <i class="bx bx-transfer-alt fs-4" style="color: var(--card-color);"></i>
                            </div>
                            <div class="stat-trend down">
                                <i class="bx bx-trending-down"></i> -8%
                            </div>
                        </div>
                        <p class="stat-label-main">BEKLEYEN TALEPLER</p>
                        <h4 class="stat-value" id="stat-pending">0</h4>
                        <p class="stat-sub">Onay bekleyen değişimler</p>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6">
                <div class="stat-card"
                    style="--card-color: #8b5cf6; --card-rgb: 139, 92, 246; border-bottom: 3px solid var(--card-color) !important;">
                    <div class="card-body">
                        <div class="icon-label-container">
                            <div class="icon-box">
                                <i class="bx bx-calendar-week fs-4" style="color: var(--card-color);"></i>
                            </div>
                            <div class="stat-trend up">
                                <i class="bx bx-trending-up"></i> +4%
                            </div>
                        </div>
                        <p class="stat-label-main">HAFTA SONU NÖBET</p>
                        <h4 class="stat-value" id="stat-weekend">0</h4>
                        <p class="stat-sub">Tatil günü mesaileri</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="calendar-card">
            <!-- 3. Görsel Referansı (Tab Bar ve Navigasyon) -->
            <div class="calendar-header">
                <div class="d-flex align-items-center gap-2">
                    <div class="view-buttons">
                        <button class="btn active" data-view="dayGridMonth">Ay</button>
                        <button class="btn" data-view="dayGridWeek">Hafta</button>
                        <button class="btn" data-view="multiMonthYear">Yıl</button>
                    </div>
                    <button class="btn btn-soft-primary"
                        style="background: rgba(85, 110, 230, 0.1); color: #556ee6; border: none; font-weight: 600; display: flex; align-items: center; gap: 5px; height: 38px; padding: 0 15px; border-radius: 8px;"
                        data-bs-toggle="modal" data-bs-target="#nobetBildirimModal">
                        <i class="bx bx-send"></i> Personele Bildir
                    </button>

                    <!-- Personel Filtrele -->
                    <div class="dropdown" id="personel-filter-container">
                        <button class="btn btn-soft-info" type="button" id="personel-filter-btn"
                            data-bs-toggle="dropdown" aria-expanded="false" title="Personel Filtrele"
                            style="background: rgba(0, 184, 217, 0.1); color: #00b8d9; border: none; font-weight: 600; display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; padding: 0; border-radius: 8px;">
                            <i class="bx bx-user fs-4"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0" id="personel-filter-list"
                            style="max-height: 300px; overflow-y: auto;">
                            <li><a class="dropdown-item active" href="javascript:void(0)" data-personel-id="all">Tüm
                                    Personeller</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <!-- Personeller JS ile buraya eklenecek -->
                        </ul>
                    </div>

                    <!-- Personel Nöbet İstatistikleri Butonu -->
                    <button class="btn btn-soft-warning" type="button" id="btn-nobet-istatistik"
                        data-bs-toggle="modal" data-bs-target="#nobetIstatistikModal" title="Personel Nöbet İstatistikleri"
                        style="background: rgba(245, 158, 11, 0.12); color: #d97706; border: none; font-weight: 600; display: flex; align-items: center; justify-content: center; width: 38px; height: 38px; padding: 0; border-radius: 8px;">
                        <i class="bx bx-bar-chart-alt-2 fs-4"></i>
                    </button>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="calendar-title text-uppercase font-weight-bold" id="calendar-title"
                        style="position:static; transform:none; font-size:14px;">ŞUBAT 2026</span>
                    <div class="calendar-nav d-flex gap-1">
                        <button class="btn" id="calendar-prev"><i class="bx bx-chevron-left"></i></button>
                        <button class="btn" id="calendar-today"
                            style="width:auto; height:32px; padding:0 12px; font-weight:500;">Bugün</button>
                        <button class="btn" id="calendar-next"><i class="bx bx-chevron-right"></i></button>
                        <?php if ($hasSettingPermission): ?>
                            <button class="btn ms-1" id="btn-nobet-settings" title="Nöbet Ayarları" data-bs-toggle="modal"
                                data-bs-target="#nobetSettingsModal">
                                <i class="bx bx-cog"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div id="nobet-calendar"></div>

            <!-- Legend -->
            <div class="legend-container">
                <div class="legend-item">
                    <div class="legend-color" style="background: #10b981;"></div>
                    <span>Devir Alındı</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #3b82f6;"></div>
                    <span>Planlı</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #ef4444;"></div>
                    <span>İptal</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color" style="background: #f59e0b;"></div>
                    <span>Mazeret Bildirildi</span>
                </div>
            </div>
        </div>
    </div>

</div>



<!-- Nöbet Detay Modal (Premium Modern Tasarım) -->
<div class="modal fade modern-settings-modal premium-detail-modal" id="nobetDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center">
                    <i class="bx bx-info-circle me-2 text-primary" style="font-size: 20px;"></i>
                    Giriş Kaydı Detayı
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Personel Kartı -->
                <div class="detail-personel-card d-flex align-items-center mb-4">
                    <div class="personel-avatar-wrapper me-3">
                        <img id="modal-personel-img" src="" class="rounded-circle" width="56" height="56"
                            style="object-fit: cover;">
                    </div>
                    <div class="personel-info">
                        <h6 class="mb-0 fw-bold" id="modal-personel-name" style="font-size: 16px;"></h6>
                        <p class="mb-0 text-muted small" id="modal-personel-dept" style="font-size: 13px;"></p>
                    </div>
                    <div class="ms-auto">
                        <span id="modal-durum-badge" class="badge rounded-pill px-3 py-2"
                            style="font-size: 12px; font-weight: 600;"></span>
                    </div>
                </div>

                <!-- Nöbet Zamanı -->
                <div class="detail-time-section text-center py-3 mb-4">
                    <div class="section-label mb-2">NÖBET ZAMAN DİLİMİ</div>
                    <h3 class="fw-bold mb-1" id="modal-tarih-text" style="font-size: 24px; letter-spacing: -0.01em;">
                    </h3>
                    <div class="text-primary fw-bold" id="modal-saat-text" style="font-size: 18px;"></div>
                </div>

                <!-- Detay Grid -->
                <div class="detail-grid row text-center border-top pt-4 mx-0">
                    <div class="col-4 border-end px-1">
                        <div class="grid-label">TİP</div>
                        <div id="modal-tip-text" class="grid-value"></div>
                    </div>
                    <div class="col-4 border-end px-1">
                        <div class="grid-label">İLETİŞİM</div>
                        <div id="modal-telefon-text" class="grid-value"></div>
                    </div>
                    <div class="col-4 px-1">
                        <div class="grid-label">BÖLGE</div>
                        <div id="modal-bolge-text" class="grid-value"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger btn-sm px-3 fw-bold me-auto" id="btn-delete-nobet">
                    <i class="bx bx-trash me-1"></i>Sil
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold"
                        data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary btn-sm px-4 fw-bold" id="btn-edit-nobet">
                        <i class="bx bx-edit me-1"></i>Düzenle
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Nöbet Ekleme/Düzenleme Modal -->
<div class="modal fade modern-settings-modal" id="nobetFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-plus-circle me-2 text-primary" id="form-modal-icon"></i>
                    <span id="form-modal-title">Nöbet Ekle</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="nobet-form">
                <div class="modal-body">
                    <input type="hidden" name="nobet_id" id="form-nobet-id">

                    <!-- Personel Seçimi -->
                    <div class="mb-3">
                        <?php
                        $personelOptions = [];
                        foreach ($personeller as $p) {
                            $personelOptions[\App\Helper\Security::encrypt($p->id)] = htmlspecialchars($p->adi_soyadi) . " - " . ($p->departman ?? '');
                        }
                        echo \App\Helper\Form::FormSelect2('personel_id', $personelOptions, '', 'Personel Seçiniz', 'users', 'key', '', 'form-select select2', true);
                        ?>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'nobet_tarihi', '', 'Günün tarihini seçin', 'Nöbet Tarihi', 'calendar', 'form-control flatpickr', true, '', 'off'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php
                            $tipOptions = [
                                'standart' => 'Standart',
                                'hafta_sonu' => 'Hafta Sonu',
                                'resmi_tatil' => 'Resmi Tatil',
                                'ozel' => 'Özel'
                            ];
                            echo \App\Helper\Form::FormSelect2('nobet_tipi', $tipOptions, 'standart', 'Nöbet Tipi', 'key', 'key', '', 'form-control select2', true);
                            ?>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <?php echo \App\Helper\Form::FormFloatInput('time', 'baslangic_saati', '18:00', '', 'Başlangıç Saati', 'clock', 'form-control', true); ?>
                        </div>
                        <div class="col-md-6">
                            <?php echo \App\Helper\Form::FormFloatInput('time', 'bitis_saati', '08:00', '', 'Bitiş Saati', 'clock', 'form-control', true); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatTextarea('aciklama', '', 'Ek notlar...', 'Açıklama', 'file-text', 'form-control', false, '80px'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold"
                        data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- Nöbet Bildirim Modalı (Geliştirilmiş Premium Tasarım) -->
<div class="modal fade modern-settings-modal" id="nobetBildirimModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-send me-2 text-primary"></i>Personele Bildir
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="nobet-bildirim-form">
                <div class="modal-body">
                    <!-- Bildirim İstatistikleri -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="border rounded-4 p-3 text-center bg-light">
                                <div class="text-muted small fw-bold">TOPLAM NÖBET</div>
                                <div class="fs-4 fw-bold text-dark" id="bildirim-stat-total">0</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-4 p-3 text-center bg-success bg-opacity-10">
                                <div class="text-muted small fw-bold">BİLDİRİLDİ</div>
                                <div class="fs-4 fw-bold text-success" id="bildirim-stat-sent">0</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded-4 p-3 text-center bg-warning bg-opacity-10">
                                <div class="text-muted small fw-bold">BEKLEYEN</div>
                                <div class="fs-4 fw-bold text-warning" id="bildirim-stat-pending">0</div>
                            </div>
                        </div>
                    </div>

                    <!-- Bekleyen Personeller Listesi -->
                    <div id="bildirim-pending-list-container" class="mb-4" style="display:none;">
                        <label class="form-label fw-bold mb-2 d-flex justify-content-between align-items-center" style="font-size: 12px; color: #71717a;">
                            <span>BİLDİRİM GÖNDERİLECEK PERSONELLER</span>
                            <span class="badge bg-warning bg-opacity-10 text-warning border-warning border-opacity-25" id="pending-list-count">0 Kişi</span>
                        </label>
                        <div id="bildirim-pending-list" class="border rounded-4 p-2 bg-light custom-scrollbar" style="max-height: 160px; overflow-y: auto;">
                            <!-- Dinamik olarak doldurulacak -->
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold mb-3" style="font-size: 13px; color: #71717a;">BİLDİRİM
                            KAPSAMI</label>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="radio" class="btn-check" name="bildirim_turu" id="turu_bekleyen"
                                value="bekleyen" checked>
                            <label class="btn btn-outline-warning btn-sm flex-grow-1 py-2" for="turu_bekleyen">
                                <i class="bx bx-bell-plus me-1"></i>Henüz Bildirilmeyenler
                            </label>

                            <input type="radio" class="btn-check" name="bildirim_turu" id="turu_aylik" value="aylik">
                            <label class="btn btn-outline-primary btn-sm flex-grow-1 py-2" for="turu_aylik">
                                <i class="bx bx-calendar me-1"></i>Aylık (Tümü)
                            </label>

                            <input type="radio" class="btn-check" name="bildirim_turu" id="turu_haftalik"
                                value="haftalik">
                            <label class="btn btn-outline-primary btn-sm flex-grow-1 py-2" for="turu_haftalik">
                                <i class="bx bx-calendar-week me-1"></i>Haftalık
                            </label>

                            <input type="radio" class="btn-check" name="bildirim_turu" id="turu_kisi" value="kisi">
                            <label class="btn btn-outline-primary btn-sm flex-grow-1 py-2" for="turu_kisi">
                                <i class="bx bx-user me-1"></i>Kişiye Özel
                            </label>
                        </div>
                    </div>

                    <!-- Bekleyen Seçimi (Varsayılan) -->
                    <div class="bildirim-area" id="area-bekleyen">
                        <div class="alert alert-warning border-0 bg-warning bg-opacity-10 d-flex align-items-center rounded-4"
                            role="alert">
                            <i class="bx bx-info-circle fs-4 me-3"></i>
                            <div class="small">
                                Henüz bildirim gönderilmemiş <span id="bekleyen-count" class="fw-bold">0</span>
                                personele bildirim gönderilecek.
                            </div>
                        </div>
                        <div class="mb-3">
                            <?php
                            $monthsNames = [1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran', 7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'];
                            $currentM = (int) date('m');
                            $currentY = (int) date('Y');
                            $monthOptions = [];
                            for ($i = -1; $i <= 2; $i++) {
                                $targetDate = strtotime("$i month");
                                $m = (int) date('m', $targetDate);
                                $y = (int) date('Y', $targetDate);
                                $monthOptions["$y-$m"] = "{$monthsNames[$m]} $y";
                            }
                            echo \App\Helper\Form::FormSelect2('bekleyen_ay', $monthOptions, "$currentY-$currentM", 'Ay Seçin', 'calendar', 'key', '', 'form-control select2 bildirim-ay-select', true);
                            ?>
                        </div>
                    </div>

                    <!-- Aylık Seçimi -->
                    <div class="bildirim-area" id="area-aylik" style="display:none;">
                        <div class="alert alert-info border-0 bg-info bg-opacity-10 d-flex align-items-center rounded-4"
                            role="alert">
                            <i class="bx bx-info-circle fs-4 me-3"></i>
                            <div class="small">Seçili aydaki <strong>tüm personellere</strong> bildirim gönderilecek
                                (daha önce bildirim almış olsalar bile).</div>
                        </div>
                        <div class="mb-3">
                            <?php
                            echo \App\Helper\Form::FormSelect2('bildirim_ayi', $monthOptions, "$currentY-$currentM", 'Bildirim Ayı Seçin', 'calendar', 'key', '', 'form-control select2-modal bildirim-ay-select', true);
                            ?>
                        </div>
                    </div>

                    <!-- Haftalık Seçimi -->
                    <div class="bildirim-area" id="area-haftalik" style="display:none;">
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <?php echo \App\Helper\Form::FormFloatInput('text', 'hafta_baslangic', date('Y-m-d'), '', 'Başlangıç', 'calendar', 'form-control flatpickr-modal', true); ?>
                            </div>
                            <div class="col-6">
                                <?php echo \App\Helper\Form::FormFloatInput('text', 'hafta_bitis', date('Y-m-d', strtotime('+6 days')), '', 'Bitiş', 'calendar', 'form-control flatpickr-modal', true); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Kişiye Özel -->
                    <div class="bildirim-area" id="area-kisi" style="display:none;">
                        <div class="mb-3">
                            <?php
                            $pOptions = [];
                            if (isset($personeller)) {
                                foreach ($personeller as $p) {
                                    $pOptions[\App\Helper\Security::encrypt($p->id)] = htmlspecialchars($p->adi_soyadi);
                                }
                            }
                            echo \App\Helper\Form::FormSelect2('personel_id', $pOptions, '', 'Personel Seçin', 'user', 'key', '', 'form-control select2-modal', true);
                            ?>
                        </div>
                        <div class="mb-3">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'tek_tarih', date('Y-m-d'), '', 'Nöbet Tarihi', 'calendar', 'form-control flatpickr-modal', true); ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <?php echo \App\Helper\Form::FormFloatTextarea('mesaj', '', 'Bildirime eklenecek özel not...', 'Ek Mesaj (Opsiyonel)', 'file-text', 'form-control', false, '80px'); ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold"
                        data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4 fw-bold">Bildirim Gönder</button>
                </div>
            </form>
        </div>
    </div>
</div>



<!-- Nöbet Ayarları Modalı -->
<div class="modal fade modern-settings-modal" id="nobetSettingsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nöbet Planlama Ayarları</h5>
            </div>
            <form id="nobet-settings-form">
                <div class="modal-body">
                    <div class="setting-group">
                        <div class="setting-main">
                            <div class="form-check form-switch form-switch-lg p-0">
                                <input class="form-check-input" type="checkbox" name="nobet_gecmis_islem"
                                    id="setting-gecmis-islem" <?php echo $canEditPast ? 'checked' : ''; ?>>
                            </div>
                            <label class="setting-label" for="setting-gecmis-islem">
                                Geçmiş Tarihlerde İşlem Yapılabilsin
                            </label>
                        </div>
                        <span class="settings-description">
                            Geçmiş tarihlerde nöbet düzenleme izni, takvim üzerindeki geçmiş etkinliklerin silinmesini
                            ve taşınmasını kontrol eder.
                        </span>
                    </div>

                    <div class="setting-group mt-4">
                        <div class="setting-main">
                            <div class="form-check form-switch form-switch-lg p-0">
                                <input class="form-check-input" type="checkbox" name="nobet_silinmis_goster"
                                    id="setting-silinmis-goster" <?php echo $showDeletedToStaff ? 'checked' : ''; ?>>
                            </div>
                            <label class="setting-label" for="setting-silinmis-goster">
                                Personele Göster: Silinmiş Nöbetler
                            </label>
                        </div>
                        <span class="settings-description">
                            Aktif edilirse, personel kendi sayfasında iptal edilmiş veya silinmiş nöbetlerini görmeye
                            devam eder.
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Personel Nöbet İstatistikleri Modalı -->
<div class="modal fade modern-settings-modal" id="nobetIstatistikModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-bottom py-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-sm d-flex align-items-center justify-content-center rounded-3" style="width: 42px; height: 42px; background: rgba(245, 158, 11, 0.15); color: #d97706;">
                        <i class="bx bx-bar-chart-alt-2 fs-3"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Personel Nöbet İstatistikleri</h5>
                        <p class="text-muted small mb-0" id="istatistik-donem-baslik">Dönem Nöbet Dağılımı ve Katılım Özeti</p>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-success d-flex align-items-center gap-1" id="btn-export-istatistik-excel">
                        <i class="bx bx-download"></i> Excel İndir
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
            </div>

            <div class="modal-body p-4">
                <!-- Filtreleme Araç Çubuğu (Form Helper ile Oluşturuldu) -->
                <?php
                $donemTuruOptions = [
                    'aylik' => 'Aylık Dönem',
                    'yillik' => 'Tüm Yıl',
                    'ozel' => 'Özel Tarih Aralığı'
                ];

                $ayOptions = [
                    '01' => 'Ocak',
                    '02' => 'Şubat',
                    '03' => 'Mart',
                    '04' => 'Nisan',
                    '05' => 'Mayıs',
                    '06' => 'Haziran',
                    '07' => 'Temmuz',
                    '08' => 'Ağustos',
                    '09' => 'Eylül',
                    '10' => 'Ekim',
                    '11' => 'Kasım',
                    '12' => 'Aralık'
                ];

                $yilOptions = [];
                $curYear = (int)date('Y');
                for ($y = $curYear - 2; $y <= $curYear + 2; $y++) {
                    $yilOptions[(string)$y] = (string)$y;
                }

                $deptFilterOptions = ['all' => 'Tüm Departmanlar'];
                if (!empty($departments)) {
                    foreach ($departments as $dept) {
                        $deptFilterOptions[$dept] = $dept;
                    }
                }

                $personelDurumOptions = [
                    'active' => 'Aktif Personeller',
                    'all' => 'Tümü (Ayrılanlar Dahil)',
                    'passive' => 'İşten Ayrılanlar'
                ];
                ?>
                <div class="card border shadow-none mb-4 bg-light bg-opacity-50">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center">
                            <!-- Dönem Türü Seçimi -->
                            <div class="col-md-2 col-sm-6" id="ist-filter-type-col">
                                <?php echo \App\Helper\Form::FormSelect2('ist_filter_type', $donemTuruOptions, 'aylik', 'Dönem Türü', 'calendar', 'key', '', 'form-select select2', false, 'width:100%', '', 'ist-filter-type'); ?>
                            </div>

                            <!-- Ay Seçimi -->
                            <div class="col-md-2 col-sm-6" id="ist-filter-ay-wrapper">
                                <?php echo \App\Helper\Form::FormSelect2('ist_filter_ay', $ayOptions, date('m'), 'Ay Seçimi', 'calendar-event', 'key', '', 'form-select select2', false, 'width:100%', '', 'ist-filter-ay'); ?>
                            </div>

                            <!-- Yıl Seçimi -->
                            <div class="col-md-2 col-sm-6" id="ist-filter-yil-wrapper">
                                <?php echo \App\Helper\Form::FormSelect2('ist_filter_yil', $yilOptions, (string)date('Y'), 'Yıl Seçimi', 'calendar-alt', 'key', '', 'form-select select2', false, 'width:100%', '', 'ist-filter-yil'); ?>
                            </div>

                            <!-- Özel Tarih Aralığı (Özel Seçilirse) -->
                            <div class="col-md-4 col-sm-6 d-none" id="ist-filter-tarih-wrapper">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('date', 'ist-filter-baslangic', date('Y-m-01'), '', 'Başlangıç', 'calendar', 'form-control', false, null, 'off'); ?>
                                    </div>
                                    <div class="col-6">
                                        <?php echo \App\Helper\Form::FormFloatInput('date', 'ist-filter-bitis', date('Y-m-t'), '', 'Bitiş', 'calendar', 'form-control', false, null, 'off'); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Departman Seçimi -->
                            <div class="col-md-3 col-sm-6" id="ist-filter-dept-wrapper">
                                <?php echo \App\Helper\Form::FormSelect2('ist_filter_departman', $deptFilterOptions, 'all', 'Departman', 'buildings', 'key', '', 'form-select select2', false, 'width:100%', '', 'ist-filter-departman'); ?>
                            </div>

                            <!-- Personel Durumu Seçimi -->
                            <div class="col-md-2 col-sm-6" id="ist-filter-status-wrapper">
                                <?php echo \App\Helper\Form::FormSelect2('ist_filter_status', $personelDurumOptions, 'active', 'Durum', 'user-check', 'key', '', 'form-select select2', false, 'width:100%', '', 'ist-filter-status'); ?>
                            </div>

                            <!-- Yenile Butonu -->
                            <div class="col-md-1 col-sm-6">
                                <button type="button" class="btn btn-primary w-100 d-flex align-items-center justify-content-center shadow-sm" id="btn-ist-filtre-uygula" title="Filtrele" style="height: 58px; border-radius: 8px;">
                                    <i class="bx bx-refresh fs-3"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KPI Kartları -->
                <div class="row g-3 mb-4" id="ist-kpi-cards">
                    <div class="col-xl-3 col-sm-6">
                        <div class="card border shadow-sm h-100 p-3 mb-0" style="border-left: 4px solid #3b82f6 !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted small fw-bold text-uppercase" style="font-size: 11px;">Toplam Nöbet</span>
                                <div class="avatar-xs d-flex align-items-center justify-content-center rounded" style="width: 32px; height: 32px; background: rgba(59, 130, 246, 0.12); color: #3b82f6;">
                                    <i class="bx bx-calendar fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-dark" id="ist-kpi-toplam-nobet">0</h3>
                            <span class="text-muted small" id="ist-kpi-donem-text" style="font-size: 11px;">Seçili dönemde</span>
                        </div>
                    </div>

                    <div class="col-xl-3 col-sm-6">
                        <div class="card border shadow-sm h-100 p-3 mb-0" style="border-left: 4px solid #10b981 !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted small fw-bold text-uppercase" style="font-size: 11px;">Nöbet Tutan Personel</span>
                                <div class="avatar-xs d-flex align-items-center justify-content-center rounded" style="width: 32px; height: 32px; background: rgba(16, 185, 129, 0.12); color: #10b981;">
                                    <i class="bx bx-user-check fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-dark" id="ist-kpi-tutan-personel">0 / 0</h3>
                            <span class="text-muted small" id="ist-kpi-katilim-orani" style="font-size: 11px;">%0 Katılım Oranı</span>
                        </div>
                    </div>

                    <div class="col-xl-3 col-sm-6">
                        <div class="card border shadow-sm h-100 p-3 mb-0" style="border-left: 4px solid #f59e0b !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted small fw-bold text-uppercase" style="font-size: 11px;">Ortalama Nöbet</span>
                                <div class="avatar-xs d-flex align-items-center justify-content-center rounded" style="width: 32px; height: 32px; background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                                    <i class="bx bx-calculator fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-dark" id="ist-kpi-ortalama">0.0</h3>
                            <span class="text-muted small" style="font-size: 11px;">Nöbet tutan personel başına</span>
                        </div>
                    </div>

                    <div class="col-xl-3 col-sm-6">
                        <div class="card border shadow-sm h-100 p-3 mb-0" style="border-left: 4px solid #8b5cf6 !important;">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="text-muted small fw-bold text-uppercase" style="font-size: 11px;">Hafta Sonu / Tatil</span>
                                <div class="avatar-xs d-flex align-items-center justify-content-center rounded" style="width: 32px; height: 32px; background: rgba(139, 92, 246, 0.12); color: #8b5cf6;">
                                    <i class="bx bx-calendar-week fs-5"></i>
                                </div>
                            </div>
                            <h3 class="fw-bold mb-1 text-dark" id="ist-kpi-haftasonu-tatil">0 / 0</h3>
                            <span class="text-muted small" id="ist-kpi-haftasonu-oran" style="font-size: 11px;">%0 Hafta Sonu Oranı</span>
                        </div>
                    </div>
                </div>

                <!-- Tablo Başlık ve Hızlı Arama & Excel Butonu -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center gap-3">
                        <h6 class="fw-bold mb-0">Personel Dağılım Listesi</h6>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="ist-toggle-only-active" checked>
                            <label class="form-check-label small text-muted" for="ist-toggle-only-active">Yalnızca Nöbeti Olanları Göster</label>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div style="min-width: 240px;">
                            <?php echo \App\Helper\Form::FormFloatInput('text', 'ist-table-search', '', 'Personel ara...', 'Personel Ara', 'search', 'form-control'); ?>
                        </div>
                        <button type="button" class="btn btn-success d-flex align-items-center gap-2 fw-bold shadow-sm" id="btn-export-istatistik-excel" style="height: 58px; padding: 0 18px; border-radius: 8px;">
                            <i class="bx bxs-file-export fs-4"></i>
                            <span>Excel İndir</span>
                        </button>
                    </div>
                </div>

                <!-- Tablo -->
                <div class="table-responsive rounded border mb-4" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0" id="ist-personel-table">
                        <thead class="table-light sticky-top" style="z-index: 2;">
                            <tr style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">
                                <th style="width: 40px;" class="text-center">#</th>
                                <th>Personel</th>
                                <th>Departman</th>
                                <th class="text-center">Hafta İçi</th>
                                <th class="text-center">Hafta Sonu</th>
                                <th class="text-center">Resmi Tatil</th>
                                <th class="text-center">Toplam</th>
                                <th style="width: 170px;">Nöbet Payı</th>
                                <th class="text-center">Son Nöbet</th>
                                <th class="text-center" style="width: 90px;">Eylem</th>
                            </tr>
                        </thead>
                        <tbody id="ist-personel-tbody" style="font-size: 13px;">
                            <tr>
                                <td colspan="10" class="text-center py-4 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                    İstatistikler yükleniyor...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Departman Dağılım Özeti -->
                <div class="card border shadow-none bg-light bg-opacity-25 mb-0">
                    <div class="card-body p-3">
                        <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
                            <i class="bx bx-buildings text-primary"></i> Departman Bazlı Nöbet Dağılımı
                        </h6>
                        <div class="row g-2" id="ist-departman-list">
                            <!-- Dinamik doldurulacak -->
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer border-top py-2">
                <button type="button" class="btn btn-secondary btn-sm px-4 fw-bold" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<!-- Custom Context Menu -->
<div id="custom-context-menu" class="custom-context-menu shadow-lg">
    <div class="menu-header" id="menu-personel-name">PERSONEL ADI</div>
      <?php if ($canApprove): ?>
    <div class="menu-divider admin-only"></div>
    <div id="menu-item-approve" class="menu-item admin-only text-success">
        <i class="bx bx-check-circle"></i>
        <span>Nöbeti Onayla</span>
    </div>
    <div id="menu-item-unapprove" class="menu-item admin-only text-warning">
        <i class="bx bx-undo"></i>
        <span>Onayı Kaldır</span>
    </div>
    <?php endif; ?>
    <div class="menu-divider"></div>
    <div class="menu-item menu-item-tip" data-tip="hafta_sonu">
        <i class="bx bx-calendar-week text-primary"></i>
        <span>Hafta Sonu Nöbeti Yap</span>
    </div>
    <div class="menu-item menu-item-tip" data-tip="standart">
        <i class="bx bx-calendar text-secondary"></i>
        <span>Hafta İçi Nöbet Yap</span>
    </div>
    <div class="menu-item menu-item-tip" data-tip="resmi_tatil">
        <i class="bx bx-flag text-warning"></i>
        <span>Resmi Tatil Nöbeti Yap</span>
    </div>
    <div class="menu-divider"></div>
    <div id="menu-item-delete" class="menu-item text-danger">
        <i class="bx bx-trash"></i>
        <span>Nöbeti Sil</span>
    </div>
    
  

</div>

<!-- FullCalendar -->
<script src="assets/libs/fullcalendar/index.global.min.js"></script>


<script>
    document.addEventListener('DOMContentLoaded', function () {

        // ============================================
        // DEĞİŞKENLER
        // ============================================
        let calendar;
        let currentNobetId = null;
        let selectedFilterPersonelId = 'all'; // Personel filtreleme için
        let canEditPast = <?php echo $canEditPast ? 'true' : 'false'; ?>;
        const detailModal = new bootstrap.Modal(document.getElementById('nobetDetailModal'));
        const formModal = new bootstrap.Modal(document.getElementById('nobetFormModal'));
        const settingsModal = new bootstrap.Modal(document.getElementById('nobetSettingsModal'));

        // ============================================
        // TAKVİM BAŞLATMA
        // ============================================
        const calendarEl = document.getElementById('nobet-calendar');
        const Draggable = FullCalendar.Draggable;

        // Personel kartlarını sürüklenebilir yap
        new Draggable(document.getElementById('personel-container'), {
            itemSelector: '.personel-item',
            eventData: function (eventEl) {
                return {
                    id: 'new-' + Date.now(),
                    title: eventEl.dataset.name,
                    backgroundColor: eventEl.dataset.color,
                    borderColor: eventEl.dataset.color,
                    allDay: true,
                    extendedProps: {
                        personel_id: eventEl.dataset.id,
                        departman: eventEl.dataset.departman,
                        isNew: true
                    }
                };
            }
        });

        // Takvimi başlat
        calendar = new FullCalendar.Calendar(calendarEl, {
            locale: 'tr',
            timeZone: 'local',
            firstDay: 1,
            initialView: 'dayGridMonth',
            showNonCurrentDates: false,
            fixedWeekCount: false,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            editable: true,
            eventDurationEditable: false,
            droppable: true,
            selectable: true,
            selectAllow: function (selectInfo) {
                // Sadece tek gün seçimine izin ver (end - start = 1 gün)
                const diffTime = Math.abs(selectInfo.end - selectInfo.start);
                const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
                return diffDays <= 1;
            },
            dayMaxEvents: 4,
            height: '100%',

            // Etkinlikleri yükle
            events: function (info, successCallback, failureCallback) {
                fetch('views/nobet/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'get-calendar-events',
                        start: info.startStr,
                        end: info.endStr
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        let filteredData = data;
                        if (selectedFilterPersonelId !== 'all') {
                            filteredData = data.filter(ev => ev.extendedProps.raw_personel_id == selectedFilterPersonelId);
                        }
                        successCallback(filteredData);
                        updateStats(data, filteredData);
                    })
                    .catch(error => {
                        console.error('Takvim yükleme hatası:', error);
                        failureCallback(error);
                    });
            },

            // Tarih seçildiğinde - Yeni nöbet ekle
            dateClick: function (info) {
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const selectedDate = new Date(info.dateStr); selectedDate.setHours(0, 0, 0, 0);

                if (!canEditPast && selectedDate < today) {
                    showToast('error', 'Geçmiş tarihlere nöbet ekleyemezsiniz.');
                    return;
                }
                openFormModal(null, info.dateStr);
            },

            // Etkinlik tıklandığında - Detay göster
            eventClick: function (info) {
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const eventDate = new Date(info.event.start); eventDate.setHours(0, 0, 0, 0);

                if (!canEditPast && eventDate < today) {
                    showToast('error', 'Geçmiş nöbetlerde düzenleme yapılamaz.');
                    return;
                }
                showNobetDetail(info.event);
            },

            // Personel sürüklenip bırakıldığında
            eventReceive: function (info) {
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const eventDate = new Date(info.event.start); eventDate.setHours(0, 0, 0, 0);

                if (!canEditPast && eventDate < today) {
                    info.revert();
                    showToast('error', 'Geçmiş tarihlere nöbet atayamazsınız.');
                    return;
                }

                if (info.event.extendedProps.isNew) {
                    saveDroppedNobet(info);
                }
            },

            // Mevcut etkinlik taşındığında
            eventDrop: function (info) {
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const oldDate = new Date(info.oldEvent.start); oldDate.setHours(0, 0, 0, 0);

                if (!canEditPast && oldDate < today) {
                    info.revert();
                    showToast('error', 'Geçmiş nöbetler taşınamaz.');
                    return;
                }

                const newDate = new Date(info.event.start); newDate.setHours(0, 0, 0, 0);
                if (!canEditPast && newDate < today) {
                    info.revert();
                    showToast('error', 'Geçmiş tarihlere nöbet taşıyamazsınız.');
                    return;
                }

                moveNobet(info.event.id, info.event.startStr);
            },

            // Etkinlik yeniden boyutlandırıldığında
            eventResize: function (info) {
                // Nöbetler tek günlük olduğu için gerek yok ama ileride genişletilebilir
            },

            // Her etkinlik render edildiğinde - Silme ve Durum ikonları ekle
            eventDidMount: function (info) {
                const today = new Date(); today.setHours(0, 0, 0, 0);
                const eventDate = new Date(info.event.start); eventDate.setHours(0, 0, 0, 0);
                const isPast = eventDate < today;
                const isReadOnlyPast = isPast && !canEditPast;

                // Tooltip Ekle
                info.el.setAttribute('data-bs-toggle', 'tooltip');
                info.el.setAttribute('data-bs-placement', 'top');
                info.el.setAttribute('data-bs-title', info.event.title + ' | ' + formatDate(info.event.startStr));
                new bootstrap.Tooltip(info.el);

                if (isReadOnlyPast) {
                    info.el.classList.add('fc-event-past');
                    info.event.setProp('editable', false);
                } else {
                    info.el.classList.remove('fc-event-past');
                    info.event.setProp('startEditable', true);
                    info.event.setProp('durationEditable', false);
                }

                // Silme butonu (Gelecek nöbetler veya ayar açıksa geçmiş nöbetler için)
                if (!isReadOnlyPast) {
                    const deleteBtn = document.createElement('div');
                    deleteBtn.className = 'fc-event-delete-btn';
                    deleteBtn.innerHTML = '<i class="bx bx-x"></i>';
                    deleteBtn.title = 'Nöbeti Sil';

                    deleteBtn.onclick = function (e) {
                        e.stopPropagation();
                        deleteNobet(info.event.id);
                    };

                    info.el.appendChild(deleteBtn);
                }

                // Durum İkonları (Sol Üst)
                const props = info.event.extendedProps;
                if (props.durum === 'mazeret_bildirildi' || props.durum === 'talep_edildi' || props.has_talep) {
                    const statusContainer = document.createElement('div');
                    statusContainer.className = 'fc-event-status-icons';

                    // Mazeret İkonu (Alert)
                    if (props.durum === 'mazeret_bildirildi') {
                        const mIcon = document.createElement('div');
                        mIcon.className = 'fc-event-status-icon mazeret';
                        mIcon.innerHTML = '<i class="bx bx-error-circle"></i>';
                        mIcon.title = 'Mazeret Bildirildi / İptal Talebi';
                        statusContainer.appendChild(mIcon);
                    }

                    // Onay Bekleyen Nöbet Talebi İkonu (Saat)
                    if (props.durum === 'talep_edildi') {
                        const pIcon = document.createElement('div');
                        pIcon.className = 'fc-event-status-icon talep';
                        pIcon.innerHTML = '<i class="bx bx-time-five"></i>';
                        pIcon.title = 'Onay Bekleyen Nöbet Talebi';
                        statusContainer.appendChild(pIcon);
                    }

                    // Değişim Talebi İkonu (Refresh)
                    if (props.has_talep) {
                        const tIcon = document.createElement('div');
                        tIcon.className = 'fc-event-status-icon talep';
                        tIcon.innerHTML = '<i class="bx bx-sync"></i>';
                        tIcon.title = 'Bekleyen Değişim Talebi';
                        statusContainer.appendChild(tIcon);
                    }

                    info.el.appendChild(statusContainer);
                }

                // Nöbet Tipi İkonu (Görünürlüğü artırmak için)
                if (props.nobet_tipi === 'hafta_sonu' || props.nobet_tipi === 'resmi_tatil') {
                    const typeBadge = document.createElement('div');
                    typeBadge.className = 'fc-event-type-badge ' + (props.nobet_tipi === 'hafta_sonu' ? 'weekend' : 'holiday');
                    typeBadge.innerHTML = props.nobet_tipi === 'hafta_sonu' ? '<i class="bx bx-calendar-star"></i> HS' : '<i class="bx bx-flag"></i> RT';
                    info.el.appendChild(typeBadge);
                }

                // Sağ Tık Menüsü Listener
                info.el.addEventListener('contextmenu', function (e) {
                    e.preventDefault();
                    if (isReadOnlyPast) return;

                    const x = e.clientX;
                    const y = e.clientY;

                    const contextMenu = document.getElementById('custom-context-menu');
                    contextMenu.style.display = 'block';

                    // Ekran sınırlarını kontrol et
                    const menuWidth = contextMenu.offsetWidth || 200;
                    const menuHeight = contextMenu.offsetHeight || 200;
                    const winWidth = window.innerWidth;
                    const winHeight = window.innerHeight;

                    let finalX = x;
                    let finalY = y;

                    if (x + menuWidth > winWidth) finalX = winWidth - menuWidth - 10;
                    if (y + menuHeight > winHeight) finalY = winHeight - menuHeight - 10;

                    contextMenu.style.left = finalX + 'px';
                    contextMenu.style.top = finalY + 'px';
                    contextMenu.dataset.eventId = info.event.id;

                    document.getElementById('menu-personel-name').textContent = info.event.title;

                    // Aktif tipi işaretle
                    const currentTip = info.event.extendedProps.nobet_tipi;
                    document.querySelectorAll('.menu-item-tip').forEach(item => {
                        if (item.dataset.tip === currentTip) {
                            item.classList.add('active');
                        } else {
                            item.classList.remove('active');
                        }
                    });

                    // Onay butonlarını göster/gizle
                    const isOnayli = info.event.extendedProps.yonetici_onayi == 1;
                    const hasTalep = info.event.extendedProps.has_talep;
                    const approveBtn = document.getElementById('menu-item-approve');
                    const unapproveBtn = document.getElementById('menu-item-unapprove');
                    
                    if (approveBtn) {
                        // Eğer talep varsa veya onaylanmamışsa onayla butonu görünmeli
                        approveBtn.style.display = (hasTalep || !isOnayli) ? 'flex' : 'none';
                        approveBtn.querySelector('span').textContent = hasTalep ? 'Değişimi Onayla' : 'Nöbeti Onayla';
                    }
                    if (unapproveBtn) {
                        // Onay kaldır butonu sadece onaylanmış ve bekleyen talebi OLMAYAN nöbetlerde görünmeli
                        unapproveBtn.style.display = (isOnayli && !hasTalep) ? 'flex' : 'none';
                    }
                });
            },

            // Tooltip temizliği
            eventWillUnmount: function (info) {
                const tooltip = bootstrap.Tooltip.getInstance(info.el);
                if (tooltip) {
                    tooltip.dispose();
                }
            }
        });

        // ============================================
        // AYARLAR KAYDETME
        // ============================================
        document.getElementById('nobet-settings-form').addEventListener('submit', function (e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'save-settings');

            // Switch (checkbox) değeri kontrolü
            const isChecked = document.getElementById('setting-gecmis-islem').checked;
            formData.set('nobet_gecmis_islem', isChecked ? '1' : '0');

            const isShowDeletedChecked = document.getElementById('setting-silinmis-goster').checked;
            formData.set('nobet_silinmis_goster', isShowDeletedChecked ? '1' : '0');

            fetch('views/nobet/api.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('success', data.message);
                        canEditPast = isChecked;
                        calendar.refetchEvents();
                        settingsModal.hide();
                    } else {
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    console.error('Ayarlar kaydedilemedi:', error);
                    showToast('error', 'Bir hata oluştu.');
                });
        });

        calendar.render();

        // ============================================
        // TAKVİM BAŞLIK GÜNCELLEME
        // ============================================
        function updateCalendarTitle() {
            const view = calendar.view;
            const titleEl = document.getElementById('calendar-title');
            if (titleEl) {
                if (view.type === 'multiMonthYear') {
                    titleEl.textContent = view.currentStart.getFullYear();
                } else if (view.type === 'dayGridWeek') {
                    // Hafta görünümü için tarih aralığı
                    const start = view.activeStart;
                    const end = new Date(view.activeEnd);
                    end.setDate(end.getDate() - 1);

                    const startMonth = start.toLocaleDateString('tr-TR', { month: 'short' });
                    const endMonth = end.toLocaleDateString('tr-TR', { month: 'short' });

                    if (startMonth === endMonth) {
                        titleEl.textContent = `${start.getDate()} - ${end.getDate()} ${startMonth.toUpperCase()} ${start.getFullYear()}`;
                    } else {
                        titleEl.textContent = `${start.getDate()} ${startMonth.toUpperCase()} - ${end.getDate()} ${endMonth.toUpperCase()} ${start.getFullYear()}`;
                    }
                } else {
                    const date = view.currentStart;
                    const months = ['OCAK', 'ŞUBAT', 'MART', 'NİSAN', 'MAYIS', 'HAZİRAN',
                        'TEMMUZ', 'AĞUSTOS', 'EYLÜL', 'EKİM', 'KASIM', 'ARALIK'];
                    titleEl.textContent = months[date.getMonth()] + ' ' + date.getFullYear();
                }
            }
        }

        // İlk yüklemede başlığı güncelle
        setTimeout(updateCalendarTitle, 100);

        // ============================================
        // TAKVİM NAVİGASYON BUTONLARI
        // ============================================
        document.getElementById('calendar-prev').addEventListener('click', function () {
            calendar.prev();
            updateCalendarTitle();
        });

        document.getElementById('calendar-next').addEventListener('click', function () {
            calendar.next();
            updateCalendarTitle();
        });

        document.getElementById('calendar-today').addEventListener('click', function () {
            calendar.today();
            updateCalendarTitle();
        });

        // ============================================
        // GÖRÜNÜM DEĞİŞTİRME
        // ============================================
        document.querySelectorAll('.view-buttons .btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.view-buttons .btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                calendar.changeView(this.dataset.view);
                updateCalendarTitle();
            });
        });

        // ============================================
        // PERSONEL FİLTRELEME
        // ============================================
        const searchInput = document.getElementById('personel-search');
        const defaultDept = 'Kesme Açma';
        let selectedDept = localStorage.getItem('nobet_selected_dept') || defaultDept;

        // Eski veya tireli kayıtları düzelt ('Kesme-Açma' -> 'Kesme Açma')
        if (selectedDept === 'Kesme-Açma') {
            selectedDept = 'Kesme Açma';
            localStorage.setItem('nobet_selected_dept', selectedDept);
        }

        function normalizeDept(name) {
            if (!name) return '';
            return name.replace(/[-_]/g, ' ').trim().toLowerCase();
        }

        function matchDeptStrings(dept1, dept2) {
            if (!dept1 || !dept2) return false;
            if (dept1 === 'all' || dept2 === 'all') return true;
            if (dept1 === dept2) return true;
            return normalizeDept(dept1) === normalizeDept(dept2);
        }

        // Departman Dropdown Takibi
        const deptDropdownItems = document.querySelectorAll('#dept-filter-dropdown .dropdown-item');
        deptDropdownItems.forEach(item => {
            item.addEventListener('click', function () {
                // UI Güncelle
                deptDropdownItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');

                selectedDept = this.dataset.dept;

                // localStorage'a kaydet
                localStorage.setItem('nobet_selected_dept', selectedDept);

                // Buton Rengini Değiştir
                const filterBtn = document.getElementById('dept-filter-btn');
                if (filterBtn) {
                    if (selectedDept === 'all') {
                        filterBtn.classList.remove('btn-primary');
                        filterBtn.classList.add('btn-outline-light');
                    } else {
                        filterBtn.classList.remove('btn-outline-light');
                        filterBtn.classList.add('btn-primary');
                    }
                }

                filterPersonel();
            });
        });

        // Personel Durumu (Aktif / Ayrılan / Tümü) Dropdown Takibi
        let selectedStatus = localStorage.getItem('nobet_selected_status') || 'active';
        const statusDropdownItems = document.querySelectorAll('#status-filter-dropdown .dropdown-item');
        statusDropdownItems.forEach(item => {
            item.addEventListener('click', function () {
                statusDropdownItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                selectedStatus = this.dataset.status;
                localStorage.setItem('nobet_selected_status', selectedStatus);

                const statusBtn = document.getElementById('status-filter-btn');
                if (statusBtn) {
                    if (selectedStatus === 'active') {
                        statusBtn.innerHTML = '<i class="bx bx-user-check"></i>';
                        statusBtn.classList.remove('btn-primary', 'btn-danger');
                        statusBtn.classList.add('btn-outline-light');
                        statusBtn.title = 'Personel Durumu: Aktif';
                    } else if (selectedStatus === 'passive') {
                        statusBtn.innerHTML = '<i class="bx bx-user-x"></i>';
                        statusBtn.classList.remove('btn-outline-light', 'btn-primary');
                        statusBtn.classList.add('btn-danger');
                        statusBtn.title = 'Personel Durumu: İşten Ayrılanlar';
                    } else {
                        statusBtn.innerHTML = '<i class="bx bx-group"></i>';
                        statusBtn.classList.remove('btn-outline-light', 'btn-danger');
                        statusBtn.classList.add('btn-primary');
                        statusBtn.title = 'Personel Durumu: Tümü';
                    }
                }

                filterPersonel();
            });
        });

        // Sayfa açılışında kayıtlı/varsayılan departmanı ve durumu aktif yap
        (function applyStoredDept() {
            let matched = false;

            // Dropdown'da aktif olanı işaretle
            deptDropdownItems.forEach(i => {
                if (matchDeptStrings(i.dataset.dept, selectedDept)) {
                    i.classList.add('active');
                    selectedDept = i.dataset.dept; // Gerçek dropdown değerine eşitle
                    matched = true;
                } else {
                    i.classList.remove('active');
                }
            });

            // Eğer kayıtlı departman hiçbir seçenekte bulunamadıysa varsayılana ('Kesme Açma' veya 'all') dön
            if (!matched) {
                selectedDept = defaultDept;
                deptDropdownItems.forEach(i => {
                    if (matchDeptStrings(i.dataset.dept, selectedDept)) {
                        i.classList.add('active');
                        selectedDept = i.dataset.dept;
                        matched = true;
                    }
                });
                if (!matched) {
                    selectedDept = 'all';
                    const allItem = document.querySelector('#dept-filter-dropdown .dropdown-item[data-dept="all"]');
                    if (allItem) allItem.classList.add('active');
                }
                localStorage.setItem('nobet_selected_dept', selectedDept);
            }

            // Durum dropdown'ını işaretle
            statusDropdownItems.forEach(i => {
                if (i.dataset.status === selectedStatus) {
                    i.classList.add('active');
                } else {
                    i.classList.remove('active');
                }
            });

            // Buton stillerini güncelle
            const filterBtn = document.getElementById('dept-filter-btn');
            if (filterBtn) {
                if (selectedDept !== 'all') {
                    filterBtn.classList.remove('btn-outline-light');
                    filterBtn.classList.add('btn-primary');
                } else {
                    filterBtn.classList.remove('btn-primary');
                    filterBtn.classList.add('btn-outline-light');
                }
            }

            const statusBtn = document.getElementById('status-filter-btn');
            if (statusBtn) {
                if (selectedStatus === 'active') {
                    statusBtn.innerHTML = '<i class="bx bx-user-check"></i>';
                    statusBtn.classList.remove('btn-primary', 'btn-danger');
                    statusBtn.classList.add('btn-outline-light');
                } else if (selectedStatus === 'passive') {
                    statusBtn.innerHTML = '<i class="bx bx-user-x"></i>';
                    statusBtn.classList.remove('btn-outline-light', 'btn-primary');
                    statusBtn.classList.add('btn-danger');
                } else {
                    statusBtn.innerHTML = '<i class="bx bx-group"></i>';
                    statusBtn.classList.remove('btn-outline-light', 'btn-danger');
                    statusBtn.classList.add('btn-primary');
                }
            }

            // Filtreyi uygula
            filterPersonel();
        })();

        function filterPersonel() {
            const searchTerm = (searchInput ? searchInput.value : '').toLowerCase().trim();

            document.querySelectorAll('.personel-item').forEach(item => {
                const name = (item.dataset.name || '').toLowerCase();
                const itemDept = item.dataset.departman || ''; // Orijinal departman adı
                const itemDeptLower = itemDept.toLowerCase();
                const isActive = item.dataset.isActive; // '1' or '0'

                // Hem arama terimine, hem seçili departmana, hem personel durumuna bak
                const matchSearch = searchTerm === '' || name.includes(searchTerm) || itemDeptLower.includes(searchTerm);
                const matchDept = matchDeptStrings(itemDept, selectedDept);

                let matchStatus = true;
                if (selectedStatus === 'active') {
                    matchStatus = (isActive === '1');
                } else if (selectedStatus === 'passive') {
                    matchStatus = (isActive === '0');
                }

                if (matchSearch && matchDept && matchStatus) {
                    item.style.setProperty('display', 'flex', 'important');
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', filterPersonel);
        }

        // ============================================
        // PERSONEL SIRALAMA
        // ============================================
        let currentSort = 'az';
        const sortDropdownItems = document.querySelectorAll('#personel-sort-dropdown .dropdown-item');

        sortDropdownItems.forEach(item => {
            item.addEventListener('click', function () {
                sortDropdownItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                currentSort = this.dataset.sort;

                // Buton rengini güncelle
                const sortBtn = document.getElementById('personel-sort-btn');
                if (currentSort === 'az') {
                    sortBtn.classList.replace('btn-primary', 'btn-outline-light');
                } else {
                    sortBtn.classList.replace('btn-outline-light', 'btn-primary');
                }

                sortPersonel();
            });
        });

        function sortPersonel() {
            const container = document.getElementById('personel-container');
            const items = Array.from(container.querySelectorAll('.personel-item'));

            items.sort((a, b) => {
                let valA, valB;

                switch (currentSort) {
                    case 'az':
                        valA = a.dataset.name.toLowerCase();
                        valB = b.dataset.name.toLowerCase();
                        return valA.localeCompare(valB, 'tr');
                    case 'za':
                        valA = a.dataset.name.toLowerCase();
                        valB = b.dataset.name.toLowerCase();
                        return valB.localeCompare(valA, 'tr');
                    case 'count_low':
                        valA = parseInt(a.querySelector('.nobet-count').textContent) || 0;
                        valB = parseInt(b.querySelector('.nobet-count').textContent) || 0;
                        return valA - valB;
                    case 'count_high':
                        valA = parseInt(a.querySelector('.nobet-count').textContent) || 0;
                        valB = parseInt(b.querySelector('.nobet-count').textContent) || 0;
                        return valB - valA;
                    case 'dept':
                        valA = a.dataset.departman.toLowerCase();
                        valB = b.dataset.departman.toLowerCase();
                        if (valA === valB) {
                            return a.dataset.name.toLowerCase().localeCompare(b.dataset.name.toLowerCase(), 'tr');
                        }
                        return valA.localeCompare(valB, 'tr');
                    default:
                        return 0;
                }
            });

            // Re-append items in sorted order
            items.forEach(item => container.appendChild(item));
        }

        // ============================================
        // MODAL FONKSİYONLARI
        // ============================================
        function showNobetDetail(event) {
            currentNobetId = event.id;
            const props = event.extendedProps;

            // Personel Bilgileri
            document.getElementById('modal-personel-img').src = props.resim || 'assets/images/users/user-dummy-img.jpg';
            document.getElementById('modal-personel-name').textContent = event.title;
            document.getElementById('modal-personel-dept').textContent = props.departman || 'Departman belirtilmemiş';

            // Zaman Bilgileri
            document.getElementById('modal-tarih-text').textContent = formatDate(event.startStr);
            document.getElementById('modal-saat-text').textContent = (props.baslangic_saati || '18:00').substring(0, 5) + ' - ' + (props.bitis_saati || '08:00').substring(0, 5);

            // Detaylar
            document.getElementById('modal-tip-text').textContent = getNobetTipiText(props.nobet_tipi);
            document.getElementById('modal-telefon-text').textContent = props.telefon || '-';
            document.getElementById('modal-bolge-text').textContent = props.ekip_bolge || '-';

            // Durum Badge
            const badge = document.getElementById('modal-durum-badge');
            badge.textContent = props.has_talep ? 'Değişim Onayı Bekliyor' : getDurumText(props.durum);

            // Renk Belirleme
            badge.className = 'badge rounded-pill px-3 py-2 ';
            if (props.has_talep) {
                badge.classList.add('bg-warning-subtle', 'text-warning');
            } else {
                switch (props.durum) {
                    case 'mazeret_bildirildi': badge.classList.add('bg-danger-subtle', 'text-danger'); break;
                    case 'devir_alindi': badge.classList.add('bg-success-subtle', 'text-success'); break;
                    default: 
                        if (props.yonetici_onayi == 0) {
                            badge.classList.add('bg-secondary-subtle', 'text-muted');
                            badge.textContent = 'Amir Onayı Bekliyor';
                        } else {
                            badge.classList.add('bg-primary-subtle', 'text-primary');
                        }
                }
            }

            detailModal.show();
        }

        function openFormModal(nobetId = null, date = null) {
            currentNobetId = nobetId;
            const form = document.getElementById('nobet-form');
            form.reset();

            document.getElementById('form-nobet-id').value = nobetId || '';

            if (date) {
                flatpickr("#form-tarih", {
                    locale: "tr",
                    dateFormat: "d.m.Y",
                    defaultDate: date
                });
            } else {
                flatpickr("#form-tarih", {
                    locale: "tr",
                    dateFormat: "d.m.Y"
                });
            }

            if (nobetId) {
                document.getElementById('form-modal-title').textContent = 'Nöbet Düzenle';
                document.getElementById('form-modal-icon').className = 'bx bx-edit';
            } else {
                document.getElementById('form-modal-title').textContent = 'Nöbet Ekle';
                document.getElementById('form-modal-icon').className = 'bx bx-plus-circle';
            }

            formModal.show();
        }

        // ============================================
        // API FONKSİYONLARI
        // ============================================
        function saveDroppedNobet(info) {
            const formData = new URLSearchParams({
                action: 'drop-personel',
                personel_id: info.event.extendedProps.personel_id,
                nobet_tarihi: info.event.startStr
            });

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Önce sürüklenen geçici eventi sil
                        info.event.remove();
                        // Sonra veritabanından temiz veriyi çek
                        calendar.refetchEvents();
                        showToast('success', data.message);
                    } else {
                        info.revert();
                        showToast('error', data.message);
                    }
                })
                .catch(error => {
                    info.revert();
                    showToast('error', 'Bir hata oluştu');
                });
        }


        function moveNobet(nobetId, newDate) {
            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'move-nobet',
                    nobet_id: nobetId,
                    yeni_tarih: newDate
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('success', data.message);
                    } else {
                        showToast('error', data.message);
                        calendar.refetchEvents();
                    }
                });
        }

        // Form gönderimi
        document.getElementById('nobet-form').addEventListener('submit', function (e) {
            e.preventDefault();

            const formData = new FormData(this);
            const nobetId = formData.get('nobet_id');

            formData.append('action', nobetId ? 'update-nobet' : 'add-nobet');

            fetch('views/nobet/api.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        showToast('success', data.message);
                        formModal.hide();
                        calendar.refetchEvents();
                    } else {
                        showToast('error', data.message);
                    }
                });
        });

        // Nöbet silme
        const btnDeleteNobet = document.getElementById('btn-delete-nobet');
        if (btnDeleteNobet) {
            btnDeleteNobet.addEventListener('click', function () {
                if (!currentNobetId) return;

                Swal.fire({
                    title: 'Emin misiniz?',
                    text: 'Bu nöbet kaydı silinecek!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Evet, sil!',
                    cancelButtonText: 'İptal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('views/nobet/api.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({
                                action: 'delete-nobet',
                                nobet_id: currentNobetId
                            })
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    showToast('success', data.message);
                                    detailModal.hide();
                                    calendar.refetchEvents();
                                } else {
                                    showToast('error', data.message);
                                }
                            });
                    }
                });
            });
        }

        // Düzenleme butonu
        const btnEditNobet = document.getElementById('btn-edit-nobet');
        if (btnEditNobet) {
            btnEditNobet.addEventListener('click', function () {
                detailModal.hide();

                // Nöbet bilgilerini al ve formu doldur
                fetch('views/nobet/api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'get-nobet-detay',
                        nobet_id: currentNobetId
                    })
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            const nobet = data.data;
                            document.getElementById('form-nobet-id').value = nobet.id;
                            document.getElementById('form-personel').value = nobet.personel_id;
                            document.getElementById('form-tip').value = nobet.nobet_tipi;
                            document.getElementById('form-baslangic').value = nobet.baslangic_saati;
                            document.getElementById('form-bitis').value = nobet.bitis_saati;
                            document.getElementById('form-aciklama').value = nobet.aciklama || '';

                            flatpickr("#form-tarih", {
                                locale: "tr",
                                dateFormat: "d.m.Y",
                                defaultDate: nobet.nobet_tarihi
                            });

                            document.querySelector('#nobetFormModal .modal-title').innerHTML = '<i class="bx bx-edit me-2"></i>Nöbet Düzenle';
                            formModal.show();
                        }
                    });
            });
        }

        // ============================================
        // YARDIMCI FONKSİYONLAR
        // ============================================
        // ============================================
        // BİLDİRİM YÖNETİMİ
        // ============================================

        // Bildirim Türü Değişimi
        $(document).on('change', 'input[name="bildirim_turu"]', function () {
            const val = $(this).val();
            $('.bildirim-area').hide();
            $('#area-' + val).show();

            // İstatistikleri ve personelleri tekrar yükle (özellikle bekleme/aylım ayrımı için)
            loadBildirimStats();
        });

        // Label tıklamalarını da yakalayalım (bazı durumlarda change geç tetiklenebilir)
        $(document).on('click', '#nobetBildirimModal label.btn', function () {
            const targetId = $(this).attr('for');
            if (targetId && targetId.startsWith('turu_')) {
                setTimeout(() => {
                    const val = $('#' + targetId).val();
                    $('.bildirim-area').hide();
                    $('#area-' + val).show();
                }, 50);
            }
        });

        // Modal olaylarını tek bir yerde toplayalım
        $('#nobetBildirimModal').on('shown.bs.modal', function () {
            // Select2 ve Flatpickr başlat
            $('.select2-modal').select2({
                dropdownParent: $('#nobetBildirimModal'),
                width: '100%'
            });

            flatpickr(".flatpickr-modal", {
                locale: "tr",
                dateFormat: "Y-m-d"
            });

            // Doğru alanı göster (Açılışta)
            const val = $('input[name="bildirim_turu"]:checked').val() || 'bekleyen';
            $('.bildirim-area').hide();
            $('#area-' + val).show();

            // İstatistikleri yükle
            loadBildirimStats();
        });

        // Bildirim istatistiklerini yükle
        function loadBildirimStats(ay = null) {
            const turu = $('input[name="bildirim_turu"]:checked').val() || 'bekleyen';
            
            // Eğer ay verilmemişse UI'dan o an seçili olanı alalım
            if (!ay) {
                if (turu === 'bekleyen') ay = $('select[name="bekleyen_ay"]').val();
                else if (turu === 'aylik') ay = $('select[name="bildirim_ayi"]').val();
            }

            const currentDate = calendar ? calendar.getDate() : new Date();
            const monthYear = ay || `${currentDate.getFullYear()}-${currentDate.getMonth() + 1}`;

            $.ajax({
                url: 'views/nobet/api.php',
                type: 'POST',
                data: {
                    action: 'get-bildirim-stats',
                    ay: monthYear,
                    type: turu // type'ı da gönderelim ki api listeyi ona göre (bekleyen/tümü) dönsün
                },
                dataType: 'json',
                success: function (data) {
                    if (data.success) {
                        $('#bildirim-stat-total').text(data.total || 0);
                        $('#bildirim-stat-sent').text(data.sent || 0);
                        $('#bildirim-stat-pending').text(data.pending || 0);
                        $('#bekleyen-count').text(data.pending || 0);
                        
                        // Bekleyen/Aylık Personel Listesini Doldur
                        const turu = $('input[name="bildirim_turu"]:checked').val();
                        const isBekleyen = turu === 'bekleyen';
                        const isAylik = turu === 'aylik';
                        
                        const listContainer = $('#bildirim-pending-list-container');
                        const listBody = $('#bildirim-pending-list');
                        
                        if ((isBekleyen || isAylik) && data.pending_list && data.pending_list.length > 0) {
                            listContainer.show();
                            $('#pending-list-count').text(data.pending_list.length + ' Kişi');
                            
                            let listHtml = '';
                            data.pending_list.forEach(p => {
                                const pushBadge = p.has_push 
                                    ? '<span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25" style="font-size:10px;"><i class="bx bxs-check-circle me-1"></i>Push Aktif</span>' 
                                    : '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25" style="font-size:10px;"><i class="bx bxs-x-circle me-1"></i>Push Yok</span>';
                                
                                listHtml += `
                                    <div class="d-flex align-items-center justify-content-between p-2 rounded-3 border-bottom border-light hover-bg-white transition-all">
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-xs bg-white rounded-circle border d-flex align-items-center justify-content-center text-primary" style="width:28px; height:28px;">
                                                <i class="bx bx-user" style="font-size:14px;"></i>
                                            </div>
                                            <span class="fw-medium small">${p.name}</span>
                                        </div>
                                        ${pushBadge}
                                    </div>
                                `;
                            });
                            listBody.html(listHtml);
                        } else {
                            listContainer.hide();
                            listBody.empty();
                        }
                    }
                },
                error: function (err) {
                    console.error('Bildirim stats hatası:', err);
                }
            });
        }

        // Ay seçimi değiştiğinde istatistikleri güncelle
        $(document).on('change', '.bildirim-ay-select', function () {
            loadBildirimStats($(this).val());
        });

        // Bildirim Gönderimi (jQuery ile)
        $(document).on('submit', '#nobet-bildirim-form', function (e) {
            e.preventDefault();
            const $form = $(this);
            const $btn = $form.find('button[type="submit"]');
            const originalHtml = $btn.html();

            $btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Gönderiliyor...');

            $.ajax({
                url: 'views/nobet/api.php',
                type: 'POST',
                data: $form.serialize() + '&action=send-bulk-notifications',
                dataType: 'json',
                success: function (data) {
                    if (data.status === 'success') {
                        // showToast yerine daha detaylı bir sonuç gösterelim
                        let detailsHtml = '<div class="text-start mt-3" style="max-height: 250px; overflow-y: auto;">';
                        if (data.details && data.details.length > 0) {
                            data.details.forEach(res => {
                                const icon = res.success ? 'bx-check-circle text-success' : 'bx-x-circle text-danger';
                                detailsHtml += `
                                    <div class="d-flex align-items-start gap-2 mb-2 p-2 border-bottom border-light">
                                        <i class="bx ${icon} fs-5 mt-1"></i>
                                        <div>
                                            <div class="fw-bold small">${res.name}</div>
                                            <div class="text-muted" style="font-size: 11px;">${res.reason}</div>
                                        </div>
                                    </div>
                                `;
                            });
                        }
                        detailsHtml += '</div>';

                        Swal.fire({
                            title: 'Bildirim Sonuçları',
                            html: `<div class="mb-2">${data.message}</div>${detailsHtml}`,
                            icon: 'info',
                            confirmButtonText: 'Kapat',
                            customClass: {
                                confirmButton: 'btn btn-primary btn-sm px-4'
                            },
                            buttonsStyling: false
                        });

                        $('#nobetBildirimModal').modal('hide');
                        $form[0].reset();
                        // Varsayılan alana geri dön
                        $('#turu_bekleyen').prop('checked', true);
                        $('.bildirim-area').hide();
                        $('#area-bekleyen').show();
                        // Takvimi yenile
                        if (calendar) calendar.refetchEvents();
                    } else {
                        showToast('error', data.message);
                    }
                },
                error: function (err) {
                    console.error('Bildirim hatası:', err);
                    showToast('error', 'Bildirim gönderilirken bir hata oluştu');
                },
                complete: function () {
                    $btn.prop('disabled', false).html(originalHtml);
                }
            });
        });

        // ============================================
        // NÖBET SİLME
        // ============================================
        function deleteNobet(id) {
            Swal.fire({
                title: 'Nöbeti Sil?',
                text: "Bu nöbet kaydı tamamen silinecektir!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, Sil',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'delete-nobet',
                            nobet_id: id
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                showToast('success', 'Nöbet silindi');
                                if (calendar) calendar.refetchEvents();
                            } else {
                                showToast('error', data.message);
                            }
                        })
                        .catch(error => {
                            console.error('Silme hatası:', error);
                            showToast('error', 'Silme işlemi sırasında bir hata oluştu');
                        });
                }
            });
        }

        function updateStats(allEvents, filteredEvents) {
            const today = new Date().toISOString().split('T')[0];
            const viewStart = calendar.view.activeStart;
            const viewEnd = calendar.view.activeEnd;

            // Görünür aralıkta olan ve iptal edilmemiş nöbetleri filtrele
            const isVisible = (event) => {
                const eventDate = new Date(event.start);
                // durum === 'iptal' olanları sayma (istatistiklerde ve listede görünmesin)
                return eventDate >= viewStart && eventDate < viewEnd && event.extendedProps.durum !== 'iptal';
            };

            const visibleAllEvents = allEvents.filter(isVisible);
            const visibleFilteredEvents = filteredEvents.filter(isVisible);

            // 1. İstatistik Kartlarını Güncelle (Sadece filtrelenmiş veriye göre)
            let totalCount = visibleFilteredEvents.length;
            let weekendCount = 0;
            let todayNames = [];

            visibleFilteredEvents.forEach(event => {
                const eventDate = new Date(event.start);
                const dayOfWeek = eventDate.getDay();

                if (dayOfWeek === 0) {
                    weekendCount++;
                }

                if (event.start.startsWith(today)) {
                    todayNames.push(event.title);
                }
            });

            const todayText = todayNames.length > 0
                ? (todayNames.length > 1 ? todayNames.length + ' Personel' : todayNames[0])
                : '-';

            document.getElementById('stat-total').textContent = totalCount;
            document.getElementById('stat-today').textContent = todayText;
            document.getElementById('stat-weekend').textContent = weekendCount;

            // 2. Personel Havuzu (Sol Liste) ve Filtre Listesi Verilerini Hazırla
            const allCounts = {};
            const monthPersonels = new Map();

            visibleAllEvents.forEach(event => {
                if (event.extendedProps && event.extendedProps.raw_personel_id) {
                    const pIdStr = event.extendedProps.raw_personel_id;
                    allCounts[pIdStr] = (allCounts[pIdStr] || 0) + 1;

                    if (!monthPersonels.has(pIdStr)) {
                        monthPersonels.set(pIdStr, {
                            name: event.title,
                            image: event.extendedProps.resim || 'assets/images/users/user-dummy-img.jpg'
                        });
                    }
                }
            });

            // Sol listedeki badge'leri güncelle
            document.querySelectorAll('.personel-item').forEach(item => {
                const pId = item.dataset.rawId;
                const count = allCounts[pId] || 0;
                const badge = item.querySelector('.nobet-count');
                if (badge) {
                    badge.textContent = count;
                    if (count > 0) {
                        badge.classList.add('bg-primary');
                        badge.classList.remove('bg-light');
                    } else {
                        badge.classList.remove('bg-primary');
                        badge.classList.add('bg-light');
                    }
                }
            });

            // Filtre dropdown listesini güncelle
            updatePersonelFilterList(monthPersonels);

            // Eğer nöbet sayısına göre sıralama seçiliyse listeyi tekrar sırala
            if (currentSort === 'count_low' || currentSort === 'count_high') {
                sortPersonel();
            }
        }

        function updatePersonelFilterList(personels) {
            const list = document.getElementById('personel-filter-list');
            if (!list) return;

            const allItem = `<li><a class="dropdown-item d-flex align-items-center gap-2 ${selectedFilterPersonelId === 'all' ? 'active' : ''}" href="javascript:void(0)" data-personel-id="all">
                <div class="avatar-xs d-flex align-items-center justify-content-center bg-soft-primary text-primary rounded-circle" style="width:24px; height:24px; font-size:10px;">
                    <i class="bx bx-group"></i>
                </div>
                <span>Tüm Personeller</span>
            </a></li>`;

            const divider = `<li><hr class="dropdown-divider"></li>`;
            let itemsHtml = allItem + divider;

            const sorted = Array.from(personels.entries()).sort((a, b) => a[1].name.localeCompare(b[1].name, 'tr'));

            sorted.forEach(([id, data]) => {
                itemsHtml += `<li><a class="dropdown-item d-flex align-items-center gap-2 ${selectedFilterPersonelId == id ? 'active' : ''}" href="javascript:void(0)" data-personel-id="${id}">
                    <img src="${data.image}" class="rounded-circle" width="24" height="24" style="object-fit:cover;">
                    <span>${data.name}</span>
                </a></li>`;
            });

            list.innerHTML = itemsHtml;

            list.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function () {
                    selectedFilterPersonelId = this.dataset.personelId;
                    const btn = document.getElementById('personel-filter-btn');

                    if (selectedFilterPersonelId === 'all') {
                        btn.innerHTML = '<i class="bx bx-filter fs-4"></i>';
                        btn.title = 'Personel Filtrele';
                        btn.style.background = 'rgba(0, 184, 217, 0.1)';
                        btn.style.color = '#00b8d9';
                    } else {
                        const name = this.querySelector('span').textContent;
                        btn.innerHTML = '<i class="bx bx-user-check fs-4"></i>';
                        btn.title = 'Filtre: ' + name;
                        btn.style.background = '#00b8d9';
                        btn.style.color = '#fff';
                    }
                    calendar.refetchEvents();
                });
            });
        }

        function formatDate(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleDateString('tr-TR', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }

        function getDurumBadge(durum) {
            const badges = {
                'planli': '<span class="badge bg-primary">Planlı</span>',
                'devir_alindi': '<span class="badge bg-success">Devir Alındı</span>',
                'tamamlandi': '<span class="badge bg-info">Tamamlandı</span>',
                'iptal': '<span class="badge bg-danger">İptal</span>'
            };
            return badges[durum] || '<span class="badge bg-secondary">Bilinmiyor</span>';
        }

        function getDurumText(durum) {
            const texts = {
                'planli': 'Planlı',
                'devir_alindi': 'Devir Alındı',
                'tamamlandi': 'Tamamlandı',
                'iptal': 'İptal',
                'mazeret_bildirildi': 'Mazeret Bildirildi',
                'talep_edildi': 'Nöbet Talebi (Onay Bekliyor)',
                'onaylandi': 'Onaylandı'
            };
            return texts[durum] || 'Bilinmiyor';
        }

        function getNobetTipiText(tip) {
            const tipler = {
                'standart': 'Standart',
                'hafta_sonu': 'Hafta Sonu',
                'resmi_tatil': 'Resmi Tatil',
                'ozel': 'Özel'
            };
            return tipler[tip] || 'Standart';
        }

        function showToast(type, message) {
            Toastify({
                text: message,
                duration: 3000,
                gravity: "top",
                position: "center",
                style: {
                    background: "#000000",
                    borderRadius: "8px",
                    boxShadow: "0 4px 12px rgba(0,0,0,0.15)"
                },
                stopOnFocus: true
            }).showToast();
        }

        // ============================================
        // TALEPLER VE MAZERETLER YÖNETİMİ
        // ============================================
        function loadTaleplerVeMazeretler() {
            loadDegisimTalepleri();
            loadMazeretBildirimleri();
        }

        function loadDegisimTalepleri() {
            const tbody = document.getElementById('degisim-tbody');
            if (!tbody) return; // Element yoksa çık

            tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
                <i class="bx bx-loader-alt bx-spin bx-lg"></i>
                <p class="mb-0 mt-2">Yükleniyor...</p>
            </td></tr>`;

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get-degisim-talepleri' })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '';
                        let bekleyenCount = 0;

                        data.data.forEach(talep => {
                            if (talep.durum === 'personel_onayladi') bekleyenCount++;

                            const durumBadge = getDegisimDurumBadge(talep.durum);
                            const islemButtons = talep.durum === 'personel_onayladi' ? `
                            <button class="btn btn-sm btn-success me-1" onclick="onaylaDegisimTalebi(${talep.id})" title="Onayla">
                                <i class="bx bx-check"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="reddetDegisimTalebi(${talep.id})" title="Reddet">
                                <i class="bx bx-x"></i>
                            </button>
                        ` : '<span class="text-muted">-</span>';

                            html += `
                            <tr>
                                <td><strong>${talep.talep_eden_adi}</strong></td>
                                <td>${talep.talep_edilen_adi}</td>
                                <td>${formatDateShort(talep.nobet_tarihi)}</td>
                                <td>${talep.aciklama || '-'}</td>
                                <td>${durumBadge}</td>
                                <td>${formatDateShort(talep.talep_tarihi)}</td>
                                <td class="text-center">${islemButtons}</td>
                            </tr>
                        `;
                        });

                        tbody.innerHTML = html;
                        const degisimBadge = document.getElementById('degisim-badge');
                        if (degisimBadge) degisimBadge.textContent = bekleyenCount;
                        const statPending = document.getElementById('stat-pending');
                        if (statPending) statPending.textContent = bekleyenCount;
                    } else {
                        tbody.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
                        <i class="bx bx-check-circle bx-lg text-success"></i>
                        <p class="mb-0 mt-2">Bekleyen değişim talebi yok</p>
                    </td></tr>`;
                        const degisimBadge2 = document.getElementById('degisim-badge');
                        if (degisimBadge2) degisimBadge2.textContent = '0';
                        const statPending2 = document.getElementById('stat-pending');
                        if (statPending2) statPending2.textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('Değişim talepleri yüklenemedi:', error);
                    tbody.innerHTML = `<tr><td colspan="7" class="text-center text-danger py-4">
                    <i class="bx bx-error bx-lg"></i>
                    <p class="mb-0 mt-2">Yüklenirken hata oluştu</p>
                </td></tr>`;
                });
        }

        function loadMazeretBildirimleri() {
            const tbody = document.getElementById('mazeret-tbody');
            if (!tbody) return; // Element yoksa çık

            tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">
                <i class="bx bx-loader-alt bx-spin bx-lg"></i>
                <p class="mb-0 mt-2">Yükleniyor...</p>
            </td></tr>`;

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'get-mazeret-bildirimleri' })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.length > 0) {
                        let html = '';

                        data.data.forEach(mazeret => {
                            html += `
                            <tr>
                                <td><strong>${mazeret.personel_adi}</strong></td>
                                <td>${formatDateShort(mazeret.nobet_tarihi)}</td>
                                <td>${mazeret.mazeret_aciklama || '-'}</td>
                                <td>${formatDateShort(mazeret.mazeret_tarihi)}</td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-primary me-1" onclick="mazeretNobetDevret(${mazeret.id})" title="Başka Personele Ata">
                                        <i class="bx bx-user-plus"></i> Devret
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="mazeretIptalEt(${mazeret.id})" title="İptal Et">
                                        <i class="bx bx-x"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        });

                        tbody.innerHTML = html;
                        const mazeretBadge = document.getElementById('mazeret-badge');
                        if (mazeretBadge) mazeretBadge.textContent = data.data.length;
                    } else {
                        tbody.innerHTML = `<tr><td colspan="5" class="text-center text-muted py-4">
                        <i class="bx bx-check-circle bx-lg text-success"></i>
                        <p class="mb-0 mt-2">Mazeret bildirimi yok</p>
                    </td></tr>`;
                        const mazeretBadge2 = document.getElementById('mazeret-badge');
                        if (mazeretBadge2) mazeretBadge2.textContent = '0';
                    }
                })
                .catch(error => {
                    console.error('Mazeret bildirimleri yüklenemedi:', error);
                    tbody.innerHTML = `<tr><td colspan="5" class="text-center text-danger py-4">
                    <i class="bx bx-error bx-lg"></i>
                    <p class="mb-0 mt-2">Yüklenirken hata oluştu</p>
                </td></tr>`;
                });
        }

        function getDegisimDurumBadge(durum) {
            const badges = {
                'beklemede': '<span class="badge bg-secondary">Personel Onayı Bekleniyor</span>',
                'personel_onayladi': '<span class="badge bg-warning">Yönetici Onayı Bekleniyor</span>',
                'onaylandi': '<span class="badge bg-success">Onaylandı</span>',
                'reddedildi': '<span class="badge bg-danger">Reddedildi</span>',
                'iptal': '<span class="badge bg-dark">İptal</span>'
            };
            return badges[durum] || '<span class="badge bg-secondary">Bilinmiyor</span>';
        }

        function formatDateShort(dateStr) {
            if (!dateStr) return '-';
            const date = new Date(dateStr);
            return date.toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        }

        function onaylaDegisimTalebi(talepId) {
            Swal.fire({
                title: 'Değişim Talebini Onayla',
                text: 'Bu değişim talebini onaylamak istediğinize emin misiniz? Nöbet ataması değiştirilecektir.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, Onayla',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ action: 'onayla-degisim-talebi', talep_id: talepId })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('success', 'Değişim talebi onaylandı');
                                loadTaleplerVeMazeretler();
                                calendar.refetchEvents();
                            } else {
                                showToast('error', data.message || 'Bir hata oluştu');
                            }
                        });
                }
            });
        }

        function reddetDegisimTalebi(talepId) {
            Swal.fire({
                title: 'Değişim Talebini Reddet',
                input: 'textarea',
                inputLabel: 'Red Sebebi (Opsiyonel)',
                inputPlaceholder: 'Neden reddedildiğini açıklayabilirsiniz...',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Reddet',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('views/nobet/api.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'reddet-degisim-talebi',
                            talep_id: talepId,
                            red_nedeni: result.value || ''
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                showToast('success', 'Değişim talebi reddedildi');
                                loadTaleplerVeMazeretler();
                            } else {
                                showToast('error', data.message || 'Bir hata oluştu');
                            }
                        });
                }
            });
        }

        function mazeretNobetDevret(nobetId) {
            openFormModal(nobetId);
        }

        function mazeretIptalEt(nobetId) {
            Swal.fire({
                title: 'Nöbeti İptal Et',
                text: 'Bu nöbeti tamamen iptal etmek istediğinize emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Evet, İptal Et',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) {
                    deleteNobet(nobetId);
                    loadMazeretBildirimleri();
                }
            });
        }

        // Context Menu Handler
        document.addEventListener('click', function (e) {
            const contextMenu = document.getElementById('custom-context-menu');
            if (contextMenu) contextMenu.style.display = 'none';
        });

        document.querySelectorAll('.menu-item-tip').forEach(item => {
            item.addEventListener('click', function () {
                const contextMenu = document.getElementById('custom-context-menu');
                const eventId = contextMenu.dataset.eventId;
                const tip = this.dataset.tip;

                if (eventId && tip) {
                    updateEventTipi(eventId, tip);
                }
            });
        });

        document.getElementById('menu-item-delete').addEventListener('click', function () {
            const contextMenu = document.getElementById('custom-context-menu');
            const eventId = contextMenu.dataset.eventId;
            if (eventId) {
                deleteNobet(eventId);
            }
        });

        const menuApprove = document.getElementById('menu-item-approve');
        if (menuApprove) {
            menuApprove.addEventListener('click', function() {
                const eventId = document.getElementById('custom-context-menu').dataset.eventId;
                if (!eventId) return;

                const event = calendar.getEventById(eventId);
                const props = event.extendedProps;

                if (props.has_talep && props.pending_talep_id) {
                    // Değişim talebini onayla
                    actionFetch('onayla-degisim-talebi', { talep_id: props.pending_talep_id });
                } else {
                    // Normal nöbeti onayla
                    approveNobetFromCalendar(eventId);
                }
            });
        }

        const menuUnapprove = document.getElementById('menu-item-unapprove');
        if (menuUnapprove) {
            menuUnapprove.addEventListener('click', function() {
                const eventId = document.getElementById('custom-context-menu').dataset.eventId;
                if (eventId) unapproveNobetFromCalendar(eventId);
            });
        }

        function approveNobetFromCalendar(id) {
            actionFetch('onayla-nobet', { nobet_id: id });
        }

        function unapproveNobetFromCalendar(id) {
            actionFetch('onayi-kaldir-nobet', { nobet_id: id });
        }

        function actionFetch(action, extraData) {
            const params = new URLSearchParams({ action: action, ...extraData });
            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            }).then(r => r.json()).then(data => {
                if (data.success || data.status === 'success') {
                    showToast('success', data.message);
                    calendar.refetchEvents();
                } else {
                    showToast('error', data.message || 'İşlem başarısız.');
                }
            });
        }

        function updateEventTipi(id, tip) {
            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'update-nobet-tipi',
                    nobet_id: id,
                    nobet_tipi: tip
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message);
                        calendar.refetchEvents();
                    } else {
                        showToast('error', data.message || 'Bir hata oluştu');
                    }
                })
                .catch(error => {
                    console.error('Hata:', error);
                    showToast('error', 'Sunucu ile iletişim kurulamadı');
                });
        }

        // ============================================
        // PERSONEL NÖBET İSTATİSTİKLERİ MODAL & JS
        // ============================================
        let currentIstatistikData = null;
        const istatistikModalEl = document.getElementById('nobetIstatistikModal');
        const istatistikModal = istatistikModalEl ? new bootstrap.Modal(istatistikModalEl) : null;

        // Select2 Başlatma (Modal dropdownParent ile)
        $('#nobetIstatistikModal .select2').select2({
            dropdownParent: $('#nobetIstatistikModal'),
            width: '100%'
        });

        // Dönem türü değiştiğinde form alanlarını düzenle
        $('#ist-filter-type').on('change', function() {
            const val = $(this).val();
            if (val === 'aylik') {
                $('#ist-filter-ay-wrapper').removeClass('d-none');
                $('#ist-filter-yil-wrapper').removeClass('d-none');
                $('#ist-filter-tarih-wrapper').addClass('d-none');
            } else if (val === 'yillik') {
                $('#ist-filter-ay-wrapper').addClass('d-none');
                $('#ist-filter-yil-wrapper').removeClass('d-none');
                $('#ist-filter-tarih-wrapper').addClass('d-none');
            } else if (val === 'ozel') {
                $('#ist-filter-ay-wrapper').addClass('d-none');
                $('#ist-filter-yil-wrapper').addClass('d-none');
                $('#ist-filter-tarih-wrapper').removeClass('d-none');
            }
            loadNobetIstatistikleri();
        });

        // Filtre değişimlerinde veriyi tazele
        $('#ist-filter-ay, #ist-filter-yil, #ist-filter-departman, #ist-filter-status').on('change', function() {
            loadNobetIstatistikleri();
        });

        $('#ist-filter-baslangic, #ist-filter-bitis').on('change', function() {
            loadNobetIstatistikleri();
        });

        $('#btn-ist-filtre-uygula').on('click', function() {
            loadNobetIstatistikleri();
        });

        // Arama ve Sadece Nöbeti Olanlar filtreleri (İstemci tarafında anlık)
        $(document).on('input', '#ist-table-search', function() {
            if (currentIstatistikData && currentIstatistikData.personeller) {
                renderIstatistikTable(currentIstatistikData.personeller);
            }
        });

        $('#ist-toggle-only-active').on('change', function() {
            if (currentIstatistikData && currentIstatistikData.personeller) {
                renderIstatistikTable(currentIstatistikData.personeller);
            }
        });

        // Modal açıldığında takvimdeki mevcut ayı ve yılı varsayılan seç
        if (istatistikModalEl) {
            istatistikModalEl.addEventListener('show.bs.modal', function() {
                if (calendar) {
                    const calDate = calendar.getDate();
                    const currentMonthStr = String(calDate.getMonth() + 1).padStart(2, '0');
                    const currentYearStr = String(calDate.getFullYear());

                    $('#ist-filter-ay').val(currentMonthStr).trigger('change.select2');
                    $('#ist-filter-yil').val(currentYearStr).trigger('change.select2');

                    // Özel tarih inputlarını da o ayın başı ve sonu yap
                    const baslangic = `${currentYearStr}-${currentMonthStr}-01`;
                    const lastDay = new Date(calDate.getFullYear(), calDate.getMonth() + 1, 0).getDate();
                    const bitis = `${currentYearStr}-${currentMonthStr}-${String(lastDay).padStart(2, '0')}`;

                    $('#ist-filter-baslangic').val(baslangic);
                    $('#ist-filter-bitis').val(bitis);
                }
                loadNobetIstatistikleri();
            });
        }

        function loadNobetIstatistikleri() {
            const tbody = document.getElementById('ist-personel-tbody');
            if (tbody) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted">
                    <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                    İstatistikler yükleniyor...
                </td></tr>`;
            }

            const donemTuru = $('#ist-filter-type').val() || 'aylik';
            const ay = $('#ist-filter-ay').val() || '01';
            const yil = $('#ist-filter-yil').val() || new Date().getFullYear();
            const departman = $('#ist-filter-departman').val() || 'all';
            const personelDurumu = $('#ist-filter-status').val() || 'active';
            const baslangic = $('#ist-filter-baslangic').val() || '';
            const bitis = $('#ist-filter-bitis').val() || '';

            const params = new URLSearchParams({
                action: 'get-personel-istatistikleri',
                donem_turu: donemTuru,
                ay: ay,
                yil: yil,
                departman: departman,
                personel_durumu: personelDurumu,
                baslangic: baslangic,
                bitis: bitis
            });

            fetch('views/nobet/api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(res => res.json())
            .then(res => {
                if (res.success && res.data) {
                    currentIstatistikData = res.data;
                    currentIstatistikData.donem = res.donem;

                    // Başlık güncelle
                    const baslikEl = document.getElementById('istatistik-donem-baslik');
                    if (baslikEl && res.donem) {
                        baslikEl.textContent = `${res.donem.baslangic_formatli} - ${res.donem.bitis_formatli} Dönemi Nöbet Dağılımı ve Katılım Özeti`;
                    }

                    // KPI kartları güncelle
                    const kpi = res.data.kpi;
                    if (kpi) {
                        const topNobetEl = document.getElementById('ist-kpi-toplam-nobet');
                        const tutanPersEl = document.getElementById('ist-kpi-tutan-personel');
                        const katilimOranEl = document.getElementById('ist-kpi-katilim-orani');
                        const ortalamaEl = document.getElementById('ist-kpi-ortalama');
                        const haftasonuTatilEl = document.getElementById('ist-kpi-haftasonu-tatil');
                        const haftasonuOranEl = document.getElementById('ist-kpi-haftasonu-oran');

                        if (topNobetEl) topNobetEl.textContent = kpi.toplam_nobet;
                        if (tutanPersEl) tutanPersEl.textContent = `${kpi.nobet_tutan_personel} / ${kpi.toplam_personel}`;
                        
                        const katilimYuzde = kpi.toplam_personel > 0 ? Math.round((kpi.nobet_tutan_personel / kpi.toplam_personel) * 100) : 0;
                        if (katilimOranEl) katilimOranEl.textContent = `%${katilimYuzde} Katılım Oranı`;

                        if (ortalamaEl) ortalamaEl.textContent = kpi.ortalama_nobet;
                        if (haftasonuTatilEl) haftasonuTatilEl.textContent = `${kpi.hafta_sonu_toplam} / ${kpi.resmi_tatil_toplam}`;

                        const hsYuzde = kpi.toplam_nobet > 0 ? Math.round((kpi.hafta_sonu_toplam / kpi.toplam_nobet) * 100) : 0;
                        if (haftasonuOranEl) haftasonuOranEl.textContent = `%${hsYuzde} Hafta Sonu Oranı`;
                    }

                    // Tabloyu çiz
                    renderIstatistikTable(res.data.personeller);

                    // Departman özetini çiz
                    renderDepartmanSummary(res.data.departmanlar);
                } else {
                    if (tbody) {
                        tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">${res.message || 'Veriler yüklenemedi.'}</td></tr>`;
                    }
                }
            })
            .catch(err => {
                console.error('İstatistik yükleme hatası:', err);
                if (tbody) {
                    tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-danger">Sunucu ile iletişim kurulamadı.</td></tr>`;
                }
            });
        }

        function renderIstatistikTable(personeller) {
            const tbody = document.getElementById('ist-personel-tbody');
            if (!tbody) return;

            if (!personeller || personeller.length === 0) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted">Kayıt bulunamadı.</td></tr>`;
                return;
            }

            const searchVal = (istTableSearch ? istTableSearch.value : '').toLowerCase().trim();
            const onlyActive = istToggleOnlyActive ? istToggleOnlyActive.checked : false;

            const filtered = personeller.filter(p => {
                if (onlyActive && p.toplam_nobet === 0) return false;
                if (searchVal) {
                    const name = (p.adi_soyadi || '').toLowerCase();
                    const dept = (p.departman || '').toLowerCase();
                    if (!name.includes(searchVal) && !dept.includes(searchVal)) return false;
                }
                return true;
            });

            if (filtered.length === 0) {
                tbody.innerHTML = `<tr><td colspan="10" class="text-center py-4 text-muted">Filtre kriterlerine uygun personel bulunamadı.</td></tr>`;
                return;
            }

            let html = '';
            filtered.forEach((p, idx) => {
                const rowBg = p.toplam_nobet > 0 ? '' : 'class="text-muted bg-light bg-opacity-25"';
                const avatar = p.resim || 'assets/images/users/user-dummy-img.jpg';
                const deptBadge = p.departman 
                    ? `<span class="badge rounded-pill bg-light text-dark border px-2 py-1">${p.departman}</span>`
                    : '<span class="text-muted small">-</span>';

                const progressColor = p.oran > 15 ? 'bg-danger' : (p.oran > 8 ? 'bg-warning' : 'bg-primary');

                html += `<tr ${rowBg}>
                    <td class="text-center fw-bold text-muted" style="font-size: 11px;">${idx + 1}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <img src="${avatar}" class="rounded-circle" width="30" height="30" style="object-fit: cover; border: 1px solid #e2e8f0;">
                            <div>
                                <div class="fw-bold text-dark d-flex align-items-center gap-1">
                                    <span>${p.adi_soyadi}</span>
                                    ${p.is_ayrilmis ? '<span class="badge bg-soft-danger text-danger px-1 py-0" style="font-size: 9px;">Ayrıldı</span>' : ''}
                                </div>
                                ${p.cep_telefonu ? `<div class="text-muted small" style="font-size: 11px;">${p.cep_telefonu}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td>${deptBadge}</td>
                    <td class="text-center">
                        <span class="badge rounded-pill bg-light text-secondary border px-2 py-1 fw-bold">${p.hafta_ici_nobet}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill bg-soft-info text-info px-2 py-1 fw-bold" style="background: rgba(13, 202, 240, 0.12);">${p.hafta_sonu_nobet}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill bg-soft-warning text-warning px-2 py-1 fw-bold" style="background: rgba(255, 193, 7, 0.15); color: #b45309 !important;">${p.resmi_tatil_nobet}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill ${p.toplam_nobet > 0 ? 'bg-primary' : 'bg-secondary'} px-3 py-1 fw-bold fs-7">${p.toplam_nobet}</span>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="progress flex-grow-1" style="height: 6px; background-color: #e2e8f0; border-radius: 4px;">
                                <div class="progress-bar ${progressColor}" role="progressbar" style="width: ${Math.min(p.oran * 3, 100)}%; border-radius: 4px;"></div>
                            </div>
                            <span class="small fw-semibold text-muted" style="min-width: 38px; font-size: 11px;">%${p.oran}</span>
                        </div>
                    </td>
                    <td class="text-center text-muted small" style="font-size: 12px;">${p.son_nobet_formatli}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-ist-show-calendar py-1 px-2" data-personel-id="${p.id}" data-name="${p.adi_soyadi}" title="Takvimde Filtrele">
                            <i class="bx bx-calendar-check"></i>
                        </button>
                    </td>
                </tr>`;
            });

            tbody.innerHTML = html;

            // "Takvimde Filtrele" butonları
            tbody.querySelectorAll('.btn-ist-show-calendar').forEach(btn => {
                btn.addEventListener('click', function() {
                    const pId = this.dataset.personelId;
                    const pName = this.dataset.name;

                    selectedFilterPersonelId = pId;
                    const calFilterBtn = document.getElementById('personel-filter-btn');
                    if (calFilterBtn) {
                        calFilterBtn.innerHTML = '<i class="bx bx-user-check fs-4"></i>';
                        calFilterBtn.title = 'Filtre: ' + pName;
                        calFilterBtn.style.background = '#00b8d9';
                        calFilterBtn.style.color = '#fff';
                    }

                    // Takvimi güncelle ve modalı kapat
                    if (calendar) {
                        calendar.refetchEvents();
                    }
                    if (istatistikModal) {
                        istatistikModal.hide();
                    }
                });
            });
        }

        function renderDepartmanSummary(deptStats) {
            const listEl = document.getElementById('ist-departman-list');
            if (!listEl) return;

            if (!deptStats || deptStats.length === 0) {
                listEl.innerHTML = '<div class="col-12 text-muted small">Departman verisi bulunamadı.</div>';
                return;
            }

            let html = '';
            deptStats.forEach(d => {
                html += `<div class="col-md-3 col-sm-6">
                    <div class="p-2 border rounded bg-white d-flex align-items-center justify-content-between">
                        <div>
                            <div class="fw-semibold text-truncate small" style="max-width: 140px;" title="${d.departman}">${d.departman}</div>
                            <div class="text-muted" style="font-size: 10px;">${d.nobet_tutan_sayisi}/${d.personel_sayisi} Personel</div>
                        </div>
                        <span class="badge bg-soft-primary text-primary fw-bold fs-7 px-2 py-1">${d.toplam_nobet} Nöbet</span>
                    </div>
                </div>`;
            });

            listEl.innerHTML = html;
        }

        // Excel / CSV Dışa Aktarma
        const btnExportExcel = document.getElementById('btn-export-istatistik-excel');
        if (btnExportExcel) {
            btnExportExcel.addEventListener('click', function() {
                if (!currentIstatistikData || !currentIstatistikData.personeller || currentIstatistikData.personeller.length === 0) {
                    showToast('warning', 'Dışa aktarılacak veri bulunamadı.');
                    return;
                }

                let csvContent = '\uFEFF'; // UTF-8 BOM
                csvContent += 'Sıra;Personel;Departman;Hafta İçi Nöbet;Hafta Sonu Nöbet;Resmi Tatil Nöbet;Toplam Nöbet;Nöbet Payı (%);Son Nöbet Tarihi\n';

                currentIstatistikData.personeller.forEach((p, idx) => {
                    const row = [
                        idx + 1,
                        `"${(p.adi_soyadi || '').replace(/"/g, '""')}"`,
                        `"${(p.departman || '').replace(/"/g, '""')}"`,
                        p.hafta_ici_nobet,
                        p.hafta_sonu_nobet,
                        p.resmi_tatil_nobet,
                        p.toplam_nobet,
                        p.oran,
                        p.son_nobet_formatli
                    ];
                    csvContent += row.join(';') + '\n';
                });

                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.setAttribute('href', url);
                const donemStr = currentIstatistikData.donem ? `${currentIstatistikData.donem.baslangic}_${currentIstatistikData.donem.bitis}` : 'nobet';
                link.setAttribute('download', `nobet_istatistikleri_${donemStr}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });
        }

        // Sayfa yüklendiğinde talepleri yükle
        loadTaleplerVeMazeretler();

    });
</script>