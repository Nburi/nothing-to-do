<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feedback and support requests, one model with a type column (mirrors
     * AgendaEntry's homework/exam split). Every submission is visible to
     * every admin in App\Livewire\Admin\SupportQueue; the submitter sees
     * only their own, via App\Livewire\SupportCenter.
     */
    public function up(): void
    {
        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('feedback');
            $table->string('subject');
            $table->text('message');
            $table->string('status')->default('open');
            $table->text('response')->nullable();
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_requests');
    }
};
