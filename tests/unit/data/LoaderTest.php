<?php
namespace tests\unit\data;

use kungphu\data\Loader;
use kungphu\data\Permutator;

class LoaderTest extends \PHPUnit_Framework_TestCase{

    /**
     * @test
     */
    function construct(){
        $permutator = $this->getMock('kungphu\data\Permutator');
        try{ new Loader([]); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ new Loader(['foo']); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ new Loader(['foo' => []]); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ new Loader(['foo' => $permutator, 'trash']); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ new Loader(['foo' => [$permutator, 'trash']]); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ new Loader(['foo' => [$permutator], [$permutator, 'trash']]); $this->fail("Expected exception.");}catch (\LogicException $e){}
        try{ new Loader([[$permutator]]); $this->fail("Expected exception."); }catch (\LogicException $e){}
        $permutator->expects($this->exactly(2))
            ->method('loadClosure')
            ->will($this->returnValue(function(){return true; } ))
            ;
        new Loader([[$permutator]]);
        try{ new Loader([[$permutator, new \stdClass()]]); }catch (\LogicException $e){}
    }

    /**
     * @test
     */
    function load(){
        $foo = uniqid();
        $bar = uniqid();
        
        $p0 = $this->getMock('kungphu\data\Permutator');
        $p0->expects($this->exactly(2))
            ->method('loadClosure')
            ->with()
            ->will($this->returnValue(function() use ($foo) { return [$foo, func_get_args()]; } ));
        ;
        $p1 = $this->getMock('kungphu\data\Permutator');
        $p1->expects($this->exactly(2))
            ->method('loadClosure')
            ->with()
            ->will($this->returnValue(function() use ($bar) { return [$bar, func_get_args()]; } ));
        ;
        
        $sut = new Loader(['a' => [$p0, $p1]]);
        
        try{ $sut->load('foo'); $this->fail("Expected exception."); }catch (\OutOfBoundsException $e){}
        try{ $sut->load(null); $this->fail("Expected exception."); }catch (\LogicException $e){}
        try{ $sut->load(false); $this->fail("Expected exception."); }catch (\LogicException $e){}

        $actual = $sut->load('a');
        $this->assertEquals(
            [
                [$foo, [$sut, 'a', 0, $p0]],
                [$bar, [$sut, 'a', 1, $p1]],
            ],
            $actual
        );
    }
} 