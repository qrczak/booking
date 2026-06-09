<?php

namespace App\Support;

use Illuminate\Support\Facades\Lang;

class Translations
{
    /**
     * Lang group exported to the frontend SPA. Backend-only groups (e.g.
     * validation) are intentionally excluded — those reach Vue via API responses.
     */
    private const GROUP = 'ui';

    /**
     * @var array<int, string>
     */
    private const LOCALES = ['pl', 'en'];

    /**
     * @return array<int, string>
     */
    public static function locales(): array
    {
        return self::LOCALES;
    }

    /**
     * Resolve a requested locale to a supported one, falling back when unknown.
     */
    public static function normalize(string $locale): string
    {
        return in_array($locale, self::LOCALES, true)
            ? $locale
            : (string) config('app.fallback_locale');
    }

    /**
     * The translatable UI messages for a locale.
     *
     * @return array<string, mixed>
     */
    public static function messages(string $locale): array
    {
        $messages = Lang::get(self::GROUP, [], self::normalize($locale));

        return is_array($messages) ? $messages : [];
    }

    /**
     * Content hash over every exported lang file, so the client can detect
     * staleness of its cached copy without an extra request.
     */
    public static function version(): string
    {
        $fingerprint = '';

        foreach (self::LOCALES as $locale) {
            $path = lang_path($locale.'/'.self::GROUP.'.php');
            $fingerprint .= is_file($path) ? md5_file($path) : '';
        }

        return substr(md5($fingerprint), 0, 12);
    }
}
