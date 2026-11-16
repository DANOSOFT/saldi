#!/usr/bin/env python3
"""Loopback-only responses for complete, truncated and timed-out shop transfers."""
# 20261027 CDX/MJ SST-876 Reproduce transport failures after HTTP 200 headers.
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from urllib.parse import urlsplit
import time


class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        path = urlsplit(self.path).path
        body = b'{"success":false}' if path == '/refuse' else b'5'
        if path == '/empty':
            body = b''
        self.send_response(503 if path == '/unavailable' else 200)
        self.send_header('Content-Length', '100' if path in ('/partial', '/timeout') else str(len(body)))
        self.end_headers()
        self.wfile.write(body)
        self.wfile.flush()
        if path == '/timeout':
            time.sleep(2.5)
        self.close_connection = True

    def log_message(self, *args):
        pass


class Server(ThreadingHTTPServer):
    daemon_threads = True
    request_queue_size = 128


if __name__ == '__main__':
    with Server(('127.0.0.1', 0), Handler) as server:
        print(server.server_port, flush=True)
        server.serve_forever()
