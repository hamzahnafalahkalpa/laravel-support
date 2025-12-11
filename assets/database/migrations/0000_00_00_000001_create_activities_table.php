<?php

use Hanafalah\LaravelSupport\Models\Activity\Activity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;

return new class extends Migration
{
    use NowYouSeeMe;

    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.Activity', Activity::class));
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $table_name = Activity::getTableName();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('activity_flag', 50);
                $table->string('reference_type', 50);
                $table->string('reference_id', 36);
                $table->unsignedBigInteger('activity_status')->nullable();
                $table->unsignedTinyInteger('status')->default(1);
                $table->text('message')->nullable();
                $table->json('props')->nullable();
                $table->timestamps();

                $table->index(['reference_type', 'reference_id'],'act_ref');
                $table->index(['activity_flag', 'reference_type', 'reference_id'],'act_flag_ref');
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
        $table_name = Activity::getTableName();
        Schema::dropIfExists($table_name);
    }
};
