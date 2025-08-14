<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('cluster_articles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cluster_id')->constrained()->cascadeOnDelete();
            $t->foreignId('article_id')->constrained()->cascadeOnDelete();
            $t->float('score'); // similarity to centroid
            $t->timestamps();
            $t->unique(['cluster_id','article_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('cluster_articles');
    }
};
