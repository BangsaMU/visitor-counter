<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visitors', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};
