<?php
/*
	Copyright (C) 2011 Crypteo
	@author Dorian Boissonnade <dboissonnade@crypteo.fr>

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
 * \brief SQL expression wrapper
 */
class db_Expr {

	protected $expr = null;

	function __construct($x) {
		$this->expr = $x;
	}

	function __tostring() {
		return (string)$this->expr;
	}
}

/**
 * List of db_Criterion to generate 'where' query
 */
class db_Criteria {

	const _AND_ = ' AND ';
	const _OR_ = ' OR ';
	protected $operator;
	protected $criterias;

	public function __construct(array $criterias = [], $op = self::_AND_) {
		$this->operator = $op;
		$this->criterias = $criterias;
		array_walk($this->criterias, array($this, 'criterize'));
	}

	/**
	 * @return bool True is there is the criteria is empty
	 */
	public function isEmpty() {
		return empty($criterias);
	}
	
	/**
	 * Add a new criteria for this node
	 * @param mixed $criterias
	 * @return db_Criteria
	 */
	public function add($criterias) {
		if (!is_array($criterias)) $criterias = array($criterias);
		array_walk($criterias, array($this, 'criterize'));
		$this->criterias = array_merge($this->criterias, $criterias);
		return $this;
	}
	
	/**
	 * @ignore
	 * @param mixed $criterias
	 * @return db_Criteria 
	 */
	public function doAnd($criterias) {
		return $this->operator == db_Criteria::_AND_ ? $this->add($criterias) : new db_Criteria(array($this, $criterias), db_Criteria::_AND_);
	}

	/**
	 * @ignore
	 * @param mixed $criterias
	 * @return db_Criteria 
	 */
	public function doOr($criterias) {
		return $this->operator == db_Criteria::_OR_ ? $this->add($criterias) : new db_Criteria(array($this, $criterias), db_Criteria::_OR_);
	}

	/**
	 * @internal
	 * @param db_Criteria $criterias
	 * @param string $key
	 * @param db_Adapter $adapter 
	 */
	protected function sqlize(db_Criteria &$criterias, $key, db_Adapter $adapter) {
		$criterias = $criterias->toSql($adapter);
	}

	/**
	 * @internal
	 * @param mixed $value
	 * @param string $key 
	 */
	protected function criterize(&$value, $key) {
		if (!($value instanceof self))
			$value = new db_Criterion($key, $value);
	}

	/**
	 * Transform the criterias in SQL code
	 * @param db_Adapter $adapter
	 * @return string
	 */
	public function toSql(db_Adapter $adapter)
	{
		if (empty($this->criterias))
			return $this->operator == self::_AND_ ? ' 1=1 ' : ' 1=0 ';

		$criterias = $this->criterias;
		array_walk($criterias, array($this, 'sqlize'), $adapter);
		return ' ('.implode( $this->operator, $criterias ).') ';
	}

	/**
	 * Get the bind variables for the criterias
	 * @return array
	 */
	public function getParams() {
		$params = array();
		foreach ($this->criterias as $c)
			$params = array_merge($params, $c->getParams());
		return $params;
	}	
}

/**
 * Criterion for a database search
 */
class db_Criterion extends db_Criteria {

	public $column;
	public $value;
	public $op;
	private $placeHolder;

	/**
	 * Create a new criteria
	 * @param mixed $column
	 * @param mixed $value
	 * @param string $op
	 * @param string $bind If false, no bind variable will be used.
	 */
	public function __construct($column, $value, $op = '=', $bind = true) {
		$this->column = $column;
		$this->value = $value;
		$this->op = $op;
		$this->placeHolder = $bind && !($this->value instanceof db_Expr);
	}

	/**
	 * Transform the criteria in SQL code
	 * @param db_Adapter $adapter
	 * @return string
	 */	
	public function toSql(db_Adapter $adapter) {
		if (is_array($this->value)) {
			$value = $this->placeHolder ? array_fill(0, count($this->value), '?') : $this->value;
			$value =  "(" . implode( ",", $value ) . ")";
			if ($this->op=='=') $this->op = 'in';
		}
		else if (is_null($this->value)) {
			$this->op = ($this->op == '=' || $this->op == ' is null ' ? ' is null ' : ' is not null ');
			$value = '';
		}
		else
			$value = $this->placeHolder ? '?' : $this->value;
		return '('.$adapter->quoteName($this->column) . ' ' . $this->op . ' ' . $value.')';
	}

	/**
	 * Get the bind variable for the criteria
	 * @return array
	 */	
	public function getParams() {
		if (!$this->placeHolder || is_null($this->value))
			return array();

		return is_array($this->value) ? $this->value : array($this->value);
	}
}

class db_CustomCriterion extends db_Criteria {

	public $sql;
	public $params;

	public function __construct($sql, array $params) {
		$this->sql = $sql;
		$this->params = $params;
	}

	/**
	 * Transform the criteria in SQL code
	 * @param db_Adapter $adapter
	 * @return string
	 */	
	public function toSql(db_Adapter $adapter) {
		return ' ('.$this->sql.') ';
	}

	/**
	 * Get the bind variable for the criteria
	 * @return array
	 */	
	public function getParams() {
		return $this->params;
	}
}

/**
 * Represents a filter to be used with the search() function
 * @abstract
 */
abstract class db_Filter {
	public $name;
	public $args;

	public function __construct($name, $args = null) {
		$this->name = $name;
		$this->args = $args;
	}
	
