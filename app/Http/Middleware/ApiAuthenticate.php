<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        if(!$request->user()){
            return response()->json([
                'success'=>false,
                'message'=>'Utilisateur non connecté. Veuillez vous authentifier',
                'error'=> 'Unauthenticated'
            ], 401);
        }
        return $next($request);
    }
}
