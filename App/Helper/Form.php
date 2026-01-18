<?php


namespace App\Helper;

class Form
{
    public static function FormFloatInput(
        $type,
        $name,
        $value,
        $placeholder,
        $label,
        $icon,
        $class = "form-control",
        $required = false,
        $maxlength = null,
        $autocomplete = "on",
        $readonly = false,
        $attributes = ''
    ) {
        return '
        <div class="form-floating form-floating-custom">
            <input type="' . htmlspecialchars($type) . '" class="' . htmlspecialchars($class) . '" id="' . htmlspecialchars($name) . '"
                name="' . htmlspecialchars($name) . '" 
                value="' . htmlspecialchars($value ?? '') . '" 
                placeholder="' . htmlspecialchars($placeholder ?? '') . '" ' . ($required ? 'required' : '') . ' ' . ($maxlength ? 'maxlength="' . htmlspecialchars($maxlength) . '"' : '') . '
                autocomplete="' . htmlspecialchars($autocomplete) . '"
                ' . ($readonly ? 'readonly' : '') . '
                ' . $attributes . '
                >
            <label for="input-' . htmlspecialchars($name) . '">' . htmlspecialchars($label ?? '') . '</label>
            <div class="form-floating-icon">
                ' . ((strpos($icon, 'bx') !== false || strpos($icon, 'fa') !== false) ? '<i class="' . htmlspecialchars($icon) . '"></i>' : '<i data-feather="' . htmlspecialchars($icon) . '"></i>') . '
            </div>
        </div>';
    }

    //FormFloatTextarea

    public static function FormFloatTextarea(
        $name,
        $value,
        $placeholder,
        $label,
        $icon,
        $class = "form-control",
        $required = false,
        $minHeight = "100px",
        $rows = 5
    ) {
        return '
        <div class="form-floating form-floating-custom">
            <textarea class="' . htmlspecialchars($class) .
            '" id="' . htmlspecialchars($name) .
            '" name="' . htmlspecialchars($name) .
            '" placeholder="' . htmlspecialchars($placeholder ?? '') .
            '" style="min-height: ' . htmlspecialchars($minHeight) .
            ';min-width:100%" ' . ($required ? 'required' : '') . '  rows="' . htmlspecialchars($rows) . '">' .
            htmlspecialchars($value ?? '') .
            '</textarea>
            <label for="input-' . htmlspecialchars($name) . '">' . htmlspecialchars($label ?? '') . '</label>
            <div class="form-floating-icon">
                ' . ((strpos($icon, 'bx') !== false || strpos($icon, 'fa') !== false) ? '<i class="' . htmlspecialchars($icon) . '"></i>' : '<i data-feather="' . htmlspecialchars($icon) . '"></i>') . '
            </div>
        </div>';
    }

    // public static function FormSelect2($name, 
    //                                    $options, 
    //                                    $selectedValue = null, 
    //                                    $label, $icon, 
    //                                    $valueField = '', 
    //                                    $textField = '', 
    //                                    $class = "form-select select2", 
    //                                    $required = false)
    // {
    //     $html = '
    //     <div class="form-floating form-floating-custom">
    //         <select style="width:100%" 
    //                 class="' . htmlspecialchars($class) . '" 
    //                 id="' . htmlspecialchars($name) . '" 
    //                 name="' . htmlspecialchars($name) . '" ' . 
    //                 ($required ? 'required' : '') . '>';

    //     foreach ($options as $option) {
    //         $value = is_object($option) ? $option->$valueField : (is_string($option) ? $option : (string) $option);
    //         $text = is_object($option) ? $option->$textField : (is_string($option) ? $option : (string) $option);
    //         $selected = ($value == $selectedValue) ? ' selected' : '';
    //         $html .= '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($text) . '</option>';
    //     }

    //     $html .= '</select>
    //         <label for="input-' . htmlspecialchars($name) . '">' . htmlspecialchars($label) . '</label>
    //         <div class="form-floating-icon">
    //             <i data-feather="' . htmlspecialchars($icon) . '"></i>
    //         </div>
    //     </div>';

    //     return $html;
    // }


