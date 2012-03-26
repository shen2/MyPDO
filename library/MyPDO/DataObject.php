<?php
namespace MyPDO;

/**
 * 用 php 5.3 的延迟绑定特性，实现的ORM类
 * 
 * 实现了Zend_Db_Table和Zend_Db_Table_Row的大部分主要功能
 * 基于spl中的ArrayObject，速度超快
 * 
 * 取消了一部分高级特性
 * init			Table的init方法
 * Definition	表的定义
 * Metadata		元数据
 * MetadataCachey元数据缓存
 * ReferenceMap 关联信息
 * ManyToManyRowset 跨表查询
 * Cols 		字段数组
 * Default_Class 用一个类来保存默认值
 * COLUMNS      常量
 * order		方法
 * 
 * 替换了两个方法的名称：
 * Zend_Db_Table_Row_Abstract::toArray() 用 ArrayObject::getArrayCopy() 代替
 * Zend_Db_Table_Row_Abstract::delete() 用 DataObject::remove() 代替
 * 
 * 修改了构造函数的参数列表
 * 将原来一个config数组，改成直接传入三个参数，data, stored, readOnly，以提高10%的fetch性能
 * 
 * @author shen2
 *
 */
abstract class DataObject extends \ArrayObject
{
    const ADAPTER          = 'db';
    const SCHEMA           = 'schema';
    const NAME             = 'name';
    const PRIMARY          = 'primary';
    const SEQUENCE         = 'sequence';
    
    const SELECT_WITH_FROM_PART    = true;
    const SELECT_WITHOUT_FROM_PART = false;

    const ARRAYOBJECT_FLAGS= 0;//ArrayObject::ARRAY_AS_PROPS;

    /**
     * Default Adapter object.
     *
     * @var Adapter
     */
    protected static $_defaultDb = null;

    /**
     * Adapter object.
     *
     * @var Adapter
     */
    protected static $_db;

    /**
     * The schema name (default null means current schema)
     *
     * @var array
     */
    protected static $_schema = null;

    /**
     * The table name.
     *
     * @var string
     */
    protected static $_name = null;

    /**
     * The primary key column or columns.
     * A compound key should be declared as an array.
     * You may declare a single-column primary key
     * as a string.
     * modified by shen2 必须是数组!
     *
     * @var array 
     */
    protected static $_primary = null;

    /**
     * If your primary key is a compound key, and one of the columns uses
     * an auto-increment or sequence-generated value, set _identity
     * to the ordinal index in the $_primary array for that column.
     * Note this index is the position of the column in the primary key,
     * not the position of the column in the table.  The primary key
     * array is 0-based.
     *
     * @var integer
     */
    protected static $_identity = 0;

    /**
     * Define the logic for new values in the primary key.
     * May be a string, boolean true, or boolean false.
     *
     * @var mixed
     */
    protected static $_sequence = true;

    protected static $_defaultValues = array();

    /**
     * Constructor.
     *
     * Supported params for $config are:
     * - db              = user-supplied instance of database connector,
     *                     or key name of registry instance.
     * - name            = table name.
     * - primary         = string or array of primary key(s).
     *
     * @param  mixed $config Array of user-specified config options, or just the Db Adapter.
     * @return void
     */
    public static function initialize($config = array())
    {
        if ($config) {
            static::setOptions($config);
        }

        static::_setup();
    }

    /**
     * setOptions()
     *
     * @param array $options
     * @return Zend_Db_Table_Abstract
     */
    public static function setOptions(Array $options)
    {
        foreach ($options as $key => $value) {
            switch ($key) {
                case self::ADAPTER:
                    static::_setAdapter($value);
                    break;
                case self::SCHEMA:
                    static::$_schema = (string) $value;
                    break;
                case self::NAME:
                    static::$_name = (string) $value;
                    break;
                case self::PRIMARY:
                    static::$_primary = (array) $value;
                    break;
                case self::SEQUENCE:
                    static::_setSequence($value);
                    break;
                default:
                    // ignore unrecognized configuration directive
                    break;
            }
        }
    }
    
