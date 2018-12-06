<?php

namespace modules\templatemaker;

use modules\templatemaker\assets\TemplateMakerAssets;

use modules\templatemaker\services\Services;

use Craft;
use craft\i18n\PhpMessageSource;

use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\TemplateEvent;

use craft\services\Fields;
use craft\web\View;
use craft\web\UrlManager;
use craft\web\twig\variables\CraftVariable;

use yii\base\Event;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\web\NotFoundHttpException;

class TemplateMaker extends Module {

	public static $app;
	public static $config;

  //////////////////////////////////////////////////////////////////////////////
  // Construct
  //////////////////////////////////////////////////////////////////////////////

  public function __construct($id, $parent = null, array $config = [])  {

    Craft::setAlias('@modules/templatemaker', $this->getBasePath());
    Craft::setAlias('@template-maker', $this->getBasePath());

    $this->controllerNamespace = 'modules\templatemaker\controllers';

    $i18n = Craft::$app->getI18n();
    if (!isset($i18n->translations[$id]) && !isset($i18n->translations[$id.'*'])) {
      $i18n->translations[$id] = [
        'class'            => PhpMessageSource::class,
        'sourceLanguage'   => 'en',
        'basePath'         => '@modules/templatemaker/translations',
        'forceTranslation' => true,
        'allowOverrides'   => true,
      ];
    }
    // Base template directory
    Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function (RegisterTemplateRootsEvent $e) {
      if (is_dir($baseDir = $this->getBasePath().DIRECTORY_SEPARATOR.'templates')) {
        $e->roots[$this->id] = $baseDir;
      }
    });

    static::setInstance($this);

    parent::__construct($id, $parent, $config);
  }

  //////////////////////////////////////////////////////////////////////////////
  // Init
  //////////////////////////////////////////////////////////////////////////////

  public function init()  {

    parent::init();

    if ( !Craft::$app->getRequest()->getIsConsoleRequest() ) {

			self::$app = $this;
      // self::$config = TemplateMaker::$app->service->getConfig();

      Event::on(
        CraftVariable::class,
        CraftVariable::EVENT_INIT,
        function (Event $event) {
          /** @var CraftVariable $variable */
          $variable = $event->sender;
          $variable->set('templatemaker', Variables::class);
        }
      );

      // Run these within the CMS backend. Not the frontend.
      if (Craft::$app->getRequest()->getIsCpRequest()) {
        Event::on(
          View::class,
          View::EVENT_BEFORE_RENDER_TEMPLATE,
          function (TemplateEvent $event) {
            try {
              Craft::$app->getView()->registerAssetBundle(TemplateMakerAssets::class);
            } catch (InvalidConfigException $e) {
              Craft::error(
                'Error registering AssetBundle - '.$e->getMessage(),
                __METHOD__
              );
            }
          }
        );
      }
    }


    // TODO: Only allow TemplateMaker to set if it's a CP Request or Controller Request.
    // The follow condition doesn't seem to work if a controller is calling TemplateMaker
    // if ( Craft::$app->getRequest()->getIsActionRequest() || Craft::$app->getRequest()->getIsCpRequest() ) {
      // Add templateMaker class if template-maker is enabled in the config/helpers.php
      if (getenv('ENVIRONMENT') == 'dev' ) {
        TemplateMaker::$app->service->init();
      }
    // }

    // Register site routes
    Event::on(
      UrlManager::class,
      UrlManager::EVENT_REGISTER_SITE_URL_RULES,
      function (RegisterUrlRulesEvent $event) {
        $event->rules['template-maker'] = 'template-maker/default';
      }
    );

    Craft::info(
      Craft::t(
        'template-maker',
        '{name} loaded',
        ['name' => 'TemplateMaker']
      ),
      __METHOD__
    );
  }

}
