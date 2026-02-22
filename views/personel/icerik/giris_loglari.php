<div class="card border mb-0">
    <div class="card-header bg-transparent border-bottom">
        <h5 class="mb-0">Personel PWA Giriş Logları</h5>
        <p class="text-muted text-sm mb-0">Personelin mobil veya web uygulamaya yaptığı giriş kayıtları.</p>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-striped datatable dt-responsive nowrap w-100">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Giriş Tarihi</th>
                        <th>Cihaz Tipi</th>
                        <th>Tarayıcı</th>
                        <th>İşletim Sistemi</th>
                        <th>IP Adresi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $i => $log): ?>
                            <tr>
                                <td>
                                    <?php echo $i + 1; ?>
                                </td>
                                <td>
                                    <?php echo date('d.m.Y H:i', strtotime($log->giris_tarihi)); ?>
                                </td>
                                <td>
                                    <?php
                                    $icon = 'mdi-monitor';
                                    if (stripos($log->cihaz, 'Mobil') !== false || stripos($log->cihaz, 'Tablet') !== false) {
                                        $icon = 'mdi-cellphone';
                                    }
                                    ?>
                                    <i class="mdi <?php echo $icon; ?> me-1 text-primary"></i>
                                    <?php echo htmlspecialchars($log->cihaz); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log->tarayici); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log->isletim_sistemi); ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($log->ip_adresi); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>