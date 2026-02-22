<?php

namespace WPPolylangAutoTranslator;

use WPPolylangAutoTranslator\TranslateEngine\AbstractTranslateEngine;
use WPPolylangAutoTranslator\TranslateEngine;

class TranslationManager
{
  private function get_translator_engine(): AbstractTranslateEngine
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
    if (!pll_is_translated_post_type($post_type)) return;
    add_meta_box(
      "auto_translate_trigger",
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
      "auth_callback" => function () {
        return current_user_can("edit_posts");
      }
    ]);
  }

  public function render_auto_translate_trigger()
  {
    wp_nonce_field("save_auto_translate_trigger", "should_auto_translate_nonce");

    $post_id = get_the_ID();

    if (
      pll_get_post_language($post_id) === pll_default_language()
    ) {
?>
      <label>
        <input type="checkbox" name="should_auto_translate">
        保存時に他言語の記事を自動で生成する
      </label>
      <p class="description">記事の保存時に翻訳APIを実行して、他言語の記事を自動生成します。既に翻訳記事がある場合は内容を上書き更新します。</p>
    <?php
    } else {
    ?>
      <label>
        <input type="checkbox" name="was_auto_translated" <?php checked(get_post_meta($post_id, "auto_translated", true)); ?>>
        自動翻訳による記事として表示
      </label>
      <p class="description">この投稿は自動翻訳機能によって生成されたものであることを示します。テーマの設定によって、翻訳された記事を区別して表示することができます。</p>
<?php
    }
  }

  public function handle_save_event($post_id)
  {
    if (wp_is_post_revision($post_id)) return;

    $nonce_valid = isset($_POST["should_auto_translate_nonce"]) &&
      wp_verify_nonce($_POST["should_auto_translate_nonce"], "save_auto_translate_trigger");

    if (!$nonce_valid) return;

    $hook = current_filter();
    remove_action($hook, [$this, "handle_save_event"], 20);

    if (!$this->should_translate($post_id)) {
      delete_post_meta($post_id, "_auto_translate_error");
      if (isset($_POST["was_auto_translated"]) && $_POST["was_auto_translated"] === "on") {
        update_post_meta($post_id, "auto_translated", true);
      } else {
        delete_post_meta($post_id, "auto_translated");
      }
    } else {
      try {
        $this->translate_post($post_id);
        delete_post_meta($post_id, "_auto_translate_error");
      } catch (\Exception $e) {
        update_post_meta($post_id, "_auto_translate_error", "翻訳の実行中にエラーが発生しました: " . $e->getMessage());
      }
    }
    add_action($hook, [$this, "handle_save_event"], 20);
  }

  private function should_translate($post_id)
  {
    if (!isset($_POST["should_auto_translate_nonce"]) || !wp_verify_nonce($_POST["should_auto_translate_nonce"], "save_auto_translate_trigger")) {
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
    clean_post_cache($source_id);

    $languages_slug = pll_languages_list(["fields" => "slug"]);
    $languages_locale = pll_languages_list(["fields" => "locale"]);
    $engine = $this->get_translator_engine();
    $default_lang = pll_default_language();

    foreach ($languages_slug as $i => $lang_slug) {
      if ($lang_slug === $default_lang) continue;

      $target_post_id = pll_get_post($source_id, $lang_slug);
      $translate_lang = str_replace("_", "-", $languages_locale[$i]);

      $source_post = get_post($source_id, ARRAY_A);

      $texts_to_translate = [
        "title"   => $source_post["post_title"],
        "content" => $source_post["post_content"],
        "excerpt" => $source_post["post_excerpt"],
      ];

      $acf_fields = [];
      if (function_exists("get_field_objects")) {
        $acf_fields = get_field_objects($source_id);
        if ($acf_fields) {
          foreach ($acf_fields as $field) {
            $this->collect_acf_texts($field, $texts_to_translate, "acf_" . $field["key"]);
          }
        }
      }

      $translated_values = $engine->translateBatch(array_values($texts_to_translate), $translate_lang);
      $results = array_combine(array_keys($texts_to_translate), $translated_values);

      unset($source_post["ID"]);
      unset($source_post["guid"]);

      $translated_data = array_merge($source_post, [
        "post_title"   => $results["title"],
        "post_content" => $results["content"],
        "post_excerpt" => $results["excerpt"],
        "post_name"    => $source_post['post_name'] . "-{$lang_slug}",
      ]);

      if ($target_post_id) {
        $translated_data["ID"] = $target_post_id;
        wp_update_post($translated_data);
      } else {
        $target_post_id = wp_insert_post($translated_data);
        pll_set_post_language($target_post_id, $lang_slug);
        $current_translations = pll_get_post_translations($source_id);
        $current_translations[$lang_slug] = $target_post_id;
        pll_save_post_translations($current_translations);
      }

      update_post_meta($target_post_id, "auto_translated", true);

      if ($acf_fields) {
        foreach ($acf_fields as $field) {
          $translated_value = $this->apply_acf_translations($field, $results, "acf_" . $field["key"]);
          update_field($field["key"], $translated_value, $target_post_id);
        }
      }

      if (class_exists("Permalink_Manager_URI_Functions")) {
        \Permalink_Manager_URI_Functions::save_single_uri($target_post_id, \Permalink_Manager_URI_Functions_Post::get_post_uri($source_id), false, true);
      }
    }
  }

  private function collect_acf_texts($field, &$texts, $path)
  {
    if (empty($field["value"])) return;

    $type = $field["type"] ?? "";
    $text_types = ["text", "textarea", "wysiwyg"];

    if (in_array($type, $text_types)) {
      $texts[$path] = $field["value"];
    } elseif ($type === "repeater" && is_array($field["value"])) {
      foreach ($field["value"] as $i => $row) {
        foreach ($field["sub_fields"] as $sub_field) {
          $sub_field_name = $sub_field["name"];
          if (isset($row[$sub_field_name])) {
            $sub_field_with_value = $sub_field;
            $sub_field_with_value["value"] = $row[$sub_field_name];
            $this->collect_acf_texts($sub_field_with_value, $texts, "{$path}.{$i}.{$sub_field_name}");
          }
        }
      }
    } elseif ($type === "group" && is_array($field["value"])) {
      foreach ($field["sub_fields"] as $sub_field) {
        $sub_field_name = $sub_field["name"];
        if (isset($field["value"][$sub_field_name])) {
          $sub_field_with_value = $sub_field;
          $sub_field_with_value["value"] = $field["value"][$sub_field_name];
          $this->collect_acf_texts($sub_field_with_value, $texts, "{$path}.{$sub_field_name}");
        }
      }
    }
  }

  private function apply_acf_translations($field, $results, $path)
  {
    $value = $field["value"];
    if (empty($value)) return $value;

    $type = $field["type"] ?? "";
    $text_types = ["text", "textarea", "wysiwyg"];

    if (in_array($type, $text_types)) {
      return $results[$path] ?? $value;
    } elseif ($type === "repeater" && is_array($value)) {
      foreach ($value as $i => $row) {
        foreach ($field["sub_fields"] as $sub_field) {
          $sub_field_name = $sub_field["name"];
          if (isset($row[$sub_field_name])) {
            $sub_field_with_value = $sub_field;
            $sub_field_with_value["value"] = $row[$sub_field_name];
            $value[$i][$sub_field_name] = $this->apply_acf_translations($sub_field_with_value, $results, "{$path}.{$i}.{$sub_field_name}");
          }
        }
      }
      return $value;
    } elseif ($type === "group" && is_array($value)) {
      foreach ($field["sub_fields"] as $sub_field) {
        $sub_field_name = $sub_field["name"];
        if (isset($value[$sub_field_name])) {
          $sub_field_with_value = $sub_field;
          $sub_field_with_value["value"] = $value[$sub_field_name];
          $value[$sub_field_name] = $this->apply_acf_translations($sub_field_with_value, $results, "{$path}.{$sub_field_name}");
        }
      }
      return $value;
    }

    return $value;
  }
}
