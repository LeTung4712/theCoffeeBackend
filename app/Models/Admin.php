<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tymon\JWTAuth\Contracts\JWTSubject;

class Admin extends Authenticatable  implements JWTSubject
{
    use HasFactory, Notifiable, HasApiTokens;
    protected $table = 'admins'; 
    // khai báo ở đây có tác dụng là khi tạo mới 1 admin thì nó sẽ lưu vào bảng admins chứ không phải là bảng users

    protected $guard = 'admin'; 
    // $guard là 1 biến có sẵn trong laravel, nó có tác dụng là khi tạo mới 1 admin thì nó sẽ lưu vào bảng admins chứ không phải là bảng users
    
    protected $fillable = [
        'username',
        'password',
    ];
    protected $hidden = [
        'password',
    ];

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }
}
