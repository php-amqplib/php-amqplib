<?php

namespace PhpAmqpLib\Wire;

/**
 * AMQPCollectionInterface Interface
 */
interface AMQPCollectionInterface
{
    /**
     * Get the type
     *
     * @return int
     */
    public function getType();

    /**
     * Get the native data
     *
     * @return array
     */
    public function getNativeData();

    /**
     * Get the protocol
     *
     * @return string
     */
    public static function getProtocol();

    /**
     * Check if the collection protocol equals the given protocol
     *
     * @param string $proto
     * @return bool
     */
    public static function isProtocol($proto);

    /**
     * Get the supported data types
     *
     * @return array  [dataTypeConstant => dataTypeSymbol]
     */
    public static function getSupportedDataTypes();

    /**
     * Check if a datatype is supported
     *
     * @param string $type
     * @param bool $return Whether to return or raise AMQPOutOfRangeException
     * @return boolean
     */
    public static function checkDataTypeIsSupported($type, $return = true);

    /**
     * Get the symbol for a data type
     *
     * @param int $type
     * @return string
     */
    public static function getSymbolForDataType($type);

    /**
     * Get the data type for a symobl
     *
     * @param string $symbol
     * @return integer
     */
    public static function getDataTypeForSymbol($symbol);

    /**
     * Get the current value
     *
     * @return mixed
     */
    public function current();

    /**
     * Get the key
     *
     * @return mixed
     */
    public function key();

    /**
     * Get the next
     *
     * @return mixed
     */
    public function next();

    /**
     * Rewind the dataset to it's beginning
     *
     * @return mixed
     */
    public function rewind();

    /**
     * Indicates whether the data is valid or not
     *
     * @return mixed
     */
    public function valid();
}
