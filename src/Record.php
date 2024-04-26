<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger;

use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\FileBag;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

use function config;
use function json_encode;
use function array_filter;
use function in_array;
use function explode;
use function implode;
use function str_contains;
use function strtolower;
use function strpos;
use function substr_replace;
use function is_array;

/**
 * Class Record represents captured information.
 */
final class Record
{
    protected const hiddenHeaders = [
        'authorization',
    ];

    /**
     * @var array List of sensitive cookies that should be cleared from the log.
     */
    protected readonly array $hiddenCookies;

    /**
     * @var array|string[] List of sensitive tokens that should be cleared from the log.
     */
    protected array $sensitiveTokens = [
        '"password"',
        '"oldPassword"',
        '"passwordConfirmation"',
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
     * @var mixed Unique identifier of the authenticated user
     */
    protected mixed $authIdentifier = null;

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
        $this->hiddenCookies = [config('session.cookie'), ':access-token'];
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
        $this->requestBody = $this->clearSensitiveData($request->getContent());
        $this->uploadedFiles = $request->files;

        if (($user = $request->user()) && $user instanceof Authenticatable) {
            $this->authIdentifier = $user->getAuthIdentifier();
        }
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
     * Clear sensitive data from the given content.
     *
     * @param string $content
     *
     * @return string
     */
    protected function clearSensitiveData(string $content): string
    {
        foreach ($this->sensitiveTokens as $sensitiveToken) {
            $content = $this->clearSensitiveToken($content, $sensitiveToken);
        }

        return $content;
    }

    /**
     * Clear sensitive token from the given content.
     *
     * @param string $content
     * @param string $token
     *
     * @return string
     */
    protected function clearSensitiveToken(string $content, string $token): string
    {
        // Find the position of the first occurrence of "password"
        $position = strpos($content, $token);

        // If $token is found
        if ($position !== false) {
            // Find the position of the next '"' character after $token
            $startQuote = strpos($content, '"', $position + strlen($token) + 1);

            // If '"' is found after $token
            if ($startQuote !== false) {
                // Find the position of the next '"' character after the first '"'
                $endQuote = strpos($content, '"', $startQuote + 1);

                // If both start and end '"' are found
                if ($endQuote !== false) {
                    // Remove the content between the double quotes
                    $content = substr_replace($content, '', $startQuote + 1, $endQuote - $startQuote - 1);
                }
            }
        }

        return $content;
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
                'user_id'        => $this->authIdentifier,
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
                foreach ($this->hiddenCookies as $hiddenCookie) {
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
            foreach ($this->hiddenCookies as $hiddenCookie) {
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
        $files = $this->prepareUploadedFiles($fileBag);

        foreach ($files as $uploadedFile) {
            $metadata[] = [
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
     * Collect uploaded files into a single array.
     *
     * @param \Symfony\Component\HttpFoundation\FileBag|array $fileBag
     *
     * @return array<UploadedFile>
     */
    protected function prepareUploadedFiles(FileBag|array $fileBag): array
    {
        $items = [];

        foreach ($fileBag as $item) {
            if ($item instanceof UploadedFile) {
                $items[] = $item;
            } elseif (is_array($item)) {
                $items = [...$items, ...$this->prepareUploadedFiles($item)];
            }
        }

        return $items;
    }

    /**
     * Get UUID of the record.
     *
     * @return \Symfony\Component\Uid\UuidV4
     */
    public function getUuid(): UuidV4
    {
        return $this->uuid;
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
