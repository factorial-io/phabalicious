<?php

$spec  = array(0 => array("pipe", "r"), STDOUT, STDERR);
$pipes = array();
$cwd = getcwd();
$env = null;
$options = array();

$proc = proc_open(
    '/bin/bash',
    $spec,
    $pipes,
    $cwd,
    $env,
    $options
);

print_r($pipes);

fwrite($pipes[0], 'ls -la' . PHP_EOL);
sleep(1);
fwrite($pipes[0], 'echo "hello world"' . PHP_EOL);
$err = proc_close($proc);
