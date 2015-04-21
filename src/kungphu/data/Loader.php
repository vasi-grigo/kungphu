<?php

namespace kungphu\data;

class Loader {

    /**
     * @var array
     */
    protected $_map;
    protected $_optimize;

    function __construct(array $map, $optimize = true){
        if (empty($map)){
            throw new \LogicException('$map can not be empty.');
        }

        //validate $map
        foreach ($map as $k0 => $m0) {
            if (is_array($m0)){
                foreach ($m0 as $k1 => $m1) {
                    if ($m1 instanceof Permutator){
                        if ($m1->loadClosure() instanceof \Closure){
                            continue;
                        }
                        throw new \LogicException("\$map[$k0][$k1] does not have a defined loadClosure");
                    }
                    throw new \LogicException("\$map[$k0][$k1] is not an instance of Permutator.");
                }
                continue;
            }
            
            throw new \LogicException("\$map[$k0] is not an array of Permutator.");
        }

        $this->_map = $map;
        $this->_optimize = $optimize;
    }
    
    function load($set, $optimize = null){
        if (empty($set) && $set !== 0){
            throw new \LogicException('No $set given.');
        }
        
        if (empty($this->_map[$set])){
            throw new \OutOfBoundsException("No binding defined for $set.");
        }
        
        $optimize = !is_null($optimize) ? $optimize : $this->_optimize;
        /** @var $v Permutator */
        foreach ($this->_map[$set] as $v) {
            call_user_func($v->loadClosure(), $this, $v, $optimize);
        }
    }
}