<?php

namespace Colligator\Http\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Guard;

class IpWhitelist
{
    /**
     * Create a new filter instance.
     */
    public function __construct()
    {
    }

    protected function isWhitelisted($ip)
    {
        // https://www.uio.no/english/services/it/security/cert/about-cert/constituency.html
        $whitelist = [
            '193.157.108.0-193.157.255.255',
            '129.240.0.0-129.240.255.255',
            '158.36.184.0-158.36.191.255',
            '193.156.90.0-193.156.90.255',
            '193.156.120.0-193.156.120.255',
        ];

        $ip = ip2long($ip);

        foreach ($whitelist as $range) {
            list($start, $end) = explode('-', $range);
            if ($ip >= ip2long($start) && $ip < ip2long($end)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!$this->isWhitelisted($request->ip())) {
            return response('Du må redigere fra UiO-nettet.', 401);
        }

        return $next($request);
    }
}
