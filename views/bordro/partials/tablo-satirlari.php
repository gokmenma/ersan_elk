<?php use App\Helper\Helper; ?>
                                        <?php if (empty($personeller)): ?>
                                            <tr>
                                                <td colspan="13" class="text-center text-muted py-4">
                                                    <i class="bx bx-user-x fs-1 d-block mb-2"></i>
                                                    Bu döneme henüz personel eklenmemiş.<br>
                                                    <small>"Personelleri Güncelle" butonuna tıklayarak personelleri
                                                        ekleyebilirsiniz.</small>
                                                </td>
                                            </tr>
                                        <?php else: ?>
                                            <?php $idx = 1;
                                            foreach ($personeller as $personel):
                                                // Ön-hesaplanmış değerleri oku (tekrar hesaplama yok)
                                                $pc = $preCalc[$personel->id];
                                                $enc_id = $pc['enc_id'];
                                                $toplamAlacagiPersonel = $pc['toplamAlacagi'];
                                                $kesintiHaricIcra = $pc['kesintiHaricIcra'];
                                                $netAlacagi = $pc['netAlacagi'];
                                                $icraKesintisi = $pc['icraKesintisi'];
                                                $sgkVergiKesintisi = $pc['sgkVergiKesintisi'];
                                                $calismaGunu = $pc['calismaGunu'];
                                                $eldenOdeme = $pc['eldenOdeme'];
                                                $bankaOdemesi = $pc['bankaOdemesi'];
                                                $sodexoOdemesi = $pc['sodexoOdemesi'];
                                                ?>
                                                <tr data-id="<?= $personel->id ?>">
                                                    <td>
                                                        <div class="form-check">
                                                            <input type="checkbox" class="form-check-input personel-check"
                                                                value="<?= $personel->id ?>">
                                                        </div>
                                                    </td>
                                                    <td class="text-center fw-bold text-muted"><?= $idx++ ?></td>
                                                    <td class="text-center">
                                                        <?php
                                                        $deptName = (!empty($personel->gorev_gecmisi_var) && !empty($personel->gg_departman)) ? $personel->gg_departman : ($personel->departman ?? '-');
                                                        $deptUp = mb_convert_case($deptName, MB_CASE_UPPER, "UTF-8");
                                                        $dInfo = ['code' => '??', 'color' => '#6c757d'];

                                                        if (strpos($deptUp, 'OKUMA') !== false)
                                                            $dInfo = ['code' => 'EO', 'color' => '#0ea5e9'];
                                                        elseif (strpos($deptUp, 'KESME') !== false)
                                                            $dInfo = ['code' => 'KA', 'color' => '#f43f5e'];
                                                        elseif (strpos($deptUp, 'SAYAÇ') !== false || strpos($deptUp, 'DEGİŞ') !== false)
                                                            $dInfo = ['code' => 'ST', 'color' => '#10b981'];
                                                        elseif (strpos($deptUp, 'KAÇAK') !== false)
                                                            $dInfo = ['code' => 'KÇ', 'color' => '#8b5cf6'];
                                                        else {
                                                            $words = explode(' ', $deptUp);
                                                            if (count($words) >= 2) {
                                                                $dInfo['code'] = mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1);
                                                            } else {
                                                                $dInfo['code'] = mb_substr($deptUp, 0, 2);
                                                            }
                                                        }
                                                        ?>
                                                        <div class="dept-badge" style="--dept-color: <?= $dInfo['color'] ?>;"
                                                            data-bs-toggle="tooltip" title="<?= htmlspecialchars($deptName) ?>">
                                                            <?= $dInfo['code'] ?>
                                                        </div>
                                                        <span class="d-none"><?= $dInfo['code'] ?>
                                                            <?= htmlspecialchars($deptName) ?></span>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if (!empty($personel->ekip_adi) && $personel->ekip_adi !== "YOK") {


                                                            $ekipler = explode(',', $personel->ekip_adi);
                                                            echo '<div class="d-flex flex-wrap">';
                                                            foreach ($ekipler as $ekip) {
                                                                $cleanEkip = trim($ekip);
                                                                $cleanEkip = preg_replace('/ER-SAN ELEKTRİK|ERSAN ELEKTRİK|ER SAN ELEKTRİK/i', '', $cleanEkip);
                                                                $cleanEkip = trim($cleanEkip);

                                                                if (empty($cleanEkip))
                                                                    continue;

                                                                // Departmana göre renk belirle
                                                                $colorClass = "bg-secondary-subtle text-secondary border-secondary-subtle";
                                                                if (strpos($deptUp, 'OKUMA') !== false) {
                                                                    $colorClass = "bg-primary-subtle text-primary border-primary-subtle";
                                                                } elseif (strpos($deptUp, 'KESME') !== false) {
                                                                    $colorClass = "bg-danger-subtle text-danger border-danger-subtle";
                                                                } elseif (strpos($deptUp, 'SAYAÇ') !== false || strpos($deptUp, 'DEGİŞ') !== false) {
                                                                    $colorClass = "bg-success-subtle text-success border-success-subtle";
                                                                } elseif (strpos($deptUp, 'KAÇAK') !== false) {
                                                                    $colorClass = "bg-info-subtle text-info border-info-subtle";
                                                                }

                                                                echo '<span class="badge ' . $colorClass . ' font-size-12 px-2 py-1 mb-1 me-1 border">' . htmlspecialchars($cleanEkip) . '</span>';
                                                            }
                                                            echo '</div>';

                                                            if (!empty($personel->ekip_bolge) && $personel->ekip_bolge !== "---") {
                                                                echo '<div class="text-muted small mt-1"><i class="bx bx-map-pin"></i> ' . htmlspecialchars($personel->ekip_bolge) . '</div>';
                                                            }
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                        <div class="personel-img-zoom-container">
                                                            <img src="<?= (!empty($personel->resim_yolu) && is_file($personel->resim_yolu)) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>"
                                                                alt="" class="rounded-circle avatar-sm me-2 personel-img-zoom cursor-pointer" loading="lazy">
                                                            <div class="img-preview-tooltip">
                                                                <img src="<?= (!empty($personel->resim_yolu) && is_file($personel->resim_yolu)) ? $personel->resim_yolu : 'assets/images/users/user-dummy-img.jpg' ?>" alt="" loading="lazy">
                                                            </div>
                                                        </div>
                                                            <div>
                                                                <div class="fw-medium">
                                                                    <a target="_blank"
                                                                        href="index?p=personel/manage&id=<?= $enc_id ?>"><?= htmlspecialchars($personel->adi_soyadi) ?></a>
                                                                </div>
                                                                <small class="text-muted"
                                                                    style="font-size: 10px; letter-spacing: 0.5px;">TC:
                                                                    <?= htmlspecialchars($personel->tc_kimlik_no ?? '-') ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="text-center" style="font-size: 12px;">
                                                        <?php if (empty($personel->gorev_gecmisi_var)): ?>
                                                            <span
                                                                class="badge bg-warning-subtle text-warning border border-warning fw-medium px-2 py-1"
                                                                data-bs-toggle="tooltip"
                                                                title="Görev geçmişi tanımlı değil! Personel tablosundaki veri kullanılıyor.">
                                                                <i
                                                                    class="bx bx-error-circle me-1"></i><?= htmlspecialchars($personel->maas_durumu ?? '-') ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-light text-dark border fw-medium px-2 py-1">
                                                                <?= htmlspecialchars($personel->gg_maas_durumu ?? $personel->maas_durumu ?? '-') ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center fw-bold">
                                                        <a href="javascript:void(0);"
                                                            class="text-primary text-decoration-none btn-open-takvim"
                                                            data-id="<?= $personel->personel_id ?>"
                                                            data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                            data-ise-giris="<?= $personel->ise_giris_tarihi ?? '' ?>"
                                                            data-isten-cikis="<?= $personel->isten_cikis_tarihi ?? '' ?>"
                                                            data-ay="<?= $selectedAy ?>"
                                                            data-yil="<?= $selectedYil ?>"
                                                            title="İzin/Rapor Takvimini Görüntüle">
                                                            <?= $calismaGunu ?>
                                                        </a>
                                                    </td>

                                                    <td class="text-end text-dark fw-bold">
                                                        <span class="cursor-pointer btn-detail-old text-primary"
                                                            data-id="<?= $personel->id ?>" title="Bordro Detayını Gör">
                                                            <?= number_format($toplamAlacagiPersonel, 2, ',', '.') ?> ₺
                                                        </span>
                                                    </td>
                                                    <td class="text-end text-danger fw-bold">
                                                        <span class="cursor-pointer btn-kesinti-ekle text-danger"
                                                            data-id="<?= $personel->personel_id ?>"
                                                            data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                            data-maas="<?= floatval($personel->maas_tutari ?? 0) ?>"
                                                            data-maas-durumu="<?= $personel->maas_durumu ?? '' ?>">
                                                            <?= number_format($kesintiHaricIcra, 2, ',', '.') ?> ₺
                                                        </span>
                                                    </td>
                                                    <td class="text-end fw-bold <?= ($toplamAlacagiPersonel - $kesintiHaricIcra) < 0 ? 'text-danger' : 'text-success' ?>">
                                                        <span class="cursor-pointer btn-detail <?= ($toplamAlacagiPersonel - $kesintiHaricIcra) < 0 ? 'text-danger' : 'text-success' ?>"
                                                            data-id="<?= $personel->id ?>">
                                                            <?= number_format($toplamAlacagiPersonel - $kesintiHaricIcra, 2, ',', '.') ?> ₺
                                                        </span>
                                                    </td>
                                                    <td class="text-end text-danger fw-medium">
                                                        <?php if ($icraKesintisi > 0): ?>
                                                            <span class="hover-popover-trigger d-inline-block">
                                                                <span class="btn-icra-detail cursor-pointer text-decoration-underline"
                                                                    data-id="<?= $personel->id ?>" title="İcra Detaylarını Gör">
                                                                    <?= number_format($icraKesintisi, 2, ',', '.') . ' ₺' ?>
                                                                </span>
                                                                <?= $pc['icraPopoverHtml'] ?? '' ?>
                                                            </span>
                                                        <?php else: ?>
                                                            -
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end text-warning fw-medium">
                                                        <?= $sgkVergiKesintisi > 0 ? number_format($sgkVergiKesintisi, 2, ',', '.') . ' ₺' : '-' ?>
                                                    </td>
                                                    <td class="text-end text-primary">
                                                        <?= $bankaOdemesi > 0 ? number_format($bankaOdemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                    </td>
                                                    <td class="text-end text-info td-sodexo" style="width: 150px;">
                                                        <div
                                                            class="sodexo-wrapper d-flex align-items-center justify-content-end gap-2">
                                                            <span class="sodexo-value fw-bold">
                                                                <?= $sodexoOdemesi > 0 ? number_format($sodexoOdemesi, 2, ',', '.') . ' ₺' : '-' ?>
                                                            </span>
                                                            <input type="text"
                                                                class="form-control form-control-sm text-end update-sodexo money d-none"
                                                                style="width: 100px;" data-id="<?= $personel->id ?>"
                                                                data-net="<?= number_format($netAlacagi, 2, '.', '') ?>"
                                                                data-banka="<?= number_format($bankaOdemesi, 2, '.', '') ?>"
                                                                data-diger="<?= number_format($personel->diger_odeme ?? 0, 2, '.', '') ?>"
                                                                data-icra="<?= number_format($icraKesintisi, 2, '.', '') ?>"
                                                                data-toplam_alacak="<?= number_format($toplamAlacagiPersonel, 2, '.', '') ?>"
                                                                data-current-val="<?= $sodexoOdemesi ?>"
                                                                value="<?= Helper::formattedMoney($sodexoOdemesi) ?>">
                                                            <a href="javascript:void(0);" class="btn-edit-sodexo-inline text-muted"
                                                                title="Düzenle">
                                                                <i data-feather="edit-3" style="width: 14px; height: 14px;"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                    <td class="text-end fw-bold td-elden <?= $eldenOdeme < 0 ? 'text-danger' : 'text-warning' ?>">
                                                        <?= $eldenOdeme != 0 ? number_format($eldenOdeme, 2, ',', '.') . ' ₺' : '-' ?>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button"
                                                                data-bs-toggle="dropdown" data-bs-boundary="viewport"
                                                                aria-expanded="false">
                                                                <i class="bx bx-dots-vertical-rounded"></i>
                                                            </button>
                                                            <ul class="dropdown-menu dropdown-menu-end">
                                                                <li>
                                                                    <a class="dropdown-item btn-odeme<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);" data-id="<?= $personel->id ?>"
                                                                        data-net="<?= $netAlacagi ?>"
                                                                        data-banka="<?= $bankaOdemesi ?>"
                                                                        data-sodexo="<?= $sodexoOdemesi ?>"
                                                                        data-diger="<?= $personel->diger_odeme ?? 0 ?>"
                                                                        data-icra="<?= $icraKesintisi ?>"
                                                                        data-toplam_alacak="<?= $toplamAlacagiPersonel ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                        <i class="mdi mdi-wallet-outline me-2 text-primary"></i>
                                                                        Ödeme
                                                                        Dağıt
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-detail" href="javascript:void(0);"
                                                                        data-id="<?= $personel->id ?>">
                                                                        <i class="mdi mdi-information-outline me-2 text-info"></i>
                                                                        Detay
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-gelir-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);"
                                                                        data-id="<?= $personel->personel_id ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                        <i
                                                                            class="mdi mdi-plus-circle-outline me-2 text-success"></i>
                                                                        Gelir Ekle
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-kesinti-ekle<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);"
                                                                        data-id="<?= $personel->personel_id ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>"
                                                                        data-maas="<?= floatval($personel->maas_tutari ?? 0) ?>"
                                                                        data-maas-durumu="<?= $personel->maas_durumu ?? '' ?>">
                                                                        <i
                                                                            class="mdi mdi-minus-circle-outline me-2 text-danger"></i>
                                                                        Kesinti Ekle
                                                                    </a>
                                                                </li>
                                                                <li>
                                                                </li>
                                                                <li>
                                                                    <a class="dropdown-item btn-remove text-danger<?= $donemKapali ? ' disabled' : '' ?>"
                                                                        href="javascript:void(0);" data-id="<?= $personel->id ?>"
                                                                        data-ad="<?= htmlspecialchars($personel->adi_soyadi) ?>">
                                                                        <i class="mdi mdi-trash-can-outline me-2"></i> Dönemden
                                                                        Çıkar
                                                                    </a>
                                                                </li>
                                                            </ul>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
