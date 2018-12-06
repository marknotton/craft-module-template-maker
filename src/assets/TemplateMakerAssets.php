<?php

namespace modules\templatemaker\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class TemplateMakerAssets extends AssetBundle {

  public function init() {
    $this->sourcePath = "@template-maker/assets";

    $this->depends = [
      CpAsset::class,
    ];

    $this->js = [
      'scripts/template-maker.js',
    ];

    $this->css = [
      'css/template-maker.css',
    ];

    parent::init();
  }
}
