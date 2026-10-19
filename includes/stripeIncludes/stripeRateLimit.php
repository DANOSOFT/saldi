<?php
//                ___   _   _   ___  _     ___  _ _
//               / __| / \ | | |   \| |   |   \| / /
//               \__ \/ _ \| |_| |) | | _ | |) |  <
//               |___/_/ \_|___|___/|_||_||___/|_\_\
//
// --- includes/stripeIncludes/stripeRateLimit.php --- 2026.08.07
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
// Copyright (c) 2003-2026 Saldi.dk ApS
// ----------------------------------------------------------------------
// 20260807 CL/LH Created: small file-based sliding-window limiter for the
//                public Stripe endpoints (per-IP coarse cap, per-order cap,
//                alert-mail throttling). Files live under the install's
//                temp/ dir via __DIR__ so the limiter is CWD-independent.
//                Fails OPEN on filesystem trouble: a broken temp dir must
//                not take the subscribe page down.

if (!function_exists('stripe_rate_limit')) {
	// true = allowed (and the attempt is recorded); false = over the limit.
	function stripe_rate_limit($key, $max, $windowSec) {
		$dir = dirname(__DIR__, 2) . '/temp';
		if (!is_dir($dir) && !@mkdir($dir, 0777, true)) return true; // fail open
		$file = $dir . '/.ht_stripe_rl_' . md5($key);
		$now = time();
		$stamps = [];
		$fp = @fopen($file, 'c+');
		if (!$fp) return true; // fail open
		@flock($fp, LOCK_EX);
		$raw = stream_get_contents($fp);
		if ($raw !== false && $raw !== '') {
			foreach (explode("\n", $raw) as $line) {
				$t = (int)$line;
				if ($t > $now - (int)$windowSec) $stamps[] = $t;
			}
		}
		$allowed = count($stamps) < (int)$max;
		if ($allowed) $stamps[] = $now;
		ftruncate($fp, 0);
		rewind($fp);
		fwrite($fp, implode("\n", $stamps));
		@flock($fp, LOCK_UN);
		fclose($fp);
		return $allowed;
	}
}
