<?php

require_once 'vendor/autoload.php';

use parser\Parser;


$signal = new Parser('https://signalua.com.ua/categories/stoly/steklyannye-stoly/');
echo '<pre>';
print_r($signal->pars());
echo '</pre>';
