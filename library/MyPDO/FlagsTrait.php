<?php
namespace MyPDO;

trait FlagsTrait{
	protected $_flags;
	
	/**
	 * 获得标志位的值
	 *
	 * @param string $name
	 * @return bool
	 */
	public function getFlag($name){
		if ($this->_flags === null)
			$this->_flags = ($this['flags'] == '') ? array() : array_flip(explode(',', $this['flags']));
		
		return isset($this->_flags[$name]);
	}
	
	/**
	 * 设置标志位的值
	 *
	 * @param string $name
	 * @param bool $bool
	 * @return $this
	 */
	public function setFlag($name, $bool){
		if ($this->_flags === null)
			$this->_flags = ($this['flags'] == '') ? array() : array_flip(explode(',', $this['flags']));
		
		if ($bool){
			if (!isset($this->_flags[$name]))
				$this->_flags[$name] = true;
		}
		else{
			if (isset($this->_flags[$name]))
				unset($this->_flags[$name]);
		}
		
		$this['flags'] = implode(',', array_keys($this->_flags));
		return $this;
	}
	
	/**
	 * 设置一组标志位
	 *
	 * @param array $data
	 * @return User
	 */
	public function setFlags($data){
		if ($this->_flags === null)
			$this->_flags = ($this['flags'] == '') ? array() : array_flip(explode(',', $this['flags']));
		
		foreach ($data as $name => $bool)
			if ($bool){
				if (!isset($this->_flags[$name]))
					$this->_flags[$name] = true;
			}
			else{
				if (isset($this->_flags[$name]))
					unset($this->_flags[$name]);
			}
		
		$this['flags'] = implode(',', array_keys($this->_flags));
		return $this;
	}
}
