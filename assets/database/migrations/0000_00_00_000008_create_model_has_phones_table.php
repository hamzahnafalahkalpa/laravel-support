<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
use Hanafalah\LaravelSupport\Models\Phone\ModelHasPhone;

return new class extends Migration {
    use NowYouSeeMe;

    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.ModelHasPhone', ModelHasPhone::class));
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
                $table->string('phone', 100)->nullable(false);
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['model_type', 'model_id'], 'phn_model');
                $table->unique(['model_type', 'model_id', 'phone'],'phn_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists($this->__table->getTable());
    }
};
