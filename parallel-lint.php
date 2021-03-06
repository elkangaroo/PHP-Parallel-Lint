<?php
use JakubOnderka\PhpParallelLint;

if (PHP_VERSION < '5.3.2') {
    die("PHP Parallel Lint require PHP 5.3.2 or newer.");
}

const SUCCESS = 0,
    WITH_ERRORS = 1,
    FAILED = 255;

function showOptions()
{
?>
Options:
    -p <php>    Specify PHP-CGI executable to run.
    -s, --short Set short_open_tag to On (default Off)
    -a, -asp    Set asp_tags to On (default Off)
    -e <ext>    Check only files with selected extensions separated by comma
                (default: php,php3,php4,php5,phtml)
    --exclude   Exclude directory. If you want exclude multiple directory, use
                multiple exclude parameters.
    -j <num>    Run <num> jobs in parallel (default 10)
    -h, --help  Print this help.
<?php
}

/**
 * Help
 */
if (!isset($_SERVER['argv'][1]) || in_array('-h', $_SERVER['argv']) || in_array('--help', $_SERVER['argv'])) { ?>
PHP Parallel Lint version 0.3
---------------------------
Usage:
    parallel-lint [sa] [-p php] [-e ext] [-j num] [--exclude dir] [files or directories]
<?php
    showOptions();
    exit;
}

require_once __DIR__ . '/src/Manager.php';

try {
    $manager = new PhpParallelLint\Manager;
    $setting = $manager->parseArguments($_SERVER['argv']);
    $result = $manager->run($setting);
    die($result ? SUCCESS : WITH_ERRORS);
} catch (PhpParallelLint\InvalidArgumentException $e) {
    echo "Invalid option {$e->getArgument()}" . PHP_EOL . PHP_EOL;
    showOptions();
    die(FAILED);
} catch (Exception $e) {
    echo $e->getMessage(), PHP_EOL;
    die(FAILED);
}
