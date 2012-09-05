<?php
namespace MyPDO;

trait CountersTrait{    
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
	
	public function inc($column, $inc = 1){
		$this[$column] = new Expr($column . ($inc >= 0 ? '+' : '') . $inc);
		
		return $this;
	}
}