    public static function FormSelect2(
        $name,
        $options,
        $selectedValue,
        $label,
        $icon = '',
        $valueField = 'key',
        $textField = '',
        $class = "form-select select2",
        $required = false,
        $style = 'width:100%'
    ) {
        // Eğer valueField boşsa, key kullan
        if ($valueField === '') {
            $valueField = 'key';
        }

        $html = '
    <div class="form-floating form-floating-custom">
        <select style=' . $style . ' 
                class="' . htmlspecialchars($class) . '" 
                id="' . htmlspecialchars($name) . '" 
                name="' . htmlspecialchars($name) . '" 
                ' . ($required ? 'required' : '') . '>';

        foreach ($options as $key => $option) {
            // Normalize value/text to safe strings.
            if ($valueField === 'key') {
                $value = $key;
            } else {
                if (is_object($option)) {
                    $value = property_exists($option, $valueField) ? $option->$valueField : (property_exists($option, 'id') ? $option->id : $key);
                } elseif (is_array($option)) {
                    $value = $option[$valueField] ?? ($option['id'] ?? $key);
                } else {
                    $value = $option;
                }
            }

            if (is_object($option)) {
                if ($textField) {
                    $text = property_exists($option, $textField) ? $option->$textField : '';
                } else {
                    $text = property_exists($option, $valueField) ? $option->$valueField : (property_exists($option, 'name') ? $option->name : '');
                }
            } elseif (is_array($option)) {
                $text = $textField ? ($option[$textField] ?? '') : ($option[$valueField] ?? ($option['name'] ?? ''));
            } else {
                $text = $option;
            }

            // Ensure we never pass arrays into htmlspecialchars.
            if (is_array($value)) {
                $value = '';
            }
            if (is_array($text)) {
                $text = '';
            }
            $selected = (($value) == $selectedValue) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value ?? '') . '"' . $selected . '>' . htmlspecialchars($text ?? '') . '</option>';
        }

        $html .= '</select>
        <label for="input-' . htmlspecialchars($name) . '">' . htmlspecialchars($label ?? '') . '</label>
        <div class="form-floating-icon">
            ' . ((strpos($icon, 'bx') !== false || strpos($icon, 'fa') !== false) ? '<i class="' . htmlspecialchars($icon) . '"></i>' : '<i data-feather="' . htmlspecialchars($icon) . '"></i>') . '
        </div>
    </div>';

        return $html;
    }

    //Multiple Select2
    public static function FormMultipleSelect2(
        $name,
        $options,
        $selectedValues = [],
        $label = '',
        $icon = '',
        $valueField = '',
        $textField = '',
        $class = "form-select select2",
        $required = false
    ) {
        // If valueField is empty, use key
        if ($valueField === '') {
            $valueField = 'key';
        }

        $html = '
    <div class="form-floating form-floating-custom">
        <select style="width:100%" 
                class="' . htmlspecialchars($class) . '" 
                id="' . htmlspecialchars($name) . '" 
                name="' . htmlspecialchars($name) . '[]" 
                multiple="multiple" ' .
            ($required ? 'required' : '') . '>';

        foreach ($options as $key => $option) {
            if ($valueField === 'key') {
                $value = $key;
            } else {
                $value = is_object($option) ? $option->$valueField : (is_string($option) ? $option : (string) $option);
            }
            $text = is_object($option) ? ($textField ? $option->$textField : $option->$valueField) : (is_string($option) ? $option : (string) $option);
            $selected = (in_array($value, (array) $selectedValues)) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value ?? '') . '"' . $selected . '>' . htmlspecialchars($text ?? '') . '</option>';
        }

        $html .= '</select>
        <label for="input-' . htmlspecialchars($name) . '">' . htmlspecialchars($label ?? '') . '</label>
        <div class="form-floating-icon">
            ' . ((strpos($icon, 'bx') !== false || strpos($icon, 'fa') !== false) ? '<i class="' . htmlspecialchars($icon) . '"></i>' : '<i data-feather="' . htmlspecialchars($icon) . '"></i>') . '
        </div>
    </div>';

        return $html;
    }



    //Type File
    public static function FormFileInput($name, $label = null, $icon = 'file', $class = "form-control", $required = false)
    {
        return '
    <div class="form-floating form-floating-custom">
        <input type="file" class="' . htmlspecialchars($class) . '" id="' . htmlspecialchars($name) . '" name="' . htmlspecialchars($name) . '" ' . ($required ? 'required' : '') . '>
        <label for="input-' . htmlspecialchars($name) . '">' . htmlspecialchars($label ?? '') . '</label>
        <div class="form-floating-icon">
            ' . ((strpos($icon, 'bx') !== false || strpos($icon, 'fa') !== false) ? '<i class="' . htmlspecialchars($icon) . '"></i>' : '<i data-feather="' . htmlspecialchars($icon) . '"></i>') . '
        </div>
    </div>';
    }
}
