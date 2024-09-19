<?php

namespace Phariscope\MultiTenant\Doctrine\Tools;

use Symfony\Component\HttpFoundation\Request;

use function SafePHP\strval;

class TenantManager
{
    public function __construct(private ?Request $request = null)
    {
    }

    public function getCurrentTenantId(): ?string
    {
        if (null !== $this->request) {
            return $this->getTenantIdFromRequest($this->request);
        }

        if (isset($_REQUEST['tenant_id'])) {
            return $_REQUEST['tenant_id'];
        }

        if (isset($_GET['tenant_id'])) {
            return $_GET['tenant_id'];
        }

        if (isset($_POST['tenant_id'])) {
            return $_POST['tenant_id'];
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['tenant_id'])) {
            return $_SESSION['tenant_id'];
        }

        if (isset($_SERVER['HTTP_X_TENANT_ID'])) {
            return $_SERVER['HTTP_X_TENANT_ID'];
        }

        if (isset($_COOKIE['tenant_id'])) {
            return $_COOKIE['tenant_id'];
        }

        return null;
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
}
