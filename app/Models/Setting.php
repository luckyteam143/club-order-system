<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::rememberForever("setting.{$key}", fn () => static::where('key', $key)->value('value') ?? $default);
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget("setting.{$key}");
    }

    /**
     * For secrets (e.g. mail_password) — stored encrypted at rest since
     * this table isn't otherwise access-restricted like .env is.
     */
    public static function getDecrypted(string $key, mixed $default = null): mixed
    {
        $value = static::get($key);

        if (! filled($value)) {
            return $default;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException) {
            return $default;
        }
    }

    public static function setEncrypted(string $key, string $value): void
    {
        static::set($key, Crypt::encryptString($value));
    }
}
