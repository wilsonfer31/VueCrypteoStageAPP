<?php
class db_Row_Observer_Owner extends db_Row_Observer {

    private $owner = null;

    public function setOwner($o) {
        $this->owner = $o;
        return $this;
    }

	public function beforeSave(db_Row $row)
	{		
		if (isset($row->update_user_id))
			$row->update_user_id = $this->o;

		if ($row->isNew() && isset($row->user_id) && !$row->user_id)
			$row->user_id = $this->o;
	}
}