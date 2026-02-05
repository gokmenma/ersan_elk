<?php
use App\Model\PersonelModel;
use App\Model\TanimlamalarModel;

$Personel = new PersonelModel();
$Tanimlamalar = new TanimlamalarModel();

$personeller = $Personel->all();

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


<!-- Sayfa Başlığı -->
<?php
$maintitle = "Nöbet Yönetimi";
$title = 'Nöbet Planlama';
?>
<?php include 'layouts/breadcrumb.php'; ?>

<!-- İstatistik Kartları - Modern Dashboard Tasarımı -->
<div class="row mb-4 g-3">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-calendar-check mt-1"></i>
                    Bu Ay Toplam Nöbet
                </div>
                <div class="stat-trend up">
                    <i class="bx bx-trending-up"></i> +12%
                </div>
            </div>
            <div class="stat-value" id="stat-total">0</div>
            <div class="stat-sub">Aylık planlama özeti</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-user-check mt-1"></i>
                    Bugün Nöbetçi
                </div>
                <div class="stat-trend">
                    <i class="bx bx-minus"></i> Stabil
                </div>
            </div>
            <div class="stat-value" id="stat-today">-</div>
            <div class="stat-sub">Güncel görevli personel</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-transfer-alt mt-1"></i>
                    Bekleyen Talepler
                </div>
                <div class="stat-trend down">
                    <i class="bx bx-trending-down"></i> -8%
                </div>
            </div>
            <div class="stat-value" id="stat-pending">0</div>
            <div class="stat-sub">Onay bekleyen değişimler</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card">
            <div class="stat-header">
                <div class="stat-label">
                    <i class="bx bx-calendar-week mt-1"></i>
                    Hafta Sonu Nöbet
                </div>
                <div class="stat-trend up">
                    <i class="bx bx-trending-up"></i> +4%
                </div>
            </div>
            <div class="stat-value" id="stat-weekend">0</div>
            <div class="stat-sub">Tatil günü mesaileri</div>
        </div>
    </div>
</div>

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
                        <li><a class="dropdown-item active" href="javascript:void(0)" data-dept="all">Tüm
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
                    ?>
                    <div class="personel-item fc-event"
                        data-id="<?php echo \App\Helper\Security::encrypt($personel->id); ?>"
                        data-name="<?php echo htmlspecialchars($personel->adi_soyadi); ?>"
                        data-departman="<?php echo htmlspecialchars($deptName); ?>" data-color="<?php echo $rowColor; ?>"
                        style="--dept-color: <?php echo $rowColor; ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="personel-name">
                                <?php echo htmlspecialchars($personel->adi_soyadi); ?>
                            </div>
                            <span class="nobet-count" data-personel-id="<?php echo $personel->id; ?>">0</span>
                        </div>
                        <div class="personel-dept">
                            <?php echo htmlspecialchars($personel->departman ?? 'Departman Belirtilmemiş'); ?>
                            <?php if ($personel->ekip_adi): ?>
                                • <?php echo $personel->ekip_adi; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="legend-container">
                <?php foreach ($deptColorMap as $normName => $color): ?>
                    <div class="legend-item">
                        <div class="legend-color" style="background: <?php echo $color; ?>;"></div>
                        <span><?php echo htmlspecialchars($deptOriginalNames[$normName]); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Sağ Panel - Takvim -->
    <div class="calendar-panel">
        <div class="calendar-card">
            <!-- 3. Görsel Referansı (Tab Bar ve Navigasyon) -->
            <div class="calendar-header">
                <div class="view-buttons">
                    <button class="btn active" data-view="dayGridMonth">Ay</button>
                    <button class="btn" data-view="timeGridWeek">Hafta</button>
                    <button class="btn" data-view="timeGridDay">Gün</button>
                    <button class="btn" data-view="listMonth">Liste</button>
                </div>

                <div class="d-flex align-items-center gap-3">
                    <span class="calendar-title text-uppercase font-weight-bold" id="calendar-title"
                        style="position:static; transform:none; font-size:14px;">ŞUBAT 2026</span>
                    <div class="calendar-nav d-flex gap-1">
                        <button class="btn" id="calendar-prev"><i class="bx bx-chevron-left"></i></button>
                        <button class="btn" id="calendar-today"
                            style="width:auto; height:32px; padding:0 12px; font-weight:500;">Bugün</button>
                        <button class="btn" id="calendar-next"><i class="bx bx-chevron-right"></i></button>
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
            </div>
        </div>
    </div>

