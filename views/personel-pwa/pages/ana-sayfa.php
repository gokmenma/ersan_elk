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
                <button onclick="openNotificationModal()"
                    class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center relative">
                    <span class="material-symbols-outlined">notifications</span>
                    <span id="notification-badge"
                        class="absolute -top-1 -right-1 min-w-[18px] h-[18px] bg-red-500 rounded-full text-[10px] font-bold flex items-center justify-center border-2 border-primary hidden"></span>
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
            <button onclick="showAllActivities()" class="text-primary text-sm font-semibold">Tümünü gör</button>
        </div>

        <div id="activities-container" class="card overflow-hidden">
            <!-- Loading State -->
            <div id="activities-loading" class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        </div>
    </section>
</div>

<!-- Notification Modal -->
<div id="notification-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>
        
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Bildirimler</h3>
            <div class="flex items-center gap-2">
                <button onclick="markAllAsRead()" class="text-xs text-primary font-medium" title="Tümünü Okundu İşaretle">
                    <span class="material-symbols-outlined text-lg">done_all</span>
                </button>
                <button onclick="deleteAllNotifications()" class="text-xs text-red-500 font-medium" title="Tümünü Sil">
                    <span class="material-symbols-outlined text-lg">delete_sweep</span>
                </button>
            </div>
        </div>

        <div id="notification-list" class="flex flex-col gap-3 max-h-[60vh] overflow-y-auto">
            <!-- Bildirimler buraya yüklenecek -->
            <div class="flex items-center justify-center py-8">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
            </div>
        </div>

        <button onclick="Modal.close('notification-modal')"
            class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
            Kapat
        </button>
    </div>
</div>

<!-- All Activities Modal -->
<div id="all-activities-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3 max-h-[85vh]">
        <div class="modal-handle"></div>
        <h3 class="text-lg font-bold text-slate-900 dark:text-white mb-4">Tüm Etkinlikler</h3>

        <div id="all-activities-list" class="flex flex-col gap-2 max-h-[60vh] overflow-y-auto">
            <!-- All activities will be loaded here -->
        </div>

        <button onclick="Modal.close('all-activities-modal')"
            class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
            Kapat
        </button>
    </div>
</div>

<!-- Notification Detail Modal -->
<div id="notification-detail-modal" class="modal-overlay">
    <div class="modal-content p-6 pt-3">
        <div class="modal-handle"></div>
        
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center gap-3">
                <button onclick="closeNotificationDetail()" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center">
                    <span class="material-symbols-outlined text-slate-600">arrow_back</span>
                </button>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white">Bildirim Detayı</h3>
            </div>
            <button onclick="deleteCurrentNotification()" class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center" title="Sil">
                <span class="material-symbols-outlined text-red-600 text-lg">delete</span>
            </button>
        </div>

        <div id="notification-detail-content" class="bg-slate-50 dark:bg-slate-800 rounded-xl p-4">
            <!-- Detail content -->
        </div>

        <button onclick="closeNotificationDetail()"
            class="w-full mt-4 py-3 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 font-semibold rounded-xl">
            Kapat
        </button>
    </div>
</div>

