<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PageCache\Tests;

use PageCache\SessionHandler;

class SesssionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        SessionHandler::disable();
    }

    public function testGetStatus()
    {
        $this->assertFalse(SessionHandler::getStatus());
    }

    /**
     * @depends testGetStatus
     */
    public function testEnableDisable()
    {
        SessionHandler::enable();
        $this->assertTrue(SessionHandler::getStatus());

        SessionHandler::disable();
        $this->assertFalse(SessionHandler::getStatus());


        SessionHandler::setStatus(true);
        $this->assertTrue(SessionHandler::getStatus());

        SessionHandler::setStatus(false);
        $this->assertFalse(SessionHandler::getStatus());
    }

    public function testExcludeKeys()
    {
        SessionHandler::excludeKeys(array('count'));
        $this->assertEquals(array('count'), SessionHandler::getExcludeKeys());

        SessionHandler::excludeKeys(array('1', '2', 'another'));
        $this->assertCount(3, SessionHandler::getExcludeKeys());
    }

    /**
     * @depends testEnableDisable
     * @depends testExcludeKeys
     */
    public function testProcess()
    {
        $_SESSION['testing'] = 'somevar';

        SessionHandler::setStatus(false);
        $this->assertNull(SessionHandler::process());

        SessionHandler::enable();
        $this->assertContains('somevar', SessionHandler::process());

        $_SESSION['process'] = 'ignorethis';

        $serialized = serialize($_SESSION);
        $this->assertEquals($serialized, SessionHandler::process());

        SessionHandler::excludeKeys(array('process'));
        $process = SessionHandler::process();
        $this->assertEquals(array('testing' => 'somevar'), unserialize($process));
    }

    /**
     * @expectedException \PHPUnit_Framework_Error
     */
    public function testExceptionArray()
    {
        SessionHandler::excludeKeys('stringval');
    }

    protected function onNotSuccessfulTest($e)
    {
        //PHP 7 scalar exception fix
        parent::onNotSuccessfulTest($e);
    }
}
