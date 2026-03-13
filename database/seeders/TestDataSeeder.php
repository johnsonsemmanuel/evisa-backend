<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\InternalNote;
use App\Models\Payment;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use App\Models\VisaType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $tourism = VisaType::where('slug', 'tourism')->first();
        $business = VisaType::where('slug', 'business')->first();

        if (!$tourism || !$business) {
            $this->command->error('Visa types not found. Run DatabaseSeeder first.');
            return;
        }

        $applicant = User::where('email', 'fatima@example.com')->first();
        $gisOfficer = User::where('email', 'kmensah@gis.gov.gh')->first();
        $mfaReviewer = User::where('email', 'aadjei@mfa.gov.gh')->first();

        if (!$applicant) {
            $this->command->error('Applicant user not found. Run DatabaseSeeder first.');
            return;
        }

        // Create additional test applicants
        $applicants = collect([$applicant]);

        $testApplicants = [
            ['first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@example.com'],
            ['first_name' => 'Marie', 'last_name' => 'Dubois', 'email' => 'marie.dubois@example.fr'],
            ['first_name' => 'Ahmed', 'last_name' => 'Hassan', 'email' => 'ahmed.hassan@example.eg'],
            ['first_name' => 'Yuki', 'last_name' => 'Tanaka', 'email' => 'yuki.tanaka@example.jp'],
            ['first_name' => 'Carlos', 'last_name' => 'Rodriguez', 'email' => 'carlos.rodriguez@example.mx'],
        ];

        foreach ($testApplicants as $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'password' => bcrypt('password'),
                    'role' => 'applicant',
                    'is_active' => true,
                    'locale' => 'en',
                ]
            );
            $applicants->push($user);
        }

        $this->command->info('Creating test applications...');

        // ══════════════════════════════════════════════════════════════════
        // 1. DRAFT Applications (not yet submitted)
        // ══════════════════════════════════════════════════════════════════
        $this->createApplication([
            'user' => $applicants[1],
            'visa_type' => $tourism,
            'status' => 'draft',
            'first_name' => 'John',
            'last_name' => 'Smith',
            'nationality' => 'USA',
            'passport_number' => 'US123456789',
            'duration_days' => 14,
            'purpose' => 'Visiting Cape Coast Castle and Kakum National Park',
        ]);

        // ══════════════════════════════════════════════════════════════════
        // 2. PENDING PAYMENT Applications
        // ══════════════════════════════════════════════════════════════════
        $this->createApplication([
            'user' => $applicants[2],
            'visa_type' => $business,
            'status' => 'pending_payment',
            'first_name' => 'Marie',
            'last_name' => 'Dubois',
            'nationality' => 'FRA',
            'passport_number' => 'FR987654321',
            'duration_days' => 7,
            'purpose' => 'Attending AfriTech Conference in Accra',
        ]);

        // ══════════════════════════════════════════════════════════════════
        // 3. SUBMITTED - Under Review by GIS (Tier 1 Tourism)
        // ══════════════════════════════════════════════════════════════════
        $app1 = $this->createApplication([
            'user' => $applicants[0], // Fatima
            'visa_type' => $business,
            'status' => 'under_review',
            'tier' => 'tier_1',
            'processing_tier' => 'fast_track',
            'assigned_agency' => 'gis',
            'first_name' => 'Fatima',
            'last_name' => 'Al-Hassan',
            'nationality' => 'ARE',
            'passport_number' => 'AE12345678',
            'duration_days' => 10,
            'purpose' => 'Meeting with potential business partners for import/export',
            'submitted_at' => now()->subHours(12),
            'sla_deadline' => now()->addHours(60),
        ]);
        $this->addPayment($app1, 'completed');
        $this->addDocuments($app1, ['passport_bio', 'photo', 'invitation_letter']);
        $this->addStatusHistory($app1, ['draft', 'pending_payment', 'submitted', 'under_review']);

        // ══════════════════════════════════════════════════════════════════
        // 4. SUBMITTED - Under Review by GIS (Tier 1 Tourism, assigned to officer)
        // ══════════════════════════════════════════════════════════════════
        $app2 = $this->createApplication([
            'user' => $applicants[3],
            'visa_type' => $tourism,
            'status' => 'under_review',
            'tier' => 'tier_1',
            'processing_tier' => 'fast_track',
            'assigned_agency' => 'gis',
            'assigned_officer_id' => $gisOfficer?->id,
            'first_name' => 'Ahmed',
            'last_name' => 'Hassan',
            'nationality' => 'EGY',
            'passport_number' => 'EG98765432',
            'duration_days' => 21,
            'purpose' => 'Tourism - visiting historical sites and beaches',
            'submitted_at' => now()->subHours(24),
            'sla_deadline' => now()->addHours(48),
        ]);
        $this->addPayment($app2, 'completed');
        $this->addDocuments($app2, ['passport_bio', 'photo']);
        $this->addStatusHistory($app2, ['draft', 'pending_payment', 'submitted', 'under_review']);
        $this->addNote($app2, $gisOfficer, 'Documents look good, verifying passport details.');

        // ══════════════════════════════════════════════════════════════════
        // 5. SUBMITTED - Under Review by GIS (Tier 2 - Extended Stay)
        // ══════════════════════════════════════════════════════════════════
        $app3 = $this->createApplication([
            'user' => $applicants[4],
            'visa_type' => $tourism,
            'status' => 'under_review',
            'tier' => 'tier_2',
            'processing_tier' => 'regular',
            'assigned_agency' => 'mfa',
            'first_name' => 'Yuki',
            'last_name' => 'Tanaka',
            'nationality' => 'JPN',
            'passport_number' => 'JP45678901',
            'duration_days' => 60,
            'purpose' => 'Extended cultural exchange and volunteer work',
            'submitted_at' => now()->subHours(36),
            'sla_deadline' => now()->addHours(84),
        ]);
        $this->addPayment($app3, 'completed');
        $this->addDocuments($app3, ['passport_bio', 'photo']);
        $this->addStatusHistory($app3, ['draft', 'pending_payment', 'submitted', 'under_review']);

        // ══════════════════════════════════════════════════════════════════
        // 6. ESCALATED to MFA (from GIS)
        // ══════════════════════════════════════════════════════════════════
        $app4 = $this->createApplication([
            'user' => $applicants[5],
            'visa_type' => $business,
            'status' => 'escalated',
            'tier' => 'tier_2',
            'processing_tier' => 'regular',
            'assigned_agency' => 'mfa',
            'first_name' => 'Carlos',
            'last_name' => 'Rodriguez',
            'nationality' => 'MEX',
            'passport_number' => 'MX11223344',
            'duration_days' => 45,
            'purpose' => 'Establishing mining equipment distribution partnership',
            'submitted_at' => now()->subDays(2),
            'sla_deadline' => now()->addHours(72),
        ]);
        $this->addPayment($app4, 'completed');
        $this->addDocuments($app4, ['passport_bio', 'photo', 'invitation_letter']);
        $this->addStatusHistory($app4, ['draft', 'pending_payment', 'submitted', 'under_review', 'escalated']);
        $this->addNote($app4, $gisOfficer, 'Escalated to MFA: Extended business stay requires additional verification of business credentials.');

        // ══════════════════════════════════════════════════════════════════
        // 7. Another ESCALATED to MFA (assigned to MFA reviewer)
        // ══════════════════════════════════════════════════════════════════
        $applicant6 = User::firstOrCreate(
            ['email' => 'sarah.johnson@example.uk'],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Johnson',
                'password' => bcrypt('password'),
                'role' => 'applicant',
                'is_active' => true,
                'locale' => 'en',
            ]
        );
        $app5 = $this->createApplication([
            'user' => $applicant6,
            'visa_type' => $business,
            'status' => 'escalated',
            'tier' => 'tier_2',
            'assigned_agency' => 'mfa',
            'assigned_officer_id' => $mfaReviewer?->id,
            'first_name' => 'Sarah',
            'last_name' => 'Johnson',
            'nationality' => 'GBR',
            'passport_number' => 'GB55667788',
            'duration_days' => 60,
            'purpose' => 'Setting up NGO operations for water sanitation project',
            'submitted_at' => now()->subDays(3),
            'sla_deadline' => now()->addHours(48),
        ]);
        $this->addPayment($app5, 'completed');
        $this->addDocuments($app5, ['passport_bio', 'photo', 'invitation_letter']);
        $this->addStatusHistory($app5, ['draft', 'pending_payment', 'submitted', 'under_review', 'escalated']);
        $this->addNote($app5, $gisOfficer, 'Escalated to MFA: NGO operations require ministry approval.');
        $this->addNote($app5, $mfaReviewer, 'Reviewing NGO registration documents.');

        // ══════════════════════════════════════════════════════════════════
        // 8. ADDITIONAL INFO REQUESTED
        // ══════════════════════════════════════════════════════════════════
        $applicant7 = User::firstOrCreate(
            ['email' => 'peter.mueller@example.de'],
            [
                'first_name' => 'Peter',
                'last_name' => 'Mueller',
                'password' => bcrypt('password'),
                'role' => 'applicant',
                'is_active' => true,
                'locale' => 'en',
            ]
        );
        $app6 = $this->createApplication([
            'user' => $applicant7,
            'visa_type' => $tourism,
            'status' => 'additional_info_requested',
            'tier' => 'tier_1',
            'assigned_agency' => 'gis',
            'assigned_officer_id' => $gisOfficer?->id,
            'first_name' => 'Peter',
            'last_name' => 'Mueller',
            'nationality' => 'DEU',
            'passport_number' => 'DE99887766',
            'duration_days' => 14,
            'purpose' => 'Safari and wildlife photography',
            'submitted_at' => now()->subDays(1),
            'sla_deadline' => now()->addHours(36),
        ]);
        $this->addPayment($app6, 'completed');
        $this->addDocuments($app6, ['passport_bio']); // Missing photo
        $this->addStatusHistory($app6, ['draft', 'pending_payment', 'submitted', 'under_review', 'additional_info_requested']);
        $this->addNote($app6, $gisOfficer, 'Passport photo is blurry. Please upload a clearer image.');

        // ══════════════════════════════════════════════════════════════════
        // 9. APPROVED Application
        // ══════════════════════════════════════════════════════════════════
        $applicant8 = User::firstOrCreate(
            ['email' => 'anna.kowalski@example.pl'],
            [
                'first_name' => 'Anna',
                'last_name' => 'Kowalski',
                'password' => bcrypt('password'),
                'role' => 'applicant',
                'is_active' => true,
                'locale' => 'en',
            ]
        );
        $app7 = $this->createApplication([
            'user' => $applicant8,
            'visa_type' => $tourism,
            'status' => 'approved',
            'tier' => 'tier_1',
            'assigned_agency' => 'gis',
            'assigned_officer_id' => $gisOfficer?->id,
            'first_name' => 'Anna',
            'last_name' => 'Kowalski',
            'nationality' => 'POL',
            'passport_number' => 'PL12349876',
            'duration_days' => 14,
            'purpose' => 'Beach vacation at Elmina',
            'submitted_at' => now()->subDays(2),
            'sla_deadline' => now()->subHours(24),
            'decided_at' => now()->subHours(12),
            'decision_notes' => 'All documents verified. Application approved.',
            'evisa_file_path' => 'evisas/GH-2024-000007.pdf',
        ]);
        $this->addPayment($app7, 'completed');
        $this->addDocuments($app7, ['passport_bio', 'photo'], 'verified');
        $this->addStatusHistory($app7, ['draft', 'pending_payment', 'submitted', 'under_review', 'approved']);

        // ══════════════════════════════════════════════════════════════════
        // 10. DENIED Application
        // ══════════════════════════════════════════════════════════════════
        $applicant9 = User::firstOrCreate(
            ['email' => 'ivan.petrov@example.ru'],
            [
                'first_name' => 'Ivan',
                'last_name' => 'Petrov',
                'password' => bcrypt('password'),
                'role' => 'applicant',
                'is_active' => true,
                'locale' => 'en',
            ]
        );
        $app8 = $this->createApplication([
            'user' => $applicant9,
            'visa_type' => $business,
            'status' => 'denied',
            'tier' => 'tier_2',
            'assigned_agency' => 'mfa',
            'assigned_officer_id' => $mfaReviewer?->id,
            'first_name' => 'Ivan',
            'last_name' => 'Petrov',
            'nationality' => 'RUS',
            'passport_number' => 'RU55443322',
            'duration_days' => 90,
            'purpose' => 'Business expansion and market research',
            'submitted_at' => now()->subDays(5),
            'sla_deadline' => now()->subDays(1),
            'decided_at' => now()->subDays(1),
            'decision_notes' => 'Unable to verify business credentials. Invitation letter appears fraudulent.',
        ]);
        $this->addPayment($app8, 'completed');
        $this->addDocuments($app8, ['passport_bio', 'photo', 'invitation_letter'], 'rejected');
        $this->addStatusHistory($app8, ['draft', 'pending_payment', 'submitted', 'under_review', 'escalated', 'denied']);

        // ══════════════════════════════════════════════════════════════════
        // 11-15. More GIS Queue Applications for realistic dashboard
        // ══════════════════════════════════════════════════════════════════
        $moreApplicants = [
            ['first_name' => 'Lisa', 'last_name' => 'Chen', 'email' => 'lisa.chen@example.cn', 'nationality' => 'CHN', 'passport' => 'CN11112222'],
            ['first_name' => 'Omar', 'last_name' => 'Ali', 'email' => 'omar.ali@example.sa', 'nationality' => 'SAU', 'passport' => 'SA33334444'],
            ['first_name' => 'Emma', 'last_name' => 'Wilson', 'email' => 'emma.wilson@example.au', 'nationality' => 'AUS', 'passport' => 'AU55556666'],
            ['first_name' => 'David', 'last_name' => 'Kim', 'email' => 'david.kim@example.kr', 'nationality' => 'KOR', 'passport' => 'KR77778888'],
            ['first_name' => 'Sofia', 'last_name' => 'Garcia', 'email' => 'sofia.garcia@example.es', 'nationality' => 'ESP', 'passport' => 'ES99990000'],
        ];

        foreach ($moreApplicants as $index => $data) {
            $user = User::firstOrCreate(
                ['email' => $data['email']],
                [
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'password' => bcrypt('password'),
                    'role' => 'applicant',
                    'is_active' => true,
                    'locale' => 'en',
                ]
            );

            $visaType = $index % 2 === 0 ? $tourism : $business;
            $duration = rand(7, 25);
            
            $app = $this->createApplication([
                'user' => $user,
                'visa_type' => $visaType,
                'status' => 'under_review',
                'tier' => 'tier_1',
                'assigned_agency' => 'gis',
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'nationality' => $data['nationality'],
                'passport_number' => $data['passport'],
                'duration_days' => $duration,
                'purpose' => $visaType->slug === 'tourism' ? 'Tourism and sightseeing' : 'Business meetings and conferences',
                'submitted_at' => now()->subHours(rand(6, 48)),
                'sla_deadline' => now()->addHours(rand(24, 66)),
            ]);
            $this->addPayment($app, 'completed');
            $this->addDocuments($app, $visaType->required_documents);
            $this->addStatusHistory($app, ['draft', 'pending_payment', 'submitted', 'under_review']);
        }

        $this->command->info('Test data created successfully!');
        $this->command->info('  - Multiple draft and pending payment applications');
        $this->command->info('  - 8+ applications in GIS queue (under_review)');
        $this->command->info('  - 2 applications escalated to MFA');
        $this->command->info('  - 1 approved application with eVisa');
        $this->command->info('  - 1 denied application');
        $this->command->info('  - 1 additional info requested');
    }

    private function createApplication(array $data): Application
    {
        return Application::create([
            'reference_number' => 'GH-' . date('Y') . '-' . str_pad(Application::count() + 1, 6, '0', STR_PAD_LEFT),
            'user_id' => $data['user']->id,
            'visa_type_id' => $data['visa_type']->id,
            'first_name_encrypted' => $data['first_name'],
            'last_name_encrypted' => $data['last_name'],
            'date_of_birth_encrypted' => fake()->date('Y-m-d', '-25 years'),
            'passport_number_encrypted' => $data['passport_number'],
            'nationality_encrypted' => $data['nationality'],
            'email_encrypted' => $data['user']->email,
            'phone_encrypted' => fake()->phoneNumber(),
            'intended_arrival' => now()->addDays(rand(14, 60))->format('Y-m-d'),
            'duration_days' => $data['duration_days'],
            'address_in_ghana' => fake()->address(),
            'purpose_of_visit' => $data['purpose'],
            'status' => $data['status'],
            'tier' => $data['tier'] ?? null,
            'processing_tier' => $data['processing_tier'] ?? null,
            'assigned_agency' => $data['assigned_agency'] ?? null,
            'risk_screening_status' => $data['risk_screening_status'] ?? 'pending',
            'assigned_officer_id' => $data['assigned_officer_id'] ?? null,
            'current_step' => $data['status'] === 'draft' ? 1 : 4,
            'submitted_at' => $data['submitted_at'] ?? null,
            'sla_deadline' => $data['sla_deadline'] ?? null,
            'decided_at' => $data['decided_at'] ?? null,
            'decision_notes' => $data['decision_notes'] ?? null,
            'evisa_file_path' => $data['evisa_file_path'] ?? null,
        ]);
    }

    private function addPayment(Application $application, string $status): void
    {
        Payment::create([
            'application_id' => $application->id,
            'user_id' => $application->user_id,
            'transaction_reference' => 'TXN_' . Str::random(16),
            'payment_provider' => 'paystack',
            'provider_reference' => 'PAY_' . Str::random(16),
            'amount' => $application->visaType->base_fee,
            'currency' => 'USD',
            'status' => $status,
            'paid_at' => $status === 'completed' ? now()->subHours(rand(1, 24)) : null,
        ]);
    }

    private function addDocuments(Application $application, array $types, string $status = 'pending'): void
    {
        foreach ($types as $type) {
            ApplicationDocument::create([
                'application_id' => $application->id,
                'document_type' => $type,
                'stored_path' => "documents/{$application->reference_number}/{$type}.pdf",
                'original_filename' => ucfirst(str_replace('_', ' ', $type)) . '.pdf',
                'mime_type' => 'application/pdf',
                'file_size' => rand(100000, 500000),
                'verification_status' => $status === 'verified' ? 'accepted' : $status,
                'ocr_status' => 'passed',
            ]);
        }
    }

    private function addStatusHistory(Application $application, array $statuses): void
    {
        $createdAt = $application->created_at ?? now()->subDays(7);
        
        for ($i = 0; $i < count($statuses); $i++) {
            $fromStatus = $i === 0 ? null : $statuses[$i - 1];
            $toStatus = $statuses[$i];
            
            ApplicationStatusHistory::create([
                'application_id' => $application->id,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'notes' => $this->getStatusNote($toStatus),
                'changed_by' => null,
                'created_at' => $createdAt->copy()->addHours($i * rand(1, 6)),
            ]);
        }
    }

    private function addNote(Application $application, ?User $user, string $content): void
    {
        if (!$user) return;
        
        InternalNote::create([
            'application_id' => $application->id,
            'user_id' => $user->id,
            'content' => $content,
            'is_private' => false,
        ]);
    }

    private function getStatusNote(string $status): string
    {
        return match ($status) {
            'draft' => 'Application created',
            'pending_payment' => 'Application ready for payment',
            'submitted' => 'Payment confirmed, application submitted',
            'under_review' => 'Application routed for review',
            'escalated' => 'Application escalated to MFA',
            'additional_info_requested' => 'Additional information requested from applicant',
            'approved' => 'Application approved',
            'denied' => 'Application denied',
            default => 'Status changed',
        };
    }
}
