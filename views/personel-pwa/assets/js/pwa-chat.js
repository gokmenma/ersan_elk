/**
 * PWA Live Chat - JavaScript
 * Personel tarafı canlı destek chat widget
 */

const LiveChat = {
  isOpen: false,
  konusmaId: null,
  lastMessageId: 0,
  lastUnreadCount: 0,
  pollInterval: null,
  unreadInterval: null,
  historyOpen: false,
  sending: false,
  POLL_INTERVAL: 3000, // 3 saniye

  /**
   * Bildirim sesi çal (Web Audio API)
   */
  playSound() {
    try {
      const AudioContext = window.AudioContext || window.webkitAudioContext;
      if (!AudioContext) return;

      const ctx = new AudioContext();
      const osc = ctx.createOscillator();
      const gainNode = ctx.createGain();

      osc.connect(gainNode);
      gainNode.connect(ctx.destination);

      osc.type = "sine";
      osc.frequency.setValueAtTime(800, ctx.currentTime);
      osc.frequency.exponentialRampToValueAtTime(1200, ctx.currentTime + 0.1);

      gainNode.gain.setValueAtTime(0.3, ctx.currentTime);
      gainNode.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.1);

      osc.start(ctx.currentTime);
      osc.stop(ctx.currentTime + 0.1);
    } catch (e) {
      console.error("Sound play error:", e);
    }
  },

  /**
   * Yönetici durumunu kontrol et
   */
  async checkAdminStatus() {
    try {
      const response = await this.apiRequestSilent("get-admin-status");
      if (response.success && response.data?.status) {
        const statusLabels = {
          cevrimici: "Çevrimiçi",
          mesgul: "Meşgul",
          cevrimdisi: "Çevrimdışı",
        };
        const statusColors = {
          cevrimici: "#22c55e",
          mesgul: "#f59e0b",
          cevrimdisi: "#94a3b8",
        };
        const st = response.data.status;

        // Header status text
        const statusEl = document.getElementById("chat-status");
        if (statusEl) statusEl.textContent = statusLabels[st] || "Çevrimiçi";

        // Online dot
        const dot = document.querySelector(".chat-online-dot");
        if (dot) dot.style.background = statusColors[st] || "#22c55e";
      }
    } catch (e) {}
  },

  /**
   * Chat FAB toggle
   */
  toggle() {
    if (this.isOpen) {
      this.close();
    } else {
      this.open();
    }
  },

  /**
   * Chat aç
   */
  async open() {
    this.isOpen = true;
    const fab = document.getElementById("chat-fab");
    const overlay = document.getElementById("chat-overlay");

    fab.classList.add("active");
    overlay.classList.add("active");

    // Unread polling durdur
    this.stopUnreadPolling();

    // Badge gizle
    const badge = document.getElementById("chat-fab-badge");
    if (badge) badge.style.display = "none";

    // History panelini kapat, aktif view'ı aç
    if (this.historyOpen) {
      this.historyOpen = false;
      document.getElementById("chat-history-panel").style.display = "none";
      document.getElementById("chat-active-view").style.display = "flex";
    }

    // Chat başlat veya devam et
    await this.checkExistingChat();

    // Yönetici durumunu kontrol et
    this.checkAdminStatus();
  },

  /**
   * Chat kapat
   */
  close() {
    this.isOpen = false;
    const fab = document.getElementById("chat-fab");
    const overlay = document.getElementById("chat-overlay");

    fab.classList.remove("active");
    overlay.classList.remove("active");

    // Polling durdur
    this.stopPolling();

    // Unread polling başlat
    this.startUnreadPolling();
  },

  /**
   * Konuşma geçmişi toggle
   */
  async toggleHistory() {
    const historyPanel = document.getElementById("chat-history-panel");
    const activeView = document.getElementById("chat-active-view");

    if (this.historyOpen) {
      // Geçmişten aktif chat'e dön
      this.historyOpen = false;
      historyPanel.style.display = "none";
      activeView.style.display = "flex";

      // Eğer eski bir konuşma görüntüleniyorsa aktif konuşmaya geri dön
      this.konusmaId = null;
      this.lastMessageId = 0;
      await this.startChat();
    } else {
      // Geçmişi göster
      this.historyOpen = true;
      this.stopPolling();
      activeView.style.display = "none";
      historyPanel.style.display = "flex";
      await this.loadHistory();
    }
  },

  /**
   * Konuşma geçmişini yükle
   */
  async loadHistory() {
    const list = document.getElementById("chat-history-list");
    if (!list) return;

    list.innerHTML =
      '<div style="text-align:center; padding:20px; color:#94a3b8;"><div class="spinner-border spinner-border-sm"></div></div>';

    try {
      const response = await this.apiRequest("get-chat-history");
      if (response.success && response.data?.conversations) {
        const convs = response.data.conversations;

        if (convs.length === 0) {
          list.innerHTML = `
            <div style="text-align:center; padding:40px 20px; color:#94a3b8;">
              <span class="material-symbols-outlined" style="font-size:40px; opacity:.5;">forum</span>
              <p style="margin-top:8px; font-size:13px;">Henüz konuşma geçmişi yok</p>
            </div>`;
          return;
        }

        const statusLabels = {
          acik: "Açık",
          beklemede: "Beklemede",
          cozuldu: "Çözüldü",
          kapali: "Kapalı",
        };
        const statusColors = {
          acik: "#16a34a",
          beklemede: "#d97706",
          cozuldu: "#2563eb",
          kapali: "#94a3b8",
        };

        let html = "";
        convs.forEach((conv) => {
          const statusLabel = statusLabels[conv.durum] || conv.durum;
          const statusColor = statusColors[conv.durum] || "#94a3b8";
          const time = conv.son_mesaj_zamani
            ? this.formatTime(conv.son_mesaj_zamani)
            : "";
          const preview = conv.son_mesaj_onizleme || "Konuşma";
          const msgCount = conv.mesaj_sayisi || 0;

          html += `
          <div onclick="LiveChat.loadOldConversation(${conv.id}, '${conv.durum}')"
               style="display:flex; align-items:center; gap:10px; padding:12px; border-radius:10px; cursor:pointer; margin-bottom:4px; border:1px solid #f1f5f9; transition:background .15s;"
               onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
            <div style="width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg, #6366f1, #8b5cf6); color:#fff; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
              <span class="material-symbols-outlined" style="font-size:18px;">chat</span>
            </div>
            <div style="flex:1; min-width:0;">
              <div style="display:flex; justify-content:space-between; align-items:center;">
                <span style="font-weight:600; font-size:13px; color:#1e293b;">${conv.konu || "Destek Talebi"}</span>
                <span style="font-size:11px; color:#94a3b8;">${time}</span>
              </div>
              <div style="font-size:12px; color:#64748b; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; margin-top:2px;">
                ${this.escapeHtml(preview)}
              </div>
              <div style="display:flex; gap:6px; align-items:center; margin-top:4px;">
                <span style="font-size:10px; padding:1px 6px; border-radius:8px; background:${statusColor}15; color:${statusColor}; font-weight:600;">${statusLabel}</span>
                <span style="font-size:10px; color:#94a3b8;">${msgCount} mesaj</span>
              </div>
            </div>
          </div>`;
        });

        list.innerHTML = html;
      }
    } catch (error) {
      console.error("Load history error:", error);
      list.innerHTML =
        '<p style="text-align:center; padding:20px; color:#94a3b8;">Geçmiş yüklenemedi</p>';
    }
  },

  /**
   * Eski konuşmayı görüntüle
   */
  async loadOldConversation(convId, durum) {
    // Geçmiş panelini kapat, aktif view'ı aç
    this.historyOpen = false;
    document.getElementById("chat-history-panel").style.display = "none";
    document.getElementById("chat-active-view").style.display = "flex";

    // Konuşma ID'sini ayarla
    this.konusmaId = convId;
    this.lastMessageId = 0;

    // Mesajları temizle
    const container = document.getElementById("chat-messages");
    const welcome = document.getElementById("chat-welcome");
    if (container) container.innerHTML = "";
    if (welcome) {
      welcome.style.display = "none";
      container.appendChild(welcome);
    }

    // Kapalı/çözülmüş konuşmalarda input'u pasif yap
    const inputArea = document.getElementById("chat-input-area");
    if (inputArea) {
      inputArea.innerHTML =
        '<div style="text-align:center; padding:10px;"><div class="spinner-border spinner-border-sm text-primary"></div></div>';
    }

    // Mesajları yükle
    try {
      const response = await this.apiRequest("get-chat-messages", {
        konusma_id: convId,
      });

      if (response.success && response.data) {
        if (response.data.messages) {
          this.renderMessages(response.data.messages);
        }
        if (response.data.opponent_last_read_id) {
          this.markMessagesAsReadUI(response.data.opponent_last_read_id);
        }

        if (inputArea) {
          if (durum === "cozuldu" || durum === "kapali") {
            this.disableInput(
              "Bu konuşma " + (durum === "cozuldu" ? "çözüldü" : "kapatıldı"),
              "lock",
            );
          } else if (response.data.is_working_hours === false) {
            this.disableInput(response.data.out_of_hours_message, "schedule");
          } else {
            this.restoreInput();
            this.showActionBar(true);
          }
        }
      }

      // Açık konuşmalar için polling başlat
      if (durum === "acik" || durum === "beklemede") {
        this.startPolling();
      } else {
        this.stopPolling();
      }
    } catch (error) {
      console.error("Load old conversation error:", error);
    }
  },

  /**
   * Mevcut aktif konuşmayı kontrol et (yeni oluşturma)
   */
  async checkExistingChat() {
    try {
      const response = await this.apiRequest("check-chat");

      if (response.success && response.data) {
        if (response.data.has_conversation) {
          // Mevcut konuşma var - yükle
          this.konusmaId = response.data.konusma_id;

          const container = document.getElementById("chat-messages");
          const welcome = document.getElementById("chat-welcome");

          if (response.data.messages && response.data.messages.length > 0) {
            if (welcome) welcome.style.display = "none";
            container.innerHTML = "";
            if (welcome) container.appendChild(welcome);
            this.renderMessages(response.data.messages);
          }

          if (response.data.opponent_last_read_id) {
            this.markMessagesAsReadUI(response.data.opponent_last_read_id);
          }

          // Input yetkilerini düzenle
          if (response.data.is_working_hours === false) {
            this.disableInput(response.data.out_of_hours_message, "schedule");
          } else {
            this.restoreInput();
            this.showActionBar(true); // Action bar göster (aktif konuşma var)
          }

          // Polling başlat
          this.startPolling();
        } else {
          // Mevcut konuşma yok - welcome ekranı göster
          this.konusmaId = null;
          const container = document.getElementById("chat-messages");
          const welcome = document.getElementById("chat-welcome");
          if (welcome) welcome.style.display = "flex";
          container.innerHTML = "";
          if (welcome) container.appendChild(welcome);

          // Input aktif, action bar gizli
          if (response.data.is_working_hours === false) {
            this.disableInput(response.data.out_of_hours_message, "schedule");
          } else {
            this.restoreInput();
            this.showActionBar(false);
          }
        }

        // Input'a focus
        setTimeout(() => {
          document.getElementById("chat-input")?.focus();
        }, 400);
      }
    } catch (error) {
      console.error("Check chat error:", error);
    }
  },

  /**
   * Mesaj gönder
   */
  async send() {
    if (this.sending) return;
    const input = document.getElementById("chat-input");
    const sendBtn = document.getElementById("chat-send-btn");
    const mesaj = input?.value?.trim();

    if (!mesaj) return;

    this.sending = true;

    // Input temizle
    input.value = "";

    const optimisticId = "temp-" + Date.now();
    // Optimistic UI
    this.addMessageToUI({
      id: optimisticId,
      gonderen_tip: "personel",
      mesaj: mesaj,
      created_at: new Date().toISOString(),
      dosya_url: null,
    });

    // Welcome'ı gizle
    const welcome = document.getElementById("chat-welcome");
    if (welcome) welcome.style.display = "none";

    sendBtn.classList.add("loading");

    try {
      // Konuşma yoksa önce oluştur
      if (!this.konusmaId) {
        const startResponse = await this.apiRequest("start-chat", {
          konu: mesaj.substring(0, 30) + "...",
        });

        if (startResponse.success && startResponse.data) {
          this.konusmaId = startResponse.data.konusma_id;
          // Action bar göster
          this.showActionBar(true);
          // Polling başlat
          this.startPolling();
        } else {
          // Başarısız oldu, optimistic mesajı sil
          document.querySelector(`[data-msg-id="${optimisticId}"]`)?.remove();

          if (
            startResponse.data &&
            startResponse.data.is_working_hours === false
          ) {
            this.disableInput(
              startResponse.data.out_of_hours_message,
              "schedule",
            );
            return;
          }

          Toast.show("Konuşma başlatılamadı", "error");
          this.sending = false;
          sendBtn.classList.remove("loading");
          return;
        }
      }

      const response = await this.apiRequest("send-chat-message", {
        konusma_id: this.konusmaId,
        mesaj: mesaj,
      });

      if (response.success) {
        if (response.data && response.data.message_id) {
          const el = document.querySelector(
            `.chat-msg[data-msg-id="${optimisticId}"]`,
          );
          if (el) el.dataset.msgId = response.data.message_id;
        }

        // Polling'i hemen tetikle
        this.pollNewMessages();
      } else {
        // Mesaj gönderilemedi, optimistic mesajı sil
        document.querySelector(`[data-msg-id="${optimisticId}"]`)?.remove();

        if (response.data && response.data.is_working_hours === false) {
          this.disableInput(response.data.out_of_hours_message, "schedule");
        } else if (
          response.message &&
          response.message.includes("kapatılmış")
        ) {
          // Konuşma kapatılmış - input'u devre dışı bırak
          this.disableInput("Bu konuşma kapatıldı", "lock");
          Toast.show(response.message, "error");
        } else {
          Toast.show(response.message || "Mesaj gönderilemedi", "error");
        }
      }
    } catch (error) {
      console.error("Send message error:", error);
      document.querySelector(`[data-msg-id="${optimisticId}"]`)?.remove();
      Toast.show("Mesaj gönderilemedi", "error");
    } finally {
      sendBtn.classList.remove("loading");
      this.sending = false;
    }
  },

  /**
   * Resim gönder
   */
  openImagePicker() {
    document.getElementById("chat-image-input")?.click();
  },

  async sendImage(input) {
    const file = input.files?.[0];
    if (!file || !this.konusmaId) return;

    // Boyut kontrolü
    if (file.size > 5 * 1024 * 1024) {
      Toast.show("Dosya boyutu 5MB'dan büyük olamaz", "error");
      input.value = "";
      return;
    }

    // Tip kontrolü
    if (!file.type.startsWith("image/")) {
      Toast.show("Sadece resim dosyaları yüklenebilir", "error");
      input.value = "";
      return;
    }

    const sendBtn = document.getElementById("chat-send-btn");
    sendBtn.classList.add("loading");

    const optimisticId = "temp-" + Date.now();
    // Optimistic UI: Yerel önizleme
    const localUrl = URL.createObjectURL(file);
    this.addMessageToUI({
      id: optimisticId,
      gonderen_tip: "personel",
      mesaj: "📷 Resim",
      created_at: new Date().toISOString(),
      dosya_url: localUrl,
      dosya_tip: file.type,
    });

    try {
      const formData = new FormData();
      formData.append("action", "send-chat-image");
      formData.append("konusma_id", this.konusmaId);
      formData.append("image", file);

      const response = await fetch("api.php", {
        method: "POST",
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        if (result.data && result.data.message_id) {
          const el = document.querySelector(
            `.chat-msg[data-msg-id="${optimisticId}"]`,
          );
          if (el) el.dataset.msgId = result.data.message_id;
        }

        this.pollNewMessages();
      } else {
        document.querySelector(`[data-msg-id="${optimisticId}"]`)?.remove();
        if (result.data && result.data.is_working_hours === false) {
          this.disableInput(result.data.out_of_hours_message, "schedule");
        } else {
          Toast.show(result.message || "Resim gönderilemedi", "error");
        }
      }
    } catch (error) {
      console.error("Send image error:", error);
      document.querySelector(`[data-msg-id="${optimisticId}"]`)?.remove();
      Toast.show("Resim gönderilemedi", "error");
    } finally {
      sendBtn.classList.remove("loading");
      input.value = "";
    }
  },

  /**
   * Resim önizleme
   */
  showImagePreview(url) {
    const preview = document.getElementById("chat-image-preview");
    const img = document.getElementById("chat-image-preview-img");
    if (preview && img) {
      img.src = url;
      preview.classList.add("active");
    }
  },

  closeImagePreview() {
    const preview = document.getElementById("chat-image-preview");
    if (preview) {
      preview.classList.remove("active");
    }
  },

  /**
   * Mesajları render et
   */
  renderMessages(messages) {
    const container = document.getElementById("chat-messages");
    if (!container) return;

    messages.forEach((msg) => {
      // Duplicate kontrolü
      if (msg.id && container.querySelector(`[data-msg-id="${msg.id}"]`)) {
        return;
      }

      this.addMessageToUI(msg);
    });

    // Son mesaj ID'sini güncelle
    if (messages.length > 0) {
      const lastMsg = messages[messages.length - 1];
      if (lastMsg.id && lastMsg.id > this.lastMessageId) {
        this.lastMessageId = lastMsg.id;
      }
    }
  },

  /**
   * Mesajı UI'ye ekle
   */
  addMessageToUI(msg) {
    const container = document.getElementById("chat-messages");
    const welcome = document.getElementById("chat-welcome");
    if (!container) return;

    // Welcome mesajını gizle
    if (welcome) welcome.style.display = "none";

    let type = "incoming";
    if (msg.gonderen_tip === "personel") type = "outgoing";
    else if (msg.gonderen_tip === "sistem") type = "system";

    const msgEl = document.createElement("div");
    msgEl.className = `chat-msg ${type}`;
    if (msg.id) msgEl.dataset.msgId = msg.id;

    let content = "";

    // Resim varsa
    if (msg.dosya_url && msg.dosya_tip?.startsWith("image/")) {
      content = `
        <div class="chat-bubble">
          <img src="${msg.dosya_url}" class="chat-msg-image" 
               onclick="LiveChat.showImagePreview('${msg.dosya_url}')" 
               alt="Resim" loading="lazy">
          ${msg.mesaj && msg.mesaj !== "📷 Resim" ? `<div style="margin-top:6px">${this.escapeHtml(msg.mesaj)}</div>` : ""}
        </div>
      `;
    } else {
      content = `<div class="chat-bubble">${this.escapeHtml(msg.mesaj)}</div>`;
    }

    // Zaman
    if (msg.created_at) {
      const time = this.formatTime(msg.created_at);
      let readIcon = "";
      if (type === "outgoing") {
        if (msg.okundu == 1) {
          readIcon = `<span class="material-symbols-outlined chat-read-icon done-all">done_all</span>`;
        } else {
          readIcon = `<span class="material-symbols-outlined chat-read-icon">done</span>`;
        }
      }
      content += `<div style="display:flex; justify-content:flex-end; align-items:center; gap:2px; margin-top:2px;">
                    <span class="chat-msg-time" style="margin-top:0;">${time}</span>
                    ${readIcon}
                  </div>`;
    }

    msgEl.innerHTML = content;
    container.appendChild(msgEl);

    // ID güncelle
    if (msg.id && msg.id > this.lastMessageId) {
      this.lastMessageId = msg.id;
    }

    // Scroll aşağı
    this.scrollToBottom();
  },

  /**
   * PWA'da okunan giden mesajların ikonlarını günceller
   */
  markMessagesAsReadUI(lastReadId) {
    const container = document.getElementById("chat-messages");
    if (!container) return;
    const msgs = container.querySelectorAll(".chat-msg.outgoing");
    msgs.forEach((msgEl) => {
      const id = parseInt(msgEl.dataset.msgId || "0");
      if (id > 0 && id <= lastReadId) {
        const icon = msgEl.querySelector(".chat-read-icon");
        if (icon && !icon.classList.contains("done-all")) {
          icon.textContent = "done_all";
          icon.classList.add("done-all");
        }
      }
    });
  },

  /**
   * Polling başlat
   */
  startPolling() {
    this.stopPolling();
    this.pollInterval = setInterval(() => {
      this.pollNewMessages();
    }, this.POLL_INTERVAL);
  },

  /**
   * Polling durdur
   */
  stopPolling() {
    if (this.pollInterval) {
      clearInterval(this.pollInterval);
      this.pollInterval = null;
    }
  },

  /**
   * Yeni mesaj kontrolü
   */
  async pollNewMessages() {
    if (!this.konusmaId || !this.isOpen) return;

    try {
      const response = await this.apiRequestSilent("poll-chat", {
        konusma_id: this.konusmaId,
        after_id: this.lastMessageId,
      });

      if (response.success && response.data) {
        if (response.data.opponent_last_read_id) {
          this.markMessagesAsReadUI(response.data.opponent_last_read_id);
        }

        if (response.data.messages?.length > 0) {
          const newMessages = response.data.messages.filter(
            (m) =>
              m.gonderen_tip !== "personel" &&
              !document.querySelector(`[data-msg-id="${m.id}"]`),
          );

          if (newMessages.length > 0) {
            this.playSound();
            this.renderMessages(newMessages);

            // Sistem mesajında kapatma/çözülme var mı kontrol et
            newMessages.forEach((m) => {
              if (m.gonderen_tip === "sistem" && m.mesaj) {
                const msg = m.mesaj.toLowerCase();
                if (
                  msg.includes("kapatıldı") ||
                  msg.includes("çözüldü") ||
                  msg.includes("kapatıldı")
                ) {
                  this.disableInput(
                    "Bu konuşma " +
                      (msg.includes("çözüldü") ? "çözüldü" : "kapatıldı"),
                    "lock",
                  );
                  this.stopPolling();
                }
              }
            });
          }
        }
      }
    } catch (error) {
      // Sessiz hata
    }
  },

  /**
   * Input alanını devre dışı bırak
   */
  disableInput(msgContent, icon = "lock") {
    const inputArea = document.getElementById("chat-input-area");
    if (inputArea) {
      inputArea.innerHTML = `
        <div style="text-align:center; padding:16px; font-size:13px; color:#64748b; background-color:#f8fafc; border-top:1px solid #e2e8f0; line-height:1.6;">
          <span class="material-symbols-outlined" style="font-size:24px; vertical-align:middle; margin-bottom:8px; display:block;">${icon}</span>
          ${msgContent || "Bu konuşma kapatıldı"}
        </div>`;
    }
    // Action bar'ı her zaman gizle
    this.showActionBar(false);
  },

  /**
   * Input alanını aktif yap
   */
  restoreInput() {
    const inputArea = document.getElementById("chat-input-area");
    if (!inputArea) return;
    // Input zaten aktifse dokunma
    if (inputArea.querySelector(".chat-input")) return;
    inputArea.innerHTML = `
      <div class="chat-input-wrapper">
        <button onclick="LiveChat.openImagePicker()" class="chat-attach-btn" aria-label="Resim Ekle">
          <span class="material-symbols-outlined">image</span>
        </button>
        <input type="text" id="chat-input" class="chat-input" placeholder="Mesajınızı yazın..."
          onkeypress="if(event.key==='Enter')LiveChat.send()" autocomplete="off">
        <button onclick="LiveChat.send()" class="chat-send-btn" id="chat-send-btn" aria-label="Gönder">
          <span class="material-symbols-outlined">send</span>
        </button>
      </div>
      <input type="file" id="chat-image-input" accept="image/*" style="display:none"
        onchange="LiveChat.sendImage(this)">`;
  },

  /**
   * Action bar göster/gizle
   */
  showActionBar(show) {
    const bar = document.getElementById("chat-action-bar");
    if (bar) {
      bar.style.display = show ? "block" : "none";
    }
  },

  /**
   * Konuşmayı çözüldü olarak işaretle
   */
  async resolveConversation() {
    if (!this.konusmaId) return;
    const confirmed = await Alert.confirm(
      "Konuşmayı Çözüldü İşaretle",
      "Bu konuşmayı çözüldü olarak işaretlemek istediğinize emin misiniz?",
      "Evet, Çözüldü",
      "Vazgeç",
    );
    if (!confirmed) return;

    try {
      const response = await this.apiRequest("update-chat-status", {
        konusma_id: this.konusmaId,
        durum: "cozuldu",
      });

      if (response.success) {
        this.disableInput(
          "Bu konuşma çözüldü olarak işaretlendi",
          "check_circle",
        );
        this.stopPolling();
        Toast.show("Konuşma çözüldü olarak işaretlendi", "success");
      } else {
        Toast.show(response.message || "İşlem başarısız", "error");
      }
    } catch (error) {
      console.error("Resolve error:", error);
      Toast.show("Bir hata oluştu", "error");
    }
  },

  /**
   * Konuşmayı kapat
   */
  async closeConversation() {
    if (!this.konusmaId) return;
    const confirmed = await Alert.confirm(
      "Konuşmayı Kapat",
      "Bu konuşmayı kapatmak istediğinize emin misiniz?",
      "Evet, Kapat",
      "Vazgeç",
    );
    if (!confirmed) return;

    try {
      const response = await this.apiRequest("update-chat-status", {
        konusma_id: this.konusmaId,
        durum: "kapali",
      });

      if (response.success) {
        this.disableInput("kapatıldı");
        this.stopPolling();
        Toast.show("Konuşma kapatıldı", "success");
      } else {
        Toast.show(response.message || "İşlem başarısız", "error");
      }
    } catch (error) {
      console.error("Close error:", error);
      Toast.show("Bir hata oluştu", "error");
    }
  },

  /**
   * Okunmamış polling başlat (chat kapalıyken)
   */
  startUnreadPolling() {
    this.stopUnreadPolling();
    this.checkUnread(); // Hemen kontrol et
    this.unreadInterval = setInterval(() => {
      this.checkUnread();
    }, 10000); // 10 saniye
  },

  stopUnreadPolling() {
    if (this.unreadInterval) {
      clearInterval(this.unreadInterval);
      this.unreadInterval = null;
    }
  },

  async checkUnread() {
    try {
      const response = await this.apiRequestSilent("get-chat-unread");
      if (response.success && response.data) {
        const count = parseInt(response.data.count) || 0;
        const badge = document.getElementById("chat-fab-badge");

        if (count > this.lastUnreadCount && count > 0) {
          this.playSound();
        }
        this.lastUnreadCount = count;

        if (badge) {
          if (count > 0) {
            badge.textContent = count;
            badge.style.display = "flex";
          } else {
            badge.style.display = "none";
          }
        }
      }
    } catch (error) {
      // Sessiz
    }
  },

  /**
   * API istekleri
   */
  async apiRequest(action, data = {}) {
    const formData = new FormData();
    formData.append("action", action);
    for (const [key, value] of Object.entries(data)) {
      formData.append(key, value);
    }

    const response = await fetch("api.php", {
      method: "POST",
      body: formData,
    });

    return await response.json();
  },

  async apiRequestSilent(action, data = {}) {
    const formData = new FormData();
    formData.append("action", action);
    for (const [key, value] of Object.entries(data)) {
      formData.append(key, value);
    }

    const response = await fetch("api.php", {
      method: "POST",
      body: formData,
    });

    return await response.json();
  },

  /**
   * Yardımcılar
   */
  scrollToBottom() {
    const container = document.getElementById("chat-messages");
    if (container) {
      setTimeout(() => {
        container.scrollTop = container.scrollHeight;
      }, 100);
    }
  },

  escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  },

  formatTime(dateString) {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMin = Math.floor(diffMs / 60000);

    if (diffMin < 1) return "Az önce";

    const hours = date.getHours().toString().padStart(2, "0");
    const minutes = date.getMinutes().toString().padStart(2, "0");

    // Bugün mü?
    if (date.toDateString() === now.toDateString()) {
      return `${hours}:${minutes}`;
    }

    // Dün mü?
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);
    if (date.toDateString() === yesterday.toDateString()) {
      return `Dün ${hours}:${minutes}`;
    }

    return `${date.getDate().toString().padStart(2, "0")}.${(date.getMonth() + 1).toString().padStart(2, "0")} ${hours}:${minutes}`;
  },
};

// Sayfa yüklendiğinde unread polling başlat
document.addEventListener("DOMContentLoaded", () => {
  setTimeout(() => {
    LiveChat.startUnreadPolling();
  }, 3000);
});

// Sayfa görünürlüğüne göre polling yönet
document.addEventListener("visibilitychange", () => {
  if (document.hidden) {
    LiveChat.stopPolling();
    LiveChat.stopUnreadPolling();
  } else {
    if (LiveChat.isOpen) {
      LiveChat.startPolling();
      LiveChat.pollNewMessages();
    } else {
      LiveChat.startUnreadPolling();
    }
  }
});
