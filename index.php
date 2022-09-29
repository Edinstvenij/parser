<?php

/**
 * Подключаем нужные библиотеки
 */

require_once 'vendor/autoload.php';
require_once 'vendor/electrolinux/phpquery/phpQuery/phpQuery.php';


/**
 *  Обозначаем нужнные нам переменые и функции
 */

// Возврощает строку с латинскими символами вместо кирилици
function translit($string)
{
    $string = (string)$string; // Преобразуем в строковое значение
    $string = trim($string); // Убираем пробелы в начале и конце строки
    $string = function_exists('mb_strtolower') ? mb_strtolower($string) : strtolower($string); // переводим строку в нижний регистр (иногда надо задать локаль)
    $string = strtr($string, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
    return $string; // Возвращаем результат
}

// Функция --- Получения DOM документа (Переход по ссылки)
function parser($url)
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}

$url = 'https://signalua.com.ua/categories/stoly/steklyannye-stoly/'; // Ссылка на каталог сайта
//  https://signalua.com.ua/categories/stoly/steklyannye-stoly/?page=2

// Добавления всех характеристик с карточки товара
function descriptions(phpQueryObject $pq): array
{
    $allDescCard = $pq->find('.product-section__specifications-list:first')->find('.product-section__specifications-row');
    $descCard = [];
    foreach ($allDescCard as $desc) {

        $descCard[] = [
            'name' => pq($desc)->find('.product-section__specifications-title')->text(),
            'value' => pq($desc)->find('.product-section__specifications-descr')->text()
        ];
    }
    return $descCard;
}

function vendor($pq)
{
    foreach (descriptions($pq) as $descItem) {
        if (in_array('Артикул', $descItem)) {
            return $descItem['value'];
        }
    }

    $productName = trim($pq->find('h1')->text());
    $directoryName = str_replace(' ', '_', translit($productName));
    return mb_strimwidth(preg_replace('#[aeiou\s]+#i', '', $directoryName), 0, 10); // Убираем все гласные и обрезаем первые 10 символов

}

/**
 *
 * Начало парсера
 *
 */

function pars($url)
{
// Каталог (Все товары)
    $result = parser($url);

    $pq = phpQuery::newDocument($result);

    $arrLinksCards = [];
    $listLinks = $pq->find('.product-box__name');
    foreach ($listLinks as $listLink) {
        $arrLinksCards[] = pq($listLink)->attr('href');
    }


// Внутри карточки товара (Отдельный отвар)
    $arrListCards = [];
    foreach ($arrLinksCards as $card) {
        $resultCard = parser($card);

        $pq = phpQuery::newDocument($resultCard);

        $imagesCard = [];
        $allImagesCard = $pq->find('.fancybox');
        foreach ($allImagesCard as $img) {
            if (in_array(pq($img)->attr('href'), $imagesCard)) {
                continue;
            }
            $imagesCard[] = pq($img)->attr('href');
        }

        $productName = trim($pq->find('h1')->text());
        $directoryName = str_replace(' ', '_', translit($productName));

        // Собираем инфу о товаре
        $arrListCards[] = [
            'mainParams' => [
                'url' => $card,
                'name' => $productName,
                'categoryId' => 1208, // id Категории из XML Хорошопа
                'vendorCode' => vendor($pq),
                'vendor' => 'Signal', //  Бренд
                'price' => ((int)preg_replace('/[^0-9]/', '', $pq->find('.product-section__price-list')->text())) + 300,
                'currencyId' => 'UAH',
                'description' => trim($pq->find('.product-section__description-text')->html()),
            ],
            'images' => $imagesCard,
            'descList' => descriptions($pq),
        ];
//        break;
    }

    return $arrListCards;
}

// Записываем все товары в файл в формате JSON

//$jsonData = json_encode(pars($url));
//file_put_contents('temp/jsonData.txt', $jsonData);


// Получаем данные из записаного файла
$jsonData = file_get_contents('temp/jsonData.txt');
$arrDataCards = json_decode($jsonData, true);


$dom = new DOMDocument('1.0', 'utf-8');
$offers = $dom->createElement('offers');
$dom->appendChild($offers);

foreach ($arrDataCards as $card) {
    $offer = $dom->createElement('offer');
    $dom->appendChild($offer);
    $offer->setAttribute('id', $card['mainParams']['vendorCode']);
    $offer->setAttribute('group_id', 7458); // 7458 ID table...
    $offer->setAttribute('available', 'true');

    foreach ($card['mainParams'] as $key => $mainParam) {
        $params = $dom->createElement($key, $mainParam);
        $offer->appendChild($params);
    }

    foreach ($card['images'] as $image) {
        $params = $dom->createElement("picture", $image);
        $offer->appendChild($params);
    }

    foreach ($card['descList'] as $descItem) {
        $params = $dom->createElement('param', $descItem['value']);
        $params->setAttribute('name', $descItem['name']);
        $offer->appendChild($params);
    }
}
$dom->save('offers.xml');

