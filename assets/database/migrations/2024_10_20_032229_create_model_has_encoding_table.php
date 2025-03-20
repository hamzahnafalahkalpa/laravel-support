<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Models\Encoding\Encoding;
use Hanafalah\LaravelSupport\Models\Encoding\ModelHasEncoding;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.ModelHasEncoding', ModelHasEncoding::class));
    }
    public function up(): void
    {
        $table_name = $this->__table->getTable();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $encoding   = app(config('database.models.Encoding', Encoding::class));

                $table->id();
                $table->string('reference_id', 36);
                $table->string('reference_type', 60);
                $table->string('value')->nullable();
                $table->foreignIdFor($encoding::class, 'encoding_id')
                    ->nullable()->cascadeOnUpdate()->restrictOnDelete();
                $table->json('props')->nullable();
                $table->timestamps();

                $table->index(["reference_id", "reference_type"], 'encoding_ref');
                $table->index(["reference_id", "reference_type", "encoding_id"], 'encoding_keys');
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
