<?php

namespace WPPolylangAutoTranslator\TranslateEngine;

abstract class AbstractTranslateEngine
{
  public function translate(string $text, string $target_lang): string
  {
    $results = $this->translateBatch([$text], $target_lang);
    return count($results) > 0 ? $results[0] : "";
  }

  public function translateBatch(array $texts, string $target_lang): array
  {
    if (empty($texts)) {
      return [];
    }

    $unique_non_empty_texts = [];
    $text_to_unique_idx = []; // maps original index to unique list index

    foreach ($texts as $i => $text) {
      if (trim($text) === "") {
        $text_to_unique_idx[$i] = null;
        continue;
      }

      $search_idx = array_search($text, $unique_non_empty_texts);
      if ($search_idx !== false) {
        $text_to_unique_idx[$i] = $search_idx;
      } else {
        $text_to_unique_idx[$i] = count($unique_non_empty_texts);
        $unique_non_empty_texts[] = $text;
      }
    }

    if (empty($unique_non_empty_texts)) {
      return array_fill(0, count($texts), "");
    }

    $unique_results = $this->doTranslateBatch($unique_non_empty_texts, $target_lang);

    $results = [];
    foreach ($texts as $i => $text) {
      $unique_idx = $text_to_unique_idx[$i];
      if ($unique_idx === null) {
        $results[] = "";
      } else {
        $results[] = $unique_results[$unique_idx] ?? "";
      }
    }
    return $results;
  }

  /**
   * Performs the actual translation via the API.
   *
   * @param array $texts List of unique, non-empty HTML/string to translate.
   * @param string $target_lang Target language code.
   * @return array Translated strings in the same order as input.
   */
  abstract protected function doTranslateBatch(array $texts, string $target_lang): array;
}
