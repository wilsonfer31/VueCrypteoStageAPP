<?php

interface db_Row_IObserver
{
	public function beforeSave(db_Row $row);
	public function afterSave(db_Row $row, $success);
	public function beforeDelete(db_Row $row);
	public function afterDelete(db_Row $row, $success);
}
