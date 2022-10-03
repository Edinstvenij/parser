<?php

require_once 'vendor/autoload.php';

use parser\Parser;

//      https://signalua.com.ua/categories/stoly/steklyannye-stoly/
//      https://signalua.com.ua/categories/stoly/steklyannye-stoly/?page=2
//      https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-galant-110-x-70-belyy

$signal = new Parser('https://signalua.com.ua/categories/stoly/steklyannye-stoly/');
echo '<pre>';
print_r($signal->pars());
echo '</pre>';
