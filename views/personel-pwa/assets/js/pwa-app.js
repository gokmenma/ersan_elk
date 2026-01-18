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
      { passive: false }
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
              "backdrop-blur-sm"
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
  // Custom mixin for consistent styling
  mixin: null,

  init() {
    if (typeof Swal !== "undefined") {
      this.mixin = Swal.mixin({
        customClass: {
          confirmButton: "btn-primary px-6 py-2.5 rounded-xl font-medium ml-2",
          cancelButton:
            "bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 px-6 py-2.5 rounded-xl font-medium hover:bg-slate-200 dark:hover:bg-slate-700 transition-colors mr-2",
          popup: "rounded-2xl dark:bg-card-dark dark:text-white",
          title: "text-xl font-bold text-slate-900 dark:text-white",
          htmlContainer: "text-slate-600 dark:text-slate-400",
        },
        buttonsStyling: false,
        reverseButtons: true,
      });
    }
  },

  async confirm(title, text, confirmText = "Evet", cancelText = "İptal") {
    if (!this.mixin) this.init();

    const result = await this.mixin.fire({
      title: title,
      text: text,
      icon: "question",
      showCancelButton: true,
      confirmButtonText: confirmText,
      cancelButtonText: cancelText,
      iconColor: "#135bec",
    });
    return result.isConfirmed;
  },

  success(title, text) {
    if (!this.mixin) this.init();

    return this.mixin.fire({
      title: title,
      text: text,
      icon: "success",
      confirmButtonText: "Tamam",
      iconColor: "#10b981",
    });
  },

  error(title, text) {
    if (!this.mixin) this.init();

    return this.mixin.fire({
      title: title,
      text: text,
      icon: "error",
      confirmButtonText: "Tamam",
      iconColor: "#ef4444",
    });
  },

  warning(title, text) {
    if (!this.mixin) this.init();

    return this.mixin.fire({
      title: title,
      text: text,
      icon: "warning",
      confirmButtonText: "Tamam",
      iconColor: "#f59e0b",
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
