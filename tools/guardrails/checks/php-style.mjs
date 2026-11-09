// Check executable PHP tokens so HTML, comments and JavaScript strings are ignored.
// Proven on cef46e47c (MB-78): multiplication used as a numeric cast and crowded closing braces.

import { execFileSync } from "node:child_process";
import { phpCommand } from "./php-syntax.mjs";
import { splitLines } from "../lib/changeset.mjs";

const TOKENIZE = String.raw`
$result = [];
foreach (json_decode(stream_get_contents(STDIN), true) as $source) {
    $tokens = [];
    $line = 1;
    $quoted = false;
    $heredoc = false;
    foreach (token_get_all($source) as $token) {
        $kind = is_array($token) ? token_name($token[0]) : '';
        $text = is_array($token) ? $token[1] : $token;
        $at = $line;
        $line += substr_count($text, "\n");
        if ($kind === 'T_START_HEREDOC') $heredoc = true;
        if ($kind === 'T_END_HEREDOC') {
            $heredoc = false;
            continue;
        }
        if ($heredoc) continue;
        if ($kind === '' && ($text === '"' || $text === chr(96))) {
            $quoted = !$quoted;
            continue;
        }
        if ($quoted || in_array($kind, ['T_WHITESPACE', 'T_COMMENT', 'T_DOC_COMMENT', 'T_INLINE_HTML', 'T_OPEN_TAG', 'T_CLOSE_TAG'], true)) continue;
        if ($kind === 'T_CONSTANT_ENCAPSED_STRING') $text = 'string';
        $tokens[] = ['text' => $text, 'kind' => $kind, 'line' => $at];
    }
    $result[] = $tokens;
}
echo json_encode($result);
`;

function indentation(line) {
  return /^[ \t]*/.exec(line)[0].replaceAll("\t", "    ").length;
}

function inspect(file, source, tokens) {
  const lines = splitLines(source);
  const scopes = [];
  const stack = [];
  const firstOnLine = new Map();
  let parentheses = 0;
  let brackets = 0;
  for (const [index, token] of tokens.entries()) {
    const parent = stack.at(-1);
    token.continuation = parentheses !== (parent?.parentheses ?? 0) || brackets !== (parent?.brackets ?? 0);
    firstOnLine.set(token.line, firstOnLine.get(token.line) ?? token);
    if (file.added.has(token.line) && parent) parent.touched = true;
    if (token.text === "{") {
      const scope = { open: token, parentheses, brackets, children: [], touched: file.added.has(token.line) };
      scopes.push(scope);
      stack.push(scope);
    } else if (token.text === "}") {
      token.closes = stack.pop();
      if (token.closes) token.closes.close = token;
    } else if (parent && !token.continuation && firstOnLine.get(token.line) === token && [";", "{", "}"].includes(tokens[index - 1]?.text)) {
      parent.children.push(token);
    }
    if (token.text === "(") parentheses++;
    if (token.text === ")") parentheses--;
    if (token.text === "[") brackets++;
    if (token.kind === "T_ATTRIBUTE") brackets++;
    if (token.text === "]") brackets--;
  }

  const findings = [];
  const report = (line, message) => findings.push({ path: file.path, line, message });
  for (const scope of scopes) {
    const width = indentation(lines[scope.open.line - 1]);
    for (const token of scope.children) {
      if (!file.added.has(token.line)) continue;
      // Require a deeper body without imposing a formatter on legacy two/four-space or tab styles.
      if (indentation(lines[token.line - 1]) <= width) {
        report(token.line, "indent the statement inside its enclosing PHP block.");
      }
    }
    if (scope.close && file.added.has(scope.close.line) && scope.open.line !== scope.close.line && firstOnLine.get(scope.close.line) === scope.close) {
      if (indentation(lines[scope.close.line - 1]) !== width) report(scope.close.line, "align the closing brace with its opening PHP block.");
    }
  }

  const closesByLine = new Map();
  for (const [index, token] of tokens.entries()) {
    if (file.added.has(token.line) && ((token.text === "*" && (tokens[index + 1]?.text === "1" || tokens[index - 1]?.text === "1")) || (token.text === "*=" && tokens[index + 1]?.text === "1"))) {
      report(token.line, "use an explicit (int) or (float) cast instead of multiplying by 1.");
    }
    if (token.closes && token.closes.open.line !== token.line) {
      const closes = closesByLine.get(token.line) ?? [];
      closes.push(token.closes);
      closesByLine.set(token.line, closes);
    }
  }
  for (const [line, closes] of closesByLine) {
    if (closes.length > 1 && (file.added.has(line) || closes.some(scope => scope.touched))) {
      report(line, "put each multiline PHP block's closing brace on its own line.");
    }
  }
  return findings;
}

export default {
  id: "php-style",
  summary: "explicit numeric casts, indented PHP statements and separate multiline closing braces",
  run(ctx) {
    const files = ctx.files.filter(file => file.status !== "D" && file.path.endsWith(".php") && file.path !== "includes/betweenUpdates2.php" && !ctx.isVendored(file.path));
    if (!files.length) return [];
    const sources = files.map(file => ctx.readHead(file.path) ?? "");
    const [command, ...args] = phpCommand();
    const output = execFileSync(
      command,
      [...args, "-r", TOKENIZE],
      { input: JSON.stringify(sources), encoding: "utf8", maxBuffer: 64 * 1024 * 1024 },
    );
    const tokenized = JSON.parse(output);
    return files.flatMap((file, index) => inspect(file, sources[index], tokenized[index]));
  },
};
