<?php
namespace ParallelLint;

require_once __DIR__ . '/exceptions.php';
require_once __DIR__ . '/Output.php';
require_once __DIR__ . '/Process.php';

class ArrayIterator extends \ArrayIterator
{
    public function getNext()
    {
        $this->next();
        return $this->current();
    }
}

class Manager
{
    const CODE_OK = 0,
        CODE_ERROR = 255;

    /** @var Output */
    protected $output;

    /**
     * @param array $arguments
     * @return Settings
     * @throws InvalidArgumentException
     */
    public function parseArguments(array $arguments)
    {
        $arguments = new ArrayIterator(array_slice($arguments, 1));
        $setting = new Settings;

        foreach ($arguments as $argument) {
            if ($argument{0} !== '-') {
                $setting->paths[] = $argument;
            } else {
                switch (substr($argument, 1)) {
                    case 'p':
                        $setting->phpExecutable = $arguments->getNext();
                        break;

                    case 'log':
                        $setting->logFile = $arguments->getNext();
                        break;

                    case 'short':
                        $setting->shortTag = true;
                        break;

                    case 'asp':
                        $setting->aspTags = true;
                        break;

                    case 'e':
                        $setting->extensions = array_map('trim', explode(',', $arguments->getNext()));
                        break;

                    case 'j':
                        $setting->parallelJobs = max((int) $arguments->getNext(), 1);
                        break;

                    default:
                        throw new InvalidArgumentException($argument);
                }
            }
        }

        return $setting;
    }

    /**
     * @param null|Settings $settings
     * @return bool
     * @throws \Exception
     */
    public function run(Settings $settings = null)
    {
        $settings = $settings ?: new Settings;

        exec(escapeshellarg($settings->phpExecutable) . ' -v', $output, $result);

        if ($result !== self::CODE_OK && $result !== self::CODE_ERROR) {
            throw new \Exception("Unable to execute '{$settings->phpExecutable} -v'");
        }

        $cmdLine = $this->getCmdLine($settings);
        $files = $this->getFilesFromPaths($settings->paths, $settings->extensions);

        $output = $output ?: new Output(new ConsoleWriter);
        $output->setTotalFileCount(count($files));

        /** @var LintProcess[] $running */
        $running = $errors = array();
        $checkedFiles = $filesWithSyntaxError = 0;

        while ($files || $running) {
            for ($i = count($running); $files && $i < $settings->parallelJobs; $i++) {
                $file = array_shift($files);
                $parallel = ($settings->parallelJobs > 1) && (count($running) + count($files) > 1);
                $running[$file] = new LintProcess($cmdLine . escapeshellarg($file), !$parallel);
            }

            if (count($running) > 1) {
				usleep(50000); // stream_select() doesn't work with proc_open()
			}

            foreach ($running as $file => $process) {
                if ($process->isReady()) {
                    $process->getResults();

                    $checkedFiles++;
                    if ($process->hasSyntaxError()) {
                        $errors[$file] = $process->getSyntaxError();
                        $filesWithSyntaxError++;
                        $output->error();
                    } else {
                        $output->ok();
                    }

                    unset($running[$file]);
                }
            }
        }

        $output->writeLine();
        $output->writeLine();

        $message = "Checked $checkedFiles files, ";
        if ($filesWithSyntaxError === 0) {
            $message .= "no syntax error found";
        } else {
            $message .= "syntax error found in $filesWithSyntaxError files";
        }

        $output->writeLine($message);

        if (!empty($errors)) {
            $output->writeLine();

            foreach ($errors as $file => $errorMessage)
            {
                $output->writeLine($errorMessage);
            }

            return false;
        }

        return true;
    }

    public function setOutput(Output $output)
    {
        $this->output = $output;
    }

    /**
     * @param Settings $settings
     * @return string
     */
    protected function getCmdLine(Settings $settings)
    {
        $cmdLine = escapeshellarg($settings->phpExecutable);
        $cmdLine .= ' -d asp_tags=' . $settings->aspTags ? 'On' : 'Off';
        $cmdLine .= ' -d short_open_tag=' . $settings->shortTag ? 'On' : 'Off';
        return $cmdLine . ' -n -l ';
    }

    /**
     * @param array $paths
     * @param array $extensions
     * @return array
     * @throws NotExistsPathException
     */
    protected function getFilesFromPaths(array $paths, array $extensions)
    {
        $files = array();

        foreach ($paths as $path) {
            if (is_file($path)) {
                $files[] = $path;
            } else if (is_dir($path)) {
                $directoryFiles = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
                foreach ($directoryFiles as $directoryFile) {
                    $directoryFile = (string) $directoryFile;
                    if (in_array(pathinfo($directoryFile, PATHINFO_EXTENSION), $extensions)) {
                        $files[] = $directoryFile;
                    }
                }
            } else {
                throw new NotExistsPathException($path);
            }
        }

        return $files;
    }
}




