<?php
namespace tests\integration\data;

use kungphu\data\Permutator;

class PermutatorTest extends \PHPUnit_Framework_TestCase{

    /**
     * @test
     */
    function main(){
        $arr = [1,2,3];
        $p = new Permutator();
        $p->a($p::rnum(0, 10));
        $p->b($p::cycle($arr));
        $data = $p->getPermutations();
        //assert that 'a' actually changes and is not stuck 
        $data = array_column($data, 'a');
        $this->assertTrue(!in_array(count($arr), array_count_values($data)));
    }
} 