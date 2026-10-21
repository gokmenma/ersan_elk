<?php
use App\Model\SystemLogModel;
use App\Service\Gate;

if (Gate::allows("log_kayitlari")) {

    $systemLogModel = new SystemLogModel();
    $dashboardData = $systemLogModel->getActivityDashboardData();
    ?>
    <div class="container-fluid">

        <!-- start page title -->
        <?php
        $maintitle = "Loglar";
        $title = "Sistem Logları";
        ?>
        <?php include 'layouts/breadcrumb.php'; ?>
        <!-- end page title -->

        <style>
            .activity-audit-page { --audit-primary:#3b82f6; --audit-orange:#e67800; --audit-border:#dbe4ef; --audit-muted:#64748b; }
            .audit-hero,.audit-kpi,.audit-panel { background:var(--bs-card-bg,#fff); border:1px solid var(--audit-border); border-radius:14px; box-shadow:0 5px 18px rgba(15,23,42,.035); }
            .audit-hero { padding:22px 28px 18px; margin-bottom:20px; }
            .audit-eyebrow { display:flex; gap:8px; align-items:center; margin-bottom:9px; }
            .audit-chip { border:1px solid #d9e3ee; border-radius:999px; padding:6px 12px; color:#475569; font-size:12px; background:#f8fafc; }
            .audit-live { background:#16a34a; color:#fff; border-radius:999px; padding:4px 13px; font-size:11px; font-weight:700; }
            .audit-title { font-size:22px; font-weight:750; color:#172033; margin:0 0 4px; }
            .audit-subtitle { color:#64748b; margin:0; font-size:13px; }
            .audit-view-switch { display:inline-flex; padding:4px; background:#f1f5f9; border-radius:10px; gap:3px; }
            .audit-switch-btn { border:0; background:transparent; color:#64748b; padding:9px 14px; border-radius:8px; font-size:12px; font-weight:600; }
            .audit-switch-btn.active { background:var(--audit-primary); color:#fff; box-shadow:0 4px 10px rgba(59,130,246,.28); }
            .audit-quick { border-top:1px solid #edf1f6; margin-top:18px; padding-top:14px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
            .audit-quick-label { color:#64748b; font-size:12px; text-transform:uppercase; }
            .audit-filter { border:1px solid #d8e2ee; background:#f8fafc; color:#334155; padding:8px 13px; border-radius:8px; font-size:12px; }
            .audit-filter.active { background:var(--audit-orange); color:#fff; border-color:var(--audit-orange); }
            .audit-kpi-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
            .audit-kpi { padding:20px 22px 14px; min-height:150px; }
            .audit-kpi-top { display:flex; justify-content:space-between; gap:10px; }
            .audit-kpi-label { color:#5f7190; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
            .audit-kpi-value { color:#172033; font-size:27px; line-height:1.2; font-weight:750; margin-top:8px; }
            .audit-kpi-value.orange { color:var(--audit-orange); } .audit-kpi-value.green { color:#16a34a; }
            .audit-kpi-icon { width:45px;height:45px;border-radius:12px;display:grid;place-items:center;font-size:21px;background:#eff6ff;color:#2563eb; }
            .audit-kpi-icon.orange { background:#fff7df;color:#e67800; } .audit-kpi-icon.green { background:#e9fbf4;color:#059669; }
            .audit-kpi-foot { border-top:1px solid #edf1f6; margin-top:20px; padding-top:11px; color:#6b7280; font-size:11px; }
            .audit-panel { padding:20px 24px; margin-bottom:20px; }
            .audit-panel-title { color:var(--audit-orange); font-size:15px; font-weight:700; }
            .audit-insight-grid { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:20px; }
            .audit-insight-card { background:linear-gradient(145deg,#fff,#f8fbff);border:1px solid var(--audit-border);border-radius:14px;padding:16px 18px;display:flex;align-items:center;gap:13px;min-height:92px; }
            .audit-insight-icon { width:42px;height:42px;flex:0 0 42px;border-radius:12px;display:grid;place-items:center;font-size:20px;background:#eaf2ff;color:#2563eb; }
            .audit-insight-icon.purple{background:#f2edff;color:#7c3aed}.audit-insight-icon.orange{background:#fff3df;color:#e67800}.audit-insight-icon.green{background:#e8faf3;color:#059669}
            .audit-insight-card small{display:block;color:#718096;font-size:10px;text-transform:uppercase;letter-spacing:.035em}.audit-insight-card strong{display:block;color:#172033;font-size:21px;line-height:1.15;margin-top:4px}.audit-insight-card span{display:block;color:#7c8aa0;font-size:10px;margin-top:3px}
            .audit-detail-grid { display:grid;grid-template-columns:minmax(0,1.25fr) minmax(0,.75fr);gap:16px;margin-bottom:20px; }
            .audit-stack { display:grid;gap:16px; }
            .audit-ranking-list { display:grid;gap:10px;margin-top:16px; }
            .audit-ranking-row { display:grid;grid-template-columns:38px minmax(150px,1fr) auto;align-items:center;gap:11px;padding:11px 12px;border:1px solid #e7edf4;border-radius:11px;background:#fbfdff; }
            .audit-rank-avatar { width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:#e8edff;color:#4f46e5;font-size:12px;font-weight:750; }
            .audit-rank-main { min-width:0; }.audit-rank-main strong{font-size:12px;color:#263247}.audit-rank-main small{display:block;color:#7b8798;font-size:10px;margin-top:2px}
            .audit-page-chips { display:flex;gap:5px;flex-wrap:wrap;margin-top:7px }.audit-page-chip{max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;background:#f1f5f9;color:#526173;border:1px solid #e1e8f0;border-radius:999px;padding:3px 7px;font-size:9px}
            .audit-rank-value{text-align:right}.audit-rank-value strong{display:block;color:#e67800;font-size:16px}.audit-rank-value small{color:#8792a3;font-size:9px}
            .audit-popular-row { display:grid;grid-template-columns:minmax(0,1fr) auto;gap:12px;padding:10px 0;border-bottom:1px solid #edf1f5; }.audit-popular-row:last-child{border-bottom:0}.audit-popular-name{font-size:11px;font-weight:650;color:#334155;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.audit-popular-meta{font-size:9px;color:#8a96a7;margin-top:3px}.audit-popular-count{font-size:13px;font-weight:750;color:#2563eb;white-space:nowrap}
            .audit-platform-row{margin-top:13px}.audit-platform-head{display:flex;justify-content:space-between;font-size:10px;color:#526173;margin-bottom:5px}.audit-platform-track{height:7px;border-radius:99px;background:#edf2f7;overflow:hidden}.audit-platform-fill{height:100%;border-radius:inherit;background:linear-gradient(90deg,#3b82f6,#7c3aed)}
            .audit-critical-row{display:flex;gap:10px;padding:10px 0;border-bottom:1px solid #edf1f5}.audit-critical-row:last-child{border-bottom:0}.audit-critical-dot{width:8px;height:8px;border-radius:50%;background:#ef4444;box-shadow:0 0 0 4px #fee2e2;margin-top:5px;flex:0 0 8px}.audit-critical-body{min-width:0}.audit-critical-body strong{display:block;font-size:11px;color:#334155}.audit-critical-body p{font-size:10px;color:#67758a;margin:3px 0 0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.audit-critical-body small{font-size:9px;color:#94a3b8}
            .audit-empty{padding:24px 12px;text-align:center;color:#94a3b8;font-size:11px}
            #activityTrendChart { min-height:330px; }
            .audit-log-area { display:none; }
            .audit-log-area.active,.audit-dashboard-area.active { display:block; }
            .audit-dashboard-area { display:none; }
            .logs-page-card { overflow:hidden; }
            .logs-page-card .card-header { padding:15px 20px 0; }
            .audit-table-card { border:1px solid var(--audit-border) !important;border-radius:14px !important;background:#fff;box-shadow:0 5px 18px rgba(15,23,42,.035) !important; }
            .audit-table-header { min-height:72px;padding:14px 20px;display:flex;align-items:center;justify-content:space-between;gap:12px;border-bottom:1px solid #e6edf5; }
            .audit-table-header h5 { margin:0;color:var(--audit-orange);font-size:15px;font-weight:750; }.audit-table-header small{color:#64748b;font-size:11px}
            .audit-table-icon { width:38px;height:38px;border-radius:10px;background:#fff7df;color:var(--audit-orange);border:1px solid #fde19a;display:grid;place-items:center;font-size:20px; }
            .audit-table-wrap { margin:0 14px 14px; }
            #unifiedLogsTable { table-layout:auto; }
            #unifiedLogsTable tbody td { border-color:#dbe4ef;padding:9px 10px;color:#334155;font-size:12px;vertical-align:middle; }
            #unifiedLogsTable tbody tr:hover td { background:#f8fbff; }
            .audit-date{display:flex;flex-direction:column;gap:2px;white-space:nowrap}.audit-date strong{font-size:11px;font-weight:600}.audit-date small{color:#7c8aa0;font-size:10px}
            .audit-user{display:flex;align-items:center;gap:9px;white-space:nowrap}.audit-user strong{font-size:11px}.audit-avatar{width:27px;height:27px;border-radius:50%;display:grid;place-items:center;background:#e4e8ff;color:#4f46e5;font-size:10px;font-weight:700}
            .audit-event-badge,.audit-module-badge,.audit-related-badge{display:inline-flex;align-items:center;gap:4px;border-radius:7px;padding:4px 8px;font-size:10px;font-weight:650;white-space:nowrap;border:1px solid transparent}
            .audit-badge-view{background:#66758c;color:#fff}.audit-badge-login{background:#e8fbf3;color:#07875d;border-color:#c6f1df}.audit-badge-critical,.audit-badge-delete{background:#fff0f1;color:#dc3545;border-color:#ffd5d9}.audit-badge-ai{background:#f0ecff;color:#6941c6;border-color:#dfd5ff}.audit-badge-operation{background:#eaf2ff;color:#2563eb;border-color:#cfdef8}
            .audit-module-badge{background:#f8fafc;color:#475569;border-color:#dbe4ef}.audit-related-badge{background:#f1f5f9;color:#475569;border-color:#dbe4ef;font-family:monospace;font-weight:500}
            .audit-detail-btn{display:inline-flex;align-items:center;gap:4px;background:#fff;color:#0878ff;border:1px solid #1682ff;border-radius:6px;padding:4px 8px;font-size:10px;margin-right:8px}.audit-detail-text{font-size:11px;color:#334155}
            @media(max-width:991px){.audit-kpi-grid,.audit-insight-grid{grid-template-columns:repeat(2,1fr)}.audit-detail-grid{grid-template-columns:1fr}.audit-view-switch{margin-top:14px}.audit-hero{text-align:left}}
            @media(max-width:575px){.audit-kpi-grid,.audit-insight-grid{grid-template-columns:1fr}.audit-hero{padding:18px}.audit-switch-btn{padding:8px 9px}.audit-quick{align-items:stretch}.audit-filter{flex:1}.audit-ranking-row{grid-template-columns:34px minmax(0,1fr)}.audit-rank-value{grid-column:2;text-align:left}}
            [data-bs-theme="dark"] .audit-hero,[data-bs-theme="dark"] .audit-kpi,[data-bs-theme="dark"] .audit-panel { --audit-border:#36404a; }
            [data-bs-theme="dark"] .audit-title,[data-bs-theme="dark"] .audit-kpi-value { color:#f1f5f9; }
            [data-bs-theme="dark"] .audit-chip,[data-bs-theme="dark"] .audit-filter { background:#171d23;color:#cbd5e1;border-color:#36404a; }
            [data-bs-theme="dark"] .audit-table-card { background:#222830;border-color:#36404a !important; }
            [data-bs-theme="dark"] .audit-insight-card,[data-bs-theme="dark"] .audit-ranking-row{background:#222830;border-color:#36404a}[data-bs-theme="dark"] .audit-insight-card strong,[data-bs-theme="dark"] .audit-rank-main strong,[data-bs-theme="dark"] .audit-popular-name,[data-bs-theme="dark"] .audit-critical-body strong{color:#e2e8f0}[data-bs-theme="dark"] .audit-page-chip{background:#191f26;border-color:#3a4551;color:#bdc7d4}[data-bs-theme="dark"] .audit-popular-row,[data-bs-theme="dark"] .audit-critical-row{border-color:#35404b}
        </style>

        <div class="activity-audit-page">
            <section class="audit-hero">
                <div class="row align-items-center">
                    <div class="col-lg-8">
                        <div class="audit-eyebrow"><span class="audit-chip"><i class="bx bx-shield-quarter me-1"></i> Güvenlik &amp; Denetim Merkezi</span><span class="audit-live">Canlı İzleme Aktif</span></div>
                        <h2 class="audit-title">Sistem Aktiviteleri &amp; Denetim Paneli</h2>
                        <p class="audit-subtitle">Kullanıcı hareketleri, sistem işlemleri, girişler ve kritik olaylar tek ekranda.</p>
                    </div>
                    <div class="col-lg-4 text-lg-end">
                        <div class="audit-view-switch" role="group" aria-label="Aktivite görünümü">
                            <button type="button" class="audit-switch-btn active" data-audit-view="dashboard"><i class="bx bx-pie-chart-alt-2 me-1"></i> Aktivite Dashboard</button>
                            <button type="button" class="audit-switch-btn" data-audit-view="logs"><i class="bx bx-list-ul me-1"></i> Aktivite Günlüğü</button>
                        </div>
                    </div>
                </div>
                <div class="audit-quick">
                    <span class="audit-quick-label"><i class="bx bx-bolt-circle me-1"></i> Hızlı filtreler:</span>
                    <button type="button" class="audit-filter active" data-category=""><i class="bx bx-menu me-1"></i> Tüm Kayıtlar</button>
                    <button type="button" class="audit-filter" data-category="operation"><i class="bx bx-pointer me-1"></i> Kullanıcı İşlemleri</button>
                    <button type="button" class="audit-filter" data-category="login"><i class="bx bx-log-in me-1"></i> Girişler</button>
                    <button type="button" class="audit-filter" data-category="delete"><i class="bx bx-trash me-1"></i> Silmeler</button>
                    <button type="button" class="audit-filter" data-category="view"><i class="bx bx-show me-1"></i> Sayfa Ziyaretleri</button>
                </div>
            </section>

            <div class="d-flex gap-2 flex-wrap mb-3" role="group" aria-label="Log kayıt kapsamı">
                <button type="button" class="btn btn-primary log-scope" data-scope="30" aria-pressed="true">Son 30 gün</button>
                <button type="button" class="btn btn-outline-secondary log-scope" data-scope="90" aria-pressed="false">Son 90 gün</button>
                <button type="button" class="btn btn-outline-secondary log-scope" data-scope="all" aria-pressed="false">Tüm kayıtlar</button>
                <button type="button" class="btn btn-outline-secondary log-scope" data-scope="archive" aria-pressed="false"><i class="bx bx-archive me-1"></i>Arşiv</button>
            </div>
            <p class="text-muted font-size-12" id="logScopeDescription">Arşivlenmemiş son 30 günlük kayıtlar gösterilir.</p>
            <div class="audit-kpi-grid">
                <div class="audit-kpi"><div class="audit-kpi-top"><div><div class="audit-kpi-label">Toplam Sistem Logu</div><div class="audit-kpi-value orange"><?= number_format($dashboardData['total'], 0, ',', '.') ?></div></div><span class="audit-kpi-icon orange"><i class="bx bx-data"></i></span></div><div class="audit-kpi-foot"><i class="bx bx-server me-1"></i> Tüm zamanlar</div></div>
                <div class="audit-kpi"><div class="audit-kpi-top"><div><div class="audit-kpi-label">Bugünkü İşlemler</div><div class="audit-kpi-value"><?= number_format($dashboardData['today_operations'], 0, ',', '.') ?></div></div><span class="audit-kpi-icon green"><i class="bx bx-bolt-circle"></i></span></div><div class="audit-kpi-foot"><i class="bx bx-time-five me-1"></i> İşlem ve aksiyonlar</div></div>
                <div class="audit-kpi"><div class="audit-kpi-top"><div><div class="audit-kpi-label">Bugünkü Girişler</div><div class="audit-kpi-value"><?= number_format($dashboardData['today_logins'], 0, ',', '.') ?></div></div><span class="audit-kpi-icon orange"><i class="bx bx-log-in"></i></span></div><div class="audit-kpi-foot"><i class="bx bx-user-circle me-1"></i> Yönetici oturumları</div></div>
                <div class="audit-kpi"><div class="audit-kpi-top"><div><div class="audit-kpi-label">Kritik &amp; Hata Olayı</div><div class="audit-kpi-value green"><?= number_format($dashboardData['today_critical'], 0, ',', '.') ?></div></div><span class="audit-kpi-icon green"><i class="bx bx-check-circle"></i></span></div><div class="audit-kpi-foot">Bugünkü kritik kayıtlar</div></div>
            </div>

            <div class="audit-dashboard-area active">
                <div class="audit-insight-grid">
                    <div class="audit-insight-card"><span class="audit-insight-icon"><i class="bx bx-show"></i></span><div><small>Bugünkü Sayfa Ziyareti</small><strong><?= number_format($dashboardData['today_views'], 0, ',', '.') ?></strong><span>Bugün oluşan görüntülemeler</span></div></div>
                    <div class="audit-insight-card"><span class="audit-insight-icon purple"><i class="bx bx-user-check"></i></span><div><small>Aktif Ziyaretçi</small><strong><?= number_format($dashboardData['active_visitors'], 0, ',', '.') ?></strong><span>Son 30 günde benzersiz kullanıcı</span></div></div>
                    <div class="audit-insight-card"><span class="audit-insight-icon orange"><i class="bx bx-window-open"></i></span><div><small>Ziyaret Edilen Sayfa</small><strong><?= number_format($dashboardData['unique_pages'], 0, ',', '.') ?></strong><span>Son 30 günde benzersiz rota</span></div></div>
                    <div class="audit-insight-card"><span class="audit-insight-icon green"><i class="bx bx-mobile-alt"></i></span><div><small>Personel PWA Trafiği</small><strong><?= number_format($dashboardData['month_pwa_views'], 0, ',', '.') ?></strong><span>Son 30 gün / <?= number_format($dashboardData['month_views'], 0, ',', '.') ?> toplam ziyaret</span></div></div>
                </div>

                <div class="audit-detail-grid">
                    <section class="audit-panel mb-0">
                        <div class="d-flex justify-content-between align-items-start"><div><div class="audit-panel-title"><i class="bx bx-user-voice me-1"></i> Personel &amp; Kullanıcı Sayfa Hareketleri</div><small class="text-muted">Son 30 günde kim, hangi sayfaları ne kadar ziyaret etti?</small></div><span class="badge bg-soft-primary text-primary">En Aktif 8</span></div>
                        <div class="audit-ranking-list">
                            <?php foreach ($dashboardData['top_visitors'] as $index => $visitor):
                                $visitorName = (string) $visitor['actor_name']; ?>
                                <div class="audit-ranking-row">
                                    <span class="audit-rank-avatar"><?= htmlspecialchars(mb_substr($visitorName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') ?></span>
                                    <div class="audit-rank-main">
                                        <strong><?= htmlspecialchars($visitorName, ENT_QUOTES, 'UTF-8') ?></strong>
                                        <small><?= number_format((int) $visitor['page_count'], 0, ',', '.') ?> farklı sayfa · Son ziyaret <?= date('d.m.Y H:i', strtotime((string) $visitor['last_visit'])) ?></small>
                                        <div class="audit-page-chips">
                                            <?php foreach ($visitor['pages'] as $page): ?>
                                                <span class="audit-page-chip" title="<?= htmlspecialchars((string) $page['name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $page['name'], ENT_QUOTES, 'UTF-8') ?> · <?= number_format((int) $page['visits'], 0, ',', '.') ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    <div class="audit-rank-value"><strong><?= number_format((int) $visitor['visit_count'], 0, ',', '.') ?></strong><small>ziyaret</small></div>
                                </div>
                            <?php endforeach; ?>
                            <?php if (!$dashboardData['top_visitors']): ?><div class="audit-empty"><i class="bx bx-info-circle me-1"></i>Son 30 günde sayfa ziyareti bulunamadı.</div><?php endif; ?>
                        </div>
                    </section>

                    <div class="audit-stack">
                        <section class="audit-panel mb-0">
                            <div class="audit-panel-title"><i class="bx bx-bar-chart-square me-1"></i> En Çok Ziyaret Edilen Sayfalar</div>
                            <small class="text-muted">Son 30 günlük yoğunluk</small>
                            <div class="mt-2">
                                <?php foreach ($dashboardData['top_pages'] as $page): ?>
                                    <div class="audit-popular-row"><div><div class="audit-popular-name" title="<?= htmlspecialchars((string) $page['page_name'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $page['page_name'], ENT_QUOTES, 'UTF-8') ?></div><div class="audit-popular-meta"><?= number_format((int) $page['visitor_count'], 0, ',', '.') ?> benzersiz ziyaretçi</div></div><span class="audit-popular-count"><?= number_format((int) $page['visit_count'], 0, ',', '.') ?></span></div>
                                <?php endforeach; ?>
                            </div>
                        </section>

                        <section class="audit-panel mb-0">
                            <div class="audit-panel-title"><i class="bx bx-devices me-1"></i> Platform Dağılımı</div>
                            <?php $platformTotal = max(1, array_sum(array_map(static fn($row) => (int) $row['visit_count'], $dashboardData['platforms']))); ?>
                            <?php foreach ($dashboardData['platforms'] as $platform): $platformRate = ((int) $platform['visit_count'] / $platformTotal) * 100; ?>
                                <div class="audit-platform-row"><div class="audit-platform-head"><span><?= htmlspecialchars((string) $platform['platform_name'], ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format((int) $platform['visit_count'], 0, ',', '.') ?> · %<?= number_format($platformRate, 1, ',', '.') ?></strong></div><div class="audit-platform-track"><div class="audit-platform-fill" style="width:<?= min(100, max(0, $platformRate)) ?>%"></div></div></div>
                            <?php endforeach; ?>
                        </section>
                    </div>
                </div>

                <section class="audit-panel">
                    <div class="d-flex justify-content-between align-items-center mb-3"><div><div class="audit-panel-title"><i class="bx bx-line-chart me-1"></i> Son 14 Günlük Aktivite Trendi</div><small class="text-muted">Operasyonel işlemler, sayfa trafiği ve oturum açma hacmi</small></div><span class="badge bg-primary"><i class="bx bx-calendar me-1"></i> Son 14 Gün</span></div>
                    <div id="activityTrendChart"></div>
                </section>

                <section class="audit-panel">
                    <div class="d-flex justify-content-between align-items-start"><div><div class="audit-panel-title"><i class="bx bx-error-circle me-1"></i> Son Kritik Olaylar</div><small class="text-muted">Hızlı inceleme gerektirebilecek en yeni kayıtlar</small></div><button type="button" class="btn btn-sm btn-outline-danger audit-filter" data-category="critical">Tüm Kritik Kayıtlar</button></div>
                    <div class="mt-2">
                        <?php foreach ($dashboardData['recent_critical'] as $critical): ?>
                            <div class="audit-critical-row"><span class="audit-critical-dot"></span><div class="audit-critical-body"><strong><?= htmlspecialchars((string) $critical['action_type'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string) $critical['actor_name'], ENT_QUOTES, 'UTF-8') ?></strong><p title="<?= htmlspecialchars((string) $critical['description'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) $critical['description'], ENT_QUOTES, 'UTF-8') ?></p><small><?= date('d.m.Y H:i:s', strtotime((string) $critical['created_at'])) ?></small></div></div>
                        <?php endforeach; ?>
                        <?php if (!$dashboardData['recent_critical']): ?><div class="audit-empty"><i class="bx bx-check-circle me-1"></i>Kritik olay kaydı bulunmuyor.</div><?php endif; ?>
                    </div>
                </section>
            </div>

            <div class="audit-log-area">
                <div class="card logs-page-card audit-table-card">
                    <div class="audit-table-header">
                        <div class="d-flex align-items-center gap-3">
                            <span class="audit-table-icon"><i class="bx bx-list-ul"></i></span>
                            <div><h5>Aktivite Listesi &amp; Filtreleme</h5><small>Anlık arama, sütun filtreleme ve tüm sistem işlem kayıtları</small></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="resetActivityFilters"><i class="bx bx-reset me-1"></i>Sıfırla</button>
                    </div>
                    <div class="table-responsive audit-table-wrap">
                        <table class="table align-middle w-100 mb-0" id="unifiedLogsTable">
                            <thead><tr>
                                <th data-filter="date">Tarih / Saat</th>
                                <th data-filter="string">Kullanıcı</th>
                                <th data-filter="select">İşlem Türü</th>
                                <th data-filter="select">Modül</th>
                                <th data-filter="string">Yapılan İşlem / Detay</th>
                                <th data-filter="string">İlgili Kayıt</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Log Detay Modal -->
        <style>
            [data-bs-theme="dark"] .logs-page-card {
                background: var(--bs-card-bg, #222830) !important;
                border: 1px solid var(--bs-card-border-color, #2b333e) !important;
                color: #cbd5e1;
            }
            [data-bs-theme="dark"] .logs-page-card > .card-header {
                background: transparent !important;
                border-bottom-color: #36404a !important;
            }
            [data-bs-theme="dark"] .logs-page-card .card-title {
                color: #f1f5f9 !important;
            }
            [data-bs-theme="dark"] .logs-page-card .nav-tabs {
                border-bottom-color: transparent !important;
            }
            [data-bs-theme="dark"] .logs-page-card .nav-tabs .nav-link {
                background: #171d23 !important;
                border-color: #303944 !important;
                color: #aeb8c5 !important;
            }
            [data-bs-theme="dark"] .logs-page-card .nav-tabs .nav-link:hover {
                background: #2b343e !important;
                color: #f8fafc !important;
            }
            [data-bs-theme="dark"] .logs-page-card .nav-tabs .nav-link.active {
                background: #37424e !important;
                border-color: #52606e !important;
                color: #ffffff !important;
            }
            [data-bs-theme="dark"] .logs-page-card .table-responsive,
            [data-bs-theme="dark"] .logs-page-card .table,
            [data-bs-theme="dark"] .logs-page-card .table > :not(caption) > * > * {
                background-color: transparent !important;
                --bs-table-bg: transparent;
                --bs-table-accent-bg: transparent;
                color: #b8c2cf !important;
            }
            [data-bs-theme="dark"] .logs-page-card .table thead,
            [data-bs-theme="dark"] .logs-page-card .table thead tr,
            [data-bs-theme="dark"] .logs-page-card .table thead th {
                background: #29313c !important;
                color: #e2e8f0 !important;
                border-color: #3b4652 !important;
            }
            [data-bs-theme="dark"] .logs-page-card .table tbody td {
                border-color: #35404b !important;
            }
            [data-bs-theme="dark"] .logs-page-card .table-hover > tbody > tr:hover > * {
                background-color: #2a333d !important;
                color: #f8fafc !important;
            }
            .ai-agent-logs-table {
                table-layout: fixed;
            }
            .ai-agent-logs-table th,
            .ai-agent-logs-table td {
                white-space: normal !important;
                overflow-wrap: anywhere;
                vertical-align: middle;
            }
            .ai-agent-logs-table td:nth-child(2),
            .ai-agent-logs-table td:nth-child(3) {
                line-height: 1.4;
            }
            [data-bs-theme="dark"] .logs-page-card .dataTables_length,
            [data-bs-theme="dark"] .logs-page-card .dataTables_filter,
            [data-bs-theme="dark"] .logs-page-card .dataTables_info,
            [data-bs-theme="dark"] .logs-page-card .dataTables_paginate,
            [data-bs-theme="dark"] .logs-page-card .dataTables_length label,
            [data-bs-theme="dark"] .logs-page-card .dataTables_filter label {
                color: #aeb8c5 !important;
            }
            [data-bs-theme="dark"] .logs-page-card .dataTables_length select,
            [data-bs-theme="dark"] .logs-page-card .dataTables_filter input {
                background-color: #171d23 !important;
                border-color: #3b4652 !important;
                color: #f8fafc !important;
            }

            #modalLogDetay .modal-content {
                border: none;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 25px 70px rgba(0, 0, 0, 0.18);
            }
            #modalLogDetay .log-modal-header {
                background: linear-gradient(135deg, #4361ee 0%, #3a0ca3 100%);
                padding: 1.5rem 1.75rem;
                position: relative;
                overflow: hidden;
            }
            #modalLogDetay .log-modal-header::before {
                content: ''; position: absolute; top: -40px; right: -40px; width: 140px; height: 140px;
                background: rgba(255, 255, 255, 0.07); border-radius: 50%;
            }
            #modalLogDetay .log-modal-header::after {
                content: ''; position: absolute; bottom: -50px; left: 30px; width: 100px; height: 100px;
                background: rgba(255, 255, 255, 0.05); border-radius: 50%;
            }
            #modalLogDetay .modal-icon-wrap {
                width: 44px; height: 44px; background: rgba(255, 255, 255, 0.15); border-radius: 12px;
                display: flex; align-items: center; justify-content: center; backdrop-filter: blur(6px); flex-shrink: 0;
            }
            #modalLogDetay .log-meta-card {
                background: #f8f9fc; border: 1px solid #e9ecf3; border-radius: 12px; padding: 0.85rem 1rem;
            }
            #modalLogDetay .log-meta-label {
                font-size: 0.7rem; font-weight: 600; text-transform: uppercase; color: #8a94ad; margin-bottom: 3px;
            }
            #modalLogDetay .log-meta-value {
                font-size: 0.925rem; font-weight: 700; color: #2d3a56; margin: 0;
            }
            #modalLogDetay .log-content-box {
                background: linear-gradient(135deg, #f8f9fc 0%, #f0f3ff 100%); border: 1px solid #dde2f1;
                border-radius: 14px; padding: 1.1rem 1.25rem; min-height: 60px;
            }
            #modalLogDetay .log-content-box .change-table {
                border-radius: 10px; overflow: hidden; border: 1px solid #dde2f1; margin-top: 0.75rem; width: 100%;
            }
            #modalLogDetay .log-content-box .change-table thead th {
                background: #4361ee; color: #fff; font-size: 0.78rem; font-weight: 600; padding: 0.6rem 0.9rem; border: none;
            }
            #modalLogDetay .log-content-box .change-table tbody td {
                padding: 0.55rem 0.9rem; font-size: 0.85rem; border-color: #eaecf4;
            }
            #modalLogDetay .change-arrow {
                display: inline-flex; align-items: center; gap: 6px; font-size: 0.82rem;
            }
            #modalLogDetay .change-table .from-val {
                background: #fee2e2; color: #b91c1c; padding: 1px 8px; border-radius: 20px; font-size: 0.78rem;
            }
            #modalLogDetay .change-table .to-val {
                background: #dcfce7; color: #15803d; padding: 1px 8px; border-radius: 20px; font-size: 0.78rem;
            }
            #modalLogDetay .change-arrow .arrow-icon { color: #94a3b8; font-size: 1rem; }
            #modalLogDetay .section-divider {
                display: flex; align-items: center; gap: 10px; margin: 1.1rem 0 0.85rem; color: #8a94ad; font-size: 0.72rem; font-weight: 700; text-transform: uppercase;
            }
            #modalLogDetay .section-divider::before, #modalLogDetay .section-divider::after {
                content: ''; flex: 1; height: 1px; background: #e2e6f0;
            }
            #modalLogDetay .btn-close-modal {
                background: linear-gradient(135deg, #4361ee, #3a0ca3); color: #fff; border: none; padding: 0.55rem 1.75rem; border-radius: 10px; font-size: 0.875rem; font-weight: 600; box-shadow: 0 4px 14px rgba(67, 97, 238, 0.35);
            }
            [data-bs-theme="dark"] #modalLogDetay .modal-content,
            [data-bs-theme="dark"] #modalLogDetay .modal-body,
            [data-bs-theme="dark"] #modalLogDetay .modal-footer {
                background-color: #222830 !important;
                color: #cbd5e1 !important;
            }
            [data-bs-theme="dark"] #modalLogDetay .log-meta-card,
            [data-bs-theme="dark"] #modalLogDetay .log-content-box {
                background: #191f26 !important;
                border-color: #3a4551 !important;
            }
            [data-bs-theme="dark"] #modalLogDetay .log-meta-value,
            [data-bs-theme="dark"] #modalLogDetay .log-content-box {
                color: #e2e8f0 !important;
            }
            [data-bs-theme="dark"] #modalLogDetay .log-content-box > div > span {
                color: #e2e8f0 !important;
            }
            [data-bs-theme="dark"] #modalLogDetay .section-divider::before,
            [data-bs-theme="dark"] #modalLogDetay .section-divider::after {
                background: #3a4551;
            }
            [data-bs-theme="dark"] #modalLogDetay .change-table {
                border-color: #3a4551;
            }
            [data-bs-theme="dark"] #modalLogDetay .change-table tbody td {
                background: #222830 !important;
                border-color: #3a4551 !important;
                color: #cbd5e1 !important;
            }
        </style>

        <div class="modal fade" id="modalLogDetay" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="log-modal-header d-flex align-items-center gap-3" style="position:relative;z-index:1;">
                        <div class="modal-icon-wrap"><i class="bx bx-bell text-white fs-5"></i></div>
                        <div class="flex-grow-1">
                            <h5 class="mb-0 text-white fw-bold" style="font-size:1rem;">Bildirim Detayı</h5>
                            <small class="text-white" style="opacity:0.65;font-size:0.75rem;">Sistem Olay Kaydı</small>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3 mb-1">
                            <div class="col-md-5">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-tag me-1"></i>İşlem Tipi</div>
                                    <p id="logDetayTitle" class="log-meta-value">-</p>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-user me-1"></i>İşlemi Yapan</div>
                                    <p id="logDetayUser" class="log-meta-value">-</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="log-meta-card h-100">
                                    <div class="log-meta-label"><i class="bx bx-calendar me-1"></i>Tarih</div>
                                    <p id="logDetayDate" class="log-meta-value" style="font-size:0.82rem;">-</p>
                                </div>
                            </div>
                        </div>
                        <div class="section-divider">İçerik Detayı</div>
                        <div id="logDetayContent" class="log-content-box" style="white-space:pre-wrap;">-</div>
                    </div>
                    <div class="modal-footer justify-content-end gap-2">
                        <button type="button" class="btn-close-modal" data-bs-dismiss="modal"><i class="bx bx-x me-1"></i>Kapat</button>
                    </div>
                </div>
            </div>
        </div>

    </div> <!-- container-fluid -->

    <!-- Load DataTables scripts directly in case they are not part of global layout -->
    <script src="assets/libs/datatables.net/js/jquery.dataTables.min.js"></script>
    <script src="assets/libs/datatables.net-bs4/js/dataTables.bootstrap4.min.js"></script>
    <link rel="stylesheet" href="assets/css/datatable-filters.css?v=<?= filemtime('assets/css/datatable-filters.css') ?>">
    <script src="assets/js/datatable-filters.js?v=<?= filemtime('assets/js/datatable-filters.js') ?>"></script>
    <script src="assets/js/datatables.init.js?v=<?= filemtime('assets/js/datatables.init.js') ?>"></script>

    <script src="assets/libs/apexcharts/apexcharts.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dashboardData = <?= json_encode($dashboardData['trend'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const dashboardArea = document.querySelector('.audit-dashboard-area');
            const logArea = document.querySelector('.audit-log-area');

            function setAuditView(view) {
                document.querySelectorAll('[data-audit-view]').forEach(function (button) {
                    button.classList.toggle('active', button.dataset.auditView === view);
                });
                dashboardArea.classList.toggle('active', view === 'dashboard');
                logArea.classList.toggle('active', view === 'logs');
                if (view === 'logs' && $.fn.DataTable) {
                    setTimeout(function () { $.fn.dataTable.tables({ visible: true, api: true }).columns.adjust(); }, 50);
                }
            }

            document.querySelectorAll('[data-audit-view]').forEach(function (button) {
                button.addEventListener('click', function () { setAuditView(button.dataset.auditView); });
            });

            document.querySelectorAll('.audit-filter').forEach(function (button) {
                button.addEventListener('click', function () {
                    document.querySelectorAll('.audit-filter').forEach(function (item) { item.classList.remove('active'); });
                    button.classList.add('active');
                    setAuditView('logs');
                    if ($.fn.DataTable.isDataTable('#unifiedLogsTable')) {
                        $('#unifiedLogsTable').DataTable().ajax.reload();
                    }
                });
            });

            if (typeof ApexCharts !== 'undefined') {
                new ApexCharts(document.querySelector('#activityTrendChart'), {
                    chart: { type: 'area', height: 330, toolbar: { show: false }, fontFamily: 'inherit' },
                    series: [
                        { name: 'Kullanıcı İşlemleri', data: dashboardData.operations },
                        { name: 'Sayfa Görüntülemeleri', data: dashboardData.views },
                        { name: 'Girişler', data: dashboardData.logins }
                    ],
                    colors: ['#3b82f6', '#94a3b8', '#10b981'],
                    dataLabels: { enabled: false }, stroke: { curve: 'smooth', width: 2 },
                    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: .22, opacityTo: .02, stops: [0, 95] } },
                    xaxis: { categories: dashboardData.labels, axisBorder: { show: false }, axisTicks: { show: false } },
                    yaxis: { min: 0, forceNiceScale: true }, grid: { borderColor: '#e9eef5', strokeDashArray: 4 },
                    legend: { position: 'top', horizontalAlign: 'right' }, tooltip: { shared: true, intersect: false }
                }).render();
            }

            // Setup DataTables parameters
            const dtOptions = {
                language: $.extend(true, {}, DT_LANG_TR),
                pageLength: 25,
                ordering: true
            };

            if ($.fn.DataTable) {
                const unifiedTable = $('#unifiedLogsTable').DataTable(applyLengthStateSave({ ...getDatatableOptions(), ...dtOptions,
                    processing: true,
                    serverSide: true,
                    autoWidth: false,
                    ajax: {
                        url: 'views/logs/api.php',
                        type: 'POST',
                        data: function (request) {
                            request.action = 'get-unified-logs';
                            request.scope = document.querySelector('.log-scope[aria-pressed="true"]')?.dataset.scope || '30';
                            request.category = document.querySelector('.audit-filter.active')?.dataset.category || '';
                        }
                    },
                    columns: [
                        { data: 'date' },
                        { data: 'user' },
                        { data: 'type' },
                        { data: 'module' },
                        { data: 'detail' },
                        { data: 'related' }
                    ],
                    order: [[0, 'desc']]
                }));

                $('.log-scope').on('click', function () {
                    $('.log-scope').removeClass('btn-primary').addClass('btn-outline-secondary').attr('aria-pressed', 'false');
                    $(this).removeClass('btn-outline-secondary').addClass('btn-primary').attr('aria-pressed', 'true');
                    const descriptions = { '30': 'Arşivlenmemiş son 30 günlük kayıtlar gösterilir.', '90': 'Arşivlenmemiş son 90 günlük kayıtlar gösterilir.', all: 'Aktif ve arşivlenmiş tüm kayıtlar gösterilir.', archive: 'Yalnızca arşivlenmiş kayıtlar gösterilir.' };
                    $('#logScopeDescription').text(descriptions[this.dataset.scope]);
                    setAuditView('logs');
                    unifiedTable.ajax.reload();
                });

                $('#resetActivityFilters').on('click', function () {
                    localStorage.removeItem('dt_adv_filters_unifiedLogsTable');
                    unifiedTable.search('');
                    unifiedTable.columns().search('');
                    unifiedTable.ajax.reload();
                    window.location.reload();
                });
            }

            // Log Detay Modal JS logic
            $('body').on('click', '.btn-log-detay', function () {
                var btn = $(this);
                var title = btn.data('title');
                var user = btn.data('user');
                var date = btn.data('date');
                var content = btn.data('content');
                var changes = [];
                try {
                    changes = JSON.parse(atob(btn.attr('data-changes') || 'W10='));
                } catch (e) {
                    changes = [];
                }
                document.getElementById('logDetayTitle').textContent = title;
                document.getElementById('logDetayUser').textContent = user;
                document.getElementById('logDetayDate').textContent = date;

                if (btn.hasClass('btn-ai-response')) {
                    document.getElementById('logDetayContent').textContent = content;
                } else if (Array.isArray(changes) && changes.length > 0) {
                    const contentBox = document.getElementById('logDetayContent');
                    contentBox.replaceChildren();

                    const summary = document.createElement('div');
                    summary.className = 'd-flex align-items-start gap-2 mb-3';
                    const summaryIcon = document.createElement('i');
                    summaryIcon.className = 'bx bx-edit-alt text-primary mt-1';
                    summaryIcon.style.cssText = 'font-size:1.1rem;flex-shrink:0;';
                    const summaryText = document.createElement('span');
                    summaryText.style.cssText = 'font-size:0.875rem;color:#374151;line-height:1.55;';
                    summaryText.textContent = content;
                    summary.append(summaryIcon, summaryText);
                    contentBox.appendChild(summary);

                    const tableWrap = document.createElement('div');
                    tableWrap.className = 'change-table';
                    const table = document.createElement('table');
                    table.className = 'table table-sm mb-0';
                    table.innerHTML = '<thead><tr><th>Alan</th><th>Eski Değer</th><th>Yeni Değer</th></tr></thead><tbody></tbody>';
                    const tbody = table.querySelector('tbody');

                    changes.forEach(function (change) {
                        const row = document.createElement('tr');
                        const fieldCell = document.createElement('td');
                        fieldCell.className = 'field-cell';
                        fieldCell.style.cssText = 'width:30%;font-weight:600;color:#4361ee;';
                        fieldCell.textContent = change.field || '-';

                        const oldCell = document.createElement('td');
                        const oldValue = document.createElement('span');
                        oldValue.className = 'from-val';
                        oldValue.textContent = change.old || 'Boş';
                        oldCell.appendChild(oldValue);

                        const newCell = document.createElement('td');
                        const newValue = document.createElement('span');
                        newValue.className = 'to-val';
                        newValue.textContent = change.new || 'Boş';
                        newCell.appendChild(newValue);

                        row.append(fieldCell, oldCell, newCell);
                        tbody.appendChild(row);
                    });

                    tableWrap.appendChild(table);
                    contentBox.appendChild(tableWrap);
                } else {
                    document.getElementById('logDetayContent').textContent = content;
                }
                var myModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalLogDetay'));
                myModal.show();
            });
        });
    </script>
<?php } ?>