    /**
     * set the default values for the table class
     *
     * @param array $defaultValues
     */
    public static function setDefaultValues(Array $defaultValues)
    {
        static::$_defaultValues = array_merge(static::$_defaultValues, $defaultValues);
    }

    public static function getDefaultValues()
    {
        return static::$_defaultValues;
    }

    /**
     * Sets the default Adapter for all Zend_Db_Table objects.
     *
     * @param  Adapter $db an Adapter object
     * @return void
     */
    public static function setDefaultAdapter(Adapter $db)
    {
        self::$_db = $db;
    }

    /**
     * Gets the default Adapter for all Zend_Db_Table objects.
     *
     * @return Adapter or null
     */
    public static function getDefaultAdapter()
    {
        return self::$_db;
    }

    /**
     * @param  Adapter $db an Adapter object
     */
    protected static function _setAdapter(Adapter $db)
    {
        static::$_db = $db;
    }

    /**
     * Gets the Adapter for this particular Zend_Db_Table object.
     *
     * @return Adapter
     */
    public static function getAdapter()
    {
        return static::$_db;
    }

    /**
     * Sets the sequence member, which defines the behavior for generating
     * primary key values in new rows.
     * - If this is a string, then the string names the sequence object.
     * - If this is boolean true, then the key uses an auto-incrementing
     *   or identity mechanism.
     * - If this is boolean false, then the key is user-defined.
     *   Use this for natural keys, for example.
     *
     * @param mixed $sequence
     * @return Zend_Db_Table_Adapter_Abstract Provides a fluent interface
     */
    protected static function _setSequence($sequence)
    {
        static::$_sequence = $sequence;
    }

    /**
     * Turnkey for initialization of a table object.
     * Calls other protected methods for individual tasks, to make it easier
     * for a subclass to override part of the setup logic.
     *
     * @return void
     */
    protected static function _setup()
    {
        static::_setupDatabaseAdapter();
        static::_setupTableName();
    }

    /**
     * Initialize database adapter.
     *
     * @return void
     */
    protected static function _setupDatabaseAdapter()
    {
        if (! static::$_db) {
            static::$_db = self::getDefaultAdapter();
            if (!static::$_db instanceof Adapter) {
                //require_once 'Zend/Db/Table/Exception.php';
                throw new DataObjectException('No adapter found for ' . get_called_class());
            }
        }
    }

    /**
     * Initialize table and schema names.
     *
     * If the table name is not set in the class definition,
     * use the class name itself as the table name.
     *
     * A schema name provided with the table name (e.g., "schema.table") overrides
     * any existing value for static::$_schema.
     *
     * @return void
     */
    protected static function _setupTableName()
    {
        if (! static::$_name) {
            static::$_name = get_called_class();
        } else if (strpos(static::$_name, '.')) {
            list(static::$_schema, static::$_name) = explode('.', static::$_name);
        }
    }

    /**
     * Returns table information.
     *
     * You can elect to return only a part of this information by supplying its key name,
     * otherwise all information is returned as an array.
     *
     * @param  string $key The specific info part to return OPTIONAL
     * @return mixed
     */
    public static function info($key = null)
    {
        $info = array(
            self::SCHEMA           => static::$_schema,
            self::NAME             => static::$_name,
            self::PRIMARY          => static::$_primary,
            self::SEQUENCE         => static::$_sequence
        );

        if ($key === null) {
            return $info;
        }

        if (!array_key_exists($key, $info)) {
            //require_once 'Zend/Db/Table/Exception.php';
            throw new DataObjectException('There is no table information for the key "' . $key . '"');
        }

        return $info[$key];
    }

    /**
     * Returns an instance of a TableSelect object.
     *
     * @param bool $withFromPart Whether or not to include the from part of the select based on the table
     * @return TableSelect
     */
    public static function select($withFromPart = self::SELECT_WITHOUT_FROM_PART)
    {
        //require_once 'Zend/Db/Table/Select.php';
        $select = new TableSelect(get_called_class());
        if ($withFromPart == self::SELECT_WITH_FROM_PART) {
            $select->from(static::$_name, Select::SQL_WILDCARD, static::$_schema);
        }
        return $select;
    }
    
