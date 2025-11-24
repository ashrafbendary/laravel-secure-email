# Changelog

All notable changes to `laravel-secure-email` will be documented in this file.

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
