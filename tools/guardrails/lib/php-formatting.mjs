// 20261116 CDX/NTR Recognize formatting without erasing string, HTML or comment changes.
import { execFileSync } from "node:child_process";
import { phpCommand } from "../checks/php-syntax.mjs";

const TOKENS = String.raw`
$hashes = [];
foreach (json_decode(stream_get_contents(STDIN), true) as $source) {
    $hash = hash_init('sha256');
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if ($token[0] === T_WHITESPACE) continue;
            $text = $token[0] === T_OPEN_TAG ? rtrim($token[1]) : $token[1];
            hash_update($hash, $token[0] . ':' . strlen($text) . ':' . $text);
        } else {
            hash_update($hash, 'literal:' . $token);
        }
    }
    $hashes[] = hash_final($hash);
}
echo json_encode($hashes);
`;

/** Compare PHP tokens without executing the source; only inter-token whitespace is ignored. */
export function phpFormattingOnly(before, after) {
  const [command, ...args] = phpCommand();
  const [oldHash, newHash] = JSON.parse(execFileSync(command, [...args, "-r", TOKENS], {
    input: JSON.stringify([before, after]), encoding: "utf8", maxBuffer: 1024 * 1024,
  }));
  return oldHash === newHash;
}
