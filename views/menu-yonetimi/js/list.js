/**
 * Minimalist Hiyerarşik Sürükle-Bırak Menü Yönetimi JS Modülü
 */
$(document).ready(function () {
    "use strict";

    const apiUrl = "views/menu-yonetimi/api.php";
    let allMenusData = [];
    let sortableInstance = null;
    let allExpanded = false;
    let draggedParentChildIds = [];

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

    // Modal Dynamic Icon Preview
    $("#menu_icon").on("input change", function () {
        const val = $.trim($(this).val());
        if (val) {
            if (typeof feather !== 'undefined' && feather.icons && feather.icons[val]) {
                $("#modalIconPreview").html(`<i data-feather="${escapeHtml(val)}"></i>`);
            } else {
                $("#modalIconPreview").html(`<i class="bx bx-${escapeHtml(val)}"></i>`);
            }
        } else {
            $("#modalIconPreview").html(`<i data-feather="help-circle"></i>`);
        }
        safeFeatherReplace();
    });

    // Parent Menus Dropdown in Modal
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

    // Build Single Minimal Menu Card HTML
    function buildMenuCardHtml(item, childCount = 0) {
        const isChild = item.parent_id > 0;
        const cardClass = isChild ? 'is-child' : (childCount > 0 ? 'is-parent children-collapsed' : 'is-parent');
        const displayStyle = isChild ? 'style="display: none;"' : '';
        const iconClass = isChild ? 'child-icon' : 'parent-icon';
        const badgeClass = isChild ? 'menu-tag menu-tag-child' : 'menu-tag menu-tag-parent';
        const badgeText = isChild ? 'Alt Menü' : 'Ana Menü';
        const iconName = item.menu_icon || 'circle';

        const iconHtml = (typeof feather !== 'undefined' && feather.icons && feather.icons[iconName])
            ? `<i data-feather="${escapeHtml(iconName)}"></i>`
            : `<i class="bx bx-${escapeHtml(iconName)}"></i>`;

        const groupBadge = item.group_name 
            ? `<span class="menu-tag menu-tag-group group-name-badge">${escapeHtml(item.group_name)}</span>` 
            : '';

        const linkBadge = item.menu_link 
            ? `<span class="badge bg-light text-muted border rounded-pill font-size-10 font-monospace" title="Link: ${escapeHtml(item.menu_link)}"><i class="bx bx-link me-1"></i>${escapeHtml(item.menu_link)}</span>` 
            : '';

        let permBadge = '';
        if (item.permission_names) {
            permBadge = `<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill font-size-10" title="Yetki: ${escapeHtml(item.permission_names)} (${escapeHtml(item.permission_auth_names || '-')})"><i class="mdi mdi-shield-check me-1"></i>${escapeHtml(item.permission_names)}</span>`;
        } else if (item.menu_link) {
            permBadge = `<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill font-size-10" title="Bu menü linki (${escapeHtml(item.menu_link)}) ile eşleşen yetki tanımı bulunmuyor"><i class="mdi mdi-shield-alert-outline me-1"></i>Yetki Tanımsız</span>`;
        }

        const permInfoRow = item.permission_names
            ? `<div class="col-12"><div class="alert alert-light border py-1 px-2 mb-0 font-size-11 text-muted d-flex align-items-center gap-1"><i class="mdi mdi-shield-check text-success fs-6"></i><span><strong>Eşleşen Yetki:</strong> ${escapeHtml(item.permission_names)} <code class="ms-1">${escapeHtml(item.permission_auth_names || '')}</code></span></div></div>`
            : (item.menu_link ? `<div class="col-12"><div class="alert alert-warning-subtle border border-warning-subtle py-1 px-2 mb-0 font-size-11 text-warning d-flex align-items-center gap-1"><i class="mdi mdi-alert-circle-outline fs-6"></i><span><strong>Dikkat:</strong> Bu menü linki (${escapeHtml(item.menu_link)}) için tanımlı bir yetki bulunmuyor.</span></div></div>` : '');

        const childCountBadge = (!isChild && childCount > 0)
            ? `<span class="menu-tag text-muted border bg-light fs-10 child-count-badge" title="${childCount} Alt Menü">${childCount} Alt Menü</span>`
            : '';

        const childFoldBtn = (!isChild && childCount > 0)
            ? `<button type="button" class="btn-ghost btn-toggle-children" title="Alt Menüleri Aç / Kapat"><i class="bx bx-chevron-right fs-5 text-primary"></i></button>`
            : '';

        const indentBtn = isChild 
            ? `<button type="button" class="btn-ghost btn-outdent" title="Ana Menü Yap"><i class="bx bx-left-arrow-alt fs-5 text-purple"></i></button>`
            : `<button type="button" class="btn-ghost btn-indent" title="Alt Menü Yap"><i class="bx bx-right-arrow-alt fs-5 text-primary"></i></button>`;

        return `
            <div class="menu-item-card ${cardClass}" 
                 data-id="${item.id}" 
                 data-enc-id="${item.encrypted_id}" 
                 data-parent-id="${item.parent_id}" 
                 data-group="${escapeHtml(item.group_name || '')}" 
                 data-group-order="${parseInt(item.group_order, 10) || 1}"
                 data-active="${item.is_active}"
                 ${displayStyle}>
                
                <div class="menu-item-header">
                    <div class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden">
                        <div class="menu-drag-handle" title="Sürükle & Sırala">
                            <i class="bx bx-dots-vertical-rounded fs-5"></i>
                        </div>
                        
                        <div class="menu-icon-box ${iconClass}">
                            ${iconHtml}
                        </div>

                        <div class="d-flex align-items-center flex-wrap gap-2 text-truncate item-badges-wrap">
                            <span class="menu-title-text fw-semibold text-dark fs-13">${escapeHtml(item.menu_name)}</span>
                            <span class="${badgeClass} item-type-badge">${badgeText}</span>
                            ${childCountBadge}
                            ${groupBadge}
                            ${linkBadge}
                            ${permBadge}
                        </div>
                    </div>

                    <div class="menu-action-btn-group flex-shrink-0">
                        ${childFoldBtn}
                        <button type="button" class="btn-ghost btn-move-up" title="Yukarı"><i class="bx bx-up-arrow-alt fs-5"></i></button>
                        <button type="button" class="btn-ghost btn-move-down" title="Aşağı"><i class="bx bx-down-arrow-alt fs-5"></i></button>
                        ${indentBtn}
                        <button type="button" class="btn-ghost btn-toggle-edit" title="Düzenle"><i class="bx bx-cog fs-5"></i></button>
                    </div>
                </div>

                <div class="menu-item-body">
                    <form class="inline-menu-form" autocomplete="off">
                        <input type="hidden" name="id" value="${item.encrypted_id}">
                        <input type="hidden" name="parent_id" class="inline-parent-id-input" value="${item.parent_id}">
                        <div class="row g-2">
                            ${permInfoRow}
                            <div class="col-md-4">
                                <label class="form-label fs-11 text-muted mb-1 fw-semibold">Menü Adı</label>
                                <input type="text" name="menu_name" class="form-control form-control-sm" value="${escapeHtml(item.menu_name)}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-11 text-muted mb-1 fw-semibold">Sayfa Bağlantısı (Link)</label>
                                <input type="text" name="menu_link" class="form-control form-control-sm" value="${escapeHtml(item.menu_link || '')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-11 text-muted mb-1 fw-semibold">İkon</label>
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text inline-icon-preview">${iconHtml}</span>
                                    <input type="text" name="menu_icon" class="form-control inline-icon-input" value="${escapeHtml(item.menu_icon || '')}" placeholder="users, home vb.">
                                </div>
                            </div>

                            <div class="col-md-5">
                                <label class="form-label fs-11 text-muted mb-1 fw-semibold">Sayfa Açıklaması</label>
                                <input type="text" name="page_description" class="form-control form-control-sm" value="${escapeHtml(item.page_description || '')}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fs-11 text-muted mb-1 fw-semibold">Grup Adı</label>
                                <input type="text" name="group_name" class="form-control form-control-sm" value="${escapeHtml(item.group_name || 'Yönetim')}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label fs-11 text-muted mb-1 fw-semibold">Menüde Görünsün</label>
                                <select name="is_menu" class="form-select form-select-sm">
                                    <option value="1" ${item.is_menu == 1 ? 'selected' : ''}>Evet</option>
                                    <option value="0" ${item.is_menu == 0 ? 'selected' : ''}>Hayır</option>
                                </select>
                            </div>

                            <div class="col-12 d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
                                <button type="button" class="btn btn-outline-danger btn-sm btn-delete-item px-2 py-1 fs-12" data-id="${item.encrypted_id}" data-name="${escapeHtml(item.menu_name)}">
                                    <i class="bx bx-trash me-1"></i> Menüyü Sil
                                </button>
                                <button type="submit" class="btn btn-primary btn-sm btn-save-inline px-3 py-1 fs-12">
                                    <i class="bx bx-save me-1"></i> Kaydet
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        `;
    }

    // Render Menu Tree
    function renderMenuTree(items) {
        allSubmenusExpanded = false;
        $("#btnToggleAllAccordions").html('<i class="bx bx-expand-vertical me-1"></i> Tümünü Aç');
        
        const container = $("#menuTreeContainer");
        container.empty();

        if (!items || items.length === 0) {
            $("#menuTreeEmpty").show();
            container.hide();
            return;
        }

        $("#menuTreeEmpty").hide();
        container.show();

        // 1. Group parents and children (2 seviye hiyerarşi garantisi: alt menünün alt menüsü varsa kök ana menüye bağla)
        const itemsById = {};
        $.each(items, function (i, item) {
            itemsById[item.id] = item;
        });

        const resolveRootParentId = function (item) {
            let pid = parseInt(item.parent_id, 10) || 0;
            const visited = new Set([parseInt(item.id, 10)]);
            while (pid > 0 && itemsById[pid] && (parseInt(itemsById[pid].parent_id, 10) || 0) > 0) {
                if (visited.has(pid)) {
                    pid = 0;
                    break;
                }
                visited.add(pid);
                pid = parseInt(itemsById[pid].parent_id, 10) || 0;
            }
            if (pid > 0 && !itemsById[pid]) {
                return 0;
            }
            return pid;
        };

        const parents = [];
        const childrenMap = {};

        $.each(items, function (i, item) {
            const rootParentId = resolveRootParentId(item);
            item.parent_id = rootParentId;
            if (rootParentId == 0) {
                parents.push(item);
            } else {
                if (!childrenMap[rootParentId]) {
                    childrenMap[rootParentId] = [];
                }
                childrenMap[rootParentId].push(item);
            }
        });

        // 2. Render parent followed by children
        const renderedIds = new Set();

        $.each(parents, function (i, parent) {
            const childList = childrenMap[parent.id] || [];
            container.append(buildMenuCardHtml(parent, childList.length));
            renderedIds.add(parent.id);

            $.each(childList, function (j, child) {
                container.append(buildMenuCardHtml(child, 0));
                renderedIds.add(child.id);
            });
        });

        // Leftover items
        $.each(items, function (i, item) {
            if (!renderedIds.has(item.id)) {
                container.append(buildMenuCardHtml(item, 0));
            }
        });

        safeFeatherReplace();
        initSortable();
        applyFilters();
    }

    // Bir kartın ait olduğu üst grubun Ana Menü (.is-parent, parent_id=0) kartını bulur.
    // Alt menünün alt menüsü (3. seviye) oluşmasını kesin olarak engeller.
    function findGroupParentCard(card) {
        if (!card || !card.length) return $();

        const prevCard = card.prev('.menu-item-card');
        if (prevCard.length > 0) {
            if (prevCard.hasClass('is-child')) {
                const candidateParentId = parseInt(prevCard.attr('data-parent-id'), 10) || 0;
                if (candidateParentId > 0) {
                    const candidateParent = $(`#menuTreeContainer .menu-item-card.is-parent[data-id="${candidateParentId}"]`);
                    if (candidateParent.length > 0 && (parseInt(candidateParent.attr('data-parent-id'), 10) || 0) === 0) {
                        return candidateParent;
                    }
                }
            } else if (prevCard.hasClass('is-parent') && (parseInt(prevCard.attr('data-parent-id'), 10) || 0) === 0) {
                return prevCard;
            }
        }

        // Yukarı doğru en yakın gerçek Ana Menü (.is-parent ve data-parent-id="0") kartını bul
        const prevParents = card.prevAll('.menu-item-card.is-parent');
        for (let i = 0; i < prevParents.length; i++) {
            const p = $(prevParents[i]);
            if ((parseInt(p.attr('data-parent-id'), 10) || 0) === 0) {
                return p;
            }
        }

        return $();
    }

    // Kartın grup bilgisini (attribute, rozet ve inline form input) senkronize eder
    function syncCardGroup(card, groupName, groupOrder) {
        if (!card || !card.length) return;
        if (typeof groupName === 'string' && groupName !== '') {
            card.attr('data-group', groupName);
            card.find('input[name="group_name"]').val(groupName);
            const badge = card.find('.group-name-badge');
            if (badge.length > 0) {
                badge.text(groupName);
            }
        }
        if (groupOrder && groupOrder > 0) {
            card.attr('data-group-order', groupOrder);
        }
    }

    // Ana menü kartının "X Alt Menü" rozetini ve akordiyon aç/kapat butonunu günceller
    function updateParentCardUI(parentId) {
        const pid = parseInt(parentId, 10) || 0;
        if (pid <= 0) return;

        const parentCard = $(`#menuTreeContainer .menu-item-card[data-id="${pid}"]`);
        if (parentCard.length === 0) return;

        const childCards = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${pid}"]`);
        const childCount = childCards.length;
        const countBadge = parentCard.find('.child-count-badge');
        const foldBtn = parentCard.find('.btn-toggle-children');

        if (childCount > 0) {
            if (countBadge.length > 0) {
                countBadge.attr('title', `${childCount} Alt Menü`).text(`${childCount} Alt Menü`);
            } else {
                parentCard.find('.item-type-badge').after(
                    `<span class="menu-tag text-muted border bg-light fs-10 child-count-badge" title="${childCount} Alt Menü">${childCount} Alt Menü</span>`
                );
            }

            const isCollapsed = parentCard.hasClass('children-collapsed');
            const chevronClass = isCollapsed ? 'bx-chevron-right' : 'bx-chevron-down';
            if (foldBtn.length === 0) {
                parentCard.find('.menu-action-btn-group').prepend(
                    `<button type="button" class="btn-ghost btn-toggle-children" title="Alt Menüleri Aç / Kapat"><i class="bx ${chevronClass} fs-5 text-primary"></i></button>`
                );
            } else {
                foldBtn.find('i').removeClass('bx-chevron-right bx-chevron-down').addClass(chevronClass);
            }
        } else {
            countBadge.remove();
            foldBtn.remove();
            parentCard.removeClass('children-collapsed');
        }
    }

    // Bir kartı görsel ve yapısal olarak Alt Menü (.is-child) durumuna çevirir
    function applyChildStateToCard(card, targetParentId, parentGroupName, parentGroupOrder) {
        card.removeClass('is-parent children-collapsed').addClass('is-child');
        card.css('display', '');
        card.attr('data-parent-id', targetParentId);
        card.find('.inline-parent-id-input').val(targetParentId);
        card.find('.menu-icon-box').removeClass('parent-icon').addClass('child-icon');
        card.find('.item-type-badge').removeClass('menu-tag-parent').addClass('menu-tag-child').text('Alt Menü');
        card.find('.child-count-badge').remove();
        card.find('.btn-toggle-children').remove();

        const indentBtn = card.find('.btn-indent');
        if (indentBtn.length > 0) {
            indentBtn.replaceWith(`<button type="button" class="btn-ghost btn-outdent" title="Ana Menü Yap"><i class="bx bx-left-arrow-alt fs-5 text-purple"></i></button>`);
        }

        syncCardGroup(card, parentGroupName, parentGroupOrder);
    }

    // Initialize SortableJS
    function initSortable() {
        const el = document.getElementById('menuTreeContainer');
        if (!el) return;

        if (sortableInstance) {
            sortableInstance.destroy();
        }

        sortableInstance = new Sortable(el, {
            animation: 160,
            handle: '.menu-drag-handle',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onStart: function (evt) {
                const draggedCard = $(evt.item);
                draggedParentChildIds = [];

                if (draggedCard.hasClass('is-parent')) {
                    const parentId = draggedCard.attr('data-id');
                    draggedParentChildIds = $(`#menuTreeContainer .menu-item-card[data-parent-id="${parentId}"]`)
                        .map(function () { return $(this).attr('data-id'); })
                        .get();
                }
            },
            onEnd: function (evt) {
                const draggedCard = $(evt.item);

                if (draggedCard.hasClass('is-parent')) {
                    // Kapalı (veya açık) bir ana menünün hemen altına bırakıldığında,
                    // o ana menünün alt menülerinin arasına girmesini önle; son alt menünün arkasına yerleştir.
                    const nextCard = draggedCard.next('.menu-item-card');
                    if (nextCard.length > 0 && nextCard.hasClass('is-child')) {
                        const splitParentId = parseInt(nextCard.attr('data-parent-id'), 10) || 0;
                        const draggedId = parseInt(draggedCard.attr('data-id'), 10) || 0;
                        if (splitParentId > 0 && splitParentId !== draggedId) {
                            const splitSiblings = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${splitParentId}"]`);
                            if (splitSiblings.length > 0) {
                                draggedCard.insertAfter(splitSiblings.last());
                            }
                        }
                    }

                    // Sortable yalnız sürüklenen kartı taşır. Ana menünün çocuklarını da
                    // hemen arkasına alarak ekrandaki hiyerarşik bloğu birlikte koru.
                    if (draggedParentChildIds.length > 0) {
                        let insertAfter = draggedCard;
                        draggedParentChildIds.forEach(function (childId) {
                            const childCard = $(`#menuTreeContainer .menu-item-card[data-id="${childId}"]`);
                            if (childCard.length) {
                                childCard.insertAfter(insertAfter);
                                insertAfter = childCard;
                            }
                        });
                    }
                } else if (draggedCard.hasClass('is-child')) {
                    // Alt menü başka bir grubun altına sürüklendiyse bulunduğu grubun Ana Menüsüne bağla
                    const oldParentId = parseInt(draggedCard.attr('data-parent-id'), 10) || 0;
                    const newGroupParent = findGroupParentCard(draggedCard);
                    if (newGroupParent.length > 0) {
                        const newParentId = parseInt(newGroupParent.attr('data-id'), 10) || 0;
                        if (newParentId > 0) {
                            // Eğer hedef ana menü kapalıysa ve kart onun gizli çocuklarının önüne düştüyse,
                            // ana menüyü görünür yap veya son çocuğun arkasına hizala
                            if (newGroupParent.hasClass('children-collapsed')) {
                                const hiddenChildren = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${newParentId}"]`).not(draggedCard);
                                hiddenChildren.slideDown(150);
                                newGroupParent.removeClass('children-collapsed');
                                newGroupParent.find('.btn-toggle-children i').removeClass('bx-chevron-right').addClass('bx-chevron-down');
                            }
                            draggedCard.attr('data-parent-id', newParentId);
                            draggedCard.find('.inline-parent-id-input').val(newParentId);
                            syncCardGroup(
                                draggedCard,
                                newGroupParent.attr('data-group') || '',
                                parseInt(newGroupParent.attr('data-group-order'), 10) || 0
                            );
                            if (oldParentId > 0 && oldParentId !== newParentId) {
                                updateParentCardUI(oldParentId);
                            }
                            updateParentCardUI(newParentId);
                        }
                    } else {
                        // En üste (hiçbir ana menünün altında olmayacak yere) bırakıldıysa Ana Menüye çevir
                        draggedCard.removeClass('is-child').addClass('is-parent');
                        draggedCard.attr('data-parent-id', '0');
                        draggedCard.find('.inline-parent-id-input').val('0');
                        draggedCard.find('.menu-icon-box').removeClass('child-icon').addClass('parent-icon');
                        draggedCard.find('.item-type-badge').removeClass('menu-tag-child').addClass('menu-tag-parent').text('Ana Menü');
                        draggedCard.find('.btn-outdent').replaceWith(
                            `<button type="button" class="btn-ghost btn-indent" title="Alt Menü Yap"><i class="bx bx-right-arrow-alt fs-5 text-primary"></i></button>`
                        );
                        if (oldParentId > 0) {
                            updateParentCardUI(oldParentId);
                        }
                    }
                }

                draggedParentChildIds = [];
                autoSaveHierarchy(false);
            }
        });
    }

    // Load Menu List from Server (Only active system menus)
    function loadMenuList() {
        $("#menuTreeLoading").show();
        $("#menuTreeContainer").hide();
        $("#menuTreeEmpty").hide();

        $.ajax({
            url: apiUrl,
            type: 'GET',
            data: { action: 'fetch_list', include_deleted: 0, only_active: 1 },
            dataType: 'json',
            success: function (res) {
                $("#menuTreeLoading").hide();
                if (res.status === 'success') {
                    allMenusData = res.data || [];
                    $("#badgeTotalItems").text(`${allMenusData.length} Menü`);
                    renderMenuTree(allMenusData);
                } else {
                    Toastify({
                        text: res.message || "Menü listesi alınamadı.",
                        duration: 3000,
                        backgroundColor: "#f46a6a"
                    }).showToast();
                }
            },
            error: function () {
                $("#menuTreeLoading").hide();
                Toastify({
                    text: "Sunucu ile iletişim kurulamadı.",
                    duration: 3000,
                    backgroundColor: "#f46a6a"
                }).showToast();
            }
        });
    }

    // Extract the current order from DOM while enforcing 2-level hierarchy (Ana Menü -> Alt Menü).
    function autoSaveHierarchy(silent = false) {
        const cards = $("#menuTreeContainer .menu-item-card");
        const itemsToSave = [];
        let orderIndex = 1;

        cards.each(function () {
            const card = $(this);
            const id = parseInt(card.attr('data-id'), 10);
            let parentId = 0;

            if (card.hasClass('is-child')) {
                const rawParentId = Math.max(0, parseInt(card.attr('data-parent-id'), 10) || 0);
                const parentCard = rawParentId > 0
                    ? $(`#menuTreeContainer .menu-item-card[data-id="${rawParentId}"]`)
                    : $();

                if (parentCard.length > 0 && parentCard.hasClass('is-parent') && (parseInt(parentCard.attr('data-parent-id'), 10) || 0) === 0) {
                    parentId = rawParentId;
                } else {
                    const groupParent = findGroupParentCard(card);
                    parentId = groupParent.length > 0 ? (parseInt(groupParent.attr('data-id'), 10) || 0) : 0;
                }

                if (parentId === id) {
                    parentId = 0;
                }

                card.attr('data-parent-id', parentId);
                card.find('.inline-parent-id-input').val(parentId);

                if (parentId > 0) {
                    const validParent = $(`#menuTreeContainer .menu-item-card[data-id="${parentId}"]`);
                    if (validParent.length > 0) {
                        syncCardGroup(
                            card,
                            validParent.attr('data-group') || '',
                            parseInt(validParent.attr('data-group-order'), 10) || 0
                        );
                    }
                }
            } else {
                card.attr('data-parent-id', '0');
                card.find('.inline-parent-id-input').val('0');
                parentId = 0;
            }

            const groupName = card.attr('data-group') || '';
            const groupOrder = parseInt(card.attr('data-group-order'), 10) || 0;

            itemsToSave.push({
                id: id,
                parent_id: parentId,
                menu_order: orderIndex++,
                group_name: groupName,
                group_order: groupOrder
            });
        });

        if (itemsToSave.length === 0) return;

        $.ajax({
            url: apiUrl + '?action=update_hierarchy',
            type: 'POST',
            data: JSON.stringify({ action: 'update_hierarchy', items: itemsToSave }),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            success: function (res) {
                if (res.status === 'success') {
                    if (!silent) {
                        Toastify({
                            text: "Menü sıralaması kaydedildi.",
                            duration: 2000,
                            gravity: "top",
                            position: "right",
                            backgroundColor: "#34c38f"
                        }).showToast();
                    }
                } else {
                    Swal.fire("Hata", res.message || "Hiyerarşi güncellenemedi.", "error");
                }
            },
            error: function () {
                Toastify({
                    text: "Sıralama sunucuya iletilemedi.",
                    duration: 3000,
                    backgroundColor: "#f46a6a"
                }).showToast();
            }
        });
    }

    // Reset to Default Hierarchy Button
    $("#btnResetDefaults").on("click", function () {
        Swal.fire({
            title: "Varsayılana Sıfırla?",
            html: "Tüm menü sırası ve hiyerarşik yapısı <b>fabrika varsayılan ayarlarına</b> döndürülecektir. Devam etmek istiyor musunuz?",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#64748b",
            confirmButtonText: "Evet, Sıfırla",
            cancelButtonText: "İptal"
        }).then((result) => {
            if (result.isConfirmed) {
                $("#btnResetDefaults").prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Sıfırlanıyor...');
                $.ajax({
                    url: apiUrl + '?action=reset_defaults',
                    type: 'POST',
                    data: { action: 'reset_defaults' },
                    dataType: 'json',
                    success: function (res) {
                        $("#btnResetDefaults").prop('disabled', false).html('<i class="bx bx-reset me-1"></i> Varsayılana Dön');
                        if (res.status === 'success') {
                            Toastify({
                                text: res.message,
                                duration: 3000,
                                gravity: "top",
                                position: "right",
                                backgroundColor: "#34c38f"
                            }).showToast();
                            loadMenuList();
                        } else {
                            Swal.fire("Hata", res.message || "Sıfırlanamadı.", "error");
                        }
                    },
                    error: function () {
                        $("#btnResetDefaults").prop('disabled', false).html('<i class="bx bx-reset me-1"></i> Varsayılana Dön');
                        Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
                    }
                });
            }
        });
    });

    // Indent: Turn into Sub-Menu (Her zaman bulunduğu grubun Ana Menüsüne bağlanır)
    $(document).on("click", ".btn-indent", function (e) {
        e.stopPropagation();
        const card = $(this).closest('.menu-item-card');
        const cardId = parseInt(card.attr('data-id'), 10) || 0;
        const parentCard = findGroupParentCard(card);

        if (parentCard.length === 0) {
            Toastify({
                text: "En üstteki menü alt menü yapılamaz.",
                duration: 2500,
                backgroundColor: "#f59e0b"
            }).showToast();
            return;
        }

        const targetParentId = parseInt(parentCard.attr('data-id'), 10) || 0;
        if (!targetParentId || targetParentId === cardId) {
            return;
        }

        const parentGroupName = parentCard.attr('data-group') || '';
        const parentGroupOrder = parseInt(parentCard.attr('data-group-order'), 10) || 0;

        // Bu kartın kendisine bağlı alt menüler varsa (ör. önce alttaki menü alt menü yapılıp
        // sonra üstteki menü alt menü yapıldıysa), 3. seviye oluşmaması için onları da aynı ana menüye bağla.
        const existingChildren = $(`#menuTreeContainer .menu-item-card[data-parent-id="${cardId}"]`);
        if (existingChildren.length > 0) {
            existingChildren.each(function () {
                applyChildStateToCard($(this), targetParentId, parentGroupName, parentGroupOrder);
            });
        }

        // Eğer hedef ana menü kapalıysa, alt menüleri aç ve yeni alt menüyü grubun sonuna hizala
        const existingTargetChildren = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${targetParentId}"]`).not(card).not(existingChildren);
        if (parentCard.hasClass('children-collapsed') && existingTargetChildren.length > 0) {
            // Eğer kart kapalı ana menünün gizli çocuklarının önünde kalmışsa son çocuğun arkasına taşı
            const lastTargetChild = existingTargetChildren.last();
            if (card.index() < lastTargetChild.index()) {
                card.insertAfter(lastTargetChild);
                let afterEl = card;
                existingChildren.each(function () {
                    $(this).insertAfter(afterEl);
                    afterEl = $(this);
                });
            }
            existingTargetChildren.slideDown(150);
            parentCard.removeClass('children-collapsed');
        }

        // Kartı alt menü yap
        applyChildStateToCard(card, targetParentId, parentGroupName, parentGroupOrder);

        updateParentCardUI(targetParentId);
        safeFeatherReplace();
        autoSaveHierarchy(false);
    });

    // Outdent: Turn into Main Menu
    $(document).on("click", ".btn-outdent", function (e) {
        e.stopPropagation();
        const card = $(this).closest('.menu-item-card');
        const oldParentId = parseInt(card.attr('data-parent-id'), 10) || 0;

        // Eğer eski ana menünün başka alt menüleri varsa ve bu kart aralarında yer alıyorsa,
        // altta kalan kardeş alt menülerin bu kartın alt menüsü gibi davranmaması için
        // kartı eski ana menünün son alt menüsünün arkasına taşı.
        if (oldParentId > 0) {
            const remainingSiblings = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${oldParentId}"]`).not(card);
            if (remainingSiblings.length > 0) {
                const lastSibling = remainingSiblings.last();
                if (card.index() < lastSibling.index()) {
                    card.insertAfter(lastSibling);
                }
            }
        }

        // Make it parent
        card.removeClass('is-child').addClass('is-parent');
        card.css('display', '');
        card.attr('data-parent-id', '0');
        card.find('.inline-parent-id-input').val('0');
        card.find('.menu-icon-box').removeClass('child-icon').addClass('parent-icon');
        card.find('.item-type-badge').removeClass('menu-tag-child').addClass('menu-tag-parent').text('Ana Menü');

        // Swap button to Indent
        $(this).replaceWith(`<button type="button" class="btn-ghost btn-indent" title="Alt Menü Yap"><i class="bx bx-right-arrow-alt fs-5 text-primary"></i></button>`);

        if (oldParentId > 0) {
            updateParentCardUI(oldParentId);
        }

        safeFeatherReplace();
        autoSaveHierarchy(false);
    });

    // Move Up Button
    $(document).on("click", ".btn-move-up", function (e) {
        e.stopPropagation();
        const card = $(this).closest('.menu-item-card');

        if (card.hasClass('is-parent')) {
            const prevParent = card.prevAll('.menu-item-card.is-parent').first();
            if (prevParent.length > 0) {
                const cardId = card.attr('data-id');
                const myChildren = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${cardId}"]`);
                card.insertBefore(prevParent);
                let insertAfter = card;
                myChildren.each(function () {
                    $(this).insertAfter(insertAfter);
                    insertAfter = $(this);
                });
                autoSaveHierarchy(false);
            }
        } else {
            const parentId = card.attr('data-parent-id');
            const prevSibling = card.prev('.menu-item-card.is-child[data-parent-id="' + parentId + '"]');
            if (prevSibling.length > 0) {
                card.insertBefore(prevSibling);
                autoSaveHierarchy(false);
            }
        }
    });

    // Move Down Button
    $(document).on("click", ".btn-move-down", function (e) {
        e.stopPropagation();
        const card = $(this).closest('.menu-item-card');

        if (card.hasClass('is-parent')) {
            const nextParent = card.nextAll('.menu-item-card.is-parent').first();
            if (nextParent.length > 0) {
                const nextParentId = nextParent.attr('data-id');
                const nextParentChildren = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${nextParentId}"]`);
                const targetAfter = nextParentChildren.length > 0 ? nextParentChildren.last() : nextParent;

                const cardId = card.attr('data-id');
                const myChildren = $(`#menuTreeContainer .menu-item-card.is-child[data-parent-id="${cardId}"]`);
                card.insertAfter(targetAfter);
                let insertAfter = card;
                myChildren.each(function () {
                    $(this).insertAfter(insertAfter);
                    insertAfter = $(this);
                });
                autoSaveHierarchy(false);
            }
        } else {
            const parentId = card.attr('data-parent-id');
            const nextSibling = card.next('.menu-item-card.is-child[data-parent-id="' + parentId + '"]');
            if (nextSibling.length > 0) {
                card.insertAfter(nextSibling);
                autoSaveHierarchy(false);
            }
        }
    });

    // Toggle Submenus for a Parent Menu (Akordiyon Katlama/Açma)
    $(document).on("click", ".btn-toggle-children", function (e) {
        e.stopPropagation();
        const parentCard = $(this).closest('.menu-item-card.is-parent');
        const parentId = parentCard.attr('data-id');
        const childCards = $(`#menuTreeContainer .menu-item-card[data-parent-id="${parentId}"]`);
        const toggleIcon = parentCard.find('.btn-toggle-children i');

        if (childCards.length === 0) return;

        if (parentCard.hasClass('children-collapsed')) {
            childCards.slideDown(150);
            toggleIcon.removeClass('bx-chevron-right').addClass('bx-chevron-down');
            parentCard.removeClass('children-collapsed');
        } else {
            childCards.slideUp(150);
            toggleIcon.removeClass('bx-chevron-down').addClass('bx-chevron-right');
            parentCard.addClass('children-collapsed');
        }
    });

    // Parent header'a tıklandığında alt menü akordiyonunu tetikle
    $(document).on("click", ".menu-item-card.is-parent .menu-item-header", function (e) {
        if ($(e.target).closest('.btn-ghost, .menu-drag-handle, input, select, form, a').length) {
            return;
        }
        $(this).find('.btn-toggle-children').trigger('click');
    });

    // Toggle Item Detail Edit Form (Düzenleme Paneli)
    $(document).on("click", ".btn-toggle-edit", function (e) {
        e.stopPropagation();
        const card = $(this).closest('.menu-item-card');
        const body = card.find('.menu-item-body');
        const toggleIcon = $(this).find('i');

        body.slideToggle(150, function () {
            if (body.is(':visible')) {
                toggleIcon.removeClass('bx-cog').addClass('bx-chevron-up');
            } else {
                toggleIcon.removeClass('bx-chevron-up').addClass('bx-cog');
            }
        });
    });

    // Toggle All Parent Submenus (Tümünü Aç / Kapat)
    let allSubmenusExpanded = false;
    $("#btnToggleAllAccordions").on("click", function () {
        allSubmenusExpanded = !allSubmenusExpanded;
        const childCards = $("#menuTreeContainer .menu-item-card.is-child");
        const parentToggles = $("#menuTreeContainer .menu-item-card.is-parent .btn-toggle-children i");
        const parentCards = $("#menuTreeContainer .menu-item-card.is-parent");

        if (allSubmenusExpanded) {
            childCards.slideDown(150);
            parentToggles.removeClass('bx-chevron-right').addClass('bx-chevron-down');
            parentCards.removeClass('children-collapsed');
            $(this).html('<i class="bx bx-collapse-vertical me-1"></i> Tümünü Kapat');
        } else {
            childCards.slideUp(150);
            parentToggles.removeClass('bx-chevron-down').addClass('bx-chevron-right');
            parentCards.addClass('children-collapsed');
            $(this).html('<i class="bx bx-expand-vertical me-1"></i> Tümünü Aç');
        }
    });

    // Inline Icon Live Preview
    $(document).on("input change", ".inline-icon-input", function () {
        const val = $.trim($(this).val());
        const preview = $(this).closest('.input-group').find('.inline-icon-preview');
        if (val) {
            if (typeof feather !== 'undefined' && feather.icons && feather.icons[val]) {
                preview.html(`<i data-feather="${escapeHtml(val)}"></i>`);
            } else {
                preview.html(`<i class="bx bx-${escapeHtml(val)}"></i>`);
            }
        } else {
            preview.html(`<i class="bx bx-circle"></i>`);
        }
        safeFeatherReplace();
    });

    // Inline Save Form Submit
    $(document).on("submit", ".inline-menu-form", function (e) {
        e.preventDefault();
        const form = $(this);
        const card = form.closest('.menu-item-card');
        const submitBtn = form.find('.btn-save-inline');
        const formData = form.serialize() + "&action=save&is_active=1";

        submitBtn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

        $.ajax({
            url: apiUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                submitBtn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                if (res.status === 'success') {
                    Toastify({
                        text: res.message,
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#34c38f"
                    }).showToast();

                    const newName = form.find('input[name="menu_name"]').val();
                    const newIcon = form.find('input[name="menu_icon"]').val();
                    card.find('.menu-title-text').text(newName);
                    
                    if (newIcon) {
                        const iconHtml = (typeof feather !== 'undefined' && feather.icons && feather.icons[newIcon])
                            ? `<i data-feather="${escapeHtml(newIcon)}"></i>`
                            : `<i class="bx bx-${escapeHtml(newIcon)}"></i>`;
                        card.find('.menu-icon-box').html(iconHtml);
                    }
                    safeFeatherReplace();
                } else {
                    Swal.fire("Hata", res.message || "Kaydedilemedi.", "error");
                }
            },
            error: function () {
                submitBtn.prop('disabled', false).html('<i class="bx bx-save me-1"></i> Kaydet');
                Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
            }
        });
    });

    // Soft Delete Handler
    $(document).on("click", ".btn-delete-item", function () {
        const encId = $(this).data("id");
        const menuName = $(this).data("name");
        const card = $(this).closest('.menu-item-card');

        Swal.fire({
            title: "Emin misiniz?",
            html: `<b>${escapeHtml(menuName)}</b> isimli menüyü silmek istediğinize emin misiniz?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ef4444",
            cancelButtonColor: "#64748b",
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
                                backgroundColor: "#ef4444"
                            }).showToast();

                            card.fadeOut(200, function () {
                                $(this).remove();
                                loadMenuList();
                            });
                        } else {
                            Swal.fire("Hata", res.message || "Silme başarısız.", "error");
                        }
                    },
                    error: function () {
                        Swal.fire("Hata", "Sunucu hatası oluştu.", "error");
                    }
                });
            }
        });
    });

    // Filter Logic
    function applyFilters() {
        const groupFilter = $('#filterGroupSelect').val();
        let visibleCount = 0;

        $("#menuTreeContainer .menu-item-card").each(function () {
            const card = $(this);
            const groupVal = card.attr('data-group');
            const isChild = card.hasClass('is-child');
            const parentId = card.attr('data-parent-id');
            const parentCard = $(`#menuTreeContainer .menu-item-card[data-id="${parentId}"]`);

            if (groupFilter === "" || groupVal === groupFilter) {
                if (isChild && parentCard.hasClass('children-collapsed')) {
                    card.hide();
                } else {
                    card.show();
                }
                visibleCount++;
            } else {
                card.hide();
            }
        });

        if (visibleCount === 0 && allMenusData.length > 0) {
            $("#menuTreeEmpty").show();
        } else if (allMenusData.length > 0) {
            $("#menuTreeEmpty").hide();
        }
    }

    $('#filterGroupSelect').on('change', applyFilters);

    // Open Add New Menu Modal
    $("#btnAddNewMenu").on("click", function () {
        $("#menuForm")[0].reset();
        $("#modal_menu_id").val('');
        $("#menuModalLabel").html('<i class="feather feather-plus-circle me-1"></i>Yeni Menü Ekle');
        $("#modalIconPreview").html('<i data-feather="help-circle"></i>');
        safeFeatherReplace();
        loadParentsInModal('');
        $("#menuModal").modal("show");
    });

    // Save Modal Form Submit
    $("#menuForm").on("submit", function (e) {
        e.preventDefault();
        const formData = $(this).serialize() + "&action=save&is_active=1";

        $("#btnSaveModalMenu").prop("disabled", true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Kaydediliyor...');

        $.ajax({
            url: apiUrl,
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function (res) {
                $("#btnSaveModalMenu").prop("disabled", false).html('<i class="bx bx-save me-1"></i> Kaydet');
                if (res.status === 'success') {
                    $("#menuModal").modal("hide");
                    Toastify({
                        text: res.message,
                        duration: 3000,
                        gravity: "top",
                        position: "right",
                        backgroundColor: "#34c38f"
                    }).showToast();
                    loadMenuList();
                } else {
                    Swal.fire("Hata", res.message || "Kaydedilirken bir sorun oluştu.", "error");
                }
            },
            error: function () {
                $("#btnSaveModalMenu").prop("disabled", false).html('<i class="bx bx-save me-1"></i> Kaydet');
                Swal.fire("Hata", "Sunucu ile iletişim kurulamadı.", "error");
            }
        });
    });

    // Initial load
    loadMenuList();
});
