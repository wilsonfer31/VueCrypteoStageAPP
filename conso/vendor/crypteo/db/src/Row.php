<?php


/**
 * Base class to represent a row in a database.
 *
 */
class db_Row implements IteratorAggregate,JsonSerializable  {

	/**
	 * @var array $_fields
	 */
	protected $_fields = array();

	/**
	 * @var db_Table $_table
	 */
	protected $_table = null;

	/**
	 * @var array $_editedFields
	 */
	protected $_editedFields = array();

	/**
	 * @var array $_customFields
	 */
	protected $_customFields = array();

	/**
	 * @var array $_oldValues
	 */
	protected $_oldValues = array();

	private $_init = false;
	protected $_new = true;
	private $_autoReload = true;
	private $_loaded = false;
	private $_useDateTime = false;
	private $_allowCustomField = true;

	final public function __call($name, $args)
	{
		if (substr($name,0,3) == 'get') {
			$name = substr($name,3);
			return $this->getByRel($name, isset($args[0]) ? $args[0] : array());
		}
		
		if (substr($name,0,3) == 'set') {
			$name = substr($name,3);
			return $this->setByRel($name, $args[0]);
		}		
		
		if (substr($name,0,3) == 'add') {
			$name = substr($name,3);
			return $this->addByRel($name, $args[0]);
		}		
		
		if (substr($name,0,3) == 'remove') {
			$name = substr($name,6);
			return $this->removeByRel($name, $args[0]);
		}							
		
		throw new Exception('Unknow method '. $name);
		return call_user_func_array(array($this, $name), $args);
	}

	final public function __sleep()
	{
		return array ('_fields', '_editedFields', '_new');
	}
	
	final private function __construct(db_Table $table, $datas = null)
	{
		$this->_table = $table;
		$this->_new = true;
		$this->_loaded = true;
		foreach ($table->getFields() as $k=>$v)
			$this->_fields[$k] = isset($datas[$k]) ? $datas[$k] : null;
		foreach ($table->getForeignKeys() as $v)
			$this->_fields[$v['alias']] = isset($datas[$v['table']]) ? $datas[$v['table']] : null;

		if ($datas === false) $this->_new = false;
		
		$this->init();
		$this->_init = true;
	}

	public function __isset($name)
	{
		if(property_exists($this,$name))
			return true;
		if(array_key_exists($name,$this->_fields))
			return true;
		if(array_key_exists($name,$this->_customFields))
			return true;
		return false;
	}

	public function __set($name, $value)
	{
		if(property_exists($this, $name)) {
			$this->$name = $value;
			return;
		}

		$this->setValue($name, $value);
	}

	public function __get($name)
	{
		if(property_exists($this,$name))
			return $this->$name;

		return $this->getValue($name);
	}

	/**
	 * Set the value of a property of this row
	 * @param type $name Name of the field
	 * @param self $value 
	 */
	public function setValue($name, $value)
	{
        if (!array_key_exists($name, $this->_fields)) {
			if (!$this->_allowCustomField)
				throw new db_Exception('Field "'.$name.'" does not exist in table "'.$this->_table->getName()."\"\r\n");
			$this->_customFields[$name] = $value;
			return;
		}
		
		if ($value instanceof DateTime)
			$value = $this->getDriver()->getAdapter()->formatDate($value);
			
		$fields = $this->getTable()->getFields();
        if (!isset($fields[$name]) || !$fields[$name]['generated']) {
            if (!($value instanceof self) && ($value === null || $value !== $this->_fields[$name])) {
				$this->_editedFields[$name] = $value;
				$this->_oldValues[$name] = $this->_fields[$name];
            }
        }

		$this->_fields[$name] = $value;
	}
	
	/**
	 * Return the value of a property of this row
	 * @return mixed
	 */
	public function getValue($name)
	{
		if(!array_key_exists($name, $this->_fields)) {
			if(!array_key_exists($name, $this->_customFields)) {
				return null;
			}
			return $this->_customFields[$name];
		}			

		$this->load();
		
		// Temporary support for date
		if ($this->_useDateTime) {
			$fields = $this->getTable()->getFields();
			if ($fields[$name]['type'] == 'date')
				return $this->getDriver()->getAdapter()->parseDate($this->_fields[$name]);
		}
		
		return $this->_fields[$name];
	}

	/**
	 * Check if the row is a new one
	 * @return bool True if it's a new row
	 */
	public function isNew()
	{
		return $this->_new;
	}

