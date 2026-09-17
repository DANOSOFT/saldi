<?php
// --- includes/stdFunc/timeclamp.php --- patch 0.0.3 --- 2026-05-26 ---
//
// clampTimeToFractions($data, $path, $startTime, $baseTime, $fractionMin, $fractionMax)
//
// Returns a DateTime::ATOM string for next_interaction:
//   min( fractionMax * remaining, max( baseTime, fractionMin * remaining ) )
//
// $data        - the response array containing the expiry value
// $path        - key path to the expiry field, e.g. ['session', 'expires']
// $startTime         - baseline DateTime to calculate from (default new DateTime())
// $baseTime    - absolute minimum floor as a modify string (default "+5 minutes")
// $fractionMin - lower fraction of remaining lifetime (default 0.2)
// $fractionMax - upper fraction of remaining lifetime, 1.0 = no cap (default 0.8)
//
// Examples:
//   $data['next_interaction'] = clampTimeToFractions($data, ['session', 'expires']);
//   $data['next_interaction'] = clampTimeToFractions($data, ['session', 'expires'], new DateTime(), "+5 minutes", 0.2, 0.8);

if (!function_exists('clampTimeToFractions')) {
    function clampTimeToFractions(array $data, array $path, ?DateTime $startTime = null, string $baseTime = "+5 minutes", float $fractionMin = 0.2, float $fractionMax = 0.8): string {
        $startTime = $startTime ?? new DateTime();
        $floor = (clone $startTime)->modify($baseTime);

        // Traverse the key path with isset safety
        $node = $data;
        foreach ($path as $key) {
            if (!isset($node[$key])) {
                return $floor->format(DateTime::ATOM);
            }
            $node = $node[$key];
        }

        $remainingSeconds = (new DateTime($node))->getTimestamp() - $startTime->getTimestamp();
        $lower = (clone $startTime)->modify('+' . (int)($remainingSeconds * $fractionMin) . ' seconds');
        $upper = (clone $startTime)->modify('+' . (int)($remainingSeconds * $fractionMax) . ' seconds');

        return min($upper, max($floor, $lower))->format(DateTime::ATOM);
    }
}
