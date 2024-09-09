<?php

namespace coucounco\LaravelOtc\Http\Middlewares;

use Closure;
use Illuminate\Http\Client\HttpClientException;
use coucounco\LaravelOtc\Otc;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OtcMiddleware
{

    public function handle($request, Closure $next, $inputs = null)
    {
        if(!Otc::check()) {
            abort(401, 'Unauthorized1');
        }
        $inputs = explode(',', $inputs ?? '');

        $authenticatable = $inputs[0] ?? config('otc.default-authenticatable', 'user');
        $modelClass = config('otc.authenticatables.'.$authenticatable.'.model');
        if(get_class(Otc::user()) !== $modelClass) {
            abort(401, 'Unauthorized2');
        }

        return $next($request);
    }
}
