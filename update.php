#!/usr/bin/env php
<?php

// Update library on all files bellow
$found = trim(shell_exec('find $HOME/Projects -name start.php')) . PHP_EOL;
$found .= trim(shell_exec('find $HOME/Workspace -name start.php'));


// Read library file
$library = '';
$handle = fopen(__DIR__ . '/start.php', 'r');

while (($line = fgets($handle)) !== false) {
    if (trim($line) == '// Create server launcher') {
        break;
    }
    $library .= $line;
}

fclose($handle);


// Replace all files using the library
$files = explode(PHP_EOL, $found);

foreach ($files as $file) {
    if (dirname($file) == __DIR__) {
        echo 'Skip ' . $file . PHP_EOL;
        continue;
    }

    echo 'Patching ' . $file . PHP_EOL;

    $output = $library;
    $skip = true;
    $handle = fopen($file, 'r+');

    while (($line = fgets($handle)) !== false) {
        if (trim($line) == '// Create server launcher') {
            $skip = false;
        }
        if ($skip) {
            continue;
        }
        $output .= $line;
    }

    fclose($handle);
    file_put_contents($file, $output);
}
