<?php

namespace App\Domain\BusinessConfiguration\Services;

use App\Domain\Billing\Services\EntitlementEvaluator;
use App\Domain\BusinessConfiguration\Models\ConfigurationImport;
use App\Domain\BusinessConfiguration\Models\ConfigurationImportRow;
use App\Domain\BusinessConfiguration\Models\ImportDuplicateCandidate;
use App\Domain\BusinessConfiguration\Models\ImportedConfigurationRecord;
use App\Domain\BusinessConfiguration\Models\Service;
use App\Domain\ClientRecords\Models\Client;
use App\Domain\ClientRecords\Services\ClientIdentityService;
use App\Domain\ClientRecords\Support\ClientIdentityNormalizer;
use App\Domain\PlatformAccess\Models\Business;
use App\Domain\PlatformAccess\Models\StaffProfile;
use App\Support\Files\TenantPrivateStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConfigurationImportService
{
    private const TYPES = ['clients', 'staff', 'services', 'products'];

    private const REQUIRED = [
        'clients' => ['name'],
        'staff' => ['display_name', 'email'],
        'services' => ['name', 'price_minor', 'duration_minutes'],
        'products' => ['name', 'sku'],
    ];

    public function __construct(
        private readonly TenantPrivateStorage $storage,
        private readonly EntitlementEvaluator $entitlements,
        private readonly ClientIdentityService $clientIdentity,
    ) {}

    public function template(string $entityType): string
    {
        $this->assertType($entityType);
        $headers = match ($entityType) {
            'clients' => ['external_id', 'name', 'email', 'mobile'],
            'staff' => ['external_id', 'display_name', 'email', 'mobile', 'title'],
            'services' => ['external_id', 'name', 'price_minor', 'duration_minutes', 'currency_code'],
            'products' => ['external_id', 'name', 'sku', 'price_minor'],
        };

        return implode(',', $headers)."\n";
    }

    /**
     * @param  array<string, string>  $mapping  canonical field => CSV header
     */
    public function preview(Business $business, string $entityType, string $idempotencyKey, string $sourceName, string $csv, array $mapping): ConfigurationImport
    {
        $this->assertType($entityType);
        $hash = hash('sha256', $csv);
        $existing = ConfigurationImport::query()->forBusiness($business)->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            if (! hash_equals($existing->source_hash, $hash) || $existing->entity_type !== $entityType) {
                throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for different import content.']);
            }

            return $existing->load('rows.duplicates');
        }

        [$headers, $records] = $this->parse($csv);
        foreach (self::REQUIRED[$entityType] as $field) {
            if (! isset($mapping[$field]) || ! in_array($mapping[$field], $headers, true)) {
                throw ValidationException::withMessages(['mapping.'.$field => "Map the required {$field} field to a CSV column."]);
            }
        }

        return DB::transaction(function () use ($business, $entityType, $idempotencyKey, $sourceName, $csv, $hash, $mapping, $headers, $records): ConfigurationImport {
            $sourcePath = 'imports/'.Str::ulid().'/source.csv';
            $this->storage->put($business, $sourcePath, $csv);
            $import = ConfigurationImport::query()->create([
                'business_id' => $business->id, 'entity_type' => $entityType, 'idempotency_key' => $idempotencyKey,
                'source_name' => basename($sourceName), 'source_path' => $sourcePath, 'source_hash' => $hash,
                'mapping' => $mapping, 'status' => 'previewed', 'total_rows' => count($records),
            ]);

            $duplicateCount = 0;
            $failedCount = 0;
            foreach ($records as $offset => $record) {
                $data = [];
                foreach ($mapping as $field => $header) {
                    $data[$field] = trim((string) ($record[array_search($header, $headers, true)] ?? ''));
                }
                $errors = $this->validateRow($entityType, $data);
                $rowKey = $this->rowKey($entityType, $data, $offset + 2);
                $fingerprint = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
                $duplicates = $errors === [] ? $this->duplicates($business, $entityType, $data) : [];
                $status = $errors !== [] ? 'invalid' : ($duplicates !== [] ? 'duplicate_review' : 'valid');
                $failedCount += $errors !== [] ? 1 : 0;
                $duplicateCount += $duplicates !== [] ? 1 : 0;
                $row = ConfigurationImportRow::query()->create([
                    'business_id' => $business->id, 'configuration_import_id' => $import->id,
                    'row_number' => $offset + 2, 'row_key' => $rowKey, 'fingerprint' => $fingerprint,
                    'normalized_data' => $data, 'errors' => $errors ?: null, 'status' => $status,
                ]);
                foreach ($duplicates as $duplicate) {
                    ImportDuplicateCandidate::query()->create([
                        'business_id' => $business->id, 'configuration_import_row_id' => $row->id,
                        'candidate_type' => $duplicate['type'], 'candidate_key' => $duplicate['key'],
                        'matched_fields' => $duplicate['fields'], 'resolution' => 'review',
                    ]);
                }
            }

            $import->update(['failed_rows' => $failedCount, 'duplicate_rows' => $duplicateCount]);
            if ($failedCount > 0) {
                $this->writeErrorExport($business, $import->fresh('rows'));
            }

            return $import->fresh('rows.duplicates');
        });
    }

    /** @param array<int, string> $duplicateResolutions row ID => create|update|skip */
    public function commit(ConfigurationImport $import, array $duplicateResolutions = []): ConfigurationImport
    {
        if ($import->status === 'completed') {
            return $import->fresh('rows.duplicates');
        }

        return DB::transaction(function () use ($import, $duplicateResolutions): ConfigurationImport {
            $import = ConfigurationImport::query()->lockForUpdate()->with('rows.duplicates')->findOrFail($import->id);
            if ($import->status === 'completed') {
                return $import;
            }
            if ($import->rows->contains(fn ($row) => $row->status === 'duplicate_review' && ! in_array($duplicateResolutions[$row->id] ?? null, ['create', 'update', 'skip'], true))) {
                throw ValidationException::withMessages(['duplicates' => 'Review every duplicate candidate before committing the import.']);
            }

            $increases = $import->rows->filter(fn ($row) => in_array($row->status, ['valid', 'duplicate_review'], true) && ($duplicateResolutions[$row->id] ?? 'create') === 'create')->count();
            if ($import->entity_type === 'staff' && $increases > 0) {
                $this->entitlements->authorize($import->business, 'staff.max', 'import', $increases);
            }

            $counts = ['created_rows' => 0, 'updated_rows' => 0, 'skipped_rows' => 0, 'failed_rows' => 0];
            $import->update(['status' => 'processing', 'started_at' => now()]);
            foreach ($import->rows as $row) {
                if ($row->status === 'invalid') {
                    $counts['failed_rows']++;
                    $row->update(['result_action' => 'failed']);

                    continue;
                }
                $requested = $duplicateResolutions[$row->id] ?? null;
                if ($requested === 'skip') {
                    $counts['skipped_rows']++;
                    $row->update(['status' => 'completed', 'result_action' => 'skipped']);
                    $row->duplicates()->update(['resolution' => 'skip']);

                    continue;
                }

                $record = ImportedConfigurationRecord::query()->forBusiness($import->business_id)
                    ->where('entity_type', $import->entity_type)->where('row_key', $row->row_key)->first();
                if ($record && $record->fingerprint === $row->fingerprint) {
                    $counts['skipped_rows']++;
                    $row->update(['status' => 'completed', 'result_action' => 'skipped']);

                    continue;
                }
                $action = $record ? 'updated' : 'created';
                ImportedConfigurationRecord::query()->updateOrCreate(
                    ['business_id' => $import->business_id, 'entity_type' => $import->entity_type, 'row_key' => $row->row_key],
                    ['fingerprint' => $row->fingerprint, 'data' => $row->normalized_data],
                );
                $this->projectConfigurationRecord($import->business, $import->entity_type, $row->normalized_data, $row->row_key, $requested, $row);
                $counts[$action.'_rows']++;
                $row->update(['status' => 'completed', 'result_action' => $action]);
                if ($requested) {
                    $row->duplicates()->update(['resolution' => $requested]);
                }
            }

            $import->update([...$counts, 'status' => 'completed', 'completed_at' => now()]);

            return $import->fresh('rows.duplicates');
        }, 3);
    }

    /** @return array{0:list<string>,1:list<list<string>>} */
    private function parse(string $csv): array
    {
        if ($csv === '' || strlen($csv) > 10 * 1024 * 1024 || ! mb_check_encoding($csv, 'UTF-8') || str_contains($csv, "\0")) {
            throw ValidationException::withMessages(['file' => 'Upload a non-empty UTF-8 CSV file no larger than 10 MB.']);
        }
        $lines = preg_split('/\r\n|\n|\r/', trim($csv));
        $rows = array_map(fn ($line) => str_getcsv($line, ',', '"', '\\'), $lines ?: []);
        $headers = array_map(fn ($value) => trim((string) $value), array_shift($rows) ?? []);
        if ($headers === [] || count($headers) !== count(array_unique($headers)) || in_array('', $headers, true)) {
            throw ValidationException::withMessages(['file' => 'CSV headers must be present, unique, and non-empty.']);
        }
        foreach ($rows as $index => $row) {
            if (count($row) !== count($headers)) {
                throw ValidationException::withMessages(['file' => 'CSV row '.($index + 2).' has a different number of columns than the header.']);
            }
        }

        return [$headers, array_values(array_filter($rows, fn ($row) => collect($row)->contains(fn ($value) => trim((string) $value) !== '')))];
    }

    /** @param array<string, string> $data @return list<string> */
    private function validateRow(string $type, array $data): array
    {
        $errors = [];
        foreach (self::REQUIRED[$type] as $field) {
            if (($data[$field] ?? '') === '') {
                $errors[] = "{$field} is required";
            }
        }
        if (($data['email'] ?? '') !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'email is invalid';
        }
        foreach (['price_minor', 'duration_minutes'] as $integerField) {
            if (($data[$integerField] ?? '') !== '' && filter_var($data[$integerField], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false) {
                $errors[] = "{$integerField} must be a non-negative integer";
            }
        }

        return $errors;
    }

    /** @param array<string, string> $data */
    private function rowKey(string $type, array $data, int $row): string
    {
        return Str::lower($data['external_id'] ?? $data['email'] ?? $data['sku'] ?? $data['name'] ?? $data['display_name'] ?? "row-{$row}");
    }

    /** @param array<string, string> $data @return list<array{type:string,key:string,fields:list<string>}> */
    private function duplicates(Business $business, string $type, array $data): array
    {
        if ($type === 'clients') {
            $email = ClientIdentityNormalizer::email($data['email'] ?? null);
            $mobile = ClientIdentityNormalizer::mobile($data['mobile'] ?? null);
            $candidate = Client::query()->forBusiness($business)->where('status', 'active')->where(function ($query) use ($email, $mobile): void {
                if ($mobile) {
                    $query->where('normalized_mobile', $mobile);
                }
                if ($email) {
                    $mobile ? $query->orWhere('normalized_email', $email) : $query->where('normalized_email', $email);
                }
                if (! $mobile && ! $email) {
                    $query->whereRaw('1 = 0');
                }
            })->first();
            if ($candidate) {
                $fields = collect([
                    'email' => $email && $email === $candidate->normalized_email,
                    'mobile' => $mobile && $mobile === $candidate->normalized_mobile,
                ])->filter()->keys()->all();

                return [['type' => Client::class, 'key' => $candidate->public_id, 'fields' => $fields]];
            }
        }
        if ($type === 'staff' && ($data['email'] ?? '') !== '') {
            $candidate = StaffProfile::query()->forBusiness($business)->whereRaw('lower(email) = ?', [Str::lower($data['email'])])->first();

            return $candidate ? [['type' => StaffProfile::class, 'key' => (string) $candidate->public_id, 'fields' => ['email']]] : [];
        }
        if ($type === 'services') {
            $candidate = Service::query()->forBusiness($business)->whereRaw('lower(name) = ?', [Str::lower($data['name'])])->first();

            return $candidate ? [['type' => Service::class, 'key' => (string) $candidate->public_id, 'fields' => ['name']]] : [];
        }
        $candidates = ImportedConfigurationRecord::query()->forBusiness($business)->where('entity_type', $type)->get();
        foreach ($candidates as $candidate) {
            $matched = collect(['email', 'mobile', 'sku'])->filter(fn ($field) => ($data[$field] ?? '') !== '' && Str::lower((string) ($candidate->data[$field] ?? '')) === Str::lower($data[$field]))->values()->all();
            if ($matched !== []) {
                return [['type' => ImportedConfigurationRecord::class, 'key' => (string) $candidate->id, 'fields' => $matched]];
            }
        }

        return [];
    }

    /** @param array<string, string> $data */
    private function projectConfigurationRecord(Business $business, string $type, array $data, string $rowKey, ?string $resolution = null, ?ConfigurationImportRow $row = null): void
    {
        if ($type === 'clients') {
            $candidateKey = $row?->duplicates->firstWhere('candidate_type', Client::class)?->candidate_key;
            $client = $resolution === 'update' && $candidateKey
                ? Client::query()->forBusiness($business)->where('public_id', $candidateKey)->firstOrFail()
                : Client::query()->create([
                    'business_id' => $business->id,
                    'name' => $data['name'],
                    'normalized_name' => ClientIdentityNormalizer::name($data['name']),
                    'email' => $data['email'] ?? null,
                    'normalized_email' => ClientIdentityNormalizer::email($data['email'] ?? null),
                    'mobile' => $data['mobile'] ?? null,
                    'normalized_mobile' => ClientIdentityNormalizer::mobile($data['mobile'] ?? null),
                    'communication_preferences' => [],
                    'preferences' => [],
                ]);
            if ($resolution === 'update' && $candidateKey) {
                $client = $this->clientIdentity->updateProfile($client, [
                    'name' => $data['name'], 'email' => $data['email'] ?? null, 'mobile' => $data['mobile'] ?? null,
                ], $client->version, 'Reviewed client import update.');
            }
            $this->clientIdentity->detectDuplicates($client);
        }
        if ($type === 'staff') {
            StaffProfile::query()->updateOrCreate(
                ['business_id' => $business->id, 'email' => Str::lower($data['email'])],
                ['display_name' => $data['display_name'], 'mobile' => $data['mobile'] ?? null, 'title' => $data['title'] ?? null, 'status' => 'active'],
            );
        }
        if ($type === 'services') {
            Service::query()->updateOrCreate(
                ['business_id' => $business->id, 'name' => $data['name']],
                [
                    'kind' => 'service', 'price_minor' => (int) $data['price_minor'],
                    'duration_minutes' => (int) $data['duration_minutes'], 'currency_code' => ($data['currency_code'] ?? '') ?: ($business->currency_code ?? 'USD'),
                    'tax_inclusive' => $business->tax_posture === 'inclusive', 'is_active' => true, 'online_visible' => false,
                ],
            );
        }
    }

    private function writeErrorExport(Business $business, ConfigurationImport $import): void
    {
        $csv = "row,status,errors\n";
        foreach ($import->rows->where('status', 'invalid') as $row) {
            $csv .= $row->row_number.',invalid,"'.str_replace('"', '""', implode('; ', $row->errors ?? []))."\"\n";
        }
        $path = 'imports/'.$import->public_id.'/errors.csv';
        $this->storage->put($business, $path, $csv);
        $import->update(['error_export_path' => $path]);
    }

    private function assertType(string $type): void
    {
        if (! in_array($type, self::TYPES, true)) {
            throw ValidationException::withMessages(['entity_type' => 'Import type must be clients, staff, services, or products.']);
        }
    }
}
