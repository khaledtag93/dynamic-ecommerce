<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebsiteSetting extends Model
{
    use HasFactory;

    protected $fillable = ['group', 'key', 'value', 'type'];

    public static function getValue(string $key, $default = null)
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function setValue(string $key, $value, string $group = 'general', string $type = 'string'): void
    {
        static::updateOrCreate(['key' => $key], ['group' => $group, 'value' => $value, 'type' => $type]);
    }
}
