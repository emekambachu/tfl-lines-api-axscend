<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The single definition of the client-facing line shape. Maps the raw TfL
 * fields to the app contract; the full third-party payload is never exposed.
 */
final class LineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $line = $this->resource;

        return [
            'id' => $line['id'] ?? null,
            'name' => $line['name'] ?? null,
            'mode' => $line['modeName'] ?? null,
            'routeSections' => array_map(
                fn (array $section) => [
                    'origin' => $section['originationName'] ?? null,
                    'destination' => $section['destinationName'] ?? null,
                ],
                $line['routeSections'] ?? [],
            ),
            'serviceTypes' => array_values(array_filter(array_map(
                fn (array $serviceType) => $serviceType['name'] ?? null,
                $line['serviceTypes'] ?? [],
            ))),
            'hasDisruptions' => ! empty($line['disruptions']),
        ];
    }
}
