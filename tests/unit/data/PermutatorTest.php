<?php
namespace tests\unit\data;

use kungphu\data\Permutator;
use tests\Bootstrap;

class PermutatorTest extends \PHPUnit_Framework_TestCase{

    protected $_file;

    protected function setUp(){
        $this->_file = sys_get_temp_dir() . '/' . uniqid();
        file_put_contents($this->_file, '');
        Bootstrap::getInstance()->mongo->drop();
        parent::setUp();
    }

    protected function tearDown(){
        Bootstrap::getInstance()->mongo->drop();
        @unlink($this->_file);
        parent::tearDown();
    }

    /**
     * @test
     */
    function rstr(){
        $out = [];
        for ($i = 0; $i < 5; $i++){
            $actual = call_user_func(Permutator::rstr());
            $this->assertTrue($actual[1]);
            $out[] = $actual[0];
            if ($i > 0){
                $this->assertNotEquals($out[$i-1], $out[$i]);
            }
        }
    }
    
    /**
     * @test
     */
    function rnum(){
        $out = [];
        for ($i = 0; $i < 5; $i++){
            $actual = call_user_func(Permutator::rnum(0, 10000));
            $this->assertTrue($actual[1]);
            $this->assertTrue($actual[0] >= 0 && $actual[0] <= 10000);
            $out[] = $actual[0];
            if ($i > 0){
                $this->assertNotEquals($out[$i-1], $out[$i]);
            }
        }
    }
    
    /**
     * @test
     */
    function rdate(){
        $d0 = new \DateTime();
        $d1 = new \DateTime('+1 day');
        $out = [];
        for ($i = 0; $i < 5; $i++){
            $actual = call_user_func(Permutator::rdate($d0, $d1));
            $this->assertTrue($actual[1]);
            $this->assertTrue($actual[0] >= $d0 && $actual[0] <= $d1);
            $out[] = $actual[0]->getTimestamp();
            if ($i > 0){
                $this->assertNotEquals($out[$i-1], $out[$i]);
            }
        }
    }

    /**
     * @test
     */
    function rvalue(){
        $arr = ['foo', 'bar', 'baz', 'bat'];
        for ($i = 0; $i < 5; $i++){
            $actual = call_user_func(Permutator::rvalue($arr));
            $this->assertTrue($actual[1]);
            $this->assertTrue(in_array($actual[0], $arr));
        }
    }

    /**
     * @depends permutate
     * @test
     */
    function main(){
        $arr = [1,2,3];
        $p = new Permutator();
        $p->a($p::rnum(0, 10));
        $p->b($p::rstr(0, 10));
        $p->c($p::rvalue(range(0,10)));
        $p->d(function(){ return mt_rand(0, 100); });
        $p->e($p::cycle($arr)); //get the thing cycling

        $actual = $p->getPermutations();

        $data = [
            ['a'],
            ['b'],
            ['c'],
            ['d'],
            ['e'],
        ];

        foreach ($data as $d){
            list($prop) = $d;
            //assert that no random value happens count times
            $values = array_column($actual, $prop);
            $this->assertTrue(!in_array(count($arr), array_count_values($values)));
        }
    }

    /**
     * @test
     */
    function cycle(){
        $sut = new Permutator();
        try{ $sut::cycle([]); $this->fail("Expected exception.");}catch (\LogicException $e){}
        $func = $sut->cycle([1, 2]);
        $this->assertEquals([1, false, false], call_user_func($func));
        $this->assertEquals([2, false, true], call_user_func($func));
        $this->assertEquals([1, false, false], call_user_func($func));
        $this->assertEquals([2, false, true], call_user_func($func));
    }

