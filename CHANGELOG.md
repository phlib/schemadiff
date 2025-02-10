# Change Log
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](http://keepachangelog.com/) 
and this project adheres to [Semantic Versioning](http://semver.org/).

## [Unreleased]

## [2.1.0] - 2025-02-10
### Removed
- **BC break**: Removed support for PHP versions <= v8.0 as they are no longer
  [actively supported](https://php.net/supported-versions.php) by the PHP project.

## [2.0.2] - 2025-02-08
### Changed
- Allow Symfony v7 dependencies.

## [2.0.1] - 2023-10-16
### Changed
- Allow Symfony v6 dependencies

## [2.0.0] - 2022-07-02
### Added
- Add support for PHP v8
- Type declarations have been added to all parameters and return types.
- Throw exception if required source schema is not set.
### Changed
- `SchemaInfoFactory::fromPdo()` is no longer static. This was used statically in
  the command class, which prevented dependency injection for unit tests.
- `SchemaDiffCommand` requires dependencies when constructed. This should have
  no impact on standard usage of this package.
### Removed
- **BC break**: Removed support for PHP versions <= v7.3 as they are no longer
  [actively supported](https://php.net/supported-versions.php) by the PHP project.

## [1.2.0] - 2020-04-15
### Changed
- Allow use of Symfony/Console v4 and v5. This allows SchemaDiff to co-exist
  with dependencies requiring these newer versions.

## [1.1.0] - 2018-12-07
### Added
- Compare schema attributes: *default character set* and *default collation*
### Changed
- Improved README and added screenshots
- Added missing *license* wording to README 

## [1.0.1] - 2017-02-02
### Added
- Add unique check to indexes
- Support for the same schema name on different connections
### Fixed
- Bug in tables list

## [1.0.0] - 2017-02-02
Initial Release