	abstract public function compute($v);
}

class db_Filter_NotNull extends db_Filter {
	public function compute($v) {
		return new db_Criteria(array(
			new db_Criterion($this->args, '', '<>'),
			new db_Criterion($this->args, null, '<>')));
	}
}

class db_Filter_Eq extends db_Filter {
	public function compute($v) {
		return new db_Criterion($this->args, $v, '=');
	}
}

class db_Filter_Gte extends db_Filter {
	public function compute($v) {
		return new db_Criterion($this->args, $v, '>=');
	}
}

class db_Filter_Lte extends db_Filter {
	public function compute($v) {
		return new db_Criterion($this->args, $v, '<=');
	}
}

class db_Filter_Like extends db_Filter {
	public function compute($v) {
		return new db_Criterion($this->args, $v.'%', ' like ');
	}
}

class db_Filter_Bool extends db_Filter {
	public function compute($v) {
		if ($v === -1) return null;
		$fv = 0;
		if (is_array($this->args)) {$fv = $this->args[1]; $this->args = $this->args[0];}
		return new db_Criterion($this->args, $fv, $v?'<>':'=');
	}
}

class db_Filter_Between extends db_Filter {
	public function compute($v) {
		$criterias = array();
		if (@$v->from) $criterias[] = new db_Criterion ($this->args, $v->from, '>=' );
		if (@$v->to) $criterias[] = new db_Criterion ($this->args, $v->to, '<=' );
		return new db_Criteria($criterias);		
	}
}

class db_Filter_Find extends db_Filter {
	public function compute($v) {
		$v = trim(str_replace( array ('*','_','~','?','^','%','!'), array('','','','','','',''), $v));
		$keywords = explode(' ', $v);
		$criterias = array();
		foreach ($keywords as $k)
		{
			$criterias2 = array();
			foreach ($this->args as $f)
				$criterias2[]  = new db_Criterion($f, '%'.$k.'%', 'like');
			$criterias2 = new db_Criteria($criterias2, db_Criteria::_OR_ );
			$criterias[] = $criterias2;
		}
		return new db_Criteria($criterias);	
	}
}

class db_Filter_FindField extends db_Filter {
	public function compute($v) {
		$v = trim(str_replace( array ('*','_','~','?','^','%','!'), array('','','','','','',''), $v));
		$keywords = explode(' ', $v);
	
		$criterias = array();
		foreach ($this->args as $f)
		{
			$criterias2 = array();
			foreach ($keywords as $k)
				$cond2->addAnd(new db_Criterion($f, '%'.$k.'%', 'like'));
			$criterias[] = $criterias2;
		}
		return new db_Criteria($criterias);
	}
}

class db_Filter_List extends db_Filter {
	public function compute($v) {
		if (!is_array($v)) $v = array($v);
		if (empty($v)) return null;
		return new db_Criterion($this->args, $v, ' in ');
	}
}

class db_Filter_Call extends db_Filter {
	public function compute($v) {
		return call_user_func($this->args, $v);
	}
}

/**
 * Represents a list of filters to be used with the search() function
 */
class db_Filters {

	/**
	 * Array of db_Filter
	 * @var array $filtersList
	 */
	protected $filtersList = array();

	/**
	 * Add a new filter to the list 
	 * @param db_Filter $filter
	 * @return db_Filters 
	 */
	public function addFilter(db_Filter $filter) {
		$this->filtersList[$filter->name] = $filter;
		return $this;
	}

	public function isEmpty() {
		return empty($this->filtersList);
	}

	/**
	 * Return a db_Criterias object that corresponds to the filters
	 * @param array $filters Array of parameters for the filters: keys are filter name
	 * @return db_Criteria 
	 */
	public function compute($filters, $operator = db_Criteria::_AND_)
	{
		$criterias = array();

		function xxx($x) {
			return $x!=="";
		}

		foreach ($filters as $k=>$v)
		{
			if (!isset($this->filtersList[$k]))
				throw new Exception('Incorrect search type "'.$k.'"');
			$filter = $this->filtersList[$k];

			// Ignore null entries
			if ($v===null || $v === '') continue;
			
			// Ignore null entries inside a list
			if (is_array($v)) {
				$v = array_filter($v, 'xxx');
				if (empty($v)) continue;
			}

			$crit = $filter->compute($v);
			if ($crit) $criterias[] = $filter->compute($v);
		}
		
		return new db_Criteria($criterias, $operator);
	}
}

abstract class db_Relation {

	/**
	 *
	 * @var string
	 */
	protected $name;
	/**
	 *
	 * @var db_Table
	 */
	protected $source;
	/**
	 *
	 * @var db_Table
	 */
	protected $target;
	protected $sourceFields;
	protected $targetFields;

	public function targetize(&$field, $k, $name) {
		$field = $name.'_'.$field;
		//$field = $field.'_'.$name;
	}
	
	/**
	 *
	 * @param string $name Name of the relation
	 * @param db_Table|string $source Name of the source table
	 * @param db_Table|string $target Name of the target table
	 */
	public function __construct($name, /*db_Table*/ $source, /*db_Table*/ $target)
	{
		$this->name = $name;
		$this->source = $source;
		$this->target = $target;
		//$this->initInternal();
	}

	public function getName() {
		return $this->name;
	}
	
