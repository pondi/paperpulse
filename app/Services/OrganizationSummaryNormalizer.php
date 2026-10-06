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
            'collection_id' => is_int($data['collection_id'] ?? null) && $data['collection_id'] > 0 ? $data['collection_id'] : null,
            'group_path' => $this->groupPath(array_key_exists('group_path', $data) ? $data['group_path'] : []),
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
            'abstract' => $this->text($data['summary'] ?? ($metadata['content']['summary'] ?? null) ?? $data['description'] ?? $data['content'] ?? null, 400),
            'collection_id' => $evidence['collection_id'],
            'group_path' => $evidence['group_path'],
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

    /** @return list<array{kind: string, name: string, identifier: ?string, relationship: string, confidence: float}>|null */
    public function groupPath(mixed $value): ?array
    {
        if (! is_array($value) || ! array_is_list($value) || count($value) > 4) {
            return null;
        }
        $path = [];
        $identities = [];
        foreach ($value as $index => $node) {
            if (! is_array($node)) {
                return null;
            }
            $kind = $this->text($node['kind'] ?? null, 22);
            $name = $this->text($node['name'] ?? null, 180);
            $relationship = $index === 0 ? 'subject' : 'subgroup';
            if (! $kind || ! preg_match('/^[a-z][a-z_]{0,21}$/', $kind) || ! $name
                || str_contains($name, '/') || str_contains($name, chr(92)) || preg_match('/[\x00-\x1f]/', $name) || in_array($name, ['.', '..'], true)
                || ($node['relationship'] ?? null) !== $relationship) {
                return null;
            }
            $identifier = $this->text($node['identifier'] ?? null, 120);
            $identity = $kind.':'.mb_strtolower($identifier ?? $name);
            if (in_array($identity, $identities, true)) {
                return null;
            }
            $identities[] = $identity;
            $path[] = ['kind' => $kind, 'name' => $name, 'identifier' => $identifier,
                'relationship' => $relationship, 'confidence' => $this->confidence($node['confidence'] ?? null)];
        }

        return $path;
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