</div>



<!-- Nöbet Detay Modal -->
<div class="modal fade nobet-modal" id="nobetDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-info-circle"></i>
                    Nöbet Detayı
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="text-center mb-4">
                    <img id="modal-personel-img" src="" class="rounded-circle shadow-sm" width="80" height="80"
                        style="object-fit: cover; border: 3px solid #f8f9fa;">
                    <h5 class="mt-3 mb-1" id="modal-personel-name" style="font-weight: 700; color: #1a1d21;"></h5>
                    <p class="text-muted small" id="modal-personel-dept"></p>
                </div>

                <div class="row g-3">
                    <div class="col-6">
                        <div class="nobet-info-card">
                            <div class="nobet-info-label">Tarih</div>
                            <div class="nobet-info-value" id="modal-tarih"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="nobet-info-card">
                            <div class="nobet-info-label">Saat</div>
                            <div class="nobet-info-value" id="modal-saat"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="nobet-info-card">
                            <div class="nobet-info-label">Durum</div>
                            <div class="nobet-info-value" id="modal-durum"></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="nobet-info-card">
                            <div class="nobet-info-label">Nöbet Tipi</div>
                            <div class="nobet-info-value" id="modal-tip"></div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="nobet-info-card">
                            <div class="nobet-info-label">Telefon</div>
                            <div class="nobet-info-value" id="modal-telefon"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" id="btn-delete-nobet">
                    <i class="bx bx-trash"></i>Sil
                </button>
                <button type="button" class="btn btn-warning" id="btn-edit-nobet" style="color:#fff;">
                    <i class="bx bx-edit"></i>Düzenle
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bx bx-x"></i>Kapat
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Nöbet Ekleme/Düzenleme Modal -->
<div class="modal fade nobet-modal" id="nobetFormModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-plus-circle" id="form-modal-icon"></i>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x"></i>İptal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save"></i>Kaydet
                    </button>
                </div>
            </form>
        </div>
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
        const detailModal = new bootstrap.Modal(document.getElementById('nobetDetailModal'));
        const formModal = new bootstrap.Modal(document.getElementById('nobetFormModal'));

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
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            editable: true,
            droppable: true,
            selectable: true,
            dayMaxEvents: 4,
            height: 'auto',

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
                        successCallback(data);
                        updateStats(data);
                    })
                    .catch(error => {
                        console.error('Takvim yükleme hatası:', error);
                        failureCallback(error);
                    });
            },

            // Tarih seçildiğinde - Yeni nöbet ekle
            dateClick: function (info) {
                openFormModal(null, info.dateStr);
            },

            // Etkinlik tıklandığında - Detay göster
            eventClick: function (info) {
                showNobetDetail(info.event);
            },

            // Personel sürüklenip bırakıldığında
            eventReceive: function (info) {
                if (info.event.extendedProps.isNew) {
                    saveDroppedNobet(info);
                }
            },

            // Mevcut etkinlik taşındığında
            eventDrop: function (info) {
                moveNobet(info.event.id, info.event.startStr);
            },

            // Etkinlik yeniden boyutlandırıldığında
            eventResize: function (info) {
                // Nöbetler tek günlük olduğu için gerek yok ama ileride genişletilebilir
            }
        });

        calendar.render();

        // ============================================
        // TAKVİM BAŞLIK GÜNCELLEME
        // ============================================
        function updateCalendarTitle() {
            const view = calendar.view;
            const date = view.currentStart;
            const months = ['OCAK', 'ŞUBAT', 'MART', 'NİSAN', 'MAYIS', 'HAZİRAN',
                'TEMMUZ', 'AĞUSTOS', 'EYLÜL', 'EKİM', 'KASIM', 'ARALIK'];
            const titleEl = document.getElementById('calendar-title');
            if (titleEl) {
                titleEl.textContent = months[date.getMonth()] + ' ' + date.getFullYear();
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
        let selectedDept = 'all';

        // Departman Dropdown Takibi
        const deptDropdownItems = document.querySelectorAll('#dept-filter-dropdown .dropdown-item');
        deptDropdownItems.forEach(item => {
            item.addEventListener('click', function () {
                // UI Güncelle
                deptDropdownItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');

                selectedDept = this.dataset.dept;

                // Buton Rengini Değiştir
                const filterBtn = document.getElementById('dept-filter-btn');
                if (selectedDept === 'all') {
                    filterBtn.classList.replace('btn-primary', 'btn-light');
                } else {
                    filterBtn.classList.replace('btn-light', 'btn-primary');
                }

                filterPersonel();
            });
        });

        function filterPersonel() {
            const searchTerm = searchInput.value.toLowerCase();

            document.querySelectorAll('.personel-item').forEach(item => {
                const name = item.dataset.name.toLowerCase();
                const itemDept = item.dataset.departman; // Orijinal departman adı
                const itemDeptLower = itemDept.toLowerCase();

                // Hem arama terimine hem de seçili departmana bak
                const matchSearch = name.includes(searchTerm) || itemDeptLower.includes(searchTerm);
                const matchDept = selectedDept === 'all' || itemDept === selectedDept;

                if (matchSearch && matchDept) {
                    item.style.setProperty('display', 'flex', 'important');
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });
        }

        searchInput.addEventListener('input', filterPersonel);

        // ============================================
        // MODAL FONKSİYONLARI
        // ============================================
        function showNobetDetail(event) {
            currentNobetId = event.id;
            const props = event.extendedProps;

            document.getElementById('modal-personel-img').src = props.resim || 'assets/images/users/user-dummy-img.jpg';
            document.getElementById('modal-personel-name').textContent = event.title;
            document.getElementById('modal-personel-dept').textContent = props.departman || 'Departman belirtilmemiş';
            document.getElementById('modal-tarih').textContent = formatDate(event.startStr);
            document.getElementById('modal-saat').textContent = '18:00 - 08:00';
            document.getElementById('modal-durum').innerHTML = getDurumBadge(props.durum);
            document.getElementById('modal-tip').textContent = getNobetTipiText(props.nobet_tipi);
            document.getElementById('modal-telefon').textContent = props.telefon || '-';

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
        document.getElementById('btn-delete-nobet').addEventListener('click', function () {
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

        // Düzenleme butonu
        document.getElementById('btn-edit-nobet').addEventListener('click', function () {
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

        // ============================================
        // YARDIMCI FONKSİYONLAR
        // ============================================
        function updateStats(events) {
            const today = new Date().toISOString().split('T')[0];
            let totalMonth = events.length;
            let weekendCount = 0;
            let todayPerson = '-';

            events.forEach(event => {
                const eventDate = new Date(event.start);
                const dayOfWeek = eventDate.getDay();

                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    weekendCount++;
                }

                if (event.start.startsWith(today)) {
                    todayPerson = event.title;
                }
            });

            document.getElementById('stat-total').textContent = totalMonth;
            document.getElementById('stat-today').textContent = todayPerson;
            document.getElementById('stat-weekend').textContent = weekendCount;
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
                position: "right",
                backgroundColor: type === 'success' ? '#34c38f' : '#f46a6a',
                stopOnFocus: true
            }).showToast();
        }

    });
</script>