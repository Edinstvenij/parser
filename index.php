<?php
require_once 'vendor/autoload.php';

use parser\Parser;

//      https://signalua.com.ua/categories/stoly/steklyannye-stoly/
//      https://signalua.com.ua/categories/stoly/steklyannye-stoly/?page=2
//      https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-galant-110-x-70-belyy
//      https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-damar-100-x-60-sm-cherno-belyy       (ua)
//      https://signalua.com.ua/categories/stoly/kuhonnie-stoli/stol-obedennyy-gd-017-110-170-x74-chernyy           (NO ua)
$signal = new Parser('https://signalua.com.ua/categories/stoly/steklyannye-stoly/');
//echo '<pre>';
//print_r($signal->pars());
//echo '</pre>';

// Записываем все товары в файл в формате JSON
//$signal->pars();
//$jsonData = json_encode($signal->pars());
//file_put_contents('temp/jsonData.txt', $jsonData);


// Получаем данные из записаного файла
$jsonData = file_get_contents('temp/jsonData.txt');
$arrDataCards = json_decode($jsonData, true);

echo '<pre>';
print_r($arrDataCards);
echo '</pre>';


$dom = new DOMDocument('1.0', 'utf-8');
$offers = $dom->createElement('offers');
$dom->appendChild($offers);
$offers = $dom->getElementsByTagName('offers')[0];

foreach ($arrDataCards as $card) {
    $offer = $dom->createElement('offer');
    $offers->appendChild($offer);
    $offer->setAttribute('id', $card['mainParams']['vendorCode']);
    $offer->setAttribute('group_id', 7458); // 7458 ID table...
    $offer->setAttribute('available', 'true');

    foreach ($card['mainParams'] as $key => $mainParam) {
        $params = $dom->createElement($key, htmlspecialchars($mainParam));
        $offer->appendChild($params);
    }

    foreach ($card['images'] as $image) {
        $params = $dom->createElement("picture", $image);
        $offer->appendChild($params);
    }

    foreach ($card['descList'] as $descItem) {
        $params = $dom->createElement('param', $descItem['ru']['value']);
        $params->setAttribute('name', $descItem['ru']['name'] . ' ru');
        $params->setAttribute('lang', 'ru');
        $offer->appendChild($params);

        $params = $dom->createElement('param', $descItem['ru']['value']);
        $params->setAttribute('name', $descItem['ru']['name'] . ' ua');
        $params->setAttribute('lang', 'ua');
        $offer->appendChild($params);
    }
}
$dom->save('temp/offers.xml');
