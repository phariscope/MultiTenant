<?php

namespace Phariscope\MultiTenant\Doctrine\Sqlite;

use Phariscope\MultiTenant\Share\DataPathException;
use Phariscope\MultiTenant\Share\TenantDataPath;

class PathTransformer
{
    public function __construct(private ?string $dataPath = null)
    {
    }

    public function transform(string $path, string $tenantId): string
    {
        $dataPath = $this->getDataPath($tenantId);

        $rootPath = $this->extractRootPath($path, $dataPath);

        // Calculer le relativePath par rapport au dataPath original dans le path
        $cleanDataPath = str_replace('../', '', $dataPath);
        $positionDataPath = strpos($path, $cleanDataPath);
        $originalRootPath = substr($path, 0, $positionDataPath + strlen($cleanDataPath));
        $relativePath = str_replace($originalRootPath, '', $path);

        $fullTenantPath = $rootPath . '/tenants/' . $tenantId . $relativePath;

        return $fullTenantPath;
    }

    private function getDataPath(string $tenantId): string
    {
        $tenantDataPath = new TenantDataPath($this->dataPath, $tenantId);
        $dataPath = $tenantDataPath->getTenantDataPath($tenantId);

        if (str_starts_with($dataPath, './')) {
            $dataPath = str_replace('./', '', $dataPath);
        }

        return $dataPath;
    }

    private function extractRootPath(string $path, string $dataPath): string
    {
        $nbSubfolderToRemove = 0;
        $cleanDataPath = $dataPath;

        // Ne traiter les ../ que si le dataPath a été passé directement au constructeur
        if (str_starts_with($dataPath, '../')) {
            $nbSubfolderToRemove = substr_count($dataPath, '../');
            $cleanDataPath = str_replace('../', '', $dataPath);

            // Réinitialiser si le dataPath provient de l'environnement
            if ($this->dataPath === null) {
                $nbSubfolderToRemove = 0;
            }
        }

        $positionDataPath = strpos($path, $cleanDataPath);
        if ($positionDataPath === false) {
            throw new DataPathException("DATA_PATH pattern '$cleanDataPath' not found in path '$path'");
        }

        if ($nbSubfolderToRemove > 0) {
            // Diviser le chemin en parties
            $pathParts = explode('/', $path);

            // Supprimer la partie vide du début si le chemin commence par '/'
            if ($pathParts[0] === '') {
                array_shift($pathParts);
            }

            // Trouver l'index où commence le cleanDataPath
            $cleanDataPathParts = explode('/', $cleanDataPath);
            $cleanDataPathStart = array_search($cleanDataPathParts[0], $pathParts);

            // Calculer le nouvel index en reculant de $nbSubfolderToRemove
            $newIndex = $cleanDataPathStart - $nbSubfolderToRemove;

            // Reconstruire le rootPath : prendre les parties avant newIndex + cleanDataPath
            $newRootPathParts = array_slice($pathParts, 0, $newIndex);
            $newRootPathParts = array_merge($newRootPathParts, $cleanDataPathParts);
            $rootPath = '/' . implode('/', $newRootPathParts);
        } else {
            // Pas de ../
            $positionDataPath += strlen($cleanDataPath);
            $rootPath = substr($path, 0, $positionDataPath);
        }

        return $rootPath;
    }
}
