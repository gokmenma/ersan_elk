$(document).ready(function () {
  // DataTable başlatma
  $("#logTable").DataTable({
    language: {
      url: "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json",
    },
    order: [[0, "desc"]], // ID'ye göre azalan sıralama (en yeni en üstte)
    pageLength: 25,
  });

  // Detay butonuna tıklama işlemi
  $(document).on("click", ".view-details", function () {
    var btn = $(this);
    var type = btn.data("type");
    var sender = btn.data("sender");
    var recipients = btn.data("recipients");
    var subject = btn.data("subject");
    var message = btn.data("message");
    var attachments = btn.data("attachments");
    var date = btn.data("date");

    // Modal içeriğini doldur
    $("#modalDate").text(date);
    $("#modalDateSmall").text(date);
    $("#modalSender").text(sender);

    // Tip ikonunu ayarla
    var iconEl = $("#modalTypeIcon");
    iconEl.removeClass("bg-primary-soft bg-warning-soft bg-info-soft");
    if (type === "email") {
      iconEl
        .addClass("bg-primary-soft")
        .html('<i class="fas fa-envelope fa-lg"></i>');
    } else if (type === "sms") {
      iconEl
        .addClass("bg-warning-soft")
        .html('<i class="fas fa-sms fa-lg"></i>');
    } else if (type === "push") {
      iconEl.addClass("bg-info-soft").html('<i class="fas fa-bell fa-lg"></i>');
    } else {
      iconEl
        .addClass("bg-primary-soft")
        .html('<i class="fas fa-envelope fa-lg"></i>');
    }

    // Alıcıları listele (yeni badge tasarımı)
    var recipientsHtml = "";
    if (typeof recipients === "string") {
      try {
        var recipientsArr = JSON.parse(recipients);
        if (Array.isArray(recipientsArr)) {
          recipientsArr.forEach(function (r) {
            recipientsHtml +=
              '<span class="recipient-badge"><i class="fas fa-user"></i>' +
              escapeHtml(r) +
              "</span>";
          });
        } else {
          recipientsHtml =
            '<span class="recipient-badge"><i class="fas fa-user"></i>' +
            escapeHtml(recipients) +
            "</span>";
        }
      } catch (e) {
        recipientsHtml =
          '<span class="recipient-badge"><i class="fas fa-user"></i>' +
          escapeHtml(recipients) +
          "</span>";
      }
    } else if (Array.isArray(recipients)) {
      recipients.forEach(function (r) {
        recipientsHtml +=
          '<span class="recipient-badge"><i class="fas fa-user"></i>' +
          escapeHtml(r) +
          "</span>";
      });
    } else {
      recipientsHtml =
        '<span class="recipient-badge"><i class="fas fa-user"></i>' +
        escapeHtml(recipients) +
        "</span>";
    }
    $("#modalRecipients").html(recipientsHtml);

    // Tip kontrolü (Email vs SMS vs Push)
    if (type === "sms" || type === "push") {
      $("#modalSubjectRow").hide();
      $("#modalAttachmentsRow").hide();
      $("#modalMessage").text(message); // SMS/Push için düz metin
    } else {
      $("#modalSubjectRow").show();
      $("#modalSubject").text(subject);
      $("#modalMessage").html(message); // Email için HTML

      // Ekler
      var attachmentsHtml = "";
      if (attachments) {
        try {
          var attArr =
            typeof attachments === "string"
              ? JSON.parse(attachments)
              : attachments;
          if (Array.isArray(attArr) && attArr.length > 0) {
            attArr.forEach(function (att) {
              var name = att.name || att.path || "Dosya";
              attachmentsHtml +=
                '<span class="attachment-item"><i class="fas fa-file"></i>' +
                escapeHtml(name) +
                "</span>";
            });
            $("#modalAttachmentsRow").show();
          } else {
            $("#modalAttachmentsRow").hide();
          }
        } catch (e) {
          $("#modalAttachmentsRow").hide();
        }
      } else {
        $("#modalAttachmentsRow").hide();
      }
      $("#modalAttachments").html(attachmentsHtml);
    }

    // Modalı göster
    var myModal = new bootstrap.Modal(document.getElementById("detailModal"));
    myModal.show();
  });

  // HTML escape fonksiyonu
  function escapeHtml(text) {
    if (!text) return "";
    var div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
  }
});
