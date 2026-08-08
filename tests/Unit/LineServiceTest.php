<?php

namespace Tests\Unit;

use App\Repositories\TflLineRepository;
use App\Services\LineService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config()->set('services.tfl.base_url', 'https://api.tfl.example');
        config()->set('services.tfl.app_key', null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function fakeDataset(array $lines): LineService
    {
        Http::fake(['*/Line/Route*' => Http::response($lines)]);

        return new LineService(new TflLineRepository);
    }

    private function line(array $overrides = []): array
    {
        return array_merge([
            'id' => 'victoria',
            'name' => 'Victoria',
            'modeName' => 'tube',
            'routeSections' => [
                ['originationName' => 'Brixton', 'destinationName' => 'Walthamstow Central'],
            ],
        ], $overrides);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manyLines(int $count): array
    {
        $lines = [];
        for ($i = 0; $i < $count; $i++) {
            $lines[] = $this->line(['id' => "line-{$i}", 'name' => "Line {$i}"]);
        }

        return $lines;
    }

    public function test_paginate_returns_first_page_capped_at_per_page(): void
    {
        $service = $this->fakeDataset($this->manyLines(20));

        $page = $service->paginate(1);

        $this->assertSame(20, $page->total());
        $this->assertSame(LineService::PER_PAGE, $page->count());
        $this->assertSame(1, $page->currentPage());
        $this->assertSame(2, $page->lastPage());
    }

    public function test_paginate_second_page_returns_remainder(): void
    {
        $service = $this->fakeDataset($this->manyLines(20));

        $page = $service->paginate(2);

        $this->assertSame(5, $page->count());
        $this->assertSame(2, $page->currentPage());
    }

    public function test_per_page_is_fifteen(): void
    {
        $this->assertSame(15, LineService::PER_PAGE);
    }

    public function test_search_matches_by_id(): void
    {
        $service = $this->fakeDataset([$this->line(['id' => 'bakerloo', 'name' => 'Bakerloo'])]);

        $this->assertSame(1, $service->search('bakerloo', 1)->total());
    }

    public function test_search_matches_by_name(): void
    {
        $service = $this->fakeDataset([$this->line(['name' => 'Elizabeth'])]);

        $this->assertSame(1, $service->search('elizabeth', 1)->total());
    }

    public function test_search_matches_by_mode(): void
    {
        $service = $this->fakeDataset([$this->line(['modeName' => 'dlr'])]);

        $this->assertSame(1, $service->search('dlr', 1)->total());
    }

    public function test_search_matches_by_route_section_origin(): void
    {
        $service = $this->fakeDataset([$this->line()]);

        $this->assertSame(1, $service->search('brixton', 1)->total());
    }

    public function test_search_matches_by_route_section_destination(): void
    {
        $service = $this->fakeDataset([$this->line()]);

        $this->assertSame(1, $service->search('walthamstow', 1)->total());
    }

    public function test_search_is_case_insensitive(): void
    {
        $service = $this->fakeDataset([$this->line(['name' => 'Victoria'])]);

        $this->assertSame(1, $service->search('VICTORIA', 1)->total());
    }

    public function test_search_no_match_returns_empty_paginator(): void
    {
        $service = $this->fakeDataset([$this->line()]);

        $this->assertSame(0, $service->search('nonexistent-xyz', 1)->total());
    }

    public function test_search_results_are_paginated(): void
    {
        $service = $this->fakeDataset($this->manyLines(20));

        $page = $service->search('line', 1);

        $this->assertSame(20, $page->total());
        $this->assertSame(LineService::PER_PAGE, $page->count());
        $this->assertSame(2, $page->lastPage());
    }

    public function test_dataset_is_cached_and_reused_across_calls(): void
    {
        $service = $this->fakeDataset([$this->line()]);

        $service->paginate(1);
        $service->search('victoria', 1);

        Http::assertSentCount(1);
        $this->assertTrue(Cache::has('tfl:lines:v1'));
    }
}
