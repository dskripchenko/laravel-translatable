# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Entries for releases published before this file existed were reconstructed from
the tagged commit history.

## [2.1.1] - 2026-07-20

### Added
- GitHub Actions pipeline: PHP 8.2-8.5 against Laravel 11, 12 and 13.

### Removed
- `roave/security-advisories` from the development dependencies: it refuses to install
  alongside Laravel 11, which is still supported here.

## [2.1.0] - 2026-07-20

### Changed
- Supported versions moved to the canonical matrix: PHP ^8.2 with Laravel 11, 12 and 13,
  and Pest 3 or 4, which is what makes the Laravel 13 test run possible.

## [2.0.0] - 2026-03-23

### Changed
- Major update: bug fixes across the translation service, new features, a test suite
  and documentation.

## [1.0.6] - 2025-06-16

### Changed
- The published migration uses the anonymous-class form expected by current Laravel.

## [1.0.5] - 2025-06-10

### Fixed
- A content block with no translation falls back to its default value.

## [1.0.4] - 2025-06-10

### Changed
- Content blocks are linked to pages instead of standing on their own.

## [1.0.3] - 2025-06-10

### Added
- Content blocks can be preloaded with the page.

## [1.0.2] - 2025-06-10

### Fixed
- Corrected the relation between a page and its content blocks.

## [1.0.1] - 2025-06-10

### Fixed
- Corrected a column definition in the pages migration.

## [1.0.0] - 2025-06-10

### Changed
- First stable release. Page and content-block models settled on their final shape.

## [0.0.9] - 2025-06-10

### Removed
- Configuration keys that no longer had any effect.

## [0.0.8] - 2025-06-10

### Added
- Content blocks.

## [0.0.7] - 2025-06-03

### Fixed
- Translation values keep their type on the way back out of storage.

## [0.0.6] - 2025-06-03

### Fixed
- Translation values keep their type on the way back out of storage.

## [0.0.5] - 2025-06-03

### Fixed
- Translation values keep their type on the way back out of storage.

## [0.0.4] - 2025-05-16

### Fixed
- Corrected namespaces.

## [0.0.3] - 2025-05-14

### Fixed
- Corrected namespaces.

## [0.0.2] - 2025-05-14

### Fixed
- Corrected the Composer manifest.

## [0.0.1] - 2025-05-14

### Added
- First release: database-backed translations with models, a service and a model trait.
