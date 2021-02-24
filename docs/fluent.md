# PhPgSql

## Fluent

**@todo**
- Complex
- Connection + QueryExecution
- Query (all)
- QueryBuilder
- Sql

### Common use

Fluent interface can be used to simply create SQL queries using PHP.

We can start with ```Fluent\Query``` object:

```xphp
$fluent = new Fluent\Query();
$fluent->select(['*'])->createSqlQuery(); // create Query object with SQL and params to pg_query_params
```

But if fluent object has no DB connection, you can't send query directly to database. You can pass connection as parameter in `create(Db\Connection $connection)` function or the better solution is to start with `Fluent\Connection`, which pass DB connection to `Fluent\Query` automaticaly:

```xphp
$fluent = new Fluent\Connection();
$rows = $fluent->select(['*'])->from('table')->fetchAll();
```

You can use all fetch functions as on `Db\Result`. If you create query that returns no data, you can run it with `execute()`, that return `Db\Result` object.

You can update your query till `execute()` is call, after that, no updates on query is available, you can only execute this query again by calling `reexecute()`:

```xphp
$fluent = (new Fluent\Connection())
	->select(['*'])
	->from('table');

$rows = $fluent->fetchAll();

$freshRows = $fluent->reexecute()->fetchAll();
```

You can start creating your query with every possible command, it does't matter on the order of commands, SQL is always created right. Every query is `SELECT` at first, until you call `->insert(...)`, `->update(...)`, `->delete(...)` or `->truncate(...)`, which change query to apropriate SQL command. So you can prepare you query in common way and at the end, you can decide if you want to `SELECT` data or `DELETE` data or whatsoever. If you call some command more than once, data is merged, for example, this `->select(['column1'])->select(['column2'])` is the same as `->select(['column1', 'column2'])`.

There is one special command ```->table(...)```, it define main table for SQL, when you call select, it will be used as FROM, if you call INSERT it will be used as INTO, the same for UPDATE, DELETE or TRUNCATE.

```xphp
$fluent = (new Fluent\Connection())
	->table('table', 't');

$fluent->select(['*']); // SELECT * FROM table AS t
// $fluent->value(['column' => 1]); // INSERT INTO table(column) VALUES($1);
// $fluent->set(['column' => 1]); // UPDATE table AS t SET column = $1;
```

Every table definition command (like `->table(...)`, `->from(...)`, joins, update table, ...) has table alias definition, you don't need to use this. If you want to create alias for column in select, use string key in array definition:

```xphp
(new Fluent\Connection())
	->select(['column1', 'alias' => 'column_with_alias']); // SELECT column1, column_with_alias AS alias
```

If you call more ```->where(...)``` or ```->having(...)``` it is concat with AND. You can create more sophisticated conditions with ```Complex``` object.

```xphp
(new Fluent\Connection())
	->whereOr(); // add new OR (return Complex object)
		->add('column', 1) // this is add to OR
		->add('column2', [2, 3]) // this is also add to OR
		->addComplexAnd() // this is also add to OR and can contains more ANDs
			->add('column', $this->fluent()->select([1])) // this is add to AND
			->add('column2 = ANY(?)', new Db\Query('SELECT 2')) // this is add to AND
		->parent() // get original OR
		->add('column3 IS NOT NULL') // and add to OR new condition
	->fluent() // back to original fluent object
	->select(['*'])
	->from('table')
	->createSqlQuery()
    ->createQuery() // 'SELECT * FROM table WHERE column = $1 OR column2 IN ($2, $3) OR (column IN (SELECT 1) AND column2 = ANY(SELECT 2)) OR column3 IS NOT NULL'
```

The same can be used with ```HAVING``` and ```ON``` conditions for joins, but ```ON``` conditions don't have this API. You have to pass it manually:

