<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', [
            'users' => User::orderBy('role')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('users.form', ['targetUser' => new User()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('user-photos', 'public');
        }

        User::create($data);

        return redirect()->route('users.index')->with('status', 'User baru berhasil ditambahkan.');
    }

    public function edit(User $targetUser): View
    {
        return view('users.form', compact('targetUser'));
    }

    public function update(Request $request, User $targetUser): RedirectResponse
    {
        $data = $this->validated($request, $targetUser);

        if ($targetUser->id === $request->user()->id) {
            if ($data['role'] !== 'admin') {
                return back()->withErrors(['role' => 'Kamu tidak bisa mengubah role akun sendiri.'])->withInput();
            }
            if ($data['status'] !== 'aktif') {
                return back()->withErrors(['status' => 'Kamu tidak bisa menonaktifkan akun sendiri.'])->withInput();
            }
        }

        if ($request->hasFile('photo')) {
            if ($targetUser->photo) {
                Storage::disk('public')->delete($targetUser->photo);
            }
            $data['photo'] = $request->file('photo')->store('user-photos', 'public');
        } elseif ($request->boolean('hapus_foto') && $targetUser->photo) {
            Storage::disk('public')->delete($targetUser->photo);
            $data['photo'] = null;
        }

        $targetUser->update($data);

        return redirect()->route('users.index')->with('status', 'User berhasil diperbarui.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $targetUser): array
    {
        $isNew = $targetUser === null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'.($isNew ? '' : ','.$targetUser->id)],
            'password' => [$isNew ? 'required' : 'nullable', 'string', 'min:6'],
            'role' => ['required', 'in:admin,kae,head,finance,compliance'],
            'kae_code' => [
                'nullable', 'string', 'max:5',
                'required_if:role,kae',
                'unique:users,kae_code'.($isNew ? '' : ','.$targetUser->id),
            ],
            'status' => ['required', 'in:aktif,nonaktif'],
            'target_mitra_aktif' => ['nullable', 'integer', 'min:0'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);

        unset($data['photo']);

        if ($data['role'] !== 'kae') {
            $data['kae_code'] = null;
            $data['target_mitra_aktif'] = null;
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        return $data;
    }
}
