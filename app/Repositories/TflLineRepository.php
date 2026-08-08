<?php

namespace App\Repositories;

use App\Exceptions\LineProviderUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The only class that talks to the TfL Unified API. Builds the request,
 * applies a short timeout and a bounded retry for transient failures, and
 * throws LineProviderUnavailableException on any failure. No search logic.
 */
final class TflLineRepository
{
    private const ENDPOINT = '/Line/Route';

    private const TIMEOUT_SECONDS = 5;

    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const RETRY_ATTEMPTS = 3;

    private const RETRY_SLEEP_MS = 200;

    private string $baseUrl;

    private ?string $appKey;

    public function __construct()
    {
        $this->baseUrl = (string) config('services.tfl.base_url');
        $this->appKey = config('services.tfl.app_key');
    }

    /**
     * Fetch every line across all modes as a list of raw TfL line arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchLines(): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl)
                ->timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::CONNECT_TIMEOUT_SECONDS)
                ->retry(self::RETRY_ATTEMPTS, self::RETRY_SLEEP_MS, function (Throwable $exception) {
                    // Retry only transient failures — connection errors and 5xx.
                    return $exception instanceof ConnectionException
                        || ($exception instanceof RequestException && $exception->response->serverError());
                }, throw: true)
                ->throw()
                ->get(self::ENDPOINT, $this->query());
        } catch (ConnectionException|RequestException $exception) {
            throw new LineProviderUnavailableException(
                'TfL line provider request failed.',
                previous: $exception,
            );
        }

        $lines = $response->json();

        if (! is_array($lines) || ! array_is_list($lines)) {
            throw new LineProviderUnavailableException('TfL line provider returned a malformed payload.');
        }

        return $lines;
    }

    /**
     * @return array<string, string>
     */
    private function query(): array
    {
        return $this->appKey ? ['app_key' => $this->appKey] : [];
    }
}