    /**
     * @test
     */
    function date_cycle(){
        $sut = new Permutator();
        try{ $sut::date_cycle(new \DateTime(), new \DateTime(), 'bogus'); $this->fail("Expected exception.");}catch (\LogicException $e){}

        //day
        $assert = function($expect, $actual){
            $format = 'Y-m-d H:i';
            $this->assertEquals($expect[0]->format($format), $actual[0]->format($format));
            $this->assertEquals($expect[1], $actual[1]);
        };
        $sut = new Permutator();
        $func = $sut->date_cycle(new \DateTime('-1 days'), new \DateTime('+1 days'), '+1 day');
        $assert([new \DateTime('-1 days'), false, false], call_user_func($func));
        $assert([new \DateTime(), false, false], call_user_func($func));
        $assert([new \DateTime('+1 days'), false, true], call_user_func($func));

        //hour
        $sut = new Permutator();
        $t0 = new \DateTime('today');
        $t0->setTime(0, 0, 0);
        $t1 = new \DateTime('today');
        $t1->setTime(3, 0, 0);
        $func = $sut->date_cycle($t0, $t1, '+1 hour');
        $assert([new \DateTime('today 00:00:00'), false, false], call_user_func($func));
        $assert([new \DateTime('today 01:00:00'), false, false], call_user_func($func));
        $assert([new \DateTime('today 02:00:00'), false, false], call_user_func($func));
        $assert([new \DateTime('today 03:00:00'), false, true], call_user_func($func));
    }

    /**
     * @test
     */
    function export_csv(){
        $sut = new Permutator();
        try{ $sut::export_csv([], $this->_file); $this->fail("Expected exception.");}catch (\LogicException $e){}

        $data = [
            ["\n", [[false]], false],
            [",\n", [[false, false]], false],
            [",\n", [[null, null]], false],
            [",1\n", [[false, 1]], false],
            [",1,1.1\n", [[false, 1, 1.1]], false],
            [",1,1.1,foo\n", [[false, 1, 1.1, 'foo']], false],
            [",1,1.1,foo,\"bar \"\"a\"\n", [[false, 1, 1.1, 'foo', 'bar "a']], false],
            [",1,1.1,foo,\"bar 'a\"\n", [[false, 1, 1.1, 'foo', "bar 'a"]], false],
            [",1,1.1,foo,\"bar 'a\n\"\n", [[false, 1, 1.1, 'foo', "bar 'a\n"]], false],
            [
                ",1,1.1,foo\n,1,1.1,foo\n",
                [
                    [false, 1, 1.1, 'foo'],
                    [false, 1, 1.1, 'foo'],
                ], 
                false
            ],
            [
                "a,b\n1,2\n3,4\n",
                [['a' => 1, 'b' => 2], ['a' => 3, 'b' => 4]],
                true
            ]
        ];
        foreach ($data as $d) {
            list($expected, $input, $bool) = $d;
            $sut::export_csv($input, $this->_file, $bool);
            $actual = file_get_contents($this->_file);
            $this->assertEquals($expected, $actual);
        }
    }

    /**
     * @test
     */
    function export_json(){
        $sut = new Permutator();
        try{ $sut::export_json([], $this->_file); $this->fail("Expected exception.");}catch (\LogicException $e){}
        
        $data = [
            ["[[false]]", [[false]]],
            ["[[false,false]]", [[false, false]]],
            ["[[null,null]]", [[null, null]]],
            ["[[false,1]]", [[false, 1]]],
            ["[[false,1,1.1]]", [[false, 1, 1.1]]],
            ["[[false,1,1.1,\"foo\"]]", [[false, 1, 1.1, 'foo']]],
            ["[[false,1,1.1,\"foo\",\"bar \\\"a\"]]", [[false, 1, 1.1, 'foo', 'bar "a']]],
            ["[[false,1,1.1,\"foo\",\"bar 'a\"]]", [[false, 1, 1.1, 'foo', "bar 'a"]]],
            [
                "[[false,1,1.1,\"foo\"],[false,1,1.1,\"foo\"]]",
                [
                    [false, 1, 1.1, 'foo'],
                    [false, 1, 1.1, 'foo'],
                ]
            ],
        ];
        foreach ($data as $d) {
            list($expected, $input) = $d;
            $sut::export_json($input, $this->_file);
            $actual = file_get_contents($this->_file);
            $this->assertEquals($expected, $actual);
        }
    }
    
