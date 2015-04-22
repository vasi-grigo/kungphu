<?php

namespace kungphu\data;

class Permutator {
    protected $_generators;

    /**
     * Function to run to get the data loaded.
     * 
     * @var \Closure
     */
    protected $_loadClosure;

    /**
     * Returns a callback that generates a random string value.
     *
     * @param string $prefix
     * @param bool $more_entropy
     * @return callable
     */
    static function rstr($prefix = '', $more_entropy = false){
        return function() use ($prefix, $more_entropy){
            return [uniqid($prefix, $more_entropy), true];
        };
    }

    /**
     * Returns a callback that generates a random number value.
     *
     * @param int $min
     * @param null $max
     * @param bool $float
     * @return callable
     */
    static function rnum($min = 0, $max = null, $float = false){
        return function() use ($min, $max, $float){
            $num = mt_rand($min, $max);
            return [$float ? $num / mt_getrandmax() : $num , true];
        };
    }

    /**
     * Returns a callback that generates a random date between $d0 and $d1.
     *
     * @param string $d0
     * @param string $d1
     * @return callable
     */
    static function rdate($d0, $d1){
        return function() use ($d0, $d1){
            $min = strtotime($d0);
            $max = strtotime($d1);
            $d = new \DateTime();
            $d->setTimestamp(mt_rand($min, $max));
            return [$d, true];
        };
    }

    /**
     * Returns a callback that generates a random value from the array given.
     *
     * @param array $values
     * @return callable
     */
    static function rvalue(array $values){
        return function() use ($values){
            $r = mt_rand(0, count($values) - 1);
            return $values[$r];
        };
    }

    /**
     * Returns the permutations for the combos given.
     *
     * @param array $combos An array of arrays where each element of the latter is
     * a possible value.
     *
     * @return array|mixed
     *
     * @throws \RuntimeException
     */
    static function permutate(array $combos){
        if (empty($combos)){
            return [];
        }
        foreach ($combos as $i => $c) {
            if (empty($c)){
                throw new \RuntimeException('$combos[' . $i . '] is empty. At least 1 value must be provided');
            }
        }

        //handle case when 1 value is passed
        if (count($combos) == 1){
            $el = end($combos);
            reset ($el);
            $ret = [];
            foreach ($el as $e) {
                $ret[] = [$e];
            }
            return $ret;
        }

        $reduce = function($carry, $item){
            if (empty($carry)){
                return $item;
            }
            $ret = [];
            foreach ($carry as $c) {
                foreach ($item as $i) {
                    if (is_array($c)){
                        $n = $c;
                        $n[] = $i;
                        $ret[] = $n;
                    }else{
                        $ret[] = [$c, $i];
                    }
                }
            }
            return $ret;
        };
        return array_reduce($combos, $reduce);
    }

    /**
     * Returns a callback that iterates over an array of values.
     *
     * @param array $arr Array to cycle.
     *
     * @return callable
     * @throws \RuntimeException
     */
    static function cycle(array $arr){
        if (count($arr) <= 0){
            throw new \RuntimeException('Array has no elements.');
        }

        return function() use ($arr){
            static $i = 0;
            static $i_max;
            $i_max = is_null($i_max) ? count($arr) - 1 : $i_max;
            $ret = $arr[$i];
            $last = $i == $i_max;
            $i = $i == $i_max ? 0 : $i + 1;
            return [$ret, $last];
        };
    }

    /**
     * Returns a callback that iterates over a date range.
     *
     * @param \DateTime $dt0 Start date.
     * @param \DateTime $dt1 End date.
     * @param string $step Step to make during iteration.
     *
     * @return callable
     * @throws \RuntimeException
     */
    static function date_cycle(\DateTime $dt0, \DateTime $dt1, $step){
        try{
            $test = new \DateTime();
            $test->modify($step);
        }catch (\Exception $e){
            throw new \RuntimeException('Invalid $step given.', 0, $e);
        }

        return function() use ($dt0, $dt1, $step){
            static $_dt0;
            static $_dt1;
            static $_i = false;
            $_dt0 = empty($_dt0) ? $dt0 : $_dt0;
            $_dt1 = empty($_dt1) ? $dt1 : $_dt1;

            //skip first iteration
            if ($_i){
                $_dt0->modify($step);
            }
            $_i = true;

            $_dt0 = $_dt0 > $_dt1 ? $_dt1 : $_dt0;
            return [clone $dt0, $dt0 == $dt1];
        };
    }

