<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Master\Sekolah;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'password',
        'password_changed_at',
        'sekolah_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isPasswordExpired(): bool
    {
        if (! $this->hasRole('operator_sekolah')) {
            return false;
        }

        if ($this->password_changed_at === null) {
            return true;
        }

        return $this->password_changed_at->diffInDays(now()) >= 90;
    }

    public function sekolah(): BelongsTo
    {
        return $this->belongsTo(Sekolah::class, 'sekolah_id');
    }
}
