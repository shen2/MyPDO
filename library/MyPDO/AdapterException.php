<?php
class MyPDO_AdapterException extends Exception
{
    protected $_chainedException = null;

    public function __construct($message = '', $code = 0, Exception $e = null)
    {
        if ($e && (0 === $code)) {
            $code = $e->getCode();
        }
        parent::__construct($message, $code, $e);
    }

    public function hasChainedException()
    {
        return ($this->_previous !== null);
    }

    public function getChainedException()
    {
        return $this->getPrevious();
    }

}