<?php

namespace Symfony\Cmf\Component\Testing\ErrorHandler;

/**
 * Catch deprecation notices and print a summary report at the end of the test suite
 *
 * This class was created by Nicolas Grekas for symfony/symfony and has
 * been copied to this component in order to use it in the Symfony CMF.
 * This is going to be removed as soon as the class has moved to a
 * core-team-maintained component.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DeprecationErrorHandler
{
    private static $isRegistered = false;

    public static function register()
    {
        if (self::$isRegistered) {
            return;
        }
        $deprecations = array(
            'remainingCount' => 0,
            'legacyCount' => 0,
            'otherCount' => 0,
            'remaining' => array(),
            'legacy' => array(),
            'other' => array(),
        );
        $deprecationHandler = function ($type, $msg, $file, $line, $context) use (&$deprecations) {
            if (E_USER_DEPRECATED !== $type) {
                return PHPUnit_Util_ErrorHandler::handleError($type, $msg, $file, $line, $context);
            }

            $trace = debug_backtrace(PHP_VERSION_ID >= 50400 ? DEBUG_BACKTRACE_IGNORE_ARGS | DEBUG_BACKTRACE_PROVIDE_OBJECT : true);

            $i = count($trace);
            while (isset($trace[--$i]['class']) && ('ReflectionMethod' === $trace[$i]['class'] || 0 === strpos($trace[$i]['class'], 'PHPUnit_'))) {
                // No-op
            }

            if (isset($trace[$i]['object']) || isset($trace[$i]['class'])) {
                $class = isset($trace[$i]['object']) ? get_class($trace[$i]['object']) : $trace[$i]['class'];
                $method = $trace[$i]['function'];

                $type = 0 === strpos($method, 'testLegacy') || 0 === strpos($method, 'provideLegacy') || 0 === strpos($method, 'getLegacy') || strpos($class, '\Legacy') ? 'legacy' : 'remaining';

                if ('legacy' === $type && 0 === (error_reporting() & E_USER_DEPRECATED)) {
                    @++$deprecations[$type]['Silenced']['count'];
                } else {
                    @++$deprecations[$type][$msg]['count'];
                    @++$deprecations[$type][$msg][$class.'::'.$method];
                }
            } else {
                $type = 'other';
                @++$deprecations[$type][$msg]['count'];
            }
            ++$deprecations[$type.'Count'];
        };
        $oldErrorHandler = set_error_handler($deprecationHandler);

        if (null !== $oldErrorHandler) {
            restore_error_handler();
            if (array('PHPUnit_Util_ErrorHandler', 'handleError') === $oldErrorHandler) {
                restore_error_handler();
                self::register();
            }
        } else {
            self::$isRegistered = true;
            register_shutdown_function(function () use (&$deprecations, $deprecationHandler) {

                $colorize = new \SebastianBergmann\Environment\Console();

                if ($colorize->hasColorSupport()) {
                    $colorize = function ($str, $red) {
                        $color = $red ? '41;37' : '43;30';

                        return "\x1B[{$color}m{$str}\x1B[0m";
                    };
                } else {
                    $colorize = function ($str) {return $str;};
                }

                $currErrorHandler = set_error_handler('var_dump');
                restore_error_handler();

                if ($currErrorHandler !== $deprecationHandler) {
                    echo "\n", $colorize('THE ERROR HANDLER HAS CHANGED!', true), "\n";
                }

                $cmp = function ($a, $b) {
                    return $b['count'] - $a['count'];
                };

                foreach (array('remaining', 'legacy', 'other') as $type) {
                    if ($deprecations[$type]) {
                        echo "\n", $colorize(sprintf('%s deprecation notices (%d)', ucfirst($type), $deprecations[$type.'Count']), 'legacy' !== $type), "\n";

                        uasort($deprecations[$type], $cmp);

                        foreach ($deprecations[$type] as $msg => $notices) {
                            echo "\n", $msg, ': ', $notices['count'], "x\n";

                            arsort($notices);

                            foreach ($notices as $method => $count) {
                                if ('count' !== $method) {
                                    echo '    ', $count, 'x in ', preg_replace('/(.*)\\\\(.*?::.*?)$/', '$2 from $1', $method), "\n";
                                }
                            }
                        }
                    }
                }
                if (!empty($notices)) {
                    echo "\n";
                }
            });
        }
    }
}
