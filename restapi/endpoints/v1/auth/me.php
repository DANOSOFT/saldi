<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/endpoints/v1/auth/me.php --- ver 5.0.0 --- 2026.10.08
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
// 20261001 CDX/LH Advertise the tested Tier 1 attachment, overview, OCR, push and reminder contracts.
// 20261001 CDX/LH Expose the selected tenant and its company name.

// 20261001 CDX/LH Load additive Tier 2 capabilities and expose precise module permissions.
// 20261001 CDX/LH Add Tier 4 employee link and case/booking permissions from current tenant records.
// 20261007 CDX/LH Gate claims by the selected tenant payable account configuration.
// 20261008 CL/LH Add the mobile Assist identity and delegation contract.

require_once __DIR__ . '/../../../core/MobileAuthEndpoint.php';
require_once __DIR__ . '/../../../core/MobileTenantMetadata.php';
require_once __DIR__ . '/../../../core/EmployeeContext.php';
require_once __DIR__ . '/../../../services/ClaimsService.php';

class AuthMeEndpoint extends MobileAuthEndpoint
{
    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'auth', 'action' => 'self'];
    }

    protected function handleGet($id = null)
    {
        $this->authOperation(function (): void {
            $payload = JWTAuth::validateToken();
            $auth = new MobileAuth();
            $context = $auth->context($this->principal, (int)$this->userId, empty($payload['tenant_id']) ? null : (int)$payload['tenant_id']);
            $user = $context['user'];
            $employee = (int)ifset($user, 'ansat_id', 0);
            $caseRights = null;
            $employeeLink = false;
            $currency = null;
            $tenant = null;
            $name = $user['brugernavn'];
            if ($context['tenant'] !== null) {
                global $sqhost, $squser, $sqpass, $connection;
                $connection = db_connect($sqhost, $squser, $sqpass, $context['tenant']['db'], __FILE__ . ' linje ' . __LINE__);
                $store = new MobileSchema($connection);
                $tenant = MobileTenantMetadata::tenant($context['tenant'], $connection);
                $rows = $store->rows("SELECT var_value FROM settings WHERE var_name='baseCurrency' ORDER BY id DESC LIMIT 1");
                $currency = (string)ifset($rows, [0, 'var_value'], 'DKK');
                if ($this->principal === 'account') {
                    $actor = new EmployeeContext($connection, $this->principal, (int)$user['id']);
                    $name = (string)ifset($actor->employee, 'navn', $name);
                    $caseRights = $actor->caseDigits;
                    $employeeLink = $actor->linked;
                }
            }
            $rights = $this->principal === 'registry' && $context['tenant'] === null ? Rights::registry((string)$user['rettigheder']) : Rights::tenant((string)$user['rettigheder'], $caseRights, $this->principal === 'registry');
            $rights['assistant'] = $context['tenant'] !== null && Rights::allows((string)$user['rettigheder'], 'assistant', 'read', null, $this->principal === 'registry');
            // Tier 1 contracts enabled after the backend integration gates.
            $capabilities = ['api_version' => 1, 'attachments' => 'v2', 'overview' => 'v2', 'multipart' => true, 'ocr_token' => true, 'push' => true, 'reminders' => true];
            $permissions = [];
            foreach (['kontoplan','admin','kassekladde','regnskab','finansrapport','debitorordre','debitorkonti',
                'kreditorordre','kreditorkonti','varer','enheder','backup','debitorrapport','kreditorrapport',
                'produktionsordre','varerapport','bilag','sager'] as $module) {
                $permissions[$module] = [];
                foreach (['read','write'] as $action) {
                    $permissions[$module][$action] = $context['tenant'] !== null
                        && Rights::allows((string)$user['rettigheder'], $module, $action, $caseRights, $this->principal === 'registry');
                }
            }
            $permissions['sager_loen'] = [];
            foreach (['read','write'] as $action) {
                $permissions['sager_loen'][$action] = $context['tenant'] !== null && $employeeLink
                    && Rights::allows((string)$user['rettigheder'], 'sager_loen', $action, $caseRights, $this->principal === 'registry');
            }
            $approve = $context['tenant'] !== null && $employeeLink
                && Rights::allows((string)$user['rettigheder'], 'sager_godkend', 'write', $caseRights, $this->principal === 'registry');
            $permissions['sager_godkend'] = ['read' => false, 'write' => $approve];
            $allBookings = $employeeLink && ($permissions['admin']['write'] || $approve);
            $permissions['bookings_all'] = ['read' => $allBookings, 'write' => $allBookings];
            $capabilities = $this->loadCapabilities($capabilities);
            if ($this->principal === 'account' && $context['tenant'] !== null) {
                $claims = new ClaimsService($connection, $context['tenant']['db'], $this->subject, $this->principal, (int)$user['id']);
                if (!$claims->capability()) {
                    $capabilities['claims'] = false;
                }
            }
            $this->sendResponse(true, ['subject' => $this->subject, 'principal' => $this->principal, 'user' => ['id' => (int)$user['id'], 'username' => $user['brugernavn'], 'name' => $name], 'ansat_id' => $this->principal === 'registry' ? null : ($employee ?: null), 'employee_link' => $employeeLink, 'tenant' => $tenant, 'base_currency' => $currency, 'rights' => $rights, 'capabilities' => $capabilities, 'permissions' => $permissions], 'Current user');
        });
    }

    /** Deployment-owned capability fragments add flags only after their feature gates pass. */
    private function loadCapabilities(array $capabilities): array
    {
        $tier1 = array_keys($capabilities);
        foreach (glob(__DIR__ . '/../../../core/capabilities.d/*.php') ?: [] as $file) {
            $depth = ob_get_level();
            ob_start(static fn(string $output): string => '');
            try {
                $fragment = (static fn(string $path) => require $path)($file);
            } finally {
                while (ob_get_level() > $depth) {
                    ob_end_clean();
                }
            }
            if (!is_array($fragment) || array_is_list($fragment)) {
                throw new RuntimeException('Invalid capability fragment');
            }
            foreach ($fragment as $key => $value) {
                if (!is_string($key) || !preg_match('/^[a-z][a-z0-9_]*$/D', $key)
                    || in_array($key, $tier1, true) || array_key_exists($key, $capabilities)
                    || (!is_bool($value) && $value !== 'v2')) {
                    throw new RuntimeException('Invalid or duplicate capability');
                }
                $capabilities[$key] = $value;
            }
        }
        return $capabilities;
    }

    protected function handlePost($data)
    {
        $this->sendError('validation_failed', 'Use GET /auth/me.php', 405);
    }
}

(new AuthMeEndpoint())->handleRequestMethod();
