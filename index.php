<?php

require_once './vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

use Parser\Model\SignalParser;


$url = 'https://signalua.com.ua/categories/mebel-intarsio-ukraina/';
$lastPage = 1;

$signal = new SignalParser($url, $lastPage, 1000);
$signal->pars();
$signal->getDataFile(true);
$signal->createXML();
