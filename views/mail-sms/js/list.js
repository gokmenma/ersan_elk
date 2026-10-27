$(document).ready(function () {
  let currentType = "all";
  const API_URL = "views/mail-sms/api/log.php";

  const toggleBtn = $("#btnToggleSummaryCards");
  const updateToggleState = () => {
    const isHidden = $("html").hasClass("mesajlog-summary-hidden");
    toggleBtn.attr("aria-expanded", !isHidden);
    toggleBtn
      .find("i")
      .attr("class", isHidden ? "bx bx-chevron-down" : "bx bx-chevron-up");
  };
  updateToggleState();

  toggleBtn.on("click", function () {
    const willHide = !$("html").hasClass("mesajlog-summary-hidden");
    $("html").toggleClass("mesajlog-summary-hidden", willHide);
    try {
      localStorage.setItem(
        "mesajlog_summary_cards_state",
        willHide ? "hidden" : "visible",
      );
    } catch (e) {}
    updateToggleState();
  });

  function renderSummary(s) {
    const set = (key, total, fail) => {
      $("#stat_" + key).text(total || 0);
      $("#stat_sub_" + key).text(
        (total || 0) - (fail || 0) + " başarılı | " + (fail || 0) + " başarısız",
      );
    };
    set("toplam", s.toplam, s.basarisiz);
    set("email", s.email, s.email_hata);
    set("sms", s.sms, s.sms_hata);
    set("push", s.push, s.push_hata);
  }

  const table = $("#logTable").DataTable(
    applyLengthStateSave({
      ...getDatatableOptions(),
      processing: true,
      serverSide: true,
      ajax: {
        url: API_URL,
        type: "POST",
        data: function (d) {
          d.action = "log-ajax-list";
          d.type = currentType === "all" ? "" : currentType;
          d.start_date = $("#start_date").val();
          d.end_date = $("#end_date").val();
        },
        dataSrc: function (json) {
          if (json.summary) renderSummary(json.summary);
          return json.data;
        },
      },
      columns: [
        { data: "id", className: "text-center" },
        { data: "type", className: "text-center", orderable: true },
        { data: "sender" },
        { data: "recipients", orderable: true },
        { data: "subject", orderable: false },
        { data: "status", className: "text-center" },
        { data: "created_at" },
        {
          data: "actions",
          className: "text-center",
          orderable: false,
          searchable: false,
        },
      ],
      createdRow: function (row) {
        $(row)
          .find("td:not(:last-child)")
          .attr("style", "cursor: pointer !important");
      },
      order: [[0, "desc"]],
    }),
  );

  $(".status-quick-filter").on("click", function () {
    currentType = $(this).data("type") || "all";
    $(".status-quick-filter").removeClass("active");
    $(this).addClass("active");
    table.ajax.reload();
  });

  $("#start_date, #end_date").on("change", function () {
    table.ajax.reload();
  });

  $("#btnHeaderPrint").on("click", function () {
    window.print();
  });

  $("#logTable tbody").on("click", "tr td:not(:last-child)", function (e) {
    if ($(e.target).closest("a, button, .dropdown-menu").length > 0) return;
    $(this).closest("tr").find(".view-details").trigger("click");
  });

  function escapeHtml(text) {
    if (!text) return "";
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }

  const iconMap = {
    email: ["bg-primary-soft", "fa-envelope"],
    sms: ["bg-warning-soft", "fa-sms"],
    push: ["bg-info-soft", "fa-bell"],
  };

  $(document).on("click", ".view-details", function () {
    const id = $(this).data("id");
    $.post(API_URL, { action: "log-detay", id: id }, null, "json")
      .done(function (res) {
        if (res.status !== "success") {
          Swal.fire("Hata", res.message || "Kayıt getirilemedi.", "error");
          return;
        }
        showDetail(res.data);
      })
      .fail(function (xhr) {
        const msg =
          (xhr.responseJSON && xhr.responseJSON.message) ||
          "Kayıt getirilemedi.";
        Swal.fire("Hata", msg, "error");
      });
  });

  function showDetail(d) {
    const type = d.type;
    const [bgClass, icon] = iconMap[type] || iconMap.email;
    $("#modalDate, #modalDateSmall").text(d.date);
    $("#modalSender").text(d.sender || "");
    $("#modalTypeIcon")
      .removeClass("bg-primary-soft bg-warning-soft bg-info-soft")
      .addClass(bgClass)
      .html('<i class="fas ' + icon + ' fa-lg"></i>');

    let rHtml = "";
    (d.recipients || []).forEach(function (r) {
      rHtml +=
        '<span class="recipient-badge"><i class="fas fa-user"></i>' +
        escapeHtml(r) +
        "</span>";
    });
    $("#modalRecipients").html(rHtml);

    const isEmail = type === "email";
    $("#modalSubjectRow").toggle(isEmail);
    $("#modalSubject").text(d.subject || "");

    if (isEmail) {
      $("#modalMessageText").addClass("d-none").text("");
      $("#modalMessageFrame").removeClass("d-none").attr("srcdoc", d.message || "");
    } else {
      $("#modalMessageFrame").addClass("d-none").attr("srcdoc", "");
      $("#modalMessageText").removeClass("d-none").text(d.message || "");
    }

    let aHtml = "";
    (d.attachments || []).forEach(function (name) {
      aHtml +=
        '<span class="attachment-item"><i class="fas fa-file"></i>' +
        escapeHtml(name) +
        "</span>";
    });
    $("#modalAttachments").html(aHtml);
    $("#modalAttachmentsRow").toggle(isEmail && aHtml !== "");

    bootstrap.Modal.getOrCreateInstance(
      document.getElementById("detailModal"),
    ).show();
  }
});
