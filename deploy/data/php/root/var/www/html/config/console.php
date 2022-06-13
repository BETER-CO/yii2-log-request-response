<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

$config = [
    'id' => 'basic-console',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['monolog', 'log', 'logRequestResponse'],
    'controllerNamespace' => 'app\commands',
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
        '@tests' => '@app/tests',
    ],
    'components' => [
        'cache' => [
            'class' => 'yii\caching\FileCache',
        ],
        'logRequestResponse' => [
            'class' => Beter\Yii2\LogRequestResponse\LogRequestResponseComponent::class,
            'excludedRoutes' => [
                'debug/default/toolbar',
                'debug/default/view',
                'debug/default/index',
                'debug/default/db-explain',
            ],
            'maxHeaderValueLength' => 256,
            'headersToMask' => [
                'Cookie',
                'x-Forwarded-for'
            ],
        ],
        'monolog' => [
            'class' => Beter\Yii2BeterLogging\MonologComponent::class,
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
                            ]
                        ]
                    ],
                ],
                'processor' => [
                    [
                        'name' => 'basic_processor',
                        'env' => YII_ENV, // dev, prod, etc
                        'app' => 'myapp',
                        'service' => 'api',
                        'host' => gethostname(), // or set it as you want
                    ]
                ],
            ],
        ],
        'log' => [
            'traceLevel' => 0,
            'flushInterval' => 1,
            'targets'       => [
                'monolog-proxy'      => [
                    'class'          => Beter\Yii2BeterLogging\ProxyLogTarget::class,
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
        'db' => $db,
    ],
    'params' => $params,
    /*
    'controllerMap' => [
        'fixture' => [ // Fixture generation command line.
            'class' => 'yii\faker\FixtureController',
        ],
    ],
    */
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => 'yii\gii\Module',
    ];
}

return $config;
