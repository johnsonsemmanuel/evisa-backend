<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoutingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // Emergency processing - highest priority
            [
                'name' => 'Emergency Visa Processing',
                'description' => 'Emergency visas routed to GIS with rapid override',
                'rule_type' => 'custom',
                'priority' => 1,
                'route_to' => 'gis_hq',
                'sla_hours' => 12,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'conditions' => json_encode([
                    'service_type' => 'emergency',
                ]),
            ],

            // Diplomatic visas
            [
                'name' => 'Diplomatic Visa Processing',
                'description' => 'Diplomatic and official visas to MFA Diplomatic Desk',
                'rule_type' => 'diplomatic',
                'priority' => 10,
                'route_to' => 'mfa_hq',
                'sla_hours' => 72,
                'requires_interview' => false,
                'requires_security_clearance' => true,
                'conditions' => json_encode([
                    'is_diplomatic' => true,
                ]),
            ],

            // Transit visas
            [
                'name' => 'Transit Visa Processing',
                'description' => 'Transit visas to GIS Border Control Unit',
                'rule_type' => 'visa_type',
                'priority' => 20,
                'route_to' => 'gis_hq',
                'sla_hours' => 24,
                'requires_interview' => false,
                'requires_security_clearance' => false,
            ],

            // Express processing
            [
                'name' => 'Express Processing - GIS HQ',
                'description' => 'Express service tier routed to GIS HQ',
                'rule_type' => 'custom',
                'priority' => 30,
                'route_to' => 'gis_hq',
                'sla_hours' => 48,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'conditions' => json_encode([
                    'service_type' => 'express',
                ]),
            ],

            // Premium processing
            [
                'name' => 'Premium Processing - GIS Fast Track',
                'description' => 'Premium service tier to GIS Fast Track Unit',
                'rule_type' => 'custom',
                'priority' => 31,
                'route_to' => 'gis_hq',
                'sla_hours' => 24,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'conditions' => json_encode([
                    'service_type' => 'premium',
                ]),
            ],

            // High-risk escalation
            [
                'name' => 'High Risk - GIS Intelligence Desk',
                'description' => 'High risk applications escalated to GIS Intelligence',
                'rule_type' => 'risk_level',
                'priority' => 40,
                'route_to' => 'gis_hq',
                'sla_hours' => 168,
                'requires_interview' => true,
                'requires_security_clearance' => true,
                'conditions' => json_encode([
                    'risk_level' => ['high', 'critical'],
                ]),
            ],

            // Medical emergency
            [
                'name' => 'Medical Emergency - Express Processing',
                'description' => 'Medical purpose visas get express processing',
                'rule_type' => 'purpose',
                'priority' => 50,
                'route_to' => 'gis_hq',
                'sla_hours' => 24,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'conditions' => json_encode([
                    'purpose' => ['medical', 'medical_treatment'],
                ]),
            ],

            // Conference visas (premium)
            [
                'name' => 'Conference Visa - Premium Processing',
                'description' => 'Conference attendees with premium service',
                'rule_type' => 'purpose',
                'priority' => 51,
                'route_to' => 'gis_hq',
                'sla_hours' => 24,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'conditions' => json_encode([
                    'purpose' => ['conference', 'seminar', 'workshop'],
                    'service_type' => 'premium',
                ]),
            ],

            // Nationality-based routing for specific countries
            [
                'name' => 'USA Tourist - Washington Mission',
                'description' => 'US tourist applicants routed to Washington Embassy',
                'rule_type' => 'nationality',
                'priority' => 60,
                'route_to' => 'mfa_mission',
                'sla_hours' => 72,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'nationalities' => json_encode(['US']),
                'mfa_mission_id' => 1, // Washington DC
                'conditions' => json_encode([
                    'visa_type' => ['tourist', 'business'],
                    'service_type' => 'standard',
                ]),
            ],

            [
                'name' => 'UK Tourist - London Mission',
                'description' => 'UK tourist applicants routed to London High Commission',
                'rule_type' => 'nationality',
                'priority' => 61,
                'route_to' => 'mfa_mission',
                'sla_hours' => 72,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'nationalities' => json_encode(['GB']),
                'mfa_mission_id' => 3, // London
                'conditions' => json_encode([
                    'visa_type' => ['tourist', 'business'],
                    'service_type' => 'standard',
                ]),
            ],

            [
                'name' => 'Germany Tourist - Berlin Mission',
                'description' => 'German tourist applicants routed to Berlin Embassy',
                'rule_type' => 'nationality',
                'priority' => 62,
                'route_to' => 'mfa_mission',
                'sla_hours' => 72,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'nationalities' => json_encode(['DE']),
                'mfa_mission_id' => 4, // Berlin
                'conditions' => json_encode([
                    'visa_type' => ['tourist'],
                    'service_type' => 'standard',
                ]),
            ],

            [
                'name' => 'India Student - New Delhi Mission',
                'description' => 'Indian student applicants routed to New Delhi High Commission',
                'rule_type' => 'nationality',
                'priority' => 63,
                'route_to' => 'mfa_mission',
                'sla_hours' => 168,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'nationalities' => json_encode(['IN']),
                'mfa_mission_id' => 6, // New Delhi
                'conditions' => json_encode([
                    'visa_type' => ['student'],
                    'service_type' => 'standard',
                ]),
            ],

            [
                'name' => 'Nigeria Work - Abuja Mission',
                'description' => 'Nigerian work applicants routed to Abuja High Commission',
                'rule_type' => 'nationality',
                'priority' => 64,
                'route_to' => 'mfa_mission',
                'sla_hours' => 168,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'nationalities' => json_encode(['NG']),
                'mfa_mission_id' => 9, // Abuja
                'conditions' => json_encode([
                    'visa_type' => ['work'],
                    'service_type' => 'standard',
                ]),
            ],

            // Default standard processing
            [
                'name' => 'Standard Processing - MFA Mission',
                'description' => 'Standard service tier to appropriate MFA mission',
                'rule_type' => 'custom',
                'priority' => 100,
                'route_to' => 'mfa_mission',
                'sla_hours' => 120,
                'requires_interview' => false,
                'requires_security_clearance' => false,
                'conditions' => json_encode([
                    'service_type' => 'standard',
                ]),
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('routing_rules')->insert(array_merge($rule, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
