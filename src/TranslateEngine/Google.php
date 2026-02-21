<?php

namespace WPPolylangAutoTranslator\TranslateEngine;

use WPPolylangAutoTranslator\TranslatorInterface;
use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;
use Google\Auth\Credentials\ServiceAccountCredentials;


class Google implements TranslatorInterface
{
  private $client;
  private $formattedParent;

  public function __construct(array $config)
  {
    $cred = new ServiceAccountCredentials(null, $config["json_key"]);
    $this->client = new TranslationServiceClient([
      "credentials" => $cred
    ]);
    $this->formattedParent = $this->client->locationName($config["json_key"]["project_id"], "global");
  }

  public function translate(string $text, string $target_lang): string
  {
    $contents = [$text];

    try {
      $request = (new TranslateTextRequest())
        ->setContents($contents)
        ->setTargetLanguageCode($target_lang)
        ->setParent($this->formattedParent);
      $response = $this->client->translateText($request, [
        "format" => "html"
      ]);

      foreach ($response->getTranslations() as $translation) {
        return $translation->getTranslatedText();
      }
    } catch (\Exception $e) {
      throw new \Exception("Google translation error: " . $e->getMessage());
    }
    throw new \Exception("Translation failed");
  }
}
