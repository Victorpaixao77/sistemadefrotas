<?php

namespace GestaoInterativa\Tests\Session;

use PHPUnit\Framework\TestCase;
use GestaoInterativa\Session\Session;

class SessionTest extends TestCase
{
    private $session;

    protected function setUp(): void
    {
        $this->session = new Session();
    }

    public function testSessionCanBeCreated()
    {
        $this->assertInstanceOf(Session::class, $this->session);
    }

    public function testStart()
    {
        $this->session->start();
        $this->assertEquals(PHP_SESSION_ACTIVE, session_status());
    }

    public function testSet()
    {
        $this->session->set('test_key', 'test_value');
        $this->assertEquals('test_value', $_SESSION['test_key']);
    }

    public function testGet()
    {
        $_SESSION['test_key'] = 'test_value';
        $this->assertEquals('test_value', $this->session->get('test_key'));
        $this->assertNull($this->session->get('non_existent_key'));
        $this->assertEquals('default', $this->session->get('non_existent_key', 'default'));
    }

    public function testHas()
    {
        $_SESSION['test_key'] = 'test_value';
        $this->assertTrue($this->session->has('test_key'));
        $this->assertFalse($this->session->has('non_existent_key'));
    }

    public function testRemove()
    {
        $_SESSION['test_key'] = 'test_value';
        $this->session->remove('test_key');
        $this->assertFalse(isset($_SESSION['test_key']));
    }

    public function testClear()
    {
        $_SESSION['test_key1'] = 'test_value1';
        $_SESSION['test_key2'] = 'test_value2';
        $this->session->clear();
        $this->assertEmpty($_SESSION);
    }

    public function testDestroy()
    {
        $this->session->start();
        $_SESSION['test_key'] = 'test_value';
        $this->session->destroy();
        $this->assertEquals(PHP_SESSION_NONE, session_status());
    }

    public function testRegenerate()
    {
        $this->session->start();
        $oldId = session_id();
        $this->session->regenerate();
        $this->assertNotEquals($oldId, session_id());
    }

    public function testFlash()
    {
        $this->session->flash('message', 'Test message');
        $this->assertEquals('Test message', $this->session->get('flash_message'));
        $this->assertTrue($this->session->has('flash_message'));
    }

    public function testGetFlash()
    {
        $this->session->flash('message', 'Test message');
        $this->assertEquals('Test message', $this->session->getFlash('message'));
        $this->assertFalse($this->session->has('flash_message'));
    }

    public function testHasFlash()
    {
        $this->session->flash('message', 'Test message');
        $this->assertTrue($this->session->hasFlash('message'));
        $this->assertFalse($this->session->hasFlash('non_existent_message'));
    }

    public function testKeepFlash()
    {
        $this->session->flash('message', 'Test message');
        $this->session->keepFlash('message');
        $this->assertTrue($this->session->has('flash_message'));
    }

    public function testClearFlash()
    {
        $this->session->flash('message1', 'Test message 1');
        $this->session->flash('message2', 'Test message 2');
        $this->session->clearFlash();
        $this->assertFalse($this->session->has('flash_message1'));
        $this->assertFalse($this->session->has('flash_message2'));
    }

    public function testAll()
    {
        $_SESSION['key1'] = 'value1';
        $_SESSION['key2'] = 'value2';
        $this->assertEquals([
            'key1' => 'value1',
            'key2' => 'value2'
        ], $this->session->all());
    }

    public function testReplace()
    {
        $_SESSION['key1'] = 'old_value1';
        $_SESSION['key2'] = 'old_value2';
        $this->session->replace([
            'key1' => 'new_value1',
            'key3' => 'new_value3'
        ]);
        $this->assertEquals('new_value1', $_SESSION['key1']);
        $this->assertEquals('old_value2', $_SESSION['key2']);
        $this->assertEquals('new_value3', $_SESSION['key3']);
    }

    public function testIsStarted()
    {
        $this->assertFalse($this->session->isStarted());
        $this->session->start();
        $this->assertTrue($this->session->isStarted());
    }

    public function testGetId()
    {
        $this->session->start();
        $this->assertNotEmpty($this->session->getId());
    }

    public function testSetId()
    {
        $this->session->setId('test_session_id');
        $this->assertEquals('test_session_id', $this->session->getId());
    }

    public function testGetName()
    {
        $this->assertEquals(session_name(), $this->session->getName());
    }

    public function testSetName()
    {
        $this->session->setName('test_session');
        $this->assertEquals('test_session', $this->session->getName());
    }

    public function testGetCookieParams()
    {
        $params = $this->session->getCookieParams();
        $this->assertIsArray($params);
        $this->assertArrayHasKey('lifetime', $params);
        $this->assertArrayHasKey('path', $params);
        $this->assertArrayHasKey('domain', $params);
        $this->assertArrayHasKey('secure', $params);
        $this->assertArrayHasKey('httponly', $params);
    }

    public function testSetCookieParams()
    {
        $params = [
            'lifetime' => 3600,
            'path' => '/',
            'domain' => 'example.com',
            'secure' => true,
            'httponly' => true
        ];
        $this->session->setCookieParams($params);
        $this->assertEquals($params, $this->session->getCookieParams());
    }

    public function testGetSavePath()
    {
        $this->assertEquals(session_save_path(), $this->session->getSavePath());
    }

    public function testSetSavePath()
    {
        $this->session->setSavePath('/tmp');
        $this->assertEquals('/tmp', $this->session->getSavePath());
    }

    public function testGetStatus()
    {
        $this->assertEquals(session_status(), $this->session->getStatus());
    }

    public function testIsActive()
    {
        $this->assertFalse($this->session->isActive());
        $this->session->start();
        $this->assertTrue($this->session->isActive());
    }

    public function testIsExpired()
    {
        $this->assertFalse($this->session->isExpired());
        $this->session->start();
        $this->session->set('_last_activity', time() - 3600);
        $this->assertTrue($this->session->isExpired());
    }

    public function testUpdateLastActivity()
    {
        $this->session->start();
        $this->session->updateLastActivity();
        $this->assertTrue($this->session->has('_last_activity'));
    }

    public function testGetLastActivity()
    {
        $this->session->start();
        $this->session->updateLastActivity();
        $this->assertIsInt($this->session->getLastActivity());
    }

    public function testSetLifetime()
    {
        $this->session->setLifetime(3600);
        $this->assertEquals(3600, $this->session->getLifetime());
    }

    public function testGetLifetime()
    {
        $this->assertEquals(ini_get('session.gc_maxlifetime'), $this->session->getLifetime());
    }

    public function testSetGcProbability()
    {
        $this->session->setGcProbability(1, 100);
        $this->assertEquals(1, ini_get('session.gc_probability'));
        $this->assertEquals(100, ini_get('session.gc_divisor'));
    }

    public function testGetGcProbability()
    {
        $this->assertEquals(ini_get('session.gc_probability'), $this->session->getGcProbability());
    }

    public function testSetGcDivisor()
    {
        $this->session->setGcDivisor(100);
        $this->assertEquals(100, ini_get('session.gc_divisor'));
    }

    public function testGetGcDivisor()
    {
        $this->assertEquals(ini_get('session.gc_divisor'), $this->session->getGcDivisor());
    }

    public function testSetGcMaxLifetime()
    {
        $this->session->setGcMaxLifetime(3600);
        $this->assertEquals(3600, ini_get('session.gc_maxlifetime'));
    }

    public function testGetGcMaxLifetime()
    {
        $this->assertEquals(ini_get('session.gc_maxlifetime'), $this->session->getGcMaxLifetime());
    }
} 