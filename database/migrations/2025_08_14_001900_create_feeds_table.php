<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('feeds', function (Blueprint $t) {
            $t->id();
            $t->string('url')->unique();
            $t->string('source')->nullable(); // label/domain
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('feeds');
    }
};
