<?php

namespace App\Helpers;

use App\Models\Sysconfig;
use Illuminate\Support\Facades\Cache;

class SysconfigHelper
{
    /**
     * Get a system configuration value by key
     */
    public static function get($key, $default = null)
    {
        return Cache::remember("sysconfig.{$key}", 3600, function () use ($key, $default) {
            $config = Sysconfig::where('sysconfig_key', $key)->first();
            return $config ? $config->sysconfig_value : $default;
        });
    }

    /**
     * Get all configurations as array
     */
    public static function all()
    {
        return Cache::remember('sysconfig.all', 3600, function () {
            return Sysconfig::pluck('sysconfig_value', 'sysconfig_key')->toArray();
        });
    }
}