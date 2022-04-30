<?php

$finder = PhpCsFixer\Finder::create()
    ->exclude('vendor')
    ->in(__DIR__);

$config = new PhpCsFixer\Config();

return $config->setRules([
    '@PSR12' => true,
    '@PSR12:risky' => true,
])
    ->setFinder($finder);
