# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

- `Added` for new features.
- `Changed` for changes in existing functionality.
- `Deprecated` for soon-to-be removed features.
- `Removed` for now removed features.
- `Fixed` for any bug fixes.
- `Security` in case of vulnerabilities

## [1.0.2] - 2020.11.24

### Fixed

- Fixed bug where using `file_put_contents` was failing if a directory did not already exist.

## [1.0.1] - 2020.11.09

### Fixed

- Updated `_run` method to not save output to file if `NULL` or empty string.

## [1.0.0] - 2020.09.07

### Added

- Initial release.