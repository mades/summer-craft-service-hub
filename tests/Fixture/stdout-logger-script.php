<?php

/**
 * Runs a fixed sequence of StdOutLogger calls in a subprocess so the test can
 * capture STDOUT/STDERR separately via proc_open pipes — StdOutLogger writes
 * to php://stdout/php://stderr, which are the *real* process streams, so this
 * can't be exercised by writing to them from within the PHPUnit process
 * itself without corrupting its own output.
 *
 * Run as a subprocess: php stdout-logger-script.php <threshold> <splitStreams: 0|1> [format]
 */

require __DIR__ . '/../../vendor/autoload.php';

use SummerCraft\Service\Logger\LoggerConfig;
use SummerCraft\Service\Logger\StdOutLogger;
use SummerCraft\Core\Context\ApplicationContext;

$threshold = $argv[1] ?? 'debug';
$splitStreams = ($argv[2] ?? '1') === '1';
$format = $argv[3] ?? 'default';

$applicationContext = ApplicationContext::create(true, 'test', sys_get_temp_dir() . '/stdout-logger-fixture/');
$config = new LoggerConfig($applicationContext);
$config->threshold = $threshold;
$config->stdoutSplitStreams = $splitStreams;
$config->format = $format;

$logger = new StdOutLogger($config);

$logger->taggedDebug('TAG', 'debug message');
$logger->taggedInfo('TAG', 'info message');
$logger->taggedWarning('TAG', 'warning message');
$logger->taggedError('TAG', 'error message');