    /**
     * Returns an instance of a TableSelect object.
     *
     * @param string|array|Expr $columns
     * @return TableSelect
     */
    public static function selectCol($columns = null)
    {
        $select = new TableSelect(get_called_class());
        $select->from(static::$_name, $columns === null ? Select::SQL_WILDCARD : $columns, static::$_schema);
        return $select;
    }

    /**
     * Inserts a new row.
     *
     * @param  array  $data  Column-value pairs.
     * @return mixed         The primary key of the row inserted.
     */
    public static function insert(array $data)
    {
        /**
         * Zend_Db_Table assumes that if you have a compound primary key
         * and one of the columns in the key uses a sequence,
         * it's the _first_ column in the compound key.
         */
        $pkIdentity = static::$_primary[static::$_identity];

        /**
         * If this table uses a database sequence object and the data does not
         * specify a value, then get the next ID from the sequence and add it
         * to the row.  We assume that only the first column in a compound
         * primary key takes a value from a sequence.
         */
        if (is_string(static::$_sequence) && !isset($data[$pkIdentity])) {
            $data[$pkIdentity] = static::$_db->nextSequenceId(static::$_sequence);
            $pkSuppliedBySequence = true;
        }

        /**
         * If the primary key can be generated automatically, and no value was
         * specified in the user-supplied data, then omit it from the tuple.
         * 
         * Note: this checks for sensible values in the supplied primary key
         * position of the data.  The following values are considered empty:
         *   null, false, true, '', array()
         */
        if (!isset($pkSuppliedBySequence) && array_key_exists($pkIdentity, $data)) {
            if ($data[$pkIdentity] === null                                        // null
                || $data[$pkIdentity] === ''                                       // empty string
                || is_bool($data[$pkIdentity])                                     // boolean
                || (is_array($data[$pkIdentity]) && empty($data[$pkIdentity]))) {  // empty array
                unset($data[$pkIdentity]);
            }
        }

        /**
         * INSERT the new row.
         */
        $tableSpec = (static::$_schema ? static::$_schema . '.' : '') . static::$_name;
        static::$_db->insert($tableSpec, $data);

        /**
         * Fetch the most recent ID generated by an auto-increment
         * or IDENTITY column, unless the user has specified a value,
         * overriding the auto-increment mechanism.
         */
        if (static::$_sequence === true && !isset($data[$pkIdentity])) {
            $data[$pkIdentity] = static::$_db->lastInsertId();
        }

        /**
         * Return the primary key value if the PK is a single column,
         * else return an associative array of the PK column/value pairs.
         */
        $pkData = array_intersect_key($data, array_flip(static::$_primary));
        if (count(static::$_primary) == 1) {
            reset($pkData);
            return current($pkData);
        }

        return $pkData;
    }
    
    /**
     * Inserts Delayed a new row.
     *
     * @param  array  $data  Column-value pairs.
     * @return mixed         The primary key of the row inserted.
     */
    public static function insertDelayed(array $data)
    {
        $tableSpec = (static::$_schema ? static::$_schema . '.' : '') . static::$_name;
        return static::$_db->insertDelayed($tableSpec, $data);
    }

    /**
     * Updates existing rows.
     *
     * @param  array        $data  Column-value pairs.
     * @param  array|string $where An SQL WHERE clause, or an array of SQL WHERE clauses.
     * @return int          The number of rows updated.
     */
    public static function update(array $data, $where)
    {
        $tableSpec = (static::$_schema ? static::$_schema . '.' : '') . static::$_name;
        return static::$_db->update($tableSpec, $data, $where);
    }

    /**
     * Deletes existing rows.
     *
     * @param  array|string $where SQL WHERE clause(s).
     * @return int          The number of rows deleted.
     */
    public static function delete($where)
    {
        $tableSpec = (static::$_schema ? static::$_schema . '.' : '') . static::$_name;
        return static::$_db->delete($tableSpec, $where);
    }
    
