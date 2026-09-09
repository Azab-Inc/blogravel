<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TimingLog
{
    public function handle(Request $request, Closure $next): mixed
    {
        $start = microtime(true);
        $path = $request->path();

        register_shutdown_function(function () use ($start, $path) {
            $elapsed = round((microtime(true) - $start) * 1000);
            file_put_contents('/tmp/request_timing.log', date('H:i:s')." {$path} shutdown after {$elapsed}ms\n", FILE_APPEND);
        });

        $response = $next($request);

        $elapsed = round((microtime(true) - $start) * 1000);
        file_put_contents('/tmp/request_timing.log', date('H:i:s')." {$path} completed {$elapsed}ms status=".$response->getStatusCode()."\n", FILE_APPEND);

        return $response;
    }
}
