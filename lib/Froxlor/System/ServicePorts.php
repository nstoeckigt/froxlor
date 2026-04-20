<?php

/**
 * Helper class for service port separation
 * 
 * This file is part of the froxlor project.
 *
 * @copyright  the froxlor team
 * @license    GPLv2
 */

namespace Froxlor\System;

/**
 * ServicePorts helper class
 * 
 * Parses the service ports configuration and provides methods
 * to determine which IPs should be used for panel vs customer.
 */
class ServicePorts
{
    /**
     * Parse service ports setting string
     * 
     * Format: service:port;service:port (e.g., nginx:8080;nginx:8043)
     * 
     * @param string $setting The setting value from panel_settings
     * @return array Array of port => service mappings
     */
    public static function parseServicePorts(string $setting): array
    {
        $result = [];
        
        if (empty($setting)) {
            return $result;
        }
        
        $parts = explode(';', $setting);
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            
            $servicePort = explode(':', $part);
            if (count($servicePort) === 2) {
                $service = strtolower(trim($servicePort[0]));
                $port = (int) trim($servicePort[1]);
                if ($port > 0) {
                    $result[$port] = $service;
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Check if a port should be handled by a specific service
     * 
     * @param int $port The port to check
     * @param string $servicePortsSetting The setting from panel_settings
     * @param string $service The service to check for (e.g., 'nginx', 'apache')
     * @return bool True if the port should be handled by this service
     */
    public static function isPortForService(int $port, string $servicePortsSetting, string $service): bool
    {
        $ports = self::parseServicePorts($servicePortsSetting);
        return isset($ports[$port]) && strtolower($ports[$port]) === strtolower($service);
    }
    
    /**
     * Check if service port separation is enabled
     * 
     * @return bool True if enabled
     */
    public static function isEnabled(): bool
    {
        return \Froxlor\Settings::Get('system.enable_service_ports') == '1';
    }
    
/**
 * Get panel service ports
 * 
 * @return array Array of port => service
 */
public static function getPanelPorts(): array
{
    // New individual settings take priority
    $http_port = (int) \Froxlor\Settings::Get('system.panel_http_port') ?? 0;
    $https_port = (int) \Froxlor\Settings::Get('system.panel_https_port') ?? 0;
    $webserver = \Froxlor\Settings::Get('system.panel_webserver') ?? 'nginx';
    
    $ports = [];
    if ($http_port > 0) {
        $ports[$http_port] = $webserver;
    }
    if ($https_port > 0) {
        $ports[$https_port] = $webserver;
    }
    
    // If new settings exist, use them
    if (!empty($ports)) {
        return $ports;
    }
    
    // Fallback to old combined setting
    return self::parseServicePorts(\Froxlor\Settings::Get('system.panel_service_ports') ?? '');
}

/**
 * Get customer service ports
 * 
 * @return array Array of port => service
 */
public static function getCustomerPorts(): array
{
    // New individual settings take priority
    $http_port = (int) \Froxlor\Settings::Get('system.customer_http_port') ?? 0;
    $https_port = (int) \Froxlor\Settings::Get('system.customer_https_port') ?? 0;
    $webserver = \Froxlor\Settings::Get('system.customer_webserver') ?? 'apache2';
    
    $ports = [];
    if ($http_port > 0) {
        $ports[$http_port] = $webserver;
    }
    if ($https_port > 0) {
        $ports[$https_port] = $webserver;
    }
    
    // If new settings exist, use them
    if (!empty($ports)) {
        return $ports;
    }
    
    // Fallback to old combined setting
    return self::parseServicePorts(\Froxlor\Settings::Get('system.customer_service_ports') ?? '');
}
    
    /**
     * Check if a port is a panel port
     * 
     * @param int $port
     * @return bool
     */
    public static function isPanelPort(int $port): bool
    {
        $panelPorts = self::getPanelPorts();
        return isset($panelPorts[$port]);
    }
    
    /**
     * Check if a port is a customer port
     * 
     * @param int $port
     * @return bool
     */
    public static function isCustomerPort(int $port): bool
    {
        $customerPorts = self::getCustomerPorts();
        return isset($customerPorts[$port]);
    }
    
    /**
     * Get the primary webserver for panel
     * 
     * @return string The webserver (nginx/apache) or empty string
     */
    public static function getPanelWebserver(): string
    {
        $ports = self::getPanelPorts();
        if (!empty($ports)) {
            return reset($ports);
        }
        return '';
    }
    
    /**
     * Get the primary webserver for customer web
     * 
     * @return string The webserver (nginx/apache) or empty string
     */
    public static function getCustomerWebserver(): string
    {
        $ports = self::getCustomerPorts();
        if (!empty($ports)) {
            return reset($ports);
        }
        return \Froxlor\Settings::Get('system.webserver') ?? '';
    }
    
    /**
     * Check if HTTP to HTTPS redirect is enabled for customers
     * 
     * @return bool
     */
    public static function customerHttpToHttpsRedirect(): bool
    {
        return \Froxlor\Settings::Get('system.customer_http_to_https_redirect') == '1';
    }
}