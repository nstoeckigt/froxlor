<?php
use PHPUnit\Framework\TestCase;

use Froxlor\Api\Commands\Customers;
use Froxlor\Api\Commands\Domains;

/**
 * regression test: updating a domain whose specialsettings/ssl_specialsettings are NULL in
 * the database (e.g. when they were never set) passed null to str_replace(), which is a
 * deprecation on PHP 8.1+ and aborted the API call with "Internal PHP error: #8192".
 *
 * @covers \Froxlor\Api\Commands\Domains
 */
class DomainsUpdateNullSpecialsettingsTest extends TestCase
{
	public function testUpdateDomainWithNullSpecialsettings()
	{
		global $admin_userdata;

		$json_result = Customers::getLocal($admin_userdata, [
			'loginname' => 'test1'
		])->get();
		$customer_userdata = json_decode($json_result, true)['data'];

		Domains::getLocal($admin_userdata, [
			'domain' => 'nullspecialsettings.local',
			'customerid' => $customer_userdata['customerid']
		])->add();

		$stmt = \Froxlor\Database\Database::prepare("UPDATE `" . TABLE_PANEL_DOMAINS . "` SET `specialsettings` = NULL, `ssl_specialsettings` = NULL WHERE `domain` = :domain");
		\Froxlor\Database\Database::pexecute($stmt, ['domain' => 'nullspecialsettings.local']);

		// the API error handler turns deprecations into exceptions, mimic that here
		set_error_handler(function ($errno, $errstr) {
			throw new \ErrorException($errstr, 0, $errno);
		}, E_DEPRECATED);
		try {
			$json_result = Domains::getLocal($admin_userdata, [
				'domainname' => 'nullspecialsettings.local',
				'selectserveralias' => 2
			])->update();
		} finally {
			restore_error_handler();
		}
		$domain_data = json_decode($json_result, true)['data'];
		$this->assertEquals('0', (string)$domain_data['wwwserveralias']);

		Domains::getLocal($admin_userdata, [
			'domainname' => 'nullspecialsettings.local'
		])->delete();
	}
}
