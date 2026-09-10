<?php
// --- includes/docsIncludes/FileReservation.php ---
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
//
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260910 CL/NTR Added FileReservation: atomic no-replace reservation of a target filename
//                  (fopen 'x') with automatic "_N" suffixing, so two concurrent uploads with
//                  the same base name can never settle on the same path and overwrite each
//                  other (SST-776 follow-up).

if (!class_exists('FileReservation')) {
	/**
	 * Reserves a unique file path inside a directory without a check-then-write race.
	 *
	 * The usual pattern - loop over file_exists() until "name_N.ext" is free, then write to
	 * it - leaves a window between the check and the write in which a concurrent request can
	 * settle on the same name; whichever writes last silently replaces the other. This class
	 * closes that window by creating the target path itself with fopen() in 'x' mode, which
	 * fails atomically when the path already exists, so only one request can ever win a
	 * given name. The reserved file is an empty placeholder that the caller then overwrites
	 * in place with move_uploaded_file(), rename(), file_put_contents(), an external
	 * converter, etc. Never unlink the placeholder before writing - that reopens the race.
	 *
	 * Sibling extensions let a caller treat a base name as taken when any related file
	 * exists, e.g. a "scan.jpg" temp file or a "scan.info" metadata file next to the
	 * "scan.pdf" being reserved. Only the reserved extension is actually created; siblings
	 * are protected indirectly because every competing reservation checks them too.
	 *
	 * Typical use:
	 *   $res = FileReservation::reserve($dir, 'scan', 'pdf', ['pdf', 'jpg', 'jpeg', 'png', 'info']);
	 *   if ($res === null) { ... directory not writable / no free name ... }
	 *   if (!move_uploaded_file($tmp, $res->path())) {
	 *       $res->discard(); // nothing was written, drop the empty placeholder
	 *   }
	 */
	final class FileReservation {
		/** @var string Directory the file lives in, without trailing slash. */
		private $dir;
		/** @var string Base name that won the reservation, possibly suffixed ("scan_2"). */
		private $baseName;
		/** @var string Extension that was reserved, without leading dot. */
		private $ext;

		private function __construct(string $dir, string $baseName, string $ext) {
			$this->dir = $dir;
			$this->baseName = $baseName;
			$this->ext = $ext;
		}

		/**
		 * Atomically reserves "$dir/$baseName.$ext", or the first free "$baseName{$separator}N.$ext"
		 * (N = 1, 2, ...) when the base name is already taken by a file with the reserved
		 * extension or any of the sibling extensions.
		 *
		 * @param string   $dir          Existing directory to reserve inside.
		 * @param string   $baseName     Already-sanitised file name without extension.
		 * @param string   $ext          Extension to reserve, with or without leading dot.
		 * @param string[] $siblingExts  Extensions that also count as "name taken". The
		 *                               reserved extension is always included.
		 * @param string   $separator    Placed between base name and counter.
		 * @param int      $maxAttempts  Upper bound on candidate names tried.
		 * @return self|null Null when the directory is not writable or no candidate name
		 *                   could be reserved within $maxAttempts.
		 */
		public static function reserve(string $dir, string $baseName, string $ext, array $siblingExts = [], string $separator = '_', int $maxAttempts = 10000): ?self {
			$dir = rtrim($dir, '/');
			$ext = ltrim($ext, '.');
			$siblingExts = array_map(function ($e) { return ltrim($e, '.'); }, $siblingExts);
			if (!in_array($ext, $siblingExts, true)) {
				$siblingExts[] = $ext;
			}

			for ($n = 0; $n < $maxAttempts; $n++) {
				$candidate = $n === 0 ? $baseName : $baseName . $separator . $n;
				if (self::anyExists($dir, $candidate, $siblingExts)) {
					continue;
				}
				// 'x' = create only; fails if the path appeared between the check and here.
				$handle = @fopen("$dir/$candidate.$ext", 'x');
				if ($handle !== false) {
					fclose($handle);
					return new self($dir, $candidate, $ext);
				}
				if (!is_dir($dir) || !is_writable($dir)) {
					return null;
				}
				// Lost the race for this candidate to a concurrent reservation; try the next one.
			}
			return null;
		}

		/**
		 * @param string[] $exts
		 */
		private static function anyExists(string $dir, string $baseName, array $exts): bool {
			foreach ($exts as $e) {
				if (file_exists("$dir/$baseName.$e")) {
					return true;
				}
			}
			return false;
		}

		/** Full path of the reserved (placeholder) file. */
		public function path(): string {
			return "$this->dir/$this->baseName.$this->ext";
		}

		/** Base name that won the reservation, possibly suffixed. */
		public function baseName(): string {
			return $this->baseName;
		}

		/** Reserved extension without leading dot. */
		public function ext(): string {
			return $this->ext;
		}

		/** Directory the reservation was made in, without trailing slash. */
		public function dir(): string {
			return $this->dir;
		}

		/**
		 * Path of a related file sharing the reserved base name, e.g. the original image
		 * that gets converted into the reserved PDF, or the ".info" metadata file.
		 */
		public function siblingPath(string $ext): string {
			return "$this->dir/$this->baseName." . ltrim($ext, '.');
		}

		/**
		 * Removes the reserved file. Call this when the write into the reservation failed,
		 * so an empty placeholder is not left behind. Do NOT call it before writing.
		 */
		public function discard(): bool {
			return @unlink($this->path());
		}
	}
}
