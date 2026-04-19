<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logos', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_id')->unique();
            $table->text('object_key');
            $table->text('url');
            $table->text('mime_type')->nullable();
            $table->bigInteger('size_bytes')->nullable();
            $table->timestamps();

            $table->foreign('source_id')
                ->references('id')
                ->on('sources')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logos');
    }
};
