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

class SessionHandlerTest extends \PHPUnit\Framework\TestCase
{
    public function setUp(): void
    {
        SessionHandler::disable();
    }

    public function tearDown(): void
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
    public function testEnableDisableStatus()
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
        $this->assertEquals(array('1', '2', 'another'), SessionHandler::getExcludeKeys());
    }

    /**
     * @depends testEnableDisableStatus
     * @depends testExcludeKeys
     */
    public function testProcess()
    {
        $_SESSION['testing'] = 'somevar';

        SessionHandler::setStatus(false);
        $this->assertNull(SessionHandler::process());

        SessionHandler::enable();
        $this->assertStringContainsString('somevar', SessionHandler::process());
        $this->assertEquals(serialize($_SESSION), SessionHandler::process());

        $_SESSION['process'] = 'ignorethis';
        $this->assertEquals(serialize($_SESSION), SessionHandler::process());

        SessionHandler::excludeKeys(array('NonExistingSessionVariable'));
        SessionHandler::excludeKeys(array('process'));
        $process = SessionHandler::process();
        $this->assertEquals(array('testing' => 'somevar'), unserialize($process));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testExceptionArray()
    {
        try {
            SessionHandler::excludeKeys('stringvalue is not scalar array');
            $this->expectException('PHPUnit_Framework_Error');
        } catch (\Throwable $e) {
            // echo '~~~~As expected PHP7 throws Throwable.';
        } catch (\Exception $e) {
            // echo '~~~~As expected PHP5 throws Exception.';
        }
    }
}
