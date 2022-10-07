<?php

namespace parser;
require_once 'vendor/autoload.php';
require_once 'vendor/electrolinux/phpquery/phpQuery/phpQuery.php';

use phpQuery;

class Parser
{
    protected $url;   // Главный URL
    protected $pq;    // Страница (DOMDocument) phpQueryObject


    public function __construct(string $url)    // construct  собирает класс
    {
        $this->setUrl($url);
        $this->setPq($url);
    }


    protected function setUrl(string $url): string          // Устанавливает и возвращает значения в переменную url
    {
        return $this->url = $url;
    }

    public function getUrl(): string                       //  Возвращает значения из переменной url
    {
        return $this->url;
    }

    public function setPq(string $url): \phpQueryObject    // Устанавливает и возвращает значения в переменную pq
    {
        return $this->pq = $this->curl($url);

    }

    public function getPq(): \phpQueryObject              //  Возвращает значения из переменной url
    {
        return $this->pq;
    }


    public function curl($url): \phpQueryObject            // Возвращает DOM документа (Переход по ссылки)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return phpQuery::newDocument($result);
    }

    public function translit(string $string): string       // Возвращает строку с латинскими символами вместо кирилици
    {
        $string = (string)$string; // Преобразуем в строковое значение
        $string = trim($string); // Убираем пробелы в начале и конце строки
        $string = function_exists('mb_strtolower') ? mb_strtolower($string) : strtolower($string); // переводим строку в нижний регистр (иногда надо задать локаль)
        $string = strtr($string, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
        return $string; // Возвращаем результат
    }

    public function urlProductUa(): string                 // Возвращает ссылку на товар с Украинской локализацией
    {
        $urlProductUa = parse_url($this->getUrl());
        $urlProductUa['scheme'] .= '://';
        $urlProductUa['host'] .= '/ua';
        return implode('', $urlProductUa);
    }


    public function productName(): array                   // Возвращает массив с названием товара на двух языках
    {
        $productName = [];
        $productName['ru'] = trim($this->getPq()->find('h1.pagetitle')->text());

        $productName['ua'] = trim($this->curl($this->urlProductUa())->find('h1.pagetitle')->text());
        if (empty($productName['ua'])) {
            $productName['ua'] = &$productName['ru'];
        }
        return $productName;
    }

    public function vendorCode(): string                   // Возвращает Артикул (VendorCode)
    {
        foreach ($this->characteristics() as $descItem) {

            if (in_array('Артикул', $descItem['ru'])) {
                return $descItem['ru']['value'];
            }
        }

        $productName = trim($this->getPq()->find('h1')->text());
        $directoryName = str_replace(' ', '_', $this->translit($productName));

        // Убираем все гласные и забираем первые 10 символов
        return mb_strimwidth(preg_replace('#[aeiou\s]+#i', '', $directoryName), 0, 10);
    }

    public function description(): array                    // (Доработать, есть ситуации возвращения только RU версии) Возвращает массив с описанием товара на двух языках
    {
        $description = [];

//        str_replace($search, 'me-blya.com'...) Нужно для того что бы заменить названия магазина в описании на наше
        $search = [
            'signalua.com.ua',
            'signal.ua.com',
            'signal.com.ua',
            'signalua.com',
            'SignalUA.com.ua'
        ];
        $description['ru'] = str_replace('< / p>', '', str_replace($search, 'me-blya.com', trim($this->getPq()->find('.product-section__description-text')->html())));
        $description['ua'] = str_replace('< / p>', '', str_replace($search, 'me-blya.com', trim($this->curl($this->urlProductUa())->find('.product-section__description-text')->html())));
        if (empty($description['ua'])) {
            $description['ua'] = &$description['ru'];
        }
        return $description;
    }

    public function images(): array                         // Возвращает массив с URL фото товара
    {
        $imagesCard = [];
        $allImagesCard = $this->getPq()->find('.fancybox');
        foreach ($allImagesCard as $img) {
            if (in_array(pq($img)->attr('href'), $imagesCard)) {
                continue;
            }
            $imagesCard[] = pq($img)->attr('href');
        }
        return $imagesCard;
    }

    public function characteristics(): array                // Возвращает массив, всех характеристик с карточки товара
    {
        $descCard = [];

        $allDescCardRu = $this->getPq()->find('.product-section__specifications-list:first')->find('.product-section__specifications-row');
//        $allDescCardUa = &$allDescCardRu;   //(На потом) Украинский смещенный


        $index = 0;
        foreach ($allDescCardRu as $item) {
            $descCard[$index]['ru'] = [
                'name' => pq($item)->find('.product-section__specifications-title')->text(),
                'value' => pq($item)->find('.product-section__specifications-descr')->text()
            ];
            $index++;
        }
//
//        $index = 0;
//        foreach ($allDescCardUa as $item) {
//            $descCard[$index]['ua'] = [
//                'name' => pq($item)->find('.product-section__specifications-title')->text(),
//                'value' => pq($item)->find('.product-section__specifications-descr')->text()
//            ];
//            $index++;
//        }

        return $descCard;
    }

    public function pars() //(Если парсить все товары сразу то начала выбивать 504 Gateway Time-out)
    {
        $url = $this->getUrl();
        $pq = $this->curl($url);
        $arrListCards = []; // Инициализируем переменую(Масив) для хранения карточек товара

        $lastPage = intval($pq->find('.pagination-holder ul.pagination li:nth-child(5)')->text());

//  Переходим на следущую страницу
        for ($index = 1, $count = $lastPage; $index <= $count; $index++) {
            if ($index !== 1) {
                $urlUpdate = $url . '?page=' . $index;
            } else {
                $urlUpdate = $url;
            }

//  (function getAllUrlProductsPage) Собираем все ссылки на товар
            $pq = $this->curl($urlUpdate);

            $arrLinksCards = [];
            $listLinks = $pq->find('.catalog__products-list .product-box__name');
            foreach ($listLinks as $listLink) {
                $arrLinksCards[] = pq($listLink)->attr('href');
            }

// Внутри карточки товара (Отдельный отвар)

            foreach ($arrLinksCards as $card) {
                $this->setUrl($card);
                $pq = $this->setPq($card);
                $arrayProductName = $this->productName();
                $arrayDiscription = $this->description();


                // Собираем инфу о товаре
                $arrListCards[] = [
                    'mainParams' => [
                        'url' => $card,
                        'categoryId' => 1208, // id Категории из XML Хорошопа
                        'vendorCode' => $this->vendorCode(),
                        'vendor' => 'Signal', //  Бренд
                        'name' => $arrayProductName['ru'],
                        'name_ua' => $arrayProductName['ua'],
                        'price' => ((int)preg_replace('/[^0-9]/', '', $pq->find('.product-section__price-list')->text())) + 300,
                        'currencyId' => 'UAH',
                        'description' => $arrayDiscription['ru'],
                        'description_ua' => $arrayDiscription['ua'],
                    ],
                    'images' => $this->images(),
                    'descList' => $this->characteristics(),
                ];
            }
            $jsonData = json_encode($arrListCards);
            file_put_contents('temp/jsonData.txt', $jsonData);
        }
        return $arrListCards;
    }
}