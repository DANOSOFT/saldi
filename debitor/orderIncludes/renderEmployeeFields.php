<?php
// 20260911 CDX/LH SD-186 Render independent employee fields beside each other.

/**
 * Render the order's two employee selections from the same master-data list.
 * Keep historical selections available even after an employee has been closed.
 *
 * @param string[] $employees Active employee names from the current regnskab.
 * @param string|null $reference Current Vor ref. value.
 * @param string|null $performedBy Current Udført af value.
 * @param string $referenceLabel Translated Vor ref. label.
 * @param bool $disabled Whether the order fields are locked for editing.
 * @return string HTML table row with independent, optional employee selections.
 */
function renderOrderEmployeeFields($employees, $reference, $performedBy, $referenceLabel, $disabled = false)
{
    $fields = array(
        'ref' => array('label' => $referenceLabel, 'value' => $reference, 'old' => 'oldRef'),
        'hvem' => array('label' => 'Udført af', 'value' => $performedBy, 'old' => 'oldhvem'),
    );
    $html = '<tr>';
    foreach ($fields as $name => $field) {
        $selected = (string) $field['value'];
        $options = array_values(array_unique(array_merge(array(''), $employees)));
        if (!in_array($selected, $options, true)) {
            $options[] = $selected;
        }
        $label = htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8');
        $oldValue = htmlspecialchars($selected, ENT_QUOTES, 'UTF-8');
        $html .= "<td style='white-space:nowrap'><label for='order-$name'>$label</label></td><td>";
        $html .= "<input type='hidden' name='{$field['old']}' value='$oldValue'>";
        $html .= "<select id='order-$name' name='$name' class='inputbox' style='width:130px;'";
        $html .= " onchange='docChange = true;'" . ($disabled ? ' disabled' : '') . '>';
        foreach ($options as $employee) {
            $value = htmlspecialchars($employee, ENT_QUOTES, 'UTF-8');
            $isSelected = ($employee === $selected) ? ' selected' : '';
            $html .= "<option value='$value'$isSelected>$value</option>";
        }
        $html .= '</select></td>';
    }
    return $html . '</tr>';
}
