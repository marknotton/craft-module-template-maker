<?php

namespace modules\templatemaker\controllers;

use modules\templatemaker\TemplateMaker;
use modules\helpers\Helpers;

use Craft;
use craft\web\Controller;

class DefaultController extends Controller {

  protected $allowAnonymous = ['index'];

  public function actionIndex() {

    // Extract all post paramaters as variables
    $data = json_decode(file_get_contents('php://input'));

    // Default response
    $response = [];

    try{

      $response = TemplateMaker::$app->service->create($data);
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
