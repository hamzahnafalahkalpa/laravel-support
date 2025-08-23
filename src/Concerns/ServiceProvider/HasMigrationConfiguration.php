<?php

namespace Hanafalah\LaravelSupport\Concerns\ServiceProvider;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Hanafalah\LaravelSupport\Concerns\Support\HasRepository;
use Symfony\Component\Filesystem\Filesystem;
use Illuminate\Support\Str;

trait HasMigrationConfiguration
{
    use HasRepository;
    protected string $__migration_base_path   = '../assets/database/migrations';

    public function InitializeHasMigrationConfiguration()
    {
        $this->setMigrationBasePath($this->dir() . $this->__migration_base_path);
    }

    /**
     * Get migration path
     *
     * @return string
     */
    protected function getMigrationPath(): string
    {
        return $this->getMigrationBasePath();
    }

    /**
     * Get migration base path
     *
     * @return string
     */
    protected function getMigrationBasePath(): string
    {
        return $this->__migration_base_path;
    }

    /**
     * Set the base path for migrations.
     *
     * @param string $path The path to set as the migration base path
     * @return self
     */
    protected function setMigrationBasePath(string $path): self
    {
        if ($this::class == 'Hanafalah\ModuleUser\Commands\InstallMakeCommand') dd($path,$this->dir());
        $path = $this->makeRealPath($path, $this->dir());
        $this->__migration_base_path = $this->dir() . $path;
        return $this;
    }

    private function isValidPath(string $path)
    {
        return !Str::startsWith($path, '..') && !Str::startsWith($path, '/');
    }

    protected function makeRealPath(string $end_path, string $start_path): string
    {
        if (!$this->isValidPath($start_path)) {
            $start_path = $this->dir() . $start_path;
        }
        return (new Filesystem)->makePathRelative($end_path, $start_path);
    }

    protected function migrationPath(string $path = ''): string
    {
        return database_path('migrations' . $path);
    }

    protected function canMigrate(string $from_path = ''): array
    {
        $lists      = $this->scanMigration($from_path);
        if (Schema::hasTable('migrations')) {
            $migrated   = collect(DB::table('migrations')->pluck('migration'))->all();
            $unmigrated = $this->diff($lists, $migrated);
        }
        $lists = $unmigrated ?? $lists;
        foreach ($lists as &$list) {
            $list = 'database/migrations/' . $list . '.php';
        }
        return $lists;
    }

    protected function scanMigration(string $from_path = ''): array
    {
        $lists           = [];
        $path            = $this->getMigrationPath() . $from_path;
        if (!$this->isValidPath($path)) $path = $this->dir() . $path;
        if (is_dir($path)) {
            $scan_migrations = scandir($path);
            foreach ($scan_migrations as $file) {
                if (!Str::endsWith($file, '.php')) continue;
                $lists[] = Str::replace('.php', '', $file);
            }
        }
        return $lists;
    }

    protected function scanForPublishMigration($from_path = '', $target_path = ''): array
    {
        $publish = [];
        $path    = $this->getMigrationPath() . $from_path;
        if (!$this->isValidPath($path)) $path = $this->dir() . $path;
        if (!$this->isDir($path)) $this->makeDir($path, 0777, true);
        if (is_dir($path)) {
            $scan_migrations = scandir($path);
            foreach ($scan_migrations as $file) {
                if ($file == '.' || $file == '..' || $this->isDir($path . '/' . $file)) continue;
                $publish[$path . '/' . $file] = $this->migrationPath($target_path . '/' . $file);
            }
        }
        return $publish;
    }
}
