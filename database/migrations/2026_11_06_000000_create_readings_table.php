<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('readings', function (Blueprint $table): void {
            $table->id();
            $table->string('node', 16);
            $table->string('metric', 32);
            $table->decimal('value', 12, 4);
            $table->string('unit', 16);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();
            // Satu metric per node per detik: broker QoS boleh kirim ulang pesan,
            // upsert di ReadingIngester memakai kombinasi ini sebagai kunci dedupe.
            $table->unique(['node', 'metric', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('readings');
    }
};
