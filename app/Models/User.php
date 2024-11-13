<?php

namespace App\Models;

use App\Notifications\Api\V1\CustomVerifyEmail;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'image',
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'address',
        'suburb',
        'state',
        'post_code',
        'country',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return void
     * Override the sendEmailVerificationNotification method to use custom notification
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new CustomVerifyEmail());
    }

    /**
     * @return bool
     * Override the hasVerifiedEmail method to use custom check
     */
    public function hasVerifiedEmail() : bool
    {
        return !is_null($this->email_verified_at) && ($this->is_verified);
    }

    /**
     * @return bool
     * Override the hasVerifiedEmail method to use custom fill
     */
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'is_verified' => true,
        ])->save();
    }

}
