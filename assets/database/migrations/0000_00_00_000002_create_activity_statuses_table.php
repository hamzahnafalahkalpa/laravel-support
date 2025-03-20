<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
use Hanafalah\LaravelSupport\Models\Activity\Activity;
use Hanafalah\LaravelSupport\Models\Activity\ActivityStatus;

return new class extends Migration
{
    use NowYouSeeMe;

    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.ActivityStatus', ActivityStatus::class));
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table_name = $this->__table->getTableName();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $table->string('id', 36)->primary();
                $table->foreignIdFor(Activity::class)->index()
                    ->constrained()->cascadeOnUpdate()->cascadeOnDelete();
                $table->unsignedBigInteger('status');
                $table->unsignedTinyInteger('active')->default(1);
                $table->text('message');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists($this->__table->getTableName());
    }
};