```xphp
(new Fluent\Connection())
	->join('table', 't' /*, here could be the same as in the second argument of 'on' function */)
	// all these ons will be merged to one conditions - 't' is alias if is used or 'table' if there is no alias
	->on('t', 't.id = c.table_id') // most conditions are this simple, so you can pass simple string
	->on('t', ['t.id IN (?)', [1, 2, 3]]) // if you want to use dynamic parameters in condition, use ? in string and add param to array, where first value is condition string
	->on('t', [['t.id = c.table_id'], ['t.id = ?', 1]]) // you can pass more conditions and it will be concat with AND, in this case, every condition must be array, even if there is only one item as condition string (we can't recognize, if second argument will be new condition on string param to first condition)
	->on('t', $complex) // you can pass prepared Complex object, Complex::createAnd(...)/creatrOr(...)
```

Every condition (in `WHERE`/`HAVING`/`ON`) can be simple string, can have one argument with `=`/`IN` detection or can have many argument with `?` character:

```xphp
(new Fluent\Connection())
	->where('column IS NOT NULL');
	->where('column', $value); // in value is scalar = ? will be add, if array, Db\Query or other Fluent IN (?) will be added
	->where('column BETWEEN ? AND ?', $from, $to) // you need pass as many argument as ? is passed
```

To almost every parameters (select, where, having, on, orderBy, returning, from, joins, unions, ...) you can pass ```Db\Sql\Query``` (`Db\Sql` interface) or other ```Fluent\Query``` object. At some places (select, from, joins), you must provide alias if you want to pass this objects.

```xphp
$fluent = (new Fluent\Connection())
	->select(['column'])
	->from('table')
	->limit(1);

(new Fluent\Connection())
	->select(['c' => $fluent])

(new Fluent\Connection())
	->from($fluent, 'c')

(new Fluent\Connection())
	->join($fluent, 'c')

(new Fluent\Connection())
	->where('id IN (?)', $flunt)

(new Fluent\Connection())
	->union($flunt)
```

If you want to create copy of existing fluent query, just use `clone`:

```xphp
$newQuery = clone $existingQuery;
```

If `$existingQuery` was alredy executed, copy is cloned with reset resutlt, so you can still update `$newQuery` and then execute it.

### Inserts

You can insert simple row:

```xphp
(new Fluent\Connection())
	->insert('table')
	->values([
		'column' => 1
	])
	->execute(); // or ->getAffectedRows()
```

Or you can use returning statement:

```xphp
$insertedData = (new Fluent\Connection())
	->insert('table')
	->values([
		'column' => 1
	])
	->returning(['column'])
	->fetch();
```

If you want, you can use multi-insert too:

```xphp
(new Fluent\Connection())
	->insert('table')
	->rows([
		['column' => 1],
		['column' => 2],
		['column' => 3],
	])
	->execute();
```

Here is column names detected from the first value or you can pass them as second parametr in ```insert()```:

```xphp
(new Fluent\Connection())
	->insert('table', ['id', 'name'])
	->rows([
		[1, 'Jan'],
		[2, 'Ondra'],
		[3, 'Petr'],
	])
	->execute();
```

And of course, you can use `INSERT` - `SELECT`:

```xphp
(new Fluent\Connection())
	->insert('table', ['name'])
	->select(['column'])
	->from('table2')
	->execute(); // INSERT INTO table(name) SELECT column FROM table2
```

And if you're using the same names for columns in `INSERT` and `SELECT`, you can call insert without columns list and it will be detected from select columns.

```xphp
(new Fluent\Connection())
	->insert('table')
	->select(['column'])
	->from('table2')
	->execute(); // INSERT INTO table(column) SELECT column FROM table2
```

### Update

You can use simple update:

```xphp
(new Fluent\Connection())
	->update('table')
	->set([
		'column' => 1,
	])
	->where('column', 100)
	->execute();
```

Or complex with from (and joins, ...):

```xphp
(new Fluent\Connection())
	->update('table', 't')
	->set([
		'column' => 1,
		'column_from' => Db\Literal::create('t2.id')
	])
	->from('table2', 't2')
	->where('t2.column', 100)
	->execute();
```

### Delete

Is similar to select, just call ```->delete()```.

### Truncate

Just with table name:

```xphp
(new Fluent\Connection())
	->truncate('table')
	->execute();

(new Fluent\Connection())
	->table('table')
	->truncate()
	->execute();
```
