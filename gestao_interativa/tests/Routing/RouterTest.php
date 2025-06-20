<?php

namespace GestaoInterativa\Tests\Routing;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Routing\Router;
use GestaoInterativa\Http\Request;
use GestaoInterativa\Http\Response;

class RouterTest extends TestCase
{
    private $router;
    private $request;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->request = new Request();
    }

    public function testRouterCanBeCreated()
    {
        $this->assertInstanceOf(Router::class, $this->router);
    }

    public function testAddRoute()
    {
        $this->router->addRoute('GET', '/test', function() {
            return 'Test route';
        });

        $this->assertTrue($this->router->hasRoute('GET', '/test'));
    }

    public function testGetRoute()
    {
        $this->router->get('/test', function() {
            return 'Test GET route';
        });

        $this->assertTrue($this->router->hasRoute('GET', '/test'));
    }

    public function testPostRoute()
    {
        $this->router->post('/test', function() {
            return 'Test POST route';
        });

        $this->assertTrue($this->router->hasRoute('POST', '/test'));
    }

    public function testPutRoute()
    {
        $this->router->put('/test', function() {
            return 'Test PUT route';
        });

        $this->assertTrue($this->router->hasRoute('PUT', '/test'));
    }

    public function testDeleteRoute()
    {
        $this->router->delete('/test', function() {
            return 'Test DELETE route';
        });

        $this->assertTrue($this->router->hasRoute('DELETE', '/test'));
    }

    public function testPatchRoute()
    {
        $this->router->patch('/test', function() {
            return 'Test PATCH route';
        });

        $this->assertTrue($this->router->hasRoute('PATCH', '/test'));
    }

    public function testOptionsRoute()
    {
        $this->router->options('/test', function() {
            return 'Test OPTIONS route';
        });

        $this->assertTrue($this->router->hasRoute('OPTIONS', '/test'));
    }

    public function testAnyRoute()
    {
        $this->router->any('/test', function() {
            return 'Test ANY route';
        });

        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->assertTrue($this->router->hasRoute($method, '/test'));
        }
    }

    public function testMatchRoute()
    {
        $this->router->match(['GET', 'POST'], '/test', function() {
            return 'Test MATCH route';
        });

        $this->assertTrue($this->router->hasRoute('GET', '/test'));
        $this->assertTrue($this->router->hasRoute('POST', '/test'));
        $this->assertFalse($this->router->hasRoute('PUT', '/test'));
    }

    public function testGroupRoute()
    {
        $this->router->group('/admin', function($router) {
            $router->get('/users', function() {
                return 'Admin users';
            });
            $router->get('/settings', function() {
                return 'Admin settings';
            });
        });

        $this->assertTrue($this->router->hasRoute('GET', '/admin/users'));
        $this->assertTrue($this->router->hasRoute('GET', '/admin/settings'));
    }

    public function testGroupRouteWithMiddleware()
    {
        $this->router->group('/admin', function($router) {
            $router->get('/users', function() {
                return 'Admin users';
            });
        }, ['auth']);

        $route = $this->router->getRoute('GET', '/admin/users');
        $this->assertContains('auth', $route['middleware']);
    }

    public function testGroupRouteWithPrefix()
    {
        $this->router->group('/api', function($router) {
            $router->get('/users', function() {
                return 'API users';
            });
        }, [], 'v1');

        $this->assertTrue($this->router->hasRoute('GET', '/api/v1/users'));
    }

    public function testGroupRouteWithNamespace()
    {
        $this->router->group('/admin', function($router) {
            $router->get('/users', 'UserController@index');
        }, [], '', 'App\\Controllers\\Admin');

        $route = $this->router->getRoute('GET', '/admin/users');
        $this->assertEquals('App\\Controllers\\Admin\\UserController@index', $route['handler']);
    }

    public function testGroupRouteWithAllOptions()
    {
        $this->router->group('/api', function($router) {
            $router->get('/users', 'UserController@index');
        }, ['auth'], 'v1', 'App\\Controllers\\Api');

        $route = $this->router->getRoute('GET', '/api/v1/users');
        $this->assertContains('auth', $route['middleware']);
        $this->assertEquals('App\\Controllers\\Api\\UserController@index', $route['handler']);
    }

    public function testRouteWithParameters()
    {
        $this->router->get('/users/{id}', function($id) {
            return "User {$id}";
        });

        $route = $this->router->getRoute('GET', '/users/123');
        $this->assertEquals(['id' => '123'], $route['parameters']);
    }

    public function testRouteWithOptionalParameters()
    {
        $this->router->get('/users/{id?}', function($id = null) {
            return $id ? "User {$id}" : 'All users';
        });

        $route = $this->router->getRoute('GET', '/users');
        $this->assertEmpty($route['parameters']);

        $route = $this->router->getRoute('GET', '/users/123');
        $this->assertEquals(['id' => '123'], $route['parameters']);
    }

    public function testRouteWithMultipleParameters()
    {
        $this->router->get('/users/{id}/posts/{post_id}', function($id, $post_id) {
            return "User {$id} Post {$post_id}";
        });

        $route = $this->router->getRoute('GET', '/users/123/posts/456');
        $this->assertEquals([
            'id' => '123',
            'post_id' => '456'
        ], $route['parameters']);
    }

    public function testRouteWithParameterConstraints()
    {
        $this->router->get('/users/{id}', function($id) {
            return "User {$id}";
        })->where('id', '[0-9]+');

        $route = $this->router->getRoute('GET', '/users/123');
        $this->assertNotNull($route);

        $route = $this->router->getRoute('GET', '/users/abc');
        $this->assertNull($route);
    }

    public function testRouteWithMultipleParameterConstraints()
    {
        $this->router->get('/users/{id}/posts/{post_id}', function($id, $post_id) {
            return "User {$id} Post {$post_id}";
        })->where([
            'id' => '[0-9]+',
            'post_id' => '[0-9]+'
        ]);

        $route = $this->router->getRoute('GET', '/users/123/posts/456');
        $this->assertNotNull($route);

        $route = $this->router->getRoute('GET', '/users/abc/posts/def');
        $this->assertNull($route);
    }

    public function testRouteWithMiddleware()
    {
        $this->router->get('/admin', function() {
            return 'Admin area';
        })->middleware('auth');

        $route = $this->router->getRoute('GET', '/admin');
        $this->assertContains('auth', $route['middleware']);
    }

    public function testRouteWithMultipleMiddleware()
    {
        $this->router->get('/admin', function() {
            return 'Admin area';
        })->middleware(['auth', 'admin']);

        $route = $this->router->getRoute('GET', '/admin');
        $this->assertContains('auth', $route['middleware']);
        $this->assertContains('admin', $route['middleware']);
    }

    public function testRouteWithName()
    {
        $this->router->get('/users', function() {
            return 'Users';
        })->name('users.index');

        $route = $this->router->getRoute('GET', '/users');
        $this->assertEquals('users.index', $route['name']);
    }

    public function testRouteWithPrefix()
    {
        $this->router->get('/users', function() {
            return 'Users';
        })->prefix('api');

        $route = $this->router->getRoute('GET', '/api/users');
        $this->assertNotNull($route);
    }

    public function testRouteWithNamespace()
    {
        $this->router->get('/users', 'UserController@index')
            ->namespace('App\\Controllers');

        $route = $this->router->getRoute('GET', '/users');
        $this->assertEquals('App\\Controllers\\UserController@index', $route['handler']);
    }

    public function testRouteWithAllOptions()
    {
        $this->router->get('/users', 'UserController@index')
            ->middleware(['auth', 'admin'])
            ->name('users.index')
            ->prefix('api')
            ->namespace('App\\Controllers');

        $route = $this->router->getRoute('GET', '/api/users');
        $this->assertContains('auth', $route['middleware']);
        $this->assertContains('admin', $route['middleware']);
        $this->assertEquals('users.index', $route['name']);
        $this->assertEquals('App\\Controllers\\UserController@index', $route['handler']);
    }

    public function testDispatchRoute()
    {
        $this->router->get('/test', function() {
            return 'Test route';
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/test';

        $response = $this->router->dispatch($this->request);
        $this->assertEquals('Test route', $response->getContent());
    }

    public function testDispatchRouteWithParameters()
    {
        $this->router->get('/users/{id}', function($id) {
            return "User {$id}";
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users/123';

        $response = $this->router->dispatch($this->request);
        $this->assertEquals('User 123', $response->getContent());
    }

    public function testDispatchRouteWithMiddleware()
    {
        $this->router->get('/admin', function() {
            return 'Admin area';
        })->middleware(function($request, $next) {
            return $next($request);
        });

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/admin';

        $response = $this->router->dispatch($this->request);
        $this->assertEquals('Admin area', $response->getContent());
    }

    public function testDispatchRouteWithController()
    {
        $this->router->get('/users', 'UserController@index');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/users';

        $response = $this->router->dispatch($this->request);
        $this->assertInstanceOf(Response::class, $response);
    }

    public function testDispatchRouteNotFound()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/non-existent';

        $response = $this->router->dispatch($this->request);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testDispatchMethodNotAllowed()
    {
        $this->router->get('/test', function() {
            return 'Test route';
        });

        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/test';

        $response = $this->router->dispatch($this->request);
        $this->assertEquals(405, $response->getStatusCode());
    }
} 