## Changelog

### 0.3.0

- compatibility with Matomo 6 and PHP 8.4/8.5 (Matomo 5 is still supported)
- use the `Piwik\DI` and `Piwik\Log\LoggerInterface` APIs instead of the PHP-DI and PSR-3 libraries bundled with Matomo
- an unexpected error in a single check no longer breaks the whole System Check page and widget
- fix PHP warnings ("Undefined array key", "Trying to access array offset on value of type bool/null", ...) when an external service is unreachable, returns an error or changes its format, when `opcache_get_status()` is restricted, or when running `./console diagnostics:run`
- fix the matomo.js check throwing an exception when internet features are disabled
- fewer false warnings: outdated version numbers of PHP, MySQL/MariaDB, curl and OpenSSL installed from a Linux distribution (which backports security fixes) are now only reported as information
- PHP version check: updated end-of-life dates (PHP 8.1 to 8.5) and fetch them from endoflife.date when possible
- database version check: support MariaDB 11+ and the `5.5.5-` version prefix, check MySQL against endoflife.date, translated messages
- curl check: robust parsing of the vulnerability list, only list the first 10 CVEs, report a warning instead of an error
- matomo.js check: accept `text/javascript` (RFC 9239), check the `Content-Encoding` header, case-insensitive headers
- OPcache check: report OPcache restarts caused by a too small cache, fix the wasted memory percentage, detect the JIT correctly (PHP 8.4 disables it by default), fix the recommended value of `opcache.save_comments`
- php.ini check: `display_errors = stderr` is fine, `display_errors`/`expose_php` are not relevant on the command line, link to the PHP documentation
- external requests are cached (also failures, for one hour) so the System Check widget isn't slowed down

### 0.2.0

Matomo 5 compatibility

### 0.1.9

- removed URLCheck as a similar test is now part of Matomo
- improved matomo.js check thanks to @d--j
- improved and added translations thanks to the [contributors on Weblate](https://hosted.weblate.org/credits/matomo/communityplugin-diagnosticsextended/)

### 0.1.8

Add Greek translation

### 0.1.7

Add German and Turkish translation

### 0.1.6

skip user check if `posix_getuid()` doesn't exist

### 0.1.5

fix a warning when `opcache_get_status()` is not allowed

### 0.1.4

fix the repository URL


### 0.1.3 (unpublished)

- remove logged warning from PHP check
- don't fail if headers are missing
- skip checks when `enable_internet_features = 0` is set
- also accept brotli compression

### 0.1.2

- fix PHP version check
- add version output to curl check

### 0.1.1

fix incorrect translations


### 0.1.0

first version released
