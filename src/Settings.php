<?php

namespace WPPolylangAutoTranslator;

class Settings
{
  public function init()
  {
    add_action("admin_menu", [$this, "add_settings_page"]);
    add_action("admin_init", [$this, "register_plugin_settings"]);
  }


  public function add_settings_page()
  {
    add_options_page(
      "Auto Translator 設定",
      "Auto Translator",
      "manage_options",
      "wp-polylang-auto-translator",
      [$this, "render_settings_page"]
    );
  }

  public function register_plugin_settings()
  {
    register_setting("auto_translator_settings_group", "auto_translator_engine");
    register_setting("auto_translator_settings_group", "auto_translator_deepl_api_key");
    register_setting("auto_translator_settings_group", "auto_translator_google_service_account_key");

    add_settings_section("main_section", "API設定", null, "wp-polylang-auto-translator");

    add_settings_field("engine", "翻訳エンジン", [$this, "engine_markup"], "wp-polylang-auto-translator", "main_section");
    add_settings_field("deepl_api_key", "DeepL APIキー", [$this, "deepl_api_key_markup"], "wp-polylang-auto-translator", "main_section");
    add_settings_field("google_service_account_key", "Google Cloud サービスアカウントキー (JSON)", [$this, "google_service_account_key_markup"], "wp-polylang-auto-translator", "main_section");
  }

  public function render_settings_page()
  {
?>
    <div class="wrap">
      <h1>Auto Translator 設定</h1>
      <form method="post" action="options.php">
        <?php
        settings_fields("auto_translator_settings_group");
        do_settings_sections("wp-polylang-auto-translator");
        submit_button();
        ?>
      </form>
    </div>
  <?php
  }

  public function deepl_api_key_markup()
  {
    $key = get_option("auto_translator_deepl_api_key");
    echo '<input name="auto_translator_deepl_api_key" value="' . esc_attr($key) . '" class="regular-text">';
  }

  public function google_service_account_key_markup()
  {
    $key = get_option("auto_translator_google_service_account_key");
    echo '<textarea name="auto_translator_google_service_account_key" rows="10" cols="50" class="regular-text code">' . esc_textarea($key) . '</textarea>';
  }

  public function engine_markup()
  {
    $engine = get_option("auto_translator_engine");
  ?>
    <select name="auto_translator_engine">
      <option value="deepl" <?php selected($engine, 'deepl'); ?>>DeepL API</option>
      <option value="google" <?php selected($engine, 'google'); ?>>Google Cloud Translation</option>
    </select>
<?php
  }
}
