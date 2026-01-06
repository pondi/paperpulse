<?php

namespace App\Services\AI\TypeClassification;

/**
 * Interface for document type classification.
 */
interface TypeClassifier
{
    /**
     * Classify a document by analyzing its content.
     *
     * @param  string  $fileUri  Gemini Files API URI
     * @param  array  $hints  Optional hints (filename, extension, etc.)
     * @return ClassificationResult Classification result with confidence score
     */
    public function classify(string $fileUri, array $hints = []): ClassificationResult;
}