	public function setSourceFields($fields) {
		if (!is_array($fields)) $fields = array($fields);
		$this->sourceFields = $fields;
	}
	
	public function setTargetFields($fields) {
		if (!is_array($fields)) $fields = array($fields);
		$this->targetFields = $fields;
	}	

	private function initInternal() {
		// Move this elsewhere to avoid instancing all classes all the time
		if (!($this->source instanceof db_Table)) $this->source = db_Table::Get($this->source);
		if (!($this->target instanceof db_Table)) $this->target = db_Table::Get($this->target);
		$this->init();
	}
	
	abstract protected function init();
	abstract public function get(db_Row $row, $arg = null);
	abstract public function set(db_Row $row,  $target, $arg = null);
	abstract public function add(db_Row $row,  $target, $arg = null);
	abstract public function remove(db_Row $row, $target);
	
	static private $_relations = array();
	
	static public function AddORelation(db_Relation $rel)
	{
		self::$_relations[$rel->source->getName()][$rel->getName()] = $rel;
	}
	
	static public function AddRelation($name, $type, 
			$sourceTable, $targetTable, 
			$sourceFields = null, $targetFields = null)
	{
		self::$_relations[$sourceTable][$name] = array ($name, $type, 
			$sourceTable, $targetTable, 
			$sourceFields, $targetFields);
	}

	static public function AddTRelation($name, $type, 
			db_Table $sourceTable, db_Table $targetTable, 
			$sourceFields = null, $targetFields = null)
	{
		self::$_relations[$sourceTable->getName()][$name] = array ($name, $type, 
			$sourceTable, $targetTable, 
			$sourceFields, $targetFields);
	}
	
	static public function GetRelation($table, $name) 
	{
		$name = strtolower($name);
		if (!isset(self::$_relations[$table][$name]))
			throw new db_Exception("Relation [$name] does not exist in table [$table]");
		$relDesc = self::$_relations[$table][$name];
		if (!($relDesc instanceof db_Relation)) {
			$class = 'db_Relation_'.$relDesc[1];
			$rel = new $class($relDesc[0], $relDesc[2], $relDesc[3]);		
			if ($relDesc[4]) $rel->setSourceFields($relDesc[4]);
			if ($relDesc[5]) $rel->setTargetFields($relDesc[5]);
		} else $rel = $relDesc;
		
		$rel->initInternal();                        
		return $rel;
	}
	
	static public function GetRelations($table) 
	{
		$rels = array();
		foreach (self::$_relations[$table] as $relDesc)
		{
			$class = 'db_Relation_'.$relDesc[1];
			$rel = new $class($relDesc[0], $relDesc[2], $relDesc[3]);
			$rel->initInternal();
			if ($relDesc[3]) $rel->setSourceFields($relDesc[4]);
			if ($relDesc[4]) $rel->setTargetFields($relDesc[5]);                        
			$rels[$relDesc[0]] = $rel;
		}
		
		return $rels;
	}

	/**
	 * Add a ManyToMany relation
	 * @param string $name Name of the relation
	 * @param string $source Name of the source table
	 * @param string $target Name of the target table
	 * @return db_Relation_ManyToMany 
	 *//*
	static public function AddManyToMany($name, $source, $target)
	{
		$rel = new db_Relation_ManyToMany($name, db_Table::Get($source), db_Table::Get($target));
		db_Table::Get($source)->addRelation($rel);
		return $rel;
	}*/
	
}

class db_Relation_OneToMany extends db_Relation
{
	protected function init() {
		$this->sourceFields = $this->source->getPrimaryKeys();
		$this->targetFields = $this->sourceFields;
		array_walk($this->targetFields, array($this,'targetize'), $this->source->getName());
	}

	public function get(db_Row $row, $arg = null) {
		
		$criteria = array_intersect_key( $row->toArray(), array_flip($this->sourceFields) );
		$criteria = array_combine( $this->targetFields, $criteria );
		return $this->target->selectAll($criteria, $arg);
	}
	
	public function set(db_Row $row, $target, $arg = null) {
		
		/*$otarget = $this->target->select(
			array_combine($this->target->GetPrimaryKeys(), array_intersect_key((array)$target, $this->target->GetPrimaryKeys()))
		);

		if (!$otarget) $otarget = $this->target->newRow();
		$otarget->setFromAssoc($target, false);
		foreach (array_combine($this->sourceFields, $this->targetFields) as $s=>$t)
			$otarget->$t = $row->$s;
		return $otarget->save();*/
		throw new db_Exception('Invalid operation');
	}
	
	public function add(db_Row $row, $target, $arg = null) {
		$target = $this->target->newRow()->setFromAssoc($target);
		foreach (array_combine($this->sourceFields, $this->targetFields) as $s=>$t)
			$target->$t = $row->$s;
		return $target->save();
	}	

	public function update(db_Row $row, $target, $arg = null) {
		
		$target = $this->target->select(
			array_combine($this->target->GetPrimaryKeys(), array_intersect_key($target, $this->target->GetPrimaryKeys()))
		)->setFromAssoc($target);
		
		foreach (array_combine($this->sourceFields, $this->targetFields) as $s=>$t)
			$target->$t = $row->$s;
		return $target->save();
	}

	public function remove(db_Row $row, $target) {
		throw new Exception('Not implemented');
	}
}

class db_Relation_ManyToOne extends db_Relation
{

