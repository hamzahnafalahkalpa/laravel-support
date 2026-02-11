<?php

namespace Hanafalah\LaravelSupport\Commands;

use Hanafalah\LaravelSupport\Services\SetupCacheService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Relations\Relation;

class SetupStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:status
                            {project=backbone : Project name (backbone, lite, hq, plus, gateway)}
                            {--models : Show all cached models}
                            {--contracts : Show all cached contracts}
                            {--providers : Show all cached providers}
                            {--search= : Search for specific model/contract name}
                            {--compare : Compare cached vs current morphMap}
                            {--all : Show all details (models, contracts, providers)}
                            {--logs : Show recent profiling logs from Laravel log}
                            {--logs-lines=30 : Number of log lines to search (default 30)}
                            {--watch : Watch logs in real-time (Ctrl+C to stop)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show setup cache status and details for debugging';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Handle --logs option
        if ($this->option('logs')) {
            return $this->showProfilingLogs();
        }

        // Handle --watch option
        if ($this->option('watch')) {
            return $this->watchLogs();
        }

        $project = $this->argument('project');
        $projectName = $this->resolveProjectName($project);

        $this->info("Project: {$projectName}");
        $this->newLine();

        $cache = new SetupCacheService($projectName);
        $info = $cache->getInfo();

        // Show basic status
        $this->showBasicStatus($info);

        // Get cached data for detailed views
        $cachedData = $this->getCachedData($cache);

        // Handle search
        if ($search = $this->option('search')) {
            return $this->searchInCache($cachedData, $search);
        }

        // Handle compare
        if ($this->option('compare')) {
            return $this->compareMorphMaps($cachedData);
        }

        // Show details based on options
        $showAll = $this->option('all');

        if ($showAll || $this->option('models')) {
            $this->showModels($cachedData['models'] ?? []);
        }

        if ($showAll || $this->option('contracts')) {
            $this->showContracts($cachedData['contracts'] ?? []);
        }

        if ($showAll || $this->option('providers')) {
            $this->showProviders($cachedData['providers'] ?? []);
        }

        return Command::SUCCESS;
    }

    /**
     * Show basic cache status
     */
    protected function showBasicStatus(array $info): void
    {
        $this->line("<fg=cyan>Cache Status:</>");

        $statusColor = $info['exists'] ? ($info['valid'] ? 'green' : 'yellow') : 'red';
        $statusText = $info['exists']
            ? ($info['valid'] ? 'VALID' : 'OUTDATED (version mismatch)')
            : 'NOT SET';

        $this->table(
            ['Property', 'Value'],
            [
                ['Status', "<fg={$statusColor}>{$statusText}</>"],
                ['Cache Key', $info['cache_key']],
                ['Current Version', substr($info['current_version'], 0, 16) . '...'],
                ['Cached Version', $info['cached_version'] ? substr($info['cached_version'], 0, 16) . '...' : '<fg=gray>N/A</>'],
                ['Generated At', $info['generated_at'] ?? '<fg=gray>N/A</>'],
                ['Models', $info['models_count']],
                ['Contracts', $info['contracts_count']],
                ['Providers', $info['providers_count']],
            ]
        );

        $this->newLine();
    }

    /**
     * Get cached data from Redis
     */
    protected function getCachedData(SetupCacheService $cache): array
    {
        try {
            // Access the cached data directly
            $reflection = new \ReflectionClass($cache);
            $method = $reflection->getMethod('getFromRedis');
            $method->setAccessible(true);
            return $method->invoke($cache) ?? [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Show all models
     */
    protected function showModels(array $models): void
    {
        if (empty($models)) {
            $this->warn("No models in cache.");
            return;
        }

        $this->line("<fg=cyan>Cached Models (" . count($models) . "):</>");
        $this->newLine();

        $rows = [];
        foreach ($models as $alias => $class) {
            // Determine source (project vs repository)
            $source = $this->determineSource($class);
            $rows[] = [$alias, $class, $source];
        }

        // Sort by alias
        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

        $this->table(['Alias', 'Class', 'Source'], $rows);
        $this->newLine();
    }

    /**
     * Show all contracts
     */
    protected function showContracts(array $contracts): void
    {
        if (empty($contracts)) {
            $this->warn("No contracts in cache.");
            return;
        }

        $this->line("<fg=cyan>Cached Contracts (" . count($contracts) . "):</>");
        $this->newLine();

        $rows = [];
        foreach ($contracts as $contract => $implementation) {
            $rows[] = [
                class_basename($contract),
                $this->shortenClass($contract),
                $this->shortenClass($implementation),
            ];
        }

        // Sort by contract name
        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

        $this->table(['Name', 'Contract', 'Implementation'], $rows);
        $this->newLine();
    }

    /**
     * Show all providers
     */
    protected function showProviders(array $providers): void
    {
        if (empty($providers)) {
            $this->warn("No providers in cache.");
            return;
        }

        $this->line("<fg=cyan>Cached Providers (" . count($providers) . "):</>");
        $this->newLine();

        $rows = [];
        foreach ($providers as $provider) {
            $source = $this->determineSource($provider);
            $rows[] = [class_basename($provider), $this->shortenClass($provider), $source];
        }

        // Sort by name
        usort($rows, fn($a, $b) => strcmp($a[0], $b[0]));

        $this->table(['Name', 'Class', 'Source'], $rows);
        $this->newLine();
    }

    /**
     * Search for specific model/contract
     */
    protected function searchInCache(array $cachedData, string $search): int
    {
        $this->line("<fg=cyan>Searching for: {$search}</>");
        $this->newLine();

        $found = false;
        $searchLower = strtolower($search);

        // Search in models
        $this->line("<fg=yellow>Models:</>");
        foreach ($cachedData['models'] ?? [] as $alias => $class) {
            if (stripos($alias, $search) !== false || stripos($class, $search) !== false) {
                $source = $this->determineSource($class);
                $this->line("  <fg=green>✓</> {$alias}");
                $this->line("    Class: {$class}");
                $this->line("    Source: <fg=gray>{$source}</>");
                $found = true;
            }
        }

        // Search in current morphMap
        $this->newLine();
        $this->line("<fg=yellow>Current MorphMap (runtime):</>");
        $morphMap = Relation::morphMap();
        foreach ($morphMap as $alias => $class) {
            if (stripos($alias, $search) !== false || stripos($class, $search) !== false) {
                $source = $this->determineSource($class);
                $this->line("  <fg=green>✓</> {$alias}");
                $this->line("    Class: {$class}");
                $this->line("    Source: <fg=gray>{$source}</>");
                $found = true;
            }
        }

        // Search in contracts
        $this->newLine();
        $this->line("<fg=yellow>Contracts:</>");
        foreach ($cachedData['contracts'] ?? [] as $contract => $implementation) {
            if (stripos($contract, $search) !== false || stripos($implementation, $search) !== false) {
                $this->line("  <fg=green>✓</> " . class_basename($contract));
                $this->line("    Contract: {$contract}");
                $this->line("    Implementation: {$implementation}");
                $found = true;
            }
        }

        if (!$found) {
            $this->warn("No results found for '{$search}'");
        }

        $this->newLine();
        return Command::SUCCESS;
    }

    /**
     * Compare cached morphMap vs current runtime morphMap
     */
    protected function compareMorphMaps(array $cachedData): int
    {
        $this->line("<fg=cyan>Comparing Cached vs Runtime MorphMap:</>");
        $this->newLine();

        $cachedModels = $cachedData['models'] ?? [];
        $runtimeModels = Relation::morphMap();

        $differences = [];
        $onlyInCache = [];
        $onlyInRuntime = [];

        // Find differences
        foreach ($cachedModels as $alias => $cachedClass) {
            if (!isset($runtimeModels[$alias])) {
                $onlyInCache[$alias] = $cachedClass;
            } elseif ($runtimeModels[$alias] !== $cachedClass) {
                $differences[$alias] = [
                    'cached' => $cachedClass,
                    'runtime' => $runtimeModels[$alias],
                ];
            }
        }

        foreach ($runtimeModels as $alias => $runtimeClass) {
            if (!isset($cachedModels[$alias])) {
                $onlyInRuntime[$alias] = $runtimeClass;
            }
        }

        // Show differences
        if (!empty($differences)) {
            $this->error("CONFLICTS (different classes for same alias):");
            $rows = [];
            foreach ($differences as $alias => $diff) {
                $rows[] = [
                    $alias,
                    $this->shortenClass($diff['cached']),
                    $this->shortenClass($diff['runtime']),
                ];
            }
            $this->table(['Alias', 'Cached Class', 'Runtime Class'], $rows);
            $this->newLine();
        }

        if (!empty($onlyInCache)) {
            $this->warn("Only in cache (not in runtime):");
            foreach ($onlyInCache as $alias => $class) {
                $this->line("  - {$alias}: {$class}");
            }
            $this->newLine();
        }

        if (!empty($onlyInRuntime)) {
            $this->warn("Only in runtime (not in cache):");
            foreach ($onlyInRuntime as $alias => $class) {
                $this->line("  - {$alias}: {$class}");
            }
            $this->newLine();
        }

        if (empty($differences) && empty($onlyInCache) && empty($onlyInRuntime)) {
            $this->info("✓ Cache and runtime morphMap are identical.");
        } else {
            $this->newLine();
            $this->line("<fg=yellow>Tip:</> Run 'php artisan setup:clear {$this->argument('project')}' to regenerate cache.");
        }

        $this->newLine();
        return Command::SUCCESS;
    }

    /**
     * Determine the source of a class (project vs repository vs feature)
     */
    protected function determineSource(string $class): string
    {
        if (str_starts_with($class, 'Projects\\WellmedBackbone\\')) {
            return '<fg=green>wellmed-backbone</>';
        }
        if (str_starts_with($class, 'Projects\\WellmedLite\\')) {
            return '<fg=green>wellmed-lite</>';
        }
        if (str_starts_with($class, 'Projects\\WellmedPlus\\')) {
            return '<fg=green>wellmed-plus</>';
        }
        if (str_starts_with($class, 'Projects\\Hq\\')) {
            return '<fg=green>wellmed-hq</>';
        }
        if (str_starts_with($class, 'Features\\')) {
            return '<fg=blue>feature</>';
        }
        if (str_starts_with($class, 'Hanafalah\\')) {
            return '<fg=gray>repository</>';
        }
        if (str_starts_with($class, 'App\\')) {
            return '<fg=cyan>app</>';
        }
        return '<fg=gray>unknown</>';
    }

    /**
     * Shorten class name for display
     */
    protected function shortenClass(string $class): string
    {
        // Replace common prefixes
        $class = str_replace('Projects\\WellmedBackbone\\', 'P\\WB\\', $class);
        $class = str_replace('Projects\\WellmedLite\\', 'P\\WL\\', $class);
        $class = str_replace('Projects\\WellmedPlus\\', 'P\\WP\\', $class);
        $class = str_replace('Hanafalah\\', 'H\\', $class);
        $class = str_replace('Features\\', 'F\\', $class);

        return $class;
    }

    /**
     * Resolve the full project name from short name
     */
    protected function resolveProjectName(string $project): string
    {
        $projectMap = [
            'backbone' => 'wellmed-backbone',
            'lite' => 'wellmed-lite',
            'hq' => 'wellmed-hq',
            'plus' => 'wellmed-plus',
            'gateway' => 'wellmed-gateway',
            'satu-sehat' => 'wellmed-satu-sehat',
        ];

        if (isset($projectMap[$project])) {
            return $projectMap[$project];
        }

        if (str_starts_with($project, 'wellmed-')) {
            return $project;
        }

        return 'wellmed-' . $project;
    }

    /**
     * Show recent profiling logs from Laravel log file
     */
    protected function showProfilingLogs(): int
    {
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            $this->error("Log file not found: {$logPath}");
            return Command::FAILURE;
        }

        $lines = (int) $this->option('logs-lines');
        $this->info("Showing profiling logs (last {$lines} entries searched)");
        $this->newLine();

        // Read last N lines from log file
        $logContent = $this->tailFile($logPath, $lines * 10); // Read more lines to find profiling entries

        // Parse and display profiling entries
        $profilingPatterns = [
            'ApiAccessProfile' => '/<fg=cyan>API Access Middleware</>',
            'ApiAccess::accessOnLogin Breakdown' => '/<fg=magenta>ApiAccess accessOnLogin</>',
            'MicroTenant::accessOnLogin Breakdown' => '/<fg=magenta>MicroTenant accessOnLogin</>',
            'MicroTenant::onLogin Breakdown' => '/<fg=magenta>MicroTenant onLogin</>',
            'Token::handle Breakdown' => '/<fg=yellow>Token Schema</>',
            'JWTTokenValidator::handle Breakdown' => '/<fg=yellow>JWT Validator</>',
            'JWTTokenValidator::tokenValidator Breakdown' => '/<fg=yellow>Token Validator</>',
            'MicroTenant::tenantImpersonate Profiling' => '/<fg=yellow>Tenant Impersonate</>',
            'MicroTenant::impersonate' => '/<fg=blue>Impersonate Level</>',
            'RequestProfile' => '/<fg=magenta>Request Lifecycle</>',
            'RequestTimeline' => '/<fg=magenta>Request Timeline</>',
            'PatientProfile' => '/<fg=green>Patient Endpoint</>',
            '[PROFILE]' => '/<fg=white>JSON Profile</>',
        ];

        $entries = $this->parseProfilingLogs($logContent, $profilingPatterns);

        if (empty($entries)) {
            $this->warn("No profiling logs found. Make sure:");
            $this->line("  1. REQUEST_PROFILE=true in .env");
            $this->line("  2. 'profiling' => ['enabled' => true] in micro-tenant.php");
            $this->line("  3. An API request has been made");
            $this->newLine();
            return Command::SUCCESS;
        }

        // Group by request (based on timestamp proximity)
        $this->displayProfilingEntries($entries);

        return Command::SUCCESS;
    }

    /**
     * Watch logs in real-time
     */
    protected function watchLogs(): int
    {
        $logPath = storage_path('logs/laravel.log');

        if (!file_exists($logPath)) {
            $this->error("Log file not found: {$logPath}");
            return Command::FAILURE;
        }

        $this->info("Watching profiling logs in real-time (Ctrl+C to stop)");
        $this->line("<fg=gray>Waiting for new profiling entries...</>");
        $this->newLine();

        $lastSize = filesize($logPath);
        $patterns = ['ApiAccessProfile', 'accessOnLogin Breakdown', 'onLogin Breakdown', 'tenantImpersonate Profiling', 'MicroTenant::impersonate', 'RequestProfile'];

        while (true) {
            clearstatcache(true, $logPath);
            $currentSize = filesize($logPath);

            if ($currentSize > $lastSize) {
                $handle = fopen($logPath, 'r');
                fseek($handle, $lastSize);
                $newContent = fread($handle, $currentSize - $lastSize);
                fclose($handle);

                foreach (explode("\n", $newContent) as $line) {
                    foreach ($patterns as $pattern) {
                        if (str_contains($line, $pattern)) {
                            $this->displayLogLine($line);
                            break;
                        }
                    }
                }

                $lastSize = $currentSize;
            }

            usleep(500000); // 0.5 second
        }

        return Command::SUCCESS;
    }

    /**
     * Read last N lines from a file
     */
    protected function tailFile(string $path, int $lines): string
    {
        $handle = fopen($path, 'r');
        if (!$handle) return '';

        $buffer = '';
        $lineCount = 0;
        $chunkSize = 4096;

        fseek($handle, 0, SEEK_END);
        $position = ftell($handle);

        while ($position > 0 && $lineCount < $lines) {
            $readSize = min($chunkSize, $position);
            $position -= $readSize;
            fseek($handle, $position);
            $chunk = fread($handle, $readSize);
            $buffer = $chunk . $buffer;
            $lineCount = substr_count($buffer, "\n");
        }

        fclose($handle);

        // Return only last N lines
        $allLines = explode("\n", $buffer);
        return implode("\n", array_slice($allLines, -$lines));
    }

    /**
     * Parse profiling logs and extract entries
     */
    protected function parseProfilingLogs(string $content, array $patterns): array
    {
        $entries = [];
        $lines = explode("\n", $content);

        foreach ($lines as $index => $line) {
            // Check for new JSON-based PROFILE logs
            if (str_contains($line, '[PROFILE]')) {
                $entry = $this->parseJsonProfileLog($line);
                if ($entry) {
                    $entries[] = $entry;
                    continue;
                }
            }

            // Check for RequestTimeline logs
            if (str_contains($line, '[RequestTimeline]')) {
                $entry = $this->parseTimelineLog($line, $lines, $index);
                if ($entry) {
                    $entries[] = $entry;
                    continue;
                }
            }

            foreach ($patterns as $pattern => $label) {
                if (str_contains($line, $pattern)) {
                    // Extract timestamp
                    if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
                        $timestamp = $matches[1];
                    } else {
                        $timestamp = 'unknown';
                    }

                    // Extract JSON data if present
                    $jsonData = null;
                    if (preg_match('/\{[^}]+\}/', $line, $jsonMatches)) {
                        $jsonData = json_decode($jsonMatches[0], true);
                    }

                    // Extract box content for ApiAccessProfile
                    $boxContent = null;
                    if (str_contains($line, 'ApiAccessProfile') || str_contains($line, 'RequestProfile')) {
                        $boxContent = $this->extractBoxMetrics($line, $lines, array_search($line, $lines));
                    }

                    $entries[] = [
                        'timestamp' => $timestamp,
                        'type' => $pattern,
                        'label' => $label,
                        'data' => $jsonData,
                        'box' => $boxContent,
                        'raw' => $line,
                    ];
                    break;
                }
            }
        }

        return $entries;
    }

    /**
     * Parse JSON-based PROFILE log
     */
    protected function parseJsonProfileLog(string $line): ?array
    {
        // Extract timestamp
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $timestamp = $matches[1];
        } else {
            return null;
        }

        // Extract JSON data
        if (preg_match('/\[PROFILE\]\s*(\{.+\})/', $line, $jsonMatches)) {
            $data = json_decode($jsonMatches[1], true);
            if ($data) {
                return [
                    'timestamp' => $timestamp,
                    'type' => 'PROFILE_JSON',
                    'label' => 'Request Profile',
                    'profile_data' => $data,
                    'raw' => $line,
                ];
            }
        }

        return null;
    }

    /**
     * Parse Timeline log with box format
     */
    protected function parseTimelineLog(string $line, array $lines, int $startIndex): ?array
    {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $timestamp = $matches[1];
        } else {
            return null;
        }

        // Extract route and total time from the box
        $boxData = ['checkpoints' => [], 'sections' => [], 'queries' => []];

        for ($i = $startIndex; $i < min($startIndex + 50, count($lines)); $i++) {
            $boxLine = $lines[$i];

            // Extract route
            if (preg_match('/Route:\s*(\S+)/', $boxLine, $routeMatch)) {
                $boxData['route'] = trim($routeMatch[1]);
            }

            // Extract TOTAL
            if (preg_match('/TOTAL:\s*([\d.]+)\s*ms/', $boxLine, $totalMatch)) {
                $boxData['total'] = (float) $totalMatch[1];
            }

            // Extract timeline entries (format: "  123.4 ms │ checkpoint_name │ +12.3 ms")
            if (preg_match('/║\s*([\d.]+)\s*ms\s*│\s*(\S+)\s*│\s*\+([\d.]+)\s*ms/', $boxLine, $cpMatch)) {
                $boxData['checkpoints'][] = [
                    'elapsed' => (float) $cpMatch[1],
                    'name' => trim($cpMatch[2]),
                    'delta' => (float) $cpMatch[3],
                ];
            }

            // Extract sections (format: "section_name   123.45 ms  12.3%")
            if (preg_match('/║\s*(\w+)\s+([\d.]+)\s*ms\s+([\d.]+)%/', $boxLine, $secMatch)) {
                $boxData['sections'][$secMatch[1]] = [
                    'duration' => (float) $secMatch[2],
                    'percent' => (float) $secMatch[3],
                ];
            }

            // Extract DB queries info
            if (preg_match('/DB Queries:\s*(\d+).*Total DB Time:\s*([\d.]+)\s*ms/', $boxLine, $dbMatch)) {
                $boxData['db_count'] = (int) $dbMatch[1];
                $boxData['db_time'] = (float) $dbMatch[2];
            }

            // End of box
            if (str_contains($boxLine, '╚')) {
                break;
            }
        }

        return [
            'timestamp' => $timestamp,
            'type' => 'TIMELINE',
            'label' => 'Request Timeline',
            'timeline_data' => $boxData,
            'raw' => $line,
        ];
    }

    /**
     * Extract metrics from box-formatted log entries
     */
    protected function extractBoxMetrics(string $startLine, array $allLines, int $startIndex): ?array
    {
        $metrics = [];

        for ($i = $startIndex; $i < min($startIndex + 25, count($allLines)); $i++) {
            $line = $allLines[$i];

            // Look for metric lines with "ms" values (handle both formats)
            // Format 1: "║ Label:                        123.45 ms ║"
            // Format 2: "║ Label:                   123.45 ms ║"
            if (preg_match('/║\s*([^:]+):\s+([\d.]+)\s*ms\s*║/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = (float) $matches[2];
                $metrics[$key] = $value;
            }

            // Check for route
            if (preg_match('/║\s*Route:\s*(\S+)/', $line, $matches)) {
                $metrics['route'] = trim($matches[1]);
            }

            // End of box
            if (str_contains($line, '╚')) {
                break;
            }
        }

        return !empty($metrics) ? $metrics : null;
    }

    /**
     * Display profiling entries in a formatted way
     */
    protected function displayProfilingEntries(array $entries): void
    {
        $lastTimestamp = null;
        $requestGroup = [];

        foreach ($entries as $entry) {
            // Group entries by timestamp (within 2 seconds)
            if ($lastTimestamp && strtotime($entry['timestamp']) - strtotime($lastTimestamp) > 2) {
                $this->displayRequestGroup($requestGroup);
                $requestGroup = [];
            }

            $requestGroup[] = $entry;
            $lastTimestamp = $entry['timestamp'];
        }

        // Display last group
        if (!empty($requestGroup)) {
            $this->displayRequestGroup($requestGroup);
        }
    }

    /**
     * Display a group of entries from the same request
     */
    protected function displayRequestGroup(array $entries): void
    {
        if (empty($entries)) return;

        $timestamp = $entries[0]['timestamp'];
        $route = null;
        $totalTime = null;

        // Find route and total time from entries
        foreach ($entries as $entry) {
            if (isset($entry['box']['route'])) {
                $route = $entry['box']['route'];
            }
            if (isset($entry['box']['TOTAL FROM LARAVEL_START'])) {
                $totalTime = $entry['box']['TOTAL FROM LARAVEL_START'];
            }
            // Check new profile format
            if (isset($entry['profile_data']['route'])) {
                $route = $entry['profile_data']['route'];
            }
            if (isset($entry['profile_data']['total_ms'])) {
                $totalTime = $entry['profile_data']['total_ms'];
            }
            if (isset($entry['timeline_data']['route'])) {
                $route = $entry['timeline_data']['route'];
            }
            if (isset($entry['timeline_data']['total'])) {
                $totalTime = $entry['timeline_data']['total'];
            }
        }

        // Determine total color
        $totalColor = $totalTime > 500 ? 'red' : ($totalTime > 300 ? 'yellow' : 'green');

        $this->line("╔══════════════════════════════════════════════════════════════════╗");
        $this->line(sprintf("║ <fg=cyan>%s</>  Route: <fg=yellow>%-28s</>    ║", $timestamp, substr($route ?? 'N/A', 0, 28)));
        if ($totalTime) {
            $this->line(sprintf("║ <fg=white;bold>TOTAL: <fg=%s;bold>%6.0f ms</>                                            ║", $totalColor, $totalTime));
        }
        $this->line("╠══════════════════════════════════════════════════════════════════╣");

        // Collect impersonate levels
        $impersonateIndex = 0;
        $levelNames = ['APP', 'CENTRAL_TENANT', 'TENANT'];

        foreach ($entries as $entry) {
            // Handle new JSON profile format
            if ($entry['type'] === 'PROFILE_JSON' && isset($entry['profile_data'])) {
                $this->displayJsonProfile($entry['profile_data']);
                continue;
            }

            // Handle timeline format
            if ($entry['type'] === 'TIMELINE' && isset($entry['timeline_data'])) {
                $this->displayTimeline($entry['timeline_data']);
                continue;
            }

            // Handle accessOnLogin Breakdown
            if (isset($entry['data']) && str_contains($entry['type'], 'accessOnLogin Breakdown')) {
                $this->line("║ <fg=white;bold>ACCESS ON LOGIN BREAKDOWN:</>                                     ║");
                foreach ($entry['data'] as $key => $value) {
                    $color = $value > 100 ? 'red' : ($value > 50 ? 'yellow' : 'green');
                    $marker = in_array($key, ['api_access_init', 'onLogin', 'service_cache', 'total']) ? '★' : ' ';
                    $this->line(sprintf("║ %s ├─ %-26s <fg=%s>%10.2f ms</>            ║", $marker, $key, $color, $value));
                }
                $this->line("║                                                                  ║");
                continue;
            }

            // Handle onLogin Breakdown
            if (isset($entry['data']) && str_contains($entry['type'], 'onLogin Breakdown')) {
                $this->line("║ <fg=white;bold>ON LOGIN BREAKDOWN:</>                                            ║");
                foreach ($entry['data'] as $key => $value) {
                    $color = $value > 50 ? 'red' : ($value > 20 ? 'yellow' : 'green');
                    $marker = in_array($key, ['tenantImpersonate', 'load_user_relations']) ? '★' : ' ';
                    $this->line(sprintf("║ %s ├─ %-26s <fg=%s>%10.2f ms</>            ║", $marker, $key, $color, $value));
                }
                $this->line("║                                                                  ║");
                continue;
            }

            // Handle ApiAccess::accessOnLogin Breakdown
            if (isset($entry['data']) && str_contains($entry['type'], 'ApiAccess::accessOnLogin')) {
                $this->line("║ <fg=white;bold>API ACCESS LOGIN BREAKDOWN:</>                                    ║");
                foreach ($entry['data'] as $key => $value) {
                    $color = $value > 100 ? 'red' : ($value > 50 ? 'yellow' : 'green');
                    $marker = in_array($key, ['token_schema_validation', 'callback']) ? '★' : ' ';
                    $this->line(sprintf("║ %s ├─ %-26s <fg=%s>%10.2f ms</>            ║", $marker, $key, $color, $value));
                }
                $this->line("║                                                                  ║");
                continue;
            }

            // Handle Token::handle Breakdown
            if (isset($entry['data']) && str_contains($entry['type'], 'Token::handle')) {
                $this->line("║ <fg=white;bold>TOKEN SCHEMA BREAKDOWN:</>                                        ║");
                foreach ($entry['data'] as $key => $value) {
                    $color = $value > 50 ? 'red' : ($value > 20 ? 'yellow' : 'green');
                    $marker = in_array($key, ['authorizing_handle', 'tokenable_load']) ? '★' : ' ';
                    $this->line(sprintf("║ %s ├─ %-26s <fg=%s>%10.2f ms</>            ║", $marker, $key, $color, $value));
                }
                $this->line("║                                                                  ║");
                continue;
            }

            // Handle JWTTokenValidator breakdowns
            if (isset($entry['data']) && str_contains($entry['type'], 'JWTTokenValidator')) {
                $label = str_contains($entry['type'], 'tokenValidator') ? 'TOKEN VALIDATOR BREAKDOWN:' : 'JWT VALIDATOR BREAKDOWN:';
                $this->line("║ <fg=white;bold>{$label}</>                                   ║");
                foreach ($entry['data'] as $key => $value) {
                    $color = $value > 50 ? 'red' : ($value > 20 ? 'yellow' : 'green');
                    $marker = in_array($key, ['user_find', 'auth_login', 'auth_attempt']) ? '★' : ' ';
                    $this->line(sprintf("║ %s ├─ %-26s <fg=%s>%10.2f ms</>            ║", $marker, $key, $color, $value));
                }
                $this->line("║                                                                  ║");
                continue;
            }

            if (isset($entry['data']) && str_contains($entry['type'], 'impersonate')) {
                if (str_contains($entry['type'], 'tenantImpersonate')) {
                    // This is the summary
                    $this->line("║ <fg=white;bold>TENANT IMPERSONATE SUMMARY:</>                                   ║");
                    foreach ($entry['data'] as $key => $value) {
                        $color = $value > 50 ? 'red' : ($value > 20 ? 'yellow' : 'green');
                        $this->line(sprintf("║   ├─ %-28s <fg=%s>%10.2f ms</>            ║", $key, $color, $value));
                    }
                    $this->line("║                                                                  ║");
                } else {
                    // Individual impersonate level
                    $levelName = $levelNames[$impersonateIndex] ?? "LEVEL_{$impersonateIndex}";
                    $hasTenancy = isset($entry['data']['tenancy_init']);
                    $skipLabel = $hasTenancy ? '' : ' <fg=gray>(skip tenancy)</>';

                    $this->line(sprintf("║ <fg=cyan>%d. %s</>%s", $impersonateIndex + 1, $levelName, $skipLabel));

                    foreach ($entry['data'] as $key => $value) {
                        $color = $value > 50 ? 'red' : ($value > 20 ? 'yellow' : 'green');
                        $prefix = $key === 'tenancy_init' ? '★' : ' ';
                        $this->line(sprintf("║   %s %-28s <fg=%s>%10.2f ms</>            ║", $prefix, $key, $color, $value));
                    }
                    $impersonateIndex++;
                }
            } elseif (isset($entry['box'])) {
                // Box metrics (ApiAccessProfile) - show at the end
                $this->line("║ <fg=white;bold>API ACCESS MIDDLEWARE:</>                                        ║");

                // Define display order and labels
                $displayOrder = [
                    'Bootstrap (providers + autoload)' => 'Bootstrap',
                    'MicroTenant::accessOnLogin' => 'accessOnLogin',
                    'Tenant/Workspace Setup' => 'Tenant Setup',
                    'Controller + Response' => 'Controller+Response',
                ];

                foreach ($displayOrder as $key => $label) {
                    if (isset($entry['box'][$key])) {
                        $value = $entry['box'][$key];
                        $color = $value > 100 ? 'red' : ($value > 50 ? 'yellow' : 'green');
                        $marker = ($key === 'MicroTenant::accessOnLogin') ? '★' : ' ';
                        $this->line(sprintf("║ %s ├─ %-25s <fg=%s>%10.2f ms</>            ║", $marker, $label, $color, $value));
                    }
                }

                // Show any remaining metrics not in displayOrder
                foreach ($entry['box'] as $key => $value) {
                    if ($key === 'route' || $key === 'TOTAL FROM LARAVEL_START') continue;
                    if (isset($displayOrder[$key])) continue;
                    $color = $value > 100 ? 'red' : ($value > 50 ? 'yellow' : 'green');
                    $this->line(sprintf("║   ├─ %-25s <fg=%s>%10.2f ms</>            ║", $key, $color, $value));
                }
            }
        }

        $this->line("╚══════════════════════════════════════════════════════════════════╝");
        $this->newLine();
    }

    /**
     * Display JSON profile data
     */
    protected function displayJsonProfile(array $data): void
    {
        $status = $data['status'] ?? 'OK';
        $statusColor = match($status) {
            'SLOW' => 'red',
            'WARN' => 'yellow',
            default => 'green',
        };

        $this->line(sprintf("║ <fg=white;bold>REQUEST PROFILE</> [<fg=%s>%s</>]                                        ║", $statusColor, $status));
        $this->line("║                                                                  ║");

        // Show sections
        if (!empty($data['sections'])) {
            $this->line("║ <fg=cyan>SECTIONS:</>                                                       ║");
            foreach ($data['sections'] as $name => $duration) {
                $color = $duration > 100 ? 'red' : ($duration > 50 ? 'yellow' : 'green');
                $this->line(sprintf("║   ├─ %-28s <fg=%s>%10.2f ms</>            ║", $name, $color, $duration));
            }
        }

        // Show timeline
        if (!empty($data['timeline'])) {
            $this->line("║                                                                  ║");
            $this->line("║ <fg=cyan>TIMELINE:</>                                                       ║");
            foreach ($data['timeline'] as $cp) {
                $this->line(sprintf("║   %6.1f ms │ %-40s           ║", $cp['at_ms'], $cp['name']));
            }
        }

        // Show DB info
        if (isset($data['db_queries'])) {
            $this->line("║                                                                  ║");
            $dbColor = $data['db_queries'] > 20 ? 'red' : ($data['db_queries'] > 10 ? 'yellow' : 'green');
            $this->line(sprintf("║ <fg=cyan>DATABASE:</>  <fg=%s>%d queries</> in <fg=yellow>%.1f ms</>                           ║",
                $dbColor, $data['db_queries'], $data['db_time_ms'] ?? 0));
        }

        // Show slow queries
        if (!empty($data['slow_queries'])) {
            $this->line("║ <fg=cyan>SLOW QUERIES:</>                                                   ║");
            foreach ($data['slow_queries'] as $q) {
                $this->line(sprintf("║   [%5.1f ms] %-50s ║", $q['time_ms'], substr($q['sql'], 0, 50)));
            }
        }
    }

    /**
     * Display timeline data
     */
    protected function displayTimeline(array $data): void
    {
        // Show checkpoints
        if (!empty($data['checkpoints'])) {
            $this->line("║ <fg=cyan>TIMELINE CHECKPOINTS:</>                                          ║");
            foreach ($data['checkpoints'] as $cp) {
                $deltaColor = $cp['delta'] > 50 ? 'red' : ($cp['delta'] > 20 ? 'yellow' : 'green');
                $this->line(sprintf("║ %6.1f ms │ %-25s │ <fg=%s>+%6.1f ms</>       ║",
                    $cp['elapsed'], substr($cp['name'], 0, 25), $deltaColor, $cp['delta']));
            }
        }

        // Show sections
        if (!empty($data['sections'])) {
            $this->line("║                                                                  ║");
            $this->line("║ <fg=cyan>SECTIONS:</>                                                       ║");
            foreach ($data['sections'] as $name => $info) {
                $color = $info['duration'] > 100 ? 'red' : ($info['duration'] > 50 ? 'yellow' : 'green');
                $bar = str_repeat('▓', min(10, (int)($info['percent'] / 10)));
                $this->line(sprintf("║   %-20s <fg=%s>%8.2f ms</> %5.1f%% %-10s     ║",
                    $name, $color, $info['duration'], $info['percent'], $bar));
            }
        }

        // Show DB info
        if (isset($data['db_count'])) {
            $this->line("║                                                                  ║");
            $dbColor = $data['db_count'] > 20 ? 'red' : ($data['db_count'] > 10 ? 'yellow' : 'green');
            $this->line(sprintf("║ <fg=cyan>DATABASE:</>  <fg=%s>%d queries</> in <fg=yellow>%.1f ms</>                           ║",
                $dbColor, $data['db_count'], $data['db_time'] ?? 0));
        }
    }

    /**
     * Display a single log line with formatting
     */
    protected function displayLogLine(string $line): void
    {
        // Extract timestamp
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $timestamp = $matches[1];
        } else {
            $timestamp = date('Y-m-d H:i:s');
        }

        // Extract JSON data
        if (preg_match('/\{[^}]+\}/', $line, $jsonMatches)) {
            $data = json_decode($jsonMatches[0], true);
            if ($data) {
                $type = 'Profile';
                if (str_contains($line, 'impersonate')) {
                    if (preg_match('/impersonate (\w+)/', $line, $typeMatches)) {
                        $type = $typeMatches[1];
                    }
                }

                $this->line("<fg=gray>[{$timestamp}]</> <fg=cyan>{$type}</>");
                foreach ($data as $key => $value) {
                    $color = $value > 50 ? 'red' : ($value > 20 ? 'yellow' : 'green');
                    $this->line(sprintf("  %-25s <fg=%s>%8.2f ms</>", $key . ':', $color, $value));
                }
                $this->newLine();
                return;
            }
        }

        // Box format (ApiAccessProfile)
        if (str_contains($line, 'TOTAL FROM LARAVEL_START')) {
            if (preg_match('/([\d.]+)\s*ms/', $line, $matches)) {
                $total = (float) $matches[1];
                $color = $total > 500 ? 'red' : ($total > 200 ? 'yellow' : 'green');
                $this->line("<fg=gray>[{$timestamp}]</> <fg=cyan>Total Request:</> <fg={$color}>{$total} ms</>");
                $this->newLine();
            }
        }
    }
}