    /**
     * Fetches rows by primary key.  The argument specifies one or more primary
     * key value(s).  To find multiple rows by primary key, the argument must
     * be an array.
     *
     * This method accepts a variable number of arguments.  If the table has a
     * multi-column primary key, the number of arguments must be the same as
     * the number of columns in the primary key.  To find multiple rows in a
     * table with a multi-column primary key, each argument must be an array
     * with the same number of elements.
     *
     * The find() method always returns a Rowset object, even if only one row
     * was found.
     *
     * @param  mixed $key The value(s) of the primary keys.
     * @return SplFixedArray Row(s) matching the criteria.
     * @throws DataObjectException
     */
    public static function find()
    {
        $args = func_get_args();
        $keyNames = array_values(static::$_primary);

        if (count($args) != count($keyNames)) {
            //require_once 'Zend/Db/Table/Exception.php';
            throw new DataObjectException("Too few or too many columns for the primary key");
        }

        $whereList = array();
        $numberTerms = 0;
        foreach ($args as $keyPosition => $keyValues) {
            $keyValuesCount = count($keyValues);
            // Coerce the values to an array.
            // Don't simply typecast to array, because the values
            // might be Expr objects.
            if (!is_array($keyValues)) {
                $keyValues = array($keyValues);
            }
            if ($numberTerms == 0) {
                $numberTerms = $keyValuesCount;
            } else if ($keyValuesCount != $numberTerms) {
                //require_once 'Zend/Db/Table/Exception.php';
                throw new DataObjectException("Missing value(s) for the primary key");
            }
            $keyValues = array_values($keyValues);
            for ($i = 0; $i < $keyValuesCount; ++$i) {
                if (!isset($whereList[$i])) {
                    $whereList[$i] = array();
                }
                $whereList[$i][$keyPosition] = $keyValues[$i];
            }
        }

        $whereClause = null;
        if (count($whereList)) {
            $whereOrTerms = array();
            $tableName = static::$_db->quoteTableAs(static::$_name, null, true);
            foreach ($whereList as $keyValueSets) {
                $whereAndTerms = array();
                foreach ($keyValueSets as $keyPosition => $keyValue) {
                    //$type = $this->_metadata[$keyNames[$keyPosition]]['DATA_TYPE'];
                    $columnName = static::$_db->quoteIdentifier($keyNames[$keyPosition], true);
                    $whereAndTerms[] = static::$_db->quoteInto(
                        $tableName . '.' . $columnName . ' = ?',
                        $keyValue);//, $type FIXME 这个暂时无解，通通当成字符串处理吧
                }
                $whereOrTerms[] = '(' . implode(' AND ', $whereAndTerms) . ')';
            }
            $whereClause = '(' . implode(' OR ', $whereOrTerms) . ')';
        }

        // issue ZF-5775 (empty where clause should return empty rowset)
        if ($whereClause == null) {
            return new \SplFixedArray(0);
        }

        return static::fetchAll($whereClause);
    }

    /**
     * Fetches all rows.
     *
     * Honors the Zend_Db_Adapter fetch mode.
     *
     * @param string|array $where  OPTIONAL An SQL WHERE clause or TableSelect object.
     * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
     * @param int                               $count  OPTIONAL An SQL LIMIT count.
     * @param int                               $offset OPTIONAL An SQL LIMIT offset.
     * @return Statement The row results per the Zend_Db_Adapter fetch mode.
     */
    public static function fetchAll($where = null, $order = null, $count = null, $offset = null)
    {
        $select = static::select();

        if ($where !== null)
            static::_where($select, $where);

        if ($order !== null)
            $select->order($order);

        if ($count !== null || $offset !== null)
            $select->limit($count, $offset);
        
        return $select->fetchAll();
    }

    /**
     * Fetches one row in an object of type DataObject,
     * or returns null if no row matches the specified criteria.
     *
     * @param string|array $where  OPTIONAL An SQL WHERE clause or TableSelect object.
     * @param string|array                      $order  OPTIONAL An SQL ORDER clause.
     * @return DataObject|null The row results per the
     *     Zend_Db_Adapter fetch mode, or null if no row found.
     */
    public static function fetchRow($where = null, $order = null)
    {
        $select = static::select();

        if ($where !== null)
            static::_where($select, $where);
        
        if ($order !== null)
            $select->order($order);
        
        return $select->fetchRow();
    }

