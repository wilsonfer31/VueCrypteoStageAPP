<?php
class db_Row_Observer_Counter extends db_Row_Observer {

	protected $field;

	public function __construct($field) {
		$this->field = $field;
	}

	public function beforeSave(db_Row $row) {
		if (!$row->isNew()) return;
		$number = $row->getTable()->q()
			->select(new db_Expr('IFNULL(max(`number`),0)+1 as number'))
			->where( array ($this->field => $row->{$this->field} ) )
			->execute()->fetch(PDO::FETCH_OBJ);
		$row->number = $number->number;
	}
}