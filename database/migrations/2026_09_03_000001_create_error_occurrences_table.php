<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per rendered HTML error page (404/403/419/500/...) — never per
     * Livewire-action or API failure, those stay JSON and are excluded at the
     * point of writing (App\Services\ErrorStats::record()). Read by the admin
     * "Fehler-Statistiken" page (App\Livewire\Admin\ErrorLog); pruned nightly
     * by app:prune-error-occurrences so a runaway error can't grow this
     * table unbounded.
     */
    public function up(): void
    {
        Schema::create('error_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status_code');
            $table->string('exception_class')->nullable();
            $table->string('message', 500)->nullable();
            $table->string('path', 2048);
            $table->string('method', 10);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index('status_code');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('error_occurrences');
    }
};
