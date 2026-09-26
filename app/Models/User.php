<?php

namespace App\Models;

use Reno\Database\Eloquent\Model;
use Reno\Auth\Contracts\Authenticatable;
use Reno\Auth\Authenticatable as AuthenticatableTrait;

/**
 * User Model
 * 
 * Represents a user in the application
 */
class User extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    /**
     * The table associated with the model
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for arrays
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the posts for the user
     */
    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Get the user's profile
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Get the user's full name
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }
}
