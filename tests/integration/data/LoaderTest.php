<?php
namespace tests\integration\data;

use kungphu\data\Loader;
use kungphu\data\Permutator;
use tests\Bootstrap;

class LoaderTest extends \PHPUnit_Framework_TestCase{

    /**
     * @var Bootstrap
     */
    protected $_bootstrap;
    //some weird reserved db names
    protected $_table0 = 'trigger'; 
    protected $_table1 = 'procedure';
    protected $_col0 = 'foo';
    protected $_col1 = 'bar';
    protected $_file0;
    protected $_file1;
    
    protected function setUp(){
        $b = Bootstrap::getInstance();
        $b->pdo_mysqli->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS `$this->_table0` (
              `a` varchar(50) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
            
            CREATE TABLE IF NOT EXISTS `$this->_table1` (
              `b` varchar(50) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
SQL
        );
        $this->_file0 = sys_get_temp_dir() . '/' . uniqid();
        $this->_file1 = sys_get_temp_dir() . '/' . uniqid();
        file_put_contents($this->_file0, '');
        file_put_contents($this->_file1, '');
        $this->_bootstrap = $b;
        parent::setUp();
    }
    
    protected function tearDown(){
        $this->_bootstrap->pdo_mysqli->exec("DROP TABLE `$this->_table0`");
        $this->_bootstrap->pdo_mysqli->exec("DROP TABLE `$this->_table1`");
        $this->_bootstrap->mongo->selectCollection($this->_col0)->drop();
        $this->_bootstrap->mongo->selectCollection($this->_col1)->drop();
        @unlink($this->_file0);
        @unlink($this->_file1);
        parent::tearDown();
    }

    /**
     * Asserts main integration with pdo mysql and mysqli.
     * @test
     */
    function mysql(){
        $pdo = $this->_bootstrap->pdo_mysqli;
        $mysqli = $this->_bootstrap->mysqli;
        
        $p0 = new Permutator();
        $p0->a($p0::cycle(['a0', 'a1']));
        $p0->loadClosure($p0::load_mysql($pdo, 'trigger', $this->_file0));
        
        $p1 = new Permutator();
        $p1->b($p1::cycle(['b0', 'b1']));
        $p1->loadClosure($p0::load_mysql($mysqli, 'procedure', $this->_file1));
        
        //db empty
        $st = $pdo->query('SELECT * from `trigger`');
        $this->assertEmpty($st->fetchAll());
        $st = $pdo->query('SELECT * from `procedure`');
        $this->assertEmpty($st->fetchAll());
        
        //load
        $loader = new Loader([[$p0, $p1]]);
        $loader->load(0);
        $this->assertEquals("a0\na1\n", file_get_contents($this->_file0));
        $this->assertEquals("b0\nb1\n", file_get_contents($this->_file1));

        //db after
        $st = $pdo->query('SELECT * from `trigger`');
        $this->assertEquals([['a' => 'a0'], ['a' => 'a1']], $st->fetchAll());
        $st = $pdo->query('SELECT * from `procedure`');
        $this->assertEquals([['b' => 'b0'], ['b' => 'b1']], $st->fetchAll());
        
        //force no optimization 
        $loader->load(0);

        //db after
        $st = $pdo->query('SELECT * from `trigger`');
        $this->assertEquals(
            [['a' => 'a0'], ['a' => 'a1'], ['a' => 'a0'], ['a' => 'a1']],
            $st->fetchAll()
        );
        $st = $pdo->query('SELECT * from `procedure`');
        $this->assertEquals(
            [['b' => 'b0'], ['b' => 'b1'], ['b' => 'b0'], ['b' => 'b1']],
            $st->fetchAll()
        );

        //file overwritten
        $this->assertEquals("a0\na1\n", file_get_contents($this->_file0));
        $this->assertEquals("b0\nb1\n", file_get_contents($this->_file1));
    }

    /**
     * Asserts main integration with mongodb.
     * 
     * @test
     */
    function mongo(){
        $mongo = Bootstrap::getInstance()->mongo;
        $name = $mongo->execute('db');
        $name = $name['retval']['_name'];
        $conf0 = ['db' => $name, 'collection' => $this->_col0];
        $conf1 = ['db' => $name, 'collection' => $this->_col1];
        $filter = function(&$data){
            $data = iterator_to_array($data, false);
            foreach ($data as &$d) {
                unset($d['_id']);
            }
            return $data;
        };
        
        $p0 = new Permutator();
        $p0->a($p0::cycle(['a0', 'a1']));
        $p0->loadClosure($p0::load_mongo($conf0, $this->_file0));

        $p1 = new Permutator();
        $p1->b($p1::cycle(['b0', 'b1']));
        $p1->loadClosure($p0::load_mongo($conf1, $this->_file1));

        //check collections and files are empty
        $actual = $mongo->selectCollection($conf0['collection'])->find([]);
        $this->assertEmpty($filter($actual));
        $actual = $mongo->selectCollection($conf1['collection'])->find([]);
        $this->assertEmpty($filter($actual));
        $this->assertEquals("", file_get_contents($this->_file0));
        $this->assertEquals("", file_get_contents($this->_file1));
        
        $loader = new Loader([[$p0, $p1]]);
        $loader->load(0);
        
        //db after
        $actual = $mongo->selectCollection($conf0['collection'])->find([]);
        $this->assertEquals([['a' => 'a0'],['a'=>'a1']], $filter($actual));
        $actual = $mongo->selectCollection($conf1['collection'])->find([]);
        $this->assertEquals([['b' => 'b0'],['b'=>'b1']], $filter($actual));
        //files after
        $this->assertEquals('[{"a":"a0"},{"a":"a1"}]', file_get_contents($this->_file0));
        $this->assertEquals('[{"b":"b0"},{"b":"b1"}]', file_get_contents($this->_file1));
        
        $loader->load(0);

        //db after
        $actual = $mongo->selectCollection($conf0['collection'])->find([]);
        $this->assertEquals([['a' => 'a0'],['a'=>'a1'],['a' => 'a0'],['a'=>'a1']], $filter($actual));
        $actual = $mongo->selectCollection($conf1['collection'])->find([]);
        $this->assertEquals([['b' => 'b0'],['b'=>'b1'],['b' => 'b0'],['b'=>'b1']], $filter($actual));
        //files after
        $this->assertEquals('[{"a":"a0"},{"a":"a1"}]', file_get_contents($this->_file0));
        $this->assertEquals('[{"b":"b0"},{"b":"b1"}]', file_get_contents($this->_file1));

        //test nested data
        $obj = new \stdClass();
        $obj->foo = 'foo';
        //@todo add associative array
        $p0 = new Permutator();
        $p0->foo($obj);
        $p0->loadClosure($p0::load_mongo($conf0, $this->_file0));
        
        $loader = new Loader([[$p0]]);
        $loader->load(0, false);
        
        $actual = $mongo->selectCollection($conf0['collection'])->find(['foo' => ['$exists' => true]]);
        $actual = $filter($actual);
        $this->assertEquals(
            [['foo' => ['foo' => 'foo']]],
            $actual
        );
        $this->assertEquals('[{"foo":{"foo":"foo"}}]', file_get_contents($this->_file0));
    }
} 