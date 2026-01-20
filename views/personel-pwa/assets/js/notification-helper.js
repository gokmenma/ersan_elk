/**
 * Push Notification Helper Functions for Profile Page
 */

// Check notification status on page load
function checkNotificationStatus() {
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

  const permission = Notification.permission;

  if (permission === "granted") {
    navigator.serviceWorker.ready.then((registration) => {
      registration.pushManager.getSubscription().then((subscription) => {
        if (subscription) {
          statusEl.textContent = "Bildirimler açık";
          statusEl.classList.add("text-green-500");
          btn.textContent = "Açık ✓";
          btn.classList.remove("bg-primary");
          btn.classList.add("bg-green-500");
        } else {
          statusEl.textContent = "İzin verildi, abone olunmadı";
          btn.textContent = "Etkinleştir";
        }
      });
    });
  } else if (permission === "denied") {
    statusEl.textContent = "İzin reddedildi";
    statusEl.classList.add("text-red-500");
    btn.textContent = "Engellendi";
    btn.disabled = true;
    btn.classList.add("opacity-50", "bg-red-500");
    btn.classList.remove("bg-primary");
  } else {
    statusEl.textContent = "Bildirimler kapalı";
    btn.textContent = "Aç";
  }
}

// Toggle notification subscription
async function toggleNotifications() {
  const btn = document.getElementById("notification-toggle-btn");
  const statusEl = document.getElementById("notification-status");

  if (Notification.permission === "denied") {
    Toast.show("Bildirimler tarayıcı tarafından engellendi", "error");
    return;
  }

  btn.disabled = true;
  btn.textContent = "İşleniyor...";

  try {
    const permission = await Notification.requestPermission();

    if (permission === "granted") {
      const subscribed = await Push.subscribe();

      if (subscribed) {
        statusEl.textContent = "Bildirimler açık";
        statusEl.classList.add("text-green-500");
        btn.textContent = "Açık ✓";
        btn.classList.remove("bg-primary");
        btn.classList.add("bg-green-500");
      }
    } else if (permission === "denied") {
      statusEl.textContent = "İzin reddedildi";
      statusEl.classList.add("text-red-500");
      btn.textContent = "Engellendi";
      btn.classList.add("opacity-50", "bg-red-500");
      btn.classList.remove("bg-primary");
    }
  } catch (error) {
    console.error("Notification error:", error);
    Toast.show("Bir hata oluştu", "error");
    btn.textContent = "Tekrar Dene";
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