    /**
     * @depends export_csv
     * @test
     */
    function load_mysql(){
        $sut = new Permutator();
        try{$sut::load_mysql($this->getMock('stdClass'), '', $this->_file); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{$sut::load_mysql($this->getMock('tests\MockPDO'), '', $this->_file); $this->fail("Expected exception."); }catch (\LogicException $e){}

        $table = 'trigger';
        $with = <<<SQL
                LOAD DATA LOCAL INFILE '$this->_file'
                INTO TABLE `$table`
                FIELDS TERMINATED BY ','
                OPTIONALLY ENCLOSED BY '"'
                LINES TERMINATED BY '\n'
SQL;
        
        $loader = $this->getMock('kungphu\data\Loader', [], [], '', false);
        $sut = new Permutator();
        $sut->a($sut::cycle([1,2]));
        $this->assertEmpty(file_get_contents($this->_file));
        
        $db = $this->getMock('tests\MockPDO');
        $db->expects($this->once())
            ->method('exec')
            ->with($with)
            ;
        $cb = $sut::load_mysql($db, $table, $this->_file, false);
        $actual = $cb($loader, 'foo', 'bar', $sut, false);
        $this->assertEquals(['data' => [['a' => 1],['a' => 2]], 'query' => null], $actual);
        $this->assertEquals("1\n2\n", file_get_contents($this->_file));
    }
    
    /**
     * @depends export_csv
     * @test
     */
    function load_mongo(){
        $sut = new Permutator();
        $mongo = Bootstrap::getInstance()->mongo;
        $name = $mongo->execute('db');
        $name = $name['retval']['_name'];
        try{ $sut::load_mongo(['db' => '', 'collection' => ''], null); $this->fail("Expected exception."); } catch (\LogicException $e){}
        try{ $sut::load_mongo(['db' => 'foo', 'collection' => ''], null); $this->fail("Expected exception."); } catch (\LogicException $e){}
        try{ $sut::load_mongo(['db' => 'foo', 'collection' => 'bar'], null); $this->fail("Expected exception."); } catch (\LogicException $e){}

        $loader = $this->getMock('kungphu\data\Loader', [], [], '', false);
        $sut->a($sut::cycle([1,2]));
        $this->assertEmpty(file_get_contents($this->_file));
        
        $cb = $sut::load_mongo(
            ['db' => $name, 'collection' => 'bar'],
            $this->_file,
            false
        );
        $actual = $cb($loader, 'foo', 'bar', $sut);
        $this->assertEquals([['a' => 1], ['a' => 2]], $actual['data']);
        $this->assertTrue(count($actual['cmd']) == 2);
        $this->assertEquals('[{"a":1},{"a":2}]', file_get_contents($this->_file));
        $actual = $mongo->bar->find([]);
        $actual = iterator_to_array($actual, false);
        foreach ($actual as &$a) {
            unset($a['_id']);
        }
        $this->assertEquals(
            [['a' => 1], ['a' => 2]],
            $actual
        );
    }

    /**
     * @test
     */
    function permutate(){
        $perms = [];
        $combos = [
            'a' => [1,2],
            'b' => ['b0' => ['b00', 'b01'], 'b1' => ['b10', 'b11']],
            'c' => ['c0', 'c1']
        ];

        $all = [];
        $a = function ($it, $keys = [], &$prevKey = '') use (&$a, &$all){
            while ($it->valid()){
                if ($it->hasChildren()){
                    $keys[] = $it->key();
                    $a($it->getChildren(), $keys);
                } else {
                    $curKey = implode('.', $keys);
                    
                    //drill down to prop and generate a chain with value at end
                    $cnt = count($keys);
                    $item = [];
                    $push = &$item;
                    for ($i = 0; $i < $cnt; $i++){
                        $push[$keys[$i]] = [];
                        $push = &$push[$keys[$i]];
                    }
                    $push = $it->current();
                    
                    $all[] = $item;
                    
                    //same property being alternated
                    if ($curKey == $prevKey){
                        
                    }
                    echo "$curKey vs $prevKey" . ':' . $it->current() . PHP_EOL;
                    $prevKey = $curKey;
                }
                $it->next();
            }
        };

        $iterator = new \RecursiveArrayIterator($combos);
        echo PHP_EOL;
        iterator_apply($iterator, $a, [$iterator]);
        die();

        $b = function($val, $keys = [], array &$all = [[]], $build = '') use (&$b){
            if (!is_array($val)){
                //trash last key
                array_pop($keys);
                
                //rewind to prop
                $item = [];
                $ref = &$item;
                foreach ($keys as $k){
                    $ref[$k] = isset($ref[$k]) ? $ref[$k] : [];
                    $ref = &$ref[$k];
                }
                //set prop
                $ref = $val;
                
                $all[$build] = $item;
                return;
            }
            
            foreach ($val as $k => $v){
                $copy = $keys;
                $copy[] = $k;
                $build .= '.' . $k;
                $b($v, $copy, $all, $build);
            }
            
            return $all;
        };
        
        $foo = $b($combos);
        $this->assertEquals([], Permutator::permutate([]));
        
        try{    
            Permutator::permutate([[]]);
            $this->fail('Exception expected.');
        }catch (\LogicException $e){}
        try{
            Permutator::permutate([[1], []]);
            $this->fail('Exception expected.');
        }catch (\LogicException $e){}
        
        $str = uniqid();
        $d = new \DateTime();
        $foo = function (){ return 'foo'; };
        $arr = [
            'a' => 1, 'b' => 1.5, 'c' => $str,
            'd' => false, 'e' => null, 'f' => $d, 'g' => $foo,
            'h' => [1,2,3]
        ];
        $arr['nested_array'] = $arr;
        $arr['nested_object'] = (object) $arr;
        $obj = (object) $arr;
        
        $data = [
            //single value invocations
            [ [[1]], [[1]] ],
            [ [[1.5]], [[1.5]] ],
            [ [[$str]], [[$str]] ],
            [ [[false]], [[false]] ],
            [ [[true]], [[true]] ],
            [ [[null]], [[null]] ],
            
            [ [[1, 1]], [[1], [1]] ],
            [ [[1, 1], [1, 2]], [[1], [1,2]] ],
            [ [[1, 1], [1, 2], [2, 1], [2, 2]], [[1,2], [1,2]] ],
            [
                [
                    [0, 0, 0], 
                    [0, 0, 1], 
                    [0, 0, 2], 
                    [0, 1, 0], 
                    [0, 1, 1], 
                    [0, 1, 2], 
                    [1, 0, 0], 
                    [1, 0, 1], 
                    [1, 0, 2], 
                    [1, 1, 0], 
                    [1, 1, 1], 
                    [1, 1, 2], 
                ],
                [[0,1], [0,1], [0,1,2]]
            ],
            [
                //string - string
                [[$str, $str]], [[$str], [$str]]
            ],
            [
                //string - number
                [
                    [$str, $str],
                    [$str, 1],
                    [1, $str],
                    [1, 1],
                ],
                [[$str, 1], [$str, 1]]
            ],
            [
                //string - date
                [
                    [$str, $str],
                    [$str, $d],
                    [$d, $str],
                    [$d, $d],
                ],
                [[$str, $d], [$str, $d]]
            ]
        ];
        foreach ($data as $i => $d) {
            list($expected, $combos) = $d;
            $actual = Permutator::permutate($combos);
            $this->assertEquals($expected, $actual, "Failed for set $i: " . print_r($combos, true));
        }
    }

    /**
     * @test
     */
    function _call(){
        $sut = new Permutator();
        try{ $sut->n(1,2); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ $sut->a(function(){ return 'bs'; }); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ $sut->a(function(){ return []; }); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ $sut->a(function(){ return [null]; }); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ $sut->a(function(){ return [null, null]; }); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ $sut->a(function(){ return [null, null, null]; }); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ $sut->a(function(){ return ['a', null, null]; }); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ $sut->a(function(){ return ['a', false, null]; }); $this->fail("Expected exception.");}catch (\LogicException $e){}
        $sut->a(function($name){
            $this->assertEquals('a', $name);
            return ['a', false, false];
        });
        $sut->b(function($name){
            $this->assertEquals('b', $name);
            return ['a', true, true];
        });
    }

    /**
     * @test
     */
    function getPermutations(){
        $f = function ($items, &$perms) use (&$f){
            foreach ($items as $i) {
                $f($i, $perms);
            }
        };

        $perms = [[]];
        $a = $f(
            [[1,2], ['b' => ['b00', 'b01']]],
            $perms
        );
        
        $sut = new Permutator();
        $this->assertEquals([], $sut->getPermutations());
        
        $sut = new Permutator();
        $sut->a(1);
        $this->assertEquals([['a' => 1]], $sut->getPermutations());
        
        $sut = new Permutator();
        $sut->a(1)->b(2);
        $this->assertEquals([['a' => 1, 'b' => 2]], $sut->getPermutations());
        
        $sut = new Permutator();
        $sut->a([1]);
        $this->assertEquals([['a' => [1]]], $sut->getPermutations());
        
        $t0 = new \DateTime();
        $sut = new Permutator();
        $sut->a($t0);
        $this->assertEquals([['a' => $t0]], $sut->getPermutations());
        
        //permutate cycle
        $sut = new Permutator();
        $sut->a($sut::cycle([0,1]));
        $this->assertEquals([['a' => 0], ['a' => 1]], $sut->getPermutations());

        $sut = new Permutator();
        $sut
            ->a($sut::cycle([0,1]))
            ->b($sut::cycle([0,1]))
            ->c($sut::cycle([0,1,2]))
        ;
        $actual = $sut->getPermutations();
        $expected = [
            ['a' => 0, 'b' => 0, 'c' => 0],
            ['a' => 0, 'b' => 0, 'c' => 1],
            ['a' => 0, 'b' => 0, 'c' => 2],
            ['a' => 0, 'b' => 1, 'c' => 0],
            ['a' => 0, 'b' => 1, 'c' => 1],
            ['a' => 0, 'b' => 1, 'c' => 2],
            ['a' => 1, 'b' => 0, 'c' => 0],
            ['a' => 1, 'b' => 0, 'c' => 1],
            ['a' => 1, 'b' => 0, 'c' => 2],
            ['a' => 1, 'b' => 1, 'c' => 0],
            ['a' => 1, 'b' => 1, 'c' => 1],
            ['a' => 1, 'b' => 1, 'c' => 2]
        ];
        $this->assertEquals($expected, $actual);

        //permutate datecycle
        $t0 = new \DateTime('today');
        $t0->setTime(0, 0, 0);
        $t1 = new \DateTime('today');
        $t1->setTime(3, 0, 0);
        $sut = new Permutator();
        $sut->a($sut::date_cycle($t0, $t1, '+1 hour'));
        $actual = $sut->getPermutations();
        $expected = [
            ['a' => new \DateTime('today 00:00:00')],
            ['a' => new \DateTime('today 01:00:00')],
            ['a' => new \DateTime('today 02:00:00')],
            ['a' => new \DateTime('today 03:00:00')],
        ];
        $this->assertEquals($expected, $actual);
        
        //permutate constants
        $obj = new \stdClass();
        $obj->foo = 'foo';
        $sut = new Permutator();
        $sut->a(false)
            ->b(true)
            ->c(null)
            ->d(1)
            ->e(1.1)
            ->f(-1.1)
            ->g('string')
            ->h([])
            ->i(['a' => []])
            ->j(['a' => ['a' => 1]])
            ->k($obj)
            ;
        $actual = $sut->getPermutations();
        $this->assertEquals(
            [
                'a' => false, 'b'=>true, 'c'=>null, 'd'=>1, 'e' => 1.1, 
                'f' => -1.1, 'g' => 'string', 'h' => [], 'i' => ['a' => []],
                'j' => ['a' => ['a' => 1]], 'k' => $obj
            ],
            $actual
        );
    }

    /**
     * @test
     */
    function loadClosure(){
        $sut = new Permutator();
        try{ $sut->loadClosure(1); $this->fail("Expected exception.");}catch (\LogicException $e){}
        $sut->loadClosure(function(){ return 'foo'; });
        $func = $sut->loadClosure();
        $this->assertEquals('foo', $func());
    }

    /**
     * @depends getPermutations
     * @test
     */
    function reset(){
        $sut = new Permutator();
        $sut->a(1);
        $this->assertEquals([['a'=>1]], $sut->getPermutations());
        $sut->reset();
        $this->assertEmpty($sut->getPermutations());
    }
}