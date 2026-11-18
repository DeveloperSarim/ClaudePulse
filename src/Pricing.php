<?php
declare(strict_types=1);

namespace ClaudePulse;

class Pricing
{
    /**
     * Pricing table per million tokens ($ USD)
     * Format: [base_input, output, cache_creation (write), cache_read]
     */
    private static array $rates = [
        // Claude 5 Series & Fable (Frontier Agentic Reasoning)
        'claude-fable-5-2'           => [3.00, 15.00, 3.75, 0.30],
        'claude-5-2-fable'           => [3.00, 15.00, 3.75, 0.30],
        'claude-fable-5-1'           => [3.00, 15.00, 3.75, 0.30],
        'claude-5-fable'             => [3.00, 15.00, 3.75, 0.30],
        'claude-5-2-fable-latest'    => [3.00, 15.00, 3.75, 0.30],

        'claude-opus-5-5'            => [15.00, 75.00, 18.75, 1.50],
        'claude-5-5-opus'            => [15.00, 75.00, 18.75, 1.50],
        'claude-opus-5'              => [15.00, 75.00, 18.75, 1.50],
        'claude-5-opus'              => [15.00, 75.00, 18.75, 1.50],

        'claude-sonnet-5-5'          => [3.00, 15.00, 3.75, 0.30],
        'claude-5-5-sonnet'          => [3.00, 15.00, 3.75, 0.30],
        'claude-sonnet-5'            => [3.00, 15.00, 3.75, 0.30],
        'claude-5-sonnet'            => [3.00, 15.00, 3.75, 0.30],

        'claude-haiku-5-5'           => [0.80, 4.00, 1.00, 0.08],
        'claude-5-5-haiku'           => [0.80, 4.00, 1.00, 0.08],
        'claude-haiku-5'             => [0.80, 4.00, 1.00, 0.08],
        'claude-5-haiku'             => [0.80, 4.00, 1.00, 0.08],

        // Claude 4.6 & 4.5 Series (Frontier Coding & Engineering)
        'claude-opus-4-6'            => [15.00, 75.00, 18.75, 1.50],
        'claude-opus-4-5'            => [15.00, 75.00, 18.75, 1.50],
        'claude-opus-4-5-20251101'   => [15.00, 75.00, 18.75, 1.50],
        'claude-sonnet-4-6'          => [3.00, 15.00, 3.75, 0.30],
        'claude-sonnet-4-5'          => [3.00, 15.00, 3.75, 0.30],
        'claude-haiku-4-5'           => [0.80, 4.00, 1.00, 0.08],
        'claude-haiku-4-5-20251001'  => [0.80, 4.00, 1.00, 0.08],

        // Claude 3.7 Series (Hybrid Reasoning & Extended Thinking)
        'claude-3-7-sonnet'          => [3.00, 15.00, 3.75, 0.30],
        'claude-3-7-sonnet-latest'   => [3.00, 15.00, 3.75, 0.30],
        'claude-3-7-sonnet-20250219' => [3.00, 15.00, 3.75, 0.30],

        // Claude 3.5 Series (State of the art coding & multimodal)
        'claude-3-5-sonnet'          => [3.00, 15.00, 3.75, 0.30],
        'claude-3-5-sonnet-latest'   => [3.00, 15.00, 3.75, 0.30],
        'claude-3-5-sonnet-20241022' => [3.00, 15.00, 3.75, 0.30],
        'claude-3-5-sonnet-20240620' => [3.00, 15.00, 3.75, 0.30],
        'claude-3-5-haiku'           => [0.80, 4.00, 1.00, 0.08],
        'claude-3-5-haiku-latest'    => [0.80, 4.00, 1.00, 0.08],
        'claude-3-5-haiku-20241022'  => [0.80, 4.00, 1.00, 0.08],

        // Claude 3 Series (Foundational)
        'claude-3-opus'              => [15.00, 75.00, 18.75, 1.50],
        'claude-3-opus-latest'       => [15.00, 75.00, 18.75, 1.50],
        'claude-3-opus-20240229'     => [15.00, 75.00, 18.75, 1.50],
        'claude-3-sonnet'            => [3.00, 15.00, 3.75, 0.30],
        'claude-3-sonnet-20240229'   => [3.00, 15.00, 3.75, 0.30],
        'claude-3-haiku'             => [0.25, 1.25, 0.30, 0.03],
        'claude-3-haiku-latest'      => [0.25, 1.25, 0.30, 0.03],
        'claude-3-haiku-20240307'    => [0.25, 1.25, 0.30, 0.03],

        // Legacy 2.x
        'claude-2.1'                 => [8.00, 24.00, 10.00, 0.80],
        'claude-2.0'                 => [8.00, 24.00, 10.00, 0.80],
        'claude-instant-1.2'         => [0.80, 2.40, 1.00, 0.08],
    ];

