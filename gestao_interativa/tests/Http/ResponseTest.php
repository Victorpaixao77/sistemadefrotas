<?php

namespace GestaoInterativa\Tests\Http;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Http\Response;

class ResponseTest extends TestCase
{
    private $response;

    protected function setUp(): void
    {
        $this->response = new Response();
    }

    public function testResponseCanBeCreated()
    {
        $this->assertInstanceOf(Response::class, $this->response);
    }

    public function testSetContent()
    {
        $content = 'Test content';
        $this->response->setContent($content);
        $this->assertEquals($content, $this->response->getContent());
    }

    public function testSetStatusCode()
    {
        $this->response->setStatusCode(200);
        $this->assertEquals(200, $this->response->getStatusCode());
    }

    public function testSetHeader()
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->assertEquals('application/json', $this->response->getHeader('Content-Type'));
    }

    public function testSetHeaders()
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Custom-Header' => 'value'
        ];
        $this->response->setHeaders($headers);
        $this->assertEquals($headers, $this->response->getHeaders());
    }

    public function testRemoveHeader()
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->removeHeader('Content-Type');
        $this->assertNull($this->response->getHeader('Content-Type'));
    }

    public function testClearHeaders()
    {
        $this->response->setHeader('Content-Type', 'application/json');
        $this->response->setHeader('X-Custom-Header', 'value');
        $this->response->clearHeaders();
        $this->assertEmpty($this->response->getHeaders());
    }

    public function testSetCookie()
    {
        $this->response->setCookie('test', 'value', 3600);
        $cookies = $this->response->getCookies();
        $this->assertArrayHasKey('test', $cookies);
        $this->assertEquals('value', $cookies['test']['value']);
        $this->assertEquals(3600, $cookies['test']['expire']);
    }

    public function testSetCookies()
    {
        $cookies = [
            'test1' => ['value' => 'value1', 'expire' => 3600],
            'test2' => ['value' => 'value2', 'expire' => 7200]
        ];
        $this->response->setCookies($cookies);
        $this->assertEquals($cookies, $this->response->getCookies());
    }

    public function testRemoveCookie()
    {
        $this->response->setCookie('test', 'value', 3600);
        $this->response->removeCookie('test');
        $this->assertArrayNotHasKey('test', $this->response->getCookies());
    }

    public function testClearCookies()
    {
        $this->response->setCookie('test1', 'value1', 3600);
        $this->response->setCookie('test2', 'value2', 7200);
        $this->response->clearCookies();
        $this->assertEmpty($this->response->getCookies());
    }

    public function testSetLastModified()
    {
        $date = new \DateTime();
        $this->response->setLastModified($date);
        $this->assertEquals($date->format('D, d M Y H:i:s') . ' GMT', $this->response->getHeader('Last-Modified'));
    }

    public function testSetETag()
    {
        $this->response->setETag('test-etag');
        $this->assertEquals('"test-etag"', $this->response->getHeader('ETag'));
    }

    public function testSetCache()
    {
        $this->response->setCache(3600);
        $this->assertEquals('max-age=3600', $this->response->getHeader('Cache-Control'));
    }

    public function testSetNoCache()
    {
        $this->response->setNoCache();
        $this->assertEquals('no-store, no-cache, must-revalidate, max-age=0', $this->response->getHeader('Cache-Control'));
    }

    public function testSetPrivate()
    {
        $this->response->setPrivate();
        $this->assertEquals('private', $this->response->getHeader('Cache-Control'));
    }

    public function testSetPublic()
    {
        $this->response->setPublic();
        $this->assertEquals('public', $this->response->getHeader('Cache-Control'));
    }

    public function testSetExpires()
    {
        $date = new \DateTime('+1 hour');
        $this->response->setExpires($date);
        $this->assertEquals($date->format('D, d M Y H:i:s') . ' GMT', $this->response->getHeader('Expires'));
    }

    public function testSetVary()
    {
        $this->response->setVary('Accept-Encoding');
        $this->assertEquals('Accept-Encoding', $this->response->getHeader('Vary'));
    }

    public function testSetContentType()
    {
        $this->response->setContentType('application/json');
        $this->assertEquals('application/json', $this->response->getHeader('Content-Type'));
    }

    public function testSetContentLength()
    {
        $this->response->setContentLength(1024);
        $this->assertEquals('1024', $this->response->getHeader('Content-Length'));
    }

    public function testSetContentDisposition()
    {
        $this->response->setContentDisposition('attachment', 'test.txt');
        $this->assertEquals('attachment; filename="test.txt"', $this->response->getHeader('Content-Disposition'));
    }

    public function testSetLocation()
    {
        $this->response->setLocation('http://example.com');
        $this->assertEquals('http://example.com', $this->response->getHeader('Location'));
    }

    public function testSetRefresh()
    {
        $this->response->setRefresh(5, 'http://example.com');
        $this->assertEquals('5;url=http://example.com', $this->response->getHeader('Refresh'));
    }

    public function testSetAllow()
    {
        $this->response->setAllow(['GET', 'POST']);
        $this->assertEquals('GET, POST', $this->response->getHeader('Allow'));
    }

    public function testSetContentLanguage()
    {
        $this->response->setContentLanguage('en');
        $this->assertEquals('en', $this->response->getHeader('Content-Language'));
    }

    public function testSetContentEncoding()
    {
        $this->response->setContentEncoding('gzip');
        $this->assertEquals('gzip', $this->response->getHeader('Content-Encoding'));
    }

    public function testSetXFrameOptions()
    {
        $this->response->setXFrameOptions('DENY');
        $this->assertEquals('DENY', $this->response->getHeader('X-Frame-Options'));
    }

    public function testSetXContentTypeOptions()
    {
        $this->response->setXContentTypeOptions('nosniff');
        $this->assertEquals('nosniff', $this->response->getHeader('X-Content-Type-Options'));
    }

    public function testSetXSSProtection()
    {
        $this->response->setXSSProtection(true);
        $this->assertEquals('1; mode=block', $this->response->getHeader('X-XSS-Protection'));
    }

    public function testSetStrictTransportSecurity()
    {
        $this->response->setStrictTransportSecurity(31536000);
        $this->assertEquals('max-age=31536000', $this->response->getHeader('Strict-Transport-Security'));
    }

    public function testSetContentSecurityPolicy()
    {
        $this->response->setContentSecurityPolicy("default-src 'self'");
        $this->assertEquals("default-src 'self'", $this->response->getHeader('Content-Security-Policy'));
    }

    public function testSetReferrerPolicy()
    {
        $this->response->setReferrerPolicy('strict-origin-when-cross-origin');
        $this->assertEquals('strict-origin-when-cross-origin', $this->response->getHeader('Referrer-Policy'));
    }

    public function testSetPermissionsPolicy()
    {
        $this->response->setPermissionsPolicy('geolocation=()');
        $this->assertEquals('geolocation=()', $this->response->getHeader('Permissions-Policy'));
    }

    public function testSetCrossOriginEmbedderPolicy()
    {
        $this->response->setCrossOriginEmbedderPolicy('require-corp');
        $this->assertEquals('require-corp', $this->response->getHeader('Cross-Origin-Embedder-Policy'));
    }

    public function testSetCrossOriginOpenerPolicy()
    {
        $this->response->setCrossOriginOpenerPolicy('same-origin');
        $this->assertEquals('same-origin', $this->response->getHeader('Cross-Origin-Opener-Policy'));
    }

    public function testSetCrossOriginResourcePolicy()
    {
        $this->response->setCrossOriginResourcePolicy('same-origin');
        $this->assertEquals('same-origin', $this->response->getHeader('Cross-Origin-Resource-Policy'));
    }

    public function testSetAccessControlAllowOrigin()
    {
        $this->response->setAccessControlAllowOrigin('*');
        $this->assertEquals('*', $this->response->getHeader('Access-Control-Allow-Origin'));
    }

    public function testSetAccessControlAllowMethods()
    {
        $this->response->setAccessControlAllowMethods(['GET', 'POST']);
        $this->assertEquals('GET, POST', $this->response->getHeader('Access-Control-Allow-Methods'));
    }

    public function testSetAccessControlAllowHeaders()
    {
        $this->response->setAccessControlAllowHeaders(['Content-Type', 'Authorization']);
        $this->assertEquals('Content-Type, Authorization', $this->response->getHeader('Access-Control-Allow-Headers'));
    }

    public function testSetAccessControlAllowCredentials()
    {
        $this->response->setAccessControlAllowCredentials(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Allow-Credentials'));
    }

    public function testSetAccessControlMaxAge()
    {
        $this->response->setAccessControlMaxAge(3600);
        $this->assertEquals('3600', $this->response->getHeader('Access-Control-Max-Age'));
    }

    public function testSetAccessControlExposeHeaders()
    {
        $this->response->setAccessControlExposeHeaders(['Content-Length', 'X-Custom-Header']);
        $this->assertEquals('Content-Length, X-Custom-Header', $this->response->getHeader('Access-Control-Expose-Headers'));
    }

    public function testSetAccessControlRequestMethod()
    {
        $this->response->setAccessControlRequestMethod('GET');
        $this->assertEquals('GET', $this->response->getHeader('Access-Control-Request-Method'));
    }

    public function testSetAccessControlRequestHeaders()
    {
        $this->response->setAccessControlRequestHeaders(['Content-Type', 'Authorization']);
        $this->assertEquals('Content-Type, Authorization', $this->response->getHeader('Access-Control-Request-Headers'));
    }

    public function testSetAccessControlRequestPrivateNetwork()
    {
        $this->response->setAccessControlRequestPrivateNetwork(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Request-Private-Network'));
    }

    public function testSetAccessControlAllowPrivateNetwork()
    {
        $this->response->setAccessControlAllowPrivateNetwork(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Allow-Private-Network'));
    }

    public function testSetAccessControlRequestCredentials()
    {
        $this->response->setAccessControlRequestCredentials(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Request-Credentials'));
    }

    public function testSetAccessControlRequestMode()
    {
        $this->response->setAccessControlRequestMode('cors');
        $this->assertEquals('cors', $this->response->getHeader('Access-Control-Request-Mode'));
    }

    public function testSetAccessControlRequestDestination()
    {
        $this->response->setAccessControlRequestDestination('document');
        $this->assertEquals('document', $this->response->getHeader('Access-Control-Request-Destination'));
    }

    public function testSetAccessControlRequestRedirect()
    {
        $this->response->setAccessControlRequestRedirect('follow');
        $this->assertEquals('follow', $this->response->getHeader('Access-Control-Request-Redirect'));
    }

    public function testSetAccessControlRequestPreflight()
    {
        $this->response->setAccessControlRequestPreflight(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Request-Preflight'));
    }

    public function testSetAccessControlRequestPreflightMaxAge()
    {
        $this->response->setAccessControlRequestPreflightMaxAge(3600);
        $this->assertEquals('3600', $this->response->getHeader('Access-Control-Request-Preflight-Max-Age'));
    }

    public function testSetAccessControlRequestPreflightCache()
    {
        $this->response->setAccessControlRequestPreflightCache(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Request-Preflight-Cache'));
    }

    public function testSetAccessControlRequestPreflightCredentials()
    {
        $this->response->setAccessControlRequestPreflightCredentials(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Request-Preflight-Credentials'));
    }

    public function testSetAccessControlRequestPreflightHeaders()
    {
        $this->response->setAccessControlRequestPreflightHeaders(['Content-Type', 'Authorization']);
        $this->assertEquals('Content-Type, Authorization', $this->response->getHeader('Access-Control-Request-Preflight-Headers'));
    }

    public function testSetAccessControlRequestPreflightMethod()
    {
        $this->response->setAccessControlRequestPreflightMethod('GET');
        $this->assertEquals('GET', $this->response->getHeader('Access-Control-Request-Preflight-Method'));
    }

    public function testSetAccessControlRequestPreflightOrigin()
    {
        $this->response->setAccessControlRequestPreflightOrigin('http://example.com');
        $this->assertEquals('http://example.com', $this->response->getHeader('Access-Control-Request-Preflight-Origin'));
    }

    public function testSetAccessControlRequestPreflightPrivateNetwork()
    {
        $this->response->setAccessControlRequestPreflightPrivateNetwork(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Request-Preflight-Private-Network'));
    }

    public function testSetAccessControlRequestPreflightRedirect()
    {
        $this->response->setAccessControlRequestPreflightRedirect('follow');
        $this->assertEquals('follow', $this->response->getHeader('Access-Control-Request-Preflight-Redirect'));
    }

    public function testSetAccessControlRequestPreflightMode()
    {
        $this->response->setAccessControlRequestPreflightMode('cors');
        $this->assertEquals('cors', $this->response->getHeader('Access-Control-Request-Preflight-Mode'));
    }

    public function testSetAccessControlRequestPreflightDestination()
    {
        $this->response->setAccessControlRequestPreflightDestination('document');
        $this->assertEquals('document', $this->response->getHeader('Access-Control-Request-Preflight-Destination'));
    }

    public function testSetAccessControlRequestPreflightHeaders()
    {
        $this->response->setAccessControlRequestPreflightHeaders(['Content-Type', 'Authorization']);
        $this->assertEquals('Content-Type, Authorization', $this->response->getHeader('Access-Control-Request-Preflight-Headers'));
    }

    public function testSetAccessControlRequestPreflightMethod()
    {
        $this->response->setAccessControlRequestPreflightMethod('GET');
        $this->assertEquals('GET', $this->response->getHeader('Access-Control-Request-Preflight-Method'));
    }

    public function testSetAccessControlRequestPreflightOrigin()
    {
        $this->response->setAccessControlRequestPreflightOrigin('http://example.com');
        $this->assertEquals('http://example.com', $this->response->getHeader('Access-Control-Request-Preflight-Origin'));
    }

    public function testSetAccessControlRequestPreflightPrivateNetwork()
    {
        $this->response->setAccessControlRequestPreflightPrivateNetwork(true);
        $this->assertEquals('true', $this->response->getHeader('Access-Control-Request-Preflight-Private-Network'));
    }

    public function testSetAccessControlRequestPreflightRedirect()
    {
        $this->response->setAccessControlRequestPreflightRedirect('follow');
        $this->assertEquals('follow', $this->response->getHeader('Access-Control-Request-Preflight-Redirect'));
    }

    public function testSetAccessControlRequestPreflightMode()
    {
        $this->response->setAccessControlRequestPreflightMode('cors');
        $this->assertEquals('cors', $this->response->getHeader('Access-Control-Request-Preflight-Mode'));
    }

    public function testSetAccessControlRequestPreflightDestination()
    {
        $this->response->setAccessControlRequestPreflightDestination('document');
        $this->assertEquals('document', $this->response->getHeader('Access-Control-Request-Preflight-Destination'));
    }
} 