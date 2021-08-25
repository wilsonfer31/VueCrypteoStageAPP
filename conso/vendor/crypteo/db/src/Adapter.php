<?php

class db_Adapter_Odbc extends db_Adapter {

	public function quoteName($name)
	{
		return $this->_quoteName(strtoupper($name), '"');
	}
	
	public function describe($table, $param)
	{
		return false;
	}
}

/**
 * Adapter for sqlite database
 */
class db_Adapter_Sqlite extends db_Adapter {
	 
	public function quoteName($name)
	{
		return $this->_quoteName(strtoupper($name), '"');
	}
	
	public function describe($table, $params)
	{
		$aMapType = array (
			'INTEGER' => 'int',
			'REAL' => 'float',
			'TEXT' => 'string',
			'BLOB' => 'lob'
		);
		
		$stmt = $this->pdo->query("SELECT sql FROM sqlite_master where name = '$table' ");
		$res = $stmt->fetch(PDO::FETCH_NUM);
		$sql = $res[0];
		preg_match('/CREATE TABLE "(.*)" \((.*)\)/', $sql, $matches);
		$fields = explode(',',$matches[2]);
		
		foreach ($fields as $field)
		{
			$afield = preg_split("/[\s]+/",trim($field));

			$columns[$afield[0]] = array (
					'type' => (isset($afield[1]) && isset($aMapType[$afield[1]])) ? $aMapType[$afield[1]] : 'string',
					'primary' => strpos($field, 'PRIMARY') !== false,
					'ai' => strpos($field, 'AUTOINCREMENT') !== false,
					'signed'  => null,
					'fk' => array()
				);
		}
		return $columns;
	}	
}

/**
 * Adapter for Oracle database
 */
class db_Adapter_Oci extends db_Adapter {
	
	public function quoteName($name)
	{
		return $this->_quoteName(strtoupper($name), '"');
	}
	
	public function formatDate(DateTime $value)
	{
		return new db_Expr('to_date('.$value->format('Y-m-d H:m:i').',\'yyyy-mm-dd hh:mm:ss\')');
	}
	
	public function parseDate($date)
	{
		if ($date == null) return null;
		
		$d = strptime($date, '%d-%b-%y %I.%M.%S.000000 %p %z');
		if ($d === false)
			$d = strptime($date, '%d-%b-%y %I.%M.%S.000000 %p');
		if ($d === false)
			$d = strptime($date, '%d-%b-%y');

		if (!$d) return new DateTime($date);
       	$t = mktime($d['tm_hour'],$d['tm_min'],$d['tm_sec'],$d['tm_mon']+1,$d['tm_mday'],$d['tm_year']+1900);
		return new DateTime(date('Y-m-d H:i:s',$t));
		return new DateTime($date);
	}	
		
	public function limitQuery($query, $limit, $offset = null)
	{
		if (!$offset) $offset = 0;
			
		if (!is_null($limit)) return
			"SELECT z2.*
			FROM (
			SELECT  /*+ first_rows($limit) */ ROWNUM , z1.*
			FROM (" . $query . ") z1
			) z2
			WHERE z2.\"ROWNUM\" BETWEEN " . ($offset+1) . " AND " . ($limit+$offset);

		if (!$offset) return $query;
		
		return
			"SELECT z2.*
			FROM (
			SELECT ROWNUM, z1.*
			FROM (" . $query . ") z1
			) z2
			WHERE z2.\"ROWNUM\" > " . ($offset);
	}
		
	protected function getType($nativeType)
	{
		$aMapType = array (
			'NUMBER' => 'float',
			'VARCHAR2' => 'string',
			'CLOB' => 'lob',
			'BLOB' => 'lob',
			'TIMESTAMP' => 'timestamp',
			'DATE' => 'date'
		);
		
		return isset($aMapType[$nativeType]) ? $aMapType[$nativeType] : $nativeType;
	}
	
