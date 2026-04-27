<?php
/**
 * Mobil Nöbetler Modülü
 * Planlama, Takvim ve Onay İşlemleri
 */

use App\Model\NobetModel;
use App\Model\PersonelModel;
use App\Helper\Security;
use App\Helper\Helper;

$nobetModel = new NobetModel();
$personelModel = new PersonelModel();

// Ay ve Yıl parametreleri
$ay = isset($_GET['ay']) ? (int)$_GET['ay'] : (int)date('m');
$yil = isset($_GET['yil']) ? (int)$_GET['yil'] : (int)date('Y');
$showTab = isset($_GET['tab']) ? $_GET['tab'] : 'planlama'; // Varsayılan planlama

// İstatistikler (Bekleyenler - Seçili Aya Göre)
try {
    $db = $nobetModel->getDb();
    $stmt = $db->prepare("SELECT 
        COUNT(*) as total_bekleyen,
        SUM(CASE WHEN nobet_tipi = 'hafta_sonu' THEN 1 ELSE 0 END) as hafta_sonu_bekleyen,
        SUM(CASE WHEN nobet_tipi = 'resmi_tatil' THEN 1 ELSE 0 END) as resmi_tatil_bekleyen
    FROM nobetler 
    WHERE (yonetici_onayi = 0 OR yonetici_onayi IS NULL) 
    AND silinme_tarihi IS NULL 
    AND (durum IS NULL OR durum NOT IN ('reddedildi', 'iptal'))
    AND MONTH(nobet_tarihi) = ? AND YEAR(nobet_tarihi) = ?
    AND firma_id = ?");
    $stmt->execute([$ay, $yil, $_SESSION['firma_id']]);
    $stats = $stmt->fetch(PDO::FETCH_OBJ);
} catch (\Exception $e) {
    $stats = (object)['total_bekleyen' => 0, 'hafta_sonu_bekleyen' => 0, 'resmi_tatil_bekleyen' => 0];
}

// Personel Listesi ve Nöbet Dağılımı (Planlama için)
$personeller = $personelModel->all(true);
$aylikDagilim = $nobetModel->getAylikNobetDagilimi($yil, $ay);

// Bekleyenleri Getir (Seçili Aya Göre)
try {
    $stmt = $db->prepare("SELECT n.*, p.adi_soyadi as personel_adi, p.departman as departman, p.resim_yolu as personel_resim
        FROM nobetler n
        LEFT JOIN personel p ON n.personel_id = p.id
        WHERE (n.yonetici_onayi = 0 OR n.yonetici_onayi IS NULL)
        AND n.silinme_tarihi IS NULL
        AND (n.durum IS NULL OR n.durum NOT IN ('reddedildi', 'iptal'))
        AND MONTH(n.nobet_tarihi) = ? AND YEAR(n.nobet_tarihi) = ?
        AND n.firma_id = ?
        ORDER BY n.nobet_tarihi ASC, n.baslangic_saati ASC");
    $stmt->execute([$ay, $yil, $_SESSION['firma_id']]);
    $bekleyenler = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (\Exception $e) {
    $bekleyenler = [];
}

function getInitial($name) {
    return mb_strtoupper(mb_substr($name, 0, 1));
}
?>

<!-- Gradient Başlık -->
<header class="bg-gradient-to-br from-rose-600 to-rose-400 text-white px-4 pt-6 pb-14 rounded-b-3xl relative overflow-hidden shadow-lg">
    <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
        <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
    </div>
    <div class="relative z-10 flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-extrabold leading-tight tracking-tight">Nöbetler</h2>
            <p class="text-white/80 text-sm mt-1 font-medium">Nöbet planlama ve onay</p>
        </div>
        <button onclick="openAddNobetModal()" class="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-md flex items-center justify-center text-white active:scale-95 transition-transform border border-white/10 shadow-lg">
            <span class="material-symbols-outlined text-[28px]">add</span>
        </button>
    </div>
</header>

<div class="px-4 mt-[-36px] relative z-10 space-y-5 pb-6">
    <!-- Tab Navigation -->
    <div class="flex gap-1.5 p-1 bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 overflow-x-auto no-scrollbar">
        <button onclick="switchNobetTab('planlama')" id="btn-tab-planlama" class="flex-1 min-w-[100px] py-3 px-2 rounded-xl text-[10px] font-black flex flex-col items-center justify-center gap-1 transition-all <?= $showTab === 'planlama' ? 'bg-rose-500 text-white shadow-lg shadow-rose-500/25' : 'text-slate-400' ?>">
            <span class="material-symbols-outlined text-[20px]">calendar_month</span>
            PLANLAMA
        </button>
        <button onclick="switchNobetTab('bekleyen')" id="btn-tab-bekleyen" class="flex-1 min-w-[100px] py-3 px-2 rounded-xl text-[10px] font-black flex flex-col items-center justify-center gap-1 transition-all <?= $showTab === 'bekleyen' ? 'bg-rose-500 text-white shadow-lg shadow-rose-500/25' : 'text-slate-400' ?>">
            <span class="material-symbols-outlined text-[20px]">pending_actions</span>
            ONAY (<?= $stats->total_bekleyen ?>)
        </button>
        <button onclick="switchNobetTab('havuz')" id="btn-tab-havuz" class="flex-1 min-w-[100px] py-3 px-2 rounded-xl text-[10px] font-black flex flex-col items-center justify-center gap-1 transition-all <?= $showTab === 'havuz' ? 'bg-rose-500 text-white shadow-lg shadow-rose-500/25' : 'text-slate-400' ?>">
            <span class="material-symbols-outlined text-[20px]">groups</span>
            HAVUZ
        </button>
    </div>

    <!-- PLANLAMA TAB (Takvim) -->
    <div id="tab-planlama" class="tab-content <?= $showTab === 'planlama' ? '' : 'hidden' ?> space-y-4">
        <div class="bg-white dark:bg-card-dark rounded-3xl p-3 shadow-sm border border-slate-100 dark:border-slate-800">
            <!-- Ay Seçici Header -->
            <div class="flex items-center justify-between mb-4 px-1">
                <button onclick="calendar.prev()" class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-400 text-lg">chevron_left</span>
                </button>
                <div class="text-center">
                    <h4 id="calendar-month-title" class="text-sm font-black text-slate-800 dark:text-white uppercase tracking-tight">
                        <?= Helper::ayIsmi($ay) . ' ' . $yil ?>
                    </h4>
                </div>
                <button onclick="calendar.next()" class="w-8 h-8 rounded-lg bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-400 text-lg">chevron_right</span>
                </button>
            </div>

            <!-- Takvim Konteyner -->
            <div id="mobile-nobet-calendar" class="min-h-[300px]"></div>
        </div>

        <!-- Seçili Gün Detayları -->
        <div id="selected-day-container" class="space-y-3">
            <div class="flex items-center gap-3 px-1">
                <div class="w-1.5 h-1.5 rounded-full bg-rose-500"></div>
                <h3 id="selected-day-label" class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest">GÜN SEÇİNİZ</h3>
            </div>
            <div id="selected-day-list" class="space-y-2">
                <div class="bg-white dark:bg-card-dark rounded-2xl p-6 text-center border border-dashed border-slate-200 dark:border-slate-800">
                    <p class="text-xs text-slate-400 font-medium">Takvimden bir güne dokunarak nöbetleri görebilir veya yeni nöbet ekleyebilirsiniz.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- ONAY BEKLEYENLER TAB -->
    <div id="tab-bekleyen" class="tab-content <?= $showTab === 'bekleyen' ? '' : 'hidden' ?> space-y-4">
        <!-- Ay Seçici (Onay Sekmesi İçin) -->
        <div class="bg-white dark:bg-card-dark rounded-2xl p-3 shadow-sm border border-slate-100 dark:border-slate-800 flex items-center justify-between">
            <button onclick="changeNobetMonth(-1)" class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-400">chevron_left</span>
            </button>
            <div class="text-center">
                <h4 class="text-xs font-black text-slate-800 dark:text-white uppercase tracking-widest">
                    <?= Helper::ayIsmi($ay) . ' ' . $yil ?>
                </h4>
            </div>
            <button onclick="changeNobetMonth(1)" class="w-10 h-10 rounded-xl bg-slate-50 dark:bg-slate-800 flex items-center justify-center">
                <span class="material-symbols-outlined text-slate-400">chevron_right</span>
            </button>
        </div>

        <!-- İstatistik Özetleri -->
        <div class="grid grid-cols-2 gap-3">
            <div class="bg-white dark:bg-card-dark p-3 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center text-amber-600 flex-shrink-0">
                    <span class="material-symbols-outlined text-xl">calendar_view_week</span>
                </div>
                <div>
                    <p class="text-[9px] text-slate-400 font-bold uppercase">H. Sonu</p>
                    <p class="text-sm font-black text-slate-800 dark:text-white"><?= (int)$stats->hafta_sonu_bekleyen ?></p>
                </div>
            </div>
            <div class="bg-white dark:bg-card-dark p-3 rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center text-rose-600 flex-shrink-0">
                    <span class="material-symbols-outlined text-xl">flag</span>
                </div>
                <div>
                    <p class="text-[9px] text-slate-400 font-bold uppercase">R. Tatil</p>
                    <p class="text-sm font-black text-slate-800 dark:text-white"><?= (int)$stats->resmi_tatil_bekleyen ?></p>
                </div>
            </div>
        </div>

        <?php if (empty($bekleyenler)): ?>
            <div class="bg-white dark:bg-card-dark rounded-3xl p-10 text-center border border-dashed border-slate-200 dark:border-slate-800">
                <div class="w-16 h-16 bg-slate-50 dark:bg-slate-800 text-slate-300 dark:text-slate-600 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="material-symbols-outlined text-4xl">check_circle</span>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white uppercase text-sm">ONAY BEKLEYEN YOK</h3>
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($bekleyenler as $nobet): ?>
                    <div class="bg-white dark:bg-card-dark rounded-2xl shadow-sm border border-slate-100 dark:border-slate-800 p-4 nobet-card" data-id="<?= $nobet->id ?>">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 dark:bg-rose-900/20 flex items-center justify-center font-bold text-sm">
                                    <?= getInitial($nobet->personel_adi) ?>
                                </div>
                                <div>
                                    <h3 class="font-bold text-slate-800 dark:text-white text-sm"><?= htmlspecialchars($nobet->personel_adi) ?></h3>
                                    <p class="text-[10px] text-slate-400 uppercase font-bold"><?= htmlspecialchars($nobet->departman ?: 'Genel') ?></p>
                                </div>
                            </div>
                            <span class="bg-rose-50 text-rose-600 dark:bg-rose-900/20 px-2 py-1 rounded-lg text-[8px] font-black uppercase border border-rose-100 dark:border-rose-800">
                                <?= str_replace('_', ' ', $nobet->nobet_tipi) ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-3 mb-4">
                            <div class="bg-slate-50 dark:bg-slate-800/40 p-2 rounded-xl text-center">
                                <p class="text-[9px] font-bold text-slate-400 mb-0.5">TARİH</p>
                                <p class="text-xs font-black text-slate-700 dark:text-slate-300"><?= date('d.m.Y', strtotime($nobet->nobet_tarihi)) ?></p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-800/40 p-2 rounded-xl text-center">
                                <p class="text-[9px] font-bold text-slate-400 mb-0.5">SAAT</p>
                                <p class="text-xs font-black text-slate-700 dark:text-slate-300"><?= substr($nobet->baslangic_saati, 0, 5) ?> - <?= substr($nobet->bitis_saati, 0, 5) ?></p>
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button onclick="approveNobet('<?= Security::encrypt($nobet->id) ?>')" class="flex-1 h-10 bg-emerald-500 text-white rounded-xl text-xs font-bold shadow-lg shadow-emerald-500/25 flex items-center justify-center gap-1.5 active:scale-95 transition-transform">
                                <span class="material-symbols-outlined text-[18px] filled">check_circle</span> ONAYLA
                            </button>
                            <button onclick="deleteNobet('<?= Security::encrypt($nobet->id) ?>')" class="w-10 h-10 bg-rose-50 text-rose-600 dark:bg-rose-900/20 rounded-xl flex items-center justify-center border border-rose-100 dark:border-rose-800">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- HAVUZ TAB (Personel Listesi) -->
    <div id="tab-havuz" class="tab-content <?= $showTab === 'havuz' ? '' : 'hidden' ?> space-y-4">
        <div class="bg-white dark:bg-card-dark rounded-3xl p-4 shadow-sm border border-slate-100 dark:border-slate-800">
            <h3 class="text-xs font-black text-slate-800 dark:text-white mb-4 uppercase tracking-widest px-1">AYLIK NÖBET DAĞILIMI</h3>
            <div class="space-y-2">
                <?php foreach ($aylikDagilim as $p): ?>
                    <div onclick="quickAddNobet('<?= Security::encrypt($p->id) ?>', '<?= addslashes($p->adi_soyadi) ?>')" class="flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-transparent hover:border-primary/20 transition-all active:scale-[0.98]">
                        <div class="w-10 h-10 rounded-full bg-white dark:bg-slate-700 flex items-center justify-center text-xs font-black text-primary shadow-sm">
                            <?= getInitial($p->adi_soyadi) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-bold text-slate-800 dark:text-white truncate"><?= htmlspecialchars($p->adi_soyadi) ?></h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase"><?= htmlspecialchars($p->departman ?: 'Genel') ?></p>
                        </div>
                        <div class="text-right">
                            <span class="block text-sm font-black text-slate-800 dark:text-white"><?= (int)$p->nobet_sayisi ?></span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase">Nöbet</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- NÖBET EKLE BOTTOM SHEET -->
<div id="add-nobet-sheet" class="fixed bottom-0 left-0 right-0 bg-white dark:bg-card-dark rounded-t-3xl z-[100] transform translate-y-full transition-transform duration-300 shadow-2xl safe-area-bottom max-h-[90vh] flex flex-col">
    <div class="flex justify-center pt-3 pb-2 shrink-0 cursor-pointer" onclick="closeAddNobetModal()">
        <div class="w-12 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full"></div>
    </div>
    <div class="px-5 pb-8 overflow-y-auto no-scrollbar">
        <h3 id="add-nobet-title" class="text-xl font-black text-slate-800 dark:text-white mb-6 uppercase tracking-tight">NÖBET PLANLA</h3>
        
        <form id="add-nobet-form" class="flex flex-col h-full max-h-[70vh]">
            <input type="hidden" name="action" value="add-nobet">
            <input type="hidden" name="yonetici_onayi" value="1">
            <input type="hidden" id="form-nobet-tarihi" name="nobet_tarihi" value="<?= date('Y-m-d') ?>">
            <input type="hidden" name="nobet_tipi" value="standart">
            <input type="hidden" name="baslangic_saati" value="18:00">
            <input type="hidden" name="bitis_saati" value="08:00">
            <input type="hidden" id="selected-personel-id" name="personel_id" value="" required>
            
            <!-- Arama Kutusu -->
            <div class="relative mb-4 shrink-0 px-1">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                <input type="text" id="personel-search-input" class="w-full bg-slate-50 dark:bg-slate-800/50 border-none rounded-2xl py-4 pl-12 pr-4 text-sm font-bold text-slate-700 dark:text-white focus:ring-primary placeholder:text-slate-400" placeholder="Personel adıyla ara...">
            </div>

            <!-- Personel Listesi (Scrollable) -->
            <div id="personel-list-container" class="flex-1 overflow-y-auto space-y-2 px-1 no-scrollbar mb-6 min-h-[200px]">
                <?php foreach($personeller as $p): ?>
                    <div class="personel-item flex items-center gap-3 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/40 border-2 border-transparent transition-all active:scale-[0.98] cursor-pointer" 
                         data-id="<?= Security::encrypt($p->id) ?>" 
                         data-name="<?= htmlspecialchars(strtolower($p->adi_soyadi)) ?>">
                        <div class="w-10 h-10 rounded-full bg-white dark:bg-slate-700 flex items-center justify-center text-xs font-black text-rose-500 shadow-sm border border-slate-100 dark:border-slate-800 shrink-0">
                            <?= getInitial($p->adi_soyadi) ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-black text-slate-700 dark:text-white truncate"><?= htmlspecialchars($p->adi_soyadi) ?></h4>
                            <p class="text-[10px] text-slate-400 font-bold uppercase truncate"><?= htmlspecialchars($p->departman ?: 'Genel') ?></p>
                        </div>
                        <div class="selection-indicator opacity-0 transition-opacity">
                            <span class="material-symbols-outlined text-rose-500 filled">check_circle</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <button type="submit" id="btn-save-nobet" class="w-full py-5 bg-rose-600 text-white rounded-2xl font-black text-base shadow-lg shadow-rose-600/30 active:scale-95 transition-all shrink-0 uppercase tracking-wider opacity-50 pointer-events-none">
                PLANLAMAYI KAYDET
            </button>
        </form>
    </div>
</div>

<div id="nobet-modal-overlay" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[90] hidden opacity-0 transition-opacity" onclick="closeAddNobetModal()"></div>

<script>
let calendar;
let allEvents = [];

$(document).ready(function() {
    initCalendar();
    
    // Personel Arama
    $('#personel-search-input').on('input', function() {
        const query = $(this).val().toLowerCase();
        $('.personel-item').each(function() {
            const name = $(this).data('name');
            if (name.includes(query)) $(this).removeClass('hidden');
            else $(this).addClass('hidden');
        });
    });

    // Personel Seçimi
    $(document).on('click', '.personel-item', function() {
        const id = $(this).data('id');
        const name = $(this).find('h4').text();
        
        // UI Güncelleme
        $('.personel-item').removeClass('border-rose-500 bg-rose-50 dark:bg-rose-900/10').find('.selection-indicator').addClass('opacity-0');
        $(this).addClass('border-rose-500 bg-rose-50 dark:bg-rose-900/10').find('.selection-indicator').removeClass('opacity-0');
        
        // Form Güncelleme
        $('#selected-personel-id').val(id);
        $('#btn-save-nobet').removeClass('opacity-50 pointer-events-none');
        $('#add-nobet-title').text(name + ' İÇİN PLANLA');
    });

    $('#add-nobet-form').on('submit', function(e) {
        e.preventDefault();
        Loading.show();
        $.ajax({
            url: '../views/nobet/api.php',
            type: 'POST',
            data: $(this).serialize(),
            success: function(res) {
                Loading.hide();
                try {
                    const response = typeof res === 'object' ? res : JSON.parse(res);
                    if (response.success || response.status === 'success') {
                        Toast.show('Nöbet başarıyla eklendi');
                        location.reload();
                    } else {
                        Alert.error('Hata', response.message || 'Bir hata oluştu');
                    }
                } catch (e) { Alert.error('Hata', 'Sunucu yanıtı işlenemedi'); }
            },
            error: function() { Loading.hide(); Alert.error('Hata', 'Bağlantı hatası'); }
        });
    });
});

function initCalendar() {
    const calendarEl = document.getElementById('mobile-nobet-calendar');
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'tr',
        headerToolbar: false,
        height: 'auto',
        dayMaxEvents: 4,
        selectable: true,
        events: function(info, successCallback, failureCallback) {
            $.ajax({
                url: '../views/nobet/api.php',
                type: 'POST',
                data: { action: 'get-calendar-events', start: info.startStr, end: info.endStr },
                dataType: 'json',
                success: function(res) {
                    allEvents = res;
                    successCallback(res);
                }
            });
        },
        eventContent: function(arg) {
            const name = arg.event.title;
            const initial = name ? name.charAt(0).toUpperCase() : '?';
            const bgColor = arg.event.backgroundColor || 'var(--primary)';
            return {
                html: `<div class="fc-event-main-frame flex items-center justify-center w-full h-full">
                         <div class="w-5 h-5 rounded-full text-white flex items-center justify-center text-[8px] font-black border border-white shadow-sm" style="background-color: ${bgColor}" title="${name}">
                            ${initial}
                         </div>
                       </div>`
            };
        },
        datesSet: function(info) {
            // Ay ismini Türkçe olarak güncelle
            const title = info.view.title;
            $('#calendar-month-title').text(title);
        },
        dateClick: function(info) {
            openAddNobetModal(info.dateStr);
            showDayDetails(info.dateStr); // Yine de detayları aşağıda gösterelim
        },
        eventClick: function(info) {
            showDayDetails(info.event.startStr);
        }
    });
    calendar.render();
}

function showDayDetails(dateStr) {
    const dayEvents = allEvents.filter(e => e.start === dateStr);
    const label = new Date(dateStr).toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', weekday: 'long' });
    $('#selected-day-label').text(label);
    
    let html = '';
    if (dayEvents.length === 0) {
        html = `
            <div class="bg-white dark:bg-card-dark rounded-2xl p-5 border border-dashed border-slate-200 dark:border-slate-800 text-center">
                <p class="text-xs text-slate-400 mb-3 font-medium">Bu gün için nöbet planı yok.</p>
                <button onclick="openAddNobetModal('${dateStr}')" class="px-4 py-2 bg-primary/10 text-primary rounded-xl text-[10px] font-black uppercase">BURAYA EKLE</button>
            </div>
        `;
    } else {
        dayEvents.forEach(e => {
            const encryptedId = e.id; // API already encrypts it
            html += `
                <div class="bg-white dark:bg-card-dark rounded-2xl p-4 border border-slate-100 dark:border-slate-800 shadow-sm flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-slate-50 text-slate-400 flex items-center justify-center font-bold text-[10px]">
                            ${getInitial(e.title)}
                        </div>
                        <div>
                            <h4 class="text-xs font-bold text-slate-800 dark:text-white">${e.title}</h4>
                            <p class="text-[9px] text-slate-400 font-bold">${e.extendedProps.baslangic_saati.substr(0,5)} - ${e.extendedProps.bitis_saati.substr(0,5)}</p>
                        </div>
                    </div>
                    <button onclick="deleteNobet('${encryptedId}')" class="text-rose-500 w-8 h-8 rounded-lg hover:bg-rose-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                </div>
            `;
        });
        html += `
            <button onclick="openAddNobetModal('${dateStr}')" class="w-full py-3 bg-slate-50 dark:bg-slate-800/50 border border-dashed border-slate-200 dark:border-slate-700 rounded-2xl text-[10px] font-black text-slate-400 uppercase tracking-widest">
                + PERSONEL EKLE
            </button>
        `;
    }
    $('#selected-day-list').html(html);
    
    // Smooth scroll to details
    document.getElementById('selected-day-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function switchNobetTab(tab) {
    $('.tab-content').addClass('hidden');
    $(`#tab-${tab}`).removeClass('hidden');
    
    $('[id^="btn-tab-"]').removeClass('bg-rose-500 text-white shadow-lg shadow-rose-500/25').addClass('text-slate-400');
    $(`#btn-tab-${tab}`).addClass('bg-rose-500 text-white shadow-lg shadow-rose-500/25').removeClass('text-slate-400');
    
    if (tab === 'planlama' && calendar) {
        calendar.updateSize();
    }
}

function changeMonth(offset) {
    if (offset > 0) calendar.next();
    else calendar.prev();
    
    const date = calendar.getDate();
    const y = date.getFullYear();
    const m = date.getMonth() + 1;
    // URL'yi güncelle (isteğe bağlı, PHP verileri için)
    // location.href = `?p=nobet&tab=planlama&ay=${m}&yil=${y}`;
}

function openAddNobetModal(date = null) {
    if (date) {
        $('#form-nobet-tarihi').val(date);
    }
    $('#add-nobet-sheet').removeClass('translate-y-full');
    $('#nobet-modal-overlay').removeClass('hidden');
    setTimeout(() => $('#nobet-modal-overlay').removeClass('opacity-0'), 10);
}

function quickAddNobet(personelId, name) {
    const item = $(`.personel-item[data-id="${personelId}"]`);
    if (item.length) {
        item.click();
        // Scroll to selected item
        item[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
    } else {
        $('#selected-personel-id').val(personelId);
        $('#btn-save-nobet').removeClass('opacity-50 pointer-events-none');
        $('#add-nobet-title').text(name + ' İÇİN PLANLA');
    }
    openAddNobetModal();
}

function closeAddNobetModal() {
    $('#add-nobet-sheet').addClass('translate-y-full');
    $('#nobet-modal-overlay').addClass('opacity-0');
    setTimeout(() => {
        $('#nobet-modal-overlay').addClass('hidden');
        $('#add-nobet-title').text('NÖBET PLANLA');
        // Formu Sıfırla
        $('#selected-personel-id').val('');
        $('#personel-search-input').val('').trigger('input');
        $('.personel-item').removeClass('border-rose-500 bg-rose-50 dark:bg-rose-900/10').find('.selection-indicator').addClass('opacity-0');
        $('#btn-save-nobet').addClass('opacity-50 pointer-events-none');
    }, 300);
}

function approveNobet(id) {
    Alert.confirm('Nöbet Onayı', 'Bu nöbet kaydını onaylamak istiyor musunuz?', 'Evet, Onayla').then(res => {
        if (res) performAction('onayla-nobet', { nobet_id: id });
    });
}

function deleteNobet(id) {
    Alert.confirmDelete('Nöbeti Sil', 'Bu nöbet kaydı silinecektir. Emin misiniz?', 'Evet, Sil').then(res => {
        if (res) performAction('delete-nobet', { nobet_id: id });
    });
}

function performAction(action, data) {
    Loading.show();
    $.ajax({
        url: '../views/nobet/api.php',
        type: 'POST',
        data: { action: action, ...data },
        success: function(res) {
            Loading.hide();
            try {
                const response = typeof res === 'object' ? res : JSON.parse(res);
                if (response.success || response.status === 'success') {
                    Toast.show(response.message || 'İşlem başarılı');
                    location.reload();
                } else { Alert.error('Hata', response.message || 'Bir hata oluştu'); }
            } catch (e) { Alert.error('Hata', 'Sunucudan geçersiz yanıt alındı'); }
        },
        error: function() { Loading.hide(); Alert.error('Hata', 'Bağlantı hatası'); }
    });
}

function getInitial(name) {
    return name ? name.charAt(0).toUpperCase() : '?';
}
function changeNobetMonth(offset) {
    let currentAy = <?= $ay ?>;
    let currentYil = <?= $yil ?>;
    
    currentAy += offset;
    if (currentAy > 12) {
        currentAy = 1;
        currentYil++;
    } else if (currentAy < 1) {
        currentAy = 12;
        currentYil--;
    }
    
    window.location.href = `?p=nobet&tab=bekleyen&ay=${currentAy}&yil=${currentYil}`;
}
</script>

<style>
/* FullCalendar Mobile Tweaks */
.fc .fc-toolbar { display: none; }
.fc .fc-view-harness { background: transparent; }
.fc .fc-daygrid-day-frame { min-height: 50px !important; }
.fc .fc-daygrid-day-number { font-size: 10px; font-weight: 800; color: #94a3b8; padding: 2px 4px; width: 100%; text-align: center; }
.fc .fc-day-today { background: rgba(var(--primary-rgb), 0.05) !important; }
.fc .fc-day-today .fc-daygrid-day-number { color: var(--primary); font-size: 13px; }
.fc-theme-standard td, .fc-theme-standard th { border: 1px solid rgba(0,0,0,0.03) !important; }
.dark .fc-theme-standard td, .dark .fc-theme-standard th { border: 1px solid rgba(255,255,255,0.05) !important; }
.fc .fc-highlight { background: rgba(var(--primary-rgb), 0.1) !important; }
.fc-event { background: transparent !important; border: none !important; box-shadow: none !important; }
.fc-daygrid-event { background: transparent !important; }
.fc-event-main { padding: 0 !important; display: flex; justify-content: center; }
.fc-daygrid-event-harness { margin: 1px 0 !important; }
.fc-daygrid-day-events { margin-top: 2px !important; }
.fc-daygrid-more-link { font-size: 8px !important; font-weight: 900 !important; color: var(--primary) !important; text-align: center; width: 100%; display: block; }

/* Select2 Tailwind Integration */
.select2-container--default .select2-selection--single {
    background-color: rgb(248 250 252);
    border: none !important;
    border-radius: 1rem !important;
    height: 52px !important;
    display: flex;
    align-items: center;
}
.dark .select2-container--default .select2-selection--single {
    background-color: rgba(30, 41, 59, 0.5);
    color: white;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    font-size: 0.875rem;
    font-weight: 700;
    color: rgb(51 65 85);
    padding-left: 1rem !important;
}
.dark .select2-container--default .select2-selection--single .select2-selection__rendered {
    color: #f1f5f9;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 50px !important;
    right: 10px !important;
}
.select2-dropdown {
    border: 1px solid rgb(241 245 249) !important;
    border-radius: 1rem !important;
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1) !important;
    overflow: hidden;
}
.dark .select2-dropdown {
    background-color: #1e293b !important;
    border-color: #334155 !important;
}
.select2-search__field {
    border-radius: 0.75rem !important;
    border: 1px solid rgb(226 232 240) !important;
    margin-bottom: 5px !important;
}
.dark .select2-search__field {
    background-color: #0f172a !important;
    border-color: #334155 !important;
    color: white !important;
}
.select2-results__option {
    font-size: 0.875rem;
    font-weight: 600;
    padding: 10px 15px !important;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: var(--primary) !important;
}
</style>
