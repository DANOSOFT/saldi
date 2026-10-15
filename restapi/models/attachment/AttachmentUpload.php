<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- restapi/models/attachment/AttachmentUpload.php --- ver 5.0.0 --- 2026.10.01
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
// 20261001 CDX/LH Require bounded PDF inspection before finalizing uploads.

require_once __DIR__ . '/AttachmentException.php';
require_once __DIR__ . '/AttachmentModel.php';
require_once __DIR__ . '/../../../includes/std_func.php';

/** Decode and validate both transports before reserving a durable identity. */
final class AttachmentUpload
{
    public const MAX_BYTES = 15 * 1024 * 1024;
    public const MAX_PAGES = 20;
    public const MAX_PIXELS = 60000000;

    /**
     * B5 integration: mobileAttachmentInspectPdf(path) returns measured page_count and decoded_pixels.
     * The inspector must bound its own CPU/memory and reject malformed/encrypted PDFs.
     * @return array{content:string, sha256:string, size:int, client_id:string, extracted_data:array, accountnr:string, subject:?string}
     */
    public static function decode($data, array $files): array
    {
        if (!is_object($data)) {
            throw new AttachmentException('validation_failed', 'Expected an upload object', 422);
        }
        $content = null;
        if ($files !== []) {
            $file = ifset($files, 'file');
            if (count($files) !== 1 || !is_array($file) || !is_int(ifset($file, 'error'))) {
                throw new AttachmentException('validation_failed', 'Expected one multipart file field', 422);
            }
            $error = $file['error'];
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new AttachmentException('payload_too_large', 'File exceeds 15 MB or the server upload limit', 413);
            }
            if ($error !== UPLOAD_ERR_OK || !is_string(ifset($file, 'tmp_name')) || !is_uploaded_file($file['tmp_name'])) {
                throw new AttachmentException('validation_failed', 'Missing or incomplete multipart file', 422);
            }
            if (filesize($file['tmp_name']) > self::MAX_BYTES) {
                throw new AttachmentException('payload_too_large', 'File exceeds 15 MB', 413);
            }
            $content = file_get_contents($file['tmp_name']);
        } else {
            $encoded = ifset($data, 'image_base64');
            if (!is_string($encoded) || $encoded === '') {
                throw new AttachmentException('validation_failed', 'Expected image_base64 or multipart file', 422);
            }
            $encoded = preg_replace('/\Adata:[^;]+;base64,/', '', $encoded);
            if (strlen($encoded) > 4 * (int)ceil(self::MAX_BYTES / 3) + 4096) {
                throw new AttachmentException('payload_too_large', 'File exceeds 15 MB', 413);
            }
            $content = base64_decode($encoded, true);
        }
        if (!is_string($content) || $content === '') {
            throw new AttachmentException('validation_failed', 'Invalid or empty file data', 422);
        }
        $size = strlen($content);
        if ($size > self::MAX_BYTES) {
            throw new AttachmentException('payload_too_large', 'File exceeds 15 MB', 413);
        }
        $requestedSize = ifset($data, 'size', $size);
        if (filter_var($requestedSize, FILTER_VALIDATE_INT) === false || (int)$requestedSize !== $size) {
            throw new AttachmentException('validation_failed', 'File size does not match size', 422);
        }
        $digest = hash('sha256', $content);
        $requestedDigest = ifset($data, 'sha256', $digest);
        if (!is_string($requestedDigest) || !preg_match('/\A[a-fA-F0-9]{64}\z/', $requestedDigest)
            || !hash_equals($digest, strtolower($requestedDigest))) {
            throw new AttachmentException('validation_failed', 'File digest does not match sha256', 422);
        }
        $clientId = ifset($data, 'id', ifset($data, 'client_id'));
        if ($clientId === null) {
            if (isset($data->sha256)) {
                throw new AttachmentException('validation_failed', 'id is required with sha256', 422);
            }
            $clientId = RequestId::resolve(null);
        }
        $clientId = DurableAttachmentModel::uuid($clientId);
        if (isset($data->client_id) && DurableAttachmentModel::uuid($data->client_id) !== $clientId) {
            throw new AttachmentException('validation_failed', 'id and client_id must agree', 422);
        }
        $metadata = ifset($data, 'extracted_data', new stdClass());
        if (!is_object($metadata)) {
            throw new AttachmentException('validation_failed', 'extracted_data must be an object', 422);
        }
        $metadata = (array)$metadata;
        foreach ($metadata as $value) {
            if ((!is_scalar($value) && $value !== null) || (is_string($value) && str_contains($value, "\0"))) {
                throw new AttachmentException('validation_failed', 'extracted_data fields must be scalar', 422);
            }
        }
        foreach (['accountnr', 'subject'] as $key) {
            if (isset($data->$key) && (!is_scalar($data->$key) || (is_string($data->$key) && str_contains($data->$key, "\0")))) {
                throw new AttachmentException('validation_failed', 'Invalid ' . $key, 422);
            }
        }
        return ['content' => $content, 'sha256' => $digest, 'size' => $size, 'client_id' => $clientId,
            'extracted_data' => $metadata, 'accountnr' => (string)ifset($data, 'accountnr', ''), 'subject' => isset($data->subject) ? (string)$data->subject : null];
    }

    /**
     * Validate the detected MIME, convert legacy images, and apply B5's measured PDF limits when available.
     * sha256 identifies original upload bytes; content_sha256 identifies the stored PDF after conversion.
     * @return string Validated PDF bytes.
     */
    public static function pdf(string $content): string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($content);
        if (!in_array($mime, ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            throw new AttachmentException('unsupported_media_type', 'Only PDF, JPEG and PNG are supported', 415);
        }
        $path = tempnam(sys_get_temp_dir(), 'mobile_pdf_');
        if ($path === false) {
            throw new RuntimeException('Unable to create upload temporary file');
        }
        $converted = null;
        try {
            if (file_put_contents($path, $content) !== strlen($content)) {
                throw new RuntimeException('Unable to write upload temporary file');
            }
            if ($mime !== 'application/pdf') {
                $image = getimagesize($path);
                if (!$image) {
                    throw new AttachmentException('validation_failed', 'Invalid image', 422);
                }
                if ($image[0] * $image[1] > self::MAX_PIXELS) {
                    throw new AttachmentException('validation_failed', 'Image exceeds 60 million decoded pixels', 422);
                }
                if (!extension_loaded('gd')) {
                    throw new AttachmentException('validation_failed', 'Image conversion unavailable; upload a PDF', 422);
                }
                $converted = (new AttachmentModel())->convertUploadImage($path);
                if ($converted === false) {
                    throw new AttachmentException('validation_failed', 'Image could not be converted to PDF', 422);
                }
                $content = file_get_contents($converted);
                if ($content === false) {
                    throw new RuntimeException('Unable to read converted PDF');
                }
                $pathToInspect = $converted;
            } else {
                if (!str_starts_with($content, '%PDF-') || !str_contains(substr($content, -2048), '%%EOF')) {
                    throw new AttachmentException('validation_failed', 'Incomplete PDF', 422);
                }
                $pathToInspect = $path;
            }
            if (strlen($content) > self::MAX_BYTES) {
                throw new AttachmentException('payload_too_large', 'Converted PDF exceeds 15 MB', 413);
            }
            $renderer = __DIR__ . '/../../../includes/docsIncludes/attachmentPdfRenderer.php';
            if (is_file($renderer)) {
                require_once $renderer;
            }
            self::checkInspection(mobileAttachmentInspectPdf($pathToInspect));
            return $content;
        } finally {
            unlink($path);
            if (is_string($converted) && is_file($converted)) {
                unlink($converted);
            }
        }
    }

    /** @param array{page_count:int, decoded_pixels:int} $inspection B5 renderer measurements. */
    public static function checkInspection(array $inspection): void
    {
        $pages = ifset($inspection, 'page_count');
        $pixels = ifset($inspection, 'decoded_pixels');
        if (!is_int($pages) || !is_int($pixels) || $pages < 1 || $pixels < 0) {
            throw new AttachmentException('validation_failed', 'Invalid PDF inspection', 422);
        }
        if ($pages > self::MAX_PAGES || $pixels > self::MAX_PIXELS) {
            throw new AttachmentException('validation_failed', 'PDF exceeds 20 pages or 60 million decoded pixels', 422);
        }
    }
}
