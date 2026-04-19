<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('link');
            $table->text('guid')->unique();
            $table->timestamp('pub_date');
            $table->string('source_id');
            $table->enum('status', ['processing', 'done'])->default('processing');
            $table->timestamps();

            $table->foreign('source_id')->references('id')->on('sources');

            $table->index('source_id');
            $table->index('status');
            $table->index('pub_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
