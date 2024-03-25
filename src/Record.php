<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

use function json_encode;
use function array_filter;
use function in_array;
use function explode;
use function implode;
use function str_contains;
use function strtolower;

/**
 * Class Record represents captured information.
 */
final class Record
{
    protected const hiddenHeaders = [
        'authorization',
    ];

    protected const hiddenCookies = [
        ':access-token',
        'laravel_session',
    ];

    /**
     * @var \Symfony\Component\Uid\UuidV4 Unique identifier of the record
     */
    protected UuidV4 $uuid;

    /**
     * @var string URL of the request
     */
    protected string $url;

    /**
     * @var string HTTP Method of the request
     */
    protected string $method;

    /**
     * @var \Symfony\Component\HttpFoundation\InputBag Query parameters of the request
     */
    protected InputBag $query;

    /**
     * @var \Symfony\Component\HttpFoundation\HeaderBag HTTP headers of the request
     */
    protected HeaderBag $requestHeaders;

    /**
     * @var \Symfony\Component\HttpFoundation\ResponseHeaderBag HTTP headers of the response
     */
    protected ResponseHeaderBag $responseHeaders;

    /**
     * @var string|null Body of the request
     */
    protected string|null $requestBody = null;

    /**
     * @var string|false Body of the response
     */
    protected string|false $responseBody;

    /**
     * @var array HTTP cookies of the request
     */
    protected array $requestCookies;

    /**
     * @var \Symfony\Component\HttpFoundation\FileBag Metadata of the uploaded files
     */
    protected FileBag $uploadedFiles;

    /**
     * @var float Duration of the request in milliseconds
     */
    protected float $duration;

    /**
     * @var int Status code of the response
     */
    protected int $status;

    /**
     * @var bool Define whether the request is already captured or not
     */
    private bool $requestCaptured = false;

    /**
     * @var bool Define whether the response is already captured or not
     */
    private bool $responseCaptured = false;

    /**
     * @param \DateTimeImmutable $createdAt Datetime of the creation of the record
     */
    public function __construct(
        protected readonly DateTimeImmutable $createdAt,
    ) {
        $this->uuid = Uuid::v4();
    }

    /**
     * Capture given request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return void
     */
    public function captureRequest(Request $request): void
    {
        $this->requestCaptured = true;
        $this->url = $request->url();
        $this->method = $request->method();
        $this->query = $request->query;
        $this->requestHeaders = $request->headers;
        $this->requestCookies = $request->cookies->all();
        $this->requestBody = $request->getContent();
        $this->uploadedFiles = $request->files;
    }

    /**
     * Capture given response.
     *
     * @param \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     */
    public function captureResponse(Response $response): void
    {
        $this->responseCaptured = true;
        // Calculate duration as soon as response received.
        $this->calculateDuration();
        $this->responseHeaders = $response->headers;
        $this->responseBody = $response->getContent();
        $this->status = $response->getStatusCode();
    }

    /**
     * Calculate approximate duration of the traffic.
     *
     * @return void
     */
    protected function calculateDuration(): void
    {
        $end = new DateTimeImmutable();
        $diff = $this->createdAt->diff($end);
        $this->duration = ($diff->s * 1000) + ($diff->f / 1000);
    }

    /**
     * Dump captured data.
     *
     * @return array|array[]
     */
    public function dump(): array
    {
        $result = [];

        if ($this->isRequestCaptured()) {
            // Dump request.
            $result = [
                'uuid'           => (string)$this->uuid,
                'url'            => $this->url,
                'method'         => $this->method,
                'query'          => json_encode($this->query->all()),
                'req_headers'    => json_encode($this->filterHiddenHeaders($this->requestHeaders)),
                'req_cookies'    => json_encode($this->filterCookiesByKey($this->requestCookies)),
                'req_body'       => $this->requestBody,
                'uploaded_files' => json_encode($this->prepareUploadedFilesMetadata($this->uploadedFiles)),
                'created_at'     => $this->createdAt->format('Y-m-d\TH:i:s'),
            ];
        }

        if ($this->isResponseCaptured()) {
            // Dump response.
            $result = [
                ...$result,
                'res_headers' => json_encode($this->filterHiddenHeaders($this->responseHeaders)),
                'res_body'    => $this->responseBody,
                'status'      => $this->status,
                'duration'    => $this->duration,
            ];
        }

        return $result;
    }