	protected function init() {
		$this->targetFields = $this->target->getPrimaryKeys();
		$this->sourceFields = $this->targetFields; 
		array_walk($this->sourceFields, array($this,'targetize'), $this->target->getName());
	}

	public function get(db_Row $row, $arg = null) {
		$criteria = array_intersect_key( $row->toArray(), array_flip($this->sourceFields) );
                //var_dump($this->sourceFields);
                $criteria = array_combine( $this->targetFields, $criteria );
		return $this->target->select($criteria);
	}
	
	public function set(db_Row $row, $target, $arg = null) {
		foreach (array_combine($this->sourceFields, $this->targetFields) as $s=>$t)
			$row->$s = $target->$t;
		// TODO $row->{strtolower($this->name)} = $target;
		return $row;
	}
	
	public function add(db_Row $row, $target, $arg = null) {
		throw new db_Exception('Invalid operation');
	} 

	public function update(db_Row $row, $target, $arg = null) {
		
		$target = $this->target->select(
			array_combine($this->target->GetPrimaryKeys(), array_intersect_key($target, $this->target->GetPrimaryKeys()))
		)->setFromAssoc($target);
		
		foreach (array_combine($this->sourceFields, $this->targetFields) as $s=>$t)
			$target->$t = $row->$s;
		return $target->save();
	}

	public function remove(db_Row $row, $target) {
		throw new Exception('Not implemented');
	}
}


class db_Relation_ManyToMany extends db_Relation {

	/**
	 *
	 * @var db_Table
	 */
	private $hasTable;
	private $hasTargetFields;
	protected function init()
	{
		$this->sourceFields = $this->source->getPrimaryKeys();
		$this->targetFields = $this->target->getPrimaryKeys();

		if (!$this->hasTable) {
			try {
				$this->hasTable = db_Table::Get($this->source->getName().'_has_'.$this->target->getName());
			} catch (PDOException $e) {
				$this->hasTable = db_Table::Get($this->target->getName().'_has_'.$this->source->getName());
			}
		} else if (!($this->hasTable instanceof db_Table)) {
			$this->hasTable = db_Table::Get($this->hasTable);
		}

		$this->hasSourceFields = $this->sourceFields;
		array_walk($this->hasSourceFields, array($this,'targetize'), $this->source->getName());
		if (!$this->hasTargetFields) {
			$this->hasTargetFields = $this->targetFields;
			array_walk($this->hasTargetFields, array($this,'targetize'), $this->target->getName());
		}
	}
	
	public function setHasTable(db_Table $table) {
		$this->hasTable = $table;
	}	

	public function get(db_Row $row, $arg = null) { 
		// REWRITE
		if (count($this->targetFields) > 1 ) throw new db_Exception('Not supported');
		
		$criteria = array();
		foreach (array_combine($this->hasSourceFields, $this->sourceFields) as $h=>$s)
			$criteria[$h] = $row->$s;
	
		return $this->target
			->qs()
			->join($this->hasTable->getName(), $this->targetFields, $this->hasTargetFields)
			->where($criteria)					
			->execute()->fetchAll();
				
		return $this->target->selectAll(
			array ( new db_Criterion ( current($this->targetFields), new db_Expr(
				'(select '.implode(',',$this->hasTargetFields). ' from ' . $this->hasTable->getName() . ' where ' . implode(' AND ', $criteria) . ')'
			), 'in' ))
		);
	}
	
	public function set(db_Row $row, $targets, $arg = null) {
		$c = array();
		foreach (array_combine($this->hasSourceFields, $this->sourceFields) as $h=>$s)
			$c = array($h => $row->$s);
		$this->hasTable->delete($c);

		foreach ($targets as $t)
			$this->add($row, $t);
	}
	
	public function add(db_Row $row, $target, $arg = null) {
		
		$has = db_Row::Get($this->hasTable);
		foreach (array_combine($this->hasTargetFields, $this->targetFields) as $h=>$t)
			$has->$h = $target->$t;
		foreach (array_combine($this->hasSourceFields, $this->sourceFields) as $h=>$s)
			$has->$h = $row->$s;
		
		return $has->save();
	}

	public function update(db_Row $row, $target, $arg = null) {
		foreach (array_combine($this->sourceFields, $this->targetFields) as $s=>$t)
			$row->$t = $source->$s;
		return $row->save();
	}

	public function remove(db_Row $row, $target) {
		//$this->hasTable->delete(array())
		throw new Exception('Not implemented');
	}
}

class db_Relation_Attribute extends db_Relation {
	
	private $valueTable;

	protected function init() {
		$this->sourceFields = $this->source->getPrimaryKeys();
		$this->targetFields = $this->target->getPrimaryKeys();
		$this->valueTable = db_Table::Get($this->source->getName().'_attribute_value');
		
		$this->valueSourceFields = $this->sourceFields;
		array_walk($this->valueSourceFields, array($this,'targetize'), $this->source->getName());
		$this->valueTargetFields = $this->targetFields;
		array_walk($this->valueTargetFields, array($this,'targetize'), $this->target->getName());
		$this->valueTargetFields = array ('id_attribute');
	}
	
	public function setValueTable($table) {
		$this->valueTable = db_Table::Get($this->source->getName().'_attribute_value');
	}
	
