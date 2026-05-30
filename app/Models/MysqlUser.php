<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MysqlUser extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mysql_users';

    protected $fillable = [
        'user_account_id', 'username', 'password_hash',
    ];

    protected $hidden = ['password_hash'];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }

    public function databases()
    {
        return $this->belongsToMany(MysqlDatabase::class, 'mysql_user_database', 'mysql_user_id', 'mysql_database_id')
            ->withPivot('privileges')
            ->withTimestamps();
    }
}
