<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetInstitutionalLocale
{
    /**
     * Keep the locale request-scoped so a long-lived worker cannot leak one
     * visitor's language into the next request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $previousLocale = app()->getLocale();
        $locale = (string) $request->query('locale', 'pt-BR');

        if (! in_array($locale, ['pt-BR', 'en', 'es'], true)) {
            $locale = 'pt-BR';
        }

        app()->setLocale($locale);

        try {
            return $next($request);
        } finally {
            app()->setLocale($previousLocale);
        }
    }
}
