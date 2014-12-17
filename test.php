<?php
include 'fqueen.php';

$queen = fqueen::instance('queen.txt');
$queen->reset();

$max = 10000;
$process = 4;

echo "test push {$max} per process in 1 process \r\n";
$time_start = microtime(TRUE);
for ($i = 0; $i < $max; $i ++) {
    $queen->push($i);
}
echo intval($max / (microtime(TRUE) - $time_start)), "\r\n";
echo "queen info: ", implode(", ", $queen->info()), "\r\n";

echo "test pop {$max} per process in 1 process \r\n";
$time_start = microtime(TRUE);
while ($queen->pop() !== FALSE) {
}
echo intval($max / (microtime(TRUE) - $time_start)), "\r\n";
echo "queen info: ", implode(", ", $queen->info()), "\r\n";

$queen->reset();
echo "test push {$max} pre process in {$process} process \r\n";
for ($i = 1; $i < $process; $i ++) {
    $pid = pcntl_fork();
    if ($pid == 0) {
        break;
    }
}
sleep(1);
$time_start = microtime(TRUE);
for ($i = 0; $i < $max; $i ++) {
    $queen->push($i);
}
echo intval($max / (microtime(TRUE) - $time_start)), "\r\n";
if (! $pid) {
    die;
}

echo "queen info: ", implode(", ", $queen->info()), "\r\n";
pcntl_wait($status);

echo "test pop {$max} per process in {$process} process \r\n";
for ($i = 1; $i < $process; $i ++) {
    $pid = pcntl_fork();
    if ($pid == 0) {
        break;
    }
}
sleep(1);
$line = 0;
$time_start = microtime(TRUE);
while ($queen->pop() !== FALSE) {
    $line ++;
}
echo intval($line / (microtime(TRUE) - $time_start)), "\r\n";

echo "queen info: ", implode(", ", $queen->info()), "\r\n";
if ($pid) {
    pcntl_wait($status);
}

