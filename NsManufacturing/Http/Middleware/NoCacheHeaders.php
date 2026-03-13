<?php

namespace Modules\NsManufacturing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NoCacheHeaders
{
    /**
     * Disable client/proxy caching for the response.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle( Request $request, Closure $next ): Response
    {
        $response = $next( $request );

        $response->headers->set( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
        $response->headers->set( 'Pragma', 'no-cache' );
        $response->headers->set( 'Expires', '0' );

        return $response;
    }
}
