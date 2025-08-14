<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('clusters', function (Blueprint $t) {
            $t->id();
            $t->json('centroid')->nullable(); // embedding centroid
            $t->integer('size')->default(0);
            $t->string('label')->nullable();
            $t->text('summary')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('clusters');
    }
};
