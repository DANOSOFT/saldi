// tools/guardrails/lib/php-worker.mjs - the thread that keeps one PHP process for lib/php.mjs.
//
// lib/php.mjs answers doors.mjs synchronously, so it cannot wait on a child process itself: this
// worker owns the one PHP process (php-syntax.mjs phpCommand(), from PATH or Docker), writes each
// request to it as one line and hands the answer back through the request's port, then wakes the
// waiting thread (Atomics.notify on the request's flag). Requests are answered in order. Once PHP
// has exited or failed, every later request gets the reason.
//
// History:
// 20261106 CL/LH Created.

import { spawn } from "node:child_process";
import { parentPort, workerData } from "node:worker_threads";

const [command, ...args] = workerData.command;
const child = spawn(command, [...args, ...workerData.args], { stdio: ["pipe", "pipe", "pipe"] });
const pending = [];
let chunks = [];
let stderr = "";
let dead = null;

function answer(request, message) {
  request.port.postMessage(message);
  request.port.close();
  Atomics.store(request.flag, 0, 1);
  Atomics.notify(request.flag, 0);
}

function fail(reason) {
  if (dead) return;
  dead = reason;
  for (const request of pending.splice(0)) answer(request, { error: dead });
}

child.stdout.setEncoding("utf8");
child.stdout.on("data", (chunk) => {
  for (let nl = chunk.indexOf("\n"); nl >= 0; nl = chunk.indexOf("\n")) {
    chunks.push(chunk.slice(0, nl));
    chunk = chunk.slice(nl + 1);
    const line = chunks.join("");
    chunks = [];
    const request = pending.shift();
    if (request) answer(request, { response: line });
  }
  if (chunk) chunks.push(chunk);
});
child.stderr.setEncoding("utf8");
child.stderr.on("data", (chunk) => {
  stderr = (stderr + chunk).slice(-2000);
});
child.stdin.on("error", (error) => fail(`PHP's input closed (${error.code ?? error.message})`));
child.on("error", (error) => fail(`${command} could not start (${error.code ?? error.message})`));
child.on("exit", (code, signal) => {
  // Built without a template inside a template's interpolation, so this file stays in doors.mjs's plain subset.
  const why = signal ?? "code " + code;
  const last = stderr.trim() ? ": " + stderr.trim().split("\n").pop() : "";
  fail(`PHP exited (${why})${last}`);
});

parentPort.on("message", (request) => {
  if (dead) return answer(request, { error: dead });
  pending.push(request);
  child.stdin.write(`${request.line}\n`);
});
