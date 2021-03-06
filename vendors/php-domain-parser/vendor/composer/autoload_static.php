<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInita37157e836087cf510d3c63624657faa
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Psr\\SimpleCache\\' => 16,
            'Psr\\Log\\' => 8,
            'Pdp\\' => 4,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Psr\\SimpleCache\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/simple-cache/src',
        ),
        'Psr\\Log\\' => 
        array (
            0 => __DIR__ . '/..' . '/psr/log/Psr/Log',
        ),
        'Pdp\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInita37157e836087cf510d3c63624657faa::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInita37157e836087cf510d3c63624657faa::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
