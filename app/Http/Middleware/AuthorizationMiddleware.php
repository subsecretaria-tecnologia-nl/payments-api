<?php

namespace App\Http\Middleware;
use App\Exceptions\Handler;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;

class AuthorizationMiddleware
{   
    /**
    * The authentication guard factory instance.
    *
    * @var \Illuminate\Contracts\Auth\Factory
    */
    protected $token;

    /**
    * Create a new middleware instance.
    *
    * @param  \Illuminate\Contracts\Auth\Factory  $auth
    * @return void
    */
    public function __construct()
    {

    }

    /**
    * Handle an incoming request.
    *
    * @param  \Illuminate\Http\Request  $request
    * @param  \Closure  $next
    * @param  string|null  $guard
    * @return mixed
    */

    // Authorization: Bearer lsjfhlfhaljfahfljahflasj
    // DB table : access => history

    public function handle($request, Closure $next, $guard = null)
    {
        $authorized = false;
        $this->token = $request->bearerToken();
        if($this->token){
            // Coicidir el TOKEN con la DB
            if($this->token === env("APP_KEY"))
                // Guardar Historial
                $authorized = true;
            else
                $authorized = [403, "Forbidden"];
        }

        if($authorized === true) return $next($request);
        else abort($authorized[0] ?? 401, $authorized[1] ?? "Unauthorized");
    }
}