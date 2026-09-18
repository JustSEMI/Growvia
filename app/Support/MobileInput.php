<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class MobileInput
{
    /** @param array<string, mixed> $rules
     * @return array<string, mixed>
     */
    public static function validate(Request $request, array $rules): array
    {
        self::only($request->all(), array_keys($rules));

        return Validator::make($request->all(), $rules)->validate();
    }

    /** @param array<string, mixed> $input
     * @param  list<string>  $allowed
     */
    public static function only(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed) !== []) {
            throw ValidationException::withMessages(['request' => 'Kolom tidak dikenal.']);
        }
    }

    /** @return array<string, mixed> */
    public static function page(LengthAwarePaginator $page, string $resource, Request $request): array
    {
        return [
            'data' => $resource::collection($page->getCollection())->resolve($request),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => 15, 'total' => $page->total()],
        ];
    }
}
