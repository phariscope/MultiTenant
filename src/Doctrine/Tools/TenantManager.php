<?php

namespace Phariscope\MultiTenant\Doctrine\Tools;

class TenantManager
{
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
}
