<?php
// tests/characterization/support/LegacyShopStub.php
//
// A stand-in shop for the legacy shop sync characterization (SD-750): a `php -S` server
// on a free local port that records every request (see legacy_shop_stub_router.php).
// The legacy code is pointed at it through grupper art='API' box4/box5/box6, so nothing
// ever reaches a real shop. Some legacy calls go through a backgrounded wget or curl,
// so waitFor() polls until the expected number of requests has arrived. serve() puts a
// file where a product pull downloads it (<shop>/files/<name>).
//
// History:
// 20261026 CL/SZ Created (SD-777, sub-task of SD-750).
// 20261027 CL/SZ serve() for the product pulls' CSV files (SD-778).

final class LegacyShopStub
{
	/** @var resource */
	private $proc;
	private string $log;
	private string $files;
	public int $port;

	public function __construct()
	{
		$this->log = (string)tempnam(sys_get_temp_dir(), 'legacy-shop-stub');
		$this->files = $this->log . '-files';
		mkdir($this->files, 0700);
		$sock = stream_socket_server('tcp://127.0.0.1:0');
		if ($sock === false) {
			throw new RuntimeException('no free local port for the shop stub');
		}
		$this->port = (int)substr(strrchr(stream_socket_get_name($sock, false), ':'), 1);
		fclose($sock);
		$cmd = [PHP_BINARY, '-S', "127.0.0.1:$this->port", __DIR__ . '/legacy_shop_stub_router.php'];
		$env = ['STUB_LOG' => $this->log, 'STUB_FILES' => $this->files] + getenv();
		$proc = proc_open($cmd, [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, $env);
		if (!is_resource($proc)) {
			throw new RuntimeException('could not start the shop stub');
		}
		$this->proc = $proc;
		$until = microtime(true) + 5;
		while (!($c = @stream_socket_client("tcp://127.0.0.1:$this->port", $errno, $errstr, 0.2))) {
			if (microtime(true) > $until) {
				$this->stop();
				throw new RuntimeException('shop stub did not start');
			}
			usleep(50000);
		}
		fclose($c);
	}

	/** Base url of one stand-in endpoint, as it would be stored in box4/box5/box6. */
	public function url(string $name): string
	{
		return "http://127.0.0.1:$this->port/$name/";
	}

	/** Serves $content at <any shop>/files/$name until it is removed with unserve(). */
	public function serve(string $name, string $content): void
	{
		file_put_contents("$this->files/$name", $content);
	}

	public function unserve(string $name): void
	{
		if (is_file("$this->files/$name")) {
			unlink("$this->files/$name");
		}
	}

	/** @return list<array{method:string, uri:string, path:string, query:array<string,string>, ua:string}> */
	public function requests(): array
	{
		$lines = array_filter(explode("\n", (string)file_get_contents($this->log)));
		return array_values(array_map(fn(string $l): array => json_decode($l, true), $lines));
	}

	/**
	 * Waits until at least $count requests (those $filter accepts, if given) have arrived,
	 * then a little longer so a request too many shows up as well, and returns them. With
	 * $count 0 it just waits that settle time.
	 */
	public function waitFor(int $count, float $timeout = 5.0, float $settle = 0.5, ?callable $filter = null): array
	{
		$read = fn(): array => $filter === null ? $this->requests() : array_values(array_filter($this->requests(), $filter));
		$until = microtime(true) + $timeout;
		while (count($read()) < $count && microtime(true) < $until) {
			usleep(50000);
		}
		usleep((int)($settle * 1000000));
		return $read();
	}

	/**
	 * Clears the recorded requests once the log has stopped growing, so a backgrounded
	 * request from the previous test cannot land in the next one.
	 */
	public function reset(float $quiet = 0.4, float $timeout = 5.0): void
	{
		$until = microtime(true) + $timeout;
		do {
			clearstatcache(true, $this->log);
			$size = filesize($this->log);
			usleep((int)($quiet * 1000000));
			clearstatcache(true, $this->log);
		} while (filesize($this->log) !== $size && microtime(true) < $until);
		file_put_contents($this->log, '');
	}

	public function stop(): void
	{
		if (is_resource($this->proc)) {
			proc_terminate($this->proc);
			proc_close($this->proc);
		}
		if (is_file($this->log)) {
			unlink($this->log);
		}
		if (is_dir($this->files)) {
			array_map('unlink', glob("$this->files/*") ?: []);
			rmdir($this->files);
		}
	}
}
