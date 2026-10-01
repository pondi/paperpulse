<?php

namespace App\Services;

use DateTimeImmutable;

class OrganizationSummaryNormalizer
{
    public const VERSION = 1;

    public const ROLES = ['contracts', 'invoices', 'receipts', 'payslips', 'letters', 'other'];

    public function evidence(mixed $value): array
    {
        $data = is_array($value) ? $value : [];
        $property = ($data['address_kind'] ?? null) === 'property_subject' ? $this->text($data['property_address'] ?? null, 180) : null;
        $employer = $this->text($data['employer_name'] ?? null, 120);

        return [
            'subject' => $this->text($data['subject'] ?? null, 160),
            'property_address' => $property,
            'employer' => $employer ? ['name' => $employer, 'registration' => $this->text($data['employer_registration'] ?? null, 40)] : null,
            'role' => in_array($data['role'] ?? null, self::ROLES, true) ? $data['role'] : null,
            'confidence' => $this->confidence($data['confidence'] ?? null),
            'keywords' => $this->keywords($data['keywords'] ?? []),
        ];
    }

    public function normalize(string $type, array $data, ?int $entityId = null): array
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $organization = $data['organization'] ?? $metadata['organization_evidence'] ?? [];
        $evidence = $this->evidence($organization);
        $title = $data['title'] ?? $data['document_title'] ?? $data['contract_title'] ?? $data['receipt_description'] ?? $data['description'] ?? $metadata['title'] ?? null;
        $subtype = $data['document_type'] ?? $data['contract_type'] ?? $metadata['type'] ?? $type;
        $role = $evidence['role'] ?? match ($type) {
            'contract' => 'contracts', 'invoice' => 'invoices', 'receipt' => 'receipts', default => 'other',
        };
        $employer = $evidence['employer'];
        if (! $employer && $type === 'contract') {
            foreach (is_array($data['parties'] ?? null) ? $data['parties'] : [] as $party) {
                if (is_array($party) && strtolower((string) ($party['role'] ?? '')) === 'employer') {
                    $name = $this->text($party['name'] ?? null, 120);
                    if ($name) {
                        $employer = ['name' => $name, 'registration' => $this->text($party['registration_number'] ?? null, 40)];
                    }
                }
            }
        }
        $dates = [];
        $creationInfo = is_array($data['creation_info'] ?? null) ? $data['creation_info'] : ($metadata['creation_info'] ?? []);
        $creationInfo = is_array($creationInfo) ? $creationInfo : [];
        $sourceDates = is_array($data['dates'] ?? null) ? $data['dates'] : [];
        foreach (['document_date', 'creation_date', 'effective_date', 'expiry_date', 'signature_date', 'invoice_date', 'receipt_date'] as $field) {
            $value = $data[$field] ?? ($sourceDates[$field] ?? null) ?? ($creationInfo[$field] ?? null);
            if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if ($date && $date->format('Y-m-d') === $value) {
                    $dates[$field] = $value;
                }
            }
        }
        $confidence = $evidence['confidence'];
        if ($organization === [] && $employer) {
            $confidence = $this->confidence($data['confidence_score'] ?? $metadata['confidence_score'] ?? $metadata['extraction_confidence'] ?? ($data['quality']['confidence_score'] ?? null));
        }

        $tags = $data['tags'] ?? $metadata['tags'] ?? [];
        $tags = is_array($tags) ? $tags : [];

        return [
            'version' => self::VERSION,
            'document_type' => $this->text($subtype, 50) ?? $type,
            'title' => $this->text($title, 120) ?? ucfirst($type),
            'subject' => $evidence['subject'],
            'property_address' => $evidence['property_address'],
            'employer' => $employer,
            'role' => $role,
            'dates' => $dates,
            'keywords' => $this->keywords(array_merge($evidence['keywords'], $tags)),
            'confidence' => $confidence,
            'provenance' => ['source' => 'existing_extraction', 'entity_type' => $type, 'entity_id' => $entityId,
                'evidence' => $organization !== [] ? 'explicit_subject' : ($employer ? 'typed_employer_party' : 'basic_fields')],
        ];
    }

    private function text(mixed $value, int $limit): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim(preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '');
        if ($value === '' || in_array(strtolower($value), ['null', 'none', 'n/a', 'unknown'], true)) {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    private function confidence(mixed $value): float
    {
        return (is_int($value) || is_float($value)) && is_finite((float) $value) && $value >= 0 && $value <= 1 ? (float) $value : 0.0;
    }

    private function keywords(mixed $values): array
    {
        $result = [];
        foreach (is_array($values) ? $values : [] as $value) {
            $text = $this->text($value, 32);
            if ($text) {
                $result[mb_strtolower($text)] = $text;
            }
            if (count($result) >= 8) {
                break;
            }
        }

        return array_values($result);
    }
}
