<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit6f32508ae2022a2c16af555b29a463b0
{
    public static $files = array (
        '4cdafd4a5191caf078235e7dd119fdaf' => __DIR__ . '/..' . '/flightphp/core/flight/autoload.php',
        'e3cd3e6ea0fe16cf6c6b16fa591c5162' => __DIR__ . '/..' . '/flightphp/core/flight/Flight.php',
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->classMap = ComposerStaticInit6f32508ae2022a2c16af555b29a463b0::$classMap;

        }, null, ClassLoader::class);
    }
}
