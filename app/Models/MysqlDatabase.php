<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MysqlDatabase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'mysql_databases';

    protected $fillable = [
        'account_id', 'user_account_id', 'database_name', 'charset', 'collation', 'size_bytes',
    ];

    public function userAccount()
    {
        return $this->belongsTo(UserAccount::class);
    }

    public function users()
    {
        return $this->belongsToMany(MysqlUser::class, 'mysql_user_database', 'mysql_database_id', 'mysql_user_id')
            ->withPivot('privileges')
            ->withTimestamps();
    }
}
