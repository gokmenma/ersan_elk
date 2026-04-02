/**
 * Notlar Modülü Yönetim Scripti
 * Google Keep tarzı arayüz ve notebook yönetimi
 */

window.notlarManager = {
    activeDefterId: 'tum',
    notlar: [],
    defterler: [],
    initialized: false,
    toastContainer: null,

    init: function() {
        if (this.initialized) return;
        this.initialized = true;
        this.createToastContainer();
        this.initSelect2();
        this.bindEvents();
        this.loadDefterler();
        this.loadNotlar();
    },

    initSelect2: function() {
        if ($.fn.select2) {
            $('#notDefterSecim').select2({
                placeholder: 'Defter seçin',
                width: '100%',
                dropdownParent: $('#notModal')
            });
        }
    },

    createToastContainer: function() {
        if ($('#notlar-toast-container').length === 0) {
            $('body').append('<div id="notlar-toast-container"></div>');
        }
        this.toastContainer = $('#notlar-toast-container');
    },

    showToast: function(message, type = 'info') {
        const toast = $(`<div class="notlar-toast ${type}">${message}</div>`);
        this.toastContainer.append(toast);
        setTimeout(() => toast.remove(), 3000);
    },

    bindEvents: function() {
        const self = this;

        // Yeni Defter Butonu
        $('#btnYeniDefter').on('click', () => self.openDefterModal());
        $('#defterKaydet').on('click', () => self.saveDefter());
        $('#defterIptal').on('click', () => $('#defterModal').removeClass('show'));

        // Defter Sil Butonu
        $('#btnDefterSil').on('click', function() {
            const id = $('#editDefterId').val();
            if(id) self.deleteDefter(id);
        });

        // Yeni Not FAB
        $('#btnYeniNot').on('click', () => self.openNotModal());

        // Not Kaydet
        $('#notKaydet').on('click', () => self.saveNot());
        $('#notIptal').on('click', () => $('#notModal').removeClass('show'));

        // Arama
        $('#notSearchInput').on('input', function() {
            self.filterNotlar($(this).val());
        });

        // Notebook Değişimi
        $(document).on('click', '.nav-item', function() {
            $('.nav-item').removeClass('active');
            $(this).addClass('active');
            self.activeDefterId = $(this).data('id');
            self.loadNotlar();
        });

        // Renk Seçiciler
        $('.renk-secici-item').on('click', function() {
            $(this).parent().find('.renk-secici-item').removeClass('active');
            $(this).addClass('active');
            const targetId = $(this).parent().attr('id');
            const color = $(this).data('renk');
            if(targetId === 'defterRenkSecici') $('#defterRenk').val(color);
        });

        // Defter Seçimi Değiştiğinde Renk Göster
        $('#notDefterSecim').on('change', function() {
            const defterId = $(this).val();
            const defter = self.defterler.find(d => d.id_enc === defterId);
            if (defter) {
                $('#notRenkTrigger').css('background-color', defter.renk);
            }
        });

        // Modal Sil Butonu
        $('#btnNotSil').on('click', function() {
            const id = $('#editNotId').val();
            if(id) {
                $('#notModal').removeClass('show');
                self.deleteNot(id);
            }
        });

        // Enter ile Kayıt
        $('#notBaslik, #notIcerik').on('keypress', function(e) {
            if (e.which == 13 && !e.shiftKey) {
                if ($(this).is('textarea')) return; // Textarea'da normal enter çalışsın (shift+enter değilse?)
                e.preventDefault();
                self.saveNot();
            }
        });

        $('#defterBaslik').on('keypress', function(e) {
            if (e.which == 13) {
                e.preventDefault();
                self.saveDefter();
            }
        });
    },

    loadDefterler: function() {
        const self = this;
        $.get('views/notlar/api.php', { action: 'get-defterler' }, function(res) {
            if (res.success) {
                self.defterler = res.data;
                self.renderDefterler();
                self.updateDefterSelect();
            } else {
                self.showToast(res.message, 'error');
            }
        });
    },

    renderDefterler: function() {
        const container = $('.defter-items');
        container.empty();
        let totalCount = 0;
        this.defterler.forEach(d => {
            totalCount += parseInt(d.not_sayisi);
            const item = $(`
                <div class="nav-item ${this.activeDefterId == d.id_enc ? 'active' : ''}" data-id="${d.id_enc}">
                    <i class="bx ${d.icon}" style="color:${d.renk}"></i>
                    <span>${d.baslik}</span>
                    <span class="badge">${d.not_sayisi}</span>
                    <div class="dropdown ms-auto btn-defter-actions">
                        <button class="btn-defter-edit" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation()">
                            <i class="bx bx-dots-vertical-rounded"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                            <li><a class="dropdown-item d-flex align-items-center gap-2" href="javascript:void(0)" onclick="event.stopPropagation(); window.notlarManager.openDefterModal(null, '${d.id_enc}')">
                                <i class="bx bx-edit-alt"></i> Düzenle
                            </a></li>
                            <li><a class="dropdown-item text-danger d-flex align-items-center gap-2" href="javascript:void(0)" onclick="event.stopPropagation(); window.notlarManager.deleteDefter('${d.id_enc}')">
                                <i class="bx bx-trash"></i> Sil
                            </a></li>
                        </ul>
                    </div>
                </div>
            `);
            container.append(item);
        });
        $('.nav-tum-notlar .badge').text(totalCount);
    },

    updateDefterSelect: function() {
        const select = $('#notDefterSecim');
        select.empty();
        this.defterler.forEach(d => {
            select.append(`<option value="${d.id_enc}">${d.baslik}</option>`);
        });
    },

    loadNotlar: function() {
        const self = this;
        let pId = this.activeDefterId;
        if(pId && pId !== 'tum') {
            // Decrypt logic is handled by API if we send it correctly.
            // For now UI uses real IDs, API decrypts them.
            // Wait, my API uses Encrypted IDs for returns.
            // I should use the correct IDs.
        }
        
        $.post('views/notlar/api.php', { action: 'get-notlar', defter_id: this.activeDefterId }, function(res) {
            if (res.success) {
                self.notlar = res.data;
                self.renderNotlar();
            } else {
                self.showToast(res.message, 'error');
            }
        });
    },

    renderNotlar: function() {
        const container = $('#notlarGrid');
        container.empty();
        if (this.notlar.length === 0) {
            container.append(`
                <div class="notlar-empty">
                    <i class="bx bx-note"></i>
                    <h4>Pek yakında buralar not dolacak...</h4>
                </div>
            `);
            return;
        }

        this.notlar.forEach(n => {
            const card = $(`
                <div class="not-card ${n.pinli == 1 ? 'pinned' : ''}" data-id="${n.id_enc}" style="background-color: ${n.renk || '#fff'}">
                    <div class="not-card-header">
                        <h4 class="not-card-title">${n.baslik || ''}</h4>
                        <div class="header-actions">
                            <button class="btn-pin" onclick="event.stopPropagation(); window.notlarManager.togglePin('${n.id_enc}', ${n.pinli})" title="İğnele">
                                <i class="bx ${n.pinli == 1 ? 'bxs-pin' : 'bx-pin'}"></i>
                            </button>
                            <div class="dropdown d-inline-block">
                                <button class="btn-more" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation()">
                                    <i class="bx bx-dots-vertical-rounded"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                                    <li><a class="dropdown-item text-danger d-flex align-items-center gap-2" href="javascript:void(0)" onclick="event.stopPropagation(); window.notlarManager.deleteNot('${n.id_enc}')">
                                        <i class="bx bx-trash"></i> Sil
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="not-card-content">${n.icerik || ''}</div>
                    <div class="not-card-footer">
                        <span class="not-card-defter" style="border-left: 3px solid ${n.defter_renk}">${n.defter_adi}</span>
                        <span>${this.formatDate(n.updated_at)}</span>
                    </div>
                </div>
            `);
            card.on('click', () => this.openNotModal(n));
            container.append(card);
        });
    },

    openNotModal: function(notData = null) {
        if (notData) {
            $('#notModalTitle').text('Notu Düzenle');
            $('#notBaslik').val(notData.baslik);
            $('#notIcerik').val(notData.icerik);
            $('#editNotId').val(notData.id_enc);
            $('#btnNotSil').show();

            // Set select2 value carefully
            if (notData.defter_id_enc) {
                $('#notDefterSecim').val(notData.defter_id_enc).trigger('change');
            }
        } else {
            $('#notModalTitle').text('Yeni Not');
            $('#notBaslik').val('');
            $('#notIcerik').val('');
            $('#editNotId').val('');
            $('#btnNotSil').hide();
            
            if (this.activeDefterId && this.activeDefterId !== 'tum') {
                $('#notDefterSecim').val(this.activeDefterId).trigger('change');
            } else if (this.defterler.length > 0) {
                $('#notDefterSecim').val(this.defterler[0].id_enc).trigger('change');
            }
        }
        $('#notModal').addClass('show');
    },

    saveNot: function() {
        const self = this;
        const data = {
            action: $('#editNotId').val() ? 'update-not' : 'add-not',
            not_id: $('#editNotId').val(),
            baslik: $('#notBaslik').val(),
            icerik: $('#notIcerik').val(),
            defter_id: $('#notDefterSecim').val()
        };

        const $btn = $('#notKaydet');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span> Kaydediliyor...');

        $.post('views/notlar/api.php', data, function(res) {
            $btn.prop('disabled', false).html(originalHtml);
            if (res.success) {
                $('#notModal').removeClass('show');
                self.loadNotlar();
                self.loadDefterler();
            } else {
                self.showToast(res.message || 'Hata oluştu', 'error');
            }
        });
    },

    deleteNot: function(id_enc) {
        const self = this;
        Swal.fire({
            title: 'Notu silmek istediğinize emin misiniz?',
            text: "Bu işlem geri alınamaz!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ea4335',
            cancelButtonColor: '#5f6368',
            confirmButtonText: 'Evet, sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/notlar/api.php', { action: 'delete-not', not_id: id_enc }, function(res) {
                    if (res.success) {
                        self.loadNotlar();
                        self.showToast('Not silindi', 'success');
                    } else {
                        self.showToast(res.message || 'Silinemedi', 'error');
                    }
                });
            }
        });
    },

    togglePin: function(id_enc, currentPin) {
        const self = this;
        $.post('views/notlar/api.php', { action: 'pin-not', not_id: id_enc, pinli: currentPin == 1 ? 0 : 1 }, function(res) {
            if (res.success) {
                self.loadNotlar();
            }
        });
    },

    openDefterModal: function(e = null, id_enc = null) {
        if(e) e.stopPropagation();
        
        if (id_enc) {
            const defter = this.defterler.find(d => d.id_enc === id_enc);
            if (!defter) return;
            
            $('#defterModalTitle').text('Defteri Düzenle');
            $('#defterBaslik').val(defter.baslik);
            $('#editDefterId').val(id_enc);
            $('#defterRenk').val(defter.renk);
            $('#btnDefterSil').show();
            
            // Mark active color
            $('.renk-secici-item').removeClass('active');
            $(`.renk-secici-item[data-renk="${defter.renk}"]`).addClass('active');
        } else {
            $('#defterModalTitle').text('Yeni Defter');
            $('#defterBaslik').val('');
            $('#editDefterId').val('');
            $('#defterRenk').val('#4285f4');
            $('#btnDefterSil').hide();
            
            $('.renk-secici-item').removeClass('active');
            $(`.renk-secici-item[data-renk="#4285f4"]`).addClass('active');
        }
        $('#defterModal').addClass('show');
    },

    saveDefter: function() {
        const self = this;
        const data = {
            action: $('#editDefterId').val() ? 'update-defter' : 'add-defter',
            defter_id: $('#editDefterId').val(),
            baslik: $('#defterBaslik').val(),
            renk: $('#defterRenk').val()
        };

        $.post('views/notlar/api.php', data, function(res) {
            if (res.success) {
                $('#defterModal').removeClass('show');
                self.loadDefterler();
                self.showToast('Defter kaydedildi', 'success');
            } else {
                self.showToast(res.message || 'Kaydedilemedi', 'error');
            }
        });
    },

    deleteDefter: function(id_enc) {
        const self = this;
        Swal.fire({
            title: 'Defteri silmek istediğinize emin misiniz?',
            text: "Bu defterdeki tüm notlar silinecektir! Bu işlem geri alınamaz.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ea4335',
            cancelButtonColor: '#5f6368',
            confirmButtonText: 'Evet, her şeyi sil!',
            cancelButtonText: 'İptal'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('views/notlar/api.php', { action: 'delete-defter', defter_id: id_enc }, function(res) {
                    if (res.success) {
                        $('#defterModal').removeClass('show');
                        self.activeDefterId = 'tum';
                        self.loadDefterler();
                        self.loadNotlar();
                        self.showToast('Defter ve notlar silindi', 'success');
                    } else {
                        self.showToast(res.message || 'Silinemedi', 'error');
                    }
                });
            }
        });
    },

    filterNotlar: function(query) {
        const q = query.toLowerCase();
        $('.not-card').each(function() {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(q) > -1);
        });
    },

    formatDate: function(dateStr) {
        if(!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString('tr-TR');
    }
};
