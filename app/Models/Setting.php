<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Admin-editable configuration. Reads are cached because the mailer resolves
 * its credentials from here on every request that sends mail.
 */
class Setting extends Model
{
    public const CACHE_KEY = 'meldestelle.settings';

    protected $fillable = ['group', 'key', 'value', 'is_encrypted'];

    protected function casts(): array
    {
        return ['is_encrypted' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return array<string, string|null> */
    public static function all_values(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return static::query()->get()->mapWithKeys(fn (self $s) => [
                $s->key => $s->decoded(),
            ])->all();
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_values()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general', bool $encrypted = false): self
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->group = $group;
        $setting->is_encrypted = $encrypted;
        $setting->value = $value === null || $value === ''
            ? null
            : ($encrypted ? Crypt::encryptString((string) $value) : (string) $value);
        $setting->save();

        return $setting;
    }

    public function decoded(): ?string
    {
        if ($this->value === null) {
            return null;
        }

        if (! $this->is_encrypted) {
            return $this->value;
        }

        try {
            return Crypt::decryptString($this->value);
        } catch (Throwable) {
            // A rotated APP_KEY makes old ciphertext unreadable. Fail soft so
            // the admin can simply re-enter the value.
            return null;
        }
    }
}
