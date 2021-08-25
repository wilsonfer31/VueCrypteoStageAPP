<?phpclass db_DataHandler implements IDataHandler {	protected $table;		public function __construct($name) {		$this->table = $name;	}		protected function setTable($name) {		$this->table = $name;	}	public function getColumns() {		return array_keys(db_Table::Get($this->table)->getFields());	}		public function fetch($id) {		return db_Row::Get($this->table, $id);	}	public function autocomplete($keywords, $pagination = null) {		return db_Table::Get($this->table)->autocomplete($keywords, $pagination);	}	public function find($keywords, $pagination = null) {		return db_Table::Get($this->table)->find($keywords, $pagination);	}	public function save($datas) {		$id = @$datas->id;		return $id ? $this->update($id, $datas) : $this->add($datas);	}	public function add($datas) {		unset($datas->id);		$row = db_Row::Get($this->table);		return $row->setFromAssoc((array)$datas)->save();	}	public function update($id, $datas) {		unset($datas->id);		return db_Row::Get($this->table, $id)->setFromAssoc((array)$datas)->save();	}	public function delete($id) {		return db_Row::Get($this->table, $id)->delete();	}}