    /**
     * Fetches a new blank row (not from the database).
     *
     * @param  array $data OPTIONAL data to populate in the new row.
     * @param  string $defaultSource OPTIONAL flag to force default values into new row
     * @return DataObject
     */
    public static function createRow(array $data = array())
    {
        $row = new static(static::$_defaultValues, false, false);
        $row->setFromArray($data);
        return $row;
    }

    /**
     * Generate WHERE clause from user-supplied string or array
     *
     * @param  string|array $where  OPTIONAL An SQL WHERE clause.
     * @return TableSelect
     */
    protected static function _where(TableSelect $select, $where)
    {
        $where = (array) $where;

        foreach ($where as $key => $val) {
            // is $key an int?
            if (is_int($key)) {
                // $val is the full condition
                $select->where($val);
            } else {
                // $key is the condition with placeholder,
                // and $val is quoted into the condition
                $select->where($key, $val);
            }
        }

        return $select;
    }

    /*	下面是Object实例		*/
    
    /**
     * This is set to a copy of $_data when the data is fetched from
     * a database, specified as a new tuple in the constructor, or
     * when dirty data is posted to the database with save().
     *
     * @var array
     */
    protected $_cleanData = array();

    /**
     * Tracks columns where data has been updated. Allows more specific insert and
     * update operations.
     *
     * @var array
     */
    protected $_modifiedFields = array();

    /**
     * Connected is true if we have a reference to a live
     * Zend_Db_Table_Abstract object.
     * This is false after the Rowset has been deserialized.
     *
     * @var boolean
     */
    protected $_connected = true;

    /**
     * A row is marked read only if it contains columns that are not physically represented within
     * the database schema (e.g. evaluated columns/Expr columns). This can also be passed
     * as a run-time config options as a means of protecting row data.
     *
     * @var boolean
     */
    protected $_readOnly = false;
    
    
    /**
     * Constructor.
     *
     * Supported params for $config are:-
     * - table       = class name or object of type Zend_Db_Table_Abstract
     * - data        = values of columns in this row.
     *
     * @param  array $config OPTIONAL Array of user-specified config options.
     * @param
     * @return void
     * @throws DataObjectException
     */
    /**
     * 
     * @param array	  $data
     * @param boolean $stored
     * @param boolean $readOnly
     */
    public function __construct($data = array(), $stored = null, $readOnly = null)
    {
        parent::__construct($data, static::ARRAYOBJECT_FLAGS);
        
        if ($stored === true) {
            $this->_cleanData = $this->getArrayCopy();
        }

        if ($readOnly === true) {
            $this->setReadOnly(true);
        }

        $this->init();
    }

    /**
     * Set row field value
     *
     * @param  string $columnName The column key.
     * @param  mixed  $value      The value for the property.
     * @return void
     * @throws DataObjectException
     */
    public function offsetSet($columnName, $value)
    {
        parent::offsetSet($columnName,$value);
        $this->_modifiedFields[$columnName] = true;
    }

    /**
     * Store table, primary key and data in serialized object
     *
     * @return array
     */
    public function __sleep()
    {
        return array('_cleanData', '_readOnly' ,'_modifiedFields');
    }

    /**
     * Setup to do on wakeup.
     * A de-serialized Row should not be assumed to have access to a live
     * database connection, so set _connected = false.
     *
     * @return void
     */
    public function __wakeup()
    {
        $this->_connected = false;
    }

    /**
     * Initialize object
     *
     * Called from {@link __construct()} as final step of object instantiation.
     *
     * @return void
     */
    public function init()
    {
    }

    /**
     * Test the connected status of the row.
     *
     * @return boolean
     */
    public function isConnected()
    {
        return $this->_connected;
    }

    /**
     * Test the read-only status of the row.
     *
     * @return boolean
     */
    public function isReadOnly()
    {
        return $this->_readOnly;
    }

