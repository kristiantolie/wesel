# WebSetup

WebSetup is a utility script to automate web server configuration in your
development machine.


## Table of Content

- [Quick start](#quick-start)
- [Bugs and feature requests](#bugs-and-feature-requests)
- [Documentation](#documentation)
- [Contributing](#contributing)
- [Versioning](#versioning)
- [Creators](#creators)
- [Copyright and license](#copyright-and-license)


## Quick start

1. Edit start.php and set document_root at the bottom of the file to your
    web server documents directory.
    
    // Create launcher
    $launcher = new NginxPhpLauncher();

    // Set to your web server documents directory
    $launcher->set('document_root', realpath(__DIR__ . '/public'));
    
2. Open terminal and execute start.php.
    
    #./start.php

3. Open web browser and go to localhost:8080 to see if it works.

4. Execute the generated stop.php to stop the server.

    #./stop.php


## Bugs and feature requests

Have a bug or a feature request? Please first read the documentation and
search for existing and closed issues. If your problem or idea is not
addressed yet, please send us an email.


## Documentation

See quick start.


## Contributing

In writing.


## Versioning

WebSetup is maintained under [the Semantic Versioning guidelines]
(http://semver.org/). Sometimes we screw up, but we'll adhere to those
rules whenever possible.


## Creators

- Kristianto Lie (cre3zlie@gmail.com)


## Copyright and license

Code released under [the MIT license](LICENSE).
