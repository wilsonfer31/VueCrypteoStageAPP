<?php
/*
	This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>
*/

/**
 * SQL query generator
 *
 * Example
 * 
 *     $result = $driver->qs()
 *	    ->from('user')
 *      ->select(array('user.name', 'user.email', 'group.name'))
 *      ->join('group', 'group_id', 'id')
 *      ->where(array('service_id', 1))
 *      ->orderBy('user.name')
 *      ->execute()
 *      ->fetchAll();
 * 
 */
class db_Query extends db_Expr
{
	protected $select = array();
	protected $from = array();
	protected $orderBy = array();
	protected $groupBy = array();
	protected $joins = array();
	protected $having = null;
	protected $where = array();
	protected $limitOffset = null;
	protected $limitSize = null;
	protected $distinct = false;

	/**
	 *
	 * @var db_Driver
	 */
	private $_driver = null;

	public function __construct(db_Driver $driver)
	{
		$this->_driver = $driver;
		$this->where = new db_Criteria();
		$this->having = new db_Criteria();
	}

	/**
	 * Add fields to be selected
	 * @param string|array $fields
	 * @return db_Query 
	 */
	public function select($fields)
	{
		if (empty($fields))
			return $this;

		if (!is_array($fields))
			$fields = func_get_args();

		$this->select = array_merge($this->select, $fields);
		return $this;
	}

	/**
	 * Add a FROM part to the query
	 * @param string $table
	 * @return db_Query 
	 */
	public function from($table)
	{
		$this->from[] = $table;
		return $this;
	}

	/**
	 * Add a JOIN part to the query
	 * @param string $table Target table
	 * @param string $on The column of the source table or the complete 'on' statement if $columnJoin is null
	 * @param type $columnJoin The columns on the target table
	 * @param type $alias Alias to use for the target table
	 * @param type $type Type of join: LEFT, RIGHT, INNER
	 * @return db_Query 
	 */
	public function join($table, $on, $columnJoin = null, $alias = null, $type = 'LEFT')
	{
		$this->joins[] = array('table'=>$table, 'alias' => $alias, 'on'=>$on, 'column'=>$columnJoin, 'type'=>$type);
		return $this;
	}

	/**
	 * Add a RIGHT JOIN. See join()
	 * @param type $column
	 * @param type $table
	 * @param type $columnJoin
	 * @param type $alias
	 * @return type 
	 */
	public function rightJoin($table, $column, $columnJoin = null, $alias = null)
	{
		return $this->join($table, $column, $columnJoin, $alias, 'RIGHT');
	}

	/**
	 * Add a LEFT JOIN. See join()
	 * @param type $column
	 * @param type $table
	 * @param type $columnJoin
	 * @param type $alias
	 * @return type 
	 */
	public function leftJoin($column, $table, $columnJoin = null, $alias = null)
	{
		return $this->join($table, $column, $columnJoin, $alias, 'LEFT');
	}

	/**
	 * Add an INNER JOIN. See join()
	 * @param type $column
	 * @param type $table
	 * @param type $columnJoin
	 * @param type $className
	 * @return type 
	 */
	public function innerJoin($column, $table, $columnJoin = null, $className = null)
	{
		return $this->join(array($table, $column, $columnJoin, 'INNER'));
	}

	/**
	 * Add a WHERE part. Args can be a db_Criterias or an array of 
	 * db_Criterias or an array of the form field => value
	 * @return db_Query 
	 */
	public function where()
	{
		$this->where->doAnd($this->makeCriterias(func_get_args()));
		return $this;
	}

	/**
	 * Add a GROUP BY part
	 * @param string|array $fields
	 * @return db_Query 
	 */
	public function groupBy($fields)
	{
		if (empty($fields))
			return $this;

		if (!is_array($fields))
			$fields = func_get_args();

		$this->groupBy = array_merge($this->groupBy, $fields);
		return $this;
	}

	/**
	 * Add a HAVING part. Args can be a db_Criterias or an array of 
	 * db_Criterias or of array of the form field => value
	 * @return db_Query 
	 */
	public function having()
	{
		$this->having->doAnd($this->makeCriterias(func_get_args()));
		return $this;
	}

	/**
	 * @internal
	 * @param type $args
	 * @return db_Criteria 
	 */
	protected function makeCriterias($args)
	{
		$first = array_shift($args);
		if ($first instanceof db_Criteria)
			return $first;

		if (is_string($first)) {
			throw new db_Exception('Not implemented');
		}

		if (is_object($first))
			$first = (array)$first;
			
		if (is_array($first)) 
			return new db_Criteria($first);
		
		throw new db_Exception('Not implemented');
	}

	/**
	 * Add an ORDER part.
	 * @param string|array $fields
	 * @param type $dir [optionnal] ASC or DESC
	 * @return db_Query 
	 */
	public function order($fields, $dir = 'ASC')
	{		
		if (empty($fields))
			return $this;

		if (!is_array($fields))
			$fields = array($fields);

		$this->orderBy = $this->orderBy + array_fill_keys($fields, $dir);
		return $this;
	}

	/**
	 * Add an ORDER part.
	 * @param array $fields
	 * @return db_Query 
	 */
	public function orderBy($fields)
	{
		if (empty($fields))
			return $this;

		if (is_object($fields)) {
			if (isset($fields->field))
				return $this->order($fields->field, $fields->direction);
			$fields = (array)$fields;
		}

		if (is_array($fields) && isset($fields[0])) {
			foreach ($fields as $v) 
				$this->orderBy[$v->field] = $v->direction;
			return $this;			
		}

		$this->orderBy = $this->orderBy + $fields;
		return $this;
	}

