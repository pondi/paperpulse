<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\File;
use App\Models\OrganizationAlias;

class PropertyGroupingService
{
    private array $summaryAddresses = [];

    public function __construct(private FolderTreeService $tree) {}

    public function normalize(string $address): string
    {
        $address = trim(preg_replace('/\s+/u', ' ', $address) ?? $address);
        $address = preg_replace('/,\s*(?:\d+\.?\s*(?:etasje|etg)|gnr\.?|bnr\.?|gårdsnummer|bruksnummer)\b.*$/iu', '', $address) ?? $address;
        $address = preg_replace('/(?<=\d)\s*(?:og|and|&|–|—|-)\s*(?=\d)/iu', '-', $address) ?? $address;

        return mb_substr(trim($address), 0, 180);
    }

    public function canonicalName(int $userId, string $address): string
    {
        $normalized = $this->normalize($address);
        $alias = OrganizationAlias::withoutGlobalScope('user')->where('user_id', $userId)->where('kind', 'property')
            ->whereIn('alias_key', [OrganizationAlias::key($address), OrganizationAlias::key($normalized)])->first();
        if ($alias && ! in_array(OrganizationAlias::identity($alias->canonical_name), [OrganizationAlias::identity($address), OrganizationAlias::identity($normalized)], true)) {
            return $alias->canonical_name;
        }
        $identity = $this->addressParts($normalized);
        if (! $identity) {
            $existing = Collection::withoutGlobalScope('user')->where('user_id', $userId)->where('folder_type', 'property')
                ->where('organization_source', 'system')->get()->first(fn (Collection $folder): bool => OrganizationAlias::key($folder->name) === OrganizationAlias::key($normalized));

            return $alias?->canonical_name ?? $existing?->name ?? $normalized;
        }
        $addresses = Collection::withoutGlobalScope('user')->where('user_id', $userId)->where('folder_type', 'property')
            ->where('organization_source', 'system')->pluck('name');
        $latestFileId = File::withoutGlobalScope('user')->where('user_id', $userId)->max('id');
        if (($this->summaryAddresses[$userId]['latest_file_id'] ?? null) !== $latestFileId) {
            $this->summaryAddresses[$userId] = ['latest_file_id' => $latestFileId, 'addresses' => File::withoutGlobalScope('user')->where('user_id', $userId)
                ->where('organization_summary->version', OrganizationSummaryNormalizer::VERSION)
                ->where('organization_summary->confidence', '>=', 0.8)
                ->whereNull('organization_summary->employer')->whereNotNull('organization_summary->property_address')
                ->distinct()->pluck('organization_summary->property_address')->all()];
        }
        $addresses = $addresses->merge($this->summaryAddresses[$userId]['addresses'] ?? [])->push($normalized);
        $matches = [];
        foreach ($addresses as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }
            $label = $this->normalize($candidate);
            $parts = $this->addressParts($label);
            if ($parts && $parts['street'] === $identity['street'] && array_diff($identity['numbers'], $parts['numbers']) === []) {
                $matches[OrganizationAlias::key($label)] = $label;
            }
        }
        $ranges = array_filter($matches, fn (string $label): bool => count($this->addressParts($label)['numbers']) > 1);

        return count($ranges) === 1 ? array_values($ranges)[0] : ($matches[OrganizationAlias::key($normalized)] ?? $normalized);
    }

    public function consolidate(Collection $target): void
    {
        if (! $this->canConsolidate($target)) {
            return;
        }
        $siblings = Collection::withoutGlobalScope('user')->where('user_id', $target->user_id)
            ->where('parent_id', $target->parent_id)->where('folder_type', 'property')->where('organization_source', 'system')
            ->whereKeyNot($target->id)->get();
        foreach ($siblings as $source) {
            if (OrganizationAlias::key($this->canonicalName($target->user_id, $source->name)) === OrganizationAlias::key($target->name)
                && $this->canConsolidate($source)) {
                $this->merge($source, $target);
            }
        }
    }

    /** @return array{street: string, numbers: list<string>}|null */
    private function addressParts(string $address): ?array
    {
        if (! preg_match('/^([\p{L}][\p{L}\s.\'-]+?)\s+(\d+[a-z]?)(?:-(\d+[a-z]?))?$/iu', $address, $matches)) {
            return null;
        }

        return ['street' => OrganizationAlias::identity($matches[1]), 'numbers' => array_values(array_unique(array_map(mb_strtolower(...), array_filter([$matches[2], $matches[3] ?? null]))))];
    }

    private function canConsolidate(Collection $root): bool
    {
        $folders = collect([$root]);
        $frontier = [$root->id];
        while ($frontier !== []) {
            $children = Collection::withoutGlobalScope('user')->where('user_id', $root->user_id)->whereIn('parent_id', $frontier)->get();
            $frontier = $children->modelKeys();
            $folders = $folders->merge($children);
        }
        foreach ($folders as $folder) {
            if ($folder->organization_source !== 'system' || $folder->is_pinned || $folder->is_archived || $this->tree->hasSharing($folder)) {
                return false;
            }
        }

        return ! File::withoutGlobalScope('user')->where('user_id', $root->user_id)->where('placement_source', 'manual')
            ->whereHas('collections', fn ($query) => $query->withoutGlobalScope('user')->whereIn('collections.id', $folders->pluck('id')))->exists();
    }

    private function merge(Collection $source, Collection $target): void
    {
        foreach ($source->children()->withoutGlobalScope('user')->where('user_id', $source->user_id)->get() as $child) {
            $destination = $this->tree->ensureFolder($source->user_id, $child->name, $target->id, $child->folder_type, 'system');
            $this->merge($child, $destination);
        }
        foreach ($source->files()->withoutGlobalScope('user')->where('files.user_id', $source->user_id)->lazyById(100, 'files.id', 'id') as $file) {
            if ($file->primary_folder_id === $source->id) {
                $this->tree->place($file, $target, 'system');
            } else {
                $target->files()->syncWithoutDetaching([$file->id]);
                $source->files()->detach($file->id);
            }
        }
        $this->tree->deleteLeaf($source);
    }
}