	public function describe($table, $param)
	{
		$sql = "SELECT TC.TABLE_NAME, TB.OWNER, TC.COLUMN_NAME, TC.DATA_TYPE, TC.DATA_DEFAULT, TC.NULLABLE, TC.COLUMN_ID, TC.DATA_LENGTH, TC.DATA_SCALE, TC.DATA_PRECISION, C.CONSTRAINT_TYPE, CC.POSITION
            FROM ALL_TAB_COLUMNS TC
            LEFT JOIN (ALL_CONS_COLUMNS CC JOIN ALL_CONSTRAINTS C ON (CC.CONSTRAINT_NAME = C.CONSTRAINT_NAME AND CC.TABLE_NAME = C.TABLE_NAME AND C.CONSTRAINT_TYPE = 'P')) ON TC.TABLE_NAME = CC.TABLE_NAME AND TC.COLUMN_NAME = CC.COLUMN_NAME
            JOIN ALL_TABLES TB ON (TB.TABLE_NAME = TC.TABLE_NAME AND TB.OWNER = TC.OWNER)
            WHERE "
            . "UPPER(TC.TABLE_NAME) = UPPER('$table')";
            $sql .= "AND UPPER(TB.OWNER) = UPPER('{$param['username']}')";
            $sql .= ' ORDER BY TC.COLUMN_ID';

            $stmt = $this->pdo->query($sql);

            $columns = array();
            while ($row = $stmt->fetch(PDO::FETCH_NUM))
            {
				$pos = strpos($row[3],'(');
				if ($pos!==false)
					$row[3] = substr($row[3], 0, $pos);
				
				$type = $this->getType($row[3]);//isset($aMapType[$row[3]]) ? $aMapType[$row[3]] : $row[3];
				$columns[strtolower($row[2])] = array (
					'type' => $type,
					'primary' => $row[10] == 'P',
					'signed'  => null,
					'fk' => array()
				);
            }
			/*
			
			$stmt = $this->pdo->query("
			  select c.constraint_name,

				c.r_owner,(select r.table_name from Dba_constraints r where c.r_owner = r.owner and c.r_constraint_name = r.constraint_name) r_table_name,

				c.r_constraint_name,c.status,x.columns from Dba_constraints c


				left join (

					select  index_name, table_owner, wm_concat(column_name) columns 
					  from(  
					  select 
						dc.table_owner,
						dc.index_name,        
						dc.column_name,
						dc.column_position position
					  from Dba_ind_columns dc,Dba_indexes di        
					  where 
					   dc.index_name = di.index_name           
					  and dc.index_owner = di.owner 
				  ) group by table_owner,index_name

				) x on x.index_name =  c.r_constraint_name and x.table_owner = c.r_owner

				where c.owner = :OBJECT_OWNER and c.table_name = :OBJECT_NAME and c.constraint_type = 'R' and status = 'ENABLED'

				order by c.constraint_name  ");*/
			
		//TODO : support multiples columns fkey
			
		$stmt = $this->pdo->query("
			select a.table_name, wm_concat(LOWER(b.column_name)) column_name, c.table_name parent_table, wm_concat(d.column_name) parent_pk
			from all_constraints a, all_cons_columns b, all_constraints c, all_cons_columns d
			where a.constraint_name = b.constraint_name
			  and b.table_name=upper('$table')
			/*  and UPPER(a.OWNER) = UPPER('{$param['username']}')*/
			  and a.r_constraint_name is not null
			  and a.r_constraint_name=c.constraint_name
			  and c.constraint_name=d.constraint_name
			  and a.status = 'ENABLED'
			  group by a.table_name, c.table_name");
			
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$row = array_change_key_case($row, CASE_LOWER);
			if (isset($columns[$row['column_name']]))
				$columns[$row['column_name']]['fk'] = array (
					'table' => $row['parent_table'],
					'column' => $row['parent_pk'],
					'alias' => substr($row['column_name'],0,strlen($row['column_name'])-3)
				);			
		}	
		
		//$GLOBALS['QueryLog']->addNotice(var_export($columns,1));
					//Debug::getInstance()->AddVar('describe',$columns);
		return $columns;
	}	
}

/**
 * Adapter for mysql database
 */
class db_Adapter_Mysql extends db_Adapter {
	
	public function quoteName($name)
	{
		return $this->_quoteName($name, '`');
	}
	
