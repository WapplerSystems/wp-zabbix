<?php

namespace WPZabbix;

class Plugin
{
    public static function boot(): void
    {
        add_action('rest_api_init', [API::class, 'register']);
    }
}