    /**
     * Filter out hidden HTTP headers.
     *
     * @param \Symfony\Component\HttpFoundation\HeaderBag $headerBag
     *
     * @return array
     */
    protected function filterHiddenHeaders(HeaderBag $headerBag): array
    {
        $headers = $headerBag->all();
        if (!empty($headers['cookie'])) {
            $headers['cookie'] = $this->filterHiddenCookies($headers['cookie']);
        }

        if (!empty($headers['set-cookie'])) {
            $headers['set-cookie'] = $this->filterHiddenCookies($headers['set-cookie']);
        }

        return array_filter($headers, function ($key) {
            return !in_array(strtolower($key), self::hiddenHeaders, true);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Filter out hidden HTTP Cookies.
     *
     * @param array $cookie
     *
     * @return array
     */
    protected function filterHiddenCookies(array $cookie): array
    {
        foreach ($cookie as &$value) {
            $cookieItems = explode(';', $value);
            $filteredCookieItems = array_filter($cookieItems, function ($value) {
                foreach (self::hiddenCookies as $hiddenCookie) {
                    if (str_contains($value, $hiddenCookie)) {
                        return false;
                    }
                }

                return true;
            });
            $value = implode(';', $filteredCookieItems);
        }

        return $cookie;
    }

    /**
     * Filter hidden cookies by their keys.
     *
     * @param array $cookies
     *
     * @return array
     */
    protected function filterCookiesByKey(array $cookies): array
    {
        return array_filter($cookies, function ($key) {
            foreach (self::hiddenCookies as $hiddenCookie) {
                if (str_contains($key, $hiddenCookie)) {
                    return false;
                }
            }

            return true;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Prepare metadata for uploaded files.
     *
     * @param \Symfony\Component\HttpFoundation\FileBag $fileBag
     *
     * @return array
     */
    protected function prepareUploadedFilesMetadata(FileBag $fileBag): array
    {
        $metadata = [];
        /**
         * @var string                                              $name
         * @var \Symfony\Component\HttpFoundation\File\UploadedFile $uploadedFile
         */
        foreach ($fileBag as $name => $uploadedFile) {
            $metadata[$name] = [
                'fileName'                => $uploadedFile->getFilename(),
                'path'                    => $uploadedFile->getPath(),
                'realpath'                => $uploadedFile->getRealPath(),
                'mimeType'                => $uploadedFile->getMimeType(),
                'basename'                => $uploadedFile->getBasename(),
                'pathname'                => $uploadedFile->getPathname(),
                'clientMimeType'          => $uploadedFile->getClientMimeType(),
                'clientOriginalExtension' => $uploadedFile->getClientOriginalExtension(),
                'clientOriginalName'      => $uploadedFile->getClientOriginalName(),
                'aTime'                   => $uploadedFile->getATime(),
                'cTime'                   => $uploadedFile->getCTime(),
                'mTime'                   => $uploadedFile->getMTime(),
                'extension'               => $uploadedFile->getExtension(),
                'size'                    => $uploadedFile->getSize(),
                'guessedExtension'        => $uploadedFile->guessExtension(),
                'guessedClientExtension'  => $uploadedFile->guessClientExtension(),
                'error'                   => $uploadedFile->getError(),
                'errorMessage'            => $uploadedFile->getErrorMessage(),
            ];
        }

        return $metadata;
    }

    /**
     * Determine whether the request is captured.
     *
     * @return bool
     */
    public function isRequestCaptured(): bool
    {
        return $this->requestCaptured;
    }

    /**
     * Determine whether the response is captured.
     *
     * @return bool
     */
    public function isResponseCaptured(): bool
    {
        return $this->responseCaptured;
    }

    /**
     * Get when the record was created.
     *
     * @return \DateTimeImmutable
     */
    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
