/**
 * PWA Personel Portalı - Main JavaScript
 */

// ===== App State =====
const App = {
  isOnline: navigator.onLine,
  darkMode: localStorage.getItem("darkMode") === "true",

  init() {
    this.checkDarkMode();
    this.setupEventListeners();
    this.checkOnlineStatus();
    Push.init();
  },

  checkDarkMode() {
    if (this.darkMode) {
      document.documentElement.classList.add("dark");
    } else {
      document.documentElement.classList.remove("dark");
    }
  },

  toggleDarkMode() {
    this.darkMode = !this.darkMode;
    localStorage.setItem("darkMode", this.darkMode);
    this.checkDarkMode();
  },

  setupEventListeners() {
    // Online/Offline status
    window.addEventListener("online", () => this.updateOnlineStatus(true));
    window.addEventListener("offline", () => this.updateOnlineStatus(false));

    // Prevent bounce scroll on iOS
    document.body.addEventListener(
      "touchmove",
      (e) => {
        const activeModal = document.querySelector(".modal-overlay.active");
        if (!activeModal) return;

        const modalContent = e.target.closest(".modal-content");
        if (modalContent && activeModal.contains(modalContent)) return;

        e.preventDefault();
      },
      { passive: false },
    );
  },

  checkOnlineStatus() {
    this.updateOnlineStatus(navigator.onLine);
  },

  updateOnlineStatus(isOnline) {
    this.isOnline = isOnline;
    if (!isOnline) {
      Toast.show("Çevrimdışı moddasınız", "warning");
    }
  },
};

// ===== Loading Functions =====
const Loading = {
  count: 1, // Start with 1 for initial page load

  show() {
    this.count++;
    this.updateState();
  },

  hide() {
    this.count = Math.max(0, this.count - 1);
    this.updateState();
  },

  updateState() {
    const loader = document.getElementById("loading-overlay");
    if (!loader) return;

    if (this.count > 0) {
      loader.classList.remove("hidden");
    } else {
      loader.classList.add("hidden");

      // Switch to semi-transparent background for future API calls
      if (loader.classList.contains("bg-white")) {
        setTimeout(() => {
          // Only change if it's still hidden
          if (loader.classList.contains("hidden")) {
            loader.classList.remove("bg-white", "dark:bg-background-dark");
            loader.classList.add(
              "bg-white/80",
              "dark:bg-background-dark/80",
              "backdrop-blur-sm",
            );
          }
        }, 500);
      }
    }
  },
};