	protected function load()
	{
		if ($this->_loaded) return;
		$this->_loaded = true;
		$ed = $this->_editedFields;
		$row = $this->getTable()->select($this->getPrimaryKey(), $this );

		if (!$row) throw new db_Exception("No row found in '".$this->getTable()->getName()."' for primary key '".implode(',',$this->getPrimaryKey())."'");
		$this->setFromAssoc($ed);		
	}

	/**
	 * Get the corresponding table
	 * @return db_Table
	 */
	public function getTable()
	{
		return $this->_table;
 	}
	
	/**
	 * Get the corresponding connection
	 * @return db_Driver
	 */
	public function getDriver()
	{
		return $this->_table->getDriver();
	}

	/**
	 * Set the values of the row from an array
	 * @param array $assoc Associative array representing the row:  
	 * keys are column name 
	 * @param bool $preservePk Preserve the actual value of the primary keys
	 * @return db_Row 
	 */
	public function setFromAssoc($assoc, $preservePk = true)
	{
		$this->load();
		$assoc = (array)$assoc;
		foreach ($assoc as $k=>$v)
			if(array_key_exists(strtolower($k), $this->_fields) 
			   && (/*$this->isNew () ||*/ !$preservePk || !array_key_exists(strtolower($k),$this->_table->getPrimaryKeys())))
				$this->setValue($k, $v);
		return $this;
	}

	/**
	 * Return an array representing the row
	 * @return array
	 */
	public function toArray()
	{
		$this->load();
		foreach ($this->_fields as $k=>$v)
			$res[$k] = $this->getValue($k);
		$res = array_merge($this->_customFields, $res);
		//$res = array_merge($this->_customFields, $this->_fields);
				/*
		// Temporary support for date
		if ($this->_useDateTime) {
			$fields = $this->getTable()->getFields();
			foreach ($fields as $k=>$f) {
				if ($f['type'] == 'date')
					$res[$k] = $this->getDriver()->getAdapter()->parseDate($res[$k]);
			}
		}
		*/			
		// Arrayize the foreign fields
		foreach ($this->getTable()->getForeignKeys() as $v)
			if ($res[$v['alias']] && $res[$v['alias']] instanceof db_Row)
				$res[$v['alias']] = $res[$v['alias']]->toArray();

		return $res;
	}

	/**
	 * Return the primary keys
	 * @return type Array of the form field => value
	 */
	public function getPrimaryKey()
	{
		$primary = $this->_table->getPrimaryKeys();
		return array_intersect_key($this->_fields, $primary);
 	}

	protected function __getData($field = null)
	{
		if ($field == null)
			return array_intersect_key($this->_editedFields, $this->_table->getFields());

		if (!is_array($field))
			return array_key_exists($field, $this->_table->getFields()) ? 
						array ($field => $this->_fields[$field]) :
						array();

		return array_intersect_key($this->_fields, array_flip($field), $this->_table->getFields());
	}
	
	protected function _getData($field = null)
	{
		$res = $this->__getData($field);
		
		// Temporary support for date
		foreach ($res as &$v)
			if ($v instanceof DateTime)
				$v = $this->getDriver()->getAdapter()->formatDate($v);
			
		return $res;
	}	

	public function clone() 
	{
		$row = $this->getTable()->newRow();	
		$row->setFromAssoc($this->_fields);
		return $row;
	}

	/**
	 * Save the row inside the database
	 * @return db_Row 
	 */
	public function save()
	{
		$tx = new \db_Transaction($this->getDriver());
		$this->getTable()->notify('beforeSave', $this);
		$this->_new ? $this->insert() : $this->update();
		$this->_new = false;
		$this->getTable()->notify('afterSave', $this, true);
		$tx->commit();
		return $this;
	}

	/**
	 * @internal
	 */
	protected function insert()
	{
		//if (isset($this->date_creation) && !$this->date_creation) $this->date_creation = date('Y-m-d H:i:s');
	    $pkey = $this->_table->insert($this->_getData());
		if (isset($this->id)) $this->id = $pkey; // XXX
	    if ($this->_autoReload) $this->reload();
		else $this->reset();
		$this->_new = false;
	}

	/**
	 * @internal
	 */
	protected function update()
	{
		//if (isset($this->date_update)) $this->date_update = date('Y-m-d H:i:s');
	    $this->_table->update($this->_getData(), $this->getPrimaryKey());
	    if ($this->_autoReload) $this->reload();
		else $this->reset();
	}
	
