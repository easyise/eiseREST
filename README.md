# eiseREST

PHP library for REST API server interfaces.

## Description

### Concept:
- to be capable with [RESTful API guidelines](https://restfulapi.net)
- to support modern client-side frameworks (BackboneJS, AngularJS)
- to be object-oriented, but compatible with legacy PHP versions (>5.1)
- thrown (and uncaught) exceptions are causing server-side errors with corresponding HTTP response code (404, 403, 500, etc)
- to provide simple access to database objects (few lines of code should be enough)
- to be lightweight

### How it works

Let's assume that your site API query URL looks like this:
```
http://yoursite.com/api/employee/John_Doe/vacation
                        ^^^^^^^^   ^^^^   ^^^^^^^^
                         entity     id    subentity 
```

You need to create your own classes for entity "employee" and entity "vacation". These classes should be based on eiseREST_Entity class. It has methods `get()`, `post()`, `put()`, `delete()` and `options()` that handle corresponding HTTP methods. You can override these methods with your own code (by default they throw an error). `get()` and `post()` methods should return PHP array that will be directly converted to JSON.

```
include 'dist/eiseREST/eiseREST.php';

class restEmployee extends eiseREST_Entity { 
  function __construct(parent::__construct( $rest, array('name'=>'employee'); }
  /** + other stuff */
}
class restVacation extends eiseREST_Entity { 
  function __construct(parent::__construct( $rest, array('name'=>'vacation'); }
  /** + other stuff */ 
}
```

Next step - you register them with main REST object and call request parser and then handler:
```
$rest = new eiseREST( ); // create instance

$rest->authenticate(); // app and user authentication

$rest->registerEntity( new restEmployee($rest) );
$rest->registerEntity( new restVacation($rest) );

$rest->parse_request(); // request parse

$rest->execute();
```

You should put this code to your `$_SERVER['DOCUMENT_ROOT'].'/api/index.php'` file. To make it work properly do not forget to tune your web server so that `$_SERVER['PATH_INFO']` contains the path after you root API url (`/employee/John_Doe/vacation` in our example). See "Required server configuration" chapter below.

## Project status

Current state:
- supports GET method for database objects
- supports GET method for custom entities
- returns JSON made from PHP arrays

To do:
- Documentation (WoIP)
- Authentication (need help and advise)
- ability to handle POST, PUT and DELETE with various Content-types (WoIP)
- ability to return XML
- ability to return HTML

## Required server configuration

Some part of "server" context in NGINX config under Debian Linux with PHP running as PHP-FPM via sockets may look like this:
```
   # API
   location ~ ^(?<sys_path>/.*)/api(?P<path_info>/.+)$ {
         set $api_handler $sys_path/api/index.php;
         try_files $api_handler =404;
         include fastcgi_params;
         fastcgi_param SCRIPT_FILENAME $document_root$api_handler;
         fastcgi_param PATH_INFO $path_info;
         fastcgi_param PATH_TRANSLATED $document_root;
         fastcgi_pass unix:/var/run/php5-fpm.sock;
         break;
    }
```
It sets API handler for any site directory with name `api/` to the `index.php` file inside it and corrects PATH_INFO and PATH_TRANSLATED variables.
