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


    protected function setUrl(string $url): string  // Устанавливает и возвращает значения в переменную url
    {
        return $this->url = $url;
    }

    public function setPq(string $url): \phpQueryObject // Устанавливает и возвращает значения в переменную pq
    {
        return $this->pq = $this->curl($url);

    }

    public function getUrl(): string             //  Получаем значения из переменной url
    {
        return $this->url;
    }

    public function getPq(): \phpQueryObject                //  Получаем значения из переменной url
    {
        return $this->pq;
    }


    public function translit(string $string): string // Возвращает строку с латинскими символами вместо кирилици
    {
        $string = (string)$string; // Преобразуем в строковое значение
        $string = trim($string); // Убираем пробелы в начале и конце строки
        $string = function_exists('mb_strtolower') ? mb_strtolower($string) : strtolower($string); // переводим строку в нижний регистр (иногда надо задать локаль)
        $string = strtr($string, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
        return $string; // Возвращаем результат
    }

    public function curl($url): \phpQueryObject                   // Получения DOM документа (Переход по ссылки)
    {
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HEADER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return phpQuery::newDocument($result);
    }

    public function descriptions(): array  // Добавления всех характеристик с карточки товара
    {

        $allDescCardRu = $this->getPq()->find('.product-section__specifications-list:first')->find('.product-section__specifications-row');

        $urlProductUa = parse_url($this->getUrl());
        $urlProductUa['scheme'] .= '://';
        $urlProductUa['host'] .= '/ua';
        $urlProductUa = implode('', $urlProductUa);
        $allDescCardUa = $this->setPq($urlProductUa)->find('.product-section__specifications-list:first')->find('.product-section__specifications-row');

        $descCard = [];

        $index = 0;
        foreach ($allDescCardRu as $desc) {

            $descCard[$index]['ru'] = [
                'name' => pq($desc)->find('.product-section__specifications-title')->text(),
                'value' => pq($desc)->find('.product-section__specifications-descr')->text()
            ];
            $index++;
        }

        $index = 0;
        foreach ($allDescCardUa as $desc) {
            $descCard[$index]['ua'] = [
                'name' => pq($desc)->find('.product-section__specifications-title')->text(),
                'value' => pq($desc)->find('.product-section__specifications-descr')->text()
            ];
            $index++;
        }

        return $descCard;
    }


    public function vendor(): string
    {
        foreach ($this->descriptions($this->getPq()) as $descItem) {
            if (in_array('Артикул', $descItem)) {
                return $descItem['value'];
            }
        }

        $productName = trim($this->getPq()->find('h1')->text());
        $directoryName = str_replace(' ', '_', $this->translit($productName));

        // Убираем все гласные и забираем первые 10 символов
        return mb_strimwidth(preg_replace('#[aeiou\s]+#i', '', $directoryName), 0, 10);
    }


    function pars()
    {
        $url = $this->getUrl();
        $maxProductOnePage = 1; // Сколько товаров с 1-ой странцы забераем (Снизу есть товары не с нашей категории)
        $indexProduct = 1; // Нужно для счета
        $arrListCards = []; // Инициализируем переменую(Масив) для хранения карточек товара

        $pq = $this->curl($url);
        $lastPage = $pq->find('.catalog__products ul li');
        pq($lastPage)->find(':last')->remove();
        $lastPage = $lastPage->find(':last')->text();

//  Переходим на следущую страницу
        for ($index = 1, $count = $lastPage; $index < $count; $index++) {
            if ($index !== 1) {
                $urlUpdate = $url . '?page=' . $index;
            } else {
                $urlUpdate = $url;
            }


// Каталог (Все товары)
            $pq = $this->curl($urlUpdate);

            $arrLinksCards = [];
            $listLinks = $pq->find('.product-box__name');
            foreach ($listLinks as $listLink) {
                $arrLinksCards[] = pq($listLink)->attr('href');
            }


// Внутри карточки товара (Отдельный отвар)

            foreach ($arrLinksCards as $card) {
                $this->setUrl($card);
                $pq = $this->setPq($card);

                $imagesCard = [];
                $allImagesCard = $pq->find('.fancybox');
                foreach ($allImagesCard as $img) {
                    if (in_array(pq($img)->attr('href'), $imagesCard)) {
                        continue;
                    }
                    $imagesCard[] = pq($img)->attr('href');
                }

                $productName = trim($pq->find('h1')->text());
                $directoryName = str_replace(' ', '_', $this->translit($productName));


                // Собираем инфу о товаре
                $arrListCards[] = [
                    'mainParams' => [
                        'url' => $card,
                        'name' => [
                            'ru' => $productName,
                            'ua' => $productName
                        ],
                        'categoryId' => 1208, // id Категории из XML Хорошопа
                        'vendorCode' => $this->vendor($pq),
                        'vendor' => 'Signal', //  Бренд
                        'price' => ((int)preg_replace('/[^0-9]/', '', $pq->find('.product-section__price-list')->text())) + 300,
                        'currencyId' => 'UAH',
                        'description' => [
                            'ru' => trim($pq->find('.product-section__description-text')->html()),
                            'ua' => trim($pq->find('.product-section__description-text')->html())
                        ],
                    ],
                    'images' => $imagesCard,
                    'descList' => $this->descriptions(),
                ];

                if ($indexProduct == $maxProductOnePage) {
                    $indexProduct = 1;
                    break;
                }
                $indexProduct++;
            }
        }
        return $arrListCards;
    }

}