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
        $bool = true;

        //unkeyed map
        $permutator = $this->getMock('kungphu\data\Permutator');
        $permutator->expects($this->exactly(2))
            ->method('loadClosure')
            ->with()
            ->will($this->returnValue(function(Loader $l, Permutator $p, $bool) use (&$assert){ $assert = true; }))
        ;
        $sut = new Loader([[$permutator]]);
        $assert = false;
        $sut->load(0, $bool);
        $this->assertTrue($assert);
        
        //keyed map
        $permutator = $this->getMock('kungphu\data\Permutator');
        $assert = false;
        $permutator->expects($this->exactly(2))
            ->method('loadClosure')
            ->with()
            ->will($this->returnValue(function(Loader $l, Permutator $p, $bool) use (&$assert){ $assert = true; }))
        ;
        $sut = new Loader(['a' => [$permutator]]);
        $sut->load('a', $bool);
        $this->assertTrue($assert);

        try{ $sut->load('b'); $this->fail("Expected exception."); }catch (\OutOfBoundsException $e){}
        try{ $sut->load(''); $this->fail("Expected exception.");}catch (\LogicException $e){}
    }
} 