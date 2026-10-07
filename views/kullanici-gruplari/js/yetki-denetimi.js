$(document).ready(function () {
  var auditRows = [];
  var auditTable = null;
  $(".select2").select2({ width: "100%" });

  function syncSummaryToggle() {
    var hidden = document.documentElement.classList.contains("permission-audit-summary-hidden");
    var $button = $("#btnToggleSummaryCards");
    $button.attr("title", hidden ? "Özet kartlarını göster" : "Özet kartlarını gizle")
      .attr("aria-label", hidden ? "Özet kartlarını göster" : "Özet kartlarını gizle")
      .attr("aria-expanded", hidden ? "false" : "true")
      .find("i").attr("class", hidden ? "bx bx-chevron-down" : "bx bx-chevron-up");
  }
  syncSummaryToggle();

  $("#btnToggleSummaryCards").on("click", function () {
    var hidden = !document.documentElement.classList.contains("permission-audit-summary-hidden");
    document.documentElement.classList.toggle("permission-audit-summary-hidden", hidden);
    try { localStorage.setItem("permission_audit_summary_cards_state", hidden ? "hidden" : "visible"); } catch (e) {}
    syncSummaryToggle();
  });

  function esc(value) { return $("<div>").text(value == null ? "" : String(value)).html(); }
  function renderRows(filter) {
    var rows = auditRows.filter(function (row) {
      if (filter === "all") return true;
      if (filter === "accessible") return row.accessible;
      if (filter === "denied") return !row.accessible;
      return row.severity === filter;
    });
    var html = "";
    rows.forEach(function (row) {
      var sidebar = row.sidebar_visible ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Görünür</span>' : '<span class="badge bg-light text-secondary border rounded-pill">Gizli</span>';
      var access = row.route_accessible ? '<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Açık</span>' : '<span class="badge bg-light text-secondary border rounded-pill">Kapalı</span>';
      var findingClass = row.severity === "critical" ? "text-danger" : (row.severity === "warning" ? "text-warning" : "text-muted");
      var policyClass = row.policy_status === "Tanımlı" ? "success" : (row.policy_status === "Eksik" ? "danger" : "secondary");
      var policy = '<span class="badge bg-' + policyClass + '-subtle text-' + policyClass + ' border border-' + policyClass + '-subtle rounded-pill">' + esc(row.policy_status || "-") + '</span>';
      html += '<tr><td><div class="fw-semibold text-dark">' + esc(row.menu_name || "-") + '</div><code class="font-size-11">' + esc(row.menu_link || "Üst menü") + '</code></td><td>' + esc(row.group_name || "-") + '</td><td class="audit-code"><code>' + esc(row.permission_codes || "Eşleşme yok") + '</code></td><td>' + esc(row.source_roles || "-") + '</td><td>' + policy + '</td><td class="text-center">' + sidebar + '</td><td class="text-center">' + (row.menu_link ? access : "—") + '</td><td class="' + findingClass + ' fw-semibold font-size-11">' + esc(row.finding) + '</td></tr>';
    });
    if (auditTable) { auditTable.destroy(); auditTable = null; }
    $("#auditTableBody").html(html || '<tr><td colspan="8" class="text-center text-muted py-4">Bu filtrede kayıt bulunamadı.</td></tr>');
    if (rows.length && $.fn.DataTable && typeof getDatatableOptions === "function" && typeof applyLengthStateSave === "function") {
      auditTable = $("#permissionAuditTable").DataTable(applyLengthStateSave({
        ...getDatatableOptions(),
        pageLength: 25,
        order: [],
        columnDefs: [{ targets: [5, 6], className: "text-center" }]
      }));
    }
  }

  function renderAudit(data) {
    auditRows = data.menus || [];
    var stats = data.stats;
    $("#auditTotalCount").text(stats.total);
    $("#auditAccessibleCount").text(stats.accessible);
    $("#auditDeniedCount").text(stats.denied);
    $("#auditFindingCount").text(stats.critical + stats.warning);
    $("#auditCriticalCount").text(stats.critical + " kritik");
    $("#auditWarningCount").text(stats.warning + " uyarı");
    $("#auditSubjectTitle").text(data.subject.name + " — Yetki Denetimi");
    var migration = data.migration || {};
    var migrationText = "Politika: " + (migration.policy_ready ? "hazır" : "SQL bekliyor") +
      " · Rol tablosu: " + (migration.assignment_ready ? "hazır" : "SQL bekliyor") +
      (migration.assignment_ready ? " · Rol kaynakları: " + (migration.role_sources_match ? "uyumlu" : "UYUMSUZ") : "");
    $("#auditRoleSummary").text("Etkin roller: " + data.roles.map(function (r) { return r.role_name; }).join(", ") + " · " + migrationText);
    $("#permissionAuditEmpty").addClass("d-none");
    $("#permissionAuditResults").removeClass("d-none");
    $("#auditFilters button").removeClass("active");
    $("#auditFilters button[data-filter='all']").addClass("active");
    renderRows("all");
  }

  $("#audit_type").on("change", function () {
    var roleMode = $(this).val() === "role";
    $("#auditRoleWrap").toggleClass("d-none", !roleMode);
    $("#auditUserWrap").toggleClass("d-none", roleMode);
  });
  $("#auditFilters").on("click", "button", function () { $(this).siblings().removeClass("active"); $(this).addClass("active"); renderRows($(this).data("filter")); });
  $(document).on("click", ".audit-quick-filter", function () {
    var filter = $(this).data("filter");
    $("#auditFilters button").removeClass("active");
    $("#auditFilters button[data-filter='" + filter + "']").addClass("active");
    renderRows(filter);
    document.getElementById("permissionAuditListCard")?.scrollIntoView({ behavior: "smooth", block: "start" });
  });

  $("#btnRunPermissionAudit").on("click", function () {
    var type = $("#audit_type").val();
    var subjectId = type === "role" ? $("#audit_role").val() : $("#audit_user").val();
    if (!subjectId) { Swal.fire("Uyarı", "Lütfen denetlenecek kullanıcı veya yetki grubunu seçin.", "warning"); return; }
    var $btn = $(this), original = $btn.html();
    $btn.prop("disabled", true).html('<span class="spinner-border spinner-border-sm me-1"></span>Denetleniyor...');
    $.ajax({ url: "views/kullanici-gruplari/api.php", type: "POST", dataType: "json", data: { action: "runPermissionAudit", type: type, subject_id: subjectId, csrf_token: window.permissionAuditCsrf || "" },
      success: function (res) { if (res.status === "success") renderAudit(res.data); else Swal.fire("Hata", res.message, "error"); },
      error: function (xhr) {
        console.error("Yetki denetimi API hatası", xhr.status, xhr.responseText);
        var message = xhr.responseJSON?.message || ("Denetim çalıştırılamadı. HTTP " + (xhr.status || "bağlantı hatası"));
        Swal.fire("Hata", message, "error");
      },
      complete: function () { $btn.prop("disabled", false).html(original); }
    });
  });
});