    /**
     * Returns a default callback to be used for loading data into mysql.
     * 
     * @param \PDO| \mysqli $db
     * @param string $table
     * @param string $file
     * 
     * @return callable
     * @throws \LogicException
     */
    static function load_mysql($db, $table, $file){
        $valid = [
            $db instanceof \PDO,
            $db instanceof \mysqli
        ];
        if (!in_array(true, $valid)){
            throw new \LogicException("Unrecognized db adapter. PDO or mysqli are supported.");
        }
        if (empty($table)){
            throw new \LogicException("Empty table name provided.");
        }
        
        return function(Loader $l, Permutator $p, $optimize) use ($db, $table, $file){
            //if no optimization or file contents are empty, dump the data
            if (!$optimize || !file_exists($file)){
                self::export_csv($p->getPermutations(), $file);
            }
            
            //autoquote
            if (strpos($table, '.') === false){
                $bool = preg_match('#`.+`#i', $table);
                $table = (bool) $bool ? $table : "`$table`";
            }

            $sql = <<<SQL
                LOAD DATA LOCAL INFILE '$file'
                INTO TABLE $table
                FIELDS TERMINATED BY ','
                OPTIONALLY ENCLOSED BY '"'
                LINES TERMINATED BY '\n'
SQL;
            if ($db instanceof \PDO){
                return $db->exec($sql);
            }
            
            if ($db instanceof \mysqli){
                return $db->query($sql);
            }
            
            throw new \RuntimeException("Adapter not implemented.");
        };
    }
    
    static function load_mongo(array $conf, $file){
        //validate config
        $data = [
            //required
            ['db', ''],
            ['collection', ''],
            //optional
            ['host', 'localhost'],
            ['port', '27017']
        ];
        foreach ($data as $v) {
            if (empty($v[1]) && empty($conf[$v[0]])){
                throw new \LogicException("\$conf[$v[0]] is empty but is required.");
            }
            $conf[$v[0]] = isset($conf[$v[0]]) ? $conf[$v[0]] : $v[1];
        }
        
        if (empty($file)){
            throw new \LogicException("Empty file given.");
        }
        
        $conf['file'] = $file;
        
        return function(Loader $l, Permutator $p, $optimize) use ($conf, $file){
            //if no optimization or file contents are empty, dump the data
            if (!$optimize || !file_exists($file)){
                self::export_json($p->getPermutations(), $file);
            }
            
            $cmd = "mongoimport \\
                --db $conf[db] \\
                --collection $conf[collection] \\
                --file $conf[file] \\
                --host $conf[host] \\
                --port $conf[port] \\
                --type json --jsonArray
            ";
            
            exec($cmd, $output, $return);
            return [$output, $return];
        };
    }
    
    static function export_csv(array $data, $file, $headerline = false){
        if (empty($data)){
            throw new \RuntimeException("No data given.");
        }
        $fp = fopen($file, 'w');
        
        if ($headerline){
            fputcsv($fp, array_keys(current($data)));
        }
        
        foreach ($data as $k => $fields) {
            if (!is_array($fields)){
                throw new \RuntimeException("\$data[$k] is not an array.");
            }
            fputcsv($fp, $fields);
        }
        $bool = fclose($fp);
        if ($bool === false){
            throw new \RuntimeException("Failed to write to file '$file'.");
        }
        
        return true;
    }

    static function export_json(array $data, $file){
        if (empty($data)){
            throw new \RuntimeException("No data given.");
        }

        $data = json_encode($data);
        if ($data === false){
            throw new \RuntimeException("Failed to json_encode($data).");
        }

        $bool = file_put_contents($file, $data);
        if ($bool === false){
            throw new \RuntimeException("Failed to write to file '$file'.");
        }
        
        return true;
    }

    function __call($name, $value){
        if (count($value) > 1){
            throw new \RuntimeException("Only a single value is expected.");
        }

        $value = array_pop($value);
        $value = $value instanceof \Closure ? $value :function() use ($value) { return [$value, true]; };
        $this->_generators[$name] = $value;
        return $this;
    }

    /**
     * Returns the permutations in accordance with fields set.
     *
     * @return array|mixed
     */
    function getPermutations(){
        if (empty($this->_generators)){
            return [];
        }

        $combos = [];
        foreach ($this->_generators as $name => $g) {
            $row = [];
            while (true){
                $ret = call_user_func($g);
                $row[] = $ret[0];
                if ($ret[1]){
                    break;
                }
            }
            $combos[$name] = $row;
        }

        $map = array_keys($this->_generators);
        $ret = self::permutate($combos);
        $ret = array_map(function ($item) use ($map) { return array_combine($map, $item); }, $ret);
        return $ret;
    }

    /**
     * Getter/setter for the loading function.
     * 
     * @param \Closure $callback
     * @return callable
     * @throws \RuntimeException
     */
    function loadClosure($callback = null){
        if (empty($callback)){
            return $this->_loadClosure;
        }
        if (!$callback instanceof \Closure){
            throw new \RuntimeException('$func must be an instance of \Closure');
        }
        
        $this->_loadClosure = $callback;
    }
    
    /**
     * Resets the class throwing away all bindings.
     */
    function reset(){
        $this->_generators = [];
    }
}