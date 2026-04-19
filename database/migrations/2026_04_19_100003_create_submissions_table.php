<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('instrument_id')->constrained()->cascadeOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index('patient_id');
            $table->index('instrument_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
