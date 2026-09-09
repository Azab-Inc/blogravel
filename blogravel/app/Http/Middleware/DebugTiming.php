<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class DebugTiming
{
    public function handle(Request $request, Closure $next): mixed
    {
        $start = microtime(true);
        file_put_contents('/tmp/timing.log', '['.date('H:i:s')."] START {$request->method()} {$request->path()}\n", FILE_APPEND);

        $response = $next($request);

        $elapsed = round((microtime(true) - $start) * 1000);
        file_put_contents('/tmp/timing.log', '['.date('H:i:s')."] END {$request->method()} {$request->path()} {$elapsed}ms status={$response->getStatusCode()}\n", FILE_APPEND);

        return $response;
    }
}
