<?php

namespace WPPolylangAutoTranslator\TranslateEngine;

use DeepL\Translator;

class DeepL extends AbstractTranslateEngine
{
  private $client;

  public function __construct($config)
  {
    $this->client = new Translator($config["api_key"]);
  }

  protected function doTranslateBatch(array $texts, string $target_lang): array
  {
    try {
      $processed_texts = array_map(function ($text) {
        return "<div>" . $text . "</div>";
      }, $texts);

      $responses = $this->client->translateText($processed_texts, null, $target_lang, [
        "tag_handling" => "html",
        "tag_handling_version" => "v2"
      ]);

      if (!is_array($responses)) {
        $responses = [$responses];
      }

      return array_map(function ($response) {
        $text = $response->text;
        return preg_replace("/^<div>(.*)<\/div>$/s", "$1", $text);
      }, $responses);
    } catch (\Exception $e) {
      throw new \Exception("DeepL translation error: " . $e->getMessage());
    }
  }
}