    /**
     * Resolve rates for any given model string with intelligent family fallback
     * @return array [base_input, output, cache_creation, cache_read]
     */
    public static function getRatesForModel(?string $model): array
    {
        if (empty($model)) {
            return self::$rates['claude-sonnet-5'];
        }

        $modelLower = strtolower(trim($model));

        // 1. Exact match
        if (isset(self::$rates[$modelLower])) {
            return self::$rates[$modelLower];
        }

        // 2. Prefix or substring match in rate table
        foreach (self::$rates as $key => $rate) {
            if (str_starts_with($modelLower, $key) || str_contains($modelLower, $key)) {
                return $rate;
            }
        }

        // 3. Family heuristics
        if (str_contains($modelLower, 'fable')) {
            return self::$rates['claude-fable-5-2'];
        }

        if (str_contains($modelLower, 'opus')) {
            return self::$rates['claude-opus-5-5'];
        }

        if (str_contains($modelLower, 'haiku')) {
            if (str_contains($modelLower, '3-haiku') || str_contains($modelLower, '3.0')) {
                return self::$rates['claude-3-haiku'];
            }
            return self::$rates['claude-haiku-5-5'];
        }

        if (str_contains($modelLower, 'sonnet')) {
            return self::$rates['claude-sonnet-5-5'];
        }

        // Universal fallback to standard flagship Sonnet pricing ($3 in / $15 out)
        return self::$rates['claude-sonnet-5-5'];
    }

    /**
     * Calculate exact cost in USD for a request
     */
    public static function calculateCost(
        ?string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheCreationTokens = 0,
        int $cacheReadTokens = 0
    ): float {
        [$inRate, $outRate, $writeRate, $readRate] = self::getRatesForModel($model);

        $cost = ($inputTokens * ($inRate / 1_000_000))
              + ($outputTokens * ($outRate / 1_000_000))
              + ($cacheCreationTokens * ($writeRate / 1_000_000))
              + ($cacheReadTokens * ($readRate / 1_000_000));

        return round($cost, 6);
    }

    /**
     * Calculate cost savings in USD generated by prompt cache reads
     */
    public static function calculateSavings(?string $model, int $cacheReadTokens): float
    {
        if ($cacheReadTokens <= 0) {
            return 0.0;
        }

        [$inRate, , , $readRate] = self::getRatesForModel($model);

        // Savings = regular input cost minus cache read cost (90% discount)
        $diffPerToken = ($inRate - $readRate) / 1_000_000;
        return round($cacheReadTokens * $diffPerToken, 6);
    }

