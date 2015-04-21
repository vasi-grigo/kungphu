<?php
namespace tests;

/**
 * Singleton Initializer for test-cases.
 */
Bootstrap::getInstance();
class Bootstrap {
    /**
     * @var \PDO
     */
    public $pdo_mysqli;
    /**
     * @var \mysqli
     */
    public $mysqli;
    /**
     * @var \MongoDB
     */
    public $mongo;
    
    private static $_inst;

    private function __construct(){}

    /**
     * @return Bootstrap
     */
    public static function getInstance(){
        if(!self::$_inst){
            // Enable all errors
            error_reporting(- 1);
            ini_set('display_startup_errors', 1);
            ini_set('display_errors', 1);
            
            $config = require_once __DIR__ . '/../config/test.php';
            self::$_inst = new Bootstrap();
            self::$_inst->pdo_mysqli = $config['pdo_mysqli'];
            self::$_inst->mysqli = $config['mysqli'];
            self::$_inst->mongo = $config['mongo'];
        }
        return self::$_inst;
    }
}
class MockPDO extends \PDO{
    function __construct(){}
    function execute(){}
    function fetchAll(){}
}