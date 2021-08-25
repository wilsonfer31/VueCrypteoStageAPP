<?php

/**
 * Base class to represent a table in a database.
 */
class db_Table {

	/**
	 * DBDriver to use by default if no other defined
	 * @var db_Driver $defaultDriver
	 */
	static $defaultDriver = null;

	/**
	 * Specific DBDriver to use for this table. Default is used when null
	 * @var db_Driver $driver
	 */
	private $driver = null;

	/**
	 * Name of the table, default is the class name
	 * @var @string $tableName
	 */
	private $tableName = null;

	/**
	 * Private datas
	 */
	private $primaryKeys = array();
	private $foreignKeys = array();
	protected $fields = array();
	protected $autojoin = true;
	private $autojoinall = true;
	private $observers = array();

	/**
	 * Table Settings
	 */

	/**
	 * Fields to use for the default search
	 * @var array $findFields
	 */
	protected $findFields = array();
	/**
	 * List of database relations
	 * @var array $relations
	 */
	public $relations = array();

	final private function __construct($name, db_Driver $driver)
	{
		$this->driver = $driver;
		$this->tableName = $name;
		if (empty($this->fields))
			$this->fields = $driver->describe($this->tableName);

		if (!$this->fields)	throw new db_Exception('describe() not available for '.$this->tableName.'.');
	}

	public function getPrimaryKeys() {
		return $this->primaryKeys;
	}

	public function getForeignKeys() {
		return $this->foreignKeys;
	}

	public function getName() {
		return $this->tableName;
	}
	
	public function getTableName() {
		return $this->tableName;
	}
	
	public function getNeatTableName() {		
		return implode('', array_map('ucfirst', explode('_',str_replace('.','_',$this->tableName))));
	}	

	public function getFindFields() {
		return $this->findFields;
	}
	/*
	 * @return db_Driver
	 */
	public function getDriver()	{
		return $this->driver;
	}

	public function countColumns() {
		return count($this->fields);
	}

	public function addRelation(db_Relation $rel) {
		$this->relations[$rel->getName()] = $rel;
	}

	/**
	 * @return db_Relation
	 */
	public function getRelation($name) {
		return db_Relation::GetRelation($this->getTableName(), $name);
		if (!isset($this->relations[$name]))

			throw new db_Exception("Relation [$name] does not exist in table [{$this->getName()}]");
		return $this->relations[$name];
	}

	/**
	 *
	 * @return db_Query
	 */
	public function qs() {
		return $this->getDriver()->qs()->from($this->getName());
	}

	protected function processCriteria($criteria)
	{
		if ($criteria instanceof db_Criteria || $criteria instanceof db_Criterion)
			return $criteria;
		$res = array();
		foreach ($criteria as $k=>$v)
		{
			if (!($criteria instanceof db_Criteria) && strpos($k, '.') === false)
				$res[$this->getName().'.'.$k] = $v;
			else
				$res[$k] = $v;
		}
		return $res;
	}

	/*protected function callFk($values, &$row)
	{
		//foreach($this->getForeignKeys() as $col => $v)
		//	$rowObject[$col] = db_Table::get($v['table'])->newRow();

		$row->setFromArray(array_splice($values, 0, $row->countColumns()));
		foreach($this->getForeignKeys() as $col => $v)
		{
			$rowObject = db_Row::get($v['table']);
			$rowObject->setFromArray(array_splice($values, 0, $rowObject->countColumns()));
		}
		return $row;
	}*/


	protected function setRowFromArray(&$row, array $values)
	{
		$row->setFromArray($values); //XXX
	}

	protected function getRowFromArray(array $values)
	{
		return db_Row::GetFromArray($this, $values);
	}

