<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/notifications/preferences.php --- ver 5.0.0 --- 2026.10.08
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
// 20261008 CL/LH Expose the default-on assistant_interrupt preference with its translated label.

require_once __DIR__ . '/../../../services/MobilePushEndpoint.php';
require_once __DIR__ . '/../../../services/MobileEventFanout.php';

final class NotificationPreferencesEndpoint extends MobilePushEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'bilag','action'=>'own'];
    }

    /** @return list<string> */
    protected function methods(): array
    {
        return ['GET','PUT'];
    }

    protected function handleGet($id = null)
    {
        $this->sendResponse(true, $this->service()->preferences($this->subject));
    }

    protected function handlePut($data)
    {
        if (!is_object($data)) {
            $this->sendError('validation_failed', 'An object is required', 422);
        }
        try {
            $result = $this->service()->updatePreferences($this->subject, (array)$data);
            foreach ($result['headers'] as $key=>$value) {
                header($key . ': ' . $value);
            }
            $this->sendResponse($result['body']['success'], $result['body']['data'], $result['body']['message'], $result['status']);
        } catch (MobileT2Error $error) {
            $this->sendError($error->error, 'Notification preferences refused', $error->status, ['details'=>$error->details]);
        }
    }

    private function service(): MobileEventFanout
    {
        return new MobileEventFanout($this->conn, MobileAuth::connectRegistry(), $this->tenantId);
    }
}
(new NotificationPreferencesEndpoint())->handleRequestMethod();
