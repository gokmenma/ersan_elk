<?php

$filePath = 'c:\xampp\htdocs\ersan_elk\views\bordro\parametreler.php';
$content = file_get_contents($filePath);

// Replacement 1: HTML Block
$oldHtml = '                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="col-md-6 mb-3">

                            </div>
                            <div class="col-md-6 mb-3" id="divOran" style="display: none;">

                            </div>
                            <div class="col-md-6 mb-3">

                            </div>

                        </div>
                        <div class="col-md-6 mb-3" id="divOran" style="display: none;">

                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("number", "sira", "0", "0", "Sıralama", "bx bx-sort-amount-up", "form-control") ?>

                        </div>
                        <div class="col-md-6 mb-3">

                        </div>
                    </div>';

$newHtml = '                    <div class="row">
                        <div class="col-md-6 mb-3" id="divTutar">
                            <?= Form::FormFloatInput("number", "varsayilan_tutar", "0", "0.00", "Varsayılan Tutar", "bx bx-money", "form-control", false, null, "off", false, \'step="0.01"\') ?>
                        </div>
                        <div class="col-md-6 mb-3" id="divOran" style="display: none;">
                            <?= Form::FormFloatInput("number", "oran", "0", "0", "Oran (%)", "bx bx-percentage", "form-control", false, null, "off", false, \'step="0.01"\') ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("number", "sira", "0", "0", "Sıralama", "bx bx-sort-amount-up", "form-control") ?>
                        </div>
                    </div>';

if (strpos($content, $oldHtml) !== false) {
    $content = str_replace($oldHtml, $newHtml, $content);
    echo "HTML block replaced.\n";
} else {
    echo "HTML block NOT found.\n";
}

// Replacement 2: JS Block
$oldJs = "            // Oran Bazlı kontrolü
            if (val === 'oran_bazli') {
                $('#divOran').slideDown();
                $('input[name=\"varsayilan_tutar\"]').closest('.col-md-6').hide();
            } else {
                $('#divOran').slideUp();
                $('input[name=\"varsayilan_tutar\"]').closest('.col-md-6').show();
            }";

$newJs = "            // Oran Bazlı kontrolü
            if (['oran_bazli_vergi', 'oran_bazli_sgk', 'oran_bazli_net'].includes(val)) {
                $('#divOran').slideDown();
                $('#divTutar').hide();
            } else {
                $('#divOran').slideUp();
                $('#divTutar').show();
            }";

if (strpos($content, $oldJs) !== false) {
    $content = str_replace($oldJs, $newJs, $content);
    echo "JS block replaced.\n";
} else {
    echo "JS block NOT found.\n";
}

// Replacement 3: Edit/Copy Param JS
$oldEditJs = "            $('input[name=\"varsayilan_tutar\"]').val(param.varsayilan_tutar);
            $('input[name=\"sira\"]').val(param.sira);";

$newEditJs = "            $('input[name=\"varsayilan_tutar\"]').val(param.varsayilan_tutar);
            $('input[name=\"oran\"]').val(param.oran);
            $('input[name=\"sira\"]').val(param.sira);";

if (strpos($content, $oldEditJs) !== false) {
    $content = str_replace($oldEditJs, $newEditJs, $content);
    echo "Edit/Copy Param JS replaced.\n";
} else {
    echo "Edit/Copy Param JS NOT found.\n";
}

file_put_contents($filePath, $content);
echo "File updated.\n";
