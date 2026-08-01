<?php

namespace SummerCraft\Service\Network;

use SummerCraft\Service\Logger\Logger;
use Throwable;

class DefaultCurlNetwork implements Network
{
    private const FAKE_AGENT = [
        'headers' => [

        ],
        'options' => [
            'useragent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/69.0.3497.100 Safari/537.36',
        ],
    ];

    private CookieJar $cookieJar;

    public function __construct(
        protected Logger $logger,
    ) {
        $this->cookieJar = new CookieJar();
    }

    /**
     * Информация о заголовках последнего запрсоа curlRequest
     * header, cookie
     * @var array
     */
    private array $lastResponseInfo = array('header' => '','cookie' => []);

    /**
     * Выполняет запрос к URL через curl.
     * Позволяет сохранять cookie между запросами.
     * Заголовок и куки сохраняются в $this->lastResponseInfo.
     * @param string $url URL
     * @param string $method Method
     * @param array $post_data Для POST array(key => value)
     * @param array $additionalHeaders Дополнительные заголовки array('Accept:application/json');
     * <br/><b>fake</b> - true - Fake browser emulating
     * <br/><b>saveCookie</b> - true - Сохраняет полностью заголовоки
     * <br/><b>cookie</b> - дополнительные cookie для запроса, поверх тех, что этот
     *      инстанс уже сам подставит из cookie jar для хоста этого URL
     * <br/><b>noCookieJar</b> - true - не подставлять cookie jar для этого запроса
     * <br/><b>useragent</b> - UserAgent
     * <br/><b>no_redirects</b> - No redirect
     * <br/><b>referer</b> - Referer
     * <br/><b>connectTimeout</b> - seconds allowed for establishing the connection
     * <br/><b>timeout</b> - seconds allowed for the whole request, response
     *      included; without it a server that accepts the connection and then
     *      stalls holds the caller indefinitely
     * @return string|null Тело ответа
     */
    public function httpRequest(
        string $url,
        string $method = self::METHOD_GET,
        array $post_data = [],
        array $additionalHeaders = [],
        array $options = []
    ): ?string {
        if (isset($options['fake'])) {
            $options = array_merge(self::FAKE_AGENT['options'], $options);
            $additionalHeaders = array_merge(self::FAKE_AGENT['headers'], $additionalHeaders);
        }

        $requestHost = (string)(parse_url($url, PHP_URL_HOST) ?? '');

        $saveHeader = (isset($options['saveCookie'])) ? true : false;
        $cookies = empty($options['noCookieJar']) ? $this->cookieJar->forHost($requestHost) : [];
        if (isset($options['cookie']) && is_array($options['cookie'])) {
            $cookies = array_merge($cookies, $options['cookie']);
        }
        $cookie = '';
        foreach ($cookies as $key => $value) {
            $cookie .= $key.'='.$value.'; ';
        }
        if ($method === self::METHOD_HEAD) {
            $saveHeader = true;
        }
        $secure = (strpos($url, 'https:') === 0) ? true : false;
        try {
            $ch = curl_init();
            curl_setopt($ch,CURLOPT_URL,$url);
            curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, $options['connectTimeout'] ?? 10);
            curl_setopt($ch,CURLOPT_TIMEOUT, $options['timeout'] ?? 30);
            if ($saveHeader) {
                curl_setopt($ch, CURLOPT_HEADER,true);
            } else {
                curl_setopt($ch, CURLOPT_HEADER,false);
            }
            if ($cookie){
                curl_setopt($ch, CURLOPT_COOKIE, $cookie);
            }
            if (!empty($options['referer'])){
                curl_setopt($ch, CURLOPT_REFERER, $options['referer']);
            }
            if (isset($options['encoding']) && $options['encoding'] === 'gzip'){
                curl_setopt($ch, CURLOPT_ENCODING, "gzip");
            }
            if ($method === self::METHOD_POST_HTTP) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
                curl_setopt($ch, CURLOPT_POST, true);
            }
            if ($method === self::METHOD_POST_JSON) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
                curl_setopt($ch, CURLOPT_POST, true);
            }
            if ($method === self::METHOD_HEAD) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST,'HEAD');
                curl_setopt($ch, CURLOPT_NOBODY, true);
            }

            if ($secure) {
                if (!empty($options['noSslVerify'])) {
                    /** @noinspection CurlSslServerSpoofingInspection */
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    /** @noinspection CurlSslServerSpoofingInspection */
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                } else {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                    if (!empty($options['ssl'])) {
                        // optional: only needed for a non-system CA (e.g. self-signed
                        // for an internal service) — the system CA bundle is used otherwise
                        curl_setopt($ch, CURLOPT_CAINFO, $options['ssl']);
                    }
                }
            }


            $useragent = $options['useragent'] ?? 'Opera/9.80 (Windows NT 6.1) Presto/2.12.388 Version/12.17';
            curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
            if (!empty($options['no_redirects'])){
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            } else {
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            }

            if ($additionalHeaders) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $additionalHeaders);
            }
            $verbose = null;
            if (!empty($options['debug'])){
                curl_setopt($ch, CURLOPT_VERBOSE, true);
                $verbose = fopen('php://temp', 'wb+');
                curl_setopt($ch, CURLOPT_STDERR, $verbose);
                //$type = curl_multi_getcontent($ch)
            }
            $responseCURL = curl_exec($ch);
            // getLastResponseInfo()['info'] must be populated even on failure — callers
            // like Finance\UpdateService inspect it right after a failed request to
            // build their own error message.
            $this->lastResponseInfo['info'] = curl_getinfo($ch);
            if ($responseCURL === false) {
                $this->logger->warning(
                    'Curl Request Failed: ' . curl_error($ch),
                    ['errno' => curl_errno($ch), 'url' => $url, 'tag' => 'NETWORK']
                );
                return null;
            }
            if (!empty($options['debug'])){
                rewind($verbose);
                $verboseLog = stream_get_contents($verbose);
                $this->lastResponseInfo['debug'] = $verboseLog;
            }
            if ($saveHeader){
                $header = substr($responseCURL,0,curl_getinfo($ch,CURLINFO_HEADER_SIZE));
                $responseCURL = substr($responseCURL,curl_getinfo($ch,CURLINFO_HEADER_SIZE));
                $this->storeCookiesFromHeader($header, $requestHost);
                $this->lastResponseInfo['cookie'] = $this->cookieJar->forHost($requestHost);
                $this->lastResponseInfo['header'] = $header;
            }
            return $responseCURL;
        } catch (Throwable $ex) {
            $this->logger->warning(
                'Curl Connection Error: ' . $ex->getMessage(),
                ['exception' => $ex, 'tag' => 'NETWORK']
            );
            return null;
        }
    }

    public function getLastResponseInfo(): array
    {
        return $this->lastResponseInfo;
    }

    /**
     * Parse every Set-Cookie line in a raw response header block and store each
     * cookie in the jar, scoped by its Domain attribute (or the request host, if
     * the cookie didn't specify one).
     */
    private function storeCookiesFromHeader(string $header, string $requestHost): void
    {
        if (!preg_match_all('/^Set-Cookie:\s*(.*)$/mi', $header, $matches)) {
            return;
        }
        foreach ($matches[1] as $setCookieLine) {
            $this->cookieJar->storeFromSetCookieHeader(rtrim($setCookieLine, "\r"), $requestHost);
        }
    }

    /**
     * Build headers from array('Accept' => 'application/json') to array('Accept: application/json')
     */
    public function buildHeaders(array $keyValueArray): array
    {
        $result = [];
        foreach ($keyValueArray as $key => $value) {
            $result[] = $key . ': ' . $value;
        }
        return $result;
    }
}
