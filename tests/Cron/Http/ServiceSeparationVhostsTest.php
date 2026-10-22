<?php
use PHPUnit\Framework\TestCase;

use Froxlor\Api\Commands\Customers;
use Froxlor\Api\Commands\Domains;
use Froxlor\Cron\Http\Apache;
use Froxlor\Cron\Http\Nginx;
use Froxlor\Cron\Http\WebserverBase;
use Froxlor\Settings;

/**
 * regression test: with service separation the panel runs on its own webserver/ports.
 * Nginx::createNginxHosts() used to call getVhostsToCreate() without a webserver filter,
 * so nginx wrote vhosts for customer domains on ports (80/443) that belong to apache.
 *
 * @covers \Froxlor\Cron\Http\Nginx
 * @covers \Froxlor\Cron\Http\WebserverBase
 */
class ServiceSeparationVhostsTest extends TestCase
{
	private const DOMAIN = 'servicesepvhosttest.local';

	private const SETTINGS = [
		'system.webserver',
		'system.enable_service_ports',
		'system.panel_webserver',
		'system.panel_http_port',
		'system.panel_https_port',
		'system.apachereload_command'
	];

	private $saved = [];

	protected function setUp(): void
	{
		global $admin_userdata;

		foreach (self::SETTINGS as $setting) {
			$this->saved[$setting] = Settings::Get($setting);
		}

		$json_result = Customers::getLocal($admin_userdata, [
			'loginname' => 'test1'
		])->get();
		$customer_userdata = json_decode($json_result, true)['data'];
		Domains::getLocal($admin_userdata, [
			'domain' => self::DOMAIN,
			'customerid' => $customer_userdata['customerid']
		])->add();
	}

	protected function tearDown(): void
	{
		global $admin_userdata;

		Domains::getLocal($admin_userdata, [
			'domainname' => self::DOMAIN
		])->delete();
		foreach ($this->saved as $setting => $value) {
			Settings::Set($setting, $value, false);
		}
	}

	private function separate(string $customerWebserver, string $panelWebserver): void
	{
		Settings::Set('system.webserver', $customerWebserver, false);
		Settings::Set('system.enable_service_ports', '1', false);
		Settings::Set('system.panel_webserver', $panelWebserver, false);
		Settings::Set('system.panel_http_port', '8080', false);
		Settings::Set('system.panel_https_port', '8043', false);
	}

	public function testCustomerDomainBelongsToCustomerWebserverOnly()
	{
		$this->separate('apache2', 'nginx');

		$this->assertArrayHasKey(self::DOMAIN, WebserverBase::getVhostsToCreate('apache2'));
		$this->assertArrayNotHasKey(self::DOMAIN, WebserverBase::getVhostsToCreate('nginx'));
	}

	public function testNginxDoesNotGenerateApacheOwnedVhosts()
	{
		$this->separate('apache2', 'nginx');

		$nginx = $this->getMockBuilder(Nginx::class)
			->onlyMethods(['getLogFiles'])
			->getMock();
		$nginx->method('getLogFiles')->willReturn('');

		$method = new ReflectionMethod(Nginx::class, 'createNginxHosts');
		$data = new ReflectionProperty(Nginx::class, 'nginx_data');
		// setAccessible() is needed before PHP 8.1 and deprecated as of PHP 8.5
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
			$data->setAccessible(true);
		}
		$method->invoke($nginx);

		$this->assertSame([], $data->getValue($nginx));
	}

	public function testNginxKeepsCustomerDomainsWithoutSeparation()
	{
		Settings::Set('system.webserver', 'nginx', false);
		Settings::Set('system.enable_service_ports', '0', false);

		$this->assertArrayHasKey(self::DOMAIN, WebserverBase::getVhostsToCreate('nginx'));
	}

	public function testReloadCommandPerWebserver()
	{
		$this->separate('apache2', 'nginx');
		Settings::Set('system.apachereload_command', 'service apache2 reload', false);

		$reload = function (string $class) {
			$ws = $this->getMockBuilder($class)->disableOriginalConstructor()->onlyMethods([])->getMock();
			$method = new ReflectionMethod($class, 'getReloadCommand');
			// setAccessible() is needed before PHP 8.1 and deprecated as of PHP 8.5
			if (PHP_VERSION_ID < 80100) {
				$method->setAccessible(true);
			}
			return $method->invoke($ws);
		};

		// customer webserver keeps the configured command, the panel webserver gets its own
		$this->assertSame('service apache2 reload', $reload(Apache::class));
		$this->assertSame('service nginx reload', $reload(Nginx::class));

		Settings::Set('system.enable_service_ports', '0', false);
		$this->assertSame('service apache2 reload', $reload(Nginx::class));
	}
}
