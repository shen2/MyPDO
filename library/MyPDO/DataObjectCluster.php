<?php
namespace MyPDO;

abstract class DataObjectCluster extends DataObject{
	//在单机类的基础上派生。
	
	protected $_slaveDb;
	
    /**
     * Returns an instance of a TableSelect object.
     *
     * @param bool $withFromPart Whether or not to include the from part of the select based on the table
     * @return TableSelect
     */
    public static function selectFromMaster($withFromPart = self::SELECT_WITHOUT_FROM_PART)
    {
        $select = static::select();
        return $select;
    }
	
	public function fetchRowFromMaster($where = null, $order = null){
        $select = static::selectFromMaster();

        if ($where !== null)
            static::_where($select, $where);
        
        if ($order !== null)
            $select->order($order);
        
        return $select->fetchRow();
		
	}
	
	public function _refresh(){
		$where = $this->_getWhereQuery();
        $row = static::fetchRowFromMaster($where);

    	if (null === $row) {
            //require_once 'Zend/Db/Table/Row/Exception.php';
            throw new DataObjectException('Cannot refresh row as parent is missing');
        }
		
        $this->_cleanData = $row->getArrayCopy();
        $this->exchangeArray($this->_cleanData);
		$this->_modifiedFields = array();
	}
}