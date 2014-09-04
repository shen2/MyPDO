<?php
namespace MyPDO;
use \PDO;

/**
 * 
 * @author shen2
 * 
 * 增加了fetchAll 和 fetchRow 方法
 * 
 */
class TableSelect extends Select
{
    /**
     * Table schema for parent DataObject.
     *
     * @var array
     */
    protected $_info;

    /**
     * Table instance that created this select object
     *
     * @var string
     */
    protected $_table;

    /**
     * Class constructor
     *
     * @param string $table
     */
    public function __construct($table)
    {
        parent::__construct($table::getAdapter());

        $this->setTable($table);
    }

    /**
     * Return the table that created this select object
     *
     * @return DataObject_Abstract
     */
    public function getTable()
    {
        return $this->_table;
    }

    /**
     * Sets the primary table name and retrieves the table schema.
     *
     * @param string $table
     * @return Select This Select object.
     */
    public function setTable($table)
    {
        $this->_info    = $table::info();
        $this->_table   = $table;

        return $this;
    }

    /**
     * Tests query to determine if expressions or aliases columns exist.
     *
     * @return boolean
     */
    public function isReadOnly()
    {
        $readOnly = false;
        $fields   = $this->getPart(Select::COLUMNS);
        
        if (!count($fields)) {
            return $readOnly;
        }

        foreach ($fields as $columnEntry) {
            $column = $columnEntry[1];
            $alias = $columnEntry[2];

            if ($alias !== null) {
                $column = $alias;
            }

            switch (true) {
                case ($column == self::SQL_WILDCARD):
                    break;

                case ($column instanceof Expr):
                    $readOnly = true;
                    break 2;
            }
        }

        return $readOnly;
    }

    /**
     * Adds a FROM table and optional columns to the query.
     *
     * The table name can be expressed
     *
     * @param  array|string|Expr $name The table name or an associative array relating table name to correlation name.
     * @param  array|string|Expr $cols The columns to select from this table.
     * @param  string $schema The schema name to specify, if any.
     * @return TableSelect This TableSelect object.
     */
    public function from($name, $cols = self::SQL_WILDCARD, $schema = null)
    {
        return $this->joinInner($name, null, $cols, $schema);
    }

    /**
     * Performs a validation on the select query before passing back to the parent class.
     * Ensures that only columns from the primary DataObject are returned in the result.
     *
     * @return string|null This object as a SELECT string (or null if a string cannot be produced)
     */
    public function assemble()
    {
        if (count($this->_parts[self::UNION]) == 0) {
	        $fields  = $this->getPart(Select::COLUMNS);
	        $primary = $this->_info[DataObject::NAME];
	        $schema  = $this->_info[DataObject::SCHEMA];
        	
            // If no fields are specified we assume all fields from primary table
            if (!count($fields)) {
                $this->from($primary, self::SQL_WILDCARD, $schema);
            }
        }

        return parent::assemble();
    }
    
    /*	下面两个方法来自Table	*/
    
    /**
     * 
     * @return Statement
     */
    public function fetchAll($fetchMode = null){
    	$fetchArgument = $this->_table;
    	if (property_exists($fetchArgument, 'classFunc') && $fetchArgument::$classFunc){
    		$fetchMode = Statement::FETCH_CLASSFUNC;
    		$fetchArgument = $fetchArgument::$classFunc;
    	}
    	else{
    		$fetchMode = Statement::FETCH_DATAOBJECT;
    	}
        return new Statement($this, $fetchMode, $fetchArgument, $this->isReadOnly());
    }
    
    
    public function fetchRow($bind = array(), $fetchMode = null){
        $this->_parts[self::LIMIT_COUNT]  = 1;
        
        $stmt = $this->_adapter->query($this);
        
        if ($stmt->rowCount() == 0){
        	return null;
        }
        
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $rowClass = $this->_table;
        
        if (property_exists($rowClass, 'classFunc') && ($classFunc = $rowClass::$classFunc))
        	$rowClass = $classFunc($data);
		
        return new $rowClass($data, true, $this->isReadOnly());
    }
}