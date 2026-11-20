#!/usr/bin/env python3
"""Concurrent loopback-only shop: return a stock figure after 0.25 seconds."""
# 20261023 CDX/MJ SST-876 Provide a delayed shop for the real cURL regression.
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import time


class ShopHandler(BaseHTTPRequestHandler):
    def do_GET(self):
        time.sleep(0.25)
        self.send_response(200)
        self.end_headers()
        self.wfile.write(b"67")

    def log_message(self, *args):
        pass


class ShopServer(ThreadingHTTPServer):
    request_queue_size = 128
    daemon_threads = True


if __name__ == "__main__":
    with ShopServer(("127.0.0.1", 0), ShopHandler) as server:
        print(server.server_port, flush=True)
        server.serve_forever()
