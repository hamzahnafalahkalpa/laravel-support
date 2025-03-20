<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Models\Encoding\Encoding;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.Encoding', Encoding::class));
    }
    public function up(): void
    {
        $table_name = $this->__table->getTable();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('name')->nullable()->index();
                $table->string('flag', 45)->nullable();
                $table->timestamps();
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
