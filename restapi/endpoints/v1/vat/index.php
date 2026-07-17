<?php
// 20260715 CL/PHR Restored fiscal-year VAT retrieval through VatModel.

require_once __DIR__ . '/../../../models/finans/VatModel.php';
require_once __DIR__ . '/../../../core/BaseEndpoint.php';

class VatEndpoint extends BaseEndpoint
{
    private function mapEnglishToDanish($field)
    {
        $fieldMap = [
            'description' => 'beskrivelse',
            'vatCode' => 'kode',
            'number' => 'kodenr',
            'fiscalYear' => 'fiscal_year',
        ];

        return $fieldMap[$field] ?? $field;
    }

    protected function handleGet($id = null)
    {
        $vatCode = $_GET['vatcode'] ?? null;

        if ($id) {
            $vatItem = new VatModel(intval($id));
            if (!$vatItem->getId()) {
                $this->sendResponse(false, null, 'VAT item not found', 404);
                return;
            }
            $this->sendResponse(true, $vatItem->toArray());
            return;
        }

        if ($vatCode) {
            $vatItems = VatModel::loadFromVatcode(db_escape_string($vatCode));
        } else {
            $orderBy = $this->mapEnglishToDanish($_GET['orderBy'] ?? 'number');
            $orderDirection = $_GET['orderDirection'] ?? 'ASC';
            $field = isset($_GET['field']) ? $this->mapEnglishToDanish($_GET['field']) : null;
            $value = $_GET['value'] ?? null;

            $vatItems = $field && $value !== null
                ? VatModel::findBy($field, db_escape_string($value))
                : VatModel::getAllItems($orderBy, $orderDirection);
        }

        $items = [];
        foreach ($vatItems as $vatItem) {
            $items[] = $vatItem->toArray();
        }
        $limit = intval($_GET['limit'] ?? 50);
        if ($limit < 1 || $limit > 200) {
            $limit = 50;
        }
        $this->sendResponse(true, array_slice($items, 0, $limit));
    }

    protected function handlePost($data)
    {
        $this->sendResponse(false, null, 'POST method not supported', 405);
    }

    protected function handlePut($data)
    {
        $this->sendResponse(false, null, 'PUT method not supported', 405);
    }

    protected function handleDelete($data)
    {
        $this->sendResponse(false, null, 'DELETE method not supported', 405);
    }
}

$endpoint = new VatEndpoint();
$endpoint->handleRequestMethod();
