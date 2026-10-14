<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GoogleCalendarSettingController extends Controller
{
    public function __construct(private readonly GoogleCalendarService $calendar) {}

    public function edit(): View
    {
        return view('pengaturan.google-calendar', [
            'isConfigured' => $this->calendar->isConfigured(),
            'isConnected' => $this->calendar->isConnected(),
            'connectedEmail' => $this->calendar->connectedEmail(),
        ]);
    }

    public function connect(): RedirectResponse
    {
        if (! $this->calendar->isConfigured()) {
            return redirect()->route('google-calendar.edit')
                ->with('status', 'Client ID/Client Secret Google belum diisi di .env. Minta tim developer isi dulu.');
        }

        return redirect()->away($this->calendar->authUrl());
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            return redirect()->route('google-calendar.edit')->with('status', 'Connect dibatalkan: '.$request->input('error'));
        }

        $code = $request->input('code');
        if (! $code) {
            return redirect()->route('google-calendar.edit')->with('status', 'Gagal connect: Google tidak mengirim kode otorisasi.');
        }

        try {
            $this->calendar->handleCallback($code, $request->user());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('google-calendar.edit')->with('status', 'Gagal connect ke Google Calendar: '.$e->getMessage());
        }

        return redirect()->route('google-calendar.edit')->with('status', 'Google Calendar berhasil di-connect ('.$this->calendar->connectedEmail().').');
    }

    public function disconnect(): RedirectResponse
    {
        $this->calendar->disconnect();

        return redirect()->route('google-calendar.edit')->with('status', 'Google Calendar berhasil di-disconnect. Jadwal Zoom baru tidak akan tersinkron sampai di-connect lagi.');
    }
}
