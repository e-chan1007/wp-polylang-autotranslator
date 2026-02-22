<?php

/**
 * Plugin Name: Polylang Automatic Translator
 * Description: Polylangを使用しているWordPressサイトの翻訳を自動化するプラグインです。ACFのカスタムフィールドも翻訳可能です。
 * Version: 0.0.2
 * Requires Plugins: polylang
 */

if (!defined("ABSPATH")) exit;

require_once __DIR__ . "/vendor/autoload.php";
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4("WPPolylangAutoTranslator\\", __DIR__ . "/src/");
$loader->register(true);

$settings = new WPPolylangAutoTranslator\Settings();
$settings->init(plugin_basename(__FILE__));

$processor = new WPPolylangAutoTranslator\TranslationManager();
$processor->init();
