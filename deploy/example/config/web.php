<?php

$config = [
    'id' => 'log-request-response-web',
    'defaultRoute' => 'test/index',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['monolog', 'log' , 'logRequestResponse'],
    'components' => [
        'request' => [
            // !!! insert a secret key in the following (if it is empty) - this is required by cookie validation
            'cookieValidationKey' => '',
        ],
        // The request logger reads user status; this example always logs guests.
        'user' => [
            'class' => app\components\GuestUser::class,
        ],
        'logRequestResponse' => [
            'class' => Beter\Yii2\LogRequestResponse\LogRequestResponseComponent::class,
            'maxHeaderValueLength' => 256,
            'headersToMask' => [
                'Cookie',
                'x-Forwarded-for'
            ],
            'postParamPatternsToMask' => [
                '/password/i',
                '/csrf/i',
            ],
        ],
        'monolog' => [
            'class' => Beter\Yii2\Logging\MonologComponent::class,
            'channels' => [
                'main' => [
                    'handler' => [
                        [
                            'name' => 'standard_stream',
                            'label' => 'standard_stream',
                            'stream' => 'php://stderr',
                            'level' => 'info',
                            'bubble' => true,
                            'formatter' => [
                                'name' => 'console',
                                'colorize' => true,
                                'indentSize' => 4,
                                'trace_depth' => 10,
                            ],
                        ],
                    ],
                    'processor' => [
                        [
                            'name' => 'basic_processor',
                            'env' => YII_ENV, // dev, prod, etc
                            'app' => 'myapp',
                            'service' => 'myservice',
                            'exec_type' => 'web',
                            'host' => gethostname(), // or set it as you want
                        ],
                        [
                            'name' => 'correlation_id_processor',
                            'length' => 32,
                            'search_in_headers' => true,
                            'header_name' => 'X-Request-Id',
                        ],
                    ],
                ],
            ],
        ],
        'log' => [
            'traceLevel' => 0,
            'flushInterval' => 1,
            'targets'       => [
                'monolog-proxy'      => [
                    'class'          => Beter\Yii2\Logging\ProxyLogTarget::class,
                    'targetLogComponent' => [
                        'componentName' => 'monolog',
                        'logChannel' => 'main'
                    ],
                    'categories' => [],
                    'except' => [],
                    'exportInterval' => 1,
                    'levels'         => [
                        'error',
                        'warning',
                        'info',
                        'trace',
                    ],
                ],
            ],
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
];

return $config;
