<?php
use PHPUnit\Framework\TestCase;

use Froxlor\Cron\Http\LetsEncrypt\AcmeSh;
use Froxlor\Settings;

/**
 * regression test: switching between rsa and ecc certificates leaves the previous
 * acme.sh folder behind. getCertificateFolder() always preferred the folder without
 * "_ecc" as soon as it existed, even if it held no certificate, so the real
 * certificate in "<domain>_ecc" was never found.
 *
 * @covers \Froxlor\Cron\Http\LetsEncrypt\AcmeSh
 */
class AcmeShCertificateFolderTest extends TestCase
{
	private $base;

	private $saved;

	protected function setUp(): void
	{
		$this->base = sys_get_temp_dir() . '/frx_acme_' . uniqid();
		mkdir($this->base);
		touch($this->base . '/acme.sh');
		$this->saved = Settings::Get('system.acmeshpath');
		Settings::Set('system.acmeshpath', $this->base . '/acme.sh', false);
	}

	protected function tearDown(): void
	{
		Settings::Set('system.acmeshpath', $this->saved, false);
		exec('rm -rf ' . escapeshellarg($this->base));
	}

	private function folder(string $domain): string
	{
		$method = new ReflectionMethod(AcmeSh::class, 'getCertificateFolder');
		// setAccessible() is needed before PHP 8.1 and deprecated as of PHP 8.5
		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}
		return rtrim($method->invoke(null, $domain), '/');
	}

	public function testPrefersFolderWithCertificate()
	{
		mkdir($this->base . '/host.local');
		mkdir($this->base . '/host.local_ecc');
		touch($this->base . '/host.local_ecc/fullchain.cer');

		$this->assertSame($this->base . '/host.local_ecc', $this->folder('host.local'));
	}

	public function testKeepsRsaFolderWithCertificate()
	{
		mkdir($this->base . '/host.local');
		mkdir($this->base . '/host.local_ecc');
		touch($this->base . '/host.local/fullchain.cer');

		$this->assertSame($this->base . '/host.local', $this->folder('host.local'));
	}

	public function testFallsBackToExistingFolderWithoutCertificate()
	{
		mkdir($this->base . '/host.local_ecc');

		$this->assertSame($this->base . '/host.local_ecc', $this->folder('host.local'));
	}

	public function testNoFolderReturnsEmpty()
	{
		$this->assertSame('', $this->folder('host.local'));
	}
}
