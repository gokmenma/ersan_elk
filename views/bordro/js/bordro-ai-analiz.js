/**
 * Yapay Zeka Bordro Denetimi ve Karşılaştırmalı Risk Analiz Sayfası JavaScript Modülü
 */
$(document).ready(function () {
    let currentAuditData = null;
    let auditDataTable = null;
    let activeRiskFilter = 'all';

    // Sayı / Para birimi formatlayıcı
    function formatMoney(amount) {
        if (amount === null || amount === undefined) return '-';
        return new Intl.NumberFormat('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' ₺';
    }

    // Select2 Başlat
    if ($.fn.select2) {
        $('.select2').select2({
            width: '100%'
        });
    }

    // Analizi Çalıştır
    function loadPageAuditData(forceLlm = true) {
        const donemId = $('#auditDonemSelect').val();
        const compareDonemId = $('#auditCompareDonemSelect').val();

        if (!donemId) {
            Swal.fire({
                icon: 'warning',
                title: 'Dönem Seçilmedi',
                text: 'Lütfen analiz edilecek bordro dönemini seçiniz.'
            });
            return;
        }

        $('#pageAuditLoading').show();
        $('#pageAuditContent').hide();
        $('#btnRunPageAudit').prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i> Analiz Ediliyor...');

        $.ajax({
            url: '/views/bordro/api.php',
            type: 'POST',
            data: {
                action: 'ai-bordro-audit',
                donem_id: donemId,
                compare_donem_id: compareDonemId || '',
                use_llm: forceLlm ? 1 : 0
            },
            dataType: 'json',
            success: function (response) {
                $('#pageAuditLoading').hide();
                $('#btnRunPageAudit').prop('disabled', false).html('<i class="mdi mdi-robot fs-5 me-1"></i> Yeniden Analiz Et');

                if (response.status === 'success' && response.data) {
                    currentAuditData = response.data;
                    renderPageDashboard(response.data);
                    $('#pageAuditContent').fadeIn(200);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Analiz Hatası',
                        text: response.message || 'Denetim verileri yüklenemedi.'
                    });
                }
            },
            error: function (xhr, status, error) {
                $('#pageAuditLoading').hide();
                $('#btnRunPageAudit').prop('disabled', false).html('<i class="mdi mdi-robot fs-5 me-1"></i> Yeniden Analiz Et');
                Swal.fire({
                    icon: 'error',
                    title: 'Sunucu Hatası',
                    text: 'Denetim isteği işlenirken bir sunucu hatası meydana geldi.'
                });
            }
        });
    }

    // Dashboard ve Kartları Doldur
    function renderPageDashboard(data) {
        const summary = data.summary || {};
        const prev = summary.onceki_donem || null;
        const allRows = data.all_rows || [];
        const aiReport = data.ai_report || '';

        // 1. Üst KPI Kartları
        const score = summary.saglik_skoru || 100;
        $('#pageHealthScore').text(score);
        $('#pageHealthProgressBar').css('width', score + '%');

        let scoreClass = 'text-success';
        let barClass = 'bg-success';
        let statusText = 'Güvenli & Stabil';

        if (score < 70) {
            scoreClass = 'text-danger';
            barClass = 'bg-danger';
            statusText = 'Yüksek Risk & Acil Aksiyon';
        } else if (score < 90) {
            scoreClass = 'text-warning';
            barClass = 'bg-warning';
            statusText = 'İnceleme Gerektiren Riskler';
        }

        $('#pageHealthScore').removeClass('text-success text-warning text-danger').addClass(scoreClass);
        $('#pageHealthProgressBar').removeClass('bg-success bg-warning bg-danger').addClass(barClass);
        $('#pageHealthStatusText').text(statusText);

        $('#pageRiskAmount').text(formatMoney(summary.tahmini_finansal_risk || 0));
        $('#pageCriticalCount').text(summary.kritik_hata_sayisi || 0);

        // Karşılaştırma Dönemi Metrikleri
        if (prev) {
            $('#pageCompareLabel').text(prev.donem_adi + ' Kıyas');
            const diff = prev.net_farki || 0;
            const diffPrefix = diff > 0 ? '+' : '';
            $('#pageCompareDiff').text(diffPrefix + formatMoney(diff));
            if (diff > 0) {
                $('#pageCompareDiff').addClass('text-danger').removeClass('text-success text-dark');
            } else if (diff < 0) {
                $('#pageCompareDiff').addClass('text-success').removeClass('text-danger text-dark');
            } else {
                $('#pageCompareDiff').addClass('text-dark').removeClass('text-danger text-success');
            }
            const pDiff = prev.personel_farki || 0;
            const pDiffPrefix = pDiff > 0 ? '+' : '';
            $('#pageComparePersonnelDiff').text('Personel Sayısı: ' + summary.toplam_personel + ' (' + pDiffPrefix + pDiff + ' kişi)');
        } else {
            $('#pageCompareLabel').text('Karşılaştırma Yok');
            $('#pageCompareDiff').text('-');
            $('#pageComparePersonnelDiff').text('Kıyaslanacak önceki dönem bulunamadı');
        }

        // 2. AI Yönetici Özeti (Markdown HTML Biçimlendirme)
        let formattedReport = aiReport
            .replace(/### (.*?)\n/g, '<h5 class="fw-bold text-primary mt-2 mb-2">$1</h5>')
            .replace(/#### (.*?)\n/g, '<h6 class="fw-bold text-dark mt-3 mb-2">$1</h6>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/`(.*?)`/g, '<code class="px-2 py-0 bg-light rounded text-dark border fw-medium">$1</code>')
            .replace(/- (.*?)\n/g, '<div class="d-flex align-items-baseline gap-2 mb-1"><i class="mdi mdi-circle-medium text-primary fs-5"></i><span>$1</span></div>')
            .replace(/\n\n/g, '<div class="my-2"></div>');

        $('#pageAiReportText').html(formattedReport);

        // 3. Dağıtım Tablosu
        $('#pageSummaryDonemName').text(summary.donem_adi || '');
        $('#distCurrBanka').text(formatMoney(summary.toplam_banka || 0));
        $('#distCurrElden').text(formatMoney(summary.toplam_elden || 0));
        $('#distCurrYemek').text(formatMoney(summary.toplam_yemek || 0));
        $('#distCurrTotal').text(formatMoney(summary.toplam_net_hakedis || 0));

        if (prev) {
            $('#distPrevBanka').text(formatMoney(prev.toplam_banka || 0));
            $('#distPrevElden').text(formatMoney(prev.toplam_elden || 0));
            $('#distPrevYemek').text(formatMoney(prev.toplam_yemek || 0));
            $('#distPrevTotal').text(formatMoney(prev.toplam_net_hakedis || 0));

            const diffBanka = (summary.toplam_banka || 0) - (prev.toplam_banka || 0);
            const diffElden = (summary.toplam_elden || 0) - (prev.toplam_elden || 0);
            const diffYemek = (summary.toplam_yemek || 0) - (prev.toplam_yemek || 0);
            const diffTotal = (summary.toplam_net_hakedis || 0) - (prev.toplam_net_hakedis || 0);

            $('#distDiffBanka').text((diffBanka > 0 ? '+' : '') + formatMoney(diffBanka));
            $('#distDiffElden').text((diffElden > 0 ? '+' : '') + formatMoney(diffElden));
            $('#distDiffYemek').text((diffYemek > 0 ? '+' : '') + formatMoney(diffYemek));
            $('#distDiffTotal').text((diffTotal > 0 ? '+' : '') + formatMoney(diffTotal));
        } else {
            $('#distPrevBanka, #distPrevElden, #distPrevYemek, #distPrevTotal').text('-');
            $('#distDiffBanka, #distDiffElden, #distDiffYemek, #distDiffTotal').text('-');
        }

        // Personel Dağılım İlerleme Çubuğu
        const totalP = summary.toplam_personel || 1;
        const cleanP = summary.sorunsuz_personel_sayisi || 0;
        const issueP = summary.sorunlu_personel_sayisi || 0;
        const cleanPct = Math.round((cleanP / totalP) * 100);
        const issuePct = 100 - cleanPct;

        $('#badgeTotalPersonnel').text(totalP + ' Personel');
        $('#progCleanPersonnel').css('width', cleanPct + '%');
        $('#progCriticalPersonnel').css('width', issuePct + '%');
        $('#lblCleanCount').text(cleanP + ' (%' + cleanPct + ')');
        $('#lblIssueCount').text(issueP + ' (%' + issuePct + ')');

        // Filtre Sayaçları
        $('#filterAllCount').text(allRows.length);
        let countCritical = 0;
        let countWarning = 0;
        let countClean = 0;

        allRows.forEach(function (r) {
            if (!r.has_issue) {
                countClean++;
            } else {
                let hasCrit = r.issues.some(i => i.severity === 'CRITICAL');
                if (hasCrit) countCritical++;
                else countWarning++;
            }
        });

        $('#filterCriticalCount').text(countCritical);
        $('#filterWarningCount').text(countWarning);
        $('#filterCleanCount').text(countClean);

        // 4. DataTables Tablosunu Doldur
        renderPersonelTable(allRows);
    }

    // Personel Tablosunu Render Et
    function renderPersonelTable(rows) {
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#auditPersonelTable')) {
            $('#auditPersonelTable').DataTable().destroy();
        }

        const tbody = $('#auditPersonelTableBody');
        tbody.empty();

        rows.forEach(function (p) {
            let riskBadge = '<span class="badge bg-success-subtle text-success rounded-pill px-2 py-1"><i class="mdi mdi-check-circle"></i> Sorunsuz</span>';
            let riskType = 'clean';

            if (p.has_issue) {
                let hasCrit = p.issues.some(i => i.severity === 'CRITICAL');
                if (hasCrit) {
                    riskBadge = '<span class="badge bg-danger-subtle text-danger rounded-pill px-2 py-1"><i class="mdi mdi-alert-octagon"></i> Kritik Risk</span>';
                    riskType = 'critical';
                } else {
                    riskBadge = '<span class="badge bg-warning-subtle text-warning rounded-pill px-2 py-1"><i class="mdi mdi-alert"></i> Uyarı</span>';
                    riskType = 'warning';
                }
            }

            let issuesDetailHtml = '';
            if (p.has_issue) {
                p.issues.forEach(function (iss) {
                    let bClass = iss.severity === 'CRITICAL' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning';
                    issuesDetailHtml += `
                        <div class="mb-2 pb-1 border-bottom border-light">
                            <div class="d-flex align-items-center gap-1 mb-1">
                                <span class="badge ${bClass} rounded-pill d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                    ${iss.title}
                                </span>
                                ${iss.risk_amount > 0 ? `<span class="badge bg-light text-dark border ms-auto">${formatMoney(iss.risk_amount)}</span>` : ''}
                            </div>
                            <div class="small text-muted mb-1">${iss.description}</div>
                            <div class="small text-primary"><i class="mdi mdi-arrow-right-bold me-1"></i> ${iss.action_recommendation}</div>
                        </div>
                    `;
                });
            } else {
                issuesDetailHtml = '<span class="text-muted small"><i class="mdi mdi-check text-success me-1"></i>Tüm dağıtım ve kurallar mevzuata uygun.</span>';
            }

            // Önceki dönem kıyas hücresi
            let compareHtml = '-';
            if (p.onceki_net !== null && p.onceki_net !== undefined) {
                const diff = (p.net_alacagi || 0) - p.onceki_net;
                const diffPrefix = diff > 0 ? '+' : '';
                const diffColor = diff > 0 ? 'text-primary' : (diff < 0 ? 'text-danger' : 'text-muted');
                compareHtml = `
                    <div class="small"><span class="text-muted">Önceki:</span> ${formatMoney(p.onceki_net)}</div>
                    <div class="small ${diffColor} fw-semibold"><span class="text-muted">Fark:</span> ${diffPrefix}${formatMoney(diff)}</div>
                `;
            }

            // Gün Analizi
            let gunHtml = `
                <div class="fw-semibold text-dark">${p.calisma_gunu} Gün</div>
                <div class="small text-muted">Fiili: ${p.fiili_gun} gün</div>
                ${p.ise_giris ? `<div class="small text-primary" style="font-size: 11px;">Giriş: ${p.ise_giris}</div>` : ''}
                ${p.isten_cikis ? `<div class="small text-danger" style="font-size: 11px;">Çıkış: ${p.isten_cikis}</div>` : ''}
            `;

            // Dağıtım Hücresi
            let dagilimHtml = `
                <div class="small"><span class="text-muted">Net:</span> <strong class="text-dark">${formatMoney(p.net_alacagi)}</strong></div>
                <div class="small"><span class="text-muted">Banka:</span> ${formatMoney(p.banka_alacagi)}</div>
                <div class="small"><span class="text-muted">Elden:</span> ${formatMoney(p.elden_odeme)}</div>
                ${p.sodexo_alacagi > 0 ? `<div class="small"><span class="text-muted">Yemek:</span> ${formatMoney(p.sodexo_alacagi)}</div>` : ''}
            `;

            // Personel Bilgisi & Görev
            let gorevHtml = '';
            if (p.gorev && p.gorev !== '-') {
                gorevHtml = `<div class="small text-muted text-truncate" style="max-width: 220px;" title="${p.gorev}"><i class="mdi mdi-briefcase-outline me-1"></i>${p.gorev}</div>`;
            } else {
                gorevHtml = `<div class="small text-danger fw-medium"><i class="mdi mdi-alert-circle-outline me-1"></i>Görev Tanımsız</div>`;
            }

            let ucretBadge = p.ucret_tipi && p.ucret_tipi !== '-' 
                ? `<span class="badge bg-light text-secondary border ms-1" style="font-size: 10px;">${p.ucret_tipi}</span>` 
                : `<span class="badge bg-danger-subtle text-danger border border-danger ms-1" style="font-size: 10px;"><i class="mdi mdi-alert me-1"></i>Ücret Tipi Yok</span>`;

            const tr = `
                <tr data-risk-type="${riskType}">
                    <td>
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <div class="fw-bold text-dark">${p.ad_soyad}</div>
                            ${ucretBadge}
                        </div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <small class="text-muted">${p.tc_kimlik ? 'TC: ' + p.tc_kimlik : ''}</small>
                        </div>
                        ${gorevHtml}
                    </td>
                    <td>${gunHtml}</td>
                    <td>${dagilimHtml}</td>
                    <td>${compareHtml}</td>
                    <td>${riskBadge}</td>
                    <td>${issuesDetailHtml}</td>
                </tr>
            `;

            tbody.append(tr);
        });

        // DataTables Başlat
        if ($.fn.DataTable) {
            let options = {};
            if (typeof getDatatableOptions === 'function') {
                options = getDatatableOptions();
            }
            options.pageLength = 25;
            options.order = [[4, 'asc']]; // Kritik riskler önce gelsin

            if (typeof applyLengthStateSave === 'function') {
                options = applyLengthStateSave(options);
            }
            auditDataTable = $('#auditPersonelTable').DataTable(options);
        }
    }

    // Risk Filtreleme Butonları (Özel DataTables Arama Filtresi)
    if ($.fn.dataTable && $.fn.dataTable.ext && $.fn.dataTable.ext.search) {
        $.fn.dataTable.ext.search.push(
            function (settings, data, dataIndex, rowData, counter) {
                if (settings.sTableId !== 'auditPersonelTable') {
                    return true;
                }
                if (activeRiskFilter === 'all') {
                    return true;
                }

                const rowNode = settings.aoData[dataIndex].nTr;
                const rowRiskType = $(rowNode).attr('data-risk-type') || 'clean';

                if (activeRiskFilter === 'critical') {
                    return rowRiskType === 'critical';
                } else if (activeRiskFilter === 'warning') {
                    return rowRiskType === 'warning';
                } else if (activeRiskFilter === 'clean') {
                    return rowRiskType === 'clean';
                }
                return true;
            }
        );
    }

    $(document).on('click', '#riskFilterButtonGroup button', function (e) {
        e.preventDefault();
        $('#riskFilterButtonGroup button').removeClass('active');
        $(this).addClass('active');

        activeRiskFilter = $(this).data('filter');
        if (auditDataTable && typeof auditDataTable.draw === 'function') {
            auditDataTable.draw();
        } else {
            // DataTables henüz hazır değilse veya yüklenmediyse DOM filtreleme fallback
            $('#auditPersonelTableBody tr').each(function () {
                const type = $(this).attr('data-risk-type');
                if (activeRiskFilter === 'all' || activeRiskFilter === type) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    });

    // AI Yönetici Raporunu Göster / Gizle Toggle
    $(document).on('click', '#btnToggleAiReport', function (e) {
        e.preventDefault();
        const section = $('#aiReportSection');
        section.slideToggle(250, function () {
            if (section.is(':visible')) {
                $('#btnToggleAiReportText').text('AI Raporunu Gizle');
                $('#btnToggleAiReport').addClass('active btn-primary').removeClass('btn-outline-primary');
                // Rapora kaydır
                $('html, body').animate({
                    scrollTop: section.offset().top - 80
                }, 400);
            } else {
                $('#btnToggleAiReportText').text('AI Raporunu Göster');
                $('#btnToggleAiReport').removeClass('active btn-primary').addClass('btn-outline-primary');
            }
        });
    });

    // Dönem ve Yıl Değişiklikleri
    $(document).on('change', '#auditDonemSelect, #auditCompareDonemSelect', function () {
        loadPageAuditData(true);
    });

    $(document).on('click', '#btnRunPageAudit', function () {
        loadPageAuditData(true);
    });

    // Excel Export
    $(document).on('click', '#btnExportAuditExcel', function () {
        if (!currentAuditData || !currentAuditData.all_rows) {
            Swal.fire('Bilgi', 'Dışa aktarılacak denetim verisi bulunamadı.', 'info');
            return;
        }

        if (typeof XLSX === 'undefined') {
            Swal.fire('Hata', 'XLSX kütüphanesi yüklenemedi.', 'error');
            return;
        }

        const summary = currentAuditData.summary || {};
        const rows = currentAuditData.all_rows || [];

        const excelData = [
            ['YAPAY ZEKA BORDRO DENETİM VE RİSK ANALİZ RAPORU'],
            ['Dönem:', summary.donem_adi, 'Tarih:', summary.donem_tarih],
            ['Sağlık Skoru:', '%' + summary.saglik_skoru, 'Finansal Risk Tutarı:', summary.tahmini_finansal_risk + ' TL'],
            ['Toplam Personel:', summary.toplam_personel, 'Kritik Hatalar:', summary.kritik_hata_sayisi],
            [],
            ['Personel Adı Soyadı', 'TC Kimlik No', 'Ücret Tipi', 'Çalışma Günü', 'Fiili Gün', 'Giriş Tarihi', 'Çıkış Tarihi', 'Net Alacak', 'Banka Ödemesi', 'Elden Ödeme', 'Yemek Yardımı', 'Önceki Dönem Net', 'Risk Durumu', 'Tespit Edilen Bulgular', 'Önerilen Aksiyon']
        ];

        rows.forEach(function (r) {
            let riskStatus = r.has_issue ? (r.issues.some(i => i.severity === 'CRITICAL') ? 'Kritik Risk' : 'Uyarı') : 'Sorunsuz';
            let bulgular = [];
            let aksiyonlar = [];

            if (r.has_issue) {
                r.issues.forEach(function (iss) {
                    bulgular.push(iss.title + ': ' + iss.description);
                    aksiyonlar.push(iss.action_recommendation);
                });
            }

            excelData.push([
                r.ad_soyad,
                r.tc_kimlik,
                r.ucret_tipi,
                r.calisma_gunu,
                r.fiili_gun,
                r.ise_giris,
                r.isten_cikis,
                r.net_alacagi,
                r.banka_alacagi,
                r.elden_odeme,
                r.sodexo_alacagi,
                r.onceki_net !== null ? r.onceki_net : '-',
                riskStatus,
                bulgular.join(' | '),
                aksiyonlar.join(' | ')
            ]);
        });

        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.aoa_to_sheet(excelData);
        XLSX.utils.book_append_sheet(wb, ws, "AI_Bordro_Denetim");
        XLSX.writeFile(wb, "Bordro_AI_Denetim_Raporu_" + (summary.donem_adi || 'Donem').replace(/\s+/g, '_') + ".xlsx");
    });

    // İlk Yükleme
    loadPageAuditData(true);
});
