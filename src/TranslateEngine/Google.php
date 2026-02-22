<?php

namespace WPPolylangAutoTranslator\TranslateEngine;

use Google\Cloud\Translate\V3\Client\TranslationServiceClient;
use Google\Cloud\Translate\V3\TranslateTextRequest;
use Google\Auth\Credentials\ServiceAccountCredentials;


class Google extends AbstractTranslateEngine
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

  protected function doTranslateBatch(array $texts, string $target_lang): array
  {
    try {
      $request = (new TranslateTextRequest())
        ->setContents($texts)
        ->setTargetLanguageCode($target_lang)
        ->setParent($this->formattedParent);
      $response = $this->client->translateText($request, [
        "format" => "html"
      ]);

      $results = [];
      foreach ($response->getTranslations() as $translation) {
        $results[] = $translation->getTranslatedText();
      }
      return $results;
    } catch (\Exception $e) {
      throw new \Exception("Google translation error: " . $e->getMessage());
    }
  }
}
