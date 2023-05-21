<?php

namespace Parser\Model;

require_once 'vendor/autoload.php';

use Parser\Service\Translate\TranslateMicrosoft;
use phpQuery;

class SignalParser
{
    protected $url;   // Главный URL
    protected $pq;    // Страница (DOMDocument) phpQueryObject
    protected $lastPage; // int Ограничения по страницам
    protected $margin; // int наценка
    protected $translator; // object (TranslateInterface) Переводчик

    public function __construct(string $url, int $lastPage = 0, int $margin = 300)
    {
        $this->setUrl($url);
        $this->setPq($url);
        $this->lastPage = $lastPage;
        $this->margin = $margin;
        $this->translator = new TranslateMicrosoft();
    }

    /**
     * Устанавливает и возвращает значения в переменную url
     * @param string $url
     */
    protected function setUrl(string $url)
    {
        $this->url = $url;
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
     */
    public function setPq(string $url)
    {
        $this->pq = $this->curl($url);
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
        $string = function_exists('mb_strtolower') ? mb_strtolower($string) : strtolower(
            $string
        ); // переводим строку в нижний регистр (иногда надо задать локаль)
        $string = strtr(
            $string,
            array(
                'а' => 'a',
                'б' => 'b',
                'в' => 'v',
                'г' => 'g',
                'д' => 'd',
                'е' => 'e',
                'ё' => 'e',
                'ж' => 'j',
                'з' => 'z',
                'и' => 'i',
                'й' => 'y',
                'к' => 'k',
                'л' => 'l',
                'м' => 'm',
                'н' => 'n',
                'о' => 'o',
                'п' => 'p',
                'р' => 'r',
                'с' => 's',
                'т' => 't',
                'у' => 'u',
                'ф' => 'f',
                'х' => 'h',
                'ц' => 'c',
                'ч' => 'ch',
                'ш' => 'sh',
                'щ' => 'shch',
                'ы' => 'y',
                'э' => 'e',
                'ю' => 'yu',
                'я' => 'ya',
                'ъ' => '',
                'ь' => ''
            )
        );
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
        $urlProductUa['host'] .= '/ru';
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
            $productName['ua'] = $this->translator->translate($productName['ru']);
        }
        return $productName;
    }

    public function price()
    {
        $priceList = $this->getPq()->find('.product-section__price-list');
        $result = [];

        if (!empty(pq($priceList)->find('.product-section__new-price')->text())) {
            $result['new'] = preg_replace(
                    '/[^0-9]/',
                    '',
                    pq($priceList)->find('.product-section__new-price')->text()
                ) + $this->margin;
            $result['old'] = preg_replace(
                    '/[^0-9]/',
                    '',
                    pq($priceList)->find('.product-section__old-price')->text()
                ) + $this->margin;
        } else {
            $result['new'] = preg_replace('/[^0-9]/', '', pq($priceList)->text()) + $this->margin;
        }
        return $result;
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
        $searchBrend = [
            'signalua.com.ua',
            'signal.ua.com',
            'signal.com.ua',
            'signalua.com',
            'SignalUA.com.ua'
        ];
        $searchError = [
            '< / p>',
            ' < ',
        ];

        $description['ru'] = str_replace(
            $searchError,
            '',
            str_replace(
                $searchBrend,
                'me-blya.com',
                trim($this->getPq()->find('.product-section__description-text')->html())
            )
        );
        $description['ua'] = str_replace(
            $searchError,
            '',
            str_replace(
                $searchBrend,
                'me-blya.com',
                trim($this->curl($this->urlProductUa())->find('.product-section__description-text')->html())
            )
        );
        if (empty($description['ua'])) {
            $description['ua'] = $this->translator->translate($description['ru']);
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

        $allDescCardRu = $this->getPq()->find('.product-section__specifications-list:first')->find(
            '.product-section__specifications-row'
        );
//        $allDescCardUa = &$allDescCardRu;   //(На потом) Украинский смещенный

        $index = 0;
        foreach ($allDescCardRu as $item) {
            $value = pq($item)->find('.product-section__specifications-descr')->text();
            if (is_float($value)) {
                $value = round($value, 1);
            }

            $descCard[$index]['ru'] = [
                'name' => pq($item)->find('.product-section__specifications-title')->text(),
                'value' => $value
            ];
            $index++;
        }
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

        if ($this->lastPage) {
            $lastPage = $this->lastPage;
        } else {
            $lastPage = $pq->find('.pagination-holder ul.pagination li');
            $lastPage->find(':last')->remove();
            $lastPage = $lastPage->find(':last')->text();


            //  Сканируем первые 5 страниц
            if ($lastPage > 11) {
                $lastPage = 11;
            } else {
                if (empty($lastPage)) {
                    $lastPage = 1;
                }
            }
        }

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
                $this->setPq($card);
                $arrayProductName = $this->productName();
                $arrayDiscription = $this->description();
                $price = $this->price();

                // Собираем инфу о товаре
                $arrListCards[] = [
                    'mainParams' => [
                        'url' => $card,
                        'categoryId' => 1208, // id Категории из XML Хорошопа
                        'vendorCode' => $this->vendorCode(),
                        'vendor' => 'Signal', //  Бренд
                        'name' => $arrayProductName['ru'],
                        'name_ua' => $arrayProductName['ua'],
                        'price' => $price['new'],
                        'price_old' => $price['old'] ?? 0,
                        'currencyId' => 'UAH',
                        'description' => $arrayDiscription['ru'],
                        'description_ua' => $arrayDiscription['ua'],
                    ],
                    'images' => $this->images(),
                    'descList' => $this->characteristics(),
                ];
            }
        }

        // Записывает все в файл и сохраняет
        $jsonData = json_encode($arrListCards);
        file_put_contents('temp/jsonData.txt', $jsonData);
    }


    public function getDataFile(bool $print = false): array
    {
        $jsonData = file_get_contents('temp/jsonData.txt');
        $arrDataCards = json_decode($jsonData, true);

        if ($print === true) {
            echo '<pre>';
            print_r($arrDataCards);
            echo '</pre>';
        }

        return $arrDataCards;
    }

    public function createXML()
    {
        $dom = new \DOMDocument('1.0', 'utf-8');
        $offers = $dom->createElement('offers');
        $dom->appendChild($offers);
        $offers = $dom->getElementsByTagName('offers')[0];

        foreach ($this->getDataFile() as $card) {
            $offer = $dom->createElement('offer');
            $offers->appendChild($offer);
            $offer->setAttribute('id', $card['mainParams']['vendorCode']);
            $offer->setAttribute('group_id', 157458); // 7458 ID table...
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
        $dom->save('temp/products.xml');
    }
}