	public function get(db_Row $row, $arg = null) {
		
		$criteria = array_intersect_key( $row->toArray(), array_flip($this->sourceFields) );
		$criteria = array_combine( $this->valueSourceFields, $criteria );

		//if (!$allAttributes) $criterias[] = new db_Criterion('value', null, '<>');
		return $this->target
					->qs()
					->join($this->valueTable->getName(), $this->targetFields, $this->valueTargetFields)
					->where($criteria)					
					->execute()->fetchAll();

	}
	
	public function set(db_Row $row,  $target, $arg = null) {}
	public function add(db_Row $row,  $target, $arg = null) {}
	public function remove(db_Row $row, $target) {}	
}

/**
 * @brief Transaction wrapper
 */
class db_Transaction {

	private $db;
	private $transaction = false;

	/**
	 * Begin a new transaction
	 * @param db_Driver|db_Table $db 
	 */
	function __construct($db) {
		if ($db instanceof db_Table)
			$db = $db->getDriver();
		if (!($db instanceof db_Driver))
			throw new Exception('db_Table or db_Driver expected');
		$this->db = $db;
		if (!$this->db->inTransaction())
			$this->transaction = $this->db->beginTransaction();
	}

	function __destruct() {
		if ($this->transaction) {
		  $this->db->rollback();
		}
	}

	function commit() {
		if ($this->transaction) {
			$this->transaction = false;
			$this->db->commit();
		}
	}
}

/*
class db_Statement implements IteratorAggregate {

	protected $PDOS;
	protected $PDOp;
	public function __construct(PDO $PDOp, PDOStatement $PDOS) {
		$this->PDOp = $PDOp;
		$this->PDOS = $PDOS;
	}
	
	public function __call($func, $args) {
		return call_user_func_array(array(&$this->PDOS, $func), $args);
	}	
	
	public function __get($property) {
		return $this->PDOS->$property;
	}
	
	public function getIterator() {
		return $this->PDOS;
	}	
}

*/

/**
 * @ignore
 */
class db_Statement extends PDOStatement {

	/**
	 *
	 * @var db_Driver
	 */
	protected $driver;
	
	protected $columnTypes;
	
	public function columnConvert($k, $v)
	{
		if (is_resource($v) && get_resource_type($v) == 'stream')
			return stream_get_contents($v);
		
		if (substr($k,0,5) == 'date_') // SIGH
			return $this->driver->getAdapter()->parseDate($v);		
		
		return $v;
	}
	
	protected function __construct(db_Driver $driver) {
		$this->driver = $driver;
	}
	
	public function getColumsTypes()
	{
		if (!$this->columnTypes) {
		}
		
		return $this->columnTypes;
	}
	
	public function fetch($fetch_style = null, $cursor_orientation = null, $cursor_offset = null) {
		$types = $this->getColumsTypes();
		$row = parent::fetch($fetch_style, $cursor_orientation, $cursor_offset);
		if (!$row) return $row;
		
		//foreach ($row as $k=>$v)
//			$row->$k = $this->columnConvert($k, $v);

		return $row;
	}
}

/**
 * Represents a database connection.
 *
 * Example
 * 
 *     $db = db_Driver::Create('MyDb', array(
 *        'dsn'=>'dsn',
 *        'username'=>'username',
 *        'password'=>'password'
 *     ));
 *     $db->execute(...);
 */
class db_Driver {

	private $DESCRIBE_NAME = 'db_describe';
	
	private $describeCache = array();

	/**
	 * The corresponding PDO Connection
	 * @var PDO $conn
	 */
	private $conn;

	/**
	 * Adapter depending on the database (mysql, oracle, ...)
	 * @var db_Adapter $adapter
	 */
	private $adapter;
	
	/**
	 * Parameters for the database connection
	 * @var array $params
	 */
	private $params;
	
	/**
	 * True if a transaction was initiated
	 * @var bool $transaction
	 */
	private $transaction;

	protected $cache;
	protected $log;
	protected $tableLocation;
	protected $rowLocation;
	
	static private $objects = array();

	/**
	 * Create a new database connection
	 * @param string $name Name to identify the connection
	 * @param array $params
	 * @return db_Driver
	 */
	static public function Create($name, $params)
	{
		return self::$objects[$name] = new self($params);
	}
	
	/**
	 * Get a database connection
	 * @param string $name Name of the connection used with Create()
	 * @return db_Driver
	 */
	static public function Get($name)
	{
		return @self::$objects[$name];
	}

	public function __construct($params)
	{
		$this->params = $params;
		$this->log = db_Logger::getInstance();
		$this->cache = db_Cache::getInstance();
		$this->tableLocation = dirname(__FILE__).DIRECTORY_SEPARATOR.'Table'.DIRECTORY_SEPARATOR;
		$this->rowLocation = dirname(__FILE__).DIRECTORY_SEPARATOR.'Row'.DIRECTORY_SEPARATOR;
		$this->DESCRIBE_NAME = $this->params['dbname'].'_describe';
	}

