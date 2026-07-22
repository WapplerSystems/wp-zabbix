# WP-Zabbix Monitoring Client

WordPress REST API client for [Zabbix](https://www.zabbix.com/) monitoring. Exposes 112 health, performance, and security metrics via a single authenticated endpoint.

## Features

- **112 monitoring fields** across 13 categories
- Single aggregated `/wp-json/wpzabbix/v1/status` endpoint
- Filterable by category: `?category=plugins`
- `hash_equals()` timing-safe key comparison
- POST-only key transmission (no GET leak in access logs)
- IP whitelist support
- Rate limiting: 10 failed attempts per IP per 10 minutes
- Operation caching (5 min TTL)

## Requirements

- WordPress 6.4+
- PHP 8.1+
- Zabbix 6.0+ (HTTP agent items)

## Installation

1. Upload the `wp-zabbix` folder to `/wp-content/plugins/`
2. Activate the plugin
3. Add the shared secret to `wp-config.php`:

```php
define('WPZABBIX_KEY', 'your-64-char-random-secret');
```

4. (Optional) Restrict to Zabbix server IP:

```php
define('WPZABBIX_ALLOWED_IPS', '10.0.0.5,10.0.0.6');
```

## Zabbix Configuration

Create an HTTP agent item in Zabbix:

- **Type**: HTTP agent
- **URL**: `https://your-site.com/wp-json/wpzabbix/v1/status`
- **Method**: POST
- **Body**: `wpzabbix-key=your-secret`
- **Headers**: `Content-Type: application/x-www-form-urlencoded`

### Item JSONPath examples

| Zabbix Key | JSONPath | Type |
|---|---|---|
| `wp.version` | `$.wordpress.version` | text |
| `wp.core_updates` | `$.wordpress.core_updates` | uint |
| `php.version` | `$.php.version` | text |
| `php.memory_limit_bytes` | `$.php.memory_limit_bytes` | uint |
| `db.size_bytes` | `$.database.size_bytes` | uint |
| `db.table_count` | `$.database.table_count` | uint |
| `plugins.active_updates` | `$.plugins.active_updates` | uint |
| `plugins.total` | `$.plugins.total` | uint |
| `themes.active` | `$.themes.active` | text |
| `content.posts_published` | `$.content.posts_published` | uint |
| `users.total` | `$.users.total` | uint |
| `users.administrator` | `$.users.administrator` | uint |
| `sitehealth.critical` | `$.sitehealth.critical` | uint |
| `sitehealth.percentage` | `$.sitehealth.percentage` | float |
| `security.debug_mode` | `$.security.debug_mode` | uint |
| `security.file_editing_disabled` | `$.security.file_editing_disabled` | uint |
| `perf.autoloaded_options_size` | `$.performance.autoloaded_options_size` | uint |
| `perf.object_cache` | `$.performance.object_cache` | uint |
| `cron.total_jobs` | `$.cron.total_jobs` | uint |
| `cron.due_now` | `$.cron.due_now` | uint |
| `system.disk_free_bytes` | `$.system.disk_free_bytes` | uint |
| `ssl.expiry_days` | `$.ssl.expiry_days` | uint |

See the full response at `GET /wp-json/wpzabbix/v1/status` for all available fields.

## Trigger Recommendations

| Trigger | Expression | Severity |
|---|---|---|
| Core update available | `last(/wp.core_updates)>0` | High |
| Active plugins outdated | `last(/wp.plugins.active_updates)>0` | Warning |
| Site health critical | `last(/wp.sitehealth.critical)>0` | High |
| Debug mode enabled | `last(/wp.security.debug_mode)=1` | High |
| File editing enabled | `last(/wp.security.file_editing_disabled)=0` | Warning |
| Autoloaded options > 1MB | `last(/wp.perf.autoloaded_options_size)>1048576` | Warning |
| Object cache inactive | `last(/wp.perf.object_cache)=0` | Warning |
| Disk space low | `last(/wp.system.disk_free_bytes)<{THRESHOLD}` | High |
| Overdue cron jobs | `last(/wp.cron.due_now)>5` | Warning |
| SSL expiring soon | `last(/wp.ssl.expiry_days)<30` | High |

## Security

- The endpoint requires a shared secret defined in `wp-config.php`
- `hash_equals()` prevents timing attacks on key comparison
- POST-only prevents key leakage in web server access logs
- Rate limiting blocks brute-force attempts (10/IP/10min)
- IP whitelist available for defense-in-depth
- SSL check uses `WP_SITEURL` constant (not modifiable from DB)

## License

GPL v2 or later
