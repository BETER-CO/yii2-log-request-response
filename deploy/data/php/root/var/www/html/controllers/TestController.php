<?php

namespace app\controllers;

use yii\web\Controller;

class TestController extends Controller
{
    public $layout = false;

    public function actionIndex()
    {
        sleep(1);
        return $this->render('index');
    }
}
