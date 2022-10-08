<?php

namespace parser;
require_once 'vendor/autoload.php';

use phpQuery;

class Parser
{
    protected $url;   // Главный URL
    protected $pq;    // Страница (DOMDocument) phpQueryObject


    public function __construct(string $url)
    {
        $this->setUrl($url);
        $this->setPq($url);
    }

    /**
     * Устанавливает и возвращает значения в переменную url
     * @param string $url
     * @return string
     */
    protected function setUrl(string $url): string
    {
        return $this->url = $url;
    }

    /**
     * Возвращает значения из переменной url
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Устанавливает и возвращает значения в переменную pq
     * @param string $url
     * @return \phpQueryObject
     */
    public function setPq(string $url): \phpQueryObject
    {
        return $this->pq = $this->curl($url);

    }

    /**
     * Возвращает значения из переменной url
     * @return \phpQueryObject
     */
    public function getPq(): \phpQueryObject
    {
        return $this->pq;
    }


    /**
     * Возвращает DOM документа (Переход по ссылки)
     * @param $url
     * @return \phpQueryObject
     */
    public function curl($url): \phpQueryObject
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return phpQuery::newDocument($result);
    }

    /**
     * Возвращает строку с латинскими символами вместо кирилици
     * @param string $string
     * @return string
     */
    public function translit(string $string): string
    {
        $string = (string)$string; // Преобразуем в строковое значение
        $string = trim($string); // Убираем пробелы в начале и конце строки
        $string = function_exists('mb_strtolower') ? mb_strtolower($string) : strtolower($string); // переводим строку в нижний регистр (иногда надо задать локаль)
        $string = strtr($string, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
        return $string; // Возвращаем результат
    }

    /**
     * Возвращает ссылку на товар с Украинской локализацией
     * @return string
     */
    public function urlProductUa(): string
    {
        $urlProductUa = parse_url($this->getUrl());
        $urlProductUa['scheme'] .= '://';
        $urlProductUa['host'] .= '/ua';
        return implode('', $urlProductUa);
    }


    /**
     * Возвращает массив с названием товара на двух языках
     * @return array
     */
    public function productName(): array
    {
        $productName = [];
        $productName['ru'] = trim($this->getPq()->find('h1.pagetitle')->text());

        $productName['ua'] = trim($this->curl($this->urlProductUa())->find('h1.pagetitle')->text());
        if (empty($productName['ua'])) {
            $productName['ua'] = &$productName['ru'];
        }
        return $productName;
    }

    /**
     * Возвращает Артикул (VendorCode)
     * @return string
     */
    public function vendorCode(): string
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

    /**
     * (Доработать, есть ситуации возвращения только RU версии) Возвращает массив с описанием товара на двух языках
     * @return array
     */
    public function description(): array
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

    /**
     * Возвращает массив с URL фото товара
     * @return array
     */
    public function images(): array
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

    /**
     * Возвращает массив, всех характеристик с карточки товара
     * @return array
     */
    public function characteristics(): array
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

    /**
     * (Hачалo выбивать 504 Gateway Time-out)
     * @return void
     */
    public function pars()
    {
        $url = $this->getUrl();
        $pq = $this->curl($url);
        $arrListCards = []; // Инициализируем переменую(Масив) для хранения карточек товара

        $lastPage = intval($pq->find('.pagination-holder ul.pagination li:nth-child(5)')->text());

//  Переходим на следущую страницу
        for ($index = 1; $index <= $lastPage; $index++) {
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
                break;
            }
            break;
        }
        $jsonData = json_encode($arrListCards);
        file_put_contents('temp/jsonData.txt', $jsonData);
//        return $arrListCards;
    }
}