	/**
	 * Delete the row from the database
	 */	
	public function delete()
	{
		$tx = new \db_Transaction($this->getDriver());
		$this->getTable()->notify('beforeDelete', $this);		 
		$result = $this->_table->delete($this->getPrimaryKey());
		$this->getTable()->notify('afterDelete', $this, true);
		$tx->commit();
		$this->_new = true;
		$this->_loaded = false;
		return $result;
		// Reset to null
	}
	
	public function copy()
	{
		return $this->getTable()->newRow()->setFromAssoc($this->toArray())->save();
	}

	/**
	 * Reload the row from the database
	 */
	public function reload()
	{
	    $row = $this->_table->select( $this->getPrimaryKey(), $this );
	    if (!$row) throw new db_Exception('Cannot reload row from '.$this->_table->getName());
	    $this->reset();
	}

	/**
	 * @internal
	 * Reset the edited fields (but doesn't reload the original value)
	 */
	public function reset() {
		$this->_editedFields = array();
	}

	/**
	 * Dump in html format
	 * @param type $return If true return the html instead of sending it
	 * @return string 
	 */
	public function dump($return = false)
	{
		$str = '<span style="color:green;">Table : '.$this->tableName."</span>\r\n";
		foreach($this->_fields as $k=>$v) {
			$str .= sprintf(" + <span style='color:blue'>%s</span>: <span style='color:red'>%s</span>\r\n",$k,$v instanceof db_Row ? $v->dump(true) : $v);
		}		

		if($return)	return $str;
		echo '<pre>'.$str.'</pre>';
	}

	/**
	 * Return the number of columns 
	 * @return int 
	 */
	public function countColumns()
	{
		return count($this->_table->getFields());
	}

	/**
	 * @internal
	 * @param array $values
	 */
	public function setFromArray(array $values)
	{
		// This function is too slow to be practical
		$fields = $this->_table->getFields();
	//	if (count($values) != count($fields))
	//		throw new db_Exception('setFromArray called with a wrong number of values ['.count($values).'] for table \''.$this->getTable()->getName().'\', expected ['.count($this->_fields).'].');

		$i = 0;
		foreach (array_keys($fields) as $k)
			$this->{$k} = @$values[$i++];
		$this->reset();
		$this->_loaded = true;
		$this->_new = false;
	}

	public function setCustomValue($name, $value) 
	{
		$table = $this->getTable()->getTableName();
		$db = $this->getDriver();
		$cf = $db->t('custom_field')->select([
			'type' => $table,
			'name' => $name
		]);
		if (!$cf) throw new \db_Exception('Field '.$name.' not found for table '.$table);
		
		$cv = $db->t('custom_value')->select([
			'custom_field_id' => $cf->id, 
			'target_type' => $table, 
			'target_id' => $this->id
		]);
		
		if (!$cv) {
			$cv = $db->r('custom_value');
			$cv->custom_field_id = $cf->id;
			$cv->target_type = $table;
			$cv->target_id = $this->id;
		}
		
		$cv->value = $value;
		return $cv->save();
	}
	
