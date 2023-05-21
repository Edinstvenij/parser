<?php

namespace Parser\Service\Translate;

use GuzzleHttp\Client as GuzzleClient;

class TranslateMicrosoft implements TranslateInterface
{
    public function translate(string $text): string
    {
        $url = 'https://api.cognitive.microsofttranslator.com/translate';

        $headers = [
            'Ocp-Apim-Subscription-Key' => $_ENV['OCP_APIM_KEY'],
            'Ocp-Apim-Subscription-Region' => $_ENV['OCP_APIM_REGION'],
            'Content-Type' => 'application/json',
        ];

        $params = [
            'api-version' => '3.0',
            'to' => 'uk',
        ];

        $jsonData = json_encode([['Text' => $text]],
            JSON_UNESCAPED_UNICODE);

        $client = new GuzzleClient();

        $result = $client->post($url, [
            'headers' => $headers,
            'query' => $params,
            'body' => $jsonData,
        ]);

        $jsonResponse = $result->getBody()->getContents();

        return json_decode($jsonResponse)[0]->translations[0]->text;
    }
}