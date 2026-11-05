# Matomo DiagnosticExtended Plugin

[![Translation status](https://hosted.weblate.org/widgets/matomo/-/communityplugin-diagnosticsextended/svg-badge.svg)](https://hosted.weblate.org/projects/matomo/communityplugin-diagnosticsextended/)

## Description

This plugin adds a collection of new checks to the Matomo System Check page. They are a bit **more experimental** and can be more likely to give incorrect results, but might help you find issues with your Matomo setup.

Please report back any unexpected results you come across or other feedback so that the checks can be improved and possible integrated into Matomo core once they work reliably.

### Currently supported tests:

- curl version check (known vulnerabilities)
- OpenSSL version check
- MySQL/MariaDB version check (latest patch version, end of life)
- matomo.js check (compression, MIME type)
- OPcache check (enabled and set up correctly)
- php.ini settings
- PHP running as root
- PHP version check (latest patch version, end of life)

### Requirements

- Matomo 5 or Matomo 6
- PHP 7.2.5 or newer (the minimum of Matomo 5), tested up to PHP 8.5

### External services

When internet features are enabled in Matomo (`enable_internet_features`), some checks fetch data from
[endoflife.date](https://endoflife.date), [php.net](https://www.php.net/releases/) and
[curl.se](https://curl.se/docs/vuln.pm). The responses are cached (failures for one hour) so that the system check
stays fast even if those services can't be reached.

### False positives

Linux distributions (Debian, Ubuntu, RHEL, ...) backport security fixes without changing the upstream version number.
When PHP or the database server seem to come from a distribution package, outdated version numbers are therefore only
reported as information instead of a warning.
