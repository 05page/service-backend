<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; 
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'fullname',
        'email',
        'telephone',
        'adresse',
        'role',
        'password',
        'code_activation',
        'activate_at',
        'active',
        'created_by'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'activated_at' => 'datetime',
        'active' => 'boolean',
    ];

    const ROLE_ADMIN = 'admin';
    const ROLE_EMPLOYE = 'employe';
    // const ROLE_INTERMEDIAIRE = 'intermediaire';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            $user->activation_code = Str::random(8);
            if (!empty($user->password)) {
                $user->password = bcrypt($user->password);
            }
        });

        static::updating(function ($user) {
            if ($user->isDirty('password') && !empty($user->password)) {
                $user->password = bcrypt($user->password);
            }
        });
    }

    // Relations
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(Permissions::class, 'employe_id');
    }

    // Helpers
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isEmploye(): bool
    {
        return $this->role === self::ROLE_EMPLOYE;
    }

    // public function isIntermediaire(): bool
    // {
    //     return $this->role === self::ROLE_INTERMEDIAIRE;
    // }

     public function recordLogin()
    {
        $this->update([
            'last_login_at' => now(),
        ]);
    }

    public function deactivate()
    {
        $this->update(['active' => false]);
    }

    /**
     * Réactiver l'utilisateur
     */
    public function activate()
    {
        $this->update(['active' => true]);
    }

    public function activate_code(): bool
    {
        $this->activated_at = now();
        $this->activation_code = null; // une fois activé, on supprime le code
        return $this->save();
    }
}