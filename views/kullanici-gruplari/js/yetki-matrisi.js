$(document).ready(function () {
  var searchTimer = null;
  var activeRequest = null;
  var lastSearch = "";

  function escapeHtml(value) {
    return $("<div>").text(value == null ? "" : String(value)).html();
  }

  function roleButton(permission, role) {
    var enabled = Boolean(role.enabled);
    var stateClass = enabled ? "is-enabled" : "is-disabled";
    var icon = enabled ? "mdi-check-circle-outline" : "mdi-close-circle-outline";
    var label = enabled ? "Açık" : "Kapalı";
    var required = Boolean(permission.required);
    var disabled = !window.canManagePermissionGroups || (required && enabled);
    var title = required && enabled
      ? "Zorunlu yetki kapatılamaz"
      : (window.canManagePermissionGroups ? "Durumu değiştirmek için tıklayın" : "Yalnızca görüntüleme yetkiniz var");

    return '<button type="button" class="btn permission-role-toggle text-start px-3 py-2 ' + stateClass + '"' +
      ' data-role-id="' + escapeHtml(role.encrypted_id) + '"' +
      ' data-permission-id="' + escapeHtml(permission.encrypted_id) + '"' +
      ' data-enabled="' + (enabled ? "1" : "0") + '"' +
      (disabled ? ' disabled' : '') + ' title="' + escapeHtml(title) + '">' +
      '<span class="d-flex align-items-center justify-content-between gap-2">' +
      '<span class="text-truncate"><i class="mdi ' + icon + ' me-1 permission-state-icon"></i>' + escapeHtml(role.name) + '</span>' +
      '<span class="permission-state-label fw-semibold">' + label + '</span></span></button>';
  }

  function renderResults(permissions) {
    var $results = $("#permissionMatrixResults");
    $("#permissionMatrixSummary").text(permissions.length ? permissions.length + " yetki bulundu." : "Eşleşen aktif yetki bulunamadı.");

    if (!permissions.length) {
      $results.html('<div class="alert alert-light border text-center text-muted mb-0"><i class="mdi mdi-shield-off-outline me-1"></i>Eşleşen aktif yetki bulunamadı.</div>');
      return;
    }

    var html = "";
    permissions.forEach(function (permission) {
      html += '<div class="permission-result-card p-3 mb-3">';
      html += '<div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-3">';
      html += '<div><h6 class="font-size-14 fw-bold text-dark mb-1">' + escapeHtml(permission.name);
      if (permission.required) html += ' <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill font-size-10">Zorunlu</span>';
      html += '</h6><div class="text-muted font-size-11">' + escapeHtml(permission.group_name || "Grupsuz") + ' · <code>' + escapeHtml(permission.auth_name || "-") + '</code></div>';
      if (permission.description) html += '<p class="text-muted font-size-11 mb-0 mt-1">' + escapeHtml(permission.description) + '</p>';
      html += '</div></div><div class="d-flex flex-wrap gap-2">';
      permission.roles.forEach(function (role) { html += roleButton(permission, role); });
      html += '</div></div>';
    });
    $results.html(html);
  }

  function runSearch(search) {
    lastSearch = search;
    if (activeRequest) activeRequest.abort();
    $("#permissionMatrixSummary").text("Yetkiler aranıyor...");
    $("#permissionMatrixResults").html('<div class="text-center py-5 text-muted"><span class="spinner-border spinner-border-sm text-primary me-2"></span>Yetki grupları taranıyor...</div>');

    var request = $.ajax({
      url: "views/kullanici-gruplari/api.php",
      type: "POST",
      dataType: "json",
      data: { action: "searchPermissionRoles", search: search },
      success: function (res) {
        if (res.status === "success") renderResults(res.data || []);
        else $("#permissionMatrixResults").html('<div class="alert alert-danger mb-0">' + escapeHtml(res.message || "Arama yapılamadı.") + '</div>');
      },
      error: function (xhr, status) {
        if (status !== "abort") $("#permissionMatrixResults").html('<div class="alert alert-danger mb-0">Yetki araması sırasında bir sunucu hatası oluştu.</div>');
      },
      complete: function () {
        if (activeRequest === request) activeRequest = null;
      }
    });
    activeRequest = request;
  }

  $("#permissionMatrixSearch").on("input", function () {
    var search = $(this).val().trim();
    clearTimeout(searchTimer);
    $("#permissionMatrixClear").toggleClass("d-none", !search);
    if (search.length < 2) {
      if (activeRequest) activeRequest.abort();
      $("#permissionMatrixSummary").text("Aramak için en az 2 karakter yazın.");
      $("#permissionMatrixResults").html('<div class="text-center text-muted py-5"><i class="mdi mdi-text-search font-size-36 d-block mb-2 text-primary"></i>Aradığınız sayfa veya işlem yetkisini yazın.</div>');
      return;
    }
    searchTimer = setTimeout(function () { runSearch(search); }, 300);
  });

  $("#permissionMatrixClear").on("click", function () {
    $("#permissionMatrixSearch").val("").trigger("input").focus();
  });

  $(document).on("click", ".permission-role-toggle", function () {
    if (!window.canManagePermissionGroups) return;
    var $button = $(this);
    var newEnabled = $button.attr("data-enabled") !== "1";
    $button.addClass("is-loading").prop("disabled", true);

    $.ajax({
      url: "views/kullanici-gruplari/api.php",
      type: "POST",
      dataType: "json",
      data: {
        action: "toggleRolePermission",
        role_id: $button.attr("data-role-id"),
        permission_id: $button.attr("data-permission-id"),
        enabled: newEnabled ? "1" : "0",
        csrf_token: window.permissionMatrixCsrf || ""
      },
      success: function (res) {
        if (res.status === "success") {
          runSearch(lastSearch);
          if (typeof toastr !== "undefined") toastr.success(res.message);
        } else {
          $button.removeClass("is-loading").prop("disabled", false);
          Swal.fire("Hata", res.message || "Yetki durumu değiştirilemedi.", "error");
        }
      },
      error: function () {
        $button.removeClass("is-loading").prop("disabled", false);
        Swal.fire("Hata", "Yetki durumu değiştirilirken sunucu hatası oluştu.", "error");
      }
    });
  });
});
