<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- tests/restapi/authz-matrix.d/t4-b4b.php --- ver 5.0.0 --- 2026.10.06
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

return [
    'cases/index.php'=>['module'=>'sager_loen','action'=>'read','methods'=>['GET']],
    'cases/tasks.php'=>['module'=>'sager_loen','action'=>'read','methods'=>['GET']],
    'time-entries/hour-types.php'=>['module'=>'sager_loen','action'=>'read','methods'=>['GET']],
    'time-entries/index.php'=>['module'=>'sager_loen','action'=>'method','methods'=>['GET','POST','PATCH','DELETE']],
    'time-entries/submit.php'=>['module'=>'sager_loen','action'=>'write','methods'=>['POST']],
];
