<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Request and response logging</title>
</head>
<body>
    <h1>Request and response logging</h1>
    <p>Check the PHP service logs for this request and response.</p>
    <p>Submit the form to check masking of nested passwords and CSRF values.</p>

    <?= Html::beginForm(['test/index', 'source' => 'example-form', 'secret-token' => 'example query secret'], 'post') ?>
        <input type="hidden" name="csrf-demo" value="example CSRF secret">
        <p>
            <label>Username <input name="user[username]" value="test-user"></label>
        </p>
        <p>
            <label>Password <input type="password" name="user[password]" value="example password"></label>
        </p>
        <button type="submit">Test POST logging</button>
    <?= Html::endForm() ?>
</body>
</html>
