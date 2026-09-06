<?php

namespace Phariscope\MultiTenant\Doctrine\Tools;

use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Share\TenantException;
use Phariscope\MultiTenant\Share\TenantExistenceChecker;
use Symfony\Component\HttpFoundation\Request;

use function Safe\json_decode;
use function SafePHP\strval;

class TenantManager
{
    private Request $request;

    /** @var array<string, mixed> */
    private array $session;

    /**
     * @param array<int|string, mixed>|null $session
     */
    public function __construct(
        ?Request $request = null,
        ?array $session = null,
        private readonly ?TenantShortnameRegistry $shortnameRegistry = null,
        private readonly ?TenantExistenceChecker $existenceChecker = null,
    ) {
        if ($request !== null) {
            $this->request = $request;
        } else {
            $this->request = Request::createFromGlobals();
        }

        if ($session === null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->session = self::stringKeyedArray($_SESSION);
            } else {
                $this->session = [];
            }
        } else {
            $this->session = self::stringKeyedArray($session);
        }
    }

    /**
     * @param array<mixed> $array
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function getCurrentTenantId(): ?string
    {
        $tenantId = $this->extractExplicitTenantId();
        if ($tenantId !== null) {
            return $this->validateTenant($tenantId, null);
        }

        $shortname = $this->extractExplicitTenantShortname();
        if ($shortname !== null) {
            return $this->validateTenant(null, $shortname);
        }

        return null;
    }

    /**
     * @throws TenantException
     */
    private function validateTenant(?string $tenantId, ?string $tenantShortname): ?string
    {
        $dataPath = $_ENV['DATA_PATH'] ?? getenv('DATA_PATH');
        if (!is_string($dataPath) || $dataPath === '') {
            if ($tenantShortname !== null) {
                throw TenantException::cannotResolveShortname($tenantShortname);
            }

            throw TenantException::unknownTenantId($tenantId ?? '');
        }

        $checker = $this->existenceChecker ?? new TenantExistenceChecker($dataPath, $this->shortnameRegistry);

        return $checker->assertResolvableForHttp($tenantId, $tenantShortname);
    }

    private function extractExplicitTenantId(): ?string
    {
        if (isset($_REQUEST['tenant_id'])) {
            return $this->normalizeProvidedValue(strval($_REQUEST['tenant_id']));
        }

        if (isset($_GET['tenant_id'])) {
            return $this->normalizeProvidedValue(strval($_GET['tenant_id']));
        }

        if (isset($_POST['tenant_id'])) {
            return $this->normalizeProvidedValue(strval($_POST['tenant_id']));
        }

        if (isset($this->session['tenant_id'])) {
            return $this->normalizeProvidedValue(strval($this->session['tenant_id']));
        }

        if (isset($_SERVER['HTTP_X_TENANT_ID'])) {
            return $this->normalizeProvidedValue(strval($_SERVER['HTTP_X_TENANT_ID']));
        }

        if (isset($_COOKIE['tenant_id'])) {
            return $this->normalizeProvidedValue(strval($_COOKIE['tenant_id']));
        }

        if ($this->request->getContent() !== null) {
            $content = $this->request->getContent();
            if (json_validate($content)) {
                $json = json_decode($content, true);
                if (is_array($json) && isset($json['tenant_id'])) {
                    return $this->normalizeProvidedValue(strval($json['tenant_id']));
                }
            }
        }

        return $this->getTenantIdFromRequest($this->request);
    }

    private function extractExplicitTenantShortname(): ?string
    {
        if (isset($_REQUEST['tenant_shortname'])) {
            return $this->normalizeProvidedValue(strval($_REQUEST['tenant_shortname']));
        }

        if (isset($_GET['tenant_shortname'])) {
            return $this->normalizeProvidedValue(strval($_GET['tenant_shortname']));
        }

        if (isset($_POST['tenant_shortname'])) {
            return $this->normalizeProvidedValue(strval($_POST['tenant_shortname']));
        }

        if (isset($this->session['tenant_shortname'])) {
            return $this->normalizeProvidedValue(strval($this->session['tenant_shortname']));
        }

        if (isset($_SERVER['HTTP_X_TENANT_SHORTNAME'])) {
            return $this->normalizeProvidedValue(strval($_SERVER['HTTP_X_TENANT_SHORTNAME']));
        }

        if (isset($_COOKIE['tenant_shortname'])) {
            return $this->normalizeProvidedValue(strval($_COOKIE['tenant_shortname']));
        }

        if ($this->request->getContent() !== null) {
            $content = $this->request->getContent();
            if (json_validate($content)) {
                $json = json_decode($content, true);
                if (is_array($json) && isset($json['tenant_shortname'])) {
                    return $this->normalizeProvidedValue(strval($json['tenant_shortname']));
                }
            }
        }

        return $this->getTenantShortnameFromRequest($this->request);
    }

    private function getTenantIdFromRequest(Request $request): ?string
    {
        if ($request->query->has('tenant_id')) {
            return $this->normalizeProvidedValue(strval($request->query->get('tenant_id')));
        }

        if ($request->request->has('tenant_id')) {
            return $this->normalizeProvidedValue(strval($request->request->get('tenant_id')));
        }

        if ($request->cookies->has('tenant_id')) {
            return $this->normalizeProvidedValue(strval($request->cookies->get('tenant_id')));
        }

        if ($request->headers->has('X-Tenant-Id')) {
            return $this->normalizeProvidedValue($request->headers->get('X-Tenant-Id'));
        }

        return null;
    }

    private function getTenantShortnameFromRequest(Request $request): ?string
    {
        if ($request->query->has('tenant_shortname')) {
            return $this->normalizeProvidedValue(strval($request->query->get('tenant_shortname')));
        }

        if ($request->request->has('tenant_shortname')) {
            return $this->normalizeProvidedValue(strval($request->request->get('tenant_shortname')));
        }

        if ($request->cookies->has('tenant_shortname')) {
            return $this->normalizeProvidedValue(strval($request->cookies->get('tenant_shortname')));
        }

        if ($request->headers->has('X-Tenant-Shortname')) {
            return $this->normalizeProvidedValue($request->headers->get('X-Tenant-Shortname'));
        }

        return null;
    }

    private function normalizeProvidedValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
