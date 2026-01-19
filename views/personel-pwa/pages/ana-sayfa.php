<?php
/**
 * Personel PWA - Ana Sayfa
 * Özet bilgiler ve hızlı işlemler
 */
use App\Helper\Helper;

?>

<div class="flex flex-col min-h-screen">
    <!-- Header -->
    <header class="bg-gradient-primary text-white px-4 pt-4 pb-8 rounded-b-3xl relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute top-0 right-0 w-64 h-64 bg-white rounded-full -mr-32 -mt-32"></div>
            <div class="absolute bottom-0 left-0 w-40 h-40 bg-white rounded-full -ml-20 -mb-20"></div>
        </div>

        <div class="relative z-10">
            <!-- User Info & Notification -->
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center overflow-hidden">
                        <?php if (!empty($personel->foto)): ?>
                            <img src="<?php echo Helper::base_url('uploads/personel/' . $personel->foto); ?>" alt="Profil"
                                class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="material-symbols-outlined text-2xl">person</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="text-white/80 text-sm">Hoş geldin,</p>
                        <h1 class="text-xl font-bold"><?php echo $personel->adi_soyadi ?? 'Personel'; ?></h1>
                    </div>
                </div>
                <button onclick="Modal.open('notification-modal')"
                    class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <span
                        class="absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white"></span>
                </button>
            </div>

            <!-- Welcome Message -->
            <div class="mb-2">
                <p class="text-white/80 text-sm">
                    <?php
                    $hour = date('H');
                    if ($hour < 12)
                        echo 'Günaydın!';
                    elseif ($hour < 18)
                        echo 'İyi günler!';
                    else
                        echo 'İyi akşamlar!';
                    ?>
                </p>
                <p class="text-white/90 text-base">İşte bugünkü özet bilgileriniz.</p>
            </div>
        </div>
    </header>

    <!-- Stats Cards -->
    <section class="px-4 -mt-4 relative z-20">
        <div class="grid grid-cols-1 gap-3">
            <!-- Toplam Hakediş -->
            <div class="card p-4 flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-2xl">account_balance_wallet</span>
                </div>
                <div class="flex-1">
                    <p class="text-slate-500 dark:text-slate-400 text-sm">Toplam Hakediş</p>
                    <p class="text-xl font-bold text-slate-900 dark:text-white" id="total-earning">0,00 ₺</p>
                </div>
                <div class="flex items-center gap-1">
                    <span class="badge badge-success">+%5.2</span>
                </div>
            </div>

            <!-- 2 Column Stats -->
            <div class="grid grid-cols-2 gap-3">
                <!-- Alınan Ödeme -->
                <div class="card p-4">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-primary text-xl">payments</span>
                        <p class="text-slate-500 dark:text-slate-400 text-xs">Alınan Ödeme</p>
                    </div>
                    <p class="text-lg font-bold text-slate-900 dark:text-white" id="received-payment">0,00 ₺</p>
                </div>

                <!-- Kalan Bakiye -->
                <div class="card p-4 bg-gradient-primary text-white stat-card">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="material-symbols-outlined text-xl">savings</span>
                        <p class="text-white/80 text-xs">Kalan Bakiye</p>
                    </div>
                    <p class="text-lg font-bold" id="remaining-balance">0,00 ₺</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Actions -->
    <section class="px-4 mt-6">
        <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-3">Hızlı İşlemler</h2>
        <div class="grid grid-cols-2 gap-3">
            <a href="?page=izin" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">event_busy</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">İzin Talebi</h3>
                    <p class="text-xs text-slate-500">Yeni izin planla</p>
                </div>
            </a>

            <a href="?page=talep" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">assignment</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Talep Oluştur</h3>
                    <p class="text-xs text-slate-500">Talep, öneri, şikayet</p>
                </div>
            </a>

            <a href="?page=bordro" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">receipt_long</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Avanslar</h3>
                    <p class="text-xs text-slate-500">Avans Talebi Yap</p>
                </div>
            </a>

            <a href="?page=profil" class="quick-action">
                <div class="quick-action-icon">
                    <span class="material-symbols-outlined text-slate-600 dark:text-slate-300">person_search</span>
                </div>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 dark:text-white">Profilim</h3>
                    <p class="text-xs text-slate-500">Bilgileri güncelle</p>
                </div>
            </a>
        </div>
    </section>

    <!-- Recent Activities -->
    <section class="px-4 mt-6 mb-8">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white">Son Etkinlikler</h2>
            <button class="text-primary text-sm font-semibold">Tümünü gör</button>
        </div>

        <div class="card overflow-hidden">
            <!-- Activity 1 -->
            <div class="activity-item flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-blue-600 text-xl">verified</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">Yıllık İzin Onaylandı</p>
                    <p class="text-xs text-slate-500 truncate">20-25 Aralık tarihli talebiniz onaylandı.</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">2s önce</p>
                    <span class="badge badge-success">Onaylandı</span>
                </div>
            </div>

            <!-- Activity 2 -->
            <div class="activity-item flex items-center gap-4 p-4 border-b border-slate-100 dark:border-slate-800">
                <div
                    class="w-10 h-10 rounded-full bg-orange-100 dark:bg-orange-900/30 flex items-center justify-center">
                    <span class="material-symbols-outlined text-orange-600 text-xl">pending_actions</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">Talep #442</p>
                    <p class="text-xs text-slate-500 truncate">Ofis 4B klima onarım talebi inceleniyor.</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">Dün</p>
                    <span class="badge badge-warning">İnceleniyor</span>
                </div>
            </div>

            <!-- Activity 3 -->
            <div class="activity-item flex items-center gap-4 p-4">
                <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center">
                    <span class="material-symbols-outlined text-primary text-xl">payments</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">Bordro Hazırlandı</p>
                    <p class="text-xs text-slate-500 truncate">Aralık 2024 bordro ekstresi erişime açıldı.</p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">28 Ara</p>
                    <span class="badge badge-gray">Tamamlandı</span>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Notification Modal -->
