<?php

namespace app\controllers;

use yii\web\Controller;

class TestController extends Controller
{

    public function actionIndex()
    {
        sleep(1);
        return $this->render('index');
    }
}
