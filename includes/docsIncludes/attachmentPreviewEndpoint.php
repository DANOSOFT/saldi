<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/docsIncludes/attachmentPreviewEndpoint.php --- ver 5.0.0 --- 2026.10.01
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

require_once __DIR__ . '/../../restapi/core/BaseEndpoint.php';
require_once __DIR__ . '/../../restapi/models/attachment/DurableAttachmentModel.php';
require_once __DIR__ . '/../mobileUpdates.php';
require_once __DIR__ . '/attachmentPdfRenderer.php';

/** Shared preview controller; authorization always precedes cache access or rendering. */
class AttachmentPreviewEndpoint extends BaseEndpoint
{
    protected bool $thumbnail = false;

    /** @return array{module:string,action:string} */
    protected function requiredRight(): ?array
    {
        return ['module' => 'bilag', 'action' => 'own'];
    }

    protected function handleGet($id = null)
    {
        mobileTenantUpdates($this->conn);
        $readAll = $this->principal === 'registry';
        if (!$readAll) {
            $user = (int)$this->userId;
            $row = db_fetch_array(db_select("SELECT rettigheder FROM brugere WHERE id=$user", __FILE__ . ' line ' . __LINE__));
            $rights = Rights::tenant((string)ifset($row, 'rettigheder', '0000000000000000'));
            $readAll = $rights['finans'] || $rights['bilag'];
        }
        if (!isset($_GET['id']) || isset($_GET['file']) || isset($_GET['client_id'])) {
            throw new AttachmentException('validation_failed', 'Preview requires id only', 422);
        }
        $store = new DurableAttachmentModel($this->conn, $this->db, $this->subject, $readAll);
        $row = $store->find(['id' => $_GET['id']]);
        clearstatcache(true);
        $source = $store->path($row);
        $page = $this->thumbnail ? '1' : ifset($_GET, 'n');
        if (!is_string($page) || !preg_match('/\A[1-9][0-9]?\z/', $page)) {
            throw new AttachmentException('validation_failed', 'n must be a 1-based page number', 422);
        }
        $jpeg = null;
        if ($this->thumbnail && !empty($row['thumbnail_path'])) {
            try {
                $cached = $store->path(array_replace($row, ['storage_path' => $row['thumbnail_path']]));
                $size = getimagesize($cached);
                if ($size && $size[0] === 200 && $size[1] === 200 && $size[2] === IMAGETYPE_JPEG) {
                    $jpeg = file_get_contents($cached);
                }
            } catch (AttachmentException $error) {
                if ($error->getStatusCode() !== 404) {
                    throw $error;
                }
            }
        }
        if ($jpeg === null) {
            $jpeg = mobileAttachmentRenderPdf($source, (int)$page, $this->thumbnail ? 200 : 1600);
            if ($this->thumbnail) {
                $root = realpath(dirname(poolPuljePath($this->db)));
                $directory = $root . '/.thumbnails';
                if (is_link($directory) || (!is_dir($directory) && !mkdir($directory, 0750) && !is_dir($directory))
                    || realpath($directory) !== $directory) {
                    throw new RuntimeException('Invalid thumbnail directory');
                }
                $name = (int)$row['id'] . '-' . bin2hex(random_bytes(8)) . '.jpg';
                $target = $directory . '/' . $name;
                if (file_put_contents($target, $jpeg, LOCK_EX) !== strlen($jpeg)) {
                    throw new RuntimeException('Unable to cache thumbnail');
                }
                $relative = db_escape_string('.thumbnails/' . $name);
                $rowId = (int)$row['id'];
                $sql = "UPDATE pool_files SET thumbnail_path='$relative' WHERE id=$rowId";
                $updated = $this->conn instanceof mysqli ? mysqli_query($this->conn, $sql) : pg_query($this->conn, $sql);
                if ($updated === false) {
                    unlink($target);
                    throw new RuntimeException('Unable to persist thumbnail cache');
                }
            }
        }
        RequestId::emit();
        write_log(['method' => 'GET', 'path' => parse_url(ifset($_SERVER, 'REQUEST_URI', ''), PHP_URL_PATH),
            'request_id' => RequestId::current(), 'subject' => $this->subject, 'status' => 200], $this->db);
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($jpeg));
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $jpeg;
        exit;
    }

    protected function handlePost($data) { $this->sendError('validation_failed', 'Method Not Allowed', 405); }
    protected function handlePut($data) { $this->sendError('validation_failed', 'Method Not Allowed', 405); }
    protected function handlePatch($data) { $this->sendError('validation_failed', 'Method Not Allowed', 405); }
    protected function handleDelete($data) { $this->sendError('validation_failed', 'Method Not Allowed', 405); }

    protected function handleError($exception = null)
    {
        if ($exception instanceof Exception && $exception->getMessage() === 'Method Not Allowed') {
            $this->sendError('validation_failed', 'Method Not Allowed', 405);
        }
        if ($exception instanceof AttachmentException) {
            $this->sendError($exception->error, $exception->getMessage(), $exception->getStatusCode());
        }
        parent::handleError($exception);
    }
}
