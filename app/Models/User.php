<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'kae_code', 'photo', 'status', 'target_mitra_aktif'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isHead(): bool
    {
        return $this->role === 'head';
    }

    public function isFinance(): bool
    {
        return $this->role === 'finance';
    }

    public function isCompliance(): bool
    {
        return $this->role === 'compliance';
    }

    /** Role dengan visibilitas company-wide (bukan cuma data KAE sendiri): admin, head, finance, compliance. */
    public function canViewAll(): bool
    {
        return $this->isAdmin() || $this->isHead() || $this->isFinance() || $this->isCompliance();
    }

    public function photoUrl(): ?string
    {
        return $this->photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->photo) : null;
    }

    public function mitra()
    {
        return $this->hasMany(Mitra::class);
    }

    public function followupLogs()
    {
        return $this->hasMany(FollowupLog::class, 'kae_user_id');
    }

    /**
     * kae_code => name, for tables that store the short code and need to
     * display the KAE's actual name instead.
     */
    public static function kaeNameMap(): array
    {
        static $map = null;

        if ($map === null) {
            $map = static::where('role', 'kae')->whereNotNull('kae_code')->pluck('name', 'kae_code')->all();
        }

        return $map;
    }

    /**
     * kae_code => target_mitra_aktif, for the Run Rate Mitra Active report.
     */
    public static function kaeTargetAktifMap(): array
    {
        return static::where('role', 'kae')->whereNotNull('kae_code')->pluck('target_mitra_aktif', 'kae_code')->all();
    }

    /**
     * kae_code => photo URL (or null), for pages that show a KAE's avatar
     * without loading the full User model (e.g. Sales Overview).
     */
    public static function kaePhotoMap(): array
    {
        return static::where('role', 'kae')->whereNotNull('kae_code')->get(['kae_code', 'photo'])
            ->mapWithKeys(fn ($u) => [$u->kae_code => $u->photoUrl()])
            ->all();
    }
}
