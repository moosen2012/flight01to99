[<< previous](02-composer.md) | [next >>](04-development-helpers.md)

### Error Handler

An error handler allows you to customize what happens if your code results in an error.

A nice error page with a lot of information for debugging goes a long way during development. So the first package
for your application will take care of that.

I like [filp/whoops](https://github.com/filp/whoops), so I will show how you can install that package for your project.
If you prefer another package, feel free to install that one. This is the beauty of programming without a framework,
you have total control over your project.

An alternative package would be: [PHP-Error](https://github.com/JosephLenton/PHP-Error)

To install a new package, open up your `composer.json` and add the package to the require part. It should now look
like this:

```php
"require": {
    "php": ">=8.1.0",
    "filp/whoops": "^2.14"
},
```

Now run `composer update` in your console, and it will be installed.

Another way to install packages is to simply type "composer require filp/whoops" into your terminal at the project root,
i that case composer automatically installs the package and updates your composer.json-file.

But you can't use it yet. PHP won't know where to find the files for the classes. For this you will need an autoloader,
ideally a [PSR-4](http://www.php-fig.org/psr/psr-4/) autoloader. Composer already takes care of this for you, so you
only have to add a `require __DIR__ . '/../vendor/autoload.php';` to your `Bootstrap.php`.

**Important:** Never show any errors in your production environment. A stack trace or even just a simple error message
can help someone to gain access to your system. Always show a user-friendly error page instead and send an email to
yourself, write to a log or something similar. So only you can see the errors in the production environment.

For development that does not make sense though -- you want a nice error page. The solution is to have an environment
switch in your code. We use the getenv() function here to check the environment and define the 'dev' env as standard in
case no environment has been set.

Then after the error handler registration, throw an `Exception` to test if everything is working correctly.
Your `Bootstrap.php` should now look similar to this:

```php
<?php declare(strict_types=1);

namespace Lubian\NoFramework;

use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

require __DIR__ . '/../vendor/autoload.php';

$environment = getenv('ENVIRONMENT') ?: 'dev';

error_reporting(E_ALL);

$whoops = new Run;
if ($environment === 'dev') {
    $whoops->pushHandler(new PrettyPageHandler);
} else {
    $whoops->pushHandler(function (\Throwable $e) {
        error_log("Error: " . $e->getMessage(), $e->getCode());
        echo 'An Error happened';
    });
}
$whoops->register();

throw new \Exception("Ooooopsie");

```

You should now see a error page with the line highlighted where you throw the exception. If not, go back and debug until
you get it working. Now would also be a good time for another commit.


[<< previous](02-composer.md) | [next >>](04-development-helpers.md)
