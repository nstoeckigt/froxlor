<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../lib/Froxlor/System/ServicePorts.php';

/**
 * Test for ServicePorts helper class
 *
 * @covers \Froxlor\System\ServicePorts
 */
class ServicePortsTest extends TestCase
{
    
    public function testParseServicePortsEmpty()
    {
        // Test mit leerem String
        $result = \Froxlor\System\ServicePorts::parseServicePorts('');
        $this->assertEquals([], $result);
    }
    
    public function testParseServicePortsSingle()
    {
        // Test mit einem einzelnen Port
        $result = \Froxlor\System\ServicePorts::parseServicePorts('nginx:8080');
        $this->assertEquals([8080 => 'nginx'], $result);
    }
    
    public function testParseServicePortsMultiple()
    {
        // Test mit mehreren Ports
        $result = \Froxlor\System\ServicePorts::parseServicePorts('nginx:8080;nginx:8043');
        $this->assertEquals([
            8080 => 'nginx',
            8043 => 'nginx'
        ], $result);
    }
    
    public function testParseServicePortsMixed()
    {
        // Test mit gemischten Services
        $result = \Froxlor\System\ServicePorts::parseServicePorts('nginx:8080;apache:80;nginx:8043');
        $this->assertEquals([
            8080 => 'nginx',
            80 => 'apache',
            8043 => 'nginx'
        ], $result);
    }
    
    public function testParseServicePortsWithSpaces()
    {
        // Test mit Leerzeichen
        $result = \Froxlor\System\ServicePorts::parseServicePorts(' nginx : 8080 ; nginx : 8043 ');
        $this->assertEquals([
            8080 => 'nginx',
            8043 => 'nginx'
        ], $result);
    }
    
    public function testParseServicePortsInvalid()
        {
        // Test mit ungültigen Einträgen
        $result = \Froxlor\System\ServicePorts::parseServicePorts('invalid;;nginx:8080:extra');
        // Sollte nur den gültigen Eintrag zurückgeben
        $this->assertEquals([8080 => 'nginx'], $result);
    }
    
    public function testIsPortForServiceTrue()
    {
        // Test isPortForService mit korrektem Service
        $result = \Froxlor\System\ServicePorts::isPortForService(8080, 'nginx:8080;nginx:8043', 'nginx');
        $this->assertTrue($result);
    }
    
    public function testIsPortForServiceFalse()
    {
        // Test isPortForService mit falschem Service
        $result = \Froxlor\System\ServicePorts::isPortForService(80, 'nginx:8080;nginx:8043', 'apache');
        $this->assertFalse($result);
    }
    
    public function testIsPortForServiceCaseInsensitive()
    {
        // Test Groß-/Kleinschreibung
        $result = \Froxlor\System\ServicePorts::isPortForService(8080, 'NGINX:8080;NGINX:8043', 'nginx');
        $this->assertTrue($result);
    }
    
    public function testParseApacheNginxMixed()
    {
        // Test für typisches Mixed-Setup
        $result = \Froxlor\System\ServicePorts::parseServicePorts('apache:80;apache:443;nginx:8080;nginx:8043');
        $this->assertEquals([
            80 => 'apache',
            443 => 'apache',
            8080 => 'nginx',
            8043 => 'nginx'
        ], $result);
    }
    
    public function testParseServicePortsOnlyApache()
    {
        // Test für Apache-only Setup
        $result = \Froxlor\System\ServicePorts::parseServicePorts('apache:80;apache:443;apache:8080;apache:8043');
        $this->assertEquals([
            80 => 'apache',
            443 => 'apache',
            8080 => 'apache',
            8043 => 'apache'
        ], $result);
    }
    
    public function testParseServicePortsOnlyNginx()
    {
        // Test für Nginx-only Setup
        $result = \Froxlor\System\ServicePorts::parseServicePorts('nginx:80;nginx:443;nginx:8080;nginx:8043');
        $this->assertEquals([
            80 => 'nginx',
            443 => 'nginx',
            8080 => 'nginx',
            8043 => 'nginx'
        ], $result);
    }
}