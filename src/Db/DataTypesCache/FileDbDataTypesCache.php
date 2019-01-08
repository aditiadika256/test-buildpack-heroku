<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db\DataTypesCache;

use Forrest79\PhPgSql\Db;

class FileDbDataTypesCache extends DbDataTypesCache
{
	/** @var string */
	private $cacheFile;


	public function __construct(Db\Connection $connection, string $cacheFile)
	{
		parent::__construct($connection);
		$this->cacheFile = $cacheFile;
	}


	public function load(): array
	{
		if (!\is_file($this->cacheFile)) {
			$lockFile = $this->cacheFile . '.lock';
			$handle = \fopen($lockFile, 'c+');
			if (($handle === FALSE) || !\flock($handle, LOCK_EX)) {
				throw new \RuntimeException(sprintf('Unable to create or acquire exclusive lock on file \'%s\'.', $lockFile));
			}

			// cache still not exists
			if (!\is_file($this->cacheFile)) {
				$cacheDir = \dirname($this->cacheFile);
				if (!\is_dir($cacheDir)) {
					\mkdir($cacheDir, 0777, TRUE);
				}

				$tempFile = $this->cacheFile . '.tmp';
				\file_put_contents(
					$tempFile,
					'<?php declare(strict_types=1);' . PHP_EOL . \sprintf('return [%s];', self::prepareCacheArray($this->loadFromDb()))
				);
				rename($tempFile, $this->cacheFile); // atomic replace (in Linux)

				if (function_exists('opcache_invalidate')) {
					opcache_invalidate($this->cacheFile, TRUE);
				}
			}

			\flock($handle, LOCK_UN);
			\fclose($handle);
			@\unlink($lockFile); // intentionally @ - file may become locked on Windows
		}

		return require $this->cacheFile;
	}


	private static function prepareCacheArray(array $data): string
	{
		$cache = '';
		\array_walk($data, function(string $typname, int $oid) use (&$cache): void {
			$cache .= sprintf("%d=>'%s',", $oid, str_replace("'", "\\'", $typname));
		});
		return $cache;
	}


	public function clean(): self
	{
		@unlink($this->cacheFile); // intentionally @ - file may not exists
		return $this;
	}

}
