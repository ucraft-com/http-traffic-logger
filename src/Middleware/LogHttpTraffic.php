<?php

declare(strict_types=1);

namespace Uc\HttpTrafficLogger\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Uc\HttpTrafficLogger\TrafficManager;

use function config;
use function in_array;

/**
 * LogHttpTraffic middleware efficiently captures and records HTTP request and response data exchanged between clients
 * and servers.
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
     * @param \Illuminate\Http\Request                                                         $request
     * @param \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response) $next
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check whether the traffic should be logged or not.
        if (!config('http-traffic-logger.enabled') ||
            !in_array($request->getMethod(), config('http-traffic-logger.request_methods'))) {
            return $next($request);
        }

        $record = $this->trafficManager->capture($request);
        $response = $next($request);

        $record->setAuthUser($request->user());
        $record->captureResponse($response);
        $this->trafficManager->record($record);

        return $response;
    }
}
