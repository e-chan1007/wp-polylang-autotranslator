<?php

namespace WPPolylangAutoTranslator;

use WPPolylangAutoTranslator\TranslatorInterface;
use WPPolylangAutoTranslator\TranslateEngine;

class TranslationManager
{
  private function get_translator_engine(): TranslatorInterface
  {
    $engine_type = get_option("auto_translator_engine", "deepl");
    if ($engine_type === "google") {
      $key = get_option("auto_translator_google_service_account_key") ?: "{}";
      return new TranslateEngine\Google(["json_key" => json_decode($key, true)]);
    }
    if ($engine_type === "deepl") {
      $api_key = get_option("auto_translator_deepl_api_key") ?: "_";
      return new TranslateEngine\DeepL(["api_key" => $api_key]);
    }
    throw new \Exception("Invalid translation engine specified");
  }

  public function init()
  {
    add_action("enqueue_block_editor_assets", [$this, "enqueue_editor_assets"]);
    add_action("save_post", [$this, "handle_save_event"], 20);
    add_action("add_meta_boxes", [$this, "add_auto_translate_trigger"]);
    add_action("init", [$this, "register_meta_fields"]);
  }

  public function enqueue_editor_assets()
  {
    wp_enqueue_script(
      "wp-polylang-auto-translator-js",
      plugins_url("js/editor-notice.js", __FILE__),
      ["wp-data", "wp-editor", "wp-element", "wp-notices"],
      filemtime(plugin_dir_path(__FILE__) . 'js/editor-notice.js'),
      true
    );
  }

  public function add_auto_translate_trigger($post_type)
  {
    $post_id = get_the_ID();
    if (
      !pll_is_translated_post_type($post_type) ||
      pll_get_post_language($post_id) !== pll_default_language()
    ) return false;

    add_meta_box(
      "auto_trans_trigger",
      "自動翻訳設定",
      [$this, "render_auto_translate_trigger"],
      $post_type,
      "side"
    );
  }

  public function register_meta_fields()
  {
    register_post_meta("", "_auto_translate_error", [
      "show_in_rest" => true,
      "single"       => true,
      "type"         => "string",
    ]);
  }

  public function render_auto_translate_trigger()
  {
    wp_nonce_field("save_auto_trans_trigger", "should_auto_translate_nonce");
?>
    <label>
      <input type="checkbox" name="should_auto_translate">
      保存時に他言語の記事を自動で生成する
    </label>
    <p class="description">記事の保存時に翻訳APIを実行して、他言語の記事を自動生成します。既に翻訳記事がある場合は内容を上書き更新します。</p>
<?php
  }

  public function handle_save_event($post_id)
  {
    delete_post_meta($post_id, "_auto_translate_error");
    if (wp_is_post_revision($post_id)) return;

    if (!$this->should_translate($post_id)) {
      if (get_post_meta($post_id, "auto_translated", true)) {
        delete_post_meta($post_id, "auto_translated");
      }
      return;
    }

    $hook = current_filter();
    remove_action($hook, [$this, "handle_save_event"], 20);
    try {
      $this->translate_post($post_id);
      delete_post_meta($post_id, "_auto_translate_error");
    } catch (\Exception $e) {
      update_post_meta($post_id, "_auto_translate_error", "翻訳の実行中にエラーが発生しました: " . $e->getMessage());
    }
    add_action($hook, [$this, "handle_save_event"], 20);
  }

  private function should_translate($post_id)
  {
    if (!isset($_POST["should_auto_translate_nonce"]) || !wp_verify_nonce($_POST["should_auto_translate_nonce"], "save_auto_trans_trigger")) {
      return false;
    }

    if (!isset($_POST["should_auto_translate"]) || $_POST["should_auto_translate"] !== "on") {
      return false;
    }

    if (!function_exists("pll_get_post_language")) return false;
    if (pll_get_post_language($post_id) !== pll_default_language()) return false;

    return true;
  }

  private function translate_post($source_id)
  {
    $translations = pll_get_post_translations($source_id);
    $languages = pll_languages_list();

    foreach ($languages as $lang) {
      if ($lang === pll_default_language()) continue;
      $target_post_id = isset($translations[$lang]) ? $translations[$lang] : null;
      $translated_data = [
        "post_title"   => $this->get_translator_engine()->translate(get_the_title($source_id), $lang),
        "post_content" => $this->get_translator_engine()->translate(get_post_field("post_content", $source_id), $lang),
        "post_status"  => get_post_status($source_id),
        "post_type"    => get_post_type($source_id),
      ];

      if ($target_post_id) {
        $translated_data["ID"] = $target_post_id;
        wp_update_post($translated_data);
      } else {
        $target_post_id = wp_insert_post($translated_data);
        pll_set_post_language($target_post_id, $lang);
        $translations[$lang] = $target_post_id;
      }
      update_post_meta($target_post_id, "auto_translated", true);

      if (function_exists("get_field_objects")) {
        $this->translate_acf_fields($source_id, $target_post_id, $lang);
      }
    }
    pll_save_post_translations($translations);
  }

  private function translate_acf_fields($from_id, $to_id, $lang)
  {
    $fields = get_field_objects($from_id);
    if (!$fields) return;

    foreach ($fields as $field) {
      $translated_value = $this->process_acf_field_value($field, $lang);
      update_field($field["key"], $translated_value, $to_id);
    }
  }

  private function process_acf_field_value($field, $lang)
  {
    $type = $field["type"];
    $value = $field["value"];

    $text_types = ["text", "textarea", "wysiwyg"];
    if (in_array($type, $text_types) && !empty($value)) {
      return $this->get_translator_engine()->translate($value, $lang);
    }

    if ($type === "repeater" && is_array($value)) {
      foreach ($value as $i => $row) {
        foreach (array_keys($row) as $sub_field_key) {
          $sub_field = get_sub_field_object($sub_field_key);
          $value[$i][$sub_field_key] = $this->process_acf_field_value($sub_field, $lang);
        }
      }
      return $value;
    }

    if ($type === "group" && is_array($value)) {
      foreach (array_keys($value) as $sub_field_key) {
        $sub_field = get_sub_field_object($sub_field_key);
        $value[$sub_field_key] = $this->process_acf_field_value($sub_field, $lang);
      }
      return $value;
    }

    return $value;
  }
}
