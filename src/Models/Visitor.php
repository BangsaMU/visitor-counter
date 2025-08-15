<?php

namespace Bangsamu\VisitorCounter\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class Visitor extends Model
{
    protected $fillable = [
        'ip', 'user_agent', 'path', 'visit_date','user_id','referer'
    ];


    public $table = "visitors";
    protected static $hasCheckedTable = false;

    protected static function boot()
    {
        parent::boot();

        if (!self::$hasCheckedTable) {
            self::$hasCheckedTable = true;

            if (!Schema::hasTable((new static)->getTable())) {
                Schema::create((new static)->getTable(), function (Blueprint $table) {

                    $table->id();
                    $table->string('ip', 45);
                    $table->date('visit_date');
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->string('user_agent')->nullable();
                    $table->string('path')->nullable();
                    $table->string('referer')->nullable();
                    $table->timestamps();

                    $table->index(['visit_date', 'ip']);
                });
            }
        }
    }
}
