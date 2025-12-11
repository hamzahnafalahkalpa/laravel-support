<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Models\ReportSummary\ReportSummary;

return new class extends Migration
{
    use Hanafalah\LaravelSupport\Concerns\NowYouSeeMe;

    private $__table;

    public function __construct()
    {
        $this->__table = app(config('database.models.ReportSummary', ReportSummary::class));
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        $table_name = $this->__table->getTable();
        if (!$this->isTableExists()) {
            Schema::create($table_name, function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('morph', 100)->nullable(false);
                $table->string('flag', 100)->nullable(false);
                $table->enum('date_type', [
                    ReportSummary::DAILY_REPORT,
                    ReportSummary::MONTHLY_REPORT,
                    ReportSummary::YEARLY_REPORT
                ]);
                $table->string('date', 20)->nullable(false);
                $table->json('props')->nullable();

                $table->index([
                    'morph',
                    'flag'
                ], 'report_sum');
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
