<?php

namespace DietitianAssist\Middleware;

use PDO;

class AuditMiddleware {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function handle() {
        // Get current user ID from session or token
        $userId = $_SESSION['userId'] ?? null;
        
        // Get client IP address
        $ipAddress = $this->getClientIp();
        
        // Get user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

        // Set session variables for audit triggers
        $this->db->exec("SET @current_user_id = " . ($userId ? $userId : 'NULL'));
        $this->db->exec("SET @current_ip_address = " . $this->db->quote($ipAddress));
        $this->db->exec("SET @current_user_agent = " . $this->db->quote($userAgent));
    }

    private function getClientIp() {
        // Check for proxy headers first
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (isset($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
} 