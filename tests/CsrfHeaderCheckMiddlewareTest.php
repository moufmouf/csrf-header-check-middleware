<?php
declare(strict_types=1);

namespace TheCodingMachine\Middlewares;

use Interop\Http\ServerMiddleware\DelegateInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheCodingMachine\Middlewares\SafeRequests\IsSafeHttpMethod;
use Zend\Diactoros\Request;
use Zend\Diactoros\Response;
use Zend\Diactoros\ServerRequest;

class CsrfHeaderCheckMiddlewareTest extends AbstractMiddlewareTest
{
    public function testGetRequest()
    {
        $request = new ServerRequest([], [], "http://alice.com/hello", "Get");

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());


        $response = $middleware->process($request, $this->getDelegate());

        $this->assertSame('foobar', (string) $response->getBody());
    }

    public function testFailingPostRequestNoOrigin()
    {
        $request = new ServerRequest([], [], "http://alice.com/hello", "Post");

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());

        $this->expectException(CsrfHeaderCheckMiddlewareException::class);
        $this->expectExceptionMessage('Could not find neither the ORIGIN header nor the REFERER header in the HTTP request.');

        $response = $middleware->process($request, $this->getDelegate());
    }

    public function testFailingPostRequestNoHost()
    {
        $request = new ServerRequest([], [], "http://alice.com/hello", "Post");
        $request = $request->withHeader('Origin', "http://alice.com");
        $request = $request->withoutHeader('Host');

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());

        $this->expectException(CsrfHeaderCheckMiddlewareException::class);
        $this->expectExceptionMessage('Could not find the HOST header in the HTTP request.');

        $response = $middleware->process($request, $this->getDelegate());
    }

    public function testSuccessfullPostWithOriginAndHost()
    {
        $request = new ServerRequest([], [], "http://alice.com/hello", "Post");
        $request = $request->withHeader('Origin', "http://alice.com");

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());

        $response = $middleware->process($request, $this->getDelegate());

        $this->assertSame('foobar', (string) $response->getBody());
    }

    public function testSuccessfullPostWithOriginAndHostAndPort()
    {
        $request = new ServerRequest([], [], "http://alice.com:8080/hello", "Post");
        $request = $request->withHeader('Origin', "http://alice.com:8080");

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());

        $response = $middleware->process($request, $this->getDelegate());

        $this->assertSame('foobar', (string) $response->getBody());
    }

    public function testSuccessfullPostWithRefererAndForwardedHostAndPort()
    {
        $request = new ServerRequest([], [], "http://bob.com/hello", "Post");
        $request = $request->withHeader('Referer', "http://alice.com");
        $request = $request->withHeader('X-Forwarded-Host', "alice.com");

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());

        $response = $middleware->process($request, $this->getDelegate());

        $this->assertSame('foobar', (string) $response->getBody());
    }

    public function testAttackPostWithOriginAndHost()
    {
        $request = new ServerRequest([], [], "http://alice.com/hello", "Post");
        $request = $request->withHeader('Origin', "http://eve.com");

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());

        $this->expectException(CsrfHeaderCheckMiddlewareException::class);
        $this->expectExceptionMessage('Potential CSRF attack stopped. Source origin and target origin do not match.');
        $response = $middleware->process($request, $this->getDelegate());
    }

    public function testExceptionOnWeirdRequests()
    {
        $request = new ServerRequest([], [], "http://alice.com/hello", "Post");
        $request = $request->withHeader('Origin', "http://eve.com");
        $request = $request->withAddedHeader('Origin', "http://alice.com");

        $middleware = new CsrfHeaderCheckMiddleware(IsSafeHttpMethod::fromDefaultSafeMethods());

        $this->expectException(CsrfHeaderCheckMiddlewareException::class);
        $this->expectExceptionMessage('Unexpected request: more than one ORIGIN header sent.');
        $response = $middleware->process($request, $this->getDelegate());
    }
}
