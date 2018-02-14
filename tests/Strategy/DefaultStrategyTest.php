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
use PageCache\Strategy\DefaultStrategy;
use PageCache\StrategyInterface;

class DefaultStrategyTest extends \PHPUnit\Framework\TestCase
{
    public function testStrategy()
    {
        $strategy = new DefaultStrategy();
        $this->assertTrue($strategy instanceof StrategyInterface);

        SessionHandler::disable();

        $uri = empty($_SERVER['REQUEST_URI']) ? 'uri' : $_SERVER['REQUEST_URI'];
        $query = empty($_SERVER['QUERY_STRING']) ? 'query' : $_SERVER['QUERY_STRING'];
        $md5 = md5($uri . $_SERVER['SCRIPT_NAME'] . $query);

        $this->assertEquals($md5, $strategy->strategy());

        SessionHandler::enable();
        $this->assertNotEmpty($strategy->strategy());
    }
}
