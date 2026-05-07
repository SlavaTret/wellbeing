<?php

namespace common\services;

use common\models\AppSettings;
use common\models\Appointment;
use common\models\User;
use Yii;

/**
 * Syncs Wellbeing users → Creatio Contacts
 * and Wellbeing appointments → Creatio Activities.
 *
 * All public methods silently swallow exceptions so they never break
 * the main request flow. Errors are written to Yii::warning('creatio').
 */
class CreatioSyncService
{
    /** @var array{token: string|null, expires_at: int} */
    private static array $tokenCache = ['token' => null, 'expires_at' => 0];

    // ──────────────────────────────────────────────────────────────────────────
    // Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Upsert a Wellbeing User as a Creatio Contact.
     * Safe to call from any controller — exceptions are caught internally.
     */
    /**
     * Upsert a Wellbeing User (client) as a Creatio Contact (type=Клієнт).
     * Should be called only from registration flow (UserController::actionRegister),
     * NOT from admin user creation.
     * Stores the Creatio Contact GUID back into user.creatio_contact_id for bidirectional sync.
     */
    public function syncUser(User $user): void
    {
        Yii::info('[Creatio] syncUser called for email=' . $user->email, 'creatio');
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $settings = $this->getSettings();
            $token    = $this->getToken($settings);

            // Prefer stored ID; fall back to email lookup.
            $contactId = $user->creatio_contact_id
                ?: $this->findContactByEmail($user->email, $settings['base_url'], $token);
            Yii::warning('[Creatio] contactId=' . ($contactId ?? 'null'), 'creatio');

            // Resolve company: use stored creatio_account_id (avoids name-lookup mismatches).
            $companyName = '';
            $accountId   = null;
            if ($user->company_id) {
                $company = \common\models\Company::findOne($user->company_id);
                if ($company) {
                    $companyName = $company->name ?? '';
                    // Use stored Creatio GUID — no OData name lookup needed.
                    $accountId   = $company->creatio_account_id ?: null;
                    Yii::warning('[Creatio] company="' . $companyName . '" accountId=' . ($accountId ?? 'null (not synced yet)'), 'creatio');
                }
            }

            $payload = [
                'GivenName'        => $user->first_name ?? '',
                'Surname'          => $user->last_name  ?? '',
                'Dear'             => $user->first_name ?? '',
                'Email'            => $user->email      ?? '',
                'MobilePhone'      => $user->phone      ?? '',
                'TypeId'           => '00783ef6-f36b-1410-a883-16d83cab0980', // Клієнт
                'LanguageId'       => 'e35e61ef-cc32-4a97-b98c-52473e35ce60', // Ukrainian
                'Confirmed'        => true,
                'WelCompanyName'   => $companyName,
                'WelAdditionalCode'=> $user->id ? 'PC' . $user->id : '',
            ];

            if ($accountId) {
                $payload['AccountId'] = $accountId;
            }

            if ($contactId) {
                Yii::warning('[Creatio] patching existing contact ' . $contactId, 'creatio');
                $this->patchContact($contactId, $payload, $settings['base_url'], $token);
            } else {
                Yii::warning('[Creatio] creating new contact for ' . $user->email, 'creatio');
                $result    = $this->postJsonReturn($settings['base_url'] . '/0/odata/Contact', $payload, $token, 'postContact');
                $contactId = $result['Id'] ?? null;
                Yii::warning('[Creatio] created contact id=' . ($contactId ?? 'null'), 'creatio');
            }

            // Persist Creatio GUID back to portal DB.
            if ($contactId && $user->creatio_contact_id !== $contactId) {
                \Yii::$app->db->createCommand()
                    ->update('{{%user}}', ['creatio_contact_id' => $contactId], ['id' => $user->id])
                    ->execute();
                $user->creatio_contact_id = $contactId;
                Yii::warning('[Creatio] saved creatio_contact_id=' . $contactId . ' for portal user_id=' . $user->id, 'creatio');
            }

            Yii::warning('[Creatio] syncUser completed OK', 'creatio');
        } catch (\Throwable $e) {
            Yii::warning('[Creatio] syncUser ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine(), 'creatio');
        }
    }

