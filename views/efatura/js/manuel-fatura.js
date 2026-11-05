(function ($) {
    'use strict';

    const API = 'api/efatura-api.php';
    const STOPAJLI = ['SMM', 'GIDER_PUSULASI'];
    const KDVSIZ = ['GIDER_PUSULASI', 'MAKBUZ'];
    let satirSayaci = 0;
    let datePicker = null;
    let belgeToplam = null;

    const $modal = () => $('#modalManuelFatura');
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (m) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[m]));
    const tl = (v) => Number(v || 0).toLocaleString('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ₺';
    const num = (v) => {
        const n = parseFloat(String(v ?? '').replace(',', '.'));
        return Number.isFinite(n) ? n : 0;
    };
    const round2 = (v) => Math.round((v + Number.EPSILON) * 100) / 100;

    function initSelect2($el) {
        if ($el.hasClass('select2-hidden-accessible')) {
            $el.select2('destroy');
        }
        $el.select2({ dropdownParent: $modal(), width: '100%', minimumResultsForSearch: 8 });
    }

    function belgeTuru() {
        return String($('#manuel_belge_turu').val() || 'KAGIT');
    }

    function turDegisti() {
        const tur = belgeTuru();
        $modal().toggleClass('manuel-stopajli', STOPAJLI.includes(tur));
        const kdvsiz = KDVSIZ.includes(tur);
        $('#manuelSatirlar .satir-kdv').each(function () {
            if (kdvsiz) {
                $(this).val('0').trigger('change.select2');
            }
            $(this).prop('disabled', kdvsiz);
        });
        if (STOPAJLI.includes(tur) && $('#manuel_stopaj_orani').val() === '') {
            $('#manuel_stopaj_orani').val('20');
        }
        hesapla();
    }

    function satirEkle(veri) {
        const tpl = document.getElementById('manuelSatirSablon');
        const $satir = $(tpl.content.firstElementChild.cloneNode(true));
        satirSayaci += 1;
        $satir.find('[id]').each(function () {
            const eski = this.id;
            const yeni = eski + '_' + satirSayaci;
            this.id = yeni;
            this.removeAttribute('name');
            $satir.find('label[for="' + eski + '"]').attr('for', yeni);
        });
        $('#manuelSatirlar').append($satir);

        const d = veri || {};
        $satir.find('.satir-ad').val(d.urun_hizmet_adi || '');
        $satir.find('.satir-miktar').val(d.miktar !== undefined && d.miktar !== null ? d.miktar : 1);
        $satir.find('.satir-fiyat').val(d.birim_fiyat !== undefined && d.birim_fiyat !== null ? d.birim_fiyat : '');
        $satir.find('.satir-iskonto').val(d.iskonto_orani || 0);

        const $birim = $satir.find('.satir-birim');
        if (d.birim && $birim.find('option[value="' + d.birim + '"]').length) {
            $birim.val(d.birim);
        }
        const $kdv = $satir.find('.satir-kdv');
        if (d.kdv_orani !== undefined && d.kdv_orani !== null) {
            const oran = String(Number(d.kdv_orani));
            if (!$kdv.find('option[value="' + oran + '"]').length) {
                $kdv.append(new Option('%' + oran, oran));
            }
            $kdv.val(oran);
        }
        initSelect2($birim);
        initSelect2($kdv);
        numaralandir();
        turDegisti();
        return $satir;
    }

    function numaralandir() {
        $('#manuelSatirlar .manuel-satir').each(function (i) {
            $(this).find('.manuel-satir-no').text((i + 1) + '. kalem');
        });
    }

    function satirlariTopla() {
        return $('#manuelSatirlar .manuel-satir').map(function () {
            const $s = $(this);
            return {
                urun_hizmet_adi: $.trim($s.find('.satir-ad').val()),
                miktar: num($s.find('.satir-miktar').val()),
                birim: $s.find('.satir-birim').val() || 'C62',
                birim_fiyat: num($s.find('.satir-fiyat').val()),
                iskonto_orani: num($s.find('.satir-iskonto').val()),
                kdv_orani: num($s.find('.satir-kdv').val()),
            };
        }).get();
    }

    function hesapla() {
        let brut = 0, iskonto = 0;
        const kdvGruplari = {};
        $('#manuelSatirlar .manuel-satir').each(function () {
            const $s = $(this);
            const miktar = num($s.find('.satir-miktar').val());
            const fiyat = num($s.find('.satir-fiyat').val());
            const isk = num($s.find('.satir-iskonto').val());
            const kdv = num($s.find('.satir-kdv').val());
            const satirBrut = round2(miktar * fiyat);
            const satirIsk = round2(satirBrut * isk / 100);
            const matrah = miktar * fiyat - satirIsk;
            const satirKdv = round2(matrah * kdv / 100);
            brut += satirBrut;
            iskonto += satirIsk;
            kdvGruplari[kdv] = (kdvGruplari[kdv] || 0) + matrah;
            $s.find('.manuel-satir-tutar').text(tl(round2(matrah) + satirKdv));
        });
        let matrahToplam = 0, kdvToplam = 0;
        Object.keys(kdvGruplari).forEach((oran) => {
            matrahToplam += kdvGruplari[oran];
            kdvToplam += round2(kdvGruplari[oran] * Number(oran) / 100);
        });
        matrahToplam = round2(matrahToplam);
        kdvToplam = round2(kdvToplam);
        const stopajOrani = STOPAJLI.includes(belgeTuru()) ? num($('#manuel_stopaj_orani').val()) : 0;
        const stopaj = round2(matrahToplam * stopajOrani / 100);
        const odenecek = round2(matrahToplam + kdvToplam - stopaj);

        $('#manuelTopSatir').text(tl(brut));
        $('#manuelTopIskonto').text(tl(iskonto));
        $('#manuelTopMatrah').text(tl(matrahToplam));
        $('#manuelTopKdv').text(tl(kdvToplam));
        $('#manuelTopStopaj').text('-' + tl(stopaj));
        $('#manuelTopOdenecek').text(tl(odenecek));

        const $uyari = $('#manuelToplamUyari');
        if (belgeToplam !== null && belgeToplam !== undefined) {
            $('#manuelBelgeToplamSatiri').removeClass('d-none');
            $('#manuelBelgeToplam').text(tl(belgeToplam));
            const fark = round2(odenecek - belgeToplam);
            if (Math.abs(fark) > 0.05) {
                $uyari.html('<span class="text-danger"><i class="bx bx-error-circle me-1"></i>Hesaplanan ödenecek tutar belgede yazandan ' + esc(tl(Math.abs(fark))) + ' ' + (fark > 0 ? 'fazla' : 'eksik') + '. Kalemleri kontrol ediniz.</span>');
            } else {
                $uyari.html('<span class="text-success"><i class="bx bx-check-circle me-1"></i>Hesaplanan toplam belgedeki tutarla uyumlu.</span>');
            }
        } else {
            $('#manuelBelgeToplamSatiri').addClass('d-none');
            $uyari.empty();
        }
    }

    function sonucGoster(html, cls) {
        $('#manuelAiSonuc').attr('class', 'font-size-12 mt-2 ' + (cls || '')).html(html);
    }

    function cariDurum(cari, unvanVar) {
        const $el = $('#manuelCariDurum');
        if (cari && cari.ad) {
            $el.html('<span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold text-none" style="text-transform:none;letter-spacing:0"><i class="bx bx-link me-1"></i>Eşleşen cari: ' + esc(cari.ad) + '</span>');
        } else if (unvanVar) {
            $el.html('<span class="badge bg-warning-subtle text-warning border border-warning-subtle rounded-pill px-2 py-1 font-size-11 fw-semibold" style="text-transform:none;letter-spacing:0"><i class="bx bx-error me-1"></i>Eşleşen cari yok — kayıt cariye işlenmeyecek</span>');
        } else {
            $el.empty();
        }
    }

    function formuDoldur(header, lines) {
        const h = header || {};
        if (h.belge_turu) {
            $('#manuel_belge_turu').val(h.belge_turu).trigger('change.select2');
        }
        $('#manuel_fatura_no').val(h.fatura_no || '');
        if (datePicker && h.fatura_tarihi) {
            datePicker.setDate(h.fatura_tarihi, false, 'd.m.Y');
        } else {
            $('#manuel_fatura_tarihi').val(h.fatura_tarihi || '');
        }
        $('#manuel_ettn').val(h.ettn || '');
        $('#manuel_siparis_no').val(h.siparis_no || '');
        $('#manuel_stopaj_orani').val(h.stopaj_orani !== null && h.stopaj_orani !== undefined ? h.stopaj_orani : '');
        $('#manuel_unvan').val(h.alici_unvan || '');
        $('#manuel_vkn').val(h.alici_vkn_tckn || '');
        $('#manuel_vergi_dairesi').val(h.alici_vergi_dairesi || '');
        $('#manuel_adres').val(h.alici_adres || '');
        $('#manuel_ilce').val(h.alici_ilce || '');
        $('#manuel_il').val(h.alici_il || '');
        $('#manuel_notlar').val(h.notlar || '');

        $('#manuelSatirlar').empty();
        (lines && lines.length ? lines : [null]).forEach((l) => satirEkle(l));
        turDegisti();
    }

    function sifirla() {
        belgeToplam = null;
        $('#manuel_invoice_id').val('');
        $('#manuel_belge').val('');
        $('#formManuelFatura')[0].reset();
        $('#manuelAiPanel').show();
        $('#modalManuelFaturaLabel').text('Manuel Fatura / Makbuz Ekle');
        sonucGoster('', '');
        cariDurum(null, false);
        formuDoldur({ belge_turu: 'KAGIT' }, []);
    }

    function hazirla() {
        initSelect2($('#manuel_belge_turu'));
        if (!datePicker && typeof flatpickr !== 'undefined') {
            datePicker = flatpickr('#manuel_fatura_tarihi', {
                dateFormat: 'd.m.Y',
                locale: 'tr',
                allowInput: true,
                maxDate: 'today',
                appendTo: $modal().find('.modal-body')[0],
            });
        }
    }

    function tabloyuYenile() {
        if ($.fn.dataTable && $.fn.dataTable.isDataTable('#tblGelenFaturalar')) {
            $('#tblGelenFaturalar').DataTable().ajax.reload(null, false);
        }
    }

    function payload() {
        return {
            belge_turu: belgeTuru(),
            fatura_no: $.trim($('#manuel_fatura_no').val()),
            fatura_tarihi: $.trim($('#manuel_fatura_tarihi').val()),
            ettn: $.trim($('#manuel_ettn').val()),
            siparis_no: $.trim($('#manuel_siparis_no').val()),
            stopaj_orani: $('#manuel_stopaj_orani').val(),
            alici_unvan: $.trim($('#manuel_unvan').val()),
            alici_vkn_tckn: $.trim($('#manuel_vkn').val()),
            alici_vergi_dairesi: $.trim($('#manuel_vergi_dairesi').val()),
            alici_adres: $.trim($('#manuel_adres').val()),
            alici_ilce: $.trim($('#manuel_ilce').val()),
            alici_il: $.trim($('#manuel_il').val()),
            notlar: $.trim($('#manuel_notlar').val()),
            satirlar: satirlariTopla(),
        };
    }

    function kaydet(force) {
        const $btn = $('#btnManuelKaydet');
        const html = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Kaydediliyor...');
        $.ajax({
            url: API + '?action=manuel_fatura_kaydet',
            type: 'POST',
            dataType: 'json',
            data: { payload: JSON.stringify(payload()), invoice_id: $('#manuel_invoice_id').val(), force: force ? '1' : '0' },
        }).done(function (res) {
            if (res.status === 'warning_duplicate') {
                Swal.fire({
                    title: 'Mükerrer Olabilir',
                    text: res.message,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yine de Kaydet',
                    cancelButtonText: 'Vazgeç',
                    confirmButtonColor: '#f59e0b',
                }).then((r) => { if (r.isConfirmed) kaydet(true); });
                return;
            }
            if (res.status !== 'success') {
                Swal.fire('Kaydedilemedi', res.message || 'Belge kaydedilemedi.', 'error');
                return;
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalManuelFatura')).hide();
            Swal.fire({ title: 'Kaydedildi', text: res.message, icon: 'success', timer: 2600, showConfirmButton: false });
            tabloyuYenile();
        }).fail(function (xhr) {
            Swal.fire('Kaydedilemedi', (xhr.responseJSON && xhr.responseJSON.message) || 'Belge kaydedilemedi.', 'error');
        }).always(function () {
            $btn.prop('disabled', false).html(html);
        });
    }

    function aiDoldur() {
        const file = $('#manuel_belge')[0].files[0];
        if (!file) {
            sonucGoster('Önce fatura veya makbuz belgesini seçiniz.', 'text-warning');
            return;
        }
        if (file.size > 12 * 1024 * 1024) {
            sonucGoster('Belge en fazla 12 MB olabilir.', 'text-danger');
            return;
        }
        const $btn = $('#btnManuelAiDoldur');
        const html = $btn.html();
        const data = new FormData();
        data.append('belge', file, file.name);
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-2"></span>Belge okunuyor...');
        sonucGoster('<span class="spinner-border spinner-border-sm me-2"></span>Belge yapay zekâ ile inceleniyor, bu işlem birkaç saniye sürebilir...', 'text-muted');

        $.ajax({
            url: API + '?action=manuel_fatura_analiz',
            type: 'POST',
            data: data,
            processData: false,
            contentType: false,
            dataType: 'json',
        }).done(function (res) {
            if (res.status !== 'success' || !res.data) {
                sonucGoster(esc(res.message || 'Belge okunamadı.'), 'text-danger');
                return;
            }
            const d = res.data;
            belgeToplam = d.belge_toplam && d.belge_toplam.odenecek_tutar !== null ? Number(d.belge_toplam.odenecek_tutar) : null;
            formuDoldur(d.header, d.lines);
            cariDurum(d.eslesen_cari, !!(d.header && d.header.alici_unvan));

            const parcalar = ['<div class="text-success"><i class="bx bx-check-circle me-1"></i>Belge okundu; ' + (d.lines || []).length + ' kalem dolduruldu. Lütfen alanları kontrol ediniz.</div>'];
            if (d.mukerrer) {
                parcalar.push('<div class="text-danger mt-1"><i class="bx bx-copy me-1"></i>Benzer kayıt mevcut: ' + esc(d.mukerrer.fatura_tarihi) + ' · ' + esc(d.mukerrer.fatura_no || '-') + ' · ' + esc(d.mukerrer.tutar) + ' ₺ (' + esc(d.mukerrer.unvan) + ')</div>');
            }
            (d.uyarilar || []).forEach((u) => parcalar.push('<div class="text-warning mt-1"><i class="bx bx-error me-1"></i>' + esc(u) + '</div>'));
            sonucGoster(parcalar.join(''), '');
        }).fail(function (xhr) {
            sonucGoster(esc((xhr.responseJSON && xhr.responseJSON.message) || 'Belge okunamadı.'), 'text-danger');
        }).always(function () {
            $btn.prop('disabled', false).html(html);
        });
    }

    function ac() {
        hazirla();
        sifirla();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalManuelFatura')).show();
    }

    function duzenle(encId) {
        hazirla();
        $.getJSON(API, { action: 'manuel_fatura_getir', invoice_id: encId }).done(function (res) {
            if (res.status !== 'success') {
                Swal.fire('Hata', res.message || 'Belge getirilemedi.', 'error');
                return;
            }
            sifirla();
            $('#manuel_invoice_id').val(encId);
            $('#manuelAiPanel').hide();
            $('#modalManuelFaturaLabel').text('Manuel Belgeyi Düzenle');
            formuDoldur(res.data.header, res.data.lines);
            cariDurum(res.data.eslesen_cari, true);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalManuelFatura')).show();
        }).fail(function (xhr) {
            Swal.fire('Hata', (xhr.responseJSON && xhr.responseJSON.message) || 'Belge getirilemedi.', 'error');
        });
    }

    function sil(encId, no) {
        Swal.fire({
            title: 'Manuel Belgeyi Sil?',
            html: esc(no || 'Bu belge') + ' silinecek. Cariye işlendiyse cari hareketi de kaldırılır.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Evet, Sil',
            cancelButtonText: 'Vazgeç',
            confirmButtonColor: '#ef4444',
        }).then(function (r) {
            if (!r.isConfirmed) return;
            $.ajax({ url: API + '?action=manuel_fatura_sil', type: 'POST', dataType: 'json', data: { invoice_id: encId } })
                .done(function (res) {
                    if (res.status !== 'success') {
                        Swal.fire('Silinemedi', res.message || 'Belge silinemedi.', 'error');
                        return;
                    }
                    Swal.fire({ title: 'Silindi', text: res.message, icon: 'success', timer: 2000, showConfirmButton: false });
                    tabloyuYenile();
                })
                .fail(function (xhr) {
                    Swal.fire('Silinemedi', (xhr.responseJSON && xhr.responseJSON.message) || 'Belge silinemedi.', 'error');
                });
        });
    }

    $(function () {
        if (!document.getElementById('modalManuelFatura')) return;

        $(document).on('click', '#btnManuelFaturaEkle', ac);
        $(document).on('click', '#btnManuelAiDoldur', aiDoldur);
        $(document).on('click', '#btnManuelKaydet', function () { kaydet(false); });
        $(document).on('click', '#btnManuelSatirEkle', function () { satirEkle(null).find('.satir-ad').trigger('focus'); });
        $(document).on('click', '.btn-manuel-satir-sil', function () {
            $(this).closest('.manuel-satir').remove();
            if (!$('#manuelSatirlar .manuel-satir').length) satirEkle(null);
            numaralandir();
            hesapla();
        });
        $(document).on('input change', '#manuelSatirlar .satir-hesap, #manuel_stopaj_orani', hesapla);
        $(document).on('change', '#manuel_belge_turu', turDegisti);
        $(document).on('input', '#manuel_vkn', function () { this.value = this.value.replace(/\D/g, '').slice(0, 11); });
        $(document).on('click', '.btn-manuel-duzenle', function (e) { e.stopPropagation(); duzenle($(this).data('id')); });
        $(document).on('click', '.btn-manuel-sil', function (e) { e.stopPropagation(); sil($(this).data('id'), $(this).data('fatura-no')); });
    });
})(jQuery);
