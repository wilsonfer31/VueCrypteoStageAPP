<?php
class db_Row_Observer_Timestamping extends db_Row_Observer {

	public function beforeSave(db_Row $row)
	{
		if ($row->isNew() && isset($row->date_creation))
			$row->date_creation = date('Y-m-d H:i:s');

		if (isset($row->date_update))
			$row->date_update = date('Y-m-d H:i:s');

		if ($row->isNew() && isset($row->created_on))
			$row->date_creation = date('Y-m-d H:i:s');

		if (isset($row->updated_on))
			$row->date_update = date('Y-m-d H:i:s');
	}
}