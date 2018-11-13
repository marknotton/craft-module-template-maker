<?php

namespace modules\helpers\assets;

use Craft;
use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class TemplateMakerAssets extends AssetBundle {

  public function init() {
    $this->sourcePath = "@helpers/assets";

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
