<?php

namespace App\Support\Files;

use App\Domain\PlatformAccess\Models\Business;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class TenantPrivateStorage
{
    public function __construct(private readonly TenantContext $context) {}

    public function put(Business $business, string $path, string $contents): string
    {
        $key = $this->authorizedKey($business, $path);
        $this->disk()->put($key, $contents);

        return $key;
    }

    public function get(Business $business, string $path): string
    {
        return $this->disk()->get($this->authorizedKey($business, $path));
    }

    public function getStoredKey(Business $business, string $key): string
    {
        $expectedPrefix = TenantFilePath::private($business, 'files/');
        if (! str_starts_with($key, $expectedPrefix)) {
            throw new AccessDeniedHttpException('The stored file key does not belong to this tenant.');
        }
        $this->authorizedKey($business, 'files/check');

        return $this->disk()->get($key);
    }

    private function authorizedKey(Business $business, string $path): string
    {
        if (! $this->context->hasBusiness()
            || (int) $this->context->business()->getKey() !== (int) $business->getKey()
        ) {
            throw new AccessDeniedHttpException('Private tenant files require the matching explicit tenant context.');
        }

        return TenantFilePath::private($business, $path);
    }

    private function disk(): Filesystem
    {
        return Storage::disk('private');
    }
}
