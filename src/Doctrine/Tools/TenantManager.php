<?php

namespace Phariscope\MultiTenant\Doctrine\Tools;

use Symfony\Component\HttpFoundation\Request;

use function Safe\json_decode;
use function SafePHP\strval;

class TenantManager
{
    private Request $request;

    /** @var array<string, mixed> */
    private array $session;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(?Request $request = null, ?array $session = null)
    {
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
            return $_REQUEST['tenant_id'];
        }

        if (isset($_GET['tenant_id'])) {
            return $_GET['tenant_id'];
        }

        if (isset($_POST['tenant_id'])) {
            return $_POST['tenant_id'];
        }

        if (isset($this->session['tenant_id'])) {
            return strval($this->session['tenant_id']);
        }

        if (isset($_SERVER['HTTP_X_TENANT_ID'])) {
            return $_SERVER['HTTP_X_TENANT_ID'];
        }

        if (isset($_COOKIE['tenant_id'])) {
            return $_COOKIE['tenant_id'];
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


        return  $this->getTenantIdFromRequest($this->request);
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
