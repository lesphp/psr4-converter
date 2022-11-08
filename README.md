# PSR-4 Converter

PSR-4 Converter is a tool to automate the conversion of PSR-0 or non-standard codes to PSR-4 compliance codes.
To convert the php code (support codebase with PHP >= 5.0), this tool statically parses the code and then all the code is scanned to find the definitions that will be changed.
Making legacy code compiler with PSR-4 involves more steps than renaming namespaces, this tool solves the following problems with this conversion:

1. Ensure that there is only one class per php file;
2. Possibility of converting names from PSR-0 to PSR-4, converting underscore to namespace;
3. Prefix existing namespaces with a vendor namespace;
4. Create class_alias from the old class to the new one to ensure dynamic calls (class reference by variables);
5. Validation of invalid statements that cannot be converted in php files;
6. Creating include files with conditional functions and definitions to be included using composer.
7. Automatic generation of autoload file to load old classes, delivering the new class instead.
8. Possibility to ignore namespaces and directories.
9. Creation of a mapping json file, allowing to use it to automate other steps.
10. Rename all references from the old names to the new class and function names.
11. Rename all references in DocBlocks from the old names to the new class and function names.
12. Refactoring the imports to use the new converted names.
13. Risky conversion alert.
14. Checksum validation, allowing you to use pre-existing mapping files safely.

## Instalation
You can install this library in several ways.

### composer

1. Add the dependency to your `composer require lesphp/psr4-converter` project. It is recommended to install globally via the command `composer require --global lesphp/psr4-converter`.

2. Run the PSR-4 Converter via the command `php vendor/bin/psr4-converter help` or `psr4-converter help` if installed globally.

### Phive

1. Install PSR-4 from phive with the command `phive install --force-accept-unsigned lesphp/psr4-converter`

2. Run PSR-4 Converter via `psr4-converter help` command.

## Usage

### Mapping

The map command will generate a mapping json file containing all the information needed for the conversion,
including all old and new name mappings.

For example the following command `psr4-converter map "App" /path/to/source -m /tmp/.psr4-converter.json --append-namespace --underscore-conversion --ignore-namespaced-underscore -- ignore-path="ignored_relative_path"`
will generate the mapping file `/tmp/.psr4-converter.json` with all conversions to the `/path/to/source` directory, will also:

- Use the `App` vendor namespace for the new converted names, so a `\Old\Name` class will be `\App\Old\Name`;
- With the `--underscore-conversion` option an `Old_Name2` class will become `\App\Old\Name2`;
- With the option `--append-namespace` the vendor namespace `App` will always be added to the new name,
without this option a class `\App\Old\Name3` would become `\App\Old\Name3`, with this option it will become `\App\App\Old\Name3`;
- With the `--ignore-namespaced-underscore` option underscores in old names will be kept for classes that are already namespaced,
then a class `\Old\Name_Four` would become `\App\Old\Name_Four`.

Use the `psr4-converter map --help` command to get more details on the arguments and possibilities of the command.

### Converting

Using the mapping file it is possible to convert the code to a new directory through the command `psr4-converter convert /tmp/.psr4-converter.json /path/to/destination -m /tmp/.psr4-converter2.json --ignore-vendor-path --create-aliases --allow-risky`.
It will convert the code mapped in `/tmp/.psr4-converter.json` to the `/path/to/destination` directory, will also:

- Definitions that need to be statically included by composer will be created in `/path/to/destination/includes` .
- With the `-m` option it is possible to add other mapping files to be used only to rename the classes mapped in the additional file,
so the conversion will already have the new names of the additional mapping.
- With the option `--ignore-vendor-path` the vendor path will be ignored for generating the path of the converted file,
so a class with new name `\App\New\Name` will be in `/path/to/destination/New/Name.php`,
this is useful for doing psr-4 mappings in composer.json.
- With the `--create-aliases` option a file will be created in `/path/to/destination/includes/autoload.php`,
that can be statically included in composer to autoload the old names, keeping compatibility for dynamic calls of the old names.
- With the `--allow-risky` option the tool will allow the conversion even if there is some risk mapped.

Use the `psr4-converter convert --help` command to get more details on the arguments and possibilities of the command.

### Renaming

Using the mapping file it is possible to rename the mapped references, so the old names will be converted to the new names.
Use the rename command like this `psr4-converter rename /tmp/.psr4-converter.json /path/to/destination`.

Use the `psr4-converter rename --help` command to get more details on the arguments and possibilities of the command.