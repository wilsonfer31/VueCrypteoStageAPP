/*! \mainpage Usage
  \section Database 
  \subsection ssec1 Basic Usage
  
  <img style="vertical-align:middle;float:left;padding-right:4px;" src="../../warn.png"> Database structure is stored in a cache file "db_describe". This file must be deleted whenever the database structure has changed.
  
  <b>Create a database instance</b>
  \code{.php}  
  $db = db_Driver::Create('MyDb', array(
         'dsn'=>'dsn',
         'username'=>'username',
         'password'=>'password'
  ));
  \endcode
  
  <b>Execute a SQL query.</b>
  \code{.php}  
  $pdoStatement = $db->execute("select * from mytable where mycolumn = ?", array('myvalue'));
  \endcode
  
  <b>Criteria</b>
  
  A criteria is used to generate the where part of the query and can be either :
    - An mixed array of db_Criterion or [ column => value, .... ]\n
	  Generate a query where each column must equal its respective value and all db_Criterion must be true.\n
	  if value is a array 'in' will be used in the query.
	- A db_Criteria object
	  
  <b>Select</b>
  \code{.php}  
 
  $myRows = $db->selectAll(
	'mytable',
	array('column' => 'value') // Criteria
	array(),
	array('sortColumn')
   );
   
  $myRows = $db->selectAll(
	'mytable',
	array( 
		new db_Criterion('column', 'value', 'like'),
		new db_Criterion('column2', array('v1','v2'), 'in')
	),
	array(),
	array('sortColumn')
  );
   
  \endcode
  
  <b>Insert, update, delete</b>
  \code{.php}  
  $db->insert('mytable', array('name' => 'Toto', 'email'=>'toto@toto.com'));
  $countAffectedRows = $db->update('mytable', array('email' => 'toto@toto.com'), array('id' => 1));
  $countAffectedRows = $db->delete('mytable', array('email' => 'toto@toto.com'));
  \endcode
  
  <b>Use the query generator.</b>
  \code{.php}  
  $myUsers = $db->qs()
	->from('user')
    ->select(array('user.name', 'user.email', 'group.name'))
    ->join('group', 'group_id', 'id')
    ->where(array('service_id', 1)) // Criteria
    ->orderBy('user.name')
    ->execute()
    ->fetchAll();
  \endcode
  
  
  
  \subsection ssec2 Object Model
  
  Strict naming is required. Each table must have a column 'id', foreign key columns must follow the pattern 'tablename_id'.
  Intermediate tables for N..N relation are named 'table1_has_table2'
	
  <b>Select row by id.</b>
  \code{.php}  
  $row = $db->r('mytable', myid);
  echo $row->column;
  var_dump($row->toArray());  
  \endcode
  
  <b>Autojoin</b>
  
  Autojoin is actived by default. if there is a 1..N relation between table1 and table2, db_Row for table1 will have a table2 member.\n
  For example if you have a table user and a table group with only one group for each user:
  \code{.php}  
  $user = $db->r('user', myid);
  echo 'User group is '.$user->group->name; 
  \endcode  
  
  <b>Insert row</b>
  \code{.php}  
  $row = $db->r('mytable');
  $row->setFromAssoc(array('column' => 'value'));
  $row->column2 = 'value2';
  $row->save(); // insert
  \endcode
  
  <b>Update/delete row</b>
  \code{.php}  
  $row = $db->r('mytable', myid);
  $row->column = 'value';
  $row->save(); // update
  
  $row->update('column2', 'value2'); // update
  
  $row->delete();
  \endcode
  
  <b>Select by criteria</b>
  \code{.php}  
  // Select a single row 
  $row = $db->t('mytable')->select(array('column' => 'value'));
  $row = $db->t('mytable')->select(new db_Criterion('column', 'value', 'like'));
  
  // Select multiple rows 
  $myRows = $db->t('mytable')->selectAll(new db_Criterion('column', 'value', 'like'));
  \endcode
  
  \subsection ssec3 Overriding base class
  
  Class db_Row and db_Table can be extended for each table.\n
  Name of those classes must be db_Row_mytable / db_Table_mytable and their location must be set:
  \code{.php}  
  $db->setClassTableLocation(path);
  $db->setClassRowLocation(path);
  \endcode
 
  Examples:
  
  \code{.php}  
  class db_Row_User extends db_Row {
	public function getRights($email) {
	  return $this->getDriver()->execute(
		'select rights.* from user_group_has_rights left join `rights` on rights.id = rights_id where user_group_id = ?',
		array ($this->user_group_id)
	  )->fetchAll();
	}
  }
  $myRights = $db->r('User', $myid)->getRights();
  \endcode
  
  \code{.php}  
  class db_Table_User extends db_Row {
	public function getByEmail($email) {
	  return $this->select(array('email' => $email));	
	}
  }
  $myUser = $db->t('User')->getByEmail($myEmail);
  \endcode

  \subsection ssec4 Filters

  Filters simplify the creation of complex criteria.\n
  Filter inherit the db_Filter base class. There are several default filter (see db_Filter).

  Example to search by name / date:
  \code{.php}
  $f = new \db_Filters();
  $f->addFilter(new \db_Filter_Like('name', 'user.name'));
  $f->addFilter(new \db_Filter_Between('date_creation', 'user.date_creation'));

  /* Criteria for user with name beginnin with Jo */
  $criteria = $f->compute(['name' => 'Jo%']);

  /* Criteria for user created in 2014 */
  $criteria = $f->compute(['date_creation' => (object)['from' => '2014-01-01', 'to' => '2015-01-01']);

  /* Or criteria for both */
  $criteria = $f->compute([
    'name' => 'Jo%',
	'date_creation' => (object)['from' => '2014-01-01', 'to' => null]
  ]);

  /* then fetch them */
  $users = $db->t('user')->selectAll($criteria);
  \endcode

  The search function simplify this:
  \code{.php}
  list($count, $users) = $db->t('user')->search(
	$filters,
    [
      'name' => 'Jo%',
	  'date_creation' => (object)['from' => '2014-01-01', 'to' => null]
    ]
  );
  \endcode
  
*/