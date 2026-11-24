<?php
// 20261110 CDX/ASR Render role views without a database or translation cache.

function findtekst($key, int $language = 1): string
{
	$parts = explode('|', (string) $key, 2);
	return $parts[1] ?? $parts[0];
}

function ifset($values, $key, $default = null)
{
	return $values[$key] ?? $default;
}
