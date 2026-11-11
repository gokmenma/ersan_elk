(function () {
  const API_URL = "views/bildirim/api.php";
  const SUMMARY_KEY = "bildirim_summary_cards_state";
  const BASLIK_MAX = 50;
  const MESAJ_MAX = 178;
  const cfg = window.BILDIRIM_CFG || { aboneIds: [], aboneSayisi: 0 };
  const aboneSet = new Set(cfg.aboneIds);

  function esc(v) {
    return String(v === null || v === undefined ? "" : v)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  $(document).ready(function () {
    const $form = $("#formBildirimGonder");
    const $personel = $("#personel_ids");
    const $baslik = $("#baslik");
    const $mesaj = $("#mesaj");

    $baslik.attr("maxlength", BASLIK_MAX);
    $mesaj.attr("maxlength", MESAJ_MAX);

    const dtTable = $("#gecmisTable").DataTable(
      applyLengthStateSave({
        ...getDatatableOptions(),
        processing: true,
        serverSide: true,
        ajax: {
          url: API_URL,
          type: "POST",
          data: function (d) {
            d.action = "gonderim-gecmisi";
          },
        },
        columns: [
          { data: "id", orderable: true, searchable: false, render: (d) => '<div class="text-center text-muted font-size-12">' + esc(d) + "</div>" },
          { data: "type", orderable: false, searchable: false, render: () => '<span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2 py-1 font-size-11"><i class="bx bx-bell me-1"></i>Push</span>' },
          { data: "sender", render: (d) => '<span class="font-size-12">' + esc(d) + "</span>" },
          { data: "recipients", render: renderRecipients },
          { data: "subject", orderable: false, render: renderMesaj },
          { data: "status", render: renderStatus },
          { data: "created_at", render: renderTarih },
        ],
        order: [[0, "desc"]],
        pageLength: 10,
      }),
    );

    function renderRecipients(raw) {
      let list = [];
      try {
        const parsed = JSON.parse(raw);
        list = Array.isArray(parsed) ? parsed : [String(parsed)];
      } catch (e) {
        list = raw ? [String(raw)] : [];
      }
      if (!list.length) {
        return '<span class="text-muted">-</span>';
      }
      const first = list.slice(0, 2).map(esc).join(", ");
      const more = list.length > 2 ? ' <span class="badge bg-light text-dark border">+' + (list.length - 2) + "</span>" : "";
      return '<span class="font-size-12" title="' + esc(list.join(", ")) + '">' + first + more + "</span>";
    }

    function renderMesaj(data, type, row) {
      const baslik = data || "";
      const mesaj = row.message_preview || "";
      return (
        '<div class="d-flex flex-column"><span class="fw-semibold font-size-13">' +
        esc(baslik) +
        '</span><span class="text-muted font-size-11 text-truncate" style="max-width:360px;">' +
        esc(mesaj) +
        "</span></div>"
      );
    }

    function renderStatus(s) {
      const map = {
        success: ["success", "Başarılı"],
        partial: ["warning", "Kısmi"],
        failed: ["danger", "Başarısız"],
      };
      const m = map[s] || ["secondary", s || "-"];
      return '<div class="text-center"><span class="badge bg-' + m[0] + "-subtle text-" + m[0] + " border border-" + m[0] + '-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold">' + esc(m[1]) + "</span></div>";
    }

    function renderTarih(d) {
      if (!d) {
        return "";
      }
      const m = String(d).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/);
      return '<div class="text-center font-size-12">' + (m ? m[3] + "." + m[2] + "." + m[1] + " " + m[4] + ":" + m[5] : esc(d)) + "</div>";
    }

    const $toggle = $("#btnToggleSummaryCards");

    function setSummaryVisible(visible) {
      document.documentElement.classList.toggle("bildirim-summary-hidden", !visible);
      try {
        localStorage.setItem(SUMMARY_KEY, visible ? "visible" : "hidden");
      } catch (e) {}
      $toggle
        .attr("aria-expanded", visible ? "true" : "false")
        .attr("aria-label", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
        .attr("title", visible ? "Özet kartlarını gizle" : "Özet kartlarını göster")
        .html(visible ? '<i class="bx bx-chevron-up"></i>' : '<i class="bx bx-chevron-down"></i>');
    }

    if ($toggle.length) {
      const hidden = document.documentElement.classList.contains("bildirim-summary-hidden");
      $toggle.attr("aria-expanded", hidden ? "false" : "true");
      $toggle.html(hidden ? '<i class="bx bx-chevron-down"></i>' : '<i class="bx bx-chevron-up"></i>');
      $toggle.on("click", function () {
        setSummaryVisible(document.documentElement.classList.contains("bildirim-summary-hidden"));
      });
    }

    $("#btnHeaderPrint").on("click", function () {
      window.print();
    });

    function aliciTipi() {
      return $form.find('input[name="alici_tipi"]:checked').val();
    }

    function secilenler() {
      return $personel.val() || [];
    }

    function updateRecipientUi() {
      const toplu = aliciTipi() === "toplu";
      $("#personelSecimContainer").toggle(!toplu);

      const secili = secilenler();
      const abonesiz = secili.filter((id) => !aboneSet.has(String(id))).length;

      $("#secimOzeti").text(secili.length ? secili.length + " personel seçildi" : "Henüz personel seçilmedi");

      const $uyari = $("#aboneUyari");
      if (!toplu && abonesiz > 0) {
        $uyari
          .removeClass("d-none")
          .text(abonesiz + " kişinin bildirim aboneliği yok; bu kişilere bildirim ulaşmaz.");
      } else {
        $uyari.addClass("d-none");
      }

      const hedef = toplu ? cfg.aboneSayisi : secili.length - abonesiz;
      $("#gonderOzeti").text(
        toplu
          ? cfg.aboneSayisi + " abone personele gönderilecek"
          : secili.length
            ? hedef + " kişiye ulaşacak" + (abonesiz > 0 ? " (" + abonesiz + " abonelik yok)" : "")
            : "Alıcı seçilmedi",
      );
    }

    $form.on("change", 'input[name="alici_tipi"]', updateRecipientUi);
    $personel.on("change", updateRecipientUi);

    function pickSubscribers() {
      $("#aliciTekli").prop("checked", true);
      $personel.val(cfg.aboneIds).trigger("change");
      updateRecipientUi();
    }

    $("#btnPickSubscribers, #btnPickSubscribers2").on("click", pickSubscribers);

    $("#btnPickAll").on("click", function () {
      $("#aliciTekli").prop("checked", true);
      const all = $personel.find("option").map(function () {
        return this.value;
      }).get();
      $personel.val(all).trigger("change");
      updateRecipientUi();
    });

    $("#btnClearPick").on("click", function () {
      $personel.val(null).trigger("change");
      updateRecipientUi();
    });

    function updateCounter($input, $counter, max) {
      const len = ($input.val() || "").length;
      $counter.text(len + " / " + max).toggleClass("limit", len >= max);
    }

    function updatePreview() {
      $("#prevBaslik").text($baslik.val().trim() || "Bildirim başlığı");
      $("#prevMesaj").text($mesaj.val().trim() || "Mesajınız burada görünecek.");
      updateCounter($baslik, $("#baslikSayac"), BASLIK_MAX);
      updateCounter($mesaj, $("#mesajSayac"), MESAJ_MAX);
    }

    $baslik.on("input", updatePreview);
    $mesaj.on("input", updatePreview);

    $(".template-chip").on("click", function () {
      $baslik.val($(this).data("baslik"));
      $mesaj.val($(this).data("mesaj"));
      $("#hedef_sayfa").val($(this).data("hedef") || "").trigger("change");
      updatePreview();
      $mesaj.trigger("focus");
    });

    const $file = $("#resim");
    const $drop = $("#imageDrop");

    function clearImage() {
      $file.val("");
      $("#imagePreviewWrap").hide();
      $("#prevResim").hide().attr("src", "");
      $drop.show();
    }

    function showImage(file) {
      if (!file) {
        return clearImage();
      }
      if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) {
        Swal.fire("Uyarı", "Yalnızca JPG, PNG, GIF veya WebP görsel yükleyebilirsiniz.", "warning");
        return clearImage();
      }
      if (file.size > 2 * 1024 * 1024) {
        Swal.fire("Uyarı", "Görsel boyutu 2 MB'ı aşamaz.", "warning");
        return clearImage();
      }
      const reader = new FileReader();
      reader.onload = function (e) {
        $("#imagePreview").attr("src", e.target.result);
        $("#prevResim").attr("src", e.target.result).show();
        $("#imagePreviewWrap").show();
        $drop.hide();
      };
      reader.readAsDataURL(file);
    }

    $drop.on("click", function () {
      $file.trigger("click");
    });
    $drop.on("keydown", function (e) {
      if (e.key === "Enter" || e.key === " ") {
        e.preventDefault();
        $file.trigger("click");
      }
    });
    $drop.on("dragover", function (e) {
      e.preventDefault();
      $drop.addClass("dragover");
    });
    $drop.on("dragleave drop", function () {
      $drop.removeClass("dragover");
    });
    $drop.on("drop", function (e) {
      e.preventDefault();
      const files = e.originalEvent.dataTransfer.files;
      if (files && files[0]) {
        const dt = new DataTransfer();
        dt.items.add(files[0]);
        $file[0].files = dt.files;
        showImage(files[0]);
      }
    });
    $file.on("change", function () {
      showImage(this.files[0]);
    });
    $("#btnRemoveImage").on("click", clearImage);

    function resetAfterSend() {
      $form[0].reset();
      $personel.val(null).trigger("change");
      $("#hedef_sayfa").val("").trigger("change");
      clearImage();
      updatePreview();
      updateRecipientUi();
    }

    $form.on("submit", function (e) {
      e.preventDefault();

      const toplu = aliciTipi() === "toplu";
      const secili = secilenler();

      if (!$baslik.val().trim() || !$mesaj.val().trim()) {
        Swal.fire("Uyarı", "Başlık ve mesaj alanları zorunludur.", "warning");
        return;
      }
      if (!toplu && secili.length === 0) {
        Swal.fire("Uyarı", "Lütfen en az bir personel seçin.", "warning");
        return;
      }
      if (toplu && cfg.aboneSayisi === 0) {
        Swal.fire("Uyarı", "Henüz bildirim aboneliği olan personel yok.", "warning");
        return;
      }

      const hedefMetni = toplu ? "<b>" + cfg.aboneSayisi + "</b> abone personele" : "<b>" + secili.length + "</b> seçili personele";

      Swal.fire({
        title: "Bildirim gönderilsin mi?",
        html: hedefMetni + " gönderilecek.<br><small class='text-muted'>Gönderilen bildirim geri alınamaz.</small>",
        icon: "question",
        showCancelButton: true,
        confirmButtonText: "Evet, gönder",
        cancelButtonText: "Vazgeç",
      }).then(function (result) {
        if (!result.isConfirmed) {
          return;
        }

        const formData = new FormData($form[0]);
        formData.append("action", "send-notification");
        formData.delete("personel_ids[]");
        formData.delete("personel_ids");
        secili.forEach((id) => formData.append("personel_ids[]", id));

        const $btn = $("#btnGonder").prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin font-size-16"></i> Gönderiliyor...');

        fetch(API_URL, { method: "POST", body: formData })
          .then((res) => res.json())
          .then((data) => {
            if (data.status === "success") {
              Swal.fire({ icon: "success", title: "Gönderildi", text: data.message, timer: 2200, showConfirmButton: false });
              resetAfterSend();
              dtTable.ajax.reload(null, false);
              $("#kpi_bu_ay").text((parseInt($("#kpi_bu_ay").text(), 10) || 0) + 1);
              $("#kpi_toplam").text((parseInt($("#kpi_toplam").text(), 10) || 0) + 1);
            } else {
              Swal.fire("Hata", data.message, "error");
            }
          })
          .catch(() => Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error"))
          .finally(() => {
            $btn.prop("disabled", false).html('<i class="bx bx-send font-size-16"></i> Bildirimi Gönder');
          });
      });
    });

    updatePreview();
    updateRecipientUi();
  });
})();
