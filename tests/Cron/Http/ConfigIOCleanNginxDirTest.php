<?php
use PHPUnit\Framework\TestCase;

use Froxlor\Cron\Http\ConfigIO;
use Froxlor\Settings;

/**
 * regression test: with service separation nginx writes its vhosts to
 * system.nginxconf_vhost, but cleanWebserverConfigs() only cleaned the apache
 * directories, so stale froxlor-generated nginx vhosts stayed there forever.
 *
 * @covers \Froxlor\Cron\Http\ConfigIO
 */
class ConfigIOCleanNginxDirTest extends TestCase
{
	private const SETTINGS = [
		'system.apacheconf_vhost',
		'system.apacheconf_diroptions',
		'system.nginxconf_vhost',
		'system.enable_service_ports'
	];

	private $base;

	private $saved = [];

	protected function setUp(): void
	{
		$this->base = sys_get_temp_dir() . '/frx_cleanup_' . uniqid();
		foreach (['apache', 'nginx'] as $dir) {
			mkdir($this->base . '/' . $dir, 0777, true);
			touch($this->base . '/' . $dir . '/34_froxlor_normal_vhost_test.local.conf');
			touch($this->base . '/' . $dir . '/custom.conf');
		}
		foreach (self::SETTINGS as $setting) {
			$this->saved[$setting] = Settings::Get($setting);
		}
		Settings::Set('system.apacheconf_vhost', $this->base . '/apache/', false);
		Settings::Set('system.apacheconf_diroptions', $this->base . '/apache/', false);
		Settings::Set('system.nginxconf_vhost', $this->base . '/nginx/', false);
	}

	protected function tearDown(): void
	{
		foreach ($this->saved as $setting => $value) {
			Settings::Set($setting, $value, false);
		}
		exec('rm -rf ' . escapeshellarg($this->base));
	}

	private function clean(): void
	{
		$method = new ReflectionMethod(ConfigIO::class, 'cleanWebserverConfigs');
		// setAccessible() is needed before PHP 8.1 and deprecated as of PHP 8.5
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}
		$method->invoke(new ConfigIO());
	}

	public function testNginxDirIsCleanedWithServiceSeparation()
	{
		Settings::Set('system.enable_service_ports', '1', false);
		$this->clean();

		$this->assertFileDoesNotExist($this->base . '/apache/34_froxlor_normal_vhost_test.local.conf');
		$this->assertFileDoesNotExist($this->base . '/nginx/34_froxlor_normal_vhost_test.local.conf');
		// foreign files are left alone
		$this->assertFileExists($this->base . '/nginx/custom.conf');
		$this->assertFileExists($this->base . '/apache/custom.conf');
	}

	public function testNginxDirIsLeftAloneWithoutServiceSeparation()
	{
		Settings::Set('system.enable_service_ports', '0', false);
		$this->clean();

		$this->assertFileDoesNotExist($this->base . '/apache/34_froxlor_normal_vhost_test.local.conf');
		$this->assertFileExists($this->base . '/nginx/34_froxlor_normal_vhost_test.local.conf');
	}
}
