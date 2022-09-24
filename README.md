# PSR-4 Converter

To be documented...

## Instalation
`composer install`

## Usage
```php
./bin/psr4-converter map "App\\Modules" /path/to/source -m /tmp/.psr4-converter.json --append-namespace --underscore-conversion

./bin/psr4-converter convert /tmp/.psr4-converter.json ./src/Modules --ignore-vendor-path --create-aliases

./bin/psr4-converter rename /tmp/.psr4-converter.json ./src
```