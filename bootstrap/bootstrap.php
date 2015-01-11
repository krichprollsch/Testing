<?php

$vendorDir = realpath(__DIR__.'/../../..');

if (!$loader = include $vendorDir.'/autoload.php') {
    $nl = PHP_SAPI === 'cli' ? PHP_EOL : '<br />';
    echo "$nl$nl";
    die('You must set up the project dependencies.'.$nl.
        'Run the following commands in '.dirname(__DIR__).':'.$nl.$nl.
        'curl -s http://getcomposer.org/installer | php'.$nl.
        'php composer.phar install'.$nl);
}

use Doctrine\Common\Annotations\AnnotationRegistry;
use Symfony\Cmf\Component\Testing\ErrorHandler\DeprecationErrorHandler;

if (class_exists('PHPUnit_Util_ErrorHandler')) {
    DeprecationErrorHandler::register();
}

AnnotationRegistry::registerLoader(function($class) use ($loader) {
    $loader->loadClass($class);

    // this was class_exists($class, false) i.e. do not autoload.
    // this is required so that custom annotations (e.g. TreeUiBundle
    // annotations) are autoloaded - but they should be found by the
    // composer loader above.
    //
    // This probably slows things down.
    //
    // @todo: Fix me.
    return class_exists($class);
});

AnnotationRegistry::registerFile($vendorDir.'/doctrine/phpcr-odm/lib/Doctrine/ODM/PHPCR/Mapping/Annotations/DoctrineAnnotations.php');

if (!defined('CMF_TEST_ROOT_DIR')) {
    define('CMF_TEST_ROOT_DIR', realpath(__DIR__.'/..'));
}
if (!defined('CMF_TEST_CONFIG_DIR')) {
    define('CMF_TEST_CONFIG_DIR', CMF_TEST_ROOT_DIR.'/resources/config');
}
