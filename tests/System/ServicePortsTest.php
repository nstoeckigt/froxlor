<?php

use PHPUnit\Framework\TestCase;

use Froxlor\System\ServicePorts;

/**
 *
 * @covers \Froxlor\System\ServicePorts
 */
class ServicePortsTest extends TestCase
{
	public function testParseServicePortsEmpty()
	{
		$this->assertEquals([], ServicePorts::parseServicePorts(''));
	}

	public function testParseServicePortsSingle()
	{
		$this->assertEquals([8080 => 'nginx'], ServicePorts::parseServicePorts('nginx:8080'));
	}

	public function testParseServicePortsMultiple()
	{
		$this->assertEquals(
			[8080 => 'nginx', 8043 => 'nginx'],
			ServicePorts::parseServicePorts('nginx:8080;nginx:8043')
		);
	}

	public function testParseServicePortsMixed()
	{
		$this->assertEquals(
			[8080 => 'nginx', 80 => 'apache2', 8043 => 'nginx'],
			ServicePorts::parseServicePorts('nginx:8080;apache2:80;nginx:8043')
		);
	}

	public function testParseServicePortsTrimsWhitespace()
	{
		$this->assertEquals(
			[8080 => 'nginx', 8043 => 'nginx'],
			ServicePorts::parseServicePorts(' nginx : 8080 ; nginx : 8043 ')
		);
	}

	public function testParseServicePortsIgnoresInvalidEntries()
	{
		$this->assertEquals(
			[8080 => 'nginx'],
			ServicePorts::parseServicePorts('invalid;;nginx:8080:extra;nginx:8080')
		);
	}

	public function testParseServicePortsLowercasesServiceNames()
	{
		$this->assertEquals([8080 => 'nginx'], ServicePorts::parseServicePorts('NGINX:8080'));
	}

	public function testIsPortForServiceMatches()
	{
		$this->assertTrue(ServicePorts::isPortForService(8080, 'nginx:8080;nginx:8043', 'nginx'));
	}

	public function testIsPortForServiceRejectsWrongService()
	{
		$this->assertFalse(ServicePorts::isPortForService(80, 'nginx:8080;nginx:8043', 'apache2'));
	}

	public function testIsPortForServiceIsCaseInsensitive()
	{
		$this->assertTrue(ServicePorts::isPortForService(8080, 'NGINX:8080', 'nginx'));
		$this->assertTrue(ServicePorts::isPortForService(8080, 'nginx:8080', 'NGINX'));
	}

	public function testParseServicePortsRejectsZeroOrNegativePort()
	{
		$this->assertEquals([], ServicePorts::parseServicePorts('nginx:0;apache2:-1'));
	}
}
