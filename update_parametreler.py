
import os

file_path = r"c:\xampp\htdocs\ersan_elk\views\bordro\parametreler.php"

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replacement 1: HTML Block
old_html = """                    <div class="row">
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
                    </div>"""

new_html = """                    <div class="row">
                        <div class="col-md-6 mb-3" id="divTutar">
                            <?= Form::FormFloatInput("number", "varsayilan_tutar", "0", "0.00", "Varsayılan Tutar", "bx bx-money", "form-control", false, null, "off", false, 'step="0.01"') ?>
                        </div>
                        <div class="col-md-6 mb-3" id="divOran" style="display: none;">
                            <?= Form::FormFloatInput("number", "oran", "0", "0", "Oran (%)", "bx bx-percentage", "form-control", false, null, "off", false, 'step="0.01"') ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <?= Form::FormFloatInput("number", "sira", "0", "0", "Sıralama", "bx bx-sort-amount-up", "form-control") ?>
                        </div>
                    </div>"""

# Normalize line endings for comparison if needed, but let's try direct replace first
# If direct replace fails, we might need to be more flexible with whitespace

if old_html in content:
    content = content.replace(old_html, new_html)
    print("HTML block replaced.")
else:
    print("HTML block NOT found.")
    # Fallback: try to find it with relaxed whitespace
    import re
    # Escape special characters in old_html for regex
    old_html_regex = re.escape(old_html).replace(r'\s+', r'\s+')
    # This might be too complex for a quick script. 
    # Let's try to match a smaller unique part if the big block fails.

# Replacement 2: JS Block
old_js = """            // Oran Bazlı kontrolü
            if (val === 'oran_bazli') {
                $('#divOran').slideDown();
                $('input[name="varsayilan_tutar"]').closest('.col-md-6').hide();
            } else {
                $('#divOran').slideUp();
                $('input[name="varsayilan_tutar"]').closest('.col-md-6').show();
            }"""

new_js = """            // Oran Bazlı kontrolü
            if (['oran_bazli_vergi', 'oran_bazli_sgk', 'oran_bazli_net'].includes(val)) {
                $('#divOran').slideDown();
                $('#divTutar').hide();
            } else {
                $('#divOran').slideUp();
                $('#divTutar').show();
            }"""

if old_js in content:
    content = content.replace(old_js, new_js)
    print("JS block replaced.")
else:
    print("JS block NOT found.")

# Replacement 3: Edit Param JS
old_edit_js = """            $('input[name="varsayilan_tutar"]').val(param.varsayilan_tutar);
            $('input[name="sira"]').val(param.sira);"""

new_edit_js = """            $('input[name="varsayilan_tutar"]').val(param.varsayilan_tutar);
            $('input[name="oran"]').val(param.oran);
            $('input[name="sira"]').val(param.sira);"""

if old_edit_js in content:
    content = content.replace(old_edit_js, new_edit_js)
    print("Edit Param JS replaced.")
else:
    print("Edit Param JS NOT found.")

# Replacement 4: Copy Param JS
old_copy_js = """            $('input[name="varsayilan_tutar"]').val(param.varsayilan_tutar);
            $('input[name="sira"]').val(param.sira);"""

# Note: old_copy_js is identical to old_edit_js, so the previous replace might have caught both if we used replace(..., count) or just replace all.
# string.replace replaces all occurrences by default.
# So if the code is identical, it should have replaced both.
# Let's check if there are any other occurrences.

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("File updated.")
