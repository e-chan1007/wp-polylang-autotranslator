<?php

namespace WPPolylangAutoTranslator\TranslateEngine;

use WPPolylangAutoTranslator\TranslatorInterface;
use DeepL\Translator;

class DeepL implements TranslatorInterface
{
  private $client;

  public function __construct($config)
  {
    $this->client = new Translator($config["api_key"]);
  }

  public function translate(string $text, string $target_lang): string
  {
    try {
      $text = "<div>" . $text . "</div>";
      $response = $this->client->translateText($text, null, $target_lang, [
        "tag_handling" => "html",
        "tag_handling_version" => "v2"
      ]);
      $text = $response->text;
      $text = preg_replace("/^<div>(.*)<\/div>$/s", "$1", $text);
      return $text;
    } catch (\Exception $e) {
      throw new \Exception("DeepL translation error: " . $e->getMessage());
    }
    throw new \Exception("Translation failed");
  }
}
