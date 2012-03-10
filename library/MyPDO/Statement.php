<?php
class MyPDO_Statement implements IteratorAggregate, Countable
{
    /**
     * 尚未发射的语句
     * @var array
     */
    protected static $_waitingQueue = array();
    
    /**
     * 已经发射的语句
     * @var array
     */
    protected static $_fetchingQueue = array();
    
    /**
     * 
     * @var PDOStatement
     */
    protected static $_stmt = null;
    
    /**
     * 
     * @var MyPDO_Select
     */
    protected $_select;
    
    protected $_fetchArgument;
    
    protected $_ctorArgs;
    
	protected $_rowset = false;
	
	/**
	 * 将buffer中现有的所有结果集都取回来
	 */
	public static function flush(){
		if (self::$_stmt === null)
			return;
		
		while(self::$_stmt->nextRowset()){
    		$query = array_shift(self::$_fetchingQueue);
    		$query->_rowset = $query->_fetchAll();
    	}
    	self::$_stmt = null;
	}
    
    /**
     * 构造函数
     * 
     * @param $select
     * @param $fetchMode
     * @param $fetchArgument
     * @param $ctorArgs
     */
    public function __construct($select, $fetchMode = null, $fetchArgument = null, $ctorArgs = null){
    	$this->_select = $select;
        $this->_fetchMode = $fetchMode;
        $this->_fetchArgument = $fetchArgument;
        $this->_ctorArgs = $ctorArgs;
        
        self::$_waitingQueue[] = $this;
    }
	
    public function __toString(){
    	return $this->_select->assemble();
    }
    
    protected function _fetchAll(){
    	switch ($this->_fetchMode){
    		case MyPDO::FETCH_DATAOBJECT:
    			self::$_stmt->setFetchMode(PDO::FETCH_ASSOC);
		    	$rowset = new SplFixedArray(self::$_stmt->rowCount());
				
		    	$rowClass = $this->_fetchArgument;
				foreach (self::$_stmt as $index => $data)
		        	$rowset[$index] = new $rowClass($data, true, $this->_ctorArgs);
		        
		        return $rowset;
		    
		    case MyPDO::FETCH_CLASSFUNC:
		    	self::$_stmt->setFetchMode(PDO::FETCH_ASSOC);
		    	$rowset = new SplFixedArray(self::$_stmt->rowCount());
				
		    	$classFunc = $this->_fetchArgument;
		    	foreach (self::$_stmt as $index => $data){
		    		$rowClass = $classFunc($data);
		        	$rowset[$index] = new $rowClass($data, true, $this->_ctorArgs);
		    	}
		        return $rowset;
		        
    		default:
		    	if (isset($this->_ctorArgs))
	    			return self::$_stmt->fetchAll($this->_fetchMode, $this->_fetchArgument, $this->_ctorArgs);
	    		
    			if (isset($this->_fetchArgument))
			    	return self::$_stmt->fetchAll($this->_fetchMode, $this->_fetchArgument);
				    
			    if (isset($this->_fetchMode))
			    	return self::$_stmt->fetchAll($this->_fetchMode);
    	}
    }
    
    /**
     * 
     * @throws PDOException
     */
    public function _query(){
    	if ($this->_rowset === false){
    		
    		if (self::$_stmt){
    			while(self::$_stmt->nextRowset()){//如果已经在结果缓存中，则搜寻结果集
		    		$query = array_shift(self::$_fetchingQueue);
		    		$query->_rowset = $query->_fetchAll();
		    		
		    		if ($query === $this)
		    			return;
		    	}
    			self::$_stmt = null;
    		}
    		
    		//将当前的语句插到第一个，然后把所有语句一口气打包发送给mysql
    		$keys = array_keys(self::$_waitingQueue, $this);
    		
    		if (count($keys))
    			unset(self::$_waitingQueue[$keys[0]]);
    		
    		
    		$sql = $this->_select->assemble();
    		if (count(self::$_waitingQueue))
    			$sql .= ";\n" . implode(";\n", self::$_waitingQueue);
    		
    		implode(";\n", self::$_waitingQueue);
    		
    		self::$_stmt = $this->_select->getAdapter()->query($sql);
    		
    		$this->_rowset = $this->_fetchAll();
    		
    		self::$_fetchingQueue = self::$_waitingQueue;
    		
    		self::$_waitingQueue = array();
	    }
    }
    
    /**
     * 强制获得结果集
     * 
     * @return mixed
     */
    public function fetch(){
    	$this->_query();
    	
    	return $this->_rowset;
    }
    
    /**
     * 获得迭代器，支持foreach
     */
    public function getIterator(){
    	$this->_query();
    	
    	if (is_array($this->_rowset))
    		return new ArrayIterator($this->_rowset);
    	
    	return $this->_rowset;
    }
    
    public function current(){
    	$this->_query();
    	
    	return count($this->_rowset) ? current($this->_rowset) : null;
    }
    
	public function count() {
		$this->_query();
    	
    	return count($this->_rowset);
    }
}
