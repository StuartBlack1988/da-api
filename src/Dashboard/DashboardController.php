<?php

namespace DietitianAssist\Dashboard;

use PDO;
use PDOException;

class DashboardController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getClientDashboardMetrics($userId) {
        try {
            // Get the count of patients linked to this client
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(DISTINCT u.userId) as patientCount,
                    SUM(
                        CASE 
                            WHEN ud.hasMedicalAid = 1 THEN 
                                (SELECT COUNT(*) FROM `Dependent` d 
                                 WHERE d.userId = u.userId AND d.isActive = 1)
                            ELSE 0 
                        END
                    ) as dependentCount
                FROM `User` u
                LEFT JOIN `UserDetails` ud ON u.userId = ud.userId
                WHERE u.clientId = ? AND u.role = 'patient' AND u.isActive = 1
            ");
            
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'patientCount' => (int)$result['patientCount'],
                'dependentCount' => (int)$result['dependentCount'],
                'totalCount' => (int)$result['patientCount'] + (int)$result['dependentCount']
            ];
        } catch (PDOException $e) {
            error_log("Error getting client dashboard metrics: " . $e->getMessage());
            return ['error' => 'Failed to get dashboard metrics'];
        }
    }
} 