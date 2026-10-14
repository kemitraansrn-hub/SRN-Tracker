<?php

namespace App\Services;

use App\Models\GoogleCalendarToken;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integrasi Google Calendar pakai SATU akun Google bersama (kemitraan.srn@gmail.com),
 * di-connect sekali lewat OAuth oleh admin — bukan per-user. Sengaja panggil REST API
 * Google langsung lewat Http facade (bukan package google/apiclient) biar gak nambah
 * dependency composer baru, karena production di-deploy FTP-only tanpa composer install
 * (lihat catatan "Deploy gotcha" dompdf di memory — nambah vendor/ gede lewat FTP itu
 * rawan korup/gagal). Semua pemanggil (AssignmentController dkk) WAJIB bungkus pemanggilan
 * method create/update/delete Event di sini dengan try/catch sendiri — kegagalan sync ke
 * Calendar TIDAK BOLEH membatalkan penyimpanan data assignment, cuma dicatat jadi warning.
 */
class GoogleCalendarService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v2/userinfo';

    private const SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    public function isConfigured(): bool
    {
        return (bool) (config('services.google_calendar.client_id') && config('services.google_calendar.client_secret') && config('services.google_calendar.redirect_uri'));
    }

    public function isConnected(): bool
    {
        return GoogleCalendarToken::current() !== null;
    }

    public function authUrl(): string
    {
        $params = [
            'client_id' => config('services.google_calendar.client_id'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            // 'consent' dipaksa setiap kali biar Google SELALU ngasih refresh_token
            // baru (Google cuma ngasih refresh_token sekali di konsen pertama kalau
            // tidak dipaksa, connect ulang berikutnya diam-diam gak dapet refresh_token).
            'prompt' => 'consent',
        ];

        return self::AUTH_URL.'?'.http_build_query($params);
    }

    public function handleCallback(string $code, ?User $user): void
    {
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'redirect_uri' => config('services.google_calendar.redirect_uri'),
            'grant_type' => 'authorization_code',
            'code' => $code,
        ])->throw()->json();

        if (empty($response['refresh_token'])) {
            throw new \RuntimeException('Google tidak mengirim refresh_token. Coba disconnect akses app ini di myaccount.google.com/permissions lalu connect ulang.');
        }

        $email = null;
        try {
            $email = Http::withToken($response['access_token'])->get(self::USERINFO_URL)->json('email');
        } catch (\Throwable $e) {
            Log::warning('GoogleCalendarService: gagal ambil email akun saat connect', ['error' => $e->getMessage()]);
        }

        // Satu akun bersama — buang token lama kalau ada, simpan yang baru.
        GoogleCalendarToken::query()->delete();
        GoogleCalendarToken::create([
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'],
            'expires_at' => now()->addSeconds($response['expires_in'] ?? 3600),
            'google_email' => $email,
            'connected_by' => $user?->id,
        ]);
    }

    public function disconnect(): void
    {
        GoogleCalendarToken::query()->delete();
    }

    public function connectedEmail(): ?string
    {
        return GoogleCalendarToken::current()?->google_email;
    }

    /**
     * Bikin event baru. $attendeeEmails boleh kosong. Return Google event id,
     * atau null kalau akun belum pernah di-connect sama sekali (bukan error).
     */
    public function createEvent(string $title, string $description, Carbon $start, array $attendeeEmails = []): ?string
    {
        $token = $this->validAccessToken();
        if (! $token) {
            return null;
        }

        $payload = $this->eventPayload($title, $description, $start, $attendeeEmails);

        $response = Http::withToken($token)
            ->post(self::CALENDAR_API.'?sendUpdates=all', $payload)
            ->throw();

        return $response->json('id');
    }

    public function updateEvent(string $eventId, string $title, string $description, Carbon $start, array $attendeeEmails = []): bool
    {
        $token = $this->validAccessToken();
        if (! $token) {
            return false;
        }

        $payload = $this->eventPayload($title, $description, $start, $attendeeEmails);

        Http::withToken($token)
            ->patch(self::CALENDAR_API.'/'.$eventId.'?sendUpdates=all', $payload)
            ->throw();

        return true;
    }

    public function deleteEvent(string $eventId): void
    {
        $token = $this->validAccessToken();
        if (! $token) {
            return;
        }

        // 410/404 (event udah kehapus manual di Calendar) dianggap sukses, bukan error.
        $response = Http::withToken($token)->delete(self::CALENDAR_API.'/'.$eventId.'?sendUpdates=all');
        if (! $response->successful() && ! in_array($response->status(), [404, 410], true)) {
            $response->throw();
        }
    }

    private function eventPayload(string $title, string $description, Carbon $start, array $attendeeEmails): array
    {
        $end = $start->copy()->addHour();

        return [
            'summary' => $title,
            'description' => $description,
            'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => 'Asia/Jakarta'],
            'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => 'Asia/Jakarta'],
            'attendees' => collect($attendeeEmails)->filter()->unique()->map(fn ($email) => ['email' => $email])->values()->all(),
            'reminders' => ['useDefault' => true],
        ];
    }

    /** Access token yang masih valid, auto-refresh pakai refresh_token kalau sudah/hampir expired. Null kalau belum pernah connect. */
    private function validAccessToken(): ?string
    {
        $token = GoogleCalendarToken::current();
        if (! $token) {
            return null;
        }

        if ($token->expires_at->subMinute()->isFuture()) {
            return $token->access_token;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google_calendar.client_id'),
            'client_secret' => config('services.google_calendar.client_secret'),
            'grant_type' => 'refresh_token',
            'refresh_token' => $token->refresh_token,
        ])->throw()->json();

        $token->update([
            'access_token' => $response['access_token'],
            'expires_at' => now()->addSeconds($response['expires_in'] ?? 3600),
        ]);

        return $response['access_token'];
    }
}
