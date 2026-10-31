<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Returns\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * NEW-1: Magento's ObjectManager passes the default (null) for an optional constructor argument that di.xml does not
 * configure, so without this argument the zero_returns_* events (Zero_Webhooks) never fire.
 */
class EventManagerDiTest extends TestCase
{
    public function testEventManagerIsInjected(): void
    {
        $xml = simplexml_load_file(__DIR__ . '/../../etc/di.xml');
        $this->assertNotFalse($xml);

        $argument = $xml->xpath(
            '/config/type[@name="Zero\\Returns\\Model\\Returns"]/arguments/argument[@name="eventManager"]'
        );

        $this->assertCount(1, $argument);
        $this->assertSame('Magento\\Framework\\Event\\ManagerInterface', trim((string) $argument[0]));
        $this->assertSame('object', (string) $argument[0]->attributes('xsi', true)->type);
    }
}
