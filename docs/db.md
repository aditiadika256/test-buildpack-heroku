# PhPgSql

## DB

**@todo**

- RowFactory
- Sql (Expresion / Literal / Query)
- Connection (Query)
- Result (ResultIterator)
- Row
- Events
- Async
- Prepared statemens (+ Async)
- Transaction

### Basics

#### DB connection

First, we need to create a connection to PostgreSQL:

> Format for connection string is the same as for [pg_connect](http://php.net/manual/en/function.pg-connect.php) function.

```php
$connection = new Forrest79\PhPgSql\Db\Connection('host=localhost port=5432 dbname=test user=user1 password=xyz111 connect_timeout=5');
```

> Good habit is to use `connect_timeout` parameter because default value is infinite.

Pass `TRUE` as the second parameter to force new connection (otherwise, existing connection with the same parameters
will be reused).

Pass `TRUE` as the third parameter connect asynchronously (will be described later).

> Personal note: I'm thinking about removing this in then next big release.

You can create a blank `Connection` object and set connetion parameters on this object. Just set this before `connect()`
is executed.

```php
$connection = new Forrest79\PhPgSql\Db\Connection();
$connection->setConnectionConfig('host=localhost port=5432 dbname=test user=user1 password=xyz111 connect_timeout=5');
$connection->setConnectForceNew(TRUE);
$connection->setConnectAsync(TRUE);
```

Once you have a connection, you can manually connect it:

```php
$connection->connect();
```

When you omit this, connection is automatically connected to a DB, when some command is executed.

Connection can be manually closed:

```php
$connection->close();
```

> IMPORTANT: if you omit bad conection parameters, exception is thrown in the `connect()` function, not when connection string is set to the object.

Of course, you can get back info about actual configuration:

```php
$connectionConfig = $connection->getConnectionConfig();
```

You can check, if connection is connected:

```php
if ($connection->isConnected()) {
    // connection is connected
}
```

Even if connection is connected, there can be some network problem, server can close the connection, etc. For this you
can ping connection:

```php
if ($connection->ping()) {
    // connection is connected and active
}
```

If there is some error on database site, an exception is thrown. This library is not trying to parse database exceptions
to some specific types (foreign key violation, ...). You will get error message right from the PostgreSQL and you can
set format for this message.

```php
$connection->setErrorVerbosity(PGSQL_ERRORS_DEFAULT);
$connection->setErrorVerbosity(PGSQL_ERRORS_VERBOSE);
$connection->setErrorVerbosity(PGSQL_ERRORS_TERSE);
```

- `PGSQL_ERRORS_DEFAULT` is default and produces messages include severity, primary text, position, any detail, hint, or
  context fields
- `PGSQL_ERRORS_VERBOSE` includes all available fields
- `PGSQL_ERRORS_TERSE` returned messages include severity, primary text, and position only

> More about constants can be found at https://www.php.net/manual/en/function.pg-set-error-verbosity.php.

#### Running queries

So we have properly set connection, what do we need to know to execute an SQL query? Important is how to safely pass parameters into query. Bellow is whole chapter about it, for know, just use `?` on place, where you want to pass parameter.

Prepared statements and asynchronous queries will be described later.

The only function you need to know is `query()` (or `queryArgs()`). If you only use this one to execute queries, you won't make a mistake.

But there is another one function `execute()`. You can use this one, when you don't need a result from this query and also when you have no params to pass. Another advantage is, you can run more queries at once, just separate it with `;` (these queries are executed one by one in one statement/transaction) and sending to PostgreSQL is a little bit quicker (but really just a little bit).

```php
$connection->execute('DELETE FROM user_departments WHERE id = 1;DELETE FROM user_departments WHERE id = 2');
```

And with `query()` you can use the same queries (just not two queries in one `query()` call), but prefered is too use parameters. When you use `query()` without parameters, interanlly is used `pg_...` function, that is a little bit quicker to process (but againt, just a little bit, you don't need to care about this much).

```php
$connection->query('DELETE FROM user_departments WHERE id = ?', 1);
$connection->queryArgs('DELETE FROM user_departments WHERE id = ?', [2]);
```

The fifference between `query()` and `queryArgs()` is, that `query()` accepts many parameters and `queryArgs()` accept parameters in an `array`.

Passed variable can be scalar, `array` (is rewriten to many ?, ?, ?, ... - is usefull for example for `column IN (?)`), literal (is passed to SQL as string, never pass with this user input, possible SQL-injection), `bool` or another query.

To pass another query, we need to prepare one and then use it:

```php
$query = Forrest79\PhPgSql\Db\Sql\Query::create('SELECT id FROM users WHERE inserted_datetime::date > ?', '2020-01-02');
$queryArgs = Forrest79\PhPgSql\Db\Sql\Query::createArgs('SELECT id FROM users WHERE inserted_datetime::date > ?', ['2020-01-02']);

$result = $connection->query('SELECT d.id, d.name FROM user_departments ud JOIN departments d ON d.id = ud.department_id WHERE ud.user_id IN (?) AND d.active ORDER BY ?', $query, TRUE, Forrest79\PhPgSql\Db\Sql\Literal::create('d.id'));

$rows = $result->fetchAll();

table($rows);
/**
----------------------------------
| id          | name             |
|================================|
| (integer) 1 | (string) 'IT'    |
| (integer) 3 | (string) 'Sales' |
----------------------------------
*/
```

`ORDER BY` defined with a literal is just for example. You can write this directly to the query.

When you call `query()` function, query is executed in DB a `Result` object is returned. When you don't need query result, you don't need to use this. But mostly, you want data from your query, or how many rows was affected by query. This and more can be fetched from the result.

- `Result::fetch()` return next row from the result (you can call it in a cycle, `NULL` is returned, when there is no next row).
- `Result::fetchSingle()` return single value from row (first value from the first row)
- `Result::fetchAll()` return array of rows
- `Result::fetchPairs()` return associative array `key->value`, first parameter is column for the `key` and second is for the `value`. Columns is detected, when you omit both argument. First column in a query is for `key` a second for a `value`. You can omit key column a pass value column, in this case, you will get array list of values.
- `Result::fetchAssoc()` return array with a specified structure
   - `col1[]col2` build array `[$column1_value][][$column2_value] => Row`
   - `col1|col2=col3` build array `[$column1_value][$column2_value] => $column3_value`
   - `col1|col2=[]` build array `[$column1_value][$column2_value] => Row::toArray()`

Some examples to make it clear:

```php
$row = $connection->query('SELECT * FROM users WHERE id = ?', 1)->fetch();

dump($row); // (Row) ['id' => 1, 'nick' => 'Bob', 'inserted_datetime' => '2020-01-01 09:00:00', 'active' => 1, 'age' => 45, 'height_cm' => 178.2, 'phones' => [200300, 487412]]

$row = $connection->query('SELECT * FROM users WHERE id = ?', -1)->fetch();

dump($row); // (NULL)

$nick = $connection->query('SELECT nick FROM users WHERE id = ?', 1)->fetchSingle();

dump($nick); // (string) 'Bob'

$rows = $connection->query('SELECT id, nick, active FROM users ORDER BY nick')->fetchAll();

table($rows);
/**
---------------------------------------------------
| id          | nick               | active       |
|=================================================|
| (integer) 1 | (string) 'Bob'     | (bool) TRUE  |
| (integer) 2 | (string) 'Brandon' | (bool) TRUE  |
| (integer) 5 | (string) 'Ingrid'  | (bool) TRUE  |
| (integer) 4 | (string) 'Monica'  | (bool) TRUE  |
| (integer) 3 | (string) 'Steve'   | (bool) FALSE |
---------------------------------------------------

// special syntax for creating structure from data
//$row = $result->fetchAssoc('col1[]col2'); // $tree[$val1][$index][$val2] = Db\Row
//$row = $result->fetchAssoc('col1|col2=col3'); // $tree[$val1][$val2] = val2
//$row = $result->fetchAssoc('col1|col2=[]'); // $tree[$val1][$val2] = Db\Row::toArray()

// get indexed array, key is first column, value is second column or you can choose columns manually
//$row = $result->fetchPairs();
//$row = $result->fetchPairs('id', 'name');

//$count = $result->getRowCount(); // ->count() or count($result)
*/
```

@todo Iterate in a cycle

##### Safely passing parameters

Important is how to safety pass parameters to a query. You can do something like this:

```php
$userId = 1;

$connection->execute('DELETE FROM user_departments WHERE id = ' . $userId);
```

But, there is possible **SQL injection**. Imagine this example, where `$userId` can be some user input:

```php
$userId = '1; TRUNCATE user_departments';

$connection->query('DELETE FROM user_departments WHERE id = ' . $userId);

dump($connection->query('SELECT COUNT(*) FROM user_departments')->fetchSingle()); // (integer) 0
```

We need to pass parameter not as concatenating strings but as real query parameter - where we have separated SQL query and list of parameters. In this case DB can fail on this query, because `$userId` is not valid integer and can be used in condition with `id` column.

In this library, there are two possible way to do this. Use `?` for param. This works automatically, and we can use some special functionallity as passing arrays, literals, bools or another queries. We can also use classic parameters `$1`, `$2`, ..., but with this, no special features are available, and you can't combine `?` and `$1`.

Safe example can be:

```php
$userId = 1;

$connection->query('DELETE FROM user_departments WHERE id = ?', $userId);
$connection->query('DELETE FROM user_departments WHERE id = $1', $userId);

dump($connection->query('SELECT COUNT(*) FROM user_departments')->fetchSingle()); // (integer) 6

// ---

$userId = '1; TRUNCATE user_departments';

try {
  $connection->query('DELETE FROM user_departments WHERE id = ?', $userId);
} catch (Forrest79\PhPgSql\Db\Exceptions\QueryException $e) {
  dump($e->getMessage()); // (string) 'Query: 'DELETE FROM user_departments WHERE id = $1' failed with an error: ERROR:  invalid input syntax for type integer: \"1; TRUNCATE user_departments\".'
}

try {
  $connection->query('DELETE FROM user_departments WHERE id = $1', $userId);
} catch (Forrest79\PhPgSql\Db\Exceptions\QueryException $e) {
  dump($e->getMessage()); // (string) 'Query: 'DELETE FROM user_departments WHERE id = $1' failed with an error: ERROR:  invalid input syntax for type integer: \"1; TRUNCATE user_departments\".'
}

dump($connection->query('SELECT COUNT(*) FROM user_departments')->fetchSingle()); // (integer) 6
```

One speciality, you need to know. If you want to use char `?` in query (not in params), escape it with `\` like this `\?`. This is the only one magic thing in this library.




On results we can also get column type in PostgeSQL types or all column names:

```xphp
$row = $result->getColumnType('name');
$row = $result->getColumns();
```

We can get data parsed to the same type as column have:

```xphp
$data = $result->parseColumnValue('price', '123');
```

Or we can check, what columns was accesed in our application for concrete query. When we check this right before request
end or application exit:

```xphp
$parsedColumns = $result->getParsedColumns();
$query = $result->getQuery();
```

Then we get `array` with column names as key and `TRUE`/`FALSE` as value and we also know for what query this request
is. `TRUE` means, that in application was this column accessed. When `NULL` is returned, it means, that no column was
accesed. This could be for example for `INSERT` queries or even for `SELECT` queries.

And for `INSERT`/`UPDATE`/`DELETE` results we can get number of affected rows:

```xphp
$row = $result->getAffectedRows();
```

Finally, we can free result:

```xphp
$result->free();
```

We can also run query asynchronously. Just use this (syntax is the same as query and queryArgs):

```xphp
$asyncQuery = $connection->asyncQuery('SELECT * FROM table WHERE id = ?', 1);
$asyncQuery = $connection->asyncQueryArgs('SELECT * FROM table WHERE id = ?', [1]);
```

You can run just one async query on connection (but you can run more queries separated with `;` at once in one function
call - but only when you don't use parameters - this is `pgsql` extension limitations), before we can run new async
query, we need to get results. When you pass more queries in one function call, you need to call this for every query in
call. Results are getting in the same order as queries are pass to the function.

```xphp
$asyncQuery->getNextResult();
```

If you want to run simple SQL query/queries (separated with `;`) without parameters and you don't care about results,
you can use `execute(string $sql)` function or `asyncExecute(string $sql)` (call `completeAsyncExecute()` to be sure
that all async queries were completed).

> If you use `query()` or `asyncQuery()` without parameters, you can also pass more queries separated with `;`, but you will get only last result for non-async variant. Internally - `execute()` and `query()/asyncQuery()` without parameters call the same `pg_*` functions.

After that, we can use `$result` as normal $result from normal query.

When we get some row, we can fetch columns:

```xphp
echo $row->column1;
echo $row['column1'];
$data = $row->toArray();
```

Ale data have the right PHP type. If some type is not able to be parsed, exception is thrown. You can write and use your
own data type parser:

```xphp
$connection->setDataTypeParser(new MyOwnDataTypeParserWithDataTypeParserInterface);
```

There is also support for prepared statements. You can prepare some query on database with defined "placeholders" and
repeatedly call this query with different arguments. In query, use `?` for parameters, but in prepared statements you
can use as parameter only scalars, nothing else.

```xphp
$prepareStatement = $this->connection->prepareStatement('SELECT * FROM table WHERE id = ?');
$result1 = $prepareStatement->execute(1);
$result2 = $prepareStatement->executeArgs([2]);
```

And of course in async version:

```xphp
$prepareStatement = $this->connection->asyncPrepareStatement('SELECT * FROM table WHERE id = ?');
$asyncQuery1 = $prepareStatement->execute(1);

// you can do logic here

$result1 = $asyncQuery1->getNextResult();

$asyncQuery2 = $prepareStatement->executeArgs([2]);

// you can do logic here

$result2 = $asyncQuery2->getNextResult();
```

Don't be afraid to use transaction:

```xphp
$connection->begin();
$connection->commit();
$connection->rollback();

$connection->begin('savepoint1');
$connection->commit('savepoint1');
$connection->rollback('savepoint1');
```

Or listen on events like connect/close/query/result:

```xphp
$connection->addOnConnect(function(Connection $connection) {
	echo 'this is call after connect is done...';
});
$connection->addOnClose(function(Connection $connection) {
	echo 'this is call right before connection is closed...';
});
$connection->addOnQuery(function(Connection $connection, Query $query, ?float $time = NULL) { // $time === NULL for async queries
	echo 'this is call for every query (via query or execute method)...';
});
$connection->addOnResult(function(Connection $connection, Result $result) {
	echo 'this is call after result is created (only if query with result is call...)';
});
```

PostgreSQL can raise a notice. This is very handy for development purposes. Notices can be read
with `$connection->getNotices(bool $clearAfterRead = TRUE)`. You can call this function after query or at the end of the
PHP script.

### Data type converting

This library automatically converts PG types to PHP types. Basic types are converted by `BasicDataTypeParser`, you can
extend this parser or write your own, if you need to parse another types or if you want to change parsing behavior.

**Important!** To determine PG types from PG result is by default used function `pg_field_type`. This function has one
undocumented behavior, it's sending SQL
query `select oid,typname from pg_type` (https://github.com/php/php-src/blob/master/ext/pgsql/pgsql.c) for every request
to get proper type names. This `SELECT` is relatively fast and parsing works out of the box with this. But this SELECT
can be slower for bigger databases and in common, there is no need to run it for all requests. We can cache this data
and then use function `pg_field_type_oid`. Cache is needed to be flushed only if database structure is changed. You can
use simple cache for this and this is a recommended way. Options are, prepare your own cache with `DataTypesCache`
interface or use one already prepared, this save cache to PHP file (it's really fast especially with opcache):

```php
$rows = $connection->query('SELECT * FROM users')->fetchAll();

table($rows);
/**
-------------------------------------------------------------------------------------------------------------------------------------------
| id          | nick               | inserted_datetime          | active       | age          | height_cm      | phones                   |
|=========================================================================================================================================|
| (integer) 1 | (string) 'Bob'     | (Date) 2020-01-01 09:00:00 | (bool) TRUE  | (integer) 45 | (double) 178.2 | (array) [200300, 487412] |
| (integer) 2 | (string) 'Brandon' | (Date) 2020-01-02 12:05:00 | (bool) TRUE  | (integer) 24 | (double) 180.4 | (NULL)                   |
| (integer) 3 | (string) 'Steve'   | (Date) 2020-01-02 12:05:00 | (bool) FALSE | (integer) 41 | (double) 168   | (NULL)                   |
| (integer) 4 | (string) 'Monica'  | (Date) 2020-01-03 13:10:00 | (bool) TRUE  | (integer) 36 | (double) 175.7 | (NULL)                   |
| (integer) 5 | (string) 'Ingrid'  | (Date) 2020-01-04 14:15:00 | (bool) TRUE  | (integer) 18 | (double) 168.2 | (array) [805305]         |
-------------------------------------------------------------------------------------------------------------------------------------------
*/
```

#### How to extend default data type parsing

If you need to parse some special DB type, you have two options. You can create your own data type parse implementing
interface `Forrest79\PhPgSql\Db\DataTypeParser` with only one function `parse(string $type, ?string $value): mixed`,
that get DB type and value as `string` (or `NULL`) and return PHP value. The second option is preferable - you can
extend existing `Forrest79\PhPgSql\Db\DataTypeParsers\Basic` and only new types.

Let's say, we want to parse `point` data type:

```php
class PointDataTypeParser extends Forrest79\PhPgSql\Db\DataTypeParsers\Basic
{

	public function parse(string $type, ?string $value)
	{
		if (($type === 'point') && ($value !== NULL)) {
			return \array_map('intval', \explode(',', \substr($value, 1, -1), 2));
		}
		return parent::parse($type, $value);
	}
}
		
$connection->setDataTypeParser(new PointDataTypeParser());

$point = $connection->query('SELECT \'(1,2)\'::point')->fetchSingle();

dump($point); // (array) [1, 2]
```

#### How to use cache

You can use caching tp PHP file:

```php
$phpFileCache = new Forrest79\PhPgSql\Db\DataTypeCaches\PhpFile('/tmp/cache'); // we need connection to load data from DB and each connection can has different data types

$connection = new Forrest79\PhPgSql\Db\Connection();
$connection->setDataTypeCache($phpFileCache);

// when database structure has changed:
$phpFileCache->clean($connection);
```

If you want to use your own caching mechanisms, just implement interface `Forrest79\PhPgSql\Db\DataTypeCache`. There is
only one method `load(Connection $connection): array`, that get DB connection and return array with pairs
of `oid->type_name`, where `type_name` is passed to `DataTypeParser`. Or you can use
abstract `Forrest79\PhPgSql\Db\DataTypeCaches\DbLoader`, that has predefined
function `loadFromDb(Db\Connection $connection)` and this function already load typed from DB and return correct `array`
, that you can cache whenever you want. Predefined `PhpFile` uses this `DbLoader`.
