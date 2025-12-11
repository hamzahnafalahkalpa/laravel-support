<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
use Hanafalah\LaravelSupport\Models\Relation\ModelHasRelation;

return new class extends Migration {
    use NowYouSeeMe;

    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.ModelHasRelation', ModelHasRelation::class));
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $table_name = $this->__table->getTableName();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('model_type', 50)->nullable(false);
                $table->string('model_id', 36)->nullable(false);
                $table->string('relation_type', 50)->nullable(false);
                $table->string('relation_id', 36)->nullable(false);
                $table->json('props')->nullable(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['model_type', 'model_id'], 'mhr_model');
                $table->index(['relation_type', 'relation_id'], 'mhr_rel');
                $table->index([
                    'model_type',
                    'model_id',
                    'relation_type',
                    'relation_id'
                ], 'mhr_relmod');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->__table->getTable());
    }
};
