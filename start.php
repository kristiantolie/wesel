#!/usr/bin/env php
<?php

abstract class Launcher
{
    protected $variables = array();

    public $debug = false;

    public function get($variable)
    {
        if (!$variable) {
            die('Variable is required.' . PHP_EOL);
        }
        if (!isset($this->variables[$variable])) {
            die('Variable ' . $variable . ' not found.' . PHP_EOL);
        }
        return $this->variables[$variable];
    }

    public function set($variable, $value)
    {
        if (!$variable) {
            die('Variable is required.' . PHP_EOL);
        }
        $this->variables[$variable] = $value;
    }

    public function __get($variable)
    {
        return $this->get($variable);
    }

    public function __set($variable, $value)
    {
        $this->set($variable, $value);
    }

    public function save($file, $content)
    {
        if (!$file) {
            die('File is required.' . PHP_EOL);
        }
        file_put_contents($file, $content);
    }

    public function parse($config)
    {
        if (!$config) {
            die('Config file is required.' . PHP_EOL);
        }
        $content = file_get_contents($config);
        if (!$content) {
            die('Config file ' . $config . ' is empty.' . PHP_EOL);
        }
        return preg_replace_callback('/\{\{(.*?)\}\}/', array($this, 'replace'), $content);
    }

    private function replace($matches)
    {
        return $this->get($matches[1]);
    }

    public function execute($command, $verbose = false)
    {
        if (!$command) {
            die('Command is required.' . PHP_EOL);
        }
        $output = trim(shell_exec($command));
        if ($this->debug && $verbose) {
            echo $command . PHP_EOL;
            if ($output) {
                echo $output . PHP_EOL;
            }
        }
        return $output;
    }

    public function invoke($command, $verbose = false)
    {
        if (!$command) {
            die('Command is required.' . PHP_EOL);
        }

        // Execute in background and suppress the output
        shell_exec($command . ' > /dev/null 2>&1 &');

        if ($this->debug && $verbose) {
            echo $command . PHP_EOL;
        }
    }

    public function isRunning($process)
    {
        if (!$process) {
            die('Process is required.' . PHP_EOL);
        }
        $output = $this->execute('ps aux | grep ' . $process . ' | grep -v grep');
        return !empty($output);
    }
}


class NginxLauncher extends Launcher
{
    public function __construct()
    {
        $this->variables = array(
            'current_dir'   => realpath(__DIR__),
            'document_root' => realpath(__DIR__),
            'config_dir'    => '/usr/local/etc/nginx/conf',
        );
    }

    public function start()
    {
        $content = $this->parse(__DIR__ . '/nginx.conf');
        $this->save(__DIR__ . '/_nginx.conf', $content);

        if (!$this->isRunning('nginx')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('nginx -c ' . __DIR__ . '/_nginx.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        if ($this->isRunning('nginx')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('nginx -c ' . __DIR__ . '/_nginx.conf -s quit', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }
    }
}


class NginxFpmLauncher extends NginxLauncher
{
    public function start()
    {
        parent::start();

        $content = $this->parse(__DIR__ . '/php-fpm.conf');
        $this->save(__DIR__ . '/_php-fpm.conf', $content);

        if (!$this->isRunning('php-fpm')) {
            echo 'Starting FastCGI server...' . PHP_EOL;
            $this->execute('php-fpm -y ' . __DIR__ . '/_php-fpm.conf -c ' . __DIR__ . '/php.ini', true);
            echo 'FastCGI server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        parent::stop();

        if ($this->isRunning('php-fpm')) {
            echo 'Stopping FastCGI server...' . PHP_EOL;
            $this->execute('killall php-fpm', true);
            echo 'FastCGI server has stopped.' . PHP_EOL;
        }
    }
}


class NginxPhpLauncher extends NginxLauncher
{
    public function start()
    {
        parent::start();

        if (!$this->isRunning('php-cgi')) {
            echo 'Starting FastCGI server...' . PHP_EOL;
            $this->invoke('php-cgi -b 127.0.0.1:9000 -c ' . __DIR__ . '/php.ini', true);
            echo 'FastCGI server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        parent::stop();

        if ($this->isRunning('php-cgi')) {
            echo 'Stopping FastCGI server...' . PHP_EOL;
            $this->execute('killall php-cgi', true);
            echo 'FastCGI server has stopped.' . PHP_EOL;
        }
    }
}


class ApacheLauncher extends Launcher
{
    public function __construct()
    {
        $this->variables = array(
            'current_dir'   => realpath(__DIR__),
            'document_root' => realpath(__DIR__),
            'config_dir'    => '/private/etc/apache2',
        );
    }

    public function start()
    {
        $content = $this->parse(__DIR__ . '/httpd.conf');
        $this->save(__DIR__ . '/_httpd.conf', $content);

        if (!$this->isRunning('httpd')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/_httpd.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        if ($this->isRunning('httpd')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/_httpd.conf -k stop', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }
    }
}


class ApacheFpmLauncher extends ApacheLauncher
{
    public function start()
    {
        parent::start();

        $content = $this->parse(__DIR__ . '/php-fpm.conf');
        $this->save(__DIR__ . '/_php-fpm.conf', $content);

        if (!$this->isRunning('php-fpm')) {
            echo 'Starting FastCGI server...' . PHP_EOL;
            $this->execute('php-fpm -y ' . __DIR__ . '/_php-fpm.conf -c ' . __DIR__ . '/php.ini', true);
            echo 'FastCGI server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        parent::stop();

        if ($this->isRunning('php-fpm')) {
            echo 'Stopping FastCGI server...' . PHP_EOL;
            $this->execute('killall php-fpm', true);
            echo 'FastCGI server has stopped.' . PHP_EOL;
        }
    }
}


class ApachePhpLauncher extends ApacheLauncher
{
    public function start()
    {
        parent::start();

        if (!$this->isRunning('php-cgi')) {
            echo 'Starting FastCGI server...' . PHP_EOL;
            $this->invoke('php-cgi -b 127.0.0.1:9000 -c ' . __DIR__ . '/php.ini', true);
            echo 'FastCGI server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        parent::stop();

        if ($this->isRunning('php-cgi')) {
            echo 'Stopping FastCGI server...' . PHP_EOL;
            $this->execute('killall php-cgi', true);
            echo 'FastCGI server has stopped.' . PHP_EOL;
        }
    }
}


// Create server launcher
$launcher = new ApacheLauncher();
// $launcher->debug = true;
$launcher->set('document_root', realpath(__DIR__ . '/public'));

// Setup environment variables
putenv('PATH=/usr/local/bin:/usr/local/sbin:/usr/bin:/usr/sbin:/bin:/sbin');

// Check whether this file is executed or included
$includes = get_included_files();
if ($includes[0] == __FILE__) {
    $launcher->start();
}

// Create stop file
if (!file_exists(__DIR__ . '/stop.php')) {
    $content = "#!/usr/bin/env php\n<?php\nob_start();\ninclude __DIR__ . '/start.php';\n" .
               "ob_end_clean();\n\$launcher->stop();\n";
    file_put_contents(__DIR__ . '/stop.php', $content);
    chmod(__DIR__ . '/stop.php', 0755);
}
