<?php

namespace App\Http\Controllers;

use App\Exceptions\LineProviderUnavailableException;
use App\Http\Requests\LineSearchRequest;
use App\Http\Resources\LineResource;
use App\Services\LineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class LineController extends Controller
{
    private const ERROR_MESSAGE = 'Line data is temporarily unavailable. Please try again shortly.';

    public function __construct(
        private readonly LineService $lineService,
    ) {}

    /** Paginated browse of all lines. */
    public function index(Request $request): Response
    {
        return $this->respond(
            mode: 'browse',
            search: null,
            fetch: fn () => $this->lineService->paginate($this->page($request)),
        );
    }

    /** Search the cached dataset; a blank query falls back to browse. */
    public function search(LineSearchRequest $request): Response|RedirectResponse
    {
        $search = trim((string) $request->validated('search'));

        if ($search === '') {
            return redirect()->route('lines.index');
        }

        return $this->respond(
            mode: 'search',
            search: $search,
            fetch: fn () => $this->lineService->search($search, $this->page($request)),
        );
    }

    /**
     * Render the page, mapping paginated lines through the resource and
     * degrading gracefully to a controlled error state on provider failure.
     *
     * @param  callable(): LengthAwarePaginator<int, array<string, mixed>>  $fetch
     */
    private function respond(string $mode, ?string $search, callable $fetch): Response
    {
        try {
            $lines = $fetch();
        } catch (LineProviderUnavailableException $exception) {
            report($exception);

            return Inertia::render('Lines/Index', [
                'mode' => $mode,
                'search' => $search,
                'lines' => $this->emptyPage(),
                'error' => self::ERROR_MESSAGE,
            ]);
        }

        $lines->through(fn (array $line) => (new LineResource($line))->resolve());

        return Inertia::render('Lines/Index', [
            'mode' => $mode,
            'search' => $search,
            'lines' => $lines,
            'error' => null,
        ]);
    }

    private function page(Request $request): int
    {
        return max(1, $request->integer('page', 1));
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    private function emptyPage(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, LineService::PER_PAGE, 1);
    }
}
