<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;
use Hanafalah\LaravelSupport\Models\LogHistory\LogHistory;

return new class extends Migration
{
    use NowYouSeeMe;

    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.Loghistory', LogHistory::class));
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
                $table->ulid('id');
                $table->string('reference_type', 50)->nullable();
                $table->string('reference_id', 36)->nullable();
                $table->longText('content')->nullable();
                $table->enum('content_type', ['JSON'])->nullable();
                $table->enum('action_type', ['insert', 'update', 'delete', 'soft_delete'])->nullable();
                $table->string('author_type', 50)->nullable();
                $table->string('author_id', 36)->nullable();
                $table->text('author_name')->nullable();
                $table->unsignedTinyInteger('status')->nullable()->default(1);
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
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
