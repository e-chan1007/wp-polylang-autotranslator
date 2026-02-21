<?php

namespace WPPolylangAutoTranslator;

interface TranslatorInterface
{
    public function translate(string $text, string $target_lang): string;
}
