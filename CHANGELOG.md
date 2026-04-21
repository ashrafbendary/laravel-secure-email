# Changelog

All notable changes to `laravel-secure-email` will be documented in this file.

## v2.0.0 - 2026-04-21

### Changed
- **BREAKING**: Upgraded minimum Laravel version to 13.x (from 12.x)
- **BREAKING**: Upgraded minimum PHP version to 8.3 (from 8.2)
- Bumped `illuminate/support`, `illuminate/database`, `illuminate/mail` constraints to `^13.0`
- Bumped `orchestra/testbench` to `^11.0`
- Bumped `phpunit/phpunit` to `^11.5|^12.0`
- Updated CI test matrix to PHP 8.3/8.4/8.5 against Laravel 13.*
- Converted test suite from `/** @test */` docblock annotations to PHPUnit `#[Test]` attributes for PHPUnit 12 compatibility
- Updated `phpunit.xml.dist` schema to 12.0

### Added
- PHP 8.5 to the CI test matrix (composer constraint `^8.3` already permits 8.3, 8.4, and 8.5)
- Feature tests for `SnsWebhookController` (bounce / complaint / delivery processing, subscription confirmation with Http fake, validation failure paths)
- Feature tests for the `CheckEmailBeforeSending` listener (allow / block paths, disabled-package short-circuit)
- Feature tests for the `secure-email:subscribe-urls` Artisan command

### Fixed
- `it_can_detect_permanent_bounces` test now uses unique generated addresses so the negative assertion is independent of the positive one
- Custom model resolution (`secure-email.models.notification`, `secure-email.models.subscription`) is now actually honored by `SnsWebhookController`, `SesMonitorService`, `CheckEmailBeforeSending`, and `SubscribeUrlCommand`. Previously these classes hardcoded the default models, silently ignoring the documented config
- `SnsWebhookController::confirmSubscription` now uses `Http::timeout(10)->get()` instead of `file_get_contents`, giving it a bounded timeout and removing the dependency on `allow_url_fopen`
- `SnsWebhookController::handleNotification` now catches `\Throwable` instead of `\Exception`, so `Error`/`TypeError` return the generic 500 response instead of leaking a stack trace

### Removed
- Unused `AshrafBendary\SecureEmail\Exceptions\EmailBlockedException` class (imported but never thrown)

## v1.0.0 - 2024-11-24

### Added
- Initial release
- Support for Laravel 12 (PHP 8.2+)
- Automatic email blocking based on bounces and complaints
- SNS webhook handling for bounces, complaints, and deliveries
- Auto-confirmation of SNS subscriptions
- Event system for notification types
- Database storage for all notifications
- Configurable rules for bounces and complaints
- Permanent bounce protection
- Subject-based filtering
- Time-based notification counting
- Artisan command to display subscription URLs
- Comprehensive documentation

### Features
- Compatible with Laravel 12
- Production-ready code
- Extensive configuration options
- Event-driven architecture
- Database queries with scopes
- Custom model support
- Custom route configuration