	public function describe($table, $params)
	{
		$mysqlType = array(
			'tinyint' => array('int',1),
			'smallint' => array('int',2),
			'mediumint' => array('int',3),
			'int' => array('int',4),
			'integer' => array('int',4),
			'bigint' => array('int',8),
			'number' => array('int',8),
			'float' => array('float',4),
			'double' => array('float',8),
			'real' => array('float',8),
			'decimal' => array('float',8),

			'datetime' => array('date',8,'\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}'),
			'date' => array('date',3,'\d{4}-\d{2}-\d{2}'),
			'timestamp' => array('date',4,'\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}'),
			'time' => array('date',3,'\d{2}:\d{2}:\d{2}'),
			'year' => array('date',1,'\d{4}'),

			'char' => array('string',1,'.{0,n}',255),
			'binary' => array('string',1,'.{0,n}',255),
			'varchar' => array('string',1,'.{0,n}',255),
			'tinyblob' => array('string',1,'.{0,n}',pow(2,8)),
			'tinytext' => array('string',1,'.{0,n}',pow(2,8)),
			'blob' => array('string',1,'.{0,n}',pow(2,16)),
			'text' => array('string',1,'.{0,n}',pow(2,16)),
			'mediumblob' => array('string',1,'.{0,n}',pow(2,24)),
			'mediumtext' => array('string',1,'.{0,n}',pow(2,24)),
			'longblob' => array('string',1,'.{0,n}',pow(2,32)),
			'longtext' => array('string',1,'.{0,n}',pow(2,32)),

			'enum' => array('enum')
		);

		$stmt = $this->pdo->query("describe ". $this->quoteName($table));

		$columns = array();
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
		{
			$type = trim($row['Type']);
			preg_match('`^([a-zA-Z_-]+)(\((\d+(,\d+)?|((\'[^\']+\',?))+)\))?(\s+(?:un)?signed)?$`xi',$type,$m);
			$type = strtolower(@$m[1]);
			
			$param = array_key_exists(3,$m)?$m[3]:null;
			$signed =isset($m[6]) && stristr($m[6],'unsigned') === false?true:false;
			$columns[$row['Field']] =
				array (
					'type' => $mysqlType[$type][0],
					'primary' => $row['Key']=='PRI',
					'signed' => $signed,
					'ai' => $row['Extra'] == 'auto_increment',
					'fk' => array(),
					'generated' => strpos($row['Extra'], 'GENERATED') !== false
				);
		}
		
		preg_match('`\bdbname=(\w+)\b`', $params['dsn'], $m);
			
		$stmt = $this->pdo->query(
			"SELECT u.referenced_table_schema,u.referenced_table_name,u.referenced_column_name,u.column_name
			FROM information_schema.table_constraints AS c
			INNER JOIN information_schema.key_column_usage AS u USING( constraint_schema, constraint_name )
			WHERE c.constraint_type = 'FOREIGN KEY' AND c.table_schema='".$m[1]."' AND c.table_name='$table'"
		);

		while ($row = $stmt->fetch(PDO::FETCH_ASSOC))
			$columns[$row['column_name']]['fk'] = array (
				'schema' => $row['referenced_table_schema'],
				'table' => $row['referenced_table_name'],
				'column' => $row['referenced_column_name'],
				'alias' => substr($row['column_name'],0,strlen($row['column_name'])-3)
			);

		return $columns;
	}	
}

/**
 * Adapter interface
 * @abstract
 */
abstract class db_Adapter {

	const SEPARATOR = '.';
	
	/**
	 * @var PDO
	 */
	protected $pdo;
	
	final private function __construct(PDO $pdo) 
	{
		$this->pdo = $pdo;
	}

	/**
	 *
	 * @param DateTime $value 
	 * @return string
	 */
	public function formatDate(DateTime $value)
	{
		return $value->format('Y-m-d H:m:i');
	}
	
	/**
	 *
	 * @param string $value
	 * @return DateTime 
	 */
	public function parseDate($value)
	{
		if ($value == null) return null;
		return new DateTime($value);
	}
	
	public function limitQuery($query, $limit, $offset = null)
	{
		if (!is_null($limit))
			$query .= ' LIMIT ' . $limit;

        if (!is_null($offset))
            $query .= ' OFFSET ' . $offset;

		return $query;
	}

	public function prefixColumn($column, $prefix)
	{
		return 	$this->quoteName($prefix).self::SEPARATOR.$this->quoteName($column);
	}
	
	public function prefixColumns(array $columns, $prefix)
	{
		foreach ($columns as &$v)
			$v = $this->prefixColumn($v, $prefix);
		return $columns;
	}
	
	public function quote($value) 
	{
		if ($value instanceof db_Expr)
			return $value;
		return $this->pdo->quote($value);
	}

	protected function _quoteName($name, $quote)
	{
		if ($name instanceof db_Expr)
			return $name;
		if ($name instanceof DateTime) // XXX
			return $this->formatDate ($name);
		if ($name == '*')
			return '*';
		if (strpos($name, self::SEPARATOR) === false && strpos($name, ' ') === false)
			return $quote.$name.$quote;

		$name = preg_split("/[\s]+/", $name);
		foreach ($name as &$n) {
			$n = explode(self::SEPARATOR, $n);
			$n = implode(self::SEPARATOR, array_map(array($this, 'quoteName'), $n));
		}
		return implode(' ',$name);
	}
	
	abstract public function quoteName($name);
	abstract public function describe($table, $params);
	
	static public function Get(PDO $pdo, $name = null)
	{
		if (!$name) $name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
		$className = 'db_Adapter_' . ucfirst(strtolower($name));
		if (!class_exists($className)) 
			throw new Exception('No adapter found for '.$name);
		return new $className($pdo);
	}
}