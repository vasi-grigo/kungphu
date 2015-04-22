<?php

namespace kungphu\data;

class Loader {

    /**
     * @var array
     */
    protected $_map;

    function __construct(array $map){
        if (empty($map)){
            throw new \LogicException('$map can not be empty.');
        }

        //validate $map
        foreach ($map as $k0 => $m0) {
            if (is_array($m0) && !empty($m0)){
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
            
            throw new \LogicException("\$map[$k0] is not an array of Permutator or is empty.");
        }

        $this->_map = $map;
    }
    
    function load($set){
        if (empty($set) && $set !== 0){
            throw new \LogicException('No $set given.');
        }
        
        if (empty($this->_map[$set])){
            throw new \OutOfBoundsException("No binding defined for $set.");
        }
        
        $ret = [];
        /** @var $v Permutator */
        foreach ($this->_map[$set] as $k => $v) {
            $ret[$k] = call_user_func($v->loadClosure(), $this, $set, $k, $v);
        }
        
        return $ret;
    }
}