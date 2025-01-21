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
            /** @var string $res */
            $res = $_REQUEST['tenant_id'];
            return $res;
        }

        if (isset($_GET['tenant_id'])) {
            /** @var string $res */
            $res = $_GET['tenant_id'];
            return $res;
        }

        if (isset($_POST['tenant_id'])) {
            /** @var string $res */
            $res = $_POST['tenant_id'];
            return $res;
        }

        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['tenant_id'])) {
            /** @var string $res */
            $res = $_SESSION['tenant_id'];
            return $res;
        }

        if (isset($_SERVER['HTTP_X_TENANT_ID'])) {
            /** @var string $res */
            $res = $_SERVER['HTTP_X_TENANT_ID'];
            return $res;
        }

        if (isset($_COOKIE['tenant_id'])) {
            /** @var string $res */
            $res = $_COOKIE['tenant_id'];
            return $res;
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
