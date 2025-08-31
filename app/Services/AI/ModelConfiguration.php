<?php

namespace App\Services\AI;

class ModelConfiguration
{
    public readonly string $name;

    public readonly string $provider;

    public readonly string $type;

    public readonly float $inputCostPerMToken;

    public readonly float $outputCostPerMToken;

    public readonly int $maxTokens;

    public readonly int $contextWindow;

    public readonly array $supportedTasks;

    public readonly array $capabilities;

    public readonly float $qualityRating;

    public readonly float $speedRating;

    public readonly float $costEfficiency;

    public readonly float $availability;

    public readonly array $features;

    public function __construct(array $config)
    {
        $this->name = $config['name'];
        $this->provider = $config['provider'];
        $this->type = $config['type'];
        $this->inputCostPerMToken = $config['inputCostPerMToken'];
        $this->outputCostPerMToken = $config['outputCostPerMToken'];
        $this->maxTokens = $config['maxTokens'];
        $this->contextWindow = $config['contextWindow'];
        $this->supportedTasks = $config['supportedTasks'];
        $this->capabilities = $config['capabilities'];
        $this->qualityRating = $config['qualityRating'];
        $this->speedRating = $config['speedRating'];
        $this->costEfficiency = $config['costEfficiency'];
        $this->availability = $config['availability'];
        $this->features = $config['features'];
    }

    /**
     * Calculate estimated cost for a request
     */
    public function estimateCost(int $inputTokens, int $outputTokens): float
    {
        $inputCost = ($inputTokens / 1000000) * $this->inputCostPerMToken;
        $outputCost = ($outputTokens / 1000000) * $this->outputCostPerMToken;

        return $inputCost + $outputCost;
    }

    /**
     * Check if model supports a specific task
     */
    public function supportsTask(string $task): bool
    {
        return in_array($task, $this->supportedTasks);
    }

    /**
     * Check if model has a specific capability
     */
    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities);
    }

    /**
     * Check if model has a specific feature
     */
    public function hasFeature(string $feature): bool
    {
        return isset($this->features[$feature]) && $this->features[$feature];
    }

    /**
     * Get optimal parameters for this model
     */
    public function getOptimalParameters(string $task, array $options = []): array
    {
        $baseParams = [
            'model' => $this->name,
            'max_tokens' => $this->maxTokens,
        ];

        // Task-specific optimizations
        switch ($task) {
            case 'receipt':
                $baseParams['temperature'] = 0.1;
                $baseParams['max_tokens'] = min(2048, $this->maxTokens);
                break;

            case 'document':
                $baseParams['temperature'] = 0.2;
                $baseParams['max_tokens'] = min(3000, $this->maxTokens);
                break;

            case 'summary':
                $baseParams['temperature'] = 0.3;
                $baseParams['max_tokens'] = min(500, $this->maxTokens);
                break;

            case 'classification':
                $baseParams['temperature'] = 0.1;
                $baseParams['max_tokens'] = min(50, $this->maxTokens);
                break;

            default:
                $baseParams['temperature'] = 0.2;
        }

        // Provider-specific parameters
        if ($this->provider === 'anthropic') {
            // Anthropic-specific parameters
            if ($this->hasFeature('extended_context')) {
                $baseParams['headers'] = ['anthropic-beta' => 'context-1m-2025-08-07'];
            }
        }

        // Override with user options
        return array_merge($baseParams, $options);
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'provider' => $this->provider,
            'type' => $this->type,
            'input_cost_per_mtoken' => $this->inputCostPerMToken,
            'output_cost_per_mtoken' => $this->outputCostPerMToken,
            'max_tokens' => $this->maxTokens,
            'context_window' => $this->contextWindow,
            'supported_tasks' => $this->supportedTasks,
            'capabilities' => $this->capabilities,
            'quality_rating' => $this->qualityRating,
            'speed_rating' => $this->speedRating,
            'cost_efficiency' => $this->costEfficiency,
            'availability' => $this->availability,
            'features' => $this->features,
        ];
    }
}
