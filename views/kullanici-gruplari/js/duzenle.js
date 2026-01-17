document.addEventListener("DOMContentLoaded", function () {
  let url = "views/kullanici-gruplari/api.php";
  let permissionGroups = [];
  let userPermissions = [];
  let originalPermissions = [];
  let roleID;
  let selectedGroups = [];
  let searchTerm = ""; // Arama terimini globalde tutalım

  // Element Referansları
  const loadingSkeleton = document.getElementById("loadingSkeleton");
  const permissionContainer = document.getElementById("permissionContainer");
  const treeViewContainer = document.getElementById("treeViewContainer");
  const cardViewContainer = document.getElementById("cardViewContainer");
  const permissionSearchInput = document.getElementById("permissionSearch");
  const showTreeViewCheckbox = document.getElementById("showTreeView");
  const filterChipsContainer = document.getElementById("filterChips");
  const selectedCountEl = document.getElementById("selectedCount");
  const requiredCountEl = document.getElementById("requiredCount");


  // --- VERİ YÜKLEME ---
  roleID = $("#user_id").val();
  fetch(url, {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "action=getPermissions&id=" + encodeURIComponent(roleID),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data && data.data) {
        permissionGroups = data.data.permissions || [];
        userPermissions = (data.data.user_permissions || []).map(id => parseInt(id, 10));
        originalPermissions = [...userPermissions];
        renderViews(); // Veri geldikten sonra ilk render
      } else {
        console.error("Geçersiz veri formatı:", data);
        // Hata mesajı gösterilebilir
      }
      simulateLoading(false); // Yüklemeyi bitir
    })
    .catch(error => {
        console.error("Veri yükleme hatası:", error);
        simulateLoading(false); // Hata durumunda da yüklemeyi bitir
    });

  function simulateLoading(isLoading = true) {
    if (loadingSkeleton) loadingSkeleton.style.display = isLoading ? "block" : "none";
    if (permissionContainer) permissionContainer.style.display = isLoading ? "none" : "block";
    if (!isLoading && permissionGroups.length === 0) { // Eğer veri yüklenememişse bir mesaj gösterilebilir
        if (permissionContainer) permissionContainer.innerHTML = "<p class='text-center text-muted'>Yetki verileri yüklenemedi.</p>";
    }
  }


  // --- RENDER FONKSİYONLARI ---
  function renderViews() {
    if (!permissionGroups || permissionGroups.length === 0) return; // Veri yoksa render etme
    
    const currentSearchTerm = permissionSearchInput ? permissionSearchInput.value.toLowerCase().trim() : "";
    searchTerm = currentSearchTerm; // Global arama terimini güncelle

    renderCardView();
    renderTreeView();
    updateSelectedCountUI();
    renderFilterChips();
    applyFilters(); // Tek bir ana filtre uygulama fonksiyonu
  }

  function renderCardView() {
    if (!cardViewContainer || !permissionGroups) return;
    let html = "";
    permissionGroups.forEach((group) => {
      const selectedInGroup = group.permissions.filter((p) => userPermissions.includes(p.id)).length;
      const totalInGroup = group.permissions.length;
      const isGroupInitiallyExpanded = selectedInGroup > 0 || (searchTerm && (group.name.toLowerCase().includes(searchTerm) || group.permissions.some(p => p.name.toLowerCase().includes(searchTerm))));

      html += `
        <div class="permission-group mb-3" data-group-name="${group.name.toLowerCase()}" data-group-id="${group.id}">
          <div class="group-header d-flex justify-content-between align-items-center ${selectedInGroup > 0 ? "active" : ""}">
            <div class="d-flex align-items-center flex-grow-1">
              <div class="permission-icon me-3"><i data-feather="${group.icon || 'shield'}"></i></div>
              <div>
                <h6 class="mb-0 fw-bold">${group.name}</h6>
                <small class="text-muted">${totalInGroup} alt yetki</small>
              </div>
            </div>
            <div class="d-flex align-items-center">
              <span class="badge bg-primary bg-opacity-10 text-primary rounded-pill badge-count me-3 group-selected-count">${selectedInGroup}/${totalInGroup}</span>
              <i class="ti ti-chevron-right arrow-icon ${isGroupInitiallyExpanded ? "rotated" : ""}"></i>
            </div>
          </div>
          <div class="group-body ${isGroupInitiallyExpanded ? "show" : ""}">
            ${group.permissions.map((perm) => `
              <div class="permission-item ${userPermissions.includes(perm.id) ? "selected" : ""}" data-id="${perm.id}" data-perm-name="${perm.name.toLowerCase()}">
                <div class="flex-grow-1">
                  <h6 class="mb-0 fw-semibold perm-name-text">${perm.name}</h6>
                  ${perm.required ? '<span class="badge bg-danger-subtle text-danger-emphasis rounded-pill ms-1">Zorunlu</span>' : ""}
                  <small class="text-muted d-block perm-description-text">${perm.description || ''}</small>
                </div>
                <div class="form-check form-switch ms-3">
                  <input class="form-check-input permission-checkbox" type="checkbox" data-id="${perm.id}" ${userPermissions.includes(perm.id) ? "checked" : ""} ${perm.required ? "disabled" : ""}>
                </div>
              </div>`).join("")}
          </div>
        </div>`;
    });
    cardViewContainer.innerHTML = html;
    if (typeof feather !== 'undefined') feather.replace();
  }

  function renderTreeView() {
    if (!treeViewContainer || !permissionGroups) return;
    let html = "<ul>";
    permissionGroups.forEach((group) => {
      const isGroupInitiallyExpanded = group.permissions.some((p) => userPermissions.includes(p.id)) || (searchTerm && (group.name.toLowerCase().includes(searchTerm) || group.permissions.some(p => p.name.toLowerCase().includes(searchTerm))));

      html += `
        <li>
          <div class="tree-node">
            <a class="tree-toggle ${isGroupInitiallyExpanded ? "expanded" : "collapsed"}" data-group-name="${group.name.toLowerCase()}">
              <i class="ti ti-folder text-warning"></i><i class="ti ti-folder-open text-warning"></i>
              <span class="group-name-text">${group.name}</span>
            </a>
          </div>
          <ul style="display: ${isGroupInitiallyExpanded ? "block" : "none"};">
            ${group.permissions.map((perm) => `
              <li>
                <div class="permission-item" data-id="${perm.id}" data-perm-name="${perm.name.toLowerCase()}">
                  <div class="flex-grow-1">
                    <span class="perm-name-text">${perm.name}</span>
                    ${perm.required ? '<span class="badge bg-danger-subtle text-danger-emphasis rounded-pill ms-1">Zorunlu</span>' : ""}
                  </div>
                  <div class="form-check form-switch ms-3">
                    <input class="form-check-input permission-checkbox" type="checkbox" data-id="${perm.id}" ${userPermissions.includes(perm.id) ? "checked" : ""} ${perm.required ? "disabled" : ""}>
                  </div>
                </div>
              </li>`).join("")}
          </ul>
        </li>`;
    });
    html += "</ul>";
    treeViewContainer.innerHTML = html;
  }

  function renderFilterChips() {
    if (!filterChipsContainer || !permissionGroups) return;
    const uniqueGroupNames = [...new Set(permissionGroups.map((g) => g.name.toLowerCase()))];
    filterChipsContainer.innerHTML = "";

    const allChip = document.createElement("span");
    allChip.className = "filter-chip badge me-2 mb-2";
    allChip.textContent = "Tümünü Göster";
    allChip.dataset.group = "all";
    allChip.classList.toggle("active", selectedGroups.length === 0);
    allChip.classList.toggle("bg-primary", selectedGroups.length === 0);
    allChip.classList.toggle("text-white", selectedGroups.length === 0);
    allChip.classList.toggle("bg-secondary", selectedGroups.length > 0);
    allChip.classList.toggle("text-white", selectedGroups.length > 0); // bg-secondary için de text-white
    filterChipsContainer.appendChild(allChip);

    uniqueGroupNames.forEach((groupName) => {
      const chip = document.createElement("span");
      chip.className = "filter-chip badge me-2 mb-2";
      const originalGroupName = permissionGroups.find(g => g.name.toLowerCase() === groupName)?.name || groupName;
      chip.textContent = originalGroupName;
      chip.dataset.group = groupName;
      const isActive = selectedGroups.includes(groupName);
      chip.classList.toggle("active", isActive);
      chip.classList.toggle("bg-primary", isActive);
      chip.classList.toggle("text-white", isActive);
      chip.classList.toggle("bg-secondary", !isActive);
      chip.classList.toggle("text-white", !isActive); // bg-secondary için de text-white
      filterChipsContainer.appendChild(chip);
    });
  }

  // --- FİLTRELEME ---
  function applyFilters() {
      applyCardViewFilters();
      applyTreeViewFilters();
  }

  function applyCardViewFilters() {
    if (!cardViewContainer || !permissionGroups) return;
    const groupsElements = cardViewContainer.querySelectorAll(".permission-group");

    groupsElements.forEach(groupEl => {
        const groupName = groupEl.dataset.groupName;
        const groupData = permissionGroups.find(g => g.name.toLowerCase() === groupName);
        if (!groupData) {
            groupEl.style.display = "none";
            return;
        }

        const isVisibleByChip = selectedGroups.length === 0 || selectedGroups.includes(groupName);
        let isVisibleBySearch = true;
        let hasVisiblePermsInSearch = false;

        if (searchTerm) {
            isVisibleBySearch = groupName.includes(searchTerm);
            groupEl.querySelectorAll(".permission-item").forEach(itemEl => {
                const permName = itemEl.dataset.permName;
                const matchesSearch = permName.includes(searchTerm);
                itemEl.style.display = matchesSearch ? "flex" : "none";
                if (matchesSearch) hasVisiblePermsInSearch = true;
            });
            if (hasVisiblePermsInSearch) isVisibleBySearch = true; // Eğer iç öğe eşleşirse grubu da göster
        } else {
             groupEl.querySelectorAll(".permission-item").forEach(itemEl => { // Arama yoksa tüm item'ları göster
                itemEl.style.display = "flex";
            });
        }
        
        groupEl.style.display = isVisibleByChip && isVisibleBySearch ? "block" : "none";

        // Arama varsa ve eşleşen öğe varsa grubu aç
        if (isVisibleByChip && isVisibleBySearch && searchTerm && hasVisiblePermsInSearch) {
            const groupBody = groupEl.querySelector(".group-body");
            const arrowIcon = groupEl.querySelector(".arrow-icon");
            if (groupBody && !groupBody.classList.contains("show")) {
                groupBody.classList.add("show");
                if(arrowIcon) arrowIcon.classList.add("rotated");
            }
        }
    });
  }

  function applyTreeViewFilters() {
    if (!treeViewContainer || !permissionGroups) return;
    const groupLiElements = treeViewContainer.querySelectorAll("ul > li");

    groupLiElements.forEach(liEl => {
        const toggleEl = liEl.querySelector(".tree-toggle");
        if (!toggleEl) return;
        const groupName = toggleEl.dataset.groupName;
        const groupData = permissionGroups.find(g => g.name.toLowerCase() === groupName);
        if (!groupData) {
            liEl.style.display = "none";
            return;
        }

        const isVisibleByChip = selectedGroups.length === 0 || selectedGroups.includes(groupName);
        let isVisibleBySearch = true; // Grup seviyesinde arama
        let hasVisiblePermsInSearchInTree = false; // İzin seviyesinde arama

        const permLiElements = liEl.querySelectorAll("ul > li");

        if (searchTerm) {
            isVisibleBySearch = groupName.includes(searchTerm);
            permLiElements.forEach(permLiEl => {
                const permItemEl = permLiEl.querySelector(".permission-item");
                if(permItemEl){
                    const permName = permItemEl.dataset.permName;
                    const matchesSearch = permName.includes(searchTerm);
                    permLiEl.style.display = matchesSearch ? "block" : "none";
                    if(matchesSearch) hasVisiblePermsInSearchInTree = true;
                }
            });
            if (hasVisiblePermsInSearchInTree) isVisibleBySearch = true;
        } else {
            permLiElements.forEach(permLiEl => { // Arama yoksa tüm item'ları göster
                permLiEl.style.display = "block";
            });
        }

        liEl.style.display = isVisibleByChip && isVisibleBySearch ? "block" : "none";
        
        // Dal açma/kapama mantığı
        const subList = liEl.querySelector("ul");
        if (subList) {
            let shouldExpand = false;
            if (isVisibleByChip && isVisibleBySearch) { // Sadece grup görünürse genişletmeyi düşün
                if (groupData.permissions.some(p => userPermissions.includes(p.id))) { // Seçili yetki varsa
                    shouldExpand = true;
                }
                if (searchTerm && hasVisiblePermsInSearchInTree) { // Arama sonucu varsa
                    shouldExpand = true;
                }
                 const isSelectAllActive = userPermissions.length > 0 && userPermissions.length === permissionGroups.flatMap(g => g.permissions).length;
                 if(isSelectAllActive && selectedGroups.length === 0) { // Tümünü seç ve filtre yoksa
                    shouldExpand = true;
                 }
            }

            if (shouldExpand) {
                toggleEl.classList.remove("collapsed");
                toggleEl.classList.add("expanded");
                subList.style.display = "block";
            } else {
                // Eğer arama yoksa ve özel bir genişletme durumu yoksa, kullanıcının son durumunu koru
                // veya varsayılan olarak kapat. Şimdilik arama yoksa ve genişleme nedeni yoksa kapatalım.
                if (!searchTerm) {
                    toggleEl.classList.add("collapsed");
                    toggleEl.classList.remove("expanded");
                    subList.style.display = "none";
                }
            }
        }
    });
  }


  // --- İŞLEM FONKSİYONLARI ---
  function updateSelectedCountUI() {
    if (!selectedCountEl || !requiredCountEl || !permissionGroups) return;
    const selectedIds = new Set(userPermissions);
    const allPermissionsFlat = permissionGroups.flatMap((g) => g.permissions);
    
    selectedCountEl.textContent = selectedIds.size;
    // requiredCountEl.textContent = allPermissionsFlat.filter((p) => p.required).length; // Bu her zaman aynı kalır, gerekirse başta bir kez hesaplanır.

    document.querySelectorAll("#cardViewContainer .permission-group").forEach((groupEl) => {
        const groupName = groupEl.dataset.groupName;
        const groupData = permissionGroups.find(g => g.name.toLowerCase() === groupName);
        if (groupData) {
            const selectedInGroup = groupData.permissions.filter((p) => selectedIds.has(p.id)).length;
            const countDisplay = groupEl.querySelector(".group-selected-count");
            if(countDisplay) countDisplay.textContent = `${selectedInGroup}/${groupData.permissions.length}`;
            const header = groupEl.querySelector(".group-header");
            if(header) header.classList.toggle("active", selectedInGroup > 0);
        }
    });
  }

  function handlePermissionSelection(checkbox) {
    const permId = parseInt(checkbox.dataset.id);
    if (checkbox.checked) {
      if (!userPermissions.includes(permId)) userPermissions.push(permId);
    } else {
      userPermissions = userPermissions.filter((id) => id !== permId);
    }

    document.querySelectorAll(`.permission-checkbox[data-id="${permId}"]`).forEach((cb) => {
        cb.checked = checkbox.checked;
        const item = cb.closest(".permission-item");
        if(item) item.classList.toggle("selected", checkbox.checked);
    });
    updateSelectedCountUI();
    // Filtreleme sonrası açma/kapama durumları değişebileceği için filtreleri yeniden uygula
    applyFilters(); 
  }

  function toggleCardGroup(header) {
    const groupBody = header.nextElementSibling;
    const arrowIcon = header.querySelector(".arrow-icon");
    if (groupBody && arrowIcon) {
        groupBody.classList.toggle("show");
        arrowIcon.classList.toggle("rotated");
    }
  }

  function toggleTreeBranch(toggleLink) {
    toggleLink.classList.toggle("expanded");
    toggleLink.classList.toggle("collapsed");
    const subList = toggleLink.closest(".tree-node").nextElementSibling;
    if (subList) {
        subList.style.display = subList.style.display === "none" ? "block" : "none";
    }
  }

  // --- EVENT LISTENERS ---
  if (permissionSearchInput) {
    permissionSearchInput.addEventListener("input", function () {
        searchTerm = this.value.toLowerCase().trim(); // Global searchTerm'i güncelle
        applyFilters();
    });
  }

  document.body.addEventListener("click", function (e) {
    const target = e.target;

    if (target.closest(".group-header")) {
      toggleCardGroup(target.closest(".group-header"));
    } else if (target.closest(".tree-toggle")) {
      toggleTreeBranch(target.closest(".tree-toggle"));
    } else if (target.classList.contains("permission-checkbox")) {
      handlePermissionSelection(target);
    } else if (target.closest(".permission-item") && !target.classList.contains("form-check-input")) {
      const checkbox = target.closest(".permission-item")?.querySelector(".permission-checkbox");
      if (checkbox && !checkbox.disabled) {
        checkbox.checked = !checkbox.checked;
        handlePermissionSelection(checkbox);
      }
    } else if (target.classList.contains("filter-chip")) {
      const group = target.dataset.group;
      if (group === "all") {
        selectedGroups = [];
      } else {
        const index = selectedGroups.indexOf(group);
        if (index > -1) {
          selectedGroups.splice(index, 1);
        } else {
          selectedGroups.push(group);
        }
      }
      renderFilterChips();
      applyFilters();
    }
  });

  if (document.getElementById("resetChanges")) {
    document.getElementById("resetChanges").addEventListener("click", function () {
        userPermissions = [...originalPermissions];
        selectedGroups = []; // Filtreleri de sıfırla
        if(permissionSearchInput) permissionSearchInput.value = ""; // Aramayı sıfırla
        searchTerm = "";
        renderViews();
        Swal.fire("Değişiklikler Sıfırlandı", "Yetkiler orijinal haline geri alındı.", "info");
    });
  }
  
  $(document).on("click", "#selectAllPermissions", function() {
      const allPermissionIds = permissionGroups.flatMap(group => group.permissions.map(perm => perm.id));
      // Sadece görünür olanları değil, TÜM izinleri seç
      // Zorunlu ve seçilemeyenleri hariç tutmak isterseniz:
      // const allSelectablePermissionIds = permissionGroups.flatMap(group => 
      //     group.permissions.filter(perm => !perm.required).map(perm => perm.id)
      // );
      userPermissions = [...new Set(allPermissionIds)]; // Tümünü seç, duplike olmasın
      renderViews(); // Bu, applyFilters'ı ve dolayısıyla dalların açılmasını tetiklemeli
      Swal.fire("Başarılı", "Tüm yetkiler seçildi.", "success");
  });


  if (document.getElementById("savePermissions")) {
    document.getElementById("savePermissions").addEventListener("click", function () {
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Kaydediliyor...';

        fetch(url, {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `action=savePermissions&id=${encodeURIComponent(roleID)}&permissions=${JSON.stringify(userPermissions)}`,
        })
        .then(response => response.json())
        .then(data => {
            Swal.fire(data.status === "success" ? "Başarılı!" : "Hata!", data.message, data.status);
            if(data.status === "success") {
                originalPermissions = [...userPermissions]; // Başarılı kayıttan sonra orijinali güncelle
            }
        })
        .catch(error => {
            console.error("Kaydetme hatası:", error);
            Swal.fire("Hata!", "Sunucu ile iletişim kurulamadı.", "error");
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="ti ti-device-floppy me-1"></i> Kaydet';
        });
    });
  }

  if (document.getElementById("selectHighlighted")) {
    document.getElementById("selectHighlighted").addEventListener("click", function() {
        if (!searchTerm) {
            Swal.fire("Uyarı", "Lütfen önce bir arama yapın.", "warning");
            return;
        }
        let selectedAny = false;
        const currentViewCheckboxes = showTreeViewCheckbox.checked 
            ? treeViewContainer.querySelectorAll(".permission-checkbox:not(:disabled)")
            : cardViewContainer.querySelectorAll(".permission-checkbox:not(:disabled)");

        currentViewCheckboxes.forEach(checkbox => {
            const item = checkbox.closest(".permission-item");
            // Sadece o an görünür olan item'ları dikkate al (filtre ve arama sonucu)
            if (item && item.style.display !== 'none' && item.offsetParent !== null) { // offsetParent null değilse görünürdür
                 const permName = item.dataset.permName;
                 if (permName.includes(searchTerm) && !checkbox.checked) {
                     checkbox.checked = true;
                     handlePermissionSelection(checkbox);
                     selectedAny = true;
                 }
            }
        });
        Swal.fire(selectedAny ? "Başarılı" : "Uyarı", 
                  selectedAny ? "Arama sonucuyla eşleşen yetkiler seçildi." : "Eşleşen yeni yetki bulunamadı veya zaten seçili.", 
                  selectedAny ? "success" : "info");
    });
  }

  if (showTreeViewCheckbox) {
    showTreeViewCheckbox.addEventListener("change", function () {
        cardViewContainer.style.display = this.checked ? "none" : "block";
        treeViewContainer.style.display = this.checked ? "block" : "none";
        if (permissionSearchInput) permissionSearchInput.value = ""; // Arama kutusunu temizle
        searchTerm = "";
        applyFilters(); // Sadece filtreleri uygula, tam render'a gerek yok
    });
  }

  // Başlangıç yüklemesi
  simulateLoading(true);
});