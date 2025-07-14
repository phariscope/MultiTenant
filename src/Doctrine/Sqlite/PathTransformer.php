<?php

namespace Phariscope\MultiTenant\Doctrine\Sqlite;

class PathTransformer
{
    public function __construct(private ?string $dataPath = null)
    {
    }

    public function transform(string $path, string $tenantId): string
    {
        $dataPath = $this->getDataPath($tenantId);

        $rootPath = $this->extractRootPath($path, $dataPath);

        $relativePath = str_replace($rootPath, '', $path);

        $fullTenantPath = $rootPath . '/tenants/' . $tenantId . $relativePath;

        return $fullTenantPath;
    }

    private function getDataPath(string $tenantId): string
    {
        $tenantDataPath = new TenantDataPath($this->dataPath, $tenantId);
        $dataPath = $tenantDataPath->getTenantDataPath($tenantId);

        if (str_starts_with($dataPath, '../')) {
            $dataPath = str_replace('../', '', $dataPath);
        }

        if (str_starts_with($dataPath, './')) {
            $dataPath = str_replace('./', '', $dataPath);
        }

        return $dataPath;
    }

    private function extractRootPath(string $path, string $dataPath): string
    {
        $positionDataPath = strpos($path, $dataPath);
        if ($positionDataPath === false) {
            throw new DataPathException("DATA_PATH pattern '$dataPath' not found in path '$path'");
        }
        $positionDataPath += strlen($dataPath);
        return substr($path, 0, $positionDataPath);
    }
}