    /**
     * Upsert a Wellbeing Company as a Creatio Account.
     * Stores the Creatio Account GUID back into company.creatio_account_id for bidirectional sync.
     * Safe to call from any controller — exceptions are caught internally.
     */
    public function syncCompany(\common\models\Company $company): void
    {
        Yii::info('[Creatio] syncCompany called for name=' . $company->name, 'creatio');
        try {
            if (!$this->isEnabled()) {
                Yii::info('[Creatio] sync disabled, skipping syncCompany', 'creatio');
                return;
            }

            $settings = $this->getSettings();
            $token    = $this->getToken($settings);

            // Prefer stored Creatio ID over name lookup (faster, avoids naming collisions).
            $accountId = $company->creatio_account_id ?: $this->findAccountByName($company->name, $settings['base_url'], $token);

            $payload = [
                'Name'                        => $company->name,
                'TypeId'                      => '03a75490-53e6-df11-971b-001d60e938c6', // Клієнт
                'WelNumberOfFreeConsultation' => (int)($company->free_sessions_per_user ?? 0),
                'WelCurrencySymbolId'         => 'c1057119-53e6-df11-971b-001d60e938c6', // UAH ₴
                'WelTimeZoneId'               => '97c71d34-55d8-df11-9b2a-001d60e938c6', // Kyiv UTC+2
            ];
            if ($company->code !== null && $company->code !== '') {
                $payload['Code'] = (string)$company->code;
            }

            $isNew = false;
            if ($accountId) {
                Yii::warning('[Creatio] patching existing account ' . $accountId . ' for company=' . $company->name, 'creatio');
                $this->patchJson($settings['base_url'] . '/0/odata/Account(' . $accountId . ')', $payload, $token, 'patchAccount');
            } else {
                Yii::warning('[Creatio] creating new account for company=' . $company->name, 'creatio');
                $result    = $this->postJsonReturn($settings['base_url'] . '/0/odata/Account', $payload, $token, 'postAccount');
                $accountId = $result['Id'] ?? null;
                $isNew     = true;
                Yii::warning('[Creatio] created account id=' . ($accountId ?? 'null'), 'creatio');
            }

            // Persist Creatio GUID back to portal DB so future syncs use the stored ID.
            if ($accountId && $company->creatio_account_id !== $accountId) {
                \Yii::$app->db->createCommand()
                    ->update('company', ['creatio_account_id' => $accountId], ['id' => $company->id])
                    ->execute();
                $company->creatio_account_id = $accountId;
                Yii::warning('[Creatio] saved creatio_account_id=' . $accountId . ' for portal company_id=' . $company->id, 'creatio');
            }

            // Upload logo via SysImage → AccountLogoId (only on create, or if logo changed).
            if ($accountId && !empty($company->logo_url) && $isNew) {
                $this->uploadAccountLogo($accountId, $company->logo_url, $settings['base_url'], $token);
            }

            Yii::warning('[Creatio] syncCompany completed OK for ' . $company->name, 'creatio');
        } catch (\Throwable $e) {
            Yii::warning('[Creatio] syncCompany ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine(), 'creatio');
        }
    }

    /**
     * Upsert a Wellbeing Specialist as a Creatio Contact (type=Експерт).
     * Stores the Creatio Contact GUID back into specialist.creatio_contact_id for bidirectional sync.
     * Safe to call from any controller — exceptions are caught internally.
     */
    public function syncSpecialist(\common\models\Specialist $specialist): void
    {
        Yii::info('[Creatio] syncSpecialist called for name=' . $specialist->name, 'creatio');
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $settings = $this->getSettings();
            $token    = $this->getToken($settings);

            // Parse first/last name from full name
            $parts     = preg_split('/\s+/u', trim($specialist->name), 2);
            $firstName = $parts[0] ?? $specialist->name;
            $lastName  = $parts[1] ?? '';

            // Prefer stored Creatio ID; fall back to email lookup.
            $contactId = $specialist->creatio_contact_id;
            if (!$contactId && $specialist->email) {
                $contactId = $this->findContactByEmail($specialist->email, $settings['base_url'], $token);
            }

            $payload = [
                'GivenName' => $firstName,
                'Surname'   => $lastName,
                'Dear'      => $firstName,
                'Email'     => $specialist->email ?? '',
                'TypeId'    => '479beccb-5438-4730-ae62-f44dd65b68b9', // Експерт
                'LanguageId'=> 'e35e61ef-cc32-4a97-b98c-52473e35ce60', // Ukrainian
                'Confirmed' => true,
                'WelAdditionalCode' => 'PEX' . $specialist->id,
            ];

            if ($contactId) {
                Yii::warning('[Creatio] patching expert contact ' . $contactId . ' for specialist=' . $specialist->name, 'creatio');
                $this->patchJson($settings['base_url'] . '/0/odata/Contact(' . $contactId . ')', $payload, $token, 'patchExpert');
            } else {
                Yii::warning('[Creatio] creating expert contact for specialist=' . $specialist->name, 'creatio');
                $result    = $this->postJsonReturn($settings['base_url'] . '/0/odata/Contact', $payload, $token, 'postExpert');
                $contactId = $result['Id'] ?? null;
                Yii::warning('[Creatio] created expert contact id=' . ($contactId ?? 'null'), 'creatio');

                // Set self-reference WelCurrentExpertId after creation (Creatio expert record pattern).
                if ($contactId) {
                    $this->patchJson(
                        $settings['base_url'] . '/0/odata/Contact(' . $contactId . ')',
                        ['WelCurrentExpertId' => $contactId],
                        $token,
                        'patchExpertSelfRef'
                    );
                }
            }

            // Persist Creatio GUID back to portal DB.
            if ($contactId && $specialist->creatio_contact_id !== $contactId) {
                \Yii::$app->db->createCommand()
                    ->update('specialist', ['creatio_contact_id' => $contactId], ['id' => $specialist->id])
                    ->execute();
                $specialist->creatio_contact_id = $contactId;
                Yii::warning('[Creatio] saved creatio_contact_id=' . $contactId . ' for portal specialist_id=' . $specialist->id, 'creatio');
            }

            Yii::warning('[Creatio] syncSpecialist completed OK for ' . $specialist->name, 'creatio');
        } catch (\Throwable $e) {
            Yii::warning('[Creatio] syncSpecialist ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine(), 'creatio');
        }
    }

    /**
     * Create a Creatio Activity (consultation) for a Wellbeing appointment.
     * Uses stored Creatio IDs from user, company and specialist — no OData name/email lookups.
     * Stores the Creatio Activity GUID back into appointment.creatio_activity_id for bidirectional sync.
     * Safe to call from any controller — exceptions are caught internally.
     */
    public function syncAppointment(Appointment $appointment, User $user): void
    {
        Yii::info('[Creatio] syncAppointment id=' . $appointment->id . ' user=' . $user->email, 'creatio');
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $settings = $this->getSettings();
            $token    = $this->getToken($settings);

            // Resolve Creatio GUIDs from portal stored IDs (no network lookup needed)
            $clientContactId  = $user->creatio_contact_id ?: null;
            $clientAccountId  = null;
            $companyName      = '';
            if ($user->company_id) {
                $company     = \common\models\Company::findOne($user->company_id);
                $companyName = $company->name ?? '';
                $clientAccountId = $company->creatio_account_id ?: null;
            }

            $expertContactId = null;
            if ($appointment->specialist_id) {
                $specialist = \common\models\Specialist::findOne($appointment->specialist_id);
                $expertContactId = $specialist->creatio_contact_id ?? null;
            }

            $startIso = $this->buildIso($appointment->appointment_date, $appointment->appointment_time);
            $dueIso   = $this->buildIso($appointment->appointment_date, $appointment->appointment_time, 3600);

            // Communication method mapping → Creatio WelCommunicationMethod lookup GUIDs
            $commMethodMap = [
                'google_meet' => 'efe5d7a2-5f38-e111-851e-00155d04c01d',
                'zoom'        => '6e3d1f7d-f2aa-4d10-a44f-ebd63b2b2dfc',
                'teams'       => '0294562f-80f1-4ae6-b88c-70991d620514',
            ];
            $commMethodLabels = [
                'google_meet' => ' в Google Meet',
                'zoom'        => ' в Zoom',
                'teams'       => ' в MS Teams',
            ];
            $commMethod      = $appointment->communication_method ?? '';
            $commMethodGuid  = $commMethodMap[$commMethod]   ?? null;
            $commMethodSuffix= $commMethodLabels[$commMethod] ?? '';

            // Title format mirrors Creatio pattern: "Firstname PC{id} (CompanyName) в Google Meet"
            $clientFirstName = $user->first_name ?? '';
            $clientCode      = 'PC' . $user->id;
            $titleParts      = array_filter([$clientFirstName, $clientCode, $companyName ? "({$companyName})" : '']);
            $baseTitle       = implode(' ', $titleParts) ?: ('Wellbeing консультація #' . $appointment->id);
            $title           = $baseTitle . $commMethodSuffix;

            $isPaid = in_array($appointment->payment_status, ['paid', 'subscription'], true);

            $activity = [
                'Title'                      => $title,
                'TypeId'                     => 'fbe0acdc-cfc0-df11-b00f-001d60e938c6', // Консультація
                'ActivityCategoryId'         => '6e7fa16e-7d4f-4a68-9ff7-b6086f7a4375',
                'StatusId'                   => '384d4b84-58e6-df11-971b-001d60e938c6',
                'StartDate'                  => $startIso,
                'DueDate'                    => $dueIso,
                'WelClientTime'              => $startIso,
                'DurationInMinutes'          => 60,
                'WelStatusId'                => '044dbfd2-b27d-4f50-ad93-19b4f486de85',
                'WelConsultationServiceId'   => 'bb17fc22-3ac5-408e-a46a-99a133bf992b', // Консультація індивідуальна
                'WelIsConsultation'          => true,
                'WelIsPaid'                  => $isPaid,
                'WelAmount'                  => (float)($appointment->price ?? 0),
                'WelCurrencySymbolId'        => 'c1057119-53e6-df11-971b-001d60e938c6', // UAH ₴
                'WelTimeZoneId'              => '97c71d34-55d8-df11-9b2a-001d60e938c6', // Kyiv UTC+2
                'WelContactAdditionalCode'   => $clientCode,
                'WelCode'                    => (string)$appointment->id,
            ];

            if ($commMethodGuid) { $activity['WelCommunicationMethodId'] = $commMethodGuid; }

            if ($clientContactId)  { $activity['ContactId']   = $clientContactId;  }
            if ($clientAccountId)  { $activity['AccountId']   = $clientAccountId;  }
            if ($expertContactId)  {
                $activity['WelExpertId'] = $expertContactId;
                $activity['OwnerId']     = $expertContactId;
            }

            $result     = $this->postJsonReturn($settings['base_url'] . '/0/odata/Activity', $activity, $token, 'postActivity');
            $activityId = $result['Id'] ?? null;
            Yii::warning('[Creatio] created activity id=' . ($activityId ?? 'null'), 'creatio');

            if ($activityId) {
                \Yii::$app->db->createCommand()
                    ->update('appointment', ['creatio_activity_id' => $activityId], ['id' => $appointment->id])
                    ->execute();
                $appointment->creatio_activity_id = $activityId;
                Yii::warning('[Creatio] saved creatio_activity_id=' . $activityId . ' for portal appointment_id=' . $appointment->id, 'creatio');
            }

            Yii::warning('[Creatio] syncAppointment completed OK', 'creatio');
        } catch (\Throwable $e) {
            Yii::warning('[Creatio] syncAppointment ERROR: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine(), 'creatio');
        }
    }

    /**
     * Patch WelIsPaid=true on the linked Creatio Activity after payment is confirmed.
     * Safe to call from PaymentService — exceptions are caught internally.
     */
    public function markAppointmentPaid(Appointment $appointment): void
    {
        Yii::info('[Creatio] markAppointmentPaid appointment_id=' . $appointment->id, 'creatio');
        try {
            if (!$this->isEnabled()) {
                return;
            }

            $activityId = $appointment->creatio_activity_id;
            if (!$activityId) {
                Yii::warning('[Creatio] markAppointmentPaid: no creatio_activity_id for appointment_id=' . $appointment->id, 'creatio');
                return;
            }

            $settings = $this->getSettings();
            $token    = $this->getToken($settings);

            $this->patchJson(
                $settings['base_url'] . '/0/odata/Activity(' . $activityId . ')',
                ['WelIsPaid' => true],
                $token,
                'patchActivityPaid'
            );
            Yii::warning('[Creatio] markAppointmentPaid OK activity_id=' . $activityId, 'creatio');
        } catch (\Throwable $e) {
            Yii::warning('[Creatio] markAppointmentPaid ERROR: ' . $e->getMessage(), 'creatio');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function isEnabled(): bool
    {
        return AppSettings::get('creatio_enabled', '0') === '1';
    }

    private function getSettings(): array
    {
        $raw = AppSettings::getAll([
            'creatio_base_url',
            'creatio_identity_url',
            'creatio_client_id',
            'creatio_client_secret',
            'creatio_enabled',
        ]);

        return [
            'base_url'      => rtrim($raw['creatio_base_url']      ?? '', '/'),
            'identity_url'  => rtrim($raw['creatio_identity_url']  ?? '', '/'),
            'client_id'     => $raw['creatio_client_id']           ?? '',
            'client_secret' => $raw['creatio_client_secret']       ?? '',
        ];
    }

    /**
     * Obtain a Bearer token, re-using the cached one while it is still valid.
     *
     * @throws \RuntimeException when the token endpoint returns an error.
     */
    private function getToken(array $settings): string
    {
        if (
            self::$tokenCache['token'] !== null &&
            time() < self::$tokenCache['expires_at']
        ) {
            return self::$tokenCache['token'];
        }

        // Creatio cloud uses a separate Identity Service on {name}-is.creatio.com
        // Derive it from base_url or use explicit override from settings.
        $isUrl = $settings['identity_url'] ?: $this->deriveIdentityUrl($settings['base_url']);
        $url   = $isUrl . '/connect/token';
        Yii::warning('[Creatio] requesting token from: ' . $url, 'creatio');

        // Attempt 1: credentials in body only (standard RFC 6749 client_credentials)
        $body = http_build_query([
            'grant_type'    => 'client_credentials',
            'client_id'     => $settings['client_id'],
            'client_secret' => $settings['client_secret'],
        ]);

        $raw      = $this->curlPost($url, $body, ['Content-Type: application/x-www-form-urlencoded']);
        $httpCode = $raw['code'];
        $response = $raw['body'];

        Yii::warning("[Creatio] token attempt1 HTTP {$httpCode}: " . substr($response, 0, 300), 'creatio');

        if ($httpCode !== 200) {
            // Attempt 2: credentials in Authorization: Basic header only
            $basicAuth = base64_encode($settings['client_id'] . ':' . $settings['client_secret']);
            $body2     = http_build_query(['grant_type' => 'client_credentials']);
            $raw2      = $this->curlPost($url, $body2, [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $basicAuth,
            ]);
            $httpCode = $raw2['code'];
            $response = $raw2['body'];
            Yii::warning("[Creatio] token attempt2 HTTP {$httpCode}: " . substr($response, 0, 300), 'creatio');
        }

        if ($httpCode !== 200) {
            throw new \RuntimeException("Creatio token failed HTTP {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            throw new \RuntimeException('Creatio token missing access_token: ' . $response);
        }

        $expiresIn = (int)($data['expires_in'] ?? 3600);
        self::$tokenCache = [
            'token'      => $data['access_token'],
            'expires_at' => time() + $expiresIn - 60,
        ];
        Yii::warning('[Creatio] token obtained OK', 'creatio');
        return self::$tokenCache['token'];
    }

    /**
     * Find a Creatio Contact by email address.
     *
     * @return string|null  Creatio Contact GUID or null when not found.
     */
    private function findContactByEmail(string $email, string $baseUrl, string $token): ?string
    {
        $filter = "Email eq '" . str_replace("'", "''", $email) . "'";
        $url    = $baseUrl . '/0/odata/Contact?' . http_build_query(['$filter' => $filter, '$select' => 'Id,Email']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException('Creatio findContact cURL error: ' . $curlErr);
        }
        if ($httpCode !== 200) {
            throw new \RuntimeException("Creatio findContact returned HTTP {$httpCode}: {$raw}");
        }

        $data = json_decode($raw, true);
        $value = $data['value'] ?? [];

        if (!empty($value[0]['Id'])) {
            return $value[0]['Id'];
        }

        return null;
    }

    /**
     * Find a Creatio Account (company) by name.
     *
     * @return string|null  Creatio Account GUID or null when not found.
     */
    private function findAccountByName(string $name, string $baseUrl, string $token): ?string
    {
        if ($name === '') {
            return null;
        }
        $filter = "Name eq '" . str_replace("'", "''", $name) . "'";
        $url    = $baseUrl . '/0/odata/Account?' . http_build_query(['$filter' => $filter, '$select' => 'Id,Name']);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($raw, true);
        return $data['value'][0]['Id'] ?? null;
    }

    private function postContact(array $payload, string $baseUrl, string $token): void
    {
        $url = $baseUrl . '/0/odata/Contact';
        $this->postJson($url, $payload, $token, 201, 'postContact');
    }

    private function patchContact(string $guid, array $payload, string $baseUrl, string $token): void
    {
        $url = $baseUrl . '/0/odata/Contact(' . $guid . ')';
        $this->patchJson($url, $payload, $token, 'patchContact');
    }

    private function postActivity(array $payload, string $baseUrl, string $token): void
    {
        $url = $baseUrl . '/0/odata/Activity';
        $this->postJson($url, $payload, $token, 201, 'postActivity');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Low-level HTTP helpers
    // ──────────────────────────────────────────────────────────────────────────

    /** @return array{code:int, body:string} */
    private function curlPost(string $url, string $body, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $raw     = (string)curl_exec($ch);
        $code    = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);
        if ($curlErr) {
            throw new \RuntimeException('cURL error: ' . $curlErr);
        }
        return ['code' => $code, 'body' => $raw];
    }

    private function postJson(string $url, array $payload, string $token, int $expectedCode, string $context): void
    {
        $this->postJsonReturn($url, $payload, $token, $context, $expectedCode);
    }

    /** Like postJson but returns the decoded response body. */
    private function postJsonReturn(string $url, array $payload, string $token, string $context, int $expectedCode = 201): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("Creatio {$context} cURL error: {$curlErr}");
        }
        // Accept both 200 and 201 as success (Creatio may return either).
        if ($httpCode !== $expectedCode && $httpCode !== 200) {
            throw new \RuntimeException("Creatio {$context} returned HTTP {$httpCode}: {$raw}");
        }

        return json_decode($raw, true) ?: [];
    }

    /**
     * Upload a logo image to a Creatio Account using the SysImage entity.
     *
     * Flow: POST SysImage (metadata) → PUT SysImage(id)/Data (binary) → PATCH Account AccountLogoId.
     * This is how the Creatio UI itself uploads account logos.
     */
    private function uploadAccountLogo(string $accountId, string $logoUrl, string $baseUrl, string $token): void
    {
        // 1. Download the image from the portal
        $imgCh = curl_init($logoUrl);
        curl_setopt_array($imgCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $imgData = curl_exec($imgCh);
        $imgCode = curl_getinfo($imgCh, CURLINFO_HTTP_CODE);
        $imgErr  = curl_error($imgCh);
        curl_close($imgCh);

        if ($imgErr || $imgCode !== 200 || !$imgData) {
            Yii::warning('[Creatio] logo download failed: code=' . $imgCode . ' err=' . $imgErr . ' url=' . $logoUrl, 'creatio');
            return;
        }

        // 2. Determine MIME type from URL extension
        $ext      = strtolower(pathinfo(parse_url($logoUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        $mimeMap  = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                     'gif' => 'image/gif',  'webp' => 'image/webp', 'svg' => 'image/svg+xml'];
        $mimeType = $mimeMap[$ext] ?? 'image/png';
        $fileName = 'logo-' . $accountId . '.' . ($ext ?: 'png');

        // 3. Create SysImage metadata record
        $sysImgPayload = [
            'Name'     => $fileName,
            'MimeType' => $mimeType,
        ];
        $sysImgResult = $this->postJsonReturn($baseUrl . '/0/odata/SysImage', $sysImgPayload, $token, 'postSysImage');
        $sysImageId   = $sysImgResult['Id'] ?? null;
        if (!$sysImageId) {
            Yii::warning('[Creatio] logo: SysImage created but no Id returned', 'creatio');
            return;
        }
        Yii::warning('[Creatio] logo: SysImage created id=' . $sysImageId, 'creatio');

        // 4. Upload binary to SysImage(id)/Data
        $dataCh = curl_init($baseUrl . '/0/odata/SysImage(' . $sysImageId . ')/Data');
        curl_setopt_array($dataCh, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $imgData,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/octet-stream',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);
        $dataRaw  = curl_exec($dataCh);
        $dataCode = curl_getinfo($dataCh, CURLINFO_HTTP_CODE);
        $dataErr  = curl_error($dataCh);
        curl_close($dataCh);

        if ($dataErr || $dataCode >= 400) {
            Yii::warning('[Creatio] logo: SysImage Data upload failed HTTP=' . $dataCode . ' err=' . $dataErr . ' body=' . substr($dataRaw, 0, 200), 'creatio');
            return;
        }
        Yii::warning('[Creatio] logo: SysImage Data uploaded OK HTTP=' . $dataCode, 'creatio');

        // 5. Link SysImage to Account via AccountLogoId
        $this->patchJson(
            $baseUrl . '/0/odata/Account(' . $accountId . ')',
            ['AccountLogoId' => $sysImageId],
            $token,
            'patchAccountLogo'
        );
        Yii::warning('[Creatio] logo: AccountLogoId set to ' . $sysImageId . ' for account=' . $accountId, 'creatio');
    }

    private function patchJson(string $url, array $payload, string $token, string $context): void
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; odata.metadata=minimal',
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 10,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("Creatio {$context} cURL error: {$curlErr}");
        }
        // PATCH success: 200 or 204
        if ($httpCode !== 200 && $httpCode !== 204) {
            throw new \RuntimeException("Creatio {$context} returned HTTP {$httpCode}: {$raw}");
        }
    }

    /**
     * Derive the Creatio Identity Service URL from the main base URL.
     * https://mysite.creatio.com → https://mysite-is.creatio.com
     */
    private function deriveIdentityUrl(string $baseUrl): string
    {
        $parsed = parse_url($baseUrl);
        $host   = $parsed['host'] ?? '';
        $dotPos = strpos($host, '.');
        if ($dotPos !== false) {
            $host = substr($host, 0, $dotPos) . '-is' . substr($host, $dotPos);
        }
        return ($parsed['scheme'] ?? 'https') . '://' . $host;
    }

    /**
     * Build an ISO-8601 UTC datetime string from a date string, a time string,
     * and an optional offset in seconds.
     */
    private function buildIso(string $date, ?string $time, int $offsetSeconds = 0): string
    {
        // appointment_time is stored as HH:MM — add seconds if missing
        $timeStr = $time ?: '00:00';
        if (strlen($timeStr) === 5) {
            $timeStr .= ':00';
        }
        $datetime = \DateTime::createFromFormat('Y-m-d H:i:s', $date . ' ' . $timeStr, new \DateTimeZone('UTC'));

        if (!$datetime) {
            $datetime = new \DateTime('now', new \DateTimeZone('UTC'));
        }

        if ($offsetSeconds) {
            $datetime->modify("+{$offsetSeconds} seconds");
        }

        return $datetime->format('Y-m-d\TH:i:s\Z');
    }
}
