<?php

namespace modules\helpers\controllers;

use modules\helpers\Helpers;

use Craft;
use craft\web\Controller;
use craft\helpers\StringHelper;
use craft\elements\Entry;

class TemplateMakerController extends Controller {

  protected $allowAnonymous = ['template'];

  public function actionDefault() {

    // Extract all post paramaters as variables
    $data = json_decode(file_get_contents('php://input'));

    // Default response
    $response = [];

    try{

      $response = Helpers::$app->templateMaker->create($data);
      $response['success'] = true;

      // Craft::$app->getSession()->setNotice("Template Created");

    } catch(\Exception $e) {

      extract((array)$data);

      $response['error']        = true;
      $response['message']      = $e->getMessage();
      $response['templatePath'] = ltrim(rtrim('/'.$path, '/').'/'.$template.$timestamp.'.twig', '/');

      // Craft::$app->session->setError("Failed to create template");

    }

    return $this->asJson($response);

  }

}
