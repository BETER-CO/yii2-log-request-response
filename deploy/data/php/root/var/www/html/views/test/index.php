<?php

/** @var yii\web\View $this */

$this->title = 'My Yii Application';
?>
<div class="site-index">
    <div class="body-content">

        <div class="row">
            <div class="col-lg-12">
                <h2>Web request/response test.</h2>

                <p>
                    You already did the request, so check stderr output and find all information you need.
                </p>

                <p>
                    You may test POST request with bodyParams (password, csrf) to test sanitize function.
                    Even if you see BadRequestHttpException you may see logged data and masked POST params.
                </p>

                <p><a class="btn btn-outline-secondary" href="<?php echo \yii\helpers\Url::to(['test/post']) ?>">Test POST request</a></p>

                <form action="" method="post">
                    <input type='hidden' name='_csrf-main' value='secret data!'>
                    <input type='hidden' name='login-button' value='button no 1'>
                    <input type='hidden' name='LoginForm[username]' value='user1'>
                    <input type='hidden' name='LoginForm[password]' value='password of the user'>
                    <input type="submit" value="Test POST">
                </form>

            </div>
        </div>
    </div>
</div>
