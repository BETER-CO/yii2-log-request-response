<?php

$config = [
    'id' => 'log-request-response-cli',
    'defaultRoute' => 'test/index',
    'enableCoreCommands' => false,
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['monolog', 'log', 'logRequestResponse'],
    'controllerNamespace' => 'app\commands',
    'components' => [
        'logRequestResponse' => [
            'class' => Beter\Yii2\LogRequestResponse\LogRequestResponseComponent::class,
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
                            'exec_type' => 'cli',
                            'host' => gethostname(), // or set it as you want
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
    ],
];

return $config;
