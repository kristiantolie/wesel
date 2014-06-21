#!/usr/bin/env php
<?php

abstract class Launcher
{
    protected $variables = array();

    public $debug = false;

    public function get($variable)
    {
        if (!$variable) {
            die('Variable is required.');
        }
        if (isset($this->variables[$variable])) {
            return $this->variables[$variable];
        }
        return false;
    }

    public function set($variable, $value)
    {
        if (!$variable) {
            die('Variable is required.');
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

    public function save($filename, $content)
    {
        if (!$filename) {
            die('Filename is required.');
        }
        file_put_contents($filename, $content);
    }

    public function parse($filename)
    {
        if (!$filename) {
            die('Filename is required.');
        }
        $content = file_get_contents($filename);
        if (!$content) {
            return false;
        }
        $result = preg_replace_callback('/\{(.*?)\}/', array($this, 'replace'), $content);
        return $result;
    }

    private function replace($matches)
    {
        return $this->get($matches[1]);
    }

    public function execute($command, $verbose = false)
    {
        if (!$command) {
            die('Command is required.');
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
            die('Command is required.');
        }
        shell_exec($command . ' > /dev/null &');
        if ($this->debug && $verbose) {
            echo $command . PHP_EOL;
        }
    }

    public function isRunning($process)
    {
        if (!$process) {
            die('Process is required.');
        }
        $output = $this->execute('ps aux | grep ' . $process . ' | grep -v grep');
        return !empty($output);
    }

    public function mkdirs($parent, $dirs = array())
    {
        if (!$parent) {
            die('Parent is required.');
        }
        if (!is_array($dirs)) {
            $dirs = array();
        }
        foreach ($dirs as $dir) {
            if (!file_exists($parent . '/' . $dir)) {
                mkdir($parent . '/' . $dir);
            }
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
            'library_dir'   => '/usr/libexec/apache2',
            'config_dir'    => '/private/etc/apache2',
        );
    }

    public function start()
    {
        // Create server directories
        $this->mkdirs(__DIR__, array('conf', 'run', 'temp', 'logs'));

        // Parse config file and save to configuration directory
        $content = $this->parse(__DIR__ . '/httpd.conf');
        $this->save(__DIR__ . '/conf/httpd.conf', $content);

        // Run apache
        if (!file_exists(__DIR__ . '/run/httpd.pid')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/conf/httpd.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        // Stop apache
        if (file_exists(__DIR__ . '/run/httpd.pid')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/conf/httpd.conf -k graceful-stop', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }
    }
}

class ApachePhpLauncher extends Launcher
{
    public function __construct()
    {
        $this->variables = array(
            'current_dir'   => realpath(__DIR__),
            'document_root' => realpath(__DIR__),
            'library_dir'   => '/usr/libexec/apache2',
            'config_dir'    => '/private/etc/apache2',
        );
    }

    public function start()
    {
        // Create server directories
        $this->mkdirs(__DIR__, array('conf', 'run', 'temp', 'logs'));

        // Parse config file and save to configuration directory
        $content = $this->parse(__DIR__ . '/httpd.conf');
        $this->save(__DIR__ . '/conf/httpd.conf', $content);

        // Run apache
        if (!file_exists(__DIR__ . '/run/httpd.pid')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/conf/httpd.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }

        // Run fastcgi
        if (!$this->isRunning('php-cgi')) {
            echo 'Starting fastcgi server...' . PHP_EOL;
            $this->invoke('php-cgi -b 127.0.0.1:9000 -c ' . __DIR__ . '/php.ini', true);
            echo 'Fastcgi server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        // Stop apache
        if (file_exists(__DIR__ . '/run/httpd.pid')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/conf/httpd.conf -k graceful-stop', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }

        // Stop fastcgi
        if ($this->isRunning('php-cgi')) {
            echo 'Stopping fastcgi server...' . PHP_EOL;
            $this->execute('killall php-cgi', true);
            echo 'Fastcgi server has stopped.' . PHP_EOL;
        }
    }
}


class ApacheFpmLauncher extends Launcher
{
    public function __construct()
    {
        $this->variables = array(
            'current_dir'   => realpath(__DIR__),
            'document_root' => realpath(__DIR__),
            'library_dir'   => '/usr/libexec/apache2',
            'config_dir'    => '/private/etc/apache2',
        );
    }

    public function start()
    {
        // Create server directories
        $this->mkdirs(__DIR__, array('conf', 'run', 'temp', 'logs'));

        // Parse apache config and save to configuration directory
        $content = $this->parse(__DIR__ . '/httpd.conf');
        $this->save(__DIR__ . '/conf/httpd.conf', $content);

        // Run apache
        if (!file_exists(__DIR__ . '/run/httpd.pid')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/conf/httpd.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }

        // Parse php-fpm config and save to configuration directory
        $content = $this->parse(__DIR__ . '/php-fpm.conf');
        $this->save(__DIR__ . '/conf/php-fpm.conf', $content);

        // Run fastcgi
        if (!file_exists(__DIR__ . '/run/php-fpm.pid')) {
            echo 'Starting fastcgi server...' . PHP_EOL;
            $this->execute('php-fpm -p ' . __DIR__ . ' -y ' . __DIR__ . '/conf/php-fpm.conf -c ' . __DIR__ . '/php.ini', true);
            echo 'Fastcgi server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        // Stop apache
        if (file_exists(__DIR__ . '/run/httpd.pid')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('apachectl -f ' . __DIR__ . '/conf/httpd.conf -k graceful-stop', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }

        // Stop fastcgi
        if (file_exists(__DIR__ . '/run/php-fpm.pid')) {
            echo 'Stopping fastcgi server...' . PHP_EOL;
            $this->execute('killall php-fpm', true);
            echo 'Fastcgi server has stopped.' . PHP_EOL;
        }
    }
}


class NginxLauncher extends Launcher
{
    public function __construct()
    {
        $this->variables = array(
            'current_dir'   => realpath(__DIR__),
            'document_root' => realpath(__DIR__),
            'config_dir'   => '/usr/local/etc/nginx',
        );
    }

    public function start()
    {
        // Create server directories
        $this->mkdirs(__DIR__, array('conf', 'run', 'temp', 'logs'));

        // Parse config file and save to configuration directory
        $content = $this->parse(__DIR__ . '/nginx.conf');
        $this->save(__DIR__ . '/conf/nginx.conf', $content);

        // Run nginx
        if (!file_exists(__DIR__ . '/run/nginx.pid')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('nginx -p ' . __DIR__ . ' -c ' . __DIR__ . '/conf/nginx.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        // Stop nginx
        if (file_exists(__DIR__ . '/run/nginx.pid')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('nginx -p ' . __DIR__ . ' -c ' . __DIR__ . '/conf/nginx.conf -s quit', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }
    }
}

class NginxPhpLauncher extends Launcher
{
    public function __construct()
    {
        $this->variables = array(
            'current_dir'   => realpath(__DIR__),
            'document_root' => realpath(__DIR__),
            'config_dir'   => '/usr/local/etc/nginx',
        );
    }

    public function start()
    {
        // Create server directories
        $this->mkdirs(__DIR__, array('conf', 'run', 'temp', 'logs'));

        // Parse config file and save to configuration directory
        $content = $this->parse(__DIR__ . '/nginx.conf');
        $this->save(__DIR__ . '/conf/nginx.conf', $content);

        // Run nginx
        if (!file_exists(__DIR__ . '/run/nginx.pid')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('nginx -p ' . __DIR__ . ' -c ' . __DIR__ . '/conf/nginx.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }

        // Run fastcgi
        if (!$this->isRunning('php-cgi')) {
            echo 'Starting fastcgi server...' . PHP_EOL;
            $this->invoke('php-cgi -b 127.0.0.1:9000 -c ' . __DIR__ . '/php.ini', true);
            echo 'Fastcgi server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        // Stop nginx
        if (file_exists(__DIR__ . '/run/nginx.pid')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('nginx -p ' . __DIR__ . ' -c ' . __DIR__ . '/conf/nginx.conf -s quit', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }

        // Stop fastcgi
        if ($this->isRunning('php-cgi')) {
            echo 'Stopping fastcgi server...' . PHP_EOL;
            $this->execute('killall php-cgi', true);
            echo 'Fastcgi server has stopped.' . PHP_EOL;
        }
    }
}


class NginxFpmLauncher extends Launcher
{
    public function __construct()
    {
        $this->variables = array(
            'current_dir'   => realpath(__DIR__),
            'document_root' => realpath(__DIR__),
            'config_dir'   => '/usr/local/etc/nginx',
        );
    }

    public function start()
    {
        // Create server directories
        $this->mkdirs(__DIR__, array('conf', 'run', 'temp', 'logs'));

        // Parse nginx config and save to configuration directory
        $content = $this->parse(__DIR__ . '/nginx.conf');
        $this->save(__DIR__ . '/conf/nginx.conf', $content);

        // Run nginx
        if (!file_exists(__DIR__ . '/run/nginx.pid')) {
            echo 'Starting web server...' . PHP_EOL;
            $this->execute('nginx -p ' . __DIR__ . ' -c ' . __DIR__ . '/conf/nginx.conf', true);
            echo 'Web server has started.' . PHP_EOL;
        }

        // Parse php-fpm config and save to configuration directory
        $content = $this->parse(__DIR__ . '/php-fpm.conf');
        $this->save(__DIR__ . '/conf/php-fpm.conf', $content);

        // Run fastcgi
        if (!file_exists(__DIR__ . '/run/php-fpm.pid')) {
            echo 'Starting fastcgi server...' . PHP_EOL;
            $this->execute('php-fpm -p ' . __DIR__ . ' -y ' . __DIR__ . '/conf/php-fpm.conf -c ' . __DIR__ . '/php.ini', true);
            echo 'Fastcgi server has started.' . PHP_EOL;
        }
    }

    public function stop()
    {
        // Stop nginx
        if (file_exists(__DIR__ . '/run/nginx.pid')) {
            echo 'Stopping web server...' . PHP_EOL;
            $this->execute('nginx -p ' . __DIR__ . ' -c ' . __DIR__ . '/conf/nginx.conf -s quit', true);
            echo 'Web server has stopped.' . PHP_EOL;
        }

        // Stop fastcgi
        if (file_exists(__DIR__ . '/run/php-fpm.pid')) {
            echo 'Stopping fastcgi server...' . PHP_EOL;
            $this->execute('killall php-fpm', true);
            echo 'Fastcgi server has stopped.' . PHP_EOL;
        }
    }
}

// Use the specified PHP version
putenv('PATH=usr/bin:/usr/sbin:' . getenv('PATH'));

// Create launcher
$launcher = new ApacheLauncher();
// $launcher->debug = true;
$launcher->set('document_root', realpath(__DIR__ . '/public'));

// Check whether this file is executed or included
$includes = get_included_files();
if ($includes[0] == __FILE__) {
    $launcher->start();
}

// Create stop file
if (!file_exists(__DIR__ . '/stop.php')) {
    $content = "#!/usr/bin/env php\n<?php\nob_start();\ninclude __DIR__ . '/start.php';\nob_end_clean();\n\$launcher->stop();\n";
    file_put_contents(__DIR__ . '/stop.php', $content);
    chmod(__DIR__ . '/stop.php', 0755);
}
