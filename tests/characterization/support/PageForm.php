<?php
// tests/characterization/support/PageForm.php
//
// The fields a browser posts for a form a page has drawn, for tests that save
// a page's form through a child runner (run_debitor_order_page.php): every
// named, enabled input except the buttons, the ticked boxes (a box without a
// value attribute posts "on", as in a browser), the selected (or first) option
// of each select, and each textarea. Of two fields with one name the last one
// wins, as in PHP.
//
// History:
// 20261117 CL/ASR Created for the Del ordre and subscription copy tests (audit findings sales-F2 and sales-F3).

final class PageForm
{
    /** @return array<string, string> */
    public static function fields(string $html, string $formName): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        $xpath = new DOMXPath($doc);
        $form = $xpath->query("//form[@name='$formName']")->item(0);
        if ($form === null) {
            throw new RuntimeException("the page draws no form $formName");
        }
        $fields = [];
        foreach ($xpath->query('.//input', $form) as $input) {
            $name = $input->getAttribute('name');
            $type = strtolower($input->getAttribute('type'));
            if ($name === '' || $input->hasAttribute('disabled') || in_array($type, ['submit', 'button', 'image', 'reset'], true)) {
                continue;
            }
            if (in_array($type, ['checkbox', 'radio'], true)) {
                if (!$input->hasAttribute('checked')) {
                    continue;
                }
                $fields[$name] = $input->hasAttribute('value') ? $input->getAttribute('value') : 'on';
                continue;
            }
            $fields[$name] = $input->getAttribute('value');
        }
        foreach ($xpath->query('.//select', $form) as $select) {
            $name = $select->getAttribute('name');
            if ($name === '' || $select->hasAttribute('disabled')) {
                continue;
            }
            $chosen = null;
            foreach ($select->getElementsByTagName('option') as $option) {
                $value = $option->hasAttribute('value') ? $option->getAttribute('value') : $option->textContent;
                if ($chosen === null || $option->hasAttribute('selected')) {
                    $chosen = $value;
                }
                if ($option->hasAttribute('selected')) {
                    break;
                }
            }
            $fields[$name] = (string)$chosen;
        }
        foreach ($xpath->query('.//textarea', $form) as $textarea) {
            if ($textarea->getAttribute('name') !== '' && !$textarea->hasAttribute('disabled')) {
                $fields[$textarea->getAttribute('name')] = $textarea->textContent;
            }
        }
        return $fields;
    }
}
