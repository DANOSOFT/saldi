// 20261116 CDX/NTR Recognize formatting without erasing string, HTML or comment changes.
// 20261124 CDX/NTR Compare executable PHP tokens while ignoring comments and whitespace.
// 20261126 CDX/NTR Require maintenance when UTF-8 decoding may have lost source bytes.
import { execFileSync } from "node:child_process";
import { phpCommand } from "../checks/php-syntax.mjs";

const TOKENS = String.raw`
$hashes = [];
foreach (json_decode(stream_get_contents(STDIN), true) as $source) {
    $hash = hash_init('sha256');
    foreach (token_get_all($source) as $token) {
        if (is_array($token)) {
            if (in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) continue;
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

/** Whether executable PHP tokens match; whitespace and comments are ignored, string and HTML contents are preserved. */
export function phpBehaviorUnchanged(before, after) {
  // readBase/readHead decode as UTF-8; distinct LATIN9 bytes can become the same replacement character.
  if (before.includes("\uFFFD") || after.includes("\uFFFD")) return false;
  const [command, ...args] = phpCommand();
  const [oldHash, newHash] = JSON.parse(execFileSync(command, [...args, "-d", "display_errors=stderr", "-r", TOKENS], {
    input: JSON.stringify([before, after]), encoding: "utf8", maxBuffer: 1024 * 1024,
  }));
  return oldHash === newHash;
}