<div id="notification-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Bildirimler</h3>

        <div class="flex flex-col gap-3">
            <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                <div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-blue-600 text-lg">info</span>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900 dark:text-white">Yeni bordronuz hazır</p>
                    <p class="text-xs text-slate-500">Aralık 2024 bordronuzu görüntüleyebilirsiniz.</p>
                    <p class="text-[10px] text-slate-400 mt-1">2 saat önce</p>
                </div>
            </div>

            <div class="flex items-start gap-3 p-3 bg-slate-50 dark:bg-slate-800 rounded-xl">
                <div class="w-8 h-8 rounded-full bg-green-100 flex items-center justify-center flex-shrink-0">
                    <span class="material-symbols-outlined text-green-600 text-lg">check_circle</span>
                </div>
                <div>
                    <p class="text-sm font-medium text-slate-900 dark:text-white">İzin talebiniz onaylandı</p>
                    <p class="text-xs text-slate-500">20-25 Aralık tarihli izin talebiniz yönetici tarafından onaylandı.
                    </p>
                    <p class="text-[10px] text-slate-400 mt-1">1 gün önce</p>
                </div>
            </div>
        </div>

        <button onclick="Modal.close('notification-modal')"
            class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
            Kapat
        </button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Load dashboard data
        loadDashboardData();
    });

    async function loadDashboardData() {
        try {
            const response = await API.request('getDashboardData');
            if (response.success) {
                document.getElementById('total-earning').textContent = Format.currency(response.data.total_earning || 0);
                document.getElementById('received-payment').textContent = Format.currency(response.data.received_payment || 0);
                document.getElementById('remaining-balance').textContent = Format.currency(response.data.remaining_balance || 0);
            }
        } catch (error) {
            console.error('Dashboard data load error:', error);
        }
    }
</script>