    /**
     * Set the read-only status of the row.
     *
     * @param boolean $flag
     * @return boolean
     */
    public function setReadOnly($flag)
    {
        $this->_readOnly = (bool) $flag;
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    public function save()
    {
        /**
         * If the _cleanData array is empty,
         * this is an INSERT of a new row.
         * Otherwise it is an UPDATE.
         */
        if (empty($this->_cleanData)) {
            return $this->_doInsert();
        } else {
            return $this->_doUpdate();
        }
    }

    /**
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    protected function _doInsert()
    {
        /**
         * A read-only row cannot be saved.
         */
        if ($this->_readOnly === true) {
            //require_once'Zend/Db/Table/Row/Exception.php';
            throw new DataObjectException('This row has been marked read-only');
        }

        /**
         * Run pre-INSERT logic
         */
        $this->_insert();

        /**
         * Execute the INSERT (this may throw an exception)
         */
        $data = array_intersect_key($this->getArrayCopy(), $this->_modifiedFields);
        $primaryKey = static::insert($data);

        /**
         * Normalize the result to an array indexed by primary key column(s).
         * The table insert() method may return a scalar.
         */
        if (is_array($primaryKey)) {
            $newPrimaryKey = $primaryKey;
        } else {
            //ZF-6167 Use tempPrimaryKey temporary to avoid that zend encoding fails.
            $tempPrimaryKey = static::$_primary;
            $newPrimaryKey = array(current($tempPrimaryKey) => $primaryKey);
        }

        /**
         * Save the new primary key value in _data.  The primary key may have
         * been generated by a sequence or auto-increment mechanism, and this
         * merge should be done before the _postInsert() method is run, so the
         * new values are available for logging, etc.
         */
        $this->setFromArray($newPrimaryKey);

        /**
         * Run post-INSERT logic
         */
        $this->_postInsert();

        /**
         * Update the _cleanData to reflect that the data has been inserted.
         */
        $this->_refresh();

        return $primaryKey;
    }

    /**
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     */
    protected function _doUpdate()
    {
        /**
         * A read-only row cannot be saved.
         */
        if ($this->_readOnly === true) {
            //require_once'Zend/Db/Table/Row/Exception.php';
            throw new DataObjectException('This row has been marked read-only');
        }

        /**
         * Get expressions for a WHERE clause
         * based on the primary key value(s).
         */
        $where = $this->_getWhereQuery(false);

        /**
         * Run pre-UPDATE logic
         */
        $this->_update();

        /**
         * Compare the data to the modified fields array to discover
         * which columns have been changed.
         */
        $diffData = array_intersect_key($this->getArrayCopy(), $this->_modifiedFields);

        /**
         * Execute the UPDATE (this may throw an exception)
         * Do this only if data values were changed.
         * Use the $diffData variable, so the UPDATE statement
         * includes SET terms only for data values that changed.
         */
        if (count($diffData) > 0) {
            static::update($diffData, $where);
        }

        /**
         * Run post-UPDATE logic.  Do this before the _refresh()
         * so the _postUpdate() function can tell the difference
         * between changed data and clean (pre-changed) data.
         */
        $this->_postUpdate();

        /**
         * Refresh the data just in case triggers in the RDBMS changed
         * any columns.  Also this resets the _cleanData.
         */
        $this->_refresh();

        /**
         * Return the primary key value(s) as an array
         * if the key is compound or a scalar if the key
         * is a scalar.
         */
        $primaryKey = $this->_getPrimaryKey(true);
        if (count($primaryKey) == 1) {
            return current($primaryKey);
        }

        return $primaryKey;
    }

    /**
     * Deletes existing rows.
     *
     * @return int The number of rows deleted.
     */
    public function remove()
    {
        /**
         * A read-only row cannot be deleted.
         */
        if ($this->_readOnly === true) {
            //require_once'Zend/Db/Table/Row/Exception.php';
            throw new DataObjectException('This row has been marked read-only');
        }

        $where = $this->_getWhereQuery();

        /**
         * Execute pre-DELETE logic
         */
        $this->_delete();
        
        /**
         * Execute the DELETE (this may throw an exception)
         */
        $result = static::delete($where);

        /**
         * Execute post-DELETE logic
         */
        $this->_postDelete();

        return $result;
    }

