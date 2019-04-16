<?php

namespace Forrest79\PhPgSql\Fluent;

use Forrest79\PhPgSql\Db;

interface Sql
{

	/**
	 * @param string|self|Db\Query $table
	 * @param string|NULL $alias
	 * @return self
	 */
	function table($table, ?string $alias = NULL): self;


	function select(array $columns): self;


	function distinct(): self;


	/**
	 * @param string|self|Db\Query $from
	 * @param string|NULL $alias
	 * @return self
	 */
	function from($from, ?string $alias = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function join($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function innerJoin($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function leftJoin($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function leftOuterJoin($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function rightJoin($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function rightOuterJoin($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function fullJoin($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @param string|array|Complex|NULL $onCondition
	 * @return self
	 */
	function fullOuterJoin($join, ?string $alias = NULL, $onCondition = NULL): self;


	/**
	 * @param string|self|Db\Query $join table or query
	 * @param string|NULL $alias
	 * @return self
	 */
	function crossJoin($join, ?string $alias = NULL): self;


	/**
	 * @param string $alias
	 * @param string|array|Complex $condition
	 * @return self
	 */
	function on(string $alias, $condition): self;


	/**
	 * @param string|Complex $condition
	 * @param mixed ...$params
	 * @return self
	 */
	function where($condition, ...$params): self;


	function whereAnd(array $conditions = []): Complex;


	function whereOr(array $conditions = []): Complex;


	function groupBy(array $columns): self;


	/**
	 * @param string|Complex $condition
	 * @param mixed ...$params
	 * @return self
	 */
	function having($condition, ...$params): self;


	function havingAnd(array $conditions = []): Complex;


	function havingOr(array $conditions = []): Complex;


	function orderBy(array $colums): self;


	function limit(int $limit): self;


	function offset(int $offset): self;


	/**
	 * @param string|self|Db\Query $query
	 * @return self
	 */
	function union($query): self;


	/**
	 * @param string|self|Db\Query $query
	 * @return self
	 */
	function unionAll($query): self;


	/**
	 * @param string|self|Db\Query $query
	 * @return self
	 */
	function intersect($query): self;


	/**
	 * @param string|self|Db\Query $query
	 * @return self
	 */
	function except($query): self;


	function insert(?string $into = NULL, ?array $columns = []): self;


	function values(array $data): self;


	function rows(array $rows): self;


	function update(?string $table = NULL, ?string $alias = NULL): self;


	function set(array $data): self;


	function delete(?string $from = NULL, ?string $alias = NULL): self;


	function returning(array $returning): self;


	function truncate(?string $table = NULL): self;

}