	protected function callFk($values, &$row, $foreignKeys)
	{
		//echo "<pre>", var_export($values,1) ,$this->countColumns(),"</pre>";
		$this->setRowFromArray($row, array_splice($values, 0, $this->countColumns()));		
		foreach($foreignKeys as $col => $v)
		{
			$table = $v['table'];
			if ($v['schema'] != $this->getDriver()->getDatabaseName())
				$table = $v['schema'].'.'.$v['table'];

			$table = db_Table::Get($table, $this->getDriver());
			$rowArray = array_splice($values, 0, $table->countColumns());
			$row->{$v['alias']} = $row->{$col} ? $table->getRowFromArray($rowArray) : null;
		}
		
		return $row;
	}
	/**
	 *	Select all with autojoin
	 * @param array $criteria
	 * @param array $order
	 * @param <type> $pagination
	 * @return <type>
	 */
	public function selectJAll($criteria = array(), $order = array(), $pagination = null)
	{
		$foreignKeys = $this->getForeignKeys();
		$stmt = $this->driver->select($this->getName(), $this->processCriteria($criteria), $foreignKeys, $order, $pagination);

		$res = array();
		while ($row = $this->_select($stmt, $foreignKeys))
			$res[] = $row;
		$stmt->closeCursor();
		return $res;
	}