	public function setClassTableLocation($path) {
		if ($path[strlen($path)-1] != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;
		$this->tableLocation = $path;
		return $this;
	}

	public function setClassRowLocation($path) {
		if ($path[strlen($path)-1] != DIRECTORY_SEPARATOR)
			$path .= DIRECTORY_SEPARATOR;		
		$this->rowLocation = $path;
		return $this;
	}

	public function getClassTableLocation() {
		return $this->tableLocation;
	}

	public function getClassRowLocation() {
		return $this->rowLocation;
	}

	public function getDatabaseName() {
		return $this->params['dbname'];
	}

	/*
	public function setCache($cache) {
		$this->cache = $cache;
	}

	public function setLogger($log) {
		$this->log = $log;
	}*/

	/**
	 * Return the PDO object for this connection
	 * @return PDO
	 */
	public function connect()
	{
		if ($this->conn != null)
			return $this->conn;

		$this->conn = new \PDO(
			$this->params['dsn'],
			$this->params['username'],
			$this->params['password'],
			$this->params['options'] );
		
		$this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->conn->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
		$this->conn->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
		$this->conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
		$this->conn->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
		
		//$this->conn->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('db_Statement', array($this)));
		//$this->conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
		//

		return $this->conn;
	}

	public function disconnect()
	{
		unset($this->conn);
		$this->conn = null;
	}

	function tableExists($name)
	{
		$result = $this->connect()->query("SHOW TABLES LIKE '$name'");
		if (!$result) return false;
		$exist = $result->fetch() ? true : false;
		$result->closeCursor();
		return $exist;
	}
	
	/**
	 *	Get query object
	 * @return db_Query
	 */
	public function qs() {
		return new db_Query($this);
	}
	
	/**
	 * Return the instance of a db_Table object
	 * @string type $table
	 * @return db_Table
	 */
	public function t($table) {
		return db_Table::Get($table, $this);
	}
	
	/**
	 * Return a row from primary key or a new row
	 * @string type $table
	 * @return db_Row
	 */	
	public function r($table, $id = null) {
		return db_Row::Get($this->t($table), $id);
	}

	/**
	 * Get a new row for a table
	 * @param string $table Name of the table 
	 * @return db_Row
	 */
	public function row($table, $datas = null) {
		return db_Table::Get($table, $this)->newRow($datas);
	}

	/**
	 * Create a db expression
	 * @param string $val
	 * @return db_Expr
	 */
	public function expr($val) {
		return new db_Expr($val);
	}

	public function startTransaction() {
		return new \db_Transaction($this);
	}


	/**
	 * Initiates a transaction
	 * @link http://php.net/manual/en/pdo.begintransaction.php
	 * @return bool Returns true on success or false on failure.
	 */	
	public function beginTransaction() {
		$this->transaction = $this->connect()->beginTransaction();
		return $this->transaction;
	}

	/**
	 * Commits a transaction
	 * @link http://php.net/manual/en/pdo.commit.php
	 * @return bool Returns true on success or false on failure.
	 */	
	public function commit() {
		$this->connect()->commit();
		$this->transaction = false;
	}

	/**
	 * Rolls back a transaction
	 * @link http://php.net/manual/en/pdo.rollback.php
	 * @return bool Returns true on success or false on failure.
	 */	
	public function rollback() {
		$this->connect()->rollback();
		$this->transaction = false;
	}

	/**
	 * Check if a transaction was initiated
	 * @return bool Returns true if a transaction was initiated.
	 */		
	public function inTransaction() {
		return $this->transaction;
	}

	/**
	 *
	 * @return PDO
	 */
	public function getConnection()	{
		return $this->connect();
	}

	/**
	 * Get the db_Adapter object corresponding to this connection
	 * @return db_Adapter
	 */
	public function getAdapter()
	{
		if ($this->adapter == null) {
			$this->adapter = db_Adapter::Get($this->getConnection());
		}
		return $this->adapter;
	}
	
	/**
	 * Set the db_Adapter object corresponding to this connection 
	 * when not using the default one
	 * param db_Adapter $adapter
	 */	
	public function setAdapter(db_Adapter $adapter)
	{
		$this->adapter = $adpater;
	}
	
	/**
	 * Execute an SQL statement and return the number of affected rows
	 * @param string $sql The SQL statement to prepare and execute.
	 * @return int 
	 */	
	protected function exec($sql)
	{
		return $this->connect()->exec($sql);
	}

	/**
	 * Return the ID of the last inserted row or sequence value
	 * @param string [optional] Name of the sequence object from which the ID should be returned.
	 * @return string 
	 */
	public function lastInsertId($name = null)
	{
		return $this->getConnection()->lastInsertId($name);
	}

	/**
	 * Execute an SQL statement and return a result set as a PDOStatement object 
	 * @param string $sql The SQL statement to prepare and execute.
	 * @return PDOStatement
	 */
	protected function query($sql)
	{
		return $this->connect()->query($sql);
	}

	/**
	 * Prepare a statement for execution and return a statement object
	 * @param string $sql The SQL statement to prepare.
	 * @return PDOStatement
	 */
	public function prepare($sql, $options = array())
	{
		return $this->connect()->prepare($sql, $options);
	}

	/**
	 * Prepare, execute an SQL statement and return a result set as a PDOStatement object 
	 * @param string $query The SQL statement to prepare and execute.
	 * @param array $params [optional] <p>
	 * An array of values with as many elements as there are bound
	 * parameters in the SQL statement being executed.
	 * All values are treated as PDO::PARAM_STR.
	 * </p>
	 * @return PDOStatement
	 */
	public function execute($query, $params = array())
	{
		$this->log->startQuery($query, $params);

		try
		{
			if (empty($params))
				$stmt = $this->query($query);
			else
			{
				$stmt = $this->prepare($query);
				$stmt->execute($params);
			}
		}
		catch (Exception $e)
		{
			$this->log->stopQuery(false, $e->getMessage(), $e->getCode());
			throw $e;
		}

		$this->log->stopQuery(true);
		return $stmt;
	}

	/**
	 * Execute an SQL statement and return the number of affected rows
	 * @param string $query The SQL statement to prepare and execute.
	 * @param array $params [optional] <p>
	 * An array of values with as many elements as there are bound
	 * parameters in the SQL statement being executed.
	 * All values are treated as PDO::PARAM_STR.
	 * </p>* 
	 * @return int The number of affected rows that were modified
	 * or deleted by the SQL statement you issued.
	 */		
	public function executeUpdate($query, $params = array())
	{
		$stmt = $this->execute($query, $params);
		return $stmt->rowCount();
	}
	
	/**
	 * Prepare, execute an SQL statement, and return an array containing all of the result set rows as objects
	 * @param string $sql The SQL statement to prepare and execute.
	 * @param array $params [optional] <p>
	 * An array of values with as many elements as there are bound
	 * parameters in the SQL statement being executed.
	 * All values are treated as PDO::PARAM_STR.
	 * </p>
	 * @return PDOStatement
	 */	
    public function fetchAll($sql, array $params = array())
    {
        return $this->execute($sql, $params)->fetchAll(PDO::FETCH_OBJ);
    }

    public function fetchOneColumn($sql, array $params = array())
    {
        return $this->execute($sql, $params)->fetchAll(PDO::FETCH_COLUMN, 0);
    }

	public function fetch($sql, array $params = array())
	{
		$stmt = $this->execute($sql, $params);
		$res = $stmt->fetch(PDO::FETCH_OBJ);
		//$stmt->closeCursor();
		return $res;
	}

	/**
	 * Create and execute an SQL statement and return a result set as a PDOStatement object 
	 * @param string $table 
	 * @param mixed $criteria An array of the form field => value or a db_Criterias object
	 * @param array $join 
	 * @param array $order Array of order object ( { 'field', 'direction' } )
	 * @param object $pagination Pagination object ( { 'offset', 'limit' } )
	 * @return type 
	 */
	public function select($table, $criteria = array(), $join = array(), $order = array(), $pagination = null)
    {
		$q = $this->qs()->from($table)
			->where($criteria);

		$q->orderBy($order);

		if ($pagination)
			$q->limit($pagination->offset, $pagination->limit);

		if (strpos($table, ' ')!==false) {
			$table = explode(' ', $table);
			$table = $table[1];
		}
		
		foreach ($join as $key => $j)
			$q->join( (@$j['schema']?$j['schema'].'.':'').$j['table'], strpos($key, '.') === false ? $table.'.'.$key : $key, $j['column'], @$j['alias']);
		return $q->execute();
    }

	/**
	 * Return an array containing all the rows matching the criterias
	 * @param string $table Name of the table
	 * @param mixed $criteria [optionnal] An array of the form field => value or a db_Criterias object
	 * @param array $join [optionnal] 
	 * @param array $order [optionnal] Array of order object ( { 'field', 'direction' } )
	 * @param object $pagination [optionnal] Pagination object ( { 'offset', 'limit' } ) 
	 * @return type 
	 */	
	public function selectAll($table, $criteria = array(), $join = array(), $order = array(), $pagination = null)
    {
        return $this->select($table, $criteria, $join, $order, $pagination)->fetchAll(PDO::FETCH_OBJ);
    }
	
	/**
	 * Find the first row matching the criterias
	 * @param <type> $table Name of the table
	 * @param <type> $criteria [optionnal] An array of the form field => value or a db_Criterias object
	 * @param <type> $join [optionnal] 
	 * @param <type> $order [optionnal] Array of order object ( { 'field', 'direction' } )
	 * @return <type>
	 */
	public function selectOne($table, $criteria = array(), $join = array(), $order = array())
    {
        $stmt = $this->select($table, $criteria, $join, $order);
		$result = $this->fetchAll(PDO::FETCH_OBJ);
		$stmt->close();
		return $result;
    }

	/**
	 * @deprecated
	 * @param <type> $table
	 * @param array $fields
	 * @param array $keywords
	 * @param <type> $limit
	 * @param <type> $offset
	 * @param <type> $order
	 * @return <type>
	 */
	public function find($table, array $fields, array $keywords, $limit = null, $offset = null, $order = array())
	{
		$bind = array();
		$sqlCond = $this->findToSqlField($fields, $keywords, $bind);
		
		$sql =  'SELECT count(*)' . ' FROM ' . $this->getAdapter()->quoteName($table) . ' WHERE '.$sqlCond;
		$count = $this->execute($sql, $bind)->fetchAll(PDO::FETCH_NUM);
		$count = $count[0];

		$sql =  'SELECT *' . ' FROM ' . $this->getAdapter()->quoteName($table) . ' WHERE '.$sqlCond;
		if (!empty($order))	$sql .= ' ORDER BY ' .  $this->orderToSql($order);
		$sql = $this->getAdapter()->limitQuery($sql, $limit, $offset);
	    return array($count, $this->execute($sql, $bind)->fetchAll(PDO::FETCH_ASSOC));
	}

	/**
	 * Insert a row
	 * @param type $table Name of the table
	 * @param array $values Associative array representing the row:  
	 * keys are column name 
	 * @return int The number of rows that were inserted (always 1)
	 */
	public function insert($table, array $values)
	{
		if (empty($values))
			throw new db_Exception('There is no value to insert');
		
		$bind = array();
		foreach ($values as $key=>$val) {
			$bind[$this->getAdapter()->quoteName($key)] = $this->value($val);
		}

		$sql = "INSERT INTO "
		. $this->getAdapter()->quoteName($table)
		. ' (' . implode(', ', array_keys($bind)) . ') '
		. 'VALUES (' . implode(', ', array_values($bind)) . ')';
		
		return $this->executeUpdate($sql, array_values($values));
	}

	/**
	 * Update rows
	 * @param string $table Name of the table
	 * @param array $values Associative array representing the row:
	 * keys are column name 
	 * @param type $criteria An array of the form field => value or a db_Criterias object
	 * @return int The number of affected rows that were modified
	 */
	public function update($table, array $values, $criteria)
	{
		if (empty($criteria) or ($criteria instanceof db_Criteria and $criteria->isEmpty()))
			throw new db_Exception('update() without criteria is not allowed');

		if (empty($values))
			return 0;

		$bind = array();
		$sqlset = array();
		foreach($values as $k=>$v)
			$sqlset[] = ''.$this->getAdapter()->quoteName($k) . ' = ?';

		$sql = 'UPDATE '.$this->getAdapter()->quoteName($table)
				.' SET ' . implode(', ',$sqlset)
				.' WHERE ' . $this->criteriaToSql($criteria, $bind);

		return $this->executeUpdate($sql, array_merge(array_values($values), $bind));
	}

	/**
	 * Delete rows
	 * @param type $table Name of the table
	 * @param type $criteria An array of the form field => value or a db_Criterias object
	 * @return int The number of deleted rows
	 */
	public function delete($table, $criteria)
	{
		if (empty($criteria) or ($criteria instanceof db_Criteria and $criteria->isEmpty()))
			throw new db_Exception('delete() without criteria is not allowed');

		$bind = array();
		$sql = 'DELETE FROM '.$this->getAdapter()->quoteName($table). ' '
				. ' WHERE ' .  $this->criteriaToSql($criteria, $bind);

		return $this->executeUpdate($sql, $bind);
	}
	
	/**
	 * Count rows
	 * @param type $table Name of the table
	 * @param type $criteria An array of the form field => value or a db_Criterias object
	 * @return int The number of row matching the criterias 
	 */
	public function count($table, $criteria, $join = [])
	{
		$q = $this->qs()->from($table)
			->select(new db_Expr('count(*) as count'))
			->where($criteria);

		foreach ($join as $key => $j)
			$q->join((@$j['schema']?$j['schema'].'.':'').$j['table'], strpos($key, '.') === false ? $table.'.'.$key : $key, $j['column'], @$j['alias']);

		return $q->execute()
			->fetch(PDO::FETCH_OBJ)
			->count;
	}

	/**
	 * @ignore
	 * @param type $table
	 * @return type 
	 */
	public function describe($table)
	{
		if ($this->cache)
			if (empty($this->describeCache))
				if ($r = $this->cache->read($this->DESCRIBE_NAME))
					$this->describeCache = $r;

		if (isset($this->describeCache[$table]))
			return $this->describeCache[$table];

		$describe = $this->getAdapter()->describe($table, $this->params);
		$this->describeCache[$table] = $describe;

		if ($this->cache)
			$this->cache->write($this->DESCRIBE_NAME, $this->describeCache);

		return $describe;
	}
	
	/**
	 * Clear describe cache
	 */
	public function clearCache()
	{
		if ($this->cache)
			$this->cache->delete($this->DESCRIBE_NAME);
	}
/*
	public function findToSqlField(array $fields, array $keywords, array &$bind, $start = false)
	{
		$sql = ' 1=0 ';
		foreach ($fields as $f)
		{
			$sql .= ' OR( 1=1 ';
			foreach ($keywords as $k) {
				$bind[] =  ($start?'':'%').$k.'%';
				$sql .= ' AND ' . $f . ' LIKE ?';
			}
			$sql .= ' ) ';
		}

		return $sql;
	}

	public function findToSql(array $fields, array $keywords, array &$bind, $start = false)
	{
		$sql = ' 1=1 ';
		foreach ($keywords as $k)
		{
			$sql .= ' AND ( 1=0 ';
			foreach ($fields as $f) {
				$bind[] =  ($start?'':'%').$k.'%';
				$sql .= ' OR ' . $f . ' LIKE ?';
			}
			$sql .= ' ) ';
		}
		return $sql;
	}

	public function orderToSql(array $order)
	{
		return implode(',', $order);
	}
 */
	/**
	 *	@ignore
	 * @param <type> $criteria
	 * @param array $bind
	 * @return <type>
	 */
	public function criteriaToSql($criteria, array &$bind)
	{
		if (!$criteria instanceof db_Criteria)
			$criteria = new db_Criteria($criteria);

		$bind = $criteria->getParams();
		return $criteria->toSql($this->getAdapter());
	}
	/**
	 *	@ignore
	 * @param <type> $stmt
	 */
	protected function throwError($stmt)
	{
		$err = $stmt->errorInfo();
		throw new db_Exception($err[2]);
	}

	/**
	 * @ignore
	 * @param db_Expr $val
	 * @param <type> $key
	 * @return <type>
	 */
	protected function value($val, $key = null)
	{
		return $val instanceof db_Expr ? $val : '?';
	}
}