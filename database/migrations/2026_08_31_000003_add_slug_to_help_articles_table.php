<?php

use App\Models\HelpArticle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A slug carries real keywords into the public Hilfe-Center's URL
     * (/hilfe/{slug}) instead of a bare numeric id — see CLAUDE.md,
     * "Hilfe-Center & Support". Nullable at the DB level (defensive; the app
     * itself never leaves one blank — see HelpEditor::createArticle()/
     * updatedFormTitle()), unique so two articles can never collide.
     */
    public function up(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('title');
        });

        // Backfill any rows that existed before this column did — none on a
        // fresh install, but this keeps an already-seeded dev/production
        // table from being left with articles no public URL can reach.
        HelpArticle::whereNull('slug')->each(function (HelpArticle $article) {
            $article->update(['slug' => HelpArticle::generateSlug($article->title, $article->id)]);
        });
    }

    public function down(): void
    {
        Schema::table('help_articles', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