	public function removeCustomValue($name)
	{
		return $this->getDriver()->execute('
			delete cv from custom_value cv
			left join custom_field cf on cf.id = cv.custom_field_id
			where cf.name = ? and cv.target_type = ? and cv.target_id = ?
		', [$name, $this->getTable()->getTableName(), $this->id]);
	}

	/**
	 * Add a row inside the database depending on a relation
	 * Example: db_Row $author->addByRel('Books', $mybook);
	 * @param type $relName Name of the relation
	 * @param type $datas Array with the values of the row
	 * @return db_Row 
	 */
	public function addByRel($relName, $datas) {
		if ($this->isNew()) throw new db_Exception('Relation used with a new row');
		$rel = $this->getTable()->getRelation($relName);
		return $rel->add($this, $datas);
	}
	
	public function removeByRel($relName, $datas) {
		$rel = $this->getTable()->getRelation($relName);
		return $rel->remove($this, $datas);
	}	
	
	/**
	 * Get a collection of rows related to this one
	 * Example: db_Row $author->getByRel('Books');
	 * @param string $relName Name of the relation
	 * @return array Array of db_Row
	 */
	public function getByRel($relName, $order = array()) {
		$rel = $this->getTable()->getRelation($relName);
		return $rel->get($this, $order);
	}
	
	/**
	 * Note: save() must be called manually after this operation
	 * @param string $relName
	 * @param db_Row $target
	 * @return db_Row 
	 */
	public function setByRel($relName, $target) {
		$rel = $this->getTable()->getRelation($relName);
		return $rel->set($this, $target);		
	}

/*	public function updateByRel($relName, $datas) {
		if ($this->isNew()) throw new db_Exception('Relation used with a new row');
		$rel = $this->getTable()->getRelation($relName);
		return $rel->update($this, $datas);
	}*/
	/**
	 *	find shorthand
	 * @param <type> $table
	 * @param <type> $tableHas
	 * @param <type> $on
	 * @param <type> $hasKey
	 * @return db_Query
	 * @deprecated
	 */
	public function findMany($table, $tableHas, $on, $hasKey) {
		
		return $this->getDriver()->q()
			->from($table)
			->select($table.'.*')
			->join($tableHas, $on)
			->where(array($tableHas.'.'.$hasKey => $this->id));

/*
		foreach (db_Table::Get($tableHas)->getForeignKeys() as $k=>$v)
		{
			if ($v['table'] == $table)
				$d = $v['column'];
			if ($v['table'] == $this->getTable()->getTableName())
				$c = $v['column'];
		}*/

		$otable = db_Table::Get($table);
		echo $otable->q()
				->join($tableHas, array($c), $otable->getPrimaryKeys())
				->where(array($d, $this->id))
				->select($table.'.*')
				->toSql();
	}
	
	/** Override **/

	protected function init()
	{
	}

	/** Static **/

	/*
	static private function _Get($table, $name, $datas)
	{
		$classname = self::LoadClass($table);
		return new $classname($table, $datas);
	}*/

	/**
	 * Load the specific class for this table when it exists and return its name
	 * @param db_Table $table
	 * @return string 
	 * @ignore
	 */
	static public function LoadClass(db_Table $table)
	{
		$classfile = $table->getDriver()->getClassRowLocation().$table->getName().'.php';
		if (!file_exists($classfile))
			$classfile = $table->getDriver()->getClassRowLocation().$table->getNeatTableName().'.php';
		
		if (file_exists($classfile)) {
			require_once($classfile);
			return 'db_Row_'.$table->getNeatTableName();
		}
		
		return 'db_Row';
	}

	/**
	 * Get a row from table $name. If $id is null return a new empty row.
	 * @param string $name table name
	 * @param mixed $id row id
	 * @return db_Row
	 */
	static public function Get($name, $id = null)
	{
		if (!($name instanceof db_Table)) {
			$table = db_Table::Get($name);
			$classname = self::LoadClass($table);
		}
		else {
			$table = $name;
			$classname = self::LoadClass($table);
		}
		
		if ($id && !is_array($id) && !is_object($id)) {
			
			//$table->select(array ('id' => $id));
			//if (!$row) throw new db_Exception("No row found in '$name' for primary key '$id'");
			$row = new $classname($table);
			$row->id = $id;
			$row->reset();
			$row->_new = false;
			$row->_loaded = false;
			
			$row->load();
			return $row;
		}
		
		return new $classname($table, $id);

		if (!($name instanceof db_Table))
		{
			$table = db_Table::Get($name);
			if ($id) {
				$row = $table->select(array ('id' => $id));
				
				if (!$row) throw new db_Exception("No row found in '$name' for primary key '$id'");
				$row->_new = false;
				return $row;

				$row = self::_Get($table, $name, array ('id' => $id));
				$row->_new = false;
				//$row->load();
				
				return $row;
			}
			$datas = null;
		}
		else
		{
			$table = $name;
			$name = $table->getName();
			$datas = $id;
		}		

		return self::_Get($table, $name, $datas);
	}

	/**
	 * @ignore
	 * @param db_Table $table
	 * @param array $values
	 * @return db_Row
	 */
	static public function GetFromArray(db_Table $table, array $values)
	{
		$row = self::Get($table);
		$row->setFromArray($values);
		return $row;
	}

	/** implements **/
	public function getIterator()
	{
		return new ArrayIterator($this->toArray());
	}
	
	public function getAMFClassName()
	{
		return 'ws.types.'.ucfirst($this->getTable()->getNeatTableName());
	}
	
	public function getAMFData() 
	{
		return $this->toArray();
	}	
	
	public function jsonSerialize() 
	{
		return $this->toArray();
	}
}