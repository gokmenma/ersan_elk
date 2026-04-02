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
    Navigation.init();
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

// ===== Theme Management =====
const Theme = {
  colors: {
    blue: {
      primary: "#135bec",
      dark: "#0d47c1",
      light: "#4a87f5",
      label: "Mavi",
    },
    purple: {
      primary: "#5156be",
      dark: "#414598",
      light: "#868ae0",
      label: "Mor",
    },
    green: {
      primary: "#059669",
      dark: "#047857",
      light: "#34d399",
      label: "Yeşil",
    },
    red: {
      primary: "#f46a6a",
      dark: "#c35555",
      light: "#f89a9a",
      label: "Kırmızı",
    },
    orange: {
      primary: "#f97316",
      dark: "#c75c12",
      light: "#fb9a5c",
      label: "Turuncu",
    },
    pink: {
      primary: "#db2777",
      dark: "#be185d",
      light: "#f472b6",
      label: "Pembe",
    },
    teal: {
      primary: "#0d9488",
      dark: "#0a766d",
      light: "#3dbbb1",
      label: "Teal",
    },
    cyan: {
      primary: "#06b6d4",
      dark: "#0592aa",
      light: "#38d1e9",
      label: "Cyan",
    },
    emerald: {
      primary: "#10b981",
      dark: "#0d9467",
      light: "#40d5a7",
      label: "Zümrüt",
    },
    rose: {
      primary: "#ec003f",
      dark: "#bc0032",
      light: "#f24d79",
      label: "Rose",
    },
    ersan: {
      primary: "#e2bd61",
      dark: "#b5974d",
      light: "#ebcc85",
      label: "Ersan",
    },
    slate: {
      primary: "#252526",
      dark: "#1e1e1f",
      light: "#4d4d4f",
      label: "Gri",
    },
  },

  current: localStorage.getItem("themeColor") || "blue",

  apply(themeName) {
    if (
      themeName !== "custom" &&
      (!this.colors[themeName] || themeName === this.current)
    )
      return;
    localStorage.setItem("themeColor", themeName);
    if (themeName !== "custom") {
      localStorage.removeItem("customThemeColor");
    }
    window.location.reload();
  },

  applyCustom(color) {
    localStorage.setItem("themeColor", "custom");
    localStorage.setItem("customThemeColor", color);
    this.current = "custom";

    // Real-time apply
    const r = document.documentElement;
    r.style.setProperty("--primary", color);
    r.style.setProperty("--primary-dark", color);
    r.style.setProperty("--primary-light", color);

    const hex = color.replace("#", "");
    const pr = parseInt(hex.substring(0, 2), 16),
      pg = parseInt(hex.substring(2, 4), 16),
      pb = parseInt(hex.substring(4, 6), 16);
    r.style.setProperty("--primary-rgb", `${pr}, ${pg}, ${pb}`);

    // Update meta theme-color
    const meta = document.querySelector('meta[name="theme-color"]');
    if (meta) meta.setAttribute("content", color);

    // Update UI active states without reload
    document.querySelectorAll(".theme-swatch").forEach((s) => {
      s.classList.remove("active");
      s.innerHTML = "";
    });
    const picker = document.querySelector(".color-picker-wrapper");
    if (picker) picker.classList.add("active");
  },

  renderSwatches(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;

    container.innerHTML = "";
    Object.entries(this.colors).forEach(([name, color]) => {
      const swatch = document.createElement("button");
      swatch.type = "button";
      swatch.className =
        "theme-swatch" + (name === this.current ? " active" : "");
      swatch.style.background = color.primary;
      swatch.setAttribute("aria-label", color.label);
      swatch.setAttribute("title", color.label);
      swatch.onclick = () => this.apply(name);

      if (name === this.current) {
        swatch.innerHTML =
          '<span class="material-symbols-outlined" style="font-size:14px;color:white;">check</span>';
      }

      container.appendChild(swatch);
    });

    // Add Custom Color Picker
    const pickerWrapper = document.createElement("div");
    pickerWrapper.className =
      "color-picker-wrapper" + (this.current === "custom" ? " active" : "");
    pickerWrapper.setAttribute("title", "Özel Renk Seç");

    const initialColor = localStorage.getItem("customThemeColor") || "#135bec";
    pickerWrapper.innerHTML = `
        <input type="color" value="${initialColor}" oninput="Theme.applyCustom(this.value)">
        <span class="material-symbols-outlined">palette</span>
    `;

    container.appendChild(pickerWrapper);
  },

  toggleDarkMode() {
    const r = document.documentElement;
    const isDark = r.classList.toggle("dark");
    localStorage.setItem("darkMode", isDark);
  },
};

