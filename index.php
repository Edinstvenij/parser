<?php

require_once 'vendor/autoload.php';

use parser\Parser;

//      https://signalua.com.ua/categories/stoly/steklyannye-stoly/
//      https://signalua.com.ua/categories/stoly/steklyannye-stoly/?page=2
//      https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-galant-110-x-70-belyy
//      https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-damar-100-x-60-sm-cherno-belyy       (ua)
//      https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-gd-017-110-170-x74-chernyy           (NO ua)
$signal = new Parser('https://signalua.com.ua/categories/stoly/steklyannye-stoly/');
echo '<pre>';
print_r($signal->pars());
echo '</pre>';

//$signalProduct = new Parser('https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-gd-017-110-170-x74-chernyy');
//echo '<pre>';
//print_r($signalProduct->characteristics());
//echo '</pre>';
