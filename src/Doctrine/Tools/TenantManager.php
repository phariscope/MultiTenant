<?php

namespace Phariscope\MultiTenant\Doctrine\Tools;

use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Symfony\Component\HttpFoundation\Request;

use function Safe\json_decode;
use function SafePHP\strval;

class TenantManager
{
    private Request $request;

    /** @var array<string, mixed> */
    private array $session;

    /**
     * @param array<string, mixed>|null $session
     */
    public function __construct(
        ?Request $request = null,
        ?array $session = null,
        private readonly ?TenantShortnameRegistry $shortnameRegistry = null,
    ) {
        if ($request !== null) {
            $this->request = $request;
        } else {
            $this->request = Request::createFromGlobals();
        }

        if ($session === null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->session = $_SESSION;
            } else {
                $this->session = [];
            }
        } else {
            $this->session = $session;
        }
    }

    public function getCurrentTenantId(): ?string
    {
        if (isset($_REQUEST['tenant_id'])) {
            return strval($_REQUEST['tenant_id']);
        }

        if (isset($_GET['tenant_id'])) {
            return strval($_GET['tenant_id']);
        }

        if (isset($_POST['tenant_id'])) {
            return strval($_POST['tenant_id']);
        }

        if (isset($this->session['tenant_id'])) {
            return strval($this->session['tenant_id']);
        }

        if (isset($_SERVER['HTTP_X_TENANT_ID'])) {
            return strval($_SERVER['HTTP_X_TENANT_ID']);
        }

        if (isset($_COOKIE['tenant_id'])) {
            return strval($_COOKIE['tenant_id']);
        }

        if ($this->request->getContent() !== null) {
            $content = $this->request->getContent();
            if (json_validate($content)) {
                $json = json_decode($content, true);
                if (is_array($json) && isset($json['tenant_id'])) {
                    return strval($json['tenant_id']);
                }
            }
        }

        $fromSymfony = $this->getTenantIdFromRequest($this->request);
        if ($fromSymfony !== null) {
            return $fromSymfony;
        }

        return $this->resolveTenantIdFromShortname();
    }

    private function resolveTenantIdFromShortname(): ?string
    {
        $shortname = $this->extractRawTenantShortname();
        if ($shortname === null) {
            return null;
        }

        $registry = $this->shortnameRegistry ?? TenantShortnameRegistry::tryCreateFromEnv();
        if ($registry === null) {
            return null;
        }

        return $registry->resolveTenantId($shortname);
    }

    private function extractRawTenantShortname(): ?string
    {
        if (isset($_REQUEST['tenant_shortname'])) {
            return strval($_REQUEST['tenant_shortname']);
        }

        if (isset($_GET['tenant_shortname'])) {
            return strval($_GET['tenant_shortname']);
        }

        if (isset($_POST['tenant_shortname'])) {
            return strval($_POST['tenant_shortname']);
        }

        if (isset($this->session['tenant_shortname'])) {
            return strval($this->session['tenant_shortname']);
        }

        if (isset($_SERVER['HTTP_X_TENANT_SHORTNAME'])) {
            return strval($_SERVER['HTTP_X_TENANT_SHORTNAME']);
        }

        if (isset($_COOKIE['tenant_shortname'])) {
            return strval($_COOKIE['tenant_shortname']);
        }

        if ($this->request->getContent() !== null) {
            $content = $this->request->getContent();
            if (json_validate($content)) {
                $json = json_decode($content, true);
                if (is_array($json) && isset($json['tenant_shortname'])) {
                    return strval($json['tenant_shortname']);
                }
            }
        }

        return $this->getTenantShortnameFromRequest($this->request);
    }

    private function getTenantIdFromRequest(Request $request): ?string
    {
        if ($request->query->has('tenant_id')) {
            return strval($request->query->get('tenant_id'));
        }

        if ($request->request->has('tenant_id')) {
            return strval($request->request->get('tenant_id'));
        }

        if ($request->cookies->has('tenant_id')) {
            return strval($request->cookies->get('tenant_id'));
        }

        if ($request->headers->has('X-Tenant-Id')) {
            return $request->headers->get('X-Tenant-Id');
        }

        return null;
    }

    private function getTenantShortnameFromRequest(Request $request): ?string
    {
        if ($request->query->has('tenant_shortname')) {
            return strval($request->query->get('tenant_shortname'));
        }

        if ($request->request->has('tenant_shortname')) {
            return strval($request->request->get('tenant_shortname'));
        }

        if ($request->cookies->has('tenant_shortname')) {
            return strval($request->cookies->get('tenant_shortname'));
        }

        if ($request->headers->has('X-Tenant-Shortname')) {
            $value = $request->headers->get('X-Tenant-Shortname');
            return $value !== null && $value !== '' ? $value : null;
        }

        return null;
    }
}
