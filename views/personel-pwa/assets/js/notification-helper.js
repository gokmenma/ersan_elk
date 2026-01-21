/**
 * Push Notification Helper Functions for Profile Page
 */

// Check notification status on page load
async function checkNotificationStatus() {
  const statusEl = document.getElementById("notification-status");
  const btn = document.getElementById("notification-toggle-btn");

  if (!statusEl || !btn) return;

  if (!("Notification" in window) || !("serviceWorker" in navigator)) {
    statusEl.textContent = "Tarayıcınız desteklemiyor";
    btn.disabled = true;
    btn.classList.add("opacity-50");
    btn.textContent = "Desteklenmiyor";
    return;
  }

  // Önce sunucudan gerçek abonelik durumunu kontrol et
  try {
    const response = await API.request("check-subscription-status");

    if (response.success && response.data && response.data.subscribed) {
      // Sunucuda abonelik var
      const permission = Notification.permission;

      if (permission === "granted") {
        statusEl.textContent = "Bildirimler açık";
        statusEl.classList.remove("text-red-500");
        statusEl.classList.add("text-green-500");
        btn.textContent = "Kapat";
        btn.classList.remove("bg-primary", "bg-red-500", "opacity-50");
        btn.classList.add("bg-green-500");
        btn.disabled = false;
        btn.dataset.subscribed = "true";
      } else {
        // İzin yok ama sunucuda kayıt var - temizle
        await API.request("remove-subscription");
        statusEl.textContent = "Bildirimler kapalı";
        statusEl.classList.remove("text-green-500", "text-red-500");
        btn.textContent = "Aç";
        btn.classList.remove("bg-green-500", "bg-red-500", "opacity-50");
        btn.classList.add("bg-primary");
        btn.disabled = false;
        btn.dataset.subscribed = "false";
      }
    } else {
      // Sunucuda abonelik yok
      const permission = Notification.permission;

      if (permission === "denied") {
        statusEl.textContent = "İzin reddedildi";
        statusEl.classList.remove("text-green-500");
        statusEl.classList.add("text-red-500");
        btn.textContent = "Engellendi";
        btn.disabled = true;
        btn.classList.add("opacity-50", "bg-red-500");
        btn.classList.remove("bg-primary", "bg-green-500");
      } else {
        statusEl.textContent = "Bildirimler kapalı";
        statusEl.classList.remove("text-green-500", "text-red-500");
        btn.textContent = "Aç";
        btn.classList.remove("bg-green-500", "bg-red-500", "opacity-50");
        btn.classList.add("bg-primary");
        btn.disabled = false;
      }
      btn.dataset.subscribed = "false";
    }
  } catch (error) {
    console.error("Failed to check subscription status:", error);
    statusEl.textContent = "Durum kontrol edilemedi";
    btn.textContent = "Aç";
    btn.dataset.subscribed = "false";
  }
}

// Toggle notification subscription
async function toggleNotifications() {
  const btn = document.getElementById("notification-toggle-btn");
  const statusEl = document.getElementById("notification-status");
  const isSubscribed = btn.dataset.subscribed === "true";

  btn.disabled = true;
  btn.textContent = "İşleniyor...";

  try {
    if (isSubscribed) {
      // Bildirimleri kapat
      const confirmed = await Alert.confirm(
        "Bildirimleri Kapat",
        "Bildirimleri kapatmak istediğinize emin misiniz?",
        "Evet, Kapat",
        "Vazgeç",
      );

      if (!confirmed) {
        btn.disabled = false;
        btn.textContent = "Kapat";
        return;
      }

      // Tarayıcı aboneliğini kaldır
      if ("serviceWorker" in navigator) {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();
        if (subscription) {
          await subscription.unsubscribe();
        }
      }

      // Sunucudan aboneliği sil
      const response = await API.request("remove-subscription");

      if (response.success) {
        statusEl.textContent = "Bildirimler kapalı";
        statusEl.classList.remove("text-green-500");
        btn.textContent = "Aç";
        btn.classList.remove("bg-green-500");
        btn.classList.add("bg-primary");
        btn.dataset.subscribed = "false";

        // Manuel kapatıldığını işaretle
        localStorage.setItem("notifications_manually_disabled", "true");

        Toast.show("Bildirimler kapatıldı", "success");
      } else {
        Toast.show(response.message || "Bir hata oluştu", "error");
        btn.textContent = "Kapat";
      }
    } else {
      // Bildirimleri aç
      if (Notification.permission === "denied") {
        Toast.show("Bildirimler tarayıcı tarafından engellendi", "error");
        btn.textContent = "Engellendi";
        btn.disabled = true;
        return;
      }

      const permission = await Notification.requestPermission();

      if (permission === "granted") {
        const subscribed = await Push.subscribe();

        if (subscribed) {
          // Manuel kapatma işaretini kaldır
          localStorage.removeItem("notifications_manually_disabled");

          statusEl.textContent = "Bildirimler açık";
          statusEl.classList.add("text-green-500");
          btn.textContent = "Kapat";
          btn.classList.remove("bg-primary");
          btn.classList.add("bg-green-500");
          btn.dataset.subscribed = "true";
        } else {
          btn.textContent = "Aç";
        }
      } else if (permission === "denied") {
        statusEl.textContent = "İzin reddedildi";
        statusEl.classList.add("text-red-500");
        btn.textContent = "Engellendi";
        btn.classList.add("opacity-50", "bg-red-500");
        btn.classList.remove("bg-primary");
        btn.disabled = true;
      } else {
        btn.textContent = "Aç";
      }
    }
  } catch (error) {
    console.error("Notification toggle error:", error);
    Toast.show("Bir hata oluştu", "error");
    btn.textContent = isSubscribed ? "Kapat" : "Aç";
  }

  btn.disabled = false;
}

// Auto-check status when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", () =>
    setTimeout(checkNotificationStatus, 500),
  );
} else {
  setTimeout(checkNotificationStatus, 500);
}
