<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Database\Eloquent\Builder;

abstract class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Cursor-paginate a query, accepting ?cursor=<opaque>&limit=<int>&fields=<csv>.
     *
     * @param  Builder|\Illuminate\Database\Eloquent\Builder  $query
     * @param  array<string>  $columns  columns to select
     * @param  string[]  $allowedFields  sortable fields allowed in ?fields=
     */
    protected function cursorPaginate(
        Builder $query,
        array $columns = ['*'],
        array $allowedFields = [],
        int $defaultLimit = 15,
    ): CursorPaginator {
        $request = request();

        $limit = (int) $request->input('limit', $defaultLimit);
        $limit = max(1, min($limit, 100));

        $cursor = Cursor::fromEncoded($request->input('cursor'));

        $fields = collect(explode(',', $request->input('fields', '')))
            ->map(fn (string $f) => trim($f))
            ->filter(fn (string $f) => in_array($f, $allowedFields, true))
            ->values()
            ->all();

        if (empty($fields)) {
            $fields = ['id'];
        }

        // Apply ordering
        foreach ($fields as $field) {
            $query->orderBy($field);
        }

        return $query->cursorPaginate($limit, $columns, $cursor);
    }
}
