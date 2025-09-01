<?php

namespace App\Services\AI;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ModelConfigurationService
{
    protected array $modelRegistry = [];

    protected array $performanceMetrics = [];

    protected array $costMetrics = [];

    public function __construct()
    {
        $this->initializeModelRegistry();
        $this->loadPerformanceMetrics();
    }

    /**
     * Get the optimal model for a specific task
     */
    public function getOptimalModel(string $task, array $requirements = []): ModelConfiguration
    {
        // Handle backwards compatibility - if task is 'general', try to infer from requirements
        if ($task === 'general' && empty($requirements)) {
            $task = 'receipt'; // Default to receipt analysis
        }

        $cacheKey = "optimal_model.{$task}.".md5(serialize($requirements));

        return Cache::remember($cacheKey, now()->addHours(1), function () use ($task, $requirements) {
            return $this->selectOptimalModel($task, $requirements);
        });
    }

    /**
     * Select optimal model based on requirements
     */
    protected function selectOptimalModel(string $task, array $requirements): ModelConfiguration
    {
        $candidates = $this->getCandidateModels($task, $requirements);

        if (empty($candidates)) {
            throw new Exception("No suitable models found for task: {$task}");
        }

        // Score candidates based on requirements
        $scoredCandidates = array_map(function ($model) use ($requirements) {
            return [
                'model' => $model,
                'score' => $this->calculateModelScore($model, $requirements),
            ];
        }, $candidates);

        // Sort by score (highest first)
        usort($scoredCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        $selectedModel = $scoredCandidates[0]['model'];

        Log::info('[ModelConfigurationService] Selected optimal model', [
            'task' => $task,
            'model' => $selectedModel->name,
            'provider' => $selectedModel->provider,
            'score' => $scoredCandidates[0]['score'],
            'candidates_evaluated' => count($candidates),
        ]);

        return $selectedModel;
    }

    /**
     * Get candidate models for a task
     */
    protected function getCandidateModels(string $task, array $requirements): array
    {
        $provider = $requirements['provider'] ?? config('ai.provider');
        $budget = $requirements['budget'] ?? 'standard';
        $quality = $requirements['quality'] ?? 'high';

        $candidates = [];

        foreach ($this->modelRegistry as $model) {
            // Filter by provider if specified
            if ($provider && $provider !== 'any' && $model->provider !== $provider) {
                continue;
            }

            // Check if model supports the task
            if (! in_array($task, $model->supportedTasks)) {
                continue;
            }

            // Check budget constraints
            if (! $this->meetsBudgetRequirement($model, $budget)) {
                continue;
            }

            // Check quality requirements
            if (! $this->meetsQualityRequirement($model, $task, $quality)) {
                continue;
            }

            // Check availability
            if (! $this->isModelAvailable($model)) {
                continue;
            }

            $candidates[] = $model;
        }

        return $candidates;
    }

    /**
     * Calculate model score based on requirements
     */
    protected function calculateModelScore(ModelConfiguration $model, array $requirements): float
    {
        $score = 0.0;
        $weights = $this->getWeights($requirements);

        // Performance score (speed + accuracy)
        $performanceScore = $this->getPerformanceScore($model, $requirements['task'] ?? 'general');
        $score += $performanceScore * $weights['performance'];

        // Cost efficiency score
        $costScore = $this->getCostEfficiencyScore($model);
        $score += $costScore * $weights['cost'];

        // Reliability score (uptime + error rate)
        $reliabilityScore = $this->getReliabilityScore($model);
        $score += $reliabilityScore * $weights['reliability'];

        // Feature compatibility score
        $featureScore = $this->getFeatureCompatibilityScore($model, $requirements);
        $score += $featureScore * $weights['features'];

        // Apply penalties for known issues
        $score *= $this->getPenaltyMultiplier($model);

        return max(0.0, min(10.0, $score)); // Normalize to 0-10 scale
    }

    /**
     * Get all available models
     */
    public function getAvailableModels(?string $provider = null): array
    {
        $models = $this->modelRegistry;

        if ($provider) {
            $models = array_filter($models, fn ($model) => $model->provider === $provider);
        }

        return array_values($models);
    }

    /**
     * Get model configuration by name
     */
    public function getModelByName(string $name): ?ModelConfiguration
    {
        foreach ($this->modelRegistry as $model) {
            if ($model->name === $name) {
                return $model;
            }
        }

        return null;
    }

    /**
     * Update model performance metrics
     */
    public function updatePerformanceMetrics(string $modelName, string $task, array $metrics): void
    {
        $key = "{$modelName}.{$task}";

        if (! isset($this->performanceMetrics[$key])) {
            $this->performanceMetrics[$key] = [];
        }

        $this->performanceMetrics[$key][] = array_merge($metrics, [
            'timestamp' => time(),
            'model' => $modelName,
            'task' => $task,
        ]);

        // Keep only last 100 metrics per model-task combination
        $this->performanceMetrics[$key] = array_slice($this->performanceMetrics[$key], -100);

        // Persist to cache
        Cache::put("model_performance.{$key}", $this->performanceMetrics[$key], now()->addDays(7));
    }

    /**
     * Initialize model registry with latest models
     */
    protected function initializeModelRegistry(): void
    {
        $this->modelRegistry = [
            // OpenAI Models (2025)
            new ModelConfiguration([
                'name' => 'gpt-4.1',
                'provider' => 'openai',
                'type' => 'chat',
                'inputCostPerMToken' => 5.0, // $5 per million tokens
                'outputCostPerMToken' => 15.0, // $15 per million tokens
                'maxTokens' => 128000,
                'contextWindow' => 128000,
                'supportedTasks' => ['receipt', 'document', 'summary', 'classification', 'entities', 'tags', 'general'],
                'capabilities' => ['structured_outputs', 'function_calling', 'json_mode'],
                'qualityRating' => 9.5,
                'speedRating' => 8.0,
                'costEfficiency' => 7.0,
                'availability' => 99.9,
                'features' => [
                    'multimodal' => false,
                    'streaming' => true,
                    'batch_api' => true,
                    'structured_outputs' => true,
                    'json_schema' => true,
                ],
            ]),

            new ModelConfiguration([
                'name' => 'gpt-4.1-mini',
                'provider' => 'openai',
                'type' => 'chat',
                'inputCostPerMToken' => 0.15, // $0.15 per million tokens
                'outputCostPerMToken' => 0.60, // $0.60 per million tokens
                'maxTokens' => 16384,
                'contextWindow' => 128000,
                'supportedTasks' => ['receipt', 'document', 'summary', 'classification', 'entities', 'tags', 'general'],
                'capabilities' => ['structured_outputs', 'function_calling', 'json_mode'],
                'qualityRating' => 8.5,
                'speedRating' => 9.5,
                'costEfficiency' => 9.5,
                'availability' => 99.9,
                'features' => [
                    'multimodal' => false,
                    'streaming' => true,
                    'batch_api' => true,
                    'structured_outputs' => true,
                    'json_schema' => true,
                ],
            ]),

            new ModelConfiguration([
                'name' => 'gpt-5',
                'provider' => 'openai',
                'type' => 'chat',
                'inputCostPerMToken' => 1.25, // $1.25 per million tokens
                'outputCostPerMToken' => 10.0, // $10 per million tokens
                'maxTokens' => 32768,
                'contextWindow' => 256000,
                'supportedTasks' => ['receipt', 'document', 'summary', 'classification', 'entities', 'tags', 'complex_analysis'],
                'capabilities' => ['structured_outputs', 'function_calling', 'json_mode', 'tool_use', 'agentic'],
                'qualityRating' => 10.0,
                'speedRating' => 7.5,
                'costEfficiency' => 8.5,
                'availability' => 99.5,
                'features' => [
                    'multimodal' => true,
                    'streaming' => true,
                    'batch_api' => true,
                    'structured_outputs' => true,
                    'json_schema' => true,
                    'tool_use' => true,
                    'web_search' => true,
                    'file_search' => true,
                ],
            ]),

            // Anthropic Models (2025)
            new ModelConfiguration([
                'name' => 'claude-3.7-sonnet',
                'provider' => 'anthropic',
                'type' => 'chat',
                'inputCostPerMToken' => 3.0, // $3 per million tokens
                'outputCostPerMToken' => 15.0, // $15 per million tokens
                'maxTokens' => 4096,
                'contextWindow' => 200000,
                'supportedTasks' => ['receipt', 'document', 'summary', 'classification', 'entities', 'tags', 'general'],
                'capabilities' => ['tool_use', 'structured_outputs'],
                'qualityRating' => 9.2,
                'speedRating' => 8.5,
                'costEfficiency' => 8.0,
                'availability' => 99.8,
                'features' => [
                    'multimodal' => true,
                    'streaming' => true,
                    'tool_use' => true,
                    'thinking_mode' => true,
                    'json_mode' => true,
                ],
            ]),

            new ModelConfiguration([
                'name' => 'claude-4-opus',
                'provider' => 'anthropic',
                'type' => 'chat',
                'inputCostPerMToken' => 15.0, // $15 per million tokens
                'outputCostPerMToken' => 75.0, // $75 per million tokens
                'maxTokens' => 4096,
                'contextWindow' => 1000000, // 1M tokens with beta header
                'supportedTasks' => ['receipt', 'document', 'summary', 'classification', 'entities', 'tags', 'complex_analysis'],
                'capabilities' => ['tool_use', 'structured_outputs', 'memory'],
                'qualityRating' => 10.0,
                'speedRating' => 7.0,
                'costEfficiency' => 6.0,
                'availability' => 99.5,
                'features' => [
                    'multimodal' => true,
                    'streaming' => true,
                    'tool_use' => true,
                    'memory_files' => true,
                    'web_search' => true,
                    'json_mode' => true,
                    'extended_context' => true,
                ],
            ]),

            new ModelConfiguration([
                'name' => 'claude-4-sonnet',
                'provider' => 'anthropic',
                'type' => 'chat',
                'inputCostPerMToken' => 3.0, // $3 per million tokens
                'outputCostPerMToken' => 15.0, // $15 per million tokens
                'maxTokens' => 8192,
                'contextWindow' => 1000000, // 1M tokens with beta header
                'supportedTasks' => ['receipt', 'document', 'summary', 'classification', 'entities', 'tags', 'coding'],
                'capabilities' => ['tool_use', 'structured_outputs'],
                'qualityRating' => 9.8,
                'speedRating' => 8.8,
                'costEfficiency' => 9.0,
                'availability' => 99.7,
                'features' => [
                    'multimodal' => true,
                    'streaming' => true,
                    'tool_use' => true,
                    'coding' => true,
                    'json_mode' => true,
                    'extended_context' => true,
                ],
            ]),
        ];
    }

    /**
     * Load performance metrics from cache
     */
    protected function loadPerformanceMetrics(): void
    {
        $this->performanceMetrics = Cache::get('model_performance_registry', []);
    }

    /**
     * Check budget requirement
     */
    protected function meetsBudgetRequirement(ModelConfiguration $model, string $budget): bool
    {
        $budgetLimits = [
            'economy' => 1.0,     // $1 per million tokens max
            'standard' => 5.0,    // $5 per million tokens max
            'premium' => 20.0,    // $20 per million tokens max
            'unlimited' => PHP_FLOAT_MAX,
        ];

        $limit = $budgetLimits[$budget] ?? $budgetLimits['standard'];
        $avgCost = ($model->inputCostPerMToken + $model->outputCostPerMToken) / 2;

        return $avgCost <= $limit;
    }

    /**
     * Check quality requirement
     */
    protected function meetsQualityRequirement(ModelConfiguration $model, string $task, string $quality): bool
    {
        $qualityMinimums = [
            'basic' => 6.0,
            'standard' => 7.5,
            'high' => 8.5,
            'premium' => 9.0,
        ];

        $minimum = $qualityMinimums[$quality] ?? $qualityMinimums['standard'];

        return $model->qualityRating >= $minimum;
    }

    /**
     * Check model availability
     */
    protected function isModelAvailable(ModelConfiguration $model): bool
    {
        // Check if model is currently available (could check API status)
        return $model->availability >= 99.0;
    }

    /**
     * Get performance score for model
     */
    protected function getPerformanceScore(ModelConfiguration $model, string $task): float
    {
        // Base score from model ratings
        $baseScore = ($model->qualityRating + $model->speedRating) / 2;

        // Adjust based on historical performance for this task
        $taskKey = "{$model->name}.{$task}";
        if (isset($this->performanceMetrics[$taskKey])) {
            $recentMetrics = array_slice($this->performanceMetrics[$taskKey], -10);
            $avgSuccess = array_sum(array_column($recentMetrics, 'success_rate')) / count($recentMetrics);
            $avgLatency = array_sum(array_column($recentMetrics, 'latency_ms')) / count($recentMetrics);

            // Factor in success rate and latency
            $performanceMultiplier = $avgSuccess * (1000 / max($avgLatency, 100)); // Favor low latency
            $baseScore *= min(1.5, $performanceMultiplier);
        }

        return min(10.0, $baseScore);
    }

    /**
     * Get cost efficiency score
     */
    protected function getCostEfficiencyScore(ModelConfiguration $model): float
    {
        return $model->costEfficiency;
    }

    /**
     * Get reliability score
     */
    protected function getReliabilityScore(ModelConfiguration $model): float
    {
        return $model->availability / 10.0; // Convert percentage to 0-10 scale
    }

    /**
     * Get feature compatibility score
     */
    protected function getFeatureCompatibilityScore(ModelConfiguration $model, array $requirements): float
    {
        $requiredFeatures = $requirements['features'] ?? [];

        if (empty($requiredFeatures)) {
            return 8.0; // Default score when no specific features required
        }

        $score = 0.0;
        foreach ($requiredFeatures as $feature) {
            if (isset($model->features[$feature]) && $model->features[$feature]) {
                $score += 10.0 / count($requiredFeatures);
            }
        }

        return $score;
    }

    /**
     * Get scoring weights based on requirements
     */
    protected function getWeights(array $requirements): array
    {
        $priority = $requirements['priority'] ?? 'balanced';

        return match ($priority) {
            'speed' => ['performance' => 0.5, 'cost' => 0.2, 'reliability' => 0.2, 'features' => 0.1],
            'quality' => ['performance' => 0.6, 'cost' => 0.1, 'reliability' => 0.2, 'features' => 0.1],
            'cost' => ['performance' => 0.2, 'cost' => 0.5, 'reliability' => 0.2, 'features' => 0.1],
            'reliability' => ['performance' => 0.2, 'cost' => 0.1, 'reliability' => 0.6, 'features' => 0.1],
            default => ['performance' => 0.35, 'cost' => 0.25, 'reliability' => 0.25, 'features' => 0.15]
        };
    }

    /**
     * Get penalty multiplier for known issues
     */
    protected function getPenaltyMultiplier(ModelConfiguration $model): float
    {
        $penalties = 1.0;

        // Apply penalties for known issues
        $knownIssues = config("ai.model_issues.{$model->name}", []);
        foreach ($knownIssues as $issue) {
            $penalties *= match ($issue['severity']) {
                'critical' => 0.5,
                'major' => 0.8,
                'minor' => 0.95,
                default => 1.0
            };
        }

        return max(0.1, $penalties);
    }
}
