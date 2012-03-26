<?php
namespace MyPDO;
/**
 * 原封不动地复制了Zend_Db
 * TODO 以后取消它
 */

/**
 * Use the PROFILER constant in the config of a Zend_Db_Adapter.
 */
const PROFILER = 'profiler';

/**
 * Use the CASE_FOLDING constant in the config of a Zend_Db_Adapter.
 */
const CASE_FOLDING = 'caseFolding';

/**
 * Use the FETCH_MODE constant in the config of a Zend_Db_Adapter.
 */
const FETCH_MODE = 'fetchMode';

/**
 * Use the AUTO_QUOTE_IDENTIFIERS constant in the config of a Zend_Db_Adapter.
 */
const AUTO_QUOTE_IDENTIFIERS = 'autoQuoteIdentifiers';

/**
 * Use the ALLOW_SERIALIZATION constant in the config of a Zend_Db_Adapter.
 */
const ALLOW_SERIALIZATION = 'allowSerialization';

/**
 * Use the AUTO_RECONNECT_ON_UNSERIALIZE constant in the config of a Zend_Db_Adapter.
 */
const AUTO_RECONNECT_ON_UNSERIALIZE = 'autoReconnectOnUnserialize';

/**
 * Use the INT_TYPE, BIGINT_TYPE, and FLOAT_TYPE with the quote() method.
 */
const INT_TYPE = 0;
const BIGINT_TYPE = 1;
const FLOAT_TYPE  = 2;

const FETCH_DATAOBJECT = 'fetchDataObject';
const FETCH_CLASSFUNC = 'fetchClassFunc';
