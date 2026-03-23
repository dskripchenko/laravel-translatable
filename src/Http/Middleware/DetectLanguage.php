<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelTranslatable\Http\Middleware;

use Closure;
use Dskripchenko\LaravelTranslatable\Models\Language;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->detect($request);

        if ($locale) {
            app()->setLocale($locale);
            Language::resetStaticCache();
        }

        return $next($request);
    }

    protected function detect(Request $request): ?string
    {
        return $this->fromRouteParameter($request)
            ?? $this->fromUrlSegment($request)
            ?? $this->fromCookie($request)
            ?? $this->fromHeader($request);
    }

    protected function fromRouteParameter(Request $request): ?string
    {
        $param = $request->route('locale');
        return is_string($param) ? $this->validate($param) : null;
    }

    protected function fromUrlSegment(Request $request): ?string
    {
        $segment = $request->segment(1);
        return $segment ? $this->validate($segment) : null;
    }

    protected function fromCookie(Request $request): ?string
    {
        $cookie = $request->cookie('locale');
        return is_string($cookie) ? $this->validate($cookie) : null;
    }

    protected function fromHeader(Request $request): ?string
    {
        $preferred = $request->getPreferredLanguage();
        if (!$preferred) {
            return null;
        }

        return $this->validate($preferred)
            ?? $this->validate(substr($preferred, 0, 2));
    }

    protected function validate(string $code): ?string
    {
        try {
            Language::byCode($code);
            return mb_strtolower($code);
        } catch (\Throwable) {
            return null;
        }
    }
}
