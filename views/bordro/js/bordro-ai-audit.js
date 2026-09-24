/**
 * Yapay Zeka Bordro Denetimi ve Risk Analizi JavaScript Modülü
 */
$(document).ready(function () {
    let lastAuditData = null;

    // Sayı formatlayıcı
    function formatMoney(amount) {
        return new Intl.NumberFormat('tr-TR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount) + ' ₺';
    }

    // AI Denetimini Başlat
    function runAiBordroAudit(forceLlm = true) {
        const donemSelect = $('#donemSelect').val();
        if (!donemSelect) {
            Swal.fire({
                icon: 'warning',
                title: 'Dönem Seçilmedi',
                text: 'Lütfen önce analiz etmek istediğiniz bordro dönemini seçiniz.'
            });
            return;
        }

        $('#modalAiBordroAudit').modal('show');
        $('#aiAuditLoading').show();
        $('#aiAuditContent').hide();
        $('#btnReAudit').prop('disabled', true);

        $.ajax({
            url: '/views/bordro/api.php',
            type: 'POST',
            data: {
                action: 'ai-bordro-audit',
                donem_id: donemSelect,
                use_llm: forceLlm ? 1 : 0
            },
            dataType: 'json',
            success: function (response) {
                $('#aiAuditLoading').hide();
                $('#btnReAudit').prop('disabled', false);

                if (response.status === 'success' && response.data) {
                    lastAuditData = response.data;
                    renderAuditResults(response.data);
                    $('#aiAuditContent').fadeIn(200);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Denetim Başarısız',
                        text: response.message || 'Analiz sırasında bir hata oluştu.'
                    });
                    $('#modalAiBordroAudit').modal('hide');
                }
            },
            error: function (xhr, status, error) {
                $('#aiAuditLoading').hide();
                $('#btnReAudit').prop('disabled', false);
                Swal.fire({
                    icon: 'error',
                    title: 'Sunucu Hatası',
                    text: 'Denetim isteği işlenirken bir sunucu hatası meydana geldi.'
                });
                $('#modalAiBordroAudit').modal('hide');
            }
        });
    }

    // Sonuçları Modala Doldur
    function renderAuditResults(data) {
        const summary = data.summary || {};
        const issues = data.issues || [];
        const aiReport = data.ai_report || '';

        $('#aiAuditDonemTitle').text(summary.donem_adi + ' (' + (summary.donem_tarih || '') + ') - Toplam ' + summary.toplam_personel + ' Personel');

        // Sağlık Skoru
        const score = summary.saglik_skoru || 100;
        $('#aiHealthScoreCircle').text(score);
        $('#aiHealthProgressBar').css('width', score + '%');

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

        $('#aiHealthScoreCircle').removeClass('text-success text-warning text-danger').addClass(scoreClass);
        $('#aiHealthProgressBar').removeClass('bg-success bg-warning bg-danger').addClass(barClass);
        $('#aiHealthStatusText').text(statusText);

        // Sayaçlar
        $('#aiCriticalCount').text(summary.kritik_hata_sayisi || 0);
        $('#aiWarningCount').text(summary.uyari_sayisi || 0);
        $('#aiRiskAmount').text(formatMoney(summary.tahmini_finansal_risk || 0));
        $('#aiIssuePersonCount').text(issues.length + ' Personel');

        // AI Yönetici Özeti (Markdown basit dönüşüm veya düz metin)
        let formattedReport = aiReport
            .replace(/### (.*?)\n/g, '<h5 class="fw-bold text-primary mt-2 mb-2">$1</h5>')
            .replace(/#### (.*?)\n/g, '<h6 class="fw-bold text-dark mt-3 mb-2">$1</h6>')
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/`(.*?)`/g, '<code class="px-2 py-0 bg-white rounded text-dark border fw-medium">$1</code>')
            .replace(/- (.*?)\n/g, '<div class="d-flex align-items-baseline gap-2 mb-1"><i class="mdi mdi-circle-medium text-primary fs-5"></i><span>$1</span></div>')
            .replace(/\n\n/g, '<div class="my-2"></div>');

        $('#aiExecutiveReportText').html(formattedReport);

        // Sorunlu Personeller Tablosu
        const tbody = $('#aiAuditIssuesTableBody');
        tbody.empty();

        if (issues.length === 0) {
            tbody.html(`
                <tr>
                    <td colspan="4" class="text-center py-4 text-success fw-medium">
                        <i class="mdi mdi-check-circle-outline fs-3 d-block mb-1"></i>
                        Harika! Bu dönemde herhangi bir risk veya hatalı işlem tespit edilmedi.
                    </td>
                </tr>
            `);
        } else {
            issues.forEach(function (p) {
                let issuesHtml = '';
                let actionsHtml = '';

                p.issues.forEach(function (iss) {
                    let badgeClass = 'bg-info-subtle text-info';
                    let icon = 'mdi-information-outline';
                    if (iss.severity === 'CRITICAL') {
                        badgeClass = 'bg-danger-subtle text-danger';
                        icon = 'mdi-alert-octagon';
                    } else if (iss.severity === 'WARNING') {
                        badgeClass = 'bg-warning-subtle text-warning';
                        icon = 'mdi-alert';
                    }

                    issuesHtml += `
                        <div class="mb-2 pb-1 border-bottom border-light">
                            <div class="d-flex align-items-center gap-1 mb-1">
                                <span class="badge ${badgeClass} rounded-pill d-inline-flex align-items-center gap-1" style="font-size: 0.72rem;">
                                    <i class="mdi ${icon}"></i> ${iss.title}
                                </span>
                                ${iss.risk_amount > 0 ? `<span class="badge bg-light text-dark border ms-auto">${formatMoney(iss.risk_amount)}</span>` : ''}
                            </div>
                            <div class="small text-muted">${iss.description}</div>
                        </div>
                    `;

                    actionsHtml += `
                        <div class="mb-2 pb-1 text-secondary small">
                            <i class="mdi mdi-arrow-right-circle text-primary me-1"></i> ${iss.action_recommendation}
                        </div>
                    `;
                });

                const row = `
                    <tr>
                        <td>
                            <div class="fw-bold text-dark">${p.ad_soyad}</div>
                            <small class="text-muted">${p.tc_kimlik ? 'TC: ' + p.tc_kimlik : ''}</small>
                        </td>
                        <td>
                            <div class="small"><span class="text-muted">Net:</span> <strong>${formatMoney(p.net_alacagi)}</strong></div>
                            <div class="small"><span class="text-muted">Banka:</span> ${formatMoney(p.banka_alacagi)}</div>
                            <div class="small"><span class="text-muted">Elden:</span> ${formatMoney(p.elden_odeme)}</div>
                            ${p.sodexo_alacagi > 0 ? `<div class="small"><span class="text-muted">Yemek:</span> ${formatMoney(p.sodexo_alacagi)}</div>` : ''}
                        </td>
                        <td>${issuesHtml}</td>
                        <td>${actionsHtml}</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }
    }

    // Buton Tetikleyicileri (Yeni Sekmede Tam Sayfa AI Analizi Aç)
    $(document).on('click', '#btnAiBordroAudit', function (e) {
        e.preventDefault();
        const donemSelect = $('#donemSelect').val();
        const yilSelect = $('#yilSelect').val() || new Date().getFullYear();
        if (!donemSelect) {
            Swal.fire({
                icon: 'warning',
                title: 'Dönem Seçilmedi',
                text: 'Lütfen önce analiz etmek istediğiniz bordro dönemini seçiniz.'
            });
            return;
        }
        window.open('index.php?p=bordro/ai-analiz&donem=' + donemSelect + '&yil=' + yilSelect, '_blank');
    });

    $(document).on('click', '#btnReAudit', function (e) {
        e.preventDefault();
        runAiBordroAudit(true);
    });

    // Ana Tabloda Riskli Personelleri Filtrele
    $(document).on('click', '#btnFilterRiskliInTable', function (e) {
        e.preventDefault();
        if (!lastAuditData || !lastAuditData.issues || lastAuditData.issues.length === 0) {
            Swal.fire({
                icon: 'info',
                title: 'Filtrelenecek Risk Yok',
                text: 'Bu dönemde filtrelenecek herhangi bir riskli personel bulunmuyor.'
            });
            return;
        }

        const personNames = lastAuditData.issues.map(i => i.ad_soyad).join('|');
        if ($.fn.DataTable && $.fn.DataTable.isDataTable('#bordroTable')) {
            const dt = $('#bordroTable').DataTable();
            // 2. sütun (Personel Adı) üzerinde regex araması yap
            dt.column(2).search(personNames, true, false).draw();
            $('#modalAiBordroAudit').modal('hide');

            Swal.fire({
                icon: 'success',
                title: 'Filtre Uygulandı',
                text: 'Ana tabloda sadece risk tespit edilen ' + lastAuditData.issues.length + ' personel listeleniyor.',
                timer: 2500,
                showConfirmButton: false
            });
        }
    });
});
