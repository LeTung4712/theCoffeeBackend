<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'gender',
        'mobile_no',
        'email',
        'date_of_birth',
        'access_token',
        'refresh_token',
        'refresh_token_expired_at',
        'last_login_at',
        'login_attempts',
        'locked_until',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
        'login_attempts',
        'locked_until',
    ];

    protected $casts = [
        'birth'         => 'datetime',
        'last_login_at' => 'datetime',
        'locked_until'  => 'datetime',
        'is_active'     => 'boolean',
    ];

    // hàm này lấy id của user để tạo token
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    // Thêm type vào token
    public function getJWTCustomClaims()
    {
        return [
            'type'       => 'user',
            'mobile_no'  => $this->mobile_no,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
        ];
    }

    // hàm này check số lần đăng nhập sai nếu >= 5 thì khóa tài khoản 10 phút
    public function incrementLoginAttempts()
    {
        $this->login_attempts++;
        if ($this->login_attempts >= 5) {
            $this->locked_until = now()->addMinutes(10);
        }
        $this->save();
    }

    // hàm này reset số lần đăng nhập sai và khóa tài khoản
    public function resetLoginAttempts()
    {
        $this->login_attempts = 0;
        $this->locked_until   = null;
        $this->last_login_at  = now();
        $this->save();
    }

    // hàm này check tài khoản có bị khóa không
    public function isLocked()
    {
        return $this->locked_until && now()->isBefore($this->locked_until);
    }
}