// ===== Toast Notifications =====
const Toast = {
  container: null,

  init() {
    this.container = document.getElementById("toast-container");
  },

  show(message, type = "success", duration = 3000) {
    if (!this.container) this.init();

    const toast = document.createElement("div");
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-xl">
                    ${
                      type === "success"
                        ? "check_circle"
                        : type === "error"
                          ? "error"
                          : type === "warning"
                            ? "warning"
                            : "info"
                    }
                </span>
                <span>${message}</span>
            </div>
        `;

    this.container.appendChild(toast);

    setTimeout(() => {
      toast.style.animation = "slideOutUp 0.3s ease-out forwards";
      setTimeout(() => toast.remove(), 300);
    }, duration);
  },
};

// ===== Alert Functions =====
const Alert = {
  // Base custom class configuration
  baseClass: {
    popup: "swal-custom-popup",
    title: "swal-custom-title",
    htmlContainer: "swal-custom-content",
    actions: "swal-custom-actions",
    confirmButton: "swal-custom-confirm",
    cancelButton: "swal-custom-cancel",
    icon: "swal-custom-icon",
  },

  async confirm(title, text, confirmText = "Evet", cancelText = "Vazgeç") {
    const result = await Swal.fire({
      title: title,
      text: text,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      buttonsStyling: false,
      reverseButtons: true,
      width: 320,
      padding: 0,
      customClass: {
        popup: "swal-custom-popup",
        title: "swal-custom-title",
        htmlContainer: "swal-custom-content",
        actions: "swal-custom-actions swal-actions-two",
        confirmButton: "swal-custom-confirm swal-confirm-primary",
        cancelButton: "swal-custom-cancel",
        icon: "swal-custom-icon swal-icon-question",
      },
    });
    return result.isConfirmed;
  },

  // Delete confirmation with red button and warning icon
  async confirmDelete(
    title,
    text,
    confirmText = "Evet, Sil",
    cancelText = "Vazgeç",
  ) {
    const result = await Swal.fire({
      title: title,
      text: text,
      icon: "warning",
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      buttonsStyling: false,
      reverseButtons: true,
      width: 320,
      padding: 0,
      customClass: {
        popup: "swal-custom-popup",
        title: "swal-custom-title",
        htmlContainer: "swal-custom-content",
        actions: "swal-custom-actions swal-actions-two",
        confirmButton: "swal-custom-confirm swal-confirm-danger",
        cancelButton: "swal-custom-cancel",
        icon: "swal-custom-icon swal-icon-warning",
      },
    });
    return result.isConfirmed;
  },

  success(title, text) {
    return Swal.fire({
      title: title,
      text: text,
      icon: "success",
      confirmButtonText: "Tamam",
      showCancelButton: false,
      buttonsStyling: false,
      width: 320,
      padding: 0,
      customClass: {
        popup: "swal-custom-popup",
        title: "swal-custom-title",
        htmlContainer: "swal-custom-content",
        actions: "swal-custom-actions",
        confirmButton:
          "swal-custom-confirm swal-confirm-primary swal-confirm-full",
        icon: "swal-custom-icon swal-icon-success",
      },
    });
  },

  error(title, text) {
    return Swal.fire({
      title: title,
      text: text,
      icon: "error",
      confirmButtonText: "Tamam",
      showCancelButton: false,
      buttonsStyling: false,
      width: 320,
      padding: 0,
      customClass: {
        popup: "swal-custom-popup",
        title: "swal-custom-title",
        htmlContainer: "swal-custom-content",
        actions: "swal-custom-actions",
        confirmButton:
          "swal-custom-confirm swal-confirm-danger swal-confirm-full",
        icon: "swal-custom-icon swal-icon-error",
      },
    });
  },

  warning(title, text) {
    return Swal.fire({
      title: title,
      text: text,
      icon: "warning",
      confirmButtonText: "Tamam",
      showCancelButton: false,
      buttonsStyling: false,
      width: 320,
      padding: 0,
      customClass: {
        popup: "swal-custom-popup",
        title: "swal-custom-title",
        htmlContainer: "swal-custom-content",
        actions: "swal-custom-actions",
        confirmButton:
          "swal-custom-confirm swal-confirm-warning swal-confirm-full",
        icon: "swal-custom-icon swal-icon-warning",
      },
    });
  },
};

// ===== Modal Functions =====
const Modal = {
  open(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.add("active");
      document.body.style.overflow = "hidden";
    }
  },

  close(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
      modal.classList.remove("active");
      document.body.style.overflow = "";
    }
  },

  closeAll() {
    document.querySelectorAll(".modal-overlay").forEach((modal) => {
      modal.classList.remove("active");
    });
    document.body.style.overflow = "";
  },
};

// ===== API Helper =====
const API = {
  baseUrl: "api.php",

  async request(action, data = {}) {
    try {
      Loading.show();

      const formData = new FormData();
      formData.append("action", action);

      for (const [key, value] of Object.entries(data)) {
        formData.append(key, value);
      }

      const response = await fetch(this.baseUrl, {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      return result;
    } catch (error) {
      console.error("API Error:", error);
      Toast.show("Bir hata oluştu", "error");
      return { success: false, error: error.message };
    } finally {
      Loading.hide();
    }
  },
};

// ===== Form Helpers =====
const Form = {
  serialize(formElement) {
    const formData = new FormData(formElement);
    const data = {};

    for (const [key, value] of formData.entries()) {
      data[key] = value;
    }

    return data;
  },

  reset(formElement) {
    formElement.reset();
  },

  validate(formElement) {
    const requiredFields = formElement.querySelectorAll("[required]");
    let isValid = true;

    requiredFields.forEach((field) => {
      if (!field.value.trim()) {
        isValid = false;
        field.classList.add("border-red-500");
      } else {
        field.classList.remove("border-red-500");
      }
    });

    return isValid;
  },
};

// ===== Number Formatting =====
const Format = {
  currency(amount) {
    return new Intl.NumberFormat("tr-TR", {
      style: "currency",
      currency: "TRY",
      minimumFractionDigits: 2,
    }).format(amount);
  },

  number(num) {
    return new Intl.NumberFormat("tr-TR").format(num);
  },

  date(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat("tr-TR", {
      day: "2-digit",
      month: "long",
      year: "numeric",
    }).format(date);
  },

  dateShort(dateString) {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat("tr-TR", {
      day: "2-digit",
      month: "short",
      year: "numeric",
    }).format(date);
  },

  relativeTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDay = Math.floor(diffHour / 24);

    if (diffSec < 60) return "Az önce";
    if (diffMin < 60) return `${diffMin} dk önce`;
    if (diffHour < 24) return `${diffHour} saat önce`;
    if (diffDay < 7) return `${diffDay} gün önce`;

    return this.dateShort(dateString);
  },
};

// ===== Push Notifications =====
const Push = {
  publicKey: null,
  subscription: null,

  init: async () => {
    if (!window.isSecureContext) {
      console.error("Push messaging requires a secure context (HTTPS).");
      Toast.show("Bildirimler için HTTPS bağlantısı gereklidir!", "error");
      return;
    }

    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
      console.log("Push messaging is not supported");
      return;
    }

    // Service Worker'ın hazır olmasını bekle
    const registration = await navigator.serviceWorker.ready;

    // Mevcut aboneliği kontrol et
    Push.subscription = await registration.pushManager.getSubscription();

    if (Push.subscription) {
      console.log("User is already subscribed:", Push.subscription);
      // Sunucuyla senkronize et (opsiyonel)
      Push.sendSubscriptionToBackEnd(Push.subscription);
    }
  },

  // VAPID key'i sunucudan al
  getVapidKey: async () => {
    if (Push.publicKey) return Push.publicKey;

    try {
      const response = await API.request("get-vapid-key");
      if (response.success) {
        Push.publicKey = response.data.publicKey;
        return Push.publicKey;
      }
    } catch (error) {
      console.error("Failed to get VAPID key:", error);
    }
    return null;
  },

  // Kullanıcıyı abone yap
  subscribe: async () => {
    try {
      const vapidKey = await Push.getVapidKey();
      console.log("VAPID Key from server:", vapidKey);
      console.log("VAPID Key length:", vapidKey ? vapidKey.length : 0);

      if (!vapidKey) {
        Toast.show("Bildirim anahtarı alınamadı", "error");
        return false;
      }

      const registration = await navigator.serviceWorker.ready;

      if (!registration.active) {
        console.log("Service Worker not active yet, waiting...");
        await new Promise((resolve) => {
          const checkActive = () => {
            if (registration.active) {
              resolve();
            } else {
              setTimeout(checkActive, 100);
            }
          };
          checkActive();
        });
      }

      console.log("Service Worker is active:", registration);
      const convertedVapidKey = Push.urlBase64ToUint8Array(vapidKey);
      console.log("Converted VAPID Key:", convertedVapidKey);
      console.log("Converted Key length:", convertedVapidKey.length);

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: convertedVapidKey,
      });

      Push.subscription = subscription;
      await Push.sendSubscriptionToBackEnd(subscription);

      Toast.show("Bildirimler başarıyla açıldı", "success");
      return true;
    } catch (error) {
      console.error("Failed to subscribe the user: ", error);
      if (Notification.permission === "denied") {
        Toast.show(
          "Bildirim izni reddedildi. Tarayıcı ayarlarından izin verin.",
          "error",
        );
      } else {
        Toast.show("Bildirim aboneliği başarısız oldu", "error");
      }
      return false;
    }
  },

  // Aboneliği sunucuya gönder
  sendSubscriptionToBackEnd: async (subscription) => {
    try {
      await API.request("save-subscription", {
        subscription: JSON.stringify(subscription),
      });
    } catch (error) {
      console.error("Failed to send subscription to backend:", error);
    }
  },

  // Helper function
  urlBase64ToUint8Array: (base64String) => {
    const padding = "=".repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
      .replace(/-/g, "+")
      .replace(/_/g, "/");

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
      outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
  },
};

// ===== Initialize App =====
document.addEventListener("DOMContentLoaded", () => {
  App.init();
  Toast.init();
});

// Handle initial load completion
const hideLoader = () => {
  setTimeout(() => {
    Loading.hide();
  }, 300);
};

if (document.readyState === "complete") {
  hideLoader();
} else {
  window.addEventListener("load", hideLoader);
}

// Safety fallback - force hide after 5 seconds
setTimeout(() => {
  if (Loading.count > 0) {
    console.warn("Forcing loader hide after timeout");
    Loading.count = 0;
    Loading.updateState();
  }
}, 5000);

// ===== Click handlers for modal close on overlay =====
document.addEventListener("click", (e) => {
  if (e.target.classList.contains("modal-overlay")) {
    Modal.closeAll();
  }
});

// ===== Back button handling for Android =====
window.addEventListener("popstate", (e) => {
  const openModal = document.querySelector(".modal-overlay.active");
  if (openModal) {
    e.preventDefault();
    Modal.closeAll();
  }
});
