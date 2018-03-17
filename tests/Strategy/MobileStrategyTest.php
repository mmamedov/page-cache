<?php
/**
 * This file is part of the PageCache package.
 *
 * @author Muhammed Mamedov <mm@turkmenweb.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PageCache\Tests\Strategy;

use PageCache\SessionHandler;
use PageCache\Strategy\MobileStrategy;
use PageCache\StrategyInterface;

class MobileStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function testStrategy()
    {
        //MobileDetection stub, to simulate a mobile device
        $mobilestub = $this->getMockBuilder('Mobile_Detect')
            ->setMethods(array('isMobile', 'isTablet'))
            ->getMock();

        $mobilestub->method('isMobile')
            ->willReturn(true);

        $mobilestub->method('isTablet')
            ->willReturn(false);

        $strategy = new MobileStrategy($mobilestub);

        //expected string, with -mob in the end
        SessionHandler::disable();
        $uri = empty($_SERVER['REQUEST_URI']) ? 'uri' : $_SERVER['REQUEST_URI'];
        $query = empty($_SERVER['QUERY_STRING']) ? 'query' : $_SERVER['QUERY_STRING'];
        $md5 = md5($uri . $_SERVER['SCRIPT_NAME'] . $query) . '-mob';

        $this->assertTrue($mobilestub instanceof \Mobile_Detect);
        $this->assertTrue($strategy instanceof StrategyInterface);
        $this->assertEquals($md5, $strategy->strategy());
    }
}
