<?php

namespace Beter\Yii2\LogRequestResponse;

use Beter\Yii2\LogRequestResponse\Exception\ConfigurationException;
use Beter\Yii2\LogRequestResponse\Exception\HandlerException;
use Beter\Yii2\LogRequestResponse\Exception\RuntimeException;
use Yii;
use yii\base\Component;
use yii\base\Application as BaseApplication;
use yii\base\Event;
use yii\helpers\StringHelper;
use yii\web\HeaderCollection;

class LogRequestResponseComponent extends Component
{
    const LOG_CATEGORY = __CLASS__;

    const MIN_ALLOWED_VALUE_FOR_MAX_HEADER_VALUE_LENGTH = 1;

    const MASKED = '[Masked]';
    const TRUNCATED = '[Truncated]';

    const SANITIZE_BODY_PARAMS_MAX_DEPTH = 3;

    protected bool $registered = false;

    /**
     * Each key represent excluded route, value is always (int) 1.
     *
     * This approach speed up search. You may use isset() instead of in_array. O(1) vs O(n).
     *
     * @var array
     */
    protected array $excludedRoutes = [];

    protected int $maxHeaderValueLength = 256;

    /**
     * Each key represent header name, value is always (int) 1.
     *
     * This approach speed up search. You may use isset() instead of in_array. O(1) vs O(n).
     *
     * @var array
     */
    protected array $headersToMask = [
        'cookie' => 1,
        'x-forwarded-for' => 1,
        'x-csrf-token' => 1,
    ];

    /**
     * List of POST param patterns to check for the masking.
     *
     * @var array
     */
    protected array $postParamPatternsToMask = [
        '/password/i',
        '/csrf/i',
    ];

    /**
     * List of query param patterns to check for the masking.
     *
     * @var array
     */
    protected array $queryParamPatternsToMask = [];

    /**
     * Sets the list of excluded routes for request and response logging.
     *
     * @param array $excludedRoutes array of non-empty strings
     *
     * @return $this
     *
     * @throws ConfigurationException
     */
    protected function setExcludedRoutes(array $excludedRoutes): self
    {
        foreach ($excludedRoutes as $route) {
            if (!is_string($route) || empty($route)) {
                throw new ConfigurationException(
                    'excludedRoutes must be an array of non-empty strings',
                    ['excludedRoutes' => $excludedRoutes]
                );
            }
        }

        $this->excludedRoutes = array_fill_keys($excludedRoutes, 1);

        return $this;
    }

    protected function setMaxHeaderValueLength(int $length): self
    {
        if ($length <= static::MIN_ALLOWED_VALUE_FOR_MAX_HEADER_VALUE_LENGTH) {
            throw new ConfigurationException(
                'maxHeaderValueLength must be a positive int and must be greater than ' .
                    static::MIN_ALLOWED_VALUE_FOR_MAX_HEADER_VALUE_LENGTH,
                ['maxHeaderValueLength' => $length]
            );
        }

        $this->maxHeaderValueLength = $length;

        return $this;
    }

    /**
     * Sets the list of headers to mask for request and response logging.
     *
     * @param array $headersToMask array of non-empty strings
     *
     * @return $this
     *
     * @throws ConfigurationException
     */
    protected function setHeadersToMask(array $headersToMask): self
    {
        foreach ($headersToMask as $header) {
            if (!is_string($header) || empty($header)) {
                throw new ConfigurationException(
                    'headersToMask must be an array of non-empty strings',
                    ['headersToMask' => $headersToMask]
                );
            }
        }

        $this->headersToMask = array_fill_keys($headersToMask, 1);

        return $this;
    }

    /**
     * Sets the list of patterns for the post params keys that will trigger post param value masking.
     *
     * @param array $postParamPatternsToMask array of non-empty strings
     *
     * @return $this
     *
     * @throws ConfigurationException
     */
    protected function setPostParamPatternsToMask(array $postParamPatternsToMask): self
    {
        foreach ($postParamPatternsToMask as $postParamPattern) {
            if (!is_string($postParamPattern) || empty($postParamPattern)) {
                throw new ConfigurationException(
                    'postParamPatternsToMask must be an array of non-empty strings',
                    ['postParamPatternsToMask' => $postParamPatternsToMask]
                );
            }

            if (@preg_match($postParamPattern, '') === false) {
                throw new ConfigurationException(
                    'postParamPatternsToMask must be an array of valid regex strings',
                    ['postParamPatternsToMask' => $postParamPatternsToMask]
                );
            }
        }

        $this->postParamPatternsToMask = $postParamPatternsToMask;

        return $this;
    }

