#!/usr/bin/env php
<?php

$files = [
    __DIR__ . '/../../autoload.php',
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/vendor/autoload.php'
];

$found = false;
foreach ($files as $file) {
    if (file_exists($file)) {
        $found = $file;
        break;
    }
}

if (empty($found)) {
    echo 'Composer autoload file not found. Aborting';
    die(1);
}

require $found;
set_time_limit(0);
$route = isset($argv[1]) ? $argv[1] : null;

//check is run from project root
$project_dir = getcwd();
if ($project_dir == __DIR__){
    echo "Command must be run from project root (pwd: \033[31m$project_dir\033[0m)\n";
    exit(1);
}

echo "\033[41;37m[WIP]\033[0m\n";
exit;

$conf = explode(DIRECTORY_SEPARATOR, dirname(__DIR__));
$conf = end($conf);
$conf = $project_dir . DIRECTORY_SEPARATOR . $conf . '.php';
if (!file_exists($conf)){
    $input_dir = $project_dir . DIRECTORY_SEPARATOR . 'tests';
    $output_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . md5(__FILE__);
    echo "\033[41;37mWarning! Config file '$conf' was not found.\033[0m\n";
    echo "\033[33mDefault settings will be used:\033[0m\n";
    echo "  \033[33minput_dir\033[0m = $input_dir\n";
    echo "  \033[33moutput_dir\033[0m = $output_dir\n";
    echo "A config may be created or symlinked to the name above.\n";
    echo "Default config:\n";
    echo <<<CONF
    
    'kungphu' => [
        'input_dir' => __DIR__ . '/tests',
        'output_dir' => sys_get_temp_dir() . '/myProjectUniqueDir'
    ]
    \n
CONF;
    
    if (!file_exists($output_dir)){
        $bool = mkdir($output_dir);
        if (!$bool){
            echo "\033[41;37mCould not create output_dir '$output_dir'.\033[0m\n";
            exit(1);
        }
    }
}

if (empty($route) || in_array($route, ['-h', '--help', '-?', 'help'])){
    
    $msg = [
        "No arguments given.",
        "Available commands are:",
        "   \033[32msuggest\033[0m                          - \033[31m[WIP]\033[0m"
    ];
    $msg = implode("\n", $msg);
    echo $msg . "\n";
    exit(1);
}

if ($route == 'dump'){
    
}

echo "No command '$route' found.\n";
exit(1);

