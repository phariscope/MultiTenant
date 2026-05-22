<?php

declare(strict_types=1);

namespace Phariscope\MultiTenant\Tests\Share;

use Symfony\Component\Filesystem\Filesystem;

trait IsolatesDataPathEnvTrait
{
    /** @var array{hadEnv: bool, env: mixed, getenv: string|false}|null */
    private ?array $savedDataPathState = null;

    private ?string $isolatedDataPathDir = null;

    protected function setUpIsolatedWritableDataPath(): void
    {
        $this->isolatedDataPathDir = sys_get_temp_dir() . '/mt-cmd-' . uniqid('', true);
        mkdir($this->isolatedDataPathDir, 0775, true);
        $this->savedDataPathState = $this->captureDataPathEnv();
        $this->setDataPathEnv($this->isolatedDataPathDir);
    }

    protected function tearDownIsolatedDataPath(): void
    {
        if ($this->savedDataPathState !== null) {
            $this->restoreDataPathEnv($this->savedDataPathState);
        }

        if ($this->isolatedDataPathDir !== null && is_dir($this->isolatedDataPathDir)) {
            (new Filesystem())->remove($this->isolatedDataPathDir);
            $this->isolatedDataPathDir = null;
        }
    }

    protected function setUpDataPathEnvSnapshot(): void
    {
        $this->savedDataPathState = $this->captureDataPathEnv();
    }

    protected function tearDownDataPathEnvSnapshot(): void
    {
        if ($this->savedDataPathState !== null) {
            $this->restoreDataPathEnv($this->savedDataPathState);
        }
    }

    protected function clearDataPathEnv(): void
    {
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');
    }

    /**
     * @return array{hadEnv: bool, env: mixed, getenv: string|false}
     */
    protected function captureDataPathEnv(): array
    {
        $getenvValue = getenv('DATA_PATH');

        return [
            'hadEnv' => array_key_exists('DATA_PATH', $_ENV),
            'env' => $_ENV['DATA_PATH'] ?? null,
            'getenv' => is_string($getenvValue) && $getenvValue !== '' ? $getenvValue : false,
        ];
    }

    protected function setDataPathEnv(string $path): void
    {
        $_ENV['DATA_PATH'] = $path;
        putenv('DATA_PATH=' . $path);
    }

    /**
     * @param array{hadEnv: bool, env: mixed, getenv: string|false} $state
     */
    protected function restoreDataPathEnv(array $state): void
    {
        if ($state['hadEnv'] && is_string($state['env'])) {
            $_ENV['DATA_PATH'] = $state['env'];
        } else {
            unset($_ENV['DATA_PATH']);
        }

        if (is_string($state['getenv'])) {
            putenv('DATA_PATH=' . $state['getenv']);
        } else {
            putenv('DATA_PATH');
        }
    }
}
