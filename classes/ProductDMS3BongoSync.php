<?php

/*
 * Class developed by Marc Queralt i Bassa
 * marc@DeMomentSomTres.com
 */

/**
 * @since 1.0
 */
class ProductDMS3BongoSync extends ObjectModel {

    const STATUS_BAD_PRODUCT_ID = 0;
    const STATUS_UNPROCESSED = 1;
    const STATUS_PROCESSED = 2;

    /**
     * @var integer product ID
     * */
    public $id_product;

    /**
     * @var integer status code
     * */
    public $statusCode;

    /**
     * @var integer previous status code
     * */
    public $previousStatus;

    /** @var string Object creation date */
    public $date_add;

    /** @var string Object last modification date */
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'dms3bongo_product',
        'primary' => 'id_product',
        'fields' => array(
            'id_product' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => true
            ),
            'statusCode' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => true
            ),
            'previousStatus' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isUnsignedInt',
                'required' => false
            ),
            'date_add' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat'
            ),
            'date_upd' => array(
                'type' => self::TYPE_DATE,
                'validate' => 'isDateFormat'
            ),
        ),
    );

}
