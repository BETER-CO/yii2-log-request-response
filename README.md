# yii2-log-request-response

## Installation

The preferred way to install this extension is through [composer](https://getcomposer.org/).

Either run

```
composer require beter/yii2-log-request-response
```

or add

```
"beter/yii2-log-request-response": "~1.0.0" // put the latest version here
```

to the require section of your composer.json.

## Masking query parameters

Configure `queryParamPatternsToMask` with regular expressions matching sensitive query
parameter names:

```php
'logRequestResponse' => [
    'class' => \Beter\Yii2\LogRequestResponse\LogRequestResponseComponent::class,
    'queryParamPatternsToMask' => ['/token/i', '/^api_key$/i'],
],
```

Matching values are replaced with URL-encoded `[Masked]` in both request and response
log URLs. The default is an empty array, which leaves query parameters unchanged.

## Further reading

* [Development and testing](doc/development-and-testing.md)
