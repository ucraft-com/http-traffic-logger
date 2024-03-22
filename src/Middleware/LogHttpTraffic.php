<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Uc\HttpTrafficLogger\TrafficManager;

/**
 * LogHttpTraffic middleware efficiently captures and records HTTP request and response data exchanged between clients and servers.
 */
class LogHttpTraffic
{
    public function __construct(
        protected TrafficManager $trafficManager,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request                                        $request
     * @param \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response) $next
     *
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $record = $this->trafficManager->capture($request);
        $response = $next($request);

        $record->captureResponse($response);
        $this->trafficManager->record($record);

        return $response;
    }
}
