<?php

use Piwik\DI;
use Piwik\Plugins\DiagnosticsExtended\Diagnostic;

return [
    'diagnosticsExtended.inisettings' => DI::add([
        DI::get(Diagnostic\IniSettings\AllowUrlInclude::class),
        DI::get(Diagnostic\IniSettings\DisplayErrors::class),
        DI::get(Diagnostic\IniSettings\ExposePhp::class),
    ]),
    Diagnostic\PhpIniCheck::class => DI::autowire()
        ->constructorParameter('iniSettings', DI::get('diagnosticsExtended.inisettings')),
    'diagnostics.optional' => DI::add([
//        DI::get(Diagnostic\ExampleCheck::class),
        DI::get(Diagnostic\PhpIniCheck::class),
        DI::get(Diagnostic\PhpVersionCheck::class),
        DI::get(Diagnostic\DatabaseVersionCheck::class),
        DI::get(Diagnostic\MatomoJsCheck::class),
        DI::get(Diagnostic\CurlVersionCheck::class),
        DI::get(Diagnostic\OpensslVersionCheck::class),
        DI::get(Diagnostic\PhpUserCheck::class),
        DI::get(Diagnostic\OpcacheCheck::class),
    ]),
];
