<?php
include 'fqueen.php';

$queen = fqueen::instance('queen.txt');



$i = 0;
while ($queen->pop() !== FALSE)
{
    $i ++;
}
var_dump($i, $queen->info());