    /**
     * Sets the patterns for query param names whose values will be masked in logged URLs.
     *
     * @param array $queryParamPatternsToMask array of non-empty regex strings
     * @return $this
     * @throws ConfigurationException
     */
    protected function setQueryParamPatternsToMask(array $queryParamPatternsToMask): self
    {
        foreach ($queryParamPatternsToMask as $pattern) {
            if (!is_string($pattern) || empty($pattern)) {
                throw new ConfigurationException(
                    'queryParamPatternsToMask must be an array of non-empty strings',
                    ['queryParamPatternsToMask' => $queryParamPatternsToMask]
                );
            }

            if (@preg_match($pattern, '') === false) {
                throw new ConfigurationException(
                    'queryParamPatternsToMask must be an array of valid regex strings',
                    ['queryParamPatternsToMask' => $queryParamPatternsToMask]
                );
            }
        }

        $this->queryParamPatternsToMask = $queryParamPatternsToMask;

        return $this;
    }

    public function init()
    {
        parent::init();

        $this->registerEventListeners();
    }

    protected function registerEventListeners(): self
    {
        if ($this->registered) {
            Yii::warning(
                new HandlerException('Attempt to register event listeners after they were already registered'),
                __METHOD__
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
            Yii::error(
                new HandlerException('An error occurred during the context gathering', $context, $t),
                __METHOD__
            );
        }

        Yii::info('CLI command start', static::LOG_CATEGORY, $context);
    }

    public function cliResponseHandler($event)
    {
        $context = [];
        try {
            if (isset($_SERVER['argv'])) {
                $context['command'] = implode(' ', $_SERVER['argv']);
            }

            $context['exitStatus'] = Yii::$app->response->exitStatus;
            $context['execTimeSec'] = microtime(true) - YII_BEGIN_TIME;
            $context['memoryPeakUsageBytes'] = memory_get_peak_usage(true);
        } catch (\Throwable $t) {
            Yii::error(
                new HandlerException('An error occurred during the context gathering', $context, $t),
                __METHOD__
            );
        }

        Yii::info('CLI command end', static::LOG_CATEGORY, $context);
    }

    public function webRequestHandler($event)
    {
        /** @var \yii\web\Request $request */
        $request = Yii::$app->request;

        $context = [
            'user' => [],
            'request' => [],
            'headers' => [],
        ];

        try {
            $context['user']['id'] = Yii::$app->user->getIsGuest() ? '0' : Yii::$app->user->identity->getId();
            $context['user']['username'] = Yii::$app->user->getIsGuest() ? '[guest]' : Yii::$app->user->identity->username;
            $context['request']['method'] = $request->getMethod();
            $context['request']['absoluteUrl'] = $this->sanitizeQueryParams($request->getAbsoluteUrl());
            $context['request']['bodyParams'] = $this->sanitizeBodyParams($request->getBodyParams());
            $context['request']['userIp'] = $request->getUserIP();
            $context['headers'] = $this->sanitizeHeaders($request->getHeaders());
        } catch (\Throwable $t) {
            Yii::error(
                new HandlerException('An error occurred during the context gathering', $context, $t),
                __METHOD__
            );
        }

        // must be here, we may log a part of content at least
        Yii::info('Incoming request', static::LOG_CATEGORY, $context);
    }

    public function webResponseHandler($event)
    {
        $context = [];
        try {
            /** @var \yii\web\Request $request */
            $request = Yii::$app->request;

            /** @var \yii\web\Response $response */
            $response = $event->sender;

            $context['request'] = [
                'method' => $request->getMethod(),
                'absoluteUrl' => $this->sanitizeQueryParams($request->getAbsoluteUrl())
            ];

            $context['response'] = [
                'statusCode' => $response->getStatusCode(),
                'format' => $response->format,
                'isStream' => $response->stream !== null,
                'contentLength' => $response->stream !== null ? null : StringHelper::byteLength($response->content),
                'headers' => $this->sanitizeHeaders($response->getHeaders())
            ];

            $context['execTimeSec'] = microtime(true) - YII_BEGIN_TIME;
            $context['memoryPeakUsageBytes'] = memory_get_peak_usage(true);
        } catch (\Throwable $t) {
            Yii::error(
                new HandlerException('An error occurred during the context gathering', $context, $t),
                __METHOD__
            );
        }

        Yii::info('Outgoing response', static::LOG_CATEGORY, $context);
    }

    protected function isRouteExcluded(\yii\base\Request $request): bool
    {
        /**
         * $request->resolve() call is pretty expensive, so we may skip that call if there is nothing
         * to exclude.
         */
        if (empty($this->excludedRoutes)) {
            return false;
        }

        try {
            list($route) = $request->resolve();
            return isset($this->excludedRoutes[$route]);
        } catch (\Throwable $t) {
            // $request->resolve may generate NotFoundHttpException when it can't parse url. It's not
            // the situation when the route is not found!
        }

        return false;
    }

    protected function toFlatArray(HeaderCollection $headers): array
    {
        $flatArray = [];
        $duplicatedHeaders = false;

        foreach ($headers as $headerName => $headerValues) {
            // yii allows to set few header values for the same header name O_o
            $cnt = count($headerValues);
            if ($cnt > 1) {
                $duplicatedHeaders = true;
            }

            // process only the first value
            $flatArray[$headerName] = $cnt === 1 ? $headerValues[0] : '';
        }

        if ($duplicatedHeaders) {
            $e = new RuntimeException(
                'Few header values was found for the same header name. Check an attached context.',
                ['headers' => $headers]
            );
            Yii::warning($e, __METHOD__);
        }

        return $flatArray;
    }

    protected function sanitizeHeaders(HeaderCollection $headers): array
    {
        $sanitized = $this->toFlatArray($headers);

        foreach ($sanitized as $name => $value) {
            // for small arrays in_array is faster than isset
            if (isset($this->headersToMask[$name])) {
                $sanitized[$name] = static::MASKED;
                continue;
            }

            if (mb_strlen($value) > $this->maxHeaderValueLength) {
                $length = $this->maxHeaderValueLength - strlen(static::TRUNCATED);
                if ($length > 0) {
                    $sanitized[$name] = mb_substr($value, 0, $length) . '...' . static::TRUNCATED;
                } else {
                    // strange situation, but we need to comply our settings ;)
                    $sanitized[$name] = static::TRUNCATED;
                }

                continue;
            }
        }

        return $sanitized;
    }

    /**
     * @param $paramName
     * @return bool
     */
    protected function matchPostParamPatternsToMask($paramName): bool
    {
        foreach ($this->postParamPatternsToMask as $pattern) {
            if (preg_match($pattern, $paramName) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Masks or truncates body param values.
     *
     * @param mixed $bodyParams
     * @param int $depth
     * @return mixed
     */
    protected function sanitizeBodyParams($bodyParams, int $depth = 0)
    {
        if (!is_array($bodyParams)) {
            // yii\web\Request::getBodyParams() method returns array or object
            // I don't have an idea when object may be returned and what type of object it will be.
            return $bodyParams;
        }

        if (static::SANITIZE_BODY_PARAMS_MAX_DEPTH <= $depth) {
            return static::TRUNCATED;
        }

        $sanitized = [];

        foreach ($bodyParams as $name => $value) {
            if ($this->matchPostParamPatternsToMask($name)) {
                $sanitized[$name] = static::MASKED;
            } else {
                $sanitized[$name] = $this->sanitizeBodyParams($value, $depth + 1);
            }
        }

        return $sanitized;
    }

    /**
     * Masks matching query param values while preserving the rest of the URL.
     */
    protected function sanitizeQueryParams(string $url): string
    {
        if (empty($this->queryParamPatternsToMask)) {
            return $url;
        }

        $fragmentParts = explode('#', $url, 2);
        $urlParts = explode('?', $fragmentParts[0], 2);
        if (count($urlParts) < 2) {
            return $url;
        }

        $queryParams = explode('&', $urlParts[1]);
        foreach ($queryParams as &$param) {
            $parts = explode('=', $param, 2);
            $name = urldecode($parts[0]);
            foreach ($this->queryParamPatternsToMask as $pattern) {
                if (preg_match($pattern, $name) === 1) {
                    $param = $parts[0] . '=' . rawurlencode(static::MASKED);
                    break;
                }
            }
        }
        unset($param);

        return sprintf(
            '%s?%s%s',
            $urlParts[0],
            implode('&', $queryParams),
            isset($fragmentParts[1]) ? sprintf('#%s', $fragmentParts[1]) : ''
        );
    }

    protected function prepareSanitizedHeaderNames(): self
    {
        $this->headersToMask = array_change_key_case($this->headersToMask, CASE_LOWER);

        return $this;
    }
}
