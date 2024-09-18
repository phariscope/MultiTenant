<?php

namespace Phariscope\MultiTenant\Doctrine\Sqlite;

class PathTransformer
{
    private const SQLITE_TENANT_DATABSES_SUB_PATH = 'databases';

    private const ARRAY_SPLICE_DO_NOT_REMOVE_FILENAME = 0;

    public function __construct(private string $pathRoot = '')
    {
    }

    public function transform(string $path, string $tenantId): string
    {
        $fullPath = $this->pathRoot . $path;
        $pathParts = explode('/', $fullPath);
        array_splice(
            $pathParts,
            -1,
            self::ARRAY_SPLICE_DO_NOT_REMOVE_FILENAME,
            [self::SQLITE_TENANT_DATABSES_SUB_PATH, $tenantId]
        );
        $path = implode('/', $pathParts);
        return $path;
    }
}
