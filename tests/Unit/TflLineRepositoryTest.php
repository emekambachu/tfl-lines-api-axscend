<?php

namespace Tests\Unit;

use App\Exceptions\LineProviderUnavailableException;
use App\Repositories\TflLineRepository;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TflLineRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.tfl.base_url', 'https://api.tfl.example');
        config()->set('services.tfl.app_key', null);
    }

    public function test_fetch_lines_calls_the_route_endpoint_and_returns_the_lines(): void
    {
        Http::fake([
            '*/Line/Route' => Http::response([
                ['id' => 'victoria', 'name' => 'Victoria', 'modeName' => 'tube'],
            ]),
        ]);

        $lines = (new TflLineRepository)->fetchLines();

        $this->assertSame('victoria', $lines[0]['id']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/Line/Route'));
    }

    public function test_sends_app_key_query_param_when_configured(): void
    {
        config()->set('services.tfl.app_key', 'secret-key');
        Http::fake(['*/Line/Route*' => Http::response([])]);

        (new TflLineRepository)->fetchLines();

        Http::assertSent(fn ($request) => str_contains($request->url(), 'app_key=secret-key'));
    }

    public function test_does_not_send_app_key_when_not_configured(): void
    {
        Http::fake(['*/Line/Route*' => Http::response([])]);

        (new TflLineRepository)->fetchLines();

        Http::assertSent(fn ($request) => ! str_contains($request->url(), 'app_key'));
    }

    public function test_throws_when_provider_returns_server_error(): void
    {
        Http::fake(['*/Line/Route*' => Http::response('boom', 500)]);

        $this->expectException(LineProviderUnavailableException::class);

        (new TflLineRepository)->fetchLines();
    }

    public function test_throws_on_connection_failure(): void
    {
        Http::fake(['*/Line/Route*' => Http::failedConnection()]);

        $this->expectException(LineProviderUnavailableException::class);

        (new TflLineRepository)->fetchLines();
    }

    public function test_throws_on_malformed_payload(): void
    {
        Http::fake(['*/Line/Route*' => Http::response(['error' => 'not a list'])]);

        $this->expectException(LineProviderUnavailableException::class);

        (new TflLineRepository)->fetchLines();
    }

    public function test_retries_transient_server_errors_then_succeeds(): void
    {
        Http::fakeSequence('*/Line/Route*')
            ->push('unavailable', 503)
            ->push('unavailable', 503)
            ->push([['id' => 'victoria']], 200);

        $lines = (new TflLineRepository)->fetchLines();

        $this->assertSame('victoria', $lines[0]['id']);
        Http::assertSentCount(3);
    }

    public function test_does_not_retry_client_errors(): void
    {
        Http::fake(['*/Line/Route*' => Http::response('bad request', 400)]);

        try {
            (new TflLineRepository)->fetchLines();
            $this->fail('Expected LineProviderUnavailableException.');
        } catch (LineProviderUnavailableException) {
            Http::assertSentCount(1);
        }
    }
}