<script>
    // Global data
    var allActivitiesData = [];
    var allNotificationsData = [];
    var currentNotificationIndex = -1;

    document.addEventListener('DOMContentLoaded', function () {
        // Load dashboard data
        loadDashboardData();
        // Load notification count
        loadNotificationCount();
        // Load recent activities
        loadRecentActivities();
    });

    async function loadDashboardData() {
        try {
            var response = await API.request('getDashboardData');
            if (response.success) {
                document.getElementById('total-earning').textContent = Format.currency(response.data.total_earning || 0);
                document.getElementById('received-payment').textContent = Format.currency(response.data.received_payment || 0);
                document.getElementById('remaining-balance').textContent = Format.currency(response.data.remaining_balance || 0);
            }
        } catch (error) {
            console.error('Dashboard data load error:', error);
        }
    }

    async function loadNotificationCount() {
        try {
            var response = await API.request('getMyNotifications');
            if (response.success && response.data) {
                // Sadece okunmamış bildirimleri say
                var unreadCount = response.data.filter(function(n) { return !n.okundu; }).length;
                var badge = document.getElementById('notification-badge');
                if (badge) {
                    if (unreadCount > 0) {
                        badge.style.display = 'flex';
                        badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                    } else {
                        badge.style.display = 'none';
                    }
                }
            }
        } catch (error) {
            console.error('Notification count error:', error);
        }
    }

    async function loadRecentActivities() {
        var container = document.getElementById('activities-container');
        
        try {
            var response = await API.request('getRecentActivities');
            
            if (response.success && response.data && response.data.length > 0) {
                allActivitiesData = response.data;
                // Show first 5 activities on homepage
                var displayActivities = response.data.slice(0, 5);
                container.innerHTML = displayActivities.map(function(activity, index) {
                    return renderActivityItem(activity, index === displayActivities.length - 1);
                }).join('');
            } else {
                container.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><span class="material-symbols-outlined text-4xl text-slate-300 mb-2">timeline</span><p class="text-sm text-slate-500">Henüz etkinlik yok</p></div>';
            }
        } catch (error) {
            console.error('Activities load error:', error);
            container.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><span class="material-symbols-outlined text-4xl text-red-300 mb-2">error</span><p class="text-sm text-slate-500">Etkinlikler yüklenemedi</p></div>';
        }
    }

    function renderActivityItem(activity, isLast) {
        var iconColorMap = {
            'blue': 'bg-blue-100 dark:bg-blue-900/30 text-blue-600',
            'green': 'bg-green-100 dark:bg-green-900/30 text-green-600',
            'orange': 'bg-orange-100 dark:bg-orange-900/30 text-orange-600',
            'primary': 'bg-primary/10 text-primary'
        };

        var badgeClassMap = {
            'success': 'badge-success',
            'warning': 'badge-warning',
            'danger': 'badge-danger',
            'gray': 'badge-gray'
        };

        var iconClass = iconColorMap[activity.icon_color] || iconColorMap['primary'];
        var badgeClass = badgeClassMap[activity.status_badge] || 'badge-gray';
        var borderClass = isLast ? '' : 'border-b border-slate-100 dark:border-slate-800';

        return '<div class="activity-item flex items-center gap-4 p-4 ' + borderClass + '">' +
            '<div class="w-10 h-10 rounded-full ' + iconClass + ' flex items-center justify-center">' +
                '<span class="material-symbols-outlined text-xl">' + escapeHtml(activity.icon) + '</span>' +
            '</div>' +
            '<div class="flex-1 min-w-0">' +
                '<p class="text-sm font-semibold text-slate-900 dark:text-white truncate">' + escapeHtml(activity.title) + '</p>' +
                '<p class="text-xs text-slate-500 truncate">' + escapeHtml(activity.description) + '</p>' +
            '</div>' +
            '<div class="text-right flex-shrink-0">' +
                '<p class="text-xs text-slate-400">' + escapeHtml(activity.time_ago) + '</p>' +
                '<span class="badge ' + badgeClass + '">' + escapeHtml(activity.status_text) + '</span>' +
            '</div>' +
        '</div>';
    }

    function showAllActivities() {
        var container = document.getElementById('all-activities-list');
        
        if (allActivitiesData.length > 0) {
            container.innerHTML = allActivitiesData.map(function(activity, index) {
                return renderActivityItem(activity, index === allActivitiesData.length - 1);
            }).join('');
        } else {
            container.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><span class="material-symbols-outlined text-4xl text-slate-300 mb-2">timeline</span><p class="text-sm text-slate-500">Henüz etkinlik yok</p></div>';
        }
        
        Modal.open('all-activities-modal');
    }

    function openNotificationModal() {
        Modal.open('notification-modal');
        loadNotifications();
    }

    async function loadNotifications() {
        var container = document.getElementById('notification-list');
        container.innerHTML = '<div class="flex items-center justify-center py-8"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div></div>';

        try {
            var response = await API.request('getMyNotifications');
            
            if (response.success && response.data && response.data.length > 0) {
                allNotificationsData = response.data;
                container.innerHTML = response.data.map(function(notification, index) {
                    var unreadIndicator = notification.okundu ? '' : '<div class="absolute top-2 left-2 w-2 h-2 bg-primary rounded-full"></div>';
                    var bgClass = notification.okundu ? 'bg-slate-50 dark:bg-slate-800' : 'bg-blue-50 dark:bg-blue-900/20 border border-primary/20';
                    
                    return '<div class="relative flex items-start gap-3 p-3 ' + bgClass + ' rounded-xl cursor-pointer hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" onclick="showNotificationDetail(' + index + ')">' +
                        unreadIndicator +
                        '<div class="w-8 h-8 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">' +
                            '<span class="material-symbols-outlined text-blue-600 text-lg">notifications</span>' +
                        '</div>' +
                        '<div class="flex-1 min-w-0">' +
                            '<p class="text-sm font-medium text-slate-900 dark:text-white ' + (notification.okundu ? '' : 'font-bold') + '">' + escapeHtml(notification.title) + '</p>' +
                            '<p class="text-xs text-slate-500 line-clamp-2">' + escapeHtml(notification.body) + '</p>' +
                            '<p class="text-[10px] text-primary mt-1">' + notification.time_ago + '</p>' +
                        '</div>' +
                        '<span class="material-symbols-outlined text-slate-400 text-lg self-center">chevron_right</span>' +
                    '</div>';
                }).join('');
            } else {
                container.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><span class="material-symbols-outlined text-4xl text-slate-300 mb-2">notifications_off</span><p class="text-sm text-slate-500">Henüz bildirim yok</p></div>';
            }
        } catch (error) {
            console.error('Notifications load error:', error);
            container.innerHTML = '<div class="flex flex-col items-center justify-center py-8 text-center"><span class="material-symbols-outlined text-4xl text-red-300 mb-2">error</span><p class="text-sm text-slate-500">Bildirimler yüklenemedi</p></div>';
        }
    }

    async function showNotificationDetail(index) {
        var notification = allNotificationsData[index];
        if (!notification) return;

        currentNotificationIndex = index;

        // Bildirimi okundu olarak işaretle
        if (!notification.okundu) {
            await API.request('markNotificationRead', { notification_id: notification.id });
            allNotificationsData[index].okundu = true;
            loadNotificationCount(); // Badge'i güncelle
        }

        var container = document.getElementById('notification-detail-content');
        container.innerHTML = 
            '<div class="flex items-center gap-3 mb-4">' +
                '<div class="w-12 h-12 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0">' +
                    '<span class="material-symbols-outlined text-blue-600 text-2xl">notifications</span>' +
                '</div>' +
                '<div>' +
                    '<p class="text-xs text-primary font-medium">' + escapeHtml(notification.time_ago) + '</p>' +
                '</div>' +
            '</div>' +
            '<h4 class="text-lg font-bold text-slate-900 dark:text-white mb-3">' + escapeHtml(notification.title) + '</h4>' +
            '<p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed whitespace-pre-wrap">' + escapeHtml(notification.body) + '</p>';

        Modal.close('notification-modal');
        setTimeout(function() {
            Modal.open('notification-detail-modal');
        }, 200);
    }

    function closeNotificationDetail() {
        Modal.close('notification-detail-modal');
        setTimeout(function() {
            Modal.open('notification-modal');
            loadNotifications(); // Listeyi güncelle
        }, 200);
    }

    async function deleteCurrentNotification() {
        if (currentNotificationIndex < 0) return;
        
        var notification = allNotificationsData[currentNotificationIndex];
        if (!notification) return;

        var confirmed = await Alert.confirm('Bildirimi Sil', 'Bu bildirimi silmek istediğinize emin misiniz?', 'Evet, Sil', 'Vazgeç');
        if (!confirmed) return;

        try {
            var response = await API.request('deleteNotification', { notification_id: notification.id });
            if (response.success) {
                Toast.show('Bildirim silindi', 'success');
                allNotificationsData.splice(currentNotificationIndex, 1);
                currentNotificationIndex = -1;
                loadNotificationCount();
                closeNotificationDetail();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    async function markAllAsRead() {
        try {
            var response = await API.request('markAllNotificationsRead');
            if (response.success) {
                Toast.show('Tüm bildirimler okundu olarak işaretlendi', 'success');
                loadNotifications();
                loadNotificationCount();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    async function deleteAllNotifications() {
        var confirmed = await Alert.confirm('Tüm Bildirimleri Sil', 'Tüm bildirimleri silmek istediğinize emin misiniz?', 'Evet, Tümünü Sil', 'Vazgeç');
        if (!confirmed) return;

        try {
            var response = await API.request('deleteAllNotifications');
            if (response.success) {
                Toast.show('Tüm bildirimler silindi', 'success');
                allNotificationsData = [];
                loadNotifications();
                loadNotificationCount();
            } else {
                Toast.show(response.message || 'Bir hata oluştu', 'error');
            }
        } catch (error) {
            Toast.show('Bir hata oluştu', 'error');
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>