<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LineSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
        config()->set('services.tfl.base_url', 'https://api.tfl.example');
        config()->set('services.tfl.app_key', null);
    }

    private function makeLine(array $overrides = []): array
    {
        return array_merge([
            'id' => 'victoria',
            'name' => 'Victoria',
            'modeName' => 'tube',
            'routeSections' => [
                ['originationName' => 'Brixton', 'destinationName' => 'Walthamstow Central'],
            ],
            'serviceTypes' => [['name' => 'Regular', 'uri' => '/x']],
            'disruptions' => [],
        ], $overrides);
    }

    private function fakeLines(int $count = 1): void
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = $this->makeLine(['id' => "line-{$i}", 'name' => "Line {$i}"]);
        }

        Http::fake(['*/Line/Route*' => Http::response($lines)]);
    }

    public function test_index_shows_first_page_of_lines(): void
    {
        $this->fakeLines(20);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Index')
                ->where('mode', 'browse')
                ->where('search', null)
                ->where('lines.total', 20)
                ->where('lines.current_page', 1)
                ->where('lines.last_page', 2)
                ->has('lines.data', 15)
            );
    }

    public function test_index_second_page_returns_remainder(): void
    {
        $this->fakeLines(20);

        $this->get('/?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.current_page', 2)
                ->has('lines.data', 5)
            );
    }

    public function test_search_returns_transformed_paginated_results(): void
    {
        Http::fake(['*/Line/Route*' => Http::response([$this->makeLine()])]);

        $this->get('/search?search=victoria')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Lines/Index')
                ->where('mode', 'search')
                ->where('search', 'victoria')
                ->where('lines.total', 1)
                ->where('lines.data.0.id', 'victoria')
                ->where('lines.data.0.name', 'Victoria')
                ->where('lines.data.0.mode', 'tube')
                ->where('lines.data.0.routeSections.0.origin', 'Brixton')
                ->where('lines.data.0.routeSections.0.destination', 'Walthamstow Central')
                ->where('lines.data.0.serviceTypes', ['Regular'])
                ->where('lines.data.0.hasDisruptions', false)
            );
    }

    public function test_only_expected_resource_fields_are_exposed(): void
    {
        Http::fake(['*/Line/Route*' => Http::response([$this->makeLine()])]);

        $this->get('/search?search=victoria')
            ->assertInertia(fn (Assert $page) => $page
                ->has('lines.data.0', fn (Assert $line) => $line
                    ->has('id')
                    ->has('name')
                    ->has('mode')
                    ->has('routeSections')
                    ->has('serviceTypes')
                    ->has('hasDisruptions')
                )
            );
    }

    public function test_search_with_no_matches_is_empty(): void
    {
        Http::fake(['*/Line/Route*' => Http::response([$this->makeLine()])]);

        $this->get('/search?search=nonexistent-xyz')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('mode', 'search')
                ->where('lines.total', 0)
                ->has('lines.data', 0)
            );
    }

    public function test_blank_search_redirects_to_index(): void
    {
        $this->fakeLines(1);

        $this->get('/search?search=')->assertRedirect('/');
        $this->get('/search?search=%20%20')->assertRedirect('/');
    }

    public function test_oversized_query_fails_validation(): void
    {
        $this->fakeLines(1);

        $this->get('/search?search='.str_repeat('a', 101))
            ->assertRedirect()
            ->assertSessionHasErrors('search');
    }

    public function test_provider_failure_is_handled_gracefully(): void
    {
        Http::fake(['*/Line/Route*' => Http::failedConnection()]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lines.total', 0)
                ->where('error', 'Line data is temporarily unavailable. Please try again shortly.')
            );
    }
}