    /**
     * Sets all data in the row from an array.
     *
     * @param  array $data
     * @return DataObject Provides a fluent interface
     */
    public function setFromArray(array $data)
    {
    	//原来是array_intersect_key($data, $this->getArrayCopy())，现在取消参数列表检查，因此直接使用data
    	foreach ($data as $columnName => $value) {
            $this[$columnName] = $value;
        }

        return $this;
    }

    /**
     * Refreshes properties from the database.
     *
     * @return void
     */
    public function refresh()
    {
        return $this->_refresh();
    }

    /**
     * Retrieves an associative array of primary keys.
     *
     * @param bool $useDirty
     * @return array
     */
    protected function _getPrimaryKey($useDirty = true)
    {
        $primary = array_flip(static::$_primary);
        if ($useDirty) {
            $array = array_intersect_key($this->getArrayCopy(), $primary);
        } else {
            $array = array_intersect_key($this->_cleanData, $primary);
        }
        if (count($primary) != count($array)) {
            throw new DataObjectException("The specified Table '".get_called_class()."' does not have the same primary key as the Row");
        }
        return $array;
    }

    /**
     * Constructs where statement for retrieving row(s).
     *
     * @param bool $useDirty
     * @return array
     */
    protected function _getWhereQuery($useDirty = true)
    {
        $where = array();
        
        $primaryKey = $this->_getPrimaryKey($useDirty);
        //$info = static::info();
        //$metadata = $info[self::METADATA]; FIXME 这个暂时无解

        // retrieve recently updated row using primary keys
        $where = array();
        foreach ($primaryKey as $column => $value) {
            $tableName = static::$_db->quoteIdentifier(static::$_name, true);
            //$type = $metadata[$column]['DATA_TYPE'];
            $columnName = static::$_db->quoteIdentifier($column, true);
            $where[] = static::$_db->quoteInto("{$tableName}.{$columnName} = ?", $value);//, $type FIXME 这个暂时无解
        }
        return $where;
    }

    /**
     * Refreshes properties from the database.
     *
     * @return void
     */
    protected function _refresh()
    {
        $where = $this->_getWhereQuery();
        $row = static::fetchRow($where);

    	if (null === $row) {
            //require_once 'Zend/Db/Table/Row/Exception.php';
            throw new DataObjectException('Cannot refresh row as parent is missing');
        }
		
        $this->_cleanData = $row->getArrayCopy();
        $this->exchangeArray($this->_cleanData);
		$this->_modifiedFields = array();
    }

    /**
     * Allows pre-insert logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _insert()
    {
    }

    /**
     * Allows post-insert logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _postInsert()
    {
    }

    /**
     * Allows pre-update logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _update()
    {
    }

    /**
     * Allows post-update logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _postUpdate()
    {
    }

    /**
     * Allows pre-delete logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _delete()
    {
    }

    /**
     * Allows post-delete logic to be applied to row.
     * Subclasses may override this method.
     *
     * @return void
     */
    protected function _postDelete()
    {
    }
    
    /**
     * 批量更新计数器
     * 
     * @param string|array $columns
     * @param string|array $primaryKey
     * @return int 
     */
    public static function refreshCounters(){
		$args = func_get_args();
		$columns = array_shift($args);
		
		$data = array();
		foreach((array) $columns as $col){
			$method = 'select' . implode('', array_map('ucfirst', explode('_', $col))) . 'Count';
			$select = static::$method();
			$data[$col] = new Expr('('.$select->assemble().')');
		}
		
		$where = array();
		foreach($args as $index => $value)
			if (is_array($value)){
				if (count($value) === 0)
					return 0;
				$where[] = static::$_db->quoteInto(static::$_primary[$index] . ' in (?)', $value);
			}
			elseif ($value !== null)
				$where[] = static::$_db->quoteInto(static::$_primary[$index] . ' = ?', $value);
		
		return static::update($data, $where);
	}
}
