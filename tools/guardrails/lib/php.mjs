// tools/guardrails/lib/php.mjs - PHP's own tokenizer for doors.mjs: token_get_all through the PHP 8.2 php-syntax uses.
//
// doors.mjs reads a PHP file by PHP's tokens, never by a lexer of its own: PHP decides where every
// string, comment, heredoc, nowdoc and piece of inline HTML starts and ends. This module runs PHP
// (php-syntax.mjs phpCommand(): php 8.2 from PATH, else Docker) and hands doors.mjs its tokens.
// Importing it gives doors.mjs the tokenizer (usePhpTokenizer); without it, or where PHP cannot run,
// doors.mjs fails closed: every line that names a password in a PHP file counts (doors.mjs phpFailure).
//
// One PHP process answers every request of this process, in order: a worker thread keeps it
// (php-worker.mjs), and tokenize() waits for its answer (Atomics.wait), so doors.mjs reads
// synchronously. A batch is one request: check.mjs hands every PHP file of a change at once
// (doors.mjs prefetchPhp). PHP reads each text as bytes, so a text is sent as its UTF-8, and each
// token's length comes back in bytes and is turned into the text's own characters here.
// short_open_tag is off, as php.ini-production and php.ini-development set it, on every machine:
// a "<?" that opens no "<?php" or "<?=" is inline HTML.
//
// Only PHP 8.2 tokenizes: an older PHP reads "#[" (an attribute) as a comment. A PHP on PATH that
// is not 8.2, without Docker, is no tokenizer (phpCommand exact), and the program itself stops on
// any other version (a GUARDRAILS_PHP_IMAGE of another PHP), so doors.mjs fails closed. Once the
// tokenizer fails (no answer in time, an exit, an answer that does not fit), the process it
// started is stopped: its container by name where Docker runs it, and the process itself.
//
// History:
// 20261106 CL/LH Created.
// 20261109 CL/LH PHP 8.2 only; a failed or timed-out tokenizer's process and container are stopped.

import { spawnSync } from "node:child_process";
import { randomBytes } from "node:crypto";
import { MessageChannel, Worker, receiveMessageOnPort } from "node:worker_threads";
import { phpCommand } from "../checks/php-syntax.mjs";
import { usePhpTokenizer } from "./doors.mjs";

/**
 * The PHP program: one request per line on its input (a JSON list of base64 texts), one answer per line on its
 * output (per text, its tokens as a flat list of PHP's token name, or the character a one-character token is, and
 * the token's length in bytes).
 */
const PROGRAM = String.raw`if (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION !== '8.2') {
  fwrite(STDERR, 'PHP ' . PHP_VERSION . " is not 8.2\n");
  exit(3);
}
while (($line = fgets(STDIN)) !== false) {
  $answer = [];
  foreach (json_decode($line, true) as $text) {
    $tokens = [];
    foreach (token_get_all(base64_decode($text)) as $token) {
      if (is_array($token)) {
        $tokens[] = token_name($token[0]);
        $tokens[] = strlen($token[1]);
      } else {
        $tokens[] = $token;
        $tokens[] = strlen($token);
      }
    }
    $answer[] = $tokens;
  }
  echo json_encode($answer), "\n";
  fflush(STDOUT);
}`;

/** How long the first answer may take (Docker may have to fetch php:8.2-cli), and every later one, in ms; tests shorten them. */
export const TIMEOUTS = { first: 180_000, later: 60_000 };

let worker = null;
let answered = false;
let failure = null;
/** The PHP this module started: the Docker container's name or null, and the process id the worker writes. */
let owned = null;

function start() {
  const name = `guardrails-php-${process.pid}-${randomBytes(4).toString("hex")}`;
  const command = phpCommand(undefined, { exact: true, name });
  owned = { container: command[0] === "docker" ? name : null, pid: new Int32Array(new SharedArrayBuffer(4)) };
  worker = new Worker(new URL("./php-worker.mjs", import.meta.url), {
    workerData: { command, args: ["-d", "memory_limit=-1", "-d", "short_open_tag=0", "-r", PROGRAM], pid: owned.pid },
  });
  worker.unref();
  worker.on("error", (error) => {
    failure ??= `the PHP worker failed (${error.message})`;
    stop();
  });
}

/**
 * Stop the PHP this module started, from this thread, which a pending answer may keep busy: the container by name
 * (docker rm --force; killing the docker client alone leaves it running), then the process, then the worker.
 */
function stop() {
  if (!owned) return;
  const { container, pid } = owned;
  owned = null;
  if (container) spawnSync("docker", ["rm", "--force", container], { stdio: "ignore", timeout: 30_000 });
  const id = Atomics.load(pid, 0);
  if (id > 0) {
    try {
      process.kill(id, "SIGKILL");
    } catch {
      // Already gone.
    }
  }
  worker?.terminate();
}

/** The UTF-16 length of each token whose UTF-8 lengths `bytes` gives, over `text`; null where they do not cover it exactly. */
function charLengths(text, bytes) {
  const out = new Array(bytes.length);
  let i = 0;
  for (let t = 0; t < bytes.length; t++) {
    let need = bytes[t];
    const from = i;
    while (need > 0 && i < text.length) {
      const code = text.codePointAt(i);
      // A lone surrogate goes out as U+FFFD, three bytes.
      need -= code < 0x80 ? 1 : code < 0x800 ? 2 : code < 0x10000 || (code >= 0xd800 && code <= 0xdfff) ? 3 : 4;
      i += code > 0xffff ? 2 : 1;
    }
    if (need !== 0) return null;
    out[t] = i - from;
  }
  return i === text.length ? out : null;
}

/**
 * PHP's tokens of each of `texts`: per text a list of [name, length] (name: PHP's token name, or the character of a
 * one-character token; length in the text's own characters), or null where PHP's lengths do not cover the text.
 * Throws with the reason where PHP cannot run.
 */
export function tokenize(texts) {
  if (!texts.length) return [];
  if (failure) throw new Error(failure);
  try {
    if (!worker) start();
    const flag = new Int32Array(new SharedArrayBuffer(4));
    const { port1, port2 } = new MessageChannel();
    const line = JSON.stringify(texts.map((text) => Buffer.from(text, "utf8").toString("base64")));
    worker.postMessage({ flag, port: port2, line }, [port2]);
    const limit = answered ? TIMEOUTS.later : TIMEOUTS.first;
    const waited = Atomics.wait(flag, 0, 0, limit);
    const message = receiveMessageOnPort(port1)?.message;
    port1.close();
    if (waited === "timed-out" || !message) throw new Error(`PHP gave no answer within ${limit / 1000} s`);
    if (message.error) throw new Error(message.error);
    const answer = JSON.parse(message.response);
    if (!Array.isArray(answer) || answer.length !== texts.length) throw new Error("PHP's answer does not hold one token list per text");
    answered = true;
    return answer.map((flat, n) => {
      const names = [];
      const bytes = [];
      for (let k = 0; k < flat.length; k += 2) {
        names.push(flat[k]);
        bytes.push(flat[k + 1]);
      }
      const lengths = charLengths(texts[n], bytes);
      return lengths ? names.map((name, k) => [name, lengths[k]]) : null;
    });
  } catch (error) {
    failure = error.message.split("\n")[0];
    // No later request is sent: what still runs (a PHP busy past the timeout) is stopped.
    stop();
    throw new Error(failure);
  }
}

usePhpTokenizer(tokenize);