// ===== Navigation Functions =====
const Navigation = {
  pages: [
    "ana-sayfa",
    "puantaj",
    "nobet",
    "talep",
    "etkinlikler",
    "profil",
    "bordro",
    "izin",
    "zimmetler",
  ],

  init() {
    this.setupSwipe();
  },

  setupSwipe() {
    let touchStartX = 0;
    let touchStartY = 0;
    let touchEndX = 0;
    let touchEndY = 0;
    let ignoreSwipe = false;

    const minSwipeDistance = 50;
    const maxVerticalDistance = 100;

    document.addEventListener(
      "touchstart",
      (e) => {
        // Slider vb. elementlerdeysen sayfa kaydırmasını iptal et
        if (
          e.target.closest("#etkinlik-slider-container") ||
          e.target.closest(".overflow-x-auto") ||
          e.target.closest(".swipe-ignore")
        ) {
          ignoreSwipe = true;
          return;
        }
        ignoreSwipe = false;
        touchStartX = e.changedTouches[0].clientX;
        touchStartY = e.changedTouches[0].clientY;
      },
      { passive: true },
    );

    document.addEventListener(
      "touchend",
      (e) => {
        if (ignoreSwipe) return;
        touchEndX = e.changedTouches[0].clientX;
        touchEndY = e.changedTouches[0].clientY;
        this.handleSwipe(
          touchStartX,
          touchStartY,
          touchEndX,
          touchEndY,
          minSwipeDistance,
          maxVerticalDistance,
        );
      },
      { passive: true },
    );
  },

  handleSwipe(
    startX,
    startY,
    endX,
    endY,
    minSwipeDistance,
    maxVerticalDistance,
  ) {
    const diffX = endX - startX;
    const diffY = endY - startY;

    // Check if vertical movement is too much (likely scrolling)
    if (Math.abs(diffY) > maxVerticalDistance) return;

    // Check if horizontal movement is enough
    if (Math.abs(diffX) < minSwipeDistance) return;

    // Get current page from URL
    const urlParams = new URLSearchParams(window.location.search);
    const currentPage = urlParams.get("page") || "ana-sayfa";

    const currentIndex = this.pages.indexOf(currentPage);
    if (currentIndex === -1) return; // Not a swipeable page

    if (diffX < 0) {
      // Swipe Left -> Next Page (Right)
      if (currentIndex < this.pages.length - 1) {
        this.navigateTo(this.pages[currentIndex + 1]);
      }
    } else {
      // Swipe Right -> Previous Page (Left)
      if (currentIndex > 0) {
        this.navigateTo(this.pages[currentIndex - 1]);
      }
    }
  },

  navigateTo(page) {
    // Add slide animation class to body before navigating?
    // For now just simple navigation
    window.location.href = `?page=${page}`;
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

  show(options) {
    return Swal.fire({
      title: options.title || "",
      html: options.content || "",
      icon: options.icon || null,
      confirmButtonText: options.confirmButtonText || "Tamam",
      showCancelButton: options.showCancelButton || false,
      cancelButtonText: options.cancelButtonText || "Vazgeç",
      buttonsStyling: false,
      width: options.width || 320,
      padding: 0,
      customClass: {
        popup: "swal-custom-popup",
        title: "swal-custom-title",
        htmlContainer: "swal-custom-content",
        actions: "swal-custom-actions",
        confirmButton:
          "swal-custom-confirm swal-confirm-primary swal-confirm-full",
        cancelButton: "swal-custom-cancel",
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
      // Toast.show("Bildirimler için HTTPS bağlantısı gereklidir!", "error");
      return;
    }

    if (!("serviceWorker" in navigator) || !("PushManager" in window)) {
      console.log("Push messaging is not supported");
      return;
    }

    // iOS check
    const isIOS =
      /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isStandalone =
      window.navigator.standalone ||
      window.matchMedia("(display-mode: standalone)").matches;

    // iOS and not standalone? Guide the user
    if (isIOS && !isStandalone) {
      console.log("iOS detected but not in standalone mode. Skipping push init.");
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
    } else {
      // Abonelik yok, izin durumunu kontrol et
      if (Notification.permission === "default") {
        // İzin sıfırlanmış veya henüz istenmemiş
        // Kullanıcıya sor
        setTimeout(async () => {
          const confirmed = await Alert.confirm(
            "Bildirim İzni",
            "Önemli gelişmelerden haberdar olmak için bildirimleri açmak ister misiniz?",
            "Evet, Aç",
            "Daha Sonra",
          );

          if (confirmed) {
            Push.subscribe();
          }
        }, 2000); // Uygulama açıldıktan biraz sonra sor
      } else if (Notification.permission === "granted") {
        // İzin var ama abonelik yok
        // Eğer kullanıcı manuel olarak kapatmışsa tekrar abone olma
        if (
          localStorage.getItem("notifications_manually_disabled") === "true"
        ) {
          console.log(
            "Notifications manually disabled by user, skipping auto-resubscribe.",
          );
          return;
        }

        // Muhtemelen silinmiş, tekrar abone ol
        console.log("Permission granted but no subscription, resubscribing...");
        Push.subscribe();
      }
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
      // Hardcoded VAPID Key for testing
      const vapidKey =
        "BGSV9o3jOcpLBMINUUEuw6Nesv7cj3wGjjlzQQcZ9b4qkSr6sQlNF7np44jlMNuqMuYKicmVrJK05yIPXx4lGP0";

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

      // Use cleaner helper for VAPID key
      const applicationServerKey = Push.urlBase64ToUint8Array(vapidKey);

      const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: applicationServerKey,
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

// ===== Global Utilities =====
window.escapeHtml = function (text) {
  if (!text) return "";
  const div = document.createElement("div");
  div.textContent = text;
  return div.innerHTML;
};
