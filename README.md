# phlib/schemadiff

[![Latest Stable Version](https://img.shields.io/packagist/v/phlib/schemadiff.svg)](https://packagist.org/packages/phlib/schemadiff)
[![Total Downloads](https://img.shields.io/packagist/dt/phlib/schemadiff.svg)](https://packagist.org/packages/phlib/schemadiff)
![Licence](https://img.shields.io/github/license/phlib/schemadiff.svg?style=flat-square)

MySQL Schema Diff

## Install

Via Composer

Single project
``` bash
$ composer require phlib/schemadiff
```

Globally
``` bash
$ composer global require phlib/schemadiff
```

## Command Line Usage

If you install schemadiff globally

And add this to your PATH in your ~/.bash_profile
``` bash
export PATH=~/.composer/vendor/bin:$PATH
```

Then you can run schemadiff like this
``` bash 
$ schemadiff --help
$ schemadiff h=127.0.0.1,u=user,p=pass,D=db1 h=127.0.0.1,u=user,p=pass,D=db2
```

Otherwise run it from your project
``` bash
$ ./vendor/bin/schemadiff --help
$ ./vendor/bin/schemadiff h=127.0.0.1,u=user,p=pass,D=db1 h=127.0.0.1,u=user,p=pass,D=db2
```

## PHP Usage
``` php
<?php

$pdo = new \PDO('mysql:...', $username, $password);
$schemaInfo1 = \Phlib\SchemaDiff\SchemaInfoFactory::fromPdo($pdo, 'db1');
$schemaInfo2 = \Phlib\SchemaDiff\SchemaInfoFactory::fromPdo($pdo, 'db2');

$schemaDiff = new \Phlib\SchemaDiff\SchemaDiff(
    new Symfony\Component\Console\Output\StreamOutput(STDOUT)
);
$different = $schemaDiff->diff($schemaInfo1, $schemaInfo2);

exit($different ? 1 : 0);

```

