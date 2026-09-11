@extends('layouts.app')

@section('content')
    <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:16px; margin-bottom:22px; flex-wrap:wrap;">
        <div>
            <h1 class="display" style="font-size:24px;">User Management</h1>
            <div style="color:var(--ink-muted); font-size:13px; margin-top:4px;">
                {{ $users->count() }} akun
            </div>
        </div>
        <a href="{{ route('users.create') }}" class="btn btn-primary" style="width:auto;">+ Tambah User</a>
    </div>

    @if (session('status'))
        <div class="alert-success">{{ session('status') }}</div>
    @endif

    <section class="card table-card" style="padding:0;">
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th></th><th>Nama</th><th>Email</th><th>Role</th><th>Kode KAE</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $u)
                        <tr>
                            <td style="width:1%;">
                                @if ($u->photoUrl())
                                    <img src="{{ $u->photoUrl() }}" alt="{{ $u->name }}" style="width:28px; height:28px; border-radius:50%; object-fit:cover;">
                                @else
                                    <div style="width:28px; height:28px; border-radius:50%; background:var(--accent-soft); color:var(--accent-ink); display:flex; align-items:center; justify-content:center; font-weight:700; font-size:11px;">
                                        {{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}
                                    </div>
                                @endif
                            </td>
                            <td style="font-weight:600;">
                                {{ $u->name }}
                                @if ($u->id === auth()->id())
                                    <span style="font-size:11px; color:var(--ink-faint); font-weight:400;">(kamu)</span>
                                @endif
                            </td>
                            <td class="tnum">{{ $u->email }}</td>
                            <td>
                                @if ($u->role === 'admin')
                                    <span class="chip" style="background:var(--accent-soft); color:var(--accent-ink);">Admin</span>
                                @elseif ($u->role === 'head')
                                    <span class="chip chip-highlight">Head of SRN</span>
                                @elseif ($u->role === 'finance')
                                    <span class="chip chip-good">Finance</span>
                                @elseif ($u->role === 'compliance')
                                    <span class="chip chip-warn">Compliance</span>
                                @else
                                    <span class="chip" style="background:var(--surface-alt); color:var(--ink-muted);">KAE</span>
                                @endif
                            </td>
                            <td>
                                @if ($u->kae_code)
                                    <span style="display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:6px; background:var(--accent-soft); color:var(--accent-ink); font-size:10.5px; font-weight:700;">{{ $u->kae_code }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($u->status === 'aktif')
                                    <span class="chip chip-good">Aktif</span>
                                @else
                                    <span class="chip chip-critical">Nonaktif</span>
                                @endif
                            </td>
                            <td><a href="{{ route('users.edit', $u) }}" class="link-action" style="color:var(--accent-ink); font-size:12.5px; font-weight:600; text-decoration:none; border:1px solid var(--line); border-radius:7px; padding:5px 10px;">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="color:var(--ink-muted);">Belum ada user.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
