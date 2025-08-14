<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('articles', function (Blueprint $t) {
            $t->id();
            $t->string('source')->index();              // feed URL or domain
            $t->string('url')->unique();
            $t->string('title');
            $t->text('excerpt')->nullable();
            $t->longText('content');                    // cleaned text
            $t->json('embedding')->nullable();          // [float,...]
            $t->unsignedBigInteger('published_at')->index();
            $t->unsignedBigInteger('simhash')->nullable()->index();
            $t->unsignedBigInteger('cluster_id')->nullable()->index();
            $t->timestamps();
        });
        // FULLTEXT for fast candidate search
        DB::statement('ALTER TABLE articles ADD FULLTEXT articles_ft (title, content)');
    }
    public function down(): void {
        Schema::dropIfExists('articles');
    }
};
