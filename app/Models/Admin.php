<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'admins';
    protected $guard = 'admin';

    protected $fillable = [
        'username',
        'password',
        'access_token',
        'refresh_token',
        'refresh_token_expired_at',
    ];

    protected $hidden = [
        'password',
        'access_token',
        'refresh_token',
    ];


    // JWT required methods
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'username' => $this->username,
            'guard'    => 'admin',
        ];
    }

}
