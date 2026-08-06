/**
 * Menü Yönetimi JS Modülü - Personel Listesi Başlatma Mantığı
 */
$(document).ready(function () {
    "use strict";

    let menuTable = null;
    const apiUrl = "views/menu-yonetimi/api.php";

    function safeFeatherReplace() {
        if (typeof feather !== "undefined") {
            if (feather.icons) {
                $('[data-feather]').each(function () {
                    var iconName = $.trim($(this).attr('data-feather'));
                    if (iconName && !feather.icons[iconName]) {
                        $(this).removeAttr('data-feather').addClass('bx bx-' + iconName);
                    }
                });
            }
            try {
                feather.replace();
            } catch (e) {
                console.warn("Feather replace skipped invalid icon:", e);
            }
        }
    }

    // Dynamic icon preview listener in modal
    $("#menu_icon").on("input change", function () {
        const val = $.trim($(this).val());
        if (val) {
            if (typeof feather !== 'undefined' && feather.icons && feather.icons[val]) {
                $("#iconPreview").html(`<i data-feather="${escapeHtml(val)}"></i>`);
            } else {
                $("#iconPreview").html(`<i class="bx bx-${escapeHtml(val)}"></i>`);
            }
        } else {
            $("#iconPreview").html(`<i data-feather="help-circle"></i>`);
        }
        safeFeatherReplace();
    });

    // Parent Menus dropdown population in modal
    function loadParentsInModal(excludeEncId = '') {
        $.getJSON(apiUrl, { action: 'get_parents', exclude_id: excludeEncId }, function (res) {
            if (res.status === 'success') {
                let options = '<option value="0">Ana Menü (Üst Menü Yok)</option>';
                $.each(res.data, function (i, item) {
                    options += `<option value="${item.id}">${escapeHtml(item.menu_name)}</option>`;
                });
                $("#parent_id").html(options);
            }
        });
    }

    function updateStatusCounts(data) {
        let total = data.length;
        let aktif = 0;
        let pasif = 0;

        $.each(data, function (i, item) {
            if (item.is_active == 1) {
                aktif++;
            } else {
                pasif++;
            }
        });

        $("#count-all").text(total);
        $("#count-aktif").text(aktif);
        $("#count-pasif").text(pasif);
    }

    // Personel Listesindeki gibi applyLengthStateSave & getDatatableOptions ile başlat
    function initMenuTable() {
        var baseOptions = getDatatableOptions();
        var originalInitComplete = baseOptions.initComplete;

        var options = applyLengthStateSave({
            ...baseOptions,
            serverSide: false,
            responsive: true,
            order: [[6, "asc"]],
            ajax: {
                url: apiUrl,
                type: 'GET',
                data: function (d) {
                    d.action = 'fetch_list';
                    d.include_deleted = 0;
                },
                dataSrc: function (json) {
                    if (json.status === 'error') {
                        Toastify({
                            text: json.message || "Menü listesi yüklenemedi.",
                            duration: 3000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#f46a6a"
                        }).showToast();
                        return [];
                    }
                    updateStatusCounts(json.data || []);
                    return json.data || [];
                }
            },
            drawCallback: function () {
                safeFeatherReplace();
            },
            initComplete: function (settings, json) {
                if (typeof originalInitComplete === "function") {
                    originalInitComplete.call(this, settings, json);
                }
                const defaultStatus = $('input[name="status-filter"]:checked').val() || "Aktif";
                if (defaultStatus && this.api) {
                    this.api().column(7).search(defaultStatus).draw();
                }
            },
            columns: [
                {
                    data: null,
                    className: 'text-center',
                    render: function (data, type, row, meta) {
                        return meta.row + 1;
                    }
                },
                {
                    data: 'menu_name',
                    render: function (data, type, row) {
                        let html = `<div class="fw-bold text-dark">${escapeHtml(data)}</div>`;
                        if (row.page_description) {
                            html += `<small class="text-muted fs-11">${escapeHtml(row.page_description)}</small>`;
                        }
                        return html;
                    }
                },
                {
                    data: 'parent_name',
                    render: function (data, type, row) {
                        if (row.parent_id == 0 || !data) {
                            return '<span class="badge badge-soft-menu-parent">- Ana Menü -</span>';
                        }
                        return `<span class="badge badge-soft-menu-sub"><i class="feather feather-corner-down-right me-1"></i>${escapeHtml(data)}</span>`;
                    }
                },
                {
                    data: 'group_name',
                    render: function (data) {
                        return `<span class="badge badge-soft-menu-group">${escapeHtml(data || 'Genel')}</span>`;
                    }
                },
                {
                    data: 'menu_link',
                    render: function (data) {
                        if (!data) return '<span class="text-muted small">- Yok -</span>';
                        return `<code class="text-dark bg-light px-2 py-1 rounded fs-12">${escapeHtml(data)}</code>`;
                    }
                },
                {
                    data: 'menu_icon',
                    className: 'text-center',
                    render: function (data) {
                        if (!data) return '<span class="text-muted fs-12">-</span>';
                        const iconHtml = (typeof feather !== 'undefined' && feather.icons && feather.icons[data])
                            ? `<i data-feather="${escapeHtml(data)}"></i>`
                            : `<i class="bx bx-${escapeHtml(data)}"></i>`;
                        return `<span class="avatar-xs d-inline-flex align-items-center justify-content-center bg-light rounded-circle text-primary me-1 fs-15">${iconHtml}</span> <small class="text-muted">${escapeHtml(data)}</small>`;
                    }
                },
                {
                    data: 'menu_order',
                    className: 'text-center fw-semibold'
                },
                {
                    data: 'is_active',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'filter' || type === 'sort') {
                            return data == 1 ? 'Aktif' : 'Pasif';
                        }
                        if (data == 1) {
                            return '<span class="badge bg-success"><i class="bx bx-check me-1"></i>Aktif</span>';
                        }
                        return '<span class="badge bg-warning text-dark"><i class="bx bx-minus me-1"></i>Pasif</span>';
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        return `
                            <button type="button" class="btn btn-sm btn-outline-primary btn-edit me-1" data-id="${row.encrypted_id}" title="Düzenle">
                                <i class="bx bx-edit-alt"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-delete" data-id="${row.encrypted_id}" data-name="${escapeHtml(row.menu_name)}" title="Sil (Soft Delete)">
                                <i class="bx bx-trash"></i>
                            </button>
                        `;
                    }
                }
            ]
        });

        menuTable = $("#menuTable").DataTable(options);
    }

    // Status filter radio change handler
    $('input[name="status-filter"]').on('change', function () {
        const statusVal = $(this).val();
        if (!menuTable) return;
        menuTable.column(7).search(statusVal).draw();
    });

    // Open Modal for Add
    $("#btnAddNewMenu").on("click", function () {
        $("#menuForm")[0].reset();
        $("#menu_id").val('');
        $("#menuModalLabel").html('<i class="feather feather-plus-circle me-2"></i>Yeni Menü Ekle');
        $("#iconPreview").html('<i data-feather="help-circle"></i>');
        safeFeatherReplace();
        loadParentsInModal('');
        $("#menuModal").modal("show");
    });

    // Open Modal for Edit
    $(document).on("click", ".btn-edit", function () {
        const encId = $(this).data("id");
        $("#menuForm")[0].reset();
        $("#menu_id").val(encId);
        $("#menuModalLabel").html('<i class="feather feather-edit me-2"></i>Menü Güncelle');

        $.getJSON(apiUrl, { action: 'get_detail', id: encId }, function (res) {
            if (res.status === 'success') {
                const m = res.data;
                $("#menu_name").val(m.menu_name);
                $("#page_description").val(m.page_description || '');
                $("#group_name").val(m.group_name || 'Yönetim');
                $("#group_order").val(m.group_order || 1);
                $("#menu_link").val(m.menu_link || '');
                $("#menu_icon").val(m.menu_icon || '');
                $("#menu_order").val(m.menu_order || 1);
                $("#is_active").val(m.is_active);
                $("#is_menu").val(m.is_menu);
                $("#is_authorized").val(m.is_authorized);

                if (m.menu_icon) {
                    if (typeof feather !== 'undefined' && feather.icons && feather.icons[m.menu_icon]) {
                        $("#iconPreview").html(`<i data-feather="${escapeHtml(m.menu_icon)}"></i>`);
                    } else {
                        $("#iconPreview").html(`<i class="bx bx-${escapeHtml(m.menu_icon)}"></i>`);
                    }
                } else {
                    $("#iconPreview").html('<i data-feather="help-circle"></i>');
                }
                safeFeatherReplace();

                loadParentsInModal(encId);
                setTimeout(function () {
                    $("#parent_id").val(m.parent_id || 0);
                }, 150);

                $("#menuModal").modal("show");
            } else {
                Swal.fire("Hata", res.message || "Detaylar alınamadı.", "error");
            }
        });
    });

    // Save Form Submit
    $("#menuForm").on("submit", function (e) {
        e.preventDefault();
        const formData = $(this).serialize() + "&action=save";

        $("#btnSaveMenu").prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

        $.ajax({
            url: apiUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $("#btnSaveMenu").prop("disabled", false).html('<i class="bx bx-save me-1"></i> Kaydet');
                if (res.status === 'success') {
                    $("#menuModal").modal("hide");
                    Toastify({
                        text: res.message,
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#34c38f"
                    }).showToast();
                    if (menuTable) menuTable.ajax.reload(null, false);
                } else {
                    Swal.fire("Hata", res.message || "Kaydedilirken bir sorun oluştu.", "error");
                }
            },
            error: function () {
                $("#btnSaveMenu").prop("disabled", false).html('<i class="bx bx-save me-1"></i> Kaydet');
                Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
            }
        });
    });

    // Soft Delete Handler
    $(document).on("click", ".btn-delete", function () {
        const encId = $(this).data("id");
        const menuName = $(this).data("name");

        Swal.fire({
            title: "Emin misiniz?",
            html: `<b>${escapeHtml(menuName)}</b> isimli menüyü silmek (soft delete) istediğinize emin misiniz?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#f46a6a",
            cancelButtonColor: "#74788d",
            confirmButtonText: "Evet, Sil",
            cancelButtonText: "İptal"
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: apiUrl,
                    type: 'POST',
                    data: { action: 'soft_delete', id: encId },
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 'success') {
                            Toastify({
                                text: res.message,
                                duration: 3000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#f46a6a"
                            }).showToast();
                            if (menuTable) menuTable.ajax.reload(null, false);
                        } else {
                            Swal.fire("Hata", res.message || "Silme işlemi başarısız.", "error");
                        }
                    },
                    error: function () {
                        Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
                    }
                });
            }
        });
    });

    // Utility: HTML Escape
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Init page table on load
    initMenuTable();
});
