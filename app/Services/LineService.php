<?php

namespace App\Services;

use App\Repositories\TflLineRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;

/**
 * Orchestrates line browsing and search over the cached provider dataset.
 * Fetches the dataset through the repository, caches it for an hour, then
 * paginates it (browse) or filters it in-memory before paginating (search).
 */
final class LineService
{
    public const PER_PAGE = 15;

    private const CACHE_KEY = 'tfl:lines:v1';

    private const CACHE_TTL_SECONDS = 3600;

    public function __construct(
        private readonly TflLineRepository $repository,
    ) {}

    /**
     * Paginate the full dataset for browsing.
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(int $page = 1): LengthAwarePaginator
    {
        return $this->paginateLines($this->lines(), $page);
    }

    /**
     * Filter the dataset by a case-insensitive substring match, then paginate.
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function search(string $query, int $page = 1): LengthAwarePaginator
    {
        $needle = mb_strtolower(trim($query));

        $matches = array_values(array_filter(
            $this->lines(),
            fn (array $line) => $this->matches($line, $needle),
        ));

        return $this->paginateLines($matches, $page);
    }

    /**
     * The full provider dataset, cached for an hour.
     * @return array<int, array<string, mixed>>
     */
    private function lines(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL_SECONDS,
            fn () => $this->repository->fetchLines(),
        );
    }

    /**
     * Build an in-memory paginator over a list of lines.
     * @param  array<int, array<string, mixed>>  $lines
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function paginateLines(array $lines, int $page): LengthAwarePaginator
    {
        $page = max(1, $page);
        $items = array_slice($lines, ($page - 1) * self::PER_PAGE, self::PER_PAGE);

        return new LengthAwarePaginator(
            $items,
            count($lines),
            self::PER_PAGE,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => Paginator::resolveQueryString(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function matches(array $line, string $needle): bool
    {
        $haystacks = [
            $line['id'] ?? '',
            $line['name'] ?? '',
            $line['modeName'] ?? '',
        ];

        foreach ($line['routeSections'] ?? [] as $section) {
            $haystacks[] = $section['originationName'] ?? '';
            $haystacks[] = $section['destinationName'] ?? '';
        }

        foreach ($haystacks as $haystack) {
            if (str_contains(mb_strtolower((string) $haystack), $needle)) {
                return true;
            }
        }

        return false;
    }
}
