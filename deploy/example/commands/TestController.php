<?php

namespace app\commands;

use yii\console\Controller;
use yii\console\ExitCode;


class TestController extends Controller
{

    public function actionIndex()
    {
        echo "Started\n";
        sleep(2);
        echo "Finished\n";

        return ExitCode::OK;
    }
}
