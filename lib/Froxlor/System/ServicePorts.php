<?php

/**
 * This file is part of the Froxlor project.
 *
 * @copyright  the froxlor team
 * @license    GPLv2
 */

namespace Froxlor\System;

use Froxlor\Settings;

/**
 * Helper for the service-separation feature: which webserver
 * handles which port when the panel and customer webs are split.
 */
class ServicePorts
{
	/**
	 * Parse a "service:port;service:port" list (e.g. "nginx:8080;apache2:80").
	 * Kept as a utility for validation and import/export of settings.
	 *
	 * @return array<int,string> port => service (lowercased)
	 */
	public static function parseServicePorts(string $setting): array
	{
		$result = [];
		if ($setting === '') {
			return $result;
		}
		foreach (explode(';', $setting) as $part) {
			$part = trim($part);
			if ($part === '') {
				continue;
			}
			$pair = explode(':', $part);
			if (count($pair) !== 2) {
				continue;
			}
			$service = strtolower(trim($pair[0]));
			$port = (int) trim($pair[1]);
			if ($port > 0 && $service !== '') {
				$result[$port] = $service;
			}
		}
		return $result;
	}

	/**
	 * Check whether a given port in a "service:port;..." list is assigned to $service.
	 */
	public static function isPortForService(int $port, string $servicePortsSetting, string $service): bool
	{
		$ports = self::parseServicePorts($servicePortsSetting);
		return isset($ports[$port]) && $ports[$port] === strtolower($service);
	}

	/**
	 * Whether the service-separation feature is turned on.
	 */
	public static function isEnabled(): bool
	{
		return Settings::Get('system.enable_service_ports') == '1';
	}

	/**
	 * The webserver configured for the Froxlor panel.
	 * Falls back to the global webserver when the panel-specific setting is empty.
	 */
	public static function getPanelWebserver(): string
	{
		$ws = Settings::Get('system.panel_webserver');
		if (!empty($ws)) {
			return (string) $ws;
		}
		return self::getCustomerWebserver();
	}

	/**
	 * The webserver handling customer domains. Same as the legacy `system.webserver` setting.
	 */
	public static function getCustomerWebserver(): string
	{
		return (string) (Settings::Get('system.webserver') ?? '');
	}

	/**
	 * The ports reserved for the Froxlor panel.
	 *
	 * @return array<int,string> port => service
	 */
	public static function getPanelPorts(): array
	{
		$ports = [];
		$http = (int) (Settings::Get('system.panel_http_port') ?? 0);
		$https = (int) (Settings::Get('system.panel_https_port') ?? 0);
		$ws = self::getPanelWebserver();
		if ($http > 0) {
			$ports[$http] = $ws;
		}
		if ($https > 0) {
			$ports[$https] = $ws;
		}
		return $ports;
	}

	/**
	 * Whether a port is a panel port (managed by the panel webserver).
	 */
	public static function isPanelPort(int $port): bool
	{
		return isset(self::getPanelPorts()[$port]);
	}

	/**
	 * Decide whether the given port should be served by the given webserver
	 * under the current configuration. When service separation is disabled,
	 * every port goes to `system.webserver`; when enabled, panel ports go to
	 * the panel webserver and all remaining ports to the customer webserver.
	 *
	 * @param string $webserver "nginx" or "apache2"
	 */
	public static function isPortForWebserver(int $port, string $webserver): bool
	{
		$webserver = strtolower($webserver);
		if (!self::isEnabled()) {
			return self::getCustomerWebserver() === $webserver;
		}
		$panelPorts = self::getPanelPorts();
		if (isset($panelPorts[$port])) {
			return $panelPorts[$port] === $webserver;
		}
		return self::getCustomerWebserver() === $webserver;
	}
}