	/**
	 *  Add an ORDER ASC part.
	 * @param string|array $fields
	 * @return db_Query 
	 */
	public function ascOrder($fields)
	{		
		if (!is_array($fields))
			$fields = func_get_args();
		return $this->order($fields, 'ASC');
	}

	/**
	 * Add an ORDER DESC part.
	 * @param string|array $fields
	 * @return db_Query 
	 */
	public function descOrder($fields)
	{
		if (!is_array($fields))
			$fields = func_get_args();
		return $this->order($fields, 'DESC');
	}

	/**
	 * Limit the resulting set.
	 * @param type $offset
	 * @param type $size
	 * @return db_Query 
	 */
	public function limit($offset, $size)
	{
		$this->limitOffset = $offset;
		$this->limitSize = $size;
		return $this;
	}
	
	/**
	 * Distinct
	 * @param boolean $bool
	 * @return db_Query
	 */
	public function distinct($bool)
	{
		$this->distinct = $bool;
		return $this;
	}

	/**
	 * Return a SQL string
	 * @return string 
	 */
	public function toSql()
	{
		$params = array();
		$adapter = $this->_driver->getAdapter();
		$select = $this->select ? implode(',', array_map(array($adapter, 'quoteName'), $this->select)) : ' * ';
		$from = implode(',', array_map(array($adapter, 'quoteName'), $this->from));
		
		$join = ' ';
		foreach ($this->joins as $j)
		{
			if ($j['column'])
			{
				if (!is_array($j['column']))
					$j['column'] = array($j['column']);

				if (!is_array($j['on']))
					$j['on'] = array($j['on']);

				$on = array();
				$aon = array_combine($j['column'], $j['on']);
				foreach ($aon as $k=>$v)
					$on[] = $adapter->quoteName($v) . ' = ' . $adapter->prefixColumn($k, $j['alias']?$j['alias']:$j['table']);
										
				$join .= 
					$j['type'].' JOIN ' . $adapter->quoteName($j['table']) . ($j['alias']?' AS '.$adapter->quoteName($j['alias']):'') .
					' ON ' . implode(' and ', $on);
			}
			else {
				if ($j['on'] instanceof db_Criteria) {
					$on = $j['on']->toSql($adapter);
					$params[] = $j['on']->getParams();
				} else $on = $j['on'];
				$join .= 
					$j['type'].' JOIN ' . $adapter->quoteName($j['table']) . ($j['alias']?' AS '.$adapter->quoteName($j['alias']):'') .
					' ON ' . $on;
			}
			$join .= "\n";
		}

		$where = '';
		if ($this->where) {
			$where = 'WHERE '.$this->where->toSql($adapter);
			$params[] = $this->where->getParams();
		}

		$group = '';
		if ($this->groupBy) {
			$group = 'GROUP BY ' . implode(',', array_map(array($adapter, 'quoteName'), $this->groupBy));
		}

		$order = '';		
		if ($this->orderBy) {
			$order = array();
			foreach ($this->orderBy as $field => $dir)
//				$order[] = (is_numeric($field) ? $dir : $adapter->quoteName($field)) . ' ' . $dir;
				$order[] = (is_numeric($field) ? $dir : $field) . ' ' . $dir;
			$order = 'ORDER BY ' . implode(',', $order);
		}

		$having ='';
		if ($this->having) {
			$having = 'HAVING '.$this->having->toSql($adapter);
			$params[] = $this->having->getParams();
		}
		
		if ($this->distinct)
			$select = ' distinct '.$select;

		$sql = 'SELECT '.$select."\n".
		       'FROM '.$from ."\n".
			   $join.
			   $where."\n".
			   $group."\n".
			   $having."\n".
			   $order."\n";
		
		$sql = $adapter->limitQuery($sql, $this->limitSize, $this->limitOffset);
		return $sql;
	}

	public function __toString()
	{
		return $this->toSql();
	}

	/**
	 * Return the bind variables for this query.
	 * @return array 
	 */
	public function getParams()
	{
		$jParams = array();
		foreach ($this->joins as $j)
			if ($j['on'] instanceof db_Criteria)
				$jParams = $j['on']->getParams();
		
		$params =  array_merge(
			$jParams,
			$this->where ? $this->where->getParams() : array(),
			$this->having ? $this->having->getParams() : array()
		);
		
		//array_walk($params, create_function('$k,&$x,$adapter', 'return $x instanceof DateTime ? $adapter->formatDate($x) : $x;'), $this->_driver->getAdapter()); // XXX
		return $params;
	}

	/**
	 * Execute the query and return a result set as a PDOStatement object 
	 * @return PDOStatement
	 */
	public function execute()
	{
		return $this->_driver->execute($this->toSql(), $this->getParams());
	}

	public function getSelect()
	{
		return $this->select;
	}

	public function getJoins()
	{
		return $this->joins;
	}

	public function getHaving()
	{
		return $this->having;
	}

	public function getWhere()
	{
		return $this->where;
	}

	public function getLimit()
	{
		return $this->limitSize;
	}

	public function getOffset()
	{
		return $this->limitOffset;
	}

	public function getOrderBy()
	{
		return $this->orderBy;
	}

	public function getGroupBy()
	{
		return $this->groupBy;
	}
}