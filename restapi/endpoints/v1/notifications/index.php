<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/notifications/index.php --- ver 5.0.0 --- 2026.10.04
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
// 20261001 CDX/LH Expose nullable business references on the authenticated inbox.
require_once __DIR__ . '/../../../services/MobilePushEndpoint.php';

final class NotificationsEndpoint extends MobilePushEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'bilag', 'action'=>'own'];
    }

    /** @return list<string> */
    protected function methods(): array
    {
        return ['GET'];
    }

    protected function handleGet($id = null)
    {
        $page = $this->pagination();
        $inbox = (new PushService($this->conn))->inbox($this->subject, $page['limit'], $page['before']);
        $store = new MobileSchema($this->conn);
        $ids = array_map('intval', array_column($inbox['items'], 'notification_id'));
        $refs = [];
        if ($ids) {
            foreach ($store->rows('SELECT id,ref_type,ref_id FROM notifications WHERE id IN (' . implode(',', $ids) . ')') as $row) {
                $refs[(int)$row['id']] = $row;
            }
        }
        foreach ($inbox['items'] as &$item) {
            $ref = $refs[$item['notification_id']];
            $item['ref_type'] = $ref['ref_type'];
            $item['ref_id'] = $ref['ref_id'] === null ? null : (int)$ref['ref_id'];
        }
        unset($item);
        $this->sendResponse(true, $inbox);
    }
}

(new NotificationsEndpoint())->handleRequestMethod();
