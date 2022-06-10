<?php

namespace Beter\LogRequest;

use Beter\Yii2\LogRequestResponse\Exception\HandlerException;
use Yii;
use yii\base\Component;
use yii\base\Application as BaseApplication;
use yii\base\Event;
use yii\helpers\StringHelper;
use yii\web\HeaderCollection;
use yii\web\NotFoundHttpException;

class LogRequestResponseComponent extends Component
{
    const LOG_CATEGORY = 'requestResponseData';

    protected bool $registered = false;

    protected $excludeRoutes = [
        'debug/default/toolbar',
        'debug/default/view',
        'debug/default/index',
        'debug/default/db-explain'
    ];

    protected int $maxHeaderValueLength = 256;

    protected array $maskHeaderNames = [
        'cookie',
        'x-forwarded-for'
    ];

    public function init()
    {
        parent::init();

        $this->registerEventListeners();
    }

    protected function registerEventListeners(): self
    {
        if ($this->registered) {
            Yii::warning(
                new HandlerException('Attempt to register event listeners after they were already registered'), __METHOD__
            );
            return $this;
        }

        /** @var \yii\web\Request|\yii\console\Request $request */
        $request = Yii::$app->request;
        if ($this->isRouteExcluded($request)) {
            return $this;
        }

        if (Yii::$app instanceof \yii\web\Application) {

            $this->prepareSanitizedHeaderNames();

            Yii::$app->on(BaseApplication::EVENT_BEFORE_REQUEST, [$this, 'webRequestHandler']);

            // Application::EVENT_AFTER_REQUEST is not the best option. Handlers may change the content or headers.
            Event::on(\yii\web\Response::class, \yii\web\Response::EVENT_AFTER_SEND, [$this, 'webResponseHandler']);
        } elseif (Yii::$app instanceof \yii\console\Application) {
            Yii::$app->on(BaseApplication::EVENT_BEFORE_REQUEST, [$this, 'cliRequestHandler']);
            Yii::$app->on(BaseApplication::EVENT_AFTER_REQUEST, [$this, 'cliResponseHandler']);
        }

        $this->registered = true;

        return $this;

    }

    public function cliRequestHandler($event)
    {
        $context = [];
        try {
            if (isset($_SERVER['argv'])) {
                $context['command'] = implode(' ', $_SERVER['argv']);
            }
        } catch (\Throwable $t) {
            \Yii::error($t, __METHOD__, $context);
        }

        \Yii::info('CLI command start', static::LOG_CATEGORY, $context);
    }

    public function cliResponseHandler($event)
    {
        $context = [];
        try {
            $context['exitStatus'] = Yii::$app->response->exitStatus;
            $context['execTimeSec'] = microtime(true) - YII_BEGIN_TIME;
            $context['memoryPeakUsageBytes'] = memory_get_peak_usage(true);
        } catch (\Throwable $t) {
            \Yii::error($t, __METHOD__, $context);
        }

        \Yii::info('CLI command end', static::LOG_CATEGORY, $context);
    }

    public function webRequestHandler($event)
    {
        /** @var \yii\web\Request $request */
        $request = Yii::$app->request;
        if ($this->isRouteExcluded($request)) {
            return;
        }

        $context = [
            'user' => [],
            'request' => [],
            'headers' => [],
        ];

        try {
            $context['user']['id'] = Yii::$app->user->getIsGuest() ? '0' : Yii::$app->user->identity->getId();
            $context['user']['username'] = Yii::$app->user->getIsGuest() ? '[guest]' : Yii::$app->user->identity->username;
            $context['request']['method'] = $request->getMethod();
            $context['request']['absoluteUrl'] = $request->getAbsoluteUrl();
            $context['request']['bodyPrams'] = $request->getBodyParams();
            $context['request']['referrer'] = $request->getReferrer();
            $context['request']['userIp'] = $request->getUserIP();
            $context['request']['userAgent'] = $request->getUserAgent();
            $context['headers'] = $this->sanitizeHeaders($request->getHeaders());
        } catch (\Throwable $t) {
            \Yii::error($t, __METHOD__, $context);
        }

        // must be here, we may log a part of content at least
        \Yii::info('Incoming request', static::LOG_CATEGORY, $context);
    }

    public function webResponseHandler($event)
    {
        $context = [];
        try {
            /** @var \yii\web\Response $response */
            $response = $event->sender;
            $context['statusCode'] = $response->getStatusCode();
            $context['format'] = $response->format;
            if ($response->stream !== null) {
                $context['isStream'] = true;
            } else {
                $context['isStream'] = false;
                $context['contentLength'] = StringHelper::byteLength($response->content);
            }

            $context['headers'] = $this->sanitizeHeaders($response->getHeaders());
            $context['execTimeSec'] = microtime(true) - YII_BEGIN_TIME;
            $context['memoryPeakUsageBytes'] = memory_get_peak_usage(true);
        } catch (\Throwable $t) {
            \Yii::error($t, __METHOD__, $context);
        }

        \Yii::info('Outgoing response', static::LOG_CATEGORY, $context);
    }

    protected function getElapsedTimeFromScriptStartMsec(): int
    {
        return intval(ceil((microtime(true) - YII_BEGIN_TIME) * 1000));
    }

    protected function isRouteExcluded(\yii\base\Request $request)
    {
        try {
            list($route, $params) = $request->resolve();
            if (in_array($route, $this->excludeRoutes)) {
                return true;
            }
        } catch (NotFoundHttpException $e) {
            // don't exclude not found routes
        }

        return false;
    }

    protected function toFlatArray(HeaderCollection $headers): array
    {
        $flatArray = [];
        $duplicatedHeaders = false;

        foreach ($headers as $headerName => $headerValues) {
            // yii allows to set few header values for the same header name O_o
            if (count($headerValues) > 1) {
                $duplicatedHeaders = true;
            }

            // process only the first value
            $flatArray[$headerName] = $headerValues[0];
        }

        if ($duplicatedHeaders) {
            $e = new BaseException(
                'Few header values was found for the same header name. Check attached context',
                ['headers' => $headers]
            );
            Yii::warning($e);
        }

        return $flatArray;
    }

    protected function sanitizeHeaders(HeaderCollection $headers): array
    {
        $sanitized = $this->toFlatArray($headers);
        $truncatedStr = '... [truncated]';

        foreach ($sanitized as $name => $value) {
            // for small arrays in_array is faster than isset
            if (in_array($name, $this->maskHeaderNames)) {
                $sanitized[$name] = '[Masked]';
                continue;
            }

            if (mb_strlen($value) > $this->maxHeaderValueLength) {
                $sanitized[$name] = mb_substr($value, 0, $this->maxHeaderValueLength - strlen($truncatedStr)) .
                    $truncatedStr;

                continue;
            }
        }

        return $sanitized;
    }

    protected function prepareSanitizedHeaderNames(): self
    {
        $this->maskHeaderNames = array_map('strtolower', $this->maskHeaderNames);
        return $this;
    }
}
