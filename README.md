The Router Gear
================================================================================
[![Build Status](https://travis-ci.org/phpgearbox/router.svg)](https://travis-ci.org/phpgearbox/router)
[![Latest Stable Version](https://poser.pugx.org/gears/router/v/stable.svg)](https://packagist.org/packages/gears/router)
[![Total Downloads](https://poser.pugx.org/gears/router/downloads.svg)](https://packagist.org/packages/gears/router)
[![License](https://poser.pugx.org/gears/router/license.svg)](https://packagist.org/packages/gears/router)

**Laravel Router Standalone**

Okay so by now hopefully you have heard of [Laravel](http://laravel.com/),
the PHP framework that just makes things easy. So first things first full credit
goes to [Taylor Otwell](https://github.com/taylorotwell) for the Router API.

How to Install
--------------------------------------------------------------------------------
Installation via composer is easy:

	composer require gears/router:*

How to Use
--------------------------------------------------------------------------------
In your *legacy* - non Laravel application.
You can use the Laravel Router API like so:

```php
// Make sure you have composer included
require('vendor/autoload.php');

// Install the gears router component
$router = new Gears\Router();
$router->routesPath = '/file/path/to/my/routes';
$router->dispatch();

// At this point execution will not continue. The router will either output
// the results from a route and exit or it will output a 404 and then exit.
```

The ```/file/path/to/my/routes``` can either be a routes php file.
eg: ```/file/path/to/my/routes.php``` this file would look just like any
*routes.php* file you would find in any other Laravel App.

**OR**

The path can be to a folder containing lots of route files. The files will be
included automatically for you and added to the router. This is my prefered 
solution.

> A little aside: In my Laravel Apps I place some code, similar to what is in
> this Router that loops through a *routes* folder inside my *app* dir.
> I place one route definition per file. I rarely use Controllers.
> I name each route file like *search-{postcode}.GET.php* which contains:
> ```Route::get('/search/{postcode}', function($postcode){});```
> It makes for a very fast prototyping development life cycle.

An Example Route
--------------------------------------------------------------------------------
So just like a Laravel Route, see: http://laravel.com/docs/routing
Here is an example route file.

```php
Route::get('/', function()
{
	return 'I Am Groot';
});
```

**Class Alias**
Behind the scenes when you call ```dispatch()``` we automatically create a new
class alias called ```Route```, this sets up the normal public API you are used
to. However if the class ```Route``` is already in existence.
We also alias ourselves to ```Gears\Route```.

The 404 Error
--------------------------------------------------------------------------------
Out of the box we have built in a simple and clean looking 404 error page.
Credits go to: http://html5boilerplate.com/ Thank Guys.

However if you wish to overide the 404 content the router returns.
The instalation of the router might look like:

```php
$router = new Gears\Router();
$router->routesPath = '/file/path/to/my/routes';
$router->notFound = 'Custom 404 HTML';
$router->dispatch();
```

**The 404 Exception**
If you set the 404 parameter to a boolean value of *false*.
Then we will simply re-throw the 404 exception, which is an instance of:

```
Symfony\Component\HttpKernel\Exception\NotFoundHttpException
```

You can then deal with this yourself. An example use case might be to have
multiple routers. Which then could provide a HMVC type setup.

Exit On Complete
--------------------------------------------------------------------------------
For most setups you will probably want the execution of PHP to stop after the
router has done it's thing and sent the response. However if for whatever
reason you don't want this, perhaps some sort of output buffering or something.
The instalation of the router might look like:

```php
$router = new Gears\Router();
$router->routesPath = '/file/path/to/my/routes';
$router->exitOnComplete = false;
$router->dispatch();
```

So now for the why?
--------------------------------------------------------------------------------
While laravel is so awesomely cool and great. If you want to pull a feature out
and use it in another project it can become difficult. Firstly you have to have
an innate understanding of the [IoC Container](http://laravel.com/docs/ioc).

You then find that this class needs that class which then requires some other
config variable that is normally present in the IoC when run inside a normal
Laravel App but in your case you haven't defined it and don't really want
to define that value because it makes no sense in your lets say *legacy*
application.

Perfect example is when I tried to pull the session API out to use in wordpress.
It wanted to know about a ```booted``` method, which I think comes from
```Illuminate\Foundation\Application```. At this point in time I already had to
add various other things into the IoC to make it happy and it was the last straw
that broke the camels back, I chucked a coders tantrum, walked to the fridge,
grabbed another Redbull and sat back down with a new approach.

The result is this project.

--------------------------------------------------------------------------------
Developed by Brad Jones - brad@bjc.id.au