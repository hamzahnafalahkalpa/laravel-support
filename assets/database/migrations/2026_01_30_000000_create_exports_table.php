<?php

use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
use Hanafalah\LaravelSupport\Models\Export\Export;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use NowYouSeeMe;

    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.Export', Export::class));
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $table_name = $this->__table->getTable();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->ulid('user_id')->nullable()->index();
                $table->string('export_type');
                $table->string('reference_type')->nullable();
                $table->string('reference_id')->nullable();
                $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'])->default('PENDING')->index();
                $table->string('file_path')->nullable();
                $table->string('file_name')->nullable();
                $table->text('error_message')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Composite index for reference lookup
                $table->index(['reference_type', 'reference_id']);

                // Index for cleanup queries
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exports');
    }
};
