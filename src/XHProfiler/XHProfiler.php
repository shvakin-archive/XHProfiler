<?php

namespace XHProfiler;

require_once 'lib/xhprof_lib.php';
require_once 'lib/xhprof_runs.php';

class XHProfiler
{
    protected static $started = false;

    protected static $name;

    protected static $output;

    protected static $guiUri = '';

    public static function init(array $options =[])
    {
        if (!empty($options['enabled'])) {
            //@todo проверка на куку или паметр
            static::setGuiUri($options['uri']);
            static::start($_SERVER['SERVER_NAME'], true);
        }
    }

    public static function setGuiUri($uri)
    {
        static::$guiUri = $uri;
    }

    public static function checkEnv()
    {
        if (static::$started) {
            return false;
        }
        static::$started = true;

        if (!function_exists('xhprof_enable')) {
            trigger_error ('to use XHProfiler you mast install xhprof extension', E_USER_WARNING);
            return false;
        }
        return true;
    }

    public static function maybeStart($chance, $name = 'xhprof', $output = false, $cpu = true, $memory = true, $ignored = [])
    {
        $try = mt_rand(0, 100);
        if ($try<=$chance) {
            static::start($name, $output, $cpu, $memory, $ignored);
        }
    }

    /**
     * @param string $name
     * @param bool|\Closure $output
     * @param bool $cpu
     * @param bool $memory
     * @param array $ignored
     * @return bool
     */
    public static function start($name = 'xhprof', $output = false, $cpu = true, $memory = true, $ignored = [])
    {
        if (!static::checkEnv()) {
            return false;
        }

        static::$name = $name;
        static::$output = $output;

        $flags = XHPROF_FLAGS_NO_BUILTINS;
        if ($cpu) {
            $flags = $flags | XHPROF_FLAGS_CPU;
        }
        if ($memory) {
            $flags = $flags | XHPROF_FLAGS_MEMORY;
        }

        $ignored = $ignored + [__CLASS__.'::sendData'];

        xhprof_enable($flags, ['ignored_functions'=>$ignored]);
        register_shutdown_function([__CLASS__, 'sendData']);
    }

    public static function sendData()
    {
        $xhprofData = xhprof_disable();
        static::$started = false;

        $xhprofRuns = new \XHProfRuns_Default();
        $runId = $xhprofRuns->save_run($xhprofData, static::$name);

        $outFunction = null;
        if (is_callable(static::$output)) {
            $outFunction = static::$output;
        } else {
            if (static::$output) {
                $uri = static::$guiUri;
                if ($uri === '') {
                    $uri = 'http://' . $_SERVER['SERVER_NAME'] . '/index.php';
                }
                $outFunction = function ($runId, $name) use ($uri) {
                    echo "\n<br />\n<a target=_blanc href = \"$uri?run=$runId&source=".static::$name .'">xhprof report</a>'."\n";
                };
            }
        }
        if (is_callable($outFunction)) {
            call_user_func($outFunction, $runId, static::$name);
        }
    }
}