	/**
	 *
	 * @param <type> $criteria
	 * @param array $order
	 * @param <type> $pagination
	 * @return array<db_Row>
	 */
	public function selectAll($criteria = array(), $order = array(), $pagination = null)
	{
		if ($this->autojoinall) return $this->selectJAll($criteria, $order, $pagination);
		$stmt = $this->driver->select($this->getName(), $this->processCriteria($criteria), array(), $order, $pagination);
		$r = $stmt->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, db_Row::LoadClass($this), array($this, false));
		return $r;
	}

	protected function _select(\PDOStatement $stmt, $foreignKeys = array(), db_Row &$row = null)
	{
		if (!$row) {
		/*	var_dump($this->getName());
			$stmt->setFetchMode (PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, db_Row::GetClass($this->getName()), array ($this));
			//Bug #46139
			$row = $stmt->fetch(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE);
			$stmt->closeCursor();
			return $row;*/
			$row = db_Row::Get($this, false); // Purée
		}

		if ( empty($foreignKeys) ) // OPT
		{
			$stmt->setFetchMode(PDO::FETCH_INTO, $row);
			$row = $stmt->fetch();
			if ($row) $row->reset(); // Why did I do that? Cause also in setFromArray
			return $row;
		}

		$values = $stmt->fetch(PDO::FETCH_NUM);
		//echo "<pre>", var_export($values,1) ,"</pre>";
		if (!$values) return $values;
		$this->callFk($values, $row, $foreignKeys);

		return $row;
	}

	public function count($criteria)
	{
		return $this->driver->count($this->getName(), $criteria, $this->getForeignKeys());
	}

	public function select(array $criteria, db_Row &$row = null)
	{
		$foreignKeys = $this->autojoin ? $this->getForeignKeys() : array();
		//var_dump($foreignKeys);
		$stmt = $this->driver->select($this->getName(), $this->processCriteria($criteria), $foreignKeys);
		return $this->_select($stmt, $foreignKeys, $row);
	}

	public function rawSelect($query, $params, db_Row $row = null)
	{
		$stmt = $this->driver->execute($query, $params);
		return $this->_select($stmt, array(), $row);
	}

	public function insert(array $data)
	{

		$this->driver->insert($this->getName(), $data);
		return $this->driver->lastInsertId();
	}

	public function update(array $data, array $criteria)
	{
		return $this->driver->update($this->getName(), $data, $this->processCriteria($criteria));
	}
	
	public function save(array $data)
	{
		return $this->driver->save($this->getName(), $data);
	}

	public function delete(array $criteria)
	{
		return $this->driver->delete($this->getName(), $this->processCriteria($criteria));
	}

	/**
	 *
	 * @param array $datas
	 * @return db_Row
	 */	
	public function newRow( $datas = null)
	{
		$row = db_Row::Get($this);
		if ($datas) $row->setFromAssoc($datas);
		return $row;
	}
	
	public function getFields()
	{
		return $this->fields;
	}

	public function autocomplete($keyword, $pagination, array $order = array())
	{
		$filters = new db_Filters();
		$filters->addFilter(new db_Filter_Find('keyword', $this->findFields));
		return $this->search($filters, array('keyword' => $keyword), $pagination, $order);

		//TODO
		return $this->find($keyword, $pagination, $order);
	}
	/**
	 *	@ignore
	 * @param <type> $keyword
	 * @param <type> $pagination
	 * @param array $order
	 * @return <type>
	 */
	public function find($keyword, $pagination, array $order = array())
	{
		if (strlen($keyword) < 1) return array(0, array());
		$keyword = trim(str_replace( array ('*','_','~','?','^','%','!'), array('','','','','','',''), $keyword)) ;
		$keywords = explode(' ',$keyword);
		return $this->driver->find($this->getName(), $this->findFields ,$keywords, $pagination->limit, $pagination->offset, !empty($order) ? $order : $this->findFields);
	}

	public function search(db_Filters $filters, $search, $pagination, array $order = array())
	{
		$criterias = $filters->compute($search);

		return array (
			$this->count($criterias),
			$this->selectAll(
				$criterias,
				$order,
				$pagination
			)
		);
	}

	public function truncate()
	{
		return $this->driver->execute('delete from '.$this->getName());
	}

	/**  Override **/

	protected function init()
	{
		/* Init db relation */
		foreach($this->fields as $k=>$v)
		{
			if ($v['primary'])
				$this->primaryKeys[$k] = $k;
			if ($v['fk']) {
				$this->foreignKeys[$k] = $v['fk'];

				$table = $v['fk']['table'];
				if ($v['fk']['schema'] && $v['fk']['schema'] != $this->getDriver()->getDatabaseName())
					$table = $v['fk']['schema'].'.'.$v['fk']['table'];
				
				db_Relation::AddTRelation($v['fk']['alias'], 'ManyToOne',
						$this, $this->driver->t($table),
						$k, $v['fk']['column']);
				db_Relation::AddTRelation($this->getName(), 'OneToMany',
						$this->driver->t($table), $this,
						$v['fk']['column'],$k);				
						
				//$this->foreignKeys[$k]['alias'] = substr($k,0,strlen($k)-3);
			}
			$this->findFields[] = $k;
		}
	}

	/** Static **/

	static private $objects = array();

	/**
	 *
	 * @param string $name
	 * @param db_Driver $driver
	 * @return db_Table
	 *
	 */
	static public function Get($name, db_Driver $driver = null)
	{
		if (!$driver) $driver = self::$defaultDriver;
		if ($driver == null) throw new db_Exception('db_Table: no driver defined');
		/*
		if (!isset(self::$objects[$driver->getName()]))
			self::$objects[$driver->getName()] = array();

		$objects = &self::$objects[$driver->getName()];
		if (isset($objects[$name]))
			return $objects[$name];*/
		
		if (isset(self::$objects[$name]))
			return self::$objects[$name];		
		
		$classfile = $driver->getClassTableLocation().$name.'.php';
		if (file_exists($classfile))
		{
			require_once($classfile);
			$classname = 'db_Table_'.str_replace('.','_',$name);
			self::$objects[$name] = new $classname($name, $driver);
			
		}
		else {
			self::$objects[$name] = new self($name, $driver);
		}
		self::$objects[$name]->init();
		return self::$objects[$name];
	}
	
	public function addObserver(db_Row_IObserver $observer)
	{
		$this->observers[] = $observer;
		
	}

	public function notify($event)
	{
		$args = func_get_args();
		array_shift($args);
		foreach ($this->observers as $observer) {
			call_user_func_array(array($observer, $event), $args);
		}
	}

	public function setAutoJoin($value)
	{
		$ret = $this->autojoin;
		$this->autojoinall = $this->autojoin = $value;
		return $ret;
	}
}