<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Models\Phone\ModelHasPhone;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.ModelHasPhone', ModelHasPhone::class));
    }
    public function up(): void
    {
        $table_name = $this->__table->getTable();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('model_id', 36);
                $table->string('model_type', 50);
                $table->string('phone', 50);
                $table->timestamp('verified_at')->nullable();
                $table->json('props')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(["model_id", "model_type"], 'model_phn');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists($this->__table->getTable());
    }
};