    /**
     * Fetch and synchronize live models directly from official Anthropic API
     */
    public static function syncFromAnthropic(): array
    {
        $db = Database::getConnection();
        $stmt = $db->query("SELECT api_key_encrypted FROM upstream_keys WHERE is_active = 1 ORDER BY priority ASC, id ASC LIMIT 1");
        $row = $stmt->fetch();
        if (!$row) {
            return self::getDefaultFallbackCatalog();
        }

        try {
            $apiKey = Crypto::decrypt($row['api_key_encrypted']);
            $ch = curl_init('https://api.anthropic.com/v1/models');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            $resp = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $resp) {
                $json = json_decode($resp, true);
                if (!empty($json['data']) && is_array($json['data'])) {
                    $models = self::enrichAnthropicModels($json['data']);
                    // Cache in database settings table
                    Database::setSetting('cached_upstream_models', json_encode($models, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    Database::setSetting('models_last_synced_at', date('Y-m-d H:i:s'));
                    return $models;
                }
            }
        } catch (\Exception $e) {
            // Silently fallback if network issue
        }

        return self::getDefaultFallbackCatalog();
    }

    /**
     * Get all active models: dynamically checks cached Anthropic models or fallback
     */
    public static function getKnownModels(bool $forceSync = false): array
    {
        if ($forceSync) {
            return self::syncFromAnthropic();
        }

        // Check if cached recently (within 4 hours)
        $cachedJson = Database::getSetting('cached_upstream_models');
        $lastSynced = Database::getSetting('models_last_synced_at');

        if (!empty($cachedJson)) {
            $cached = json_decode($cachedJson, true);
            if (is_array($cached) && !empty($cached)) {
                if (!empty($lastSynced) && (time() - strtotime($lastSynced)) < (3600 * 4)) {
                    return $cached;
                }
            }
        }

        // Try syncing from Anthropic or fallback
        $models = self::syncFromAnthropic();
        if (!empty($models)) {
            return $models;
        }

        return self::getDefaultFallbackCatalog();
    }

    /**
     * Enrich raw Anthropic model data with pricing, badges, context windows, and rates
     */
    private static function enrichAnthropicModels(array $rawModels): array
    {
        $enriched = [];
        foreach ($rawModels as $m) {
            $id = $m['id'] ?? '';
            if (empty($id)) continue;

            $name = $m['display_name'] ?? $id;
            $line = strtolower($m['line'] ?? '');
            if (empty($line)) {
                if (str_contains($id, 'fable')) $line = 'fable';
                elseif (str_contains($id, 'opus')) $line = 'opus';
                elseif (str_contains($id, 'sonnet')) $line = 'sonnet';
                elseif (str_contains($id, 'haiku')) $line = 'haiku';
                else $line = 'frontier';
            }

            [$inRate, $outRate, $writeRate, $readRate] = self::getRatesForModel($id);

            $badge = match ($line) {
                'fable'  => 'Frontier Agentic Reasoning',
                'opus'   => 'Supreme Frontier',
                'sonnet' => 'Flagship Intelligence & Coding',
                'haiku'  => 'Ultra-Fast Subagent Engine',
                default  => 'Anthropic Frontier'
            };

            if (!empty($m['capabilities']['thinking']['supported'])) {
                $badge = 'Extended Thinking & Reasoning';
            }

            $desc = match ($line) {
                'fable'  => 'Flagship autonomous agentic model for multi-step reasoning, tool orchestration, and planning',
                'opus'   => 'Most powerful frontier reasoning model for the highest complexity, proofs, and codebase synthesis',
                'sonnet' => 'The premier model for speed, coding mastery, and terminal execution in Claude Code',
                'haiku'  => 'Lightweight, ultra-fast model for high-frequency subagent swarms and continuous validation',
                default  => 'Official Anthropic model routed with prompt caching and credits compatibility'
            };

            $enriched[] = [
                'id'             => $id,
                'name'           => $name,
                'alias'          => $id,
                'badge'          => $badge,
                'tier'           => ucfirst($line) . ' Series',
                'description'    => $desc,
                'input_price'    => '$' . number_format($inRate, 2) . ' / MTok',
                'output_price'   => '$' . number_format($outRate, 2) . ' / MTok',
                'cache_read'     => '$' . number_format($readRate, 2) . ' / MTok',
                'context_window' => $m['max_input_tokens'] ?? 200000,
                'max_output'     => $m['max_tokens'] ?? 64000,
                'created_at'     => $m['created_at'] ?? date('c'),
                'capabilities'   => $m['capabilities'] ?? []
            ];
        }

        return $enriched;
    }

    /**
     * Default fallback catalog if upstream Anthropic API is not yet connected
     */
    public static function getDefaultFallbackCatalog(): array
    {
        return [
            // --- VERIFIED ACTIVE FRONTIER & STANDARD MODELS FOR PROMOTIONAL CREDITS ---
            [
                'id' => 'claude-sonnet-5-5',
                'name' => 'Claude Sonnet 5.5',
                'alias' => 'claude-5-5-sonnet',
                'badge' => 'Flagship Intelligence & Coding',
                'tier' => 'Frontier Speed & Coding Mastery',
                'description' => 'The premier balance of frontier intelligence, speed, and coding execution for Claude Code CLI and Desktop',
                'input_price' => '$3.00 / MTok',
                'output_price' => '$15.00 / MTok',
                'cache_read' => '$0.30 / MTok',
                'context_window' => 1000000,
                'max_output' => 128000,
                'created_at' => '2026-09-28T00:00:00Z'
            ],
            [
                'id' => 'claude-haiku-4-5-20251001',
                'name' => 'Claude Haiku 4.5',
                'alias' => 'claude-haiku-4-5',
                'badge' => 'High Velocity',
                'tier' => 'Budget Optimized',
                'description' => 'High speed, cost effective model for continuous validation and fast pipeline steps',
                'input_price' => '$0.80 / MTok',
                'output_price' => '$4.00 / MTok',
                'cache_read' => '$0.08 / MTok',
                'context_window' => 200000,
                'max_output' => 64000,
                'created_at' => '2025-10-15T00:00:00Z'
            ],
            [
                'id' => 'claude-haiku-5-5',
                'name' => 'Claude Haiku 5.5',
                'alias' => 'claude-5-5-haiku',
                'badge' => 'Ultra Fast Agent Engine',
                'tier' => 'Subagent Swarm & High Throughput',
                'description' => 'Lightning fast response times with frontier reasoning density for background subagents, linting, and high-frequency queries',
                'input_price' => '$0.80 / MTok',
                'output_price' => '$4.00 / MTok',
                'cache_read' => '$0.08 / MTok',
                'context_window' => 1000000,
                'max_output' => 128000,
                'created_at' => '2026-10-07T18:00:00Z'
            ],
            [
                'id' => 'claude-opus-5-5',
                'name' => 'Claude Opus 5.5',
                'alias' => 'claude-5-5-opus',
                'badge' => 'Supreme Frontier',
                'tier' => 'Highest Complexity & Synthesis',
                'description' => 'Most capable frontier architecture for massive codebase refactoring, mathematical proofs, and advanced scientific inquiry',
                'input_price' => '$15.00 / MTok',
                'output_price' => '$75.00 / MTok',
                'cache_read' => '$1.50 / MTok',
                'context_window' => 1000000,
                'max_output' => 128000,
                'created_at' => '2026-09-21T16:24:00Z'
            ],
            [
                'id' => 'claude-fable-5-2',
                'name' => 'Claude Fable 5.2',
                'alias' => 'claude-5-2-fable',
                'badge' => 'Frontier Agentic Reasoning',
                'tier' => 'Autonomous Agentic Reasoning',
                'description' => 'Flagship agentic reasoning model engineered for long-horizon autonomous planning, multi-tool orchestration, and deep logic verification',
                'input_price' => '$3.00 / MTok',
                'output_price' => '$15.00 / MTok',
                'cache_read' => '$0.30 / MTok',
                'context_window' => 1000000,
                'max_output' => 128000,
                'created_at' => '2026-11-09T18:30:00Z'
            ],
            [
                'id' => 'claude-fable-5-1',
                'name' => 'Claude Fable 5.1',
                'alias' => 'claude-5-1-fable',
                'badge' => 'Agentic Reasoning',
                'tier' => 'Autonomous Agentic Reasoning',
                'description' => 'Extended thinking and logic model with autonomous context management and structured outputs',
                'input_price' => '$3.00 / MTok',
                'output_price' => '$15.00 / MTok',
                'cache_read' => '$0.30 / MTok',
                'context_window' => 1000000,
                'max_output' => 128000,
                'created_at' => '2026-08-28T00:00:00Z'
            ],
            [
                'id' => 'claude-sonnet-4-6',
                'name' => 'Claude Sonnet 4.6',
                'alias' => 'claude-sonnet-4-6',
                'badge' => 'Terminal Coding Native',
                'tier' => 'High-Speed Agent Execution',
                'description' => 'Optimized for terminal agent execution, multi-file code generation, and rapid tool iterations',
                'input_price' => '$3.00 / MTok',
                'output_price' => '$15.00 / MTok',
                'cache_read' => '$0.30 / MTok',
                'context_window' => 1000000,
                'max_output' => 128000,
                'created_at' => '2026-02-17T00:00:00Z'
            ],
            [
                'id' => 'claude-opus-4-6',
                'name' => 'Claude Opus 4.6',
                'alias' => 'claude-opus-4-6',
                'badge' => 'Frontier Reasoning',
                'tier' => 'Deep Complex Problem Solving',
                'description' => 'Next-gen frontier reasoning model for the most demanding technical synthesis and multi-step logic',
                'input_price' => '$15.00 / MTok',
                'output_price' => '$75.00 / MTok',
                'cache_read' => '$1.50 / MTok',
                'context_window' => 1000000,
                'max_output' => 128000,
                'created_at' => '2026-02-04T00:00:00Z'
            ],
            [
                'id' => 'claude-opus-4-5-20251101',
                'name' => 'Claude Opus 4.5',
                'alias' => 'claude-opus-4-5',
                'badge' => 'Frontier Reasoning',
                'tier' => 'Deep Complex Synthesis',
                'description' => 'Frontier reasoning model with 200k context window and multi-agent coordination support',
                'input_price' => '$15.00 / MTok',
                'output_price' => '$75.00 / MTok',
                'cache_read' => '$1.50 / MTok',
                'context_window' => 200000,
                'max_output' => 64000,
                'created_at' => '2025-11-24T00:00:00Z'
            ],
            [
                'id' => 'claude-3-7-sonnet-20250219',
                'name' => 'Claude 3.7 Sonnet',
                'alias' => 'claude-3-7-sonnet-latest',
                'badge' => 'Hybrid Reasoning',
                'tier' => 'Extended Thinking & Code',
                'description' => 'Hybrid reasoning model mapped seamlessly to flagship intelligence',
                'input_price' => '$3.00 / MTok',
                'output_price' => '$15.00 / MTok',
                'cache_read' => '$0.30 / MTok',
                'context_window' => 200000,
                'max_output' => 64000,
                'created_at' => '2025-02-19T00:00:00Z'
            ],
            [
                'id' => 'claude-3-5-sonnet-20241022',
                'name' => 'Claude 3.5 Sonnet',
                'alias' => 'claude-3-5-sonnet-latest',
                'badge' => 'Frontier Intelligence',
                'tier' => 'State-of-the-Art Coding',
                'description' => 'Standard frontier coding and multimodal intelligence with 200k context window',
                'input_price' => '$3.00 / MTok',
                'output_price' => '$15.00 / MTok',
                'cache_read' => '$0.30 / MTok',
                'context_window' => 200000,
                'max_output' => 64000,
                'created_at' => '2024-10-22T00:00:00Z'
            ]
        ];
    }
}
