<?php

namespace common\services;

use common\models\AppSettings;
use common\models\UserGoogleToken;
use Yii;

class GoogleCalendarService
{
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const REVOKE_URL   = 'https://oauth2.googleapis.com/revoke';
    private const CALENDAR_URL = 'https://www.googleapis.com/calendar/v3';
    private const SCOPES = [
        'https://www.googleapis.com/auth/calendar',
        'https://www.googleapis.com/auth/calendar.events',
        'https://www.googleapis.com/auth/userinfo.email',
        'openid',
    ];

    public function getRedirectUri(): string
    {
        $base = rtrim(AppSettings::get('app_url', 'http://localhost:4200'), '/');
        return $base . '/api/v1/google/callback';
    }

    public function isConfigured(): bool
    {
        return AppSettings::get('google_client_id') !== ''
            && AppSettings::get('google_client_secret') !== '';
    }

    public function getAuthUrl(int $userId): string
    {
        $clientId    = AppSettings::get('google_client_id');
        $redirectUri = $this->getRedirectUri();
        $state       = base64_encode($userId . ':' . bin2hex(random_bytes(8)));

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'             => $clientId,
            'redirect_uri'          => $redirectUri,
            'response_type'         => 'code',
            'scope'                 => implode(' ', self::SCOPES),
            'access_type'           => 'offline',
            'prompt'                => 'consent',
            'state'                 => $state,
            'include_granted_scopes'=> 'true',
        ]);
    }

    public function exchangeCode(string $code): array
    {
        return $this->post(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => AppSettings::get('google_client_id'),
            'client_secret' => AppSettings::get('google_client_secret'),
            'redirect_uri'  => $this->getRedirectUri(),
            'grant_type'    => 'authorization_code',
        ]);
    }

    public function refreshToken(UserGoogleToken $token): UserGoogleToken
    {
        if (!$token->isExpired()) {
            return $token;
        }

        $data = $this->post(self::TOKEN_URL, [
            'refresh_token' => $token->refresh_token,
            'client_id'     => AppSettings::get('google_client_id'),
            'client_secret' => AppSettings::get('google_client_secret'),
            'grant_type'    => 'refresh_token',
        ]);

        if (isset($data['access_token'])) {
            $token->access_token = $data['access_token'];
            $token->expires_at   = time() + (int)($data['expires_in'] ?? 3600);
            $token->updated_at   = time();
            $token->save(false);
        } else {
            $errorDesc = $data['error_description'] ?? $data['error'] ?? 'unknown';
            Yii::error('Google token refresh failed: ' . $errorDesc, 'google');
            throw new \RuntimeException('Google token refresh failed: ' . $errorDesc);
        }

        return $token;
    }

    public function revokeToken(string $accessToken): void
    {
        $this->post(self::REVOKE_URL, ['token' => $accessToken]);
    }

    public function getUserEmail(string $accessToken): string
    {
        $data = $this->get(
            'https://www.googleapis.com/oauth2/v3/userinfo',
            $accessToken
        );
        return $data['email'] ?? '';
    }

    /**
     * Create a Calendar event with Google Meet link.
     * Returns ['event_id' => ..., 'meet_link' => ..., 'html_link' => ...]
     */
    public function createEventWithMeet(
        UserGoogleToken $token,
        array $appointment,
        string $specialistEmail
    ): array {
        $token = $this->refreshToken($token);

        $startDateTime = $appointment['appointment_date'] . 'T' . $appointment['appointment_time'] . ':00';
        // Calculate end time = start + 1 hour
        $end = new \DateTime($startDateTime);
        $end->modify('+1 hour');
        $endDateTime = $end->format('Y-m-d\TH:i:s');

        $body = [
            'summary'     => 'Консультація з ' . $appointment['specialist_name'],
            'description' => 'Wellbeing — онлайн консультація.' .
                             "\nСпеціаліст: " . $appointment['specialist_name'] .
                             "\nФормат: " . ($appointment['book_via'] === 'phone' ? 'Телефон' : 'Google Meet'),
            'start'       => ['dateTime' => $startDateTime, 'timeZone' => 'Europe/Kiev'],
            'end'         => ['dateTime' => $endDateTime,   'timeZone' => 'Europe/Kiev'],
            'attendees'   => array_filter([
                ['email' => $token->google_email],
                $specialistEmail ? ['email' => $specialistEmail] : null,
            ]),
            'conferenceData' => [
                'createRequest' => [
                    'requestId'             => uniqid('wb_', true),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email',  'minutes' => 24 * 60],
                    ['method' => 'popup',  'minutes' => 30],
                ],
            ],
        ];

        $result = $this->post(
            self::CALENDAR_URL . '/calendars/primary/events?conferenceDataVersion=1&sendUpdates=all',
            $body,
            $token->access_token,
            true
        );

        $meetLink = '';
        foreach ($result['conferenceData']['entryPoints'] ?? [] as $ep) {
            if ($ep['entryPointType'] === 'video') {
                $meetLink = $ep['uri'];
                break;
            }
        }

        return [
            'event_id'  => $result['id'] ?? '',
            'meet_link' => $meetLink,
            'html_link' => $result['htmlLink'] ?? '',
        ];
    }

    public function deleteEvent(UserGoogleToken $token, string $eventId): void
    {
        $token = $this->refreshToken($token);
        $this->delete(
            self::CALENDAR_URL . '/calendars/primary/events/' . urlencode($eventId),
            $token->access_token
        );
    }

    /**
     * Fetch upcoming events from primary calendar (real-time, no cache).
     */
    public function getUpcomingEvents(UserGoogleToken $token, int $maxResults = 10): array
    {
        $token = $this->refreshToken($token);

        $now    = new \DateTime('now', new \DateTimeZone('Europe/Kiev'));
        $maxDay = (clone $now)->modify('+3 months');

        $params = http_build_query([
            'timeMin'      => $now->format(\DateTime::RFC3339),
            'timeMax'      => $maxDay->format(\DateTime::RFC3339),
            'maxResults'   => 50,
            'orderBy'      => 'startTime',
            'singleEvents' => 'true',
        ]);

        $data = $this->get(
            self::CALENDAR_URL . '/calendars/primary/events?' . $params,
            $token->access_token
        );

        return $data['items'] ?? [];
    }

    // ── HTTP helpers ────────────────────────────────────────────────────

    private function post(string $url, array $data, string $accessToken = '', bool $json = false): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $headers = ['Accept: application/json'];
        if ($accessToken) {
            $headers[] = 'Authorization: Bearer ' . $accessToken;
        }

        if ($json) {
            $headers[]  = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?? [];
    }

    private function get(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Accept: application/json',
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response ?: '{}', true) ?? [];
    }

    private function delete(string $url, string $accessToken): void
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}
