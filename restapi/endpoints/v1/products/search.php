<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/products/search.php --- ver 5.0.0 --- 2026.10.02
// LICENSE
//
// This program is free software. You can redistribute it and / or
// modify it under the terms of the GNU General Public License (GPL)
// which is published by The Free Software Foundation; either in version 2
// of this license or later version of your choice.
// However, respect the following:
//
// It is forbidden to use this program in competition with Saldi.DK ApS
// or other proprietor of the program without prior written agreement.
//
// The program is published with the hope that it will be beneficial,
// but WITHOUT ANY KIND OF CLAIM OR WARRANTY.
// See GNU General Public License for more details.
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2026 Danosoft ApS
// ----------------------------------------------------------------------
// 20261001 CDX/LH Created.

require_once __DIR__ . '/../../../core/BaseEndpoint.php';
require_once __DIR__ . '/../../../../includes/lager/productCatalog.php';

final class CatalogueSearchEndpoint extends BaseEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'varer', 'action' => 'read'];
    }

    protected function handleGet($id = null)
    {
        try {
            $mode = 'search';
            $this->sendResponse(true, product_catalog_query($mode, $_GET, false));
        } catch (MobileT2Error $error) {
            $message = match ($error->error) {
                'not_found' => 'Product not found',
                'gone' => 'Catalogue cursor expired; start a full sync',
                'payload_too_large' => 'Too many barcode matches',
                default => 'Invalid catalogue query',
            };
            $this->sendError($error->error, $message, $error->status, ['details' => $error->details]);
        }
    }

    protected function handleError($exception = null)
    {
        if ($exception instanceof BadMethodCallException || ($exception instanceof Exception && $exception->getMessage() === 'Method Not Allowed')) {
            header('Allow: GET');
            $this->sendError('validation_failed', 'Method not allowed', 405);
        }
        parent::handleError($exception);
    }
}

(new CatalogueSearchEndpoint())->handleRequestMethod();
