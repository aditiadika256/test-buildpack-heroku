<?php declare(strict_types=1);

namespace Forrest79\PhPgSql\Db;

class Connection
{
	/** @var string */
	private $connectionConfig = '';

	/** @var bool */
	private $connectForceNew = FALSE;

	/** @var bool */
	private $connectAsync = FALSE;

	/** @var int */
	private $connectAsyncWaitSeconds = 15;

	/** @var resource */
	private $resource;

	/** @var bool */
	private $connected = FALSE;

	/** @var resource */
	private $asyncStream;

	/** @var RowFactory */
	private $rowFactory;

	/** @var DataTypeParsers\DataTypeParser */
	private $dataTypeParser;

	/** @var Query */
	private $asyncQuery;

	/** @var AsyncResult */
	private $asyncResult;

	/** @var callable[] function(Connection $connection) {} */
	private $onConnect = [];

	/** @var callable[] function(Connection $connection) {} */
	private $onClose = [];

	/** @var callable[] function(Connection $connection, Query $query, float $time) {} */
	private $onQuery = [];


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function __construct(string $connectionConfig = '', bool $connectForceNew = FALSE, bool $connectAsync = FALSE)
	{
		if (!extension_loaded('pgsql')) {
			throw Exceptions\ConnectionException::noExtensionException();
		}

		$this->connectionConfig = $connectionConfig;
		$this->connectForceNew = $connectForceNew;
		$this->connectAsync = $connectAsync;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function connect(): self
	{
		if ($this->connectionConfig === '') {
			throw Exceptions\ConnectionException::noConfigException();
		}

		$connectType = 0;
		if ($this->connectForceNew === TRUE) {
			$connectType = $connectType | PGSQL_CONNECT_FORCE_NEW;
		}
		if ($this->connectAsync === TRUE) {
			$connectType = $connectType | PGSQL_CONNECT_ASYNC;
		}

		$resource = @\pg_connect($this->connectionConfig, $connectType); // intentionally @
		if (!$resource) {
			throw Exceptions\ConnectionException::connectionFailedException();
		} elseif (\pg_connection_status($resource) === PGSQL_CONNECTION_BAD) {
			throw Exceptions\ConnectionException::badConnectionException();
		}

		if ($this->connectAsync === TRUE) {
			$stream = \pg_socket($resource);
			if (!$stream) {
				throw Exceptions\ConnectionException::asyncStreamFailedException();
			}
			$this->asyncStream = $stream;
		} else {
			$this->connected = TRUE;
			if ($this->onConnect) {
				$this->onConnect();
			}
		}

		$this->resource = $resource;

		return $this;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function isConnected($waitForConnect = FALSE): bool
	{
		if ($waitForConnect === TRUE) {
			$this->getConnectedResource();
		}
		return $this->connected;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function ping(): bool
	{
		return \pg_ping($this->getConnectedResource());
	}


	public function setConnectionConfig(string $config): self
	{
		$this->connectionConfig = $config;
		return $this;
	}


	public function setConnectForceNew(bool $forceNew = TRUE): self
	{
		$this->connectForceNew = $forceNew;
		return $this;
	}


	public function setConnectAsync(bool $async = TRUE): self
	{
		$this->connectAsync = $async;
		return $this;
	}


	public function setConnectAsyncWaitSeconds(int $seconds): self
	{
		$this->connectAsyncWaitSeconds = $seconds;
		return $this;
	}


	public function addOnConnect(callable $callback)
	{
		$this->onConnect[] = $callback;
	}


	public function addOnClose(callable $callback)
	{
		$this->onClose[] = $callback;
	}


	public function addOnQuery(callable $callback)
	{
		$this->onQuery[] = $callback;
	}


	public function close(): self
	{
		if ($this->resource !== NULL) {
			\pg_close($this->resource);
			$this->resource = NULL;
			$this->onClose();
		}
		return $this;
	}


	public function setRowFactory(RowFactory $rowFactory): self
	{
		$this->rowFactory = $rowFactory;
		return $this;
	}


	private function getRowFactory(): RowFactory
	{
		if ($this->rowFactory === NULL) {
			$this->rowFactory = new BasicRowFactory;
		}

		return $this->rowFactory;
	}


	public function setDataTypeParser(DataTypeParsers\DataTypeParser $dataTypeParser): self
	{
		$this->dataTypeParser = $dataTypeParser;
		return $this;
	}


	private function getDataTypeParser(): DataTypeParsers\DataTypeParser
	{
		if ($this->dataTypeParser === NULL) {
			$this->dataTypeParser = new DataTypeParsers\BasicDataTypeParser;
		}

		return $this->dataTypeParser;
	}


	/**
	 * @param string|Query $query
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function query($query, ...$params): Result
	{
		return $this->queryArray($query, $params);
	}


	/**
	 * @param string|Query $query
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function queryArray($query, array $params): Result
	{
		$query = Helper::prepareSql($this->normalizeQuery($query, $params));

		$start = $this->onQuery ? microtime(TRUE) : NULL;

		$resource = @\pg_query_params($this->getConnectedResource(), $query->getSql(), $query->getParams()); // intentionally @
		if (!$resource) {
			throw Exceptions\QueryException::queryFailed($query, $this->getLastError());
		}

		if ($start !== NULL) {
			$this->onQuery($query, microtime(TRUE) - $start);
		}

		return new Result($resource, $this->getRowFactory(), $this->getDataTypeParser());
	}


	public function createQuery(string $query, ...$params): Query
	{
		return $this->createQueryArray($query, $params);
	}


	public function createQueryArray(string $query, array $params): Query
	{
		return new Query($query, $params);
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function asyncQuery($query, ...$params): AsyncResult
	{
		return $this->asyncQueryArray($query, $params);
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function asyncQueryArray($query, array $params): AsyncResult
	{
		$this->asyncQuery = $query = Helper::prepareSql($this->normalizeQuery($query, $params));
		if (@\pg_send_query_params($this->getConnectedResource(), $query->getSql(), $query->getParams()) === FALSE) { // intentionally @
			throw Exceptions\QueryException::asyncQueryFailed($query, $this->getLastError());
		}

		if ($this->onQuery) {
			$this->onQuery($query);
		}

		return $this->asyncResult = new AsyncResult($this->getRowFactory(), $this->getDataTypeParser());
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function waitForAsyncQuery(): self
	{
		if ($this->asyncResult === NULL) {
			throw Exceptions\ConnectionException::asyncNoQueryWasSentException();
		}

		$resource = \pg_get_result($this->getConnectedResource());
		if (!$resource) {
			throw Exceptions\QueryException::asyncQueryFailed($this->asyncQuery, $this->getLastError());
		}
		$this->asyncResult->finishAsyncQuery($resource);

		$this->asyncResult = NULL;

		return $this;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function begin(?string $savepoint = NULL): self
	{
		$this->query($savepoint ? "SAVEPOINT $savepoint" : 'START TRANSACTION');
		return $this;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function commit(?string $savepoint = NULL): self
	{
		$this->query($savepoint ? "RELEASE SAVEPOINT $savepoint" : 'COMMIT');
		return $this;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 * @throws Exceptions\QueryException
	 */
	public function rollback(?string $savepoint = NULL): self
	{
		$this->query($savepoint ? "ROLLBACK TO SAVEPOINT $savepoint" : 'ROLLBACK');
		return $this;
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function inTransaction(): bool
	{
		return !in_array(\pg_transaction_status($this->getConnectedResource()), [PGSQL_TRANSACTION_UNKNOWN, PGSQL_TRANSACTION_IDLE], TRUE);
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	public function getResource()
	{
		return $this->getConnectedResource();
	}


	/**
	 * @throws Exceptions\QueryException
	 */
	private function normalizeQuery($query, array $params): Query
	{
		if ($query instanceof Query) {
			if ($params) {
				throw Exceptions\QueryException::cantPassParams();
			}
		} else {
			$query = $this->createQueryArray($query, $params);
		}

		return $query;
	}


	private function getLastError(): string
	{
		return $this->resource === NULL ? \pg_last_error() : \pg_last_error($this->resource);
	}


	/**
	 * @throws Exceptions\ConnectionException
	 */
	private function getConnectedResource()
	{
		if ($this->resource === NULL) {
			$this->connect();
		}

		if ($this->connected === FALSE) {
			$start = \microtime(TRUE);
			do {
				$test = \microtime(TRUE);
				switch (\pg_connect_poll($this->resource)) {
					case PGSQL_POLLING_READING:
						while (!self::isReadable($this->asyncStream));
						break;
					case PGSQL_POLLING_WRITING:
						while (!self::isWritable($this->asyncStream));
						break;
					case PGSQL_POLLING_FAILED:
						throw Exceptions\ConnectionException::asyncConnectFailedException();
					case PGSQL_POLLING_OK:
					case PGSQL_POLLING_ACTIVE: // this can't happen?
						$this->connected = TRUE;
						$this->onConnect();
						return $this->resource;
				}
			} while (($test - $start) <= $this->connectAsyncWaitSeconds);
			throw Exceptions\ConnectionException::asyncConnectTimeoutException($test, $this->connectAsyncWaitSeconds);
		}

		return $this->resource;
	}


	private function onConnect(): void
	{
		\array_walk($this->onConnect, function(callable $event) {
			$event($this);
		});
	}


	private function onClose(): void
	{
		\array_walk($this->onClose, function(callable $event) {
			$event($this);
		});
	}


	private function onQuery(Query $query, ?float $time = NULL): void
	{
		\array_walk($this->onQuery, function(callable $event) use ($query, $time){
			$event($this, $query, $time);
		});
	}


	private static function isReadable($stream): bool
	{
		$read = [$stream]; $write = $ex = [];
		return (bool) \stream_select($read, $write, $ex, $usec = 1, 0);
	}


	private static function isWritable($stream): bool
	{
		$write = [$stream]; $read = $ex = [];
		return (bool) \stream_select($read, $write, $ex, $usec = 1, 0);
	}


	public static function literal(string $value): Literal
	{
		return new Literal($value);
	}

}