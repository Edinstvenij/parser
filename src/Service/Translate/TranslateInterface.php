<?php

namespace Parser\Service\Translate;

interface TranslateInterface
{
    public function translate(string $text): string;
}