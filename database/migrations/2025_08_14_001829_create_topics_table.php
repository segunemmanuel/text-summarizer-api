<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('topics', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index(); // swap for foreignId if you have users
            $t->string('name');
            $t->text('query'); // keywords
            $t->json('filters')->nullable(); // {domains:[], since:...}
            $t->enum('alert_type', ['instant','daily'])->default('daily');
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('topics');
    }
};
