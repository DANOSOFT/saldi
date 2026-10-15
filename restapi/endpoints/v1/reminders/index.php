<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/reminders/index.php --- ver 5.0.0 --- 2026.10.01
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
require_once __DIR__ . '/../../../services/MobilePushEndpoint.php';

final class RemindersEndpoint extends MobilePushEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module'=>'bilag', 'action'=>ifset($_SERVER, 'REQUEST_METHOD') === 'GET' ? 'read' : 'write'];
    }

    /** @return list<string> */
    protected function methods(): array
    {
        return ['GET', 'POST'];
    }

    protected function handlePost($data)
    {
        $this->sendResponse(true, $this->reminders()->create($this->subject, $data), '', 201);
    }

    protected function handleGet($id = null)
    {
        $page = $this->pagination();
        $this->sendResponse(true, $this->reminders()->history($page['limit'], $page['before']));
    }
}

(new RemindersEndpoint())->handleRequestMethod();
