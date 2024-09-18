<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools;

use PHPUnit\Framework\TestCase;
use Phariscope\MultiTenant\Doctrine\Tools\TenantManager;
use Symfony\Component\HttpFoundation\Request;

class TenantManagerTest extends TestCase
{
    private TenantManager $tenantManager;

    /** @var array<string,mixed> */
    private array $server;

    protected function setUp(): void
    {
        $this->tenantManager = new TenantManager();
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_COOKIE = [];
        $this->server = $_SERVER;
        $_SERVER = [];
    }

    public function tearDown(): void
    {
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_COOKIE = [];
        $_SERVER = $this->server;
    }

    public function testGetTenantIdFromRequestRequest(): void
    {
        $_REQUEST['tenant_id'] = 'tenant_from_request';

        $tenantId = $this->tenantManager->getCurrentTenantId();

        $this->assertEquals('tenant_from_request', $tenantId);
    }

    public function testGetTenantIdFromGetRequest(): void
    {
        $_GET['tenant_id'] = 'tenant_from_get';

        $tenantId = $this->tenantManager->getCurrentTenantId();

        $this->assertEquals('tenant_from_get', $tenantId);
    }

    public function testGetTenantIdFromPostRequest(): void
    {
        $_POST['tenant_id'] = 'tenant_from_post';

        $tenantId = $this->tenantManager->getCurrentTenantId();

        $this->assertEquals('tenant_from_post', $tenantId);
    }

    public function testGetTenantIdFromSession(): void
    {
        session_start();
        $_SESSION['tenant_id'] = 'tenant_from_session';

        $tenantId = $this->tenantManager->getCurrentTenantId();

        $this->assertEquals('tenant_from_session', $tenantId);
    }

    public function testGetTenantIdFromHttpHeader(): void
    {
        $_SERVER['HTTP_X_TENANT_ID'] = 'tenant_from_header';

        $tenantId = $this->tenantManager->getCurrentTenantId();

        $this->assertEquals('tenant_from_header', $tenantId);
    }

    public function testGetTenantIdFromCookie(): void
    {
        $_COOKIE['tenant_id'] = 'tenant_from_cookie';

        $tenantId = $this->tenantManager->getCurrentTenantId();

        $this->assertEquals('tenant_from_cookie', $tenantId);
    }

    public function testGetTenantIdReturnsNullWhenNoTenantIdIsSet(): void
    {
        $tenantId = $this->tenantManager->getCurrentTenantId();

        $this->assertNull($tenantId);
    }

    public function testGetTenantIdFromSymfonyQuery(): void
    {
        $request = new Request(['tenant_id' => 'tenant_from_symfony_request']);

        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        $this->assertEquals('tenant_from_symfony_request', $tenantId);
    }

    public function testGetTenantIdFromSymfonyRequestPost(): void
    {
        $request = new Request([], ['tenant_id' => 'tenant_from_post_request']);

        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        $this->assertEquals('tenant_from_post_request', $tenantId);
    }

    public function testGetTenantIdFromSymfonyRequestCookie(): void
    {
        $request = new Request([], [], [], ['tenant_id' => 'tenant_from_cookie_request']);

        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        $this->assertEquals('tenant_from_cookie_request', $tenantId);
    }

    public function testGetTenantIdFromSymfonyRequestHeader(): void
    {
        $request = new Request([], [], [], [], [], ['HTTP_X_TENANT_ID' => 'tenant_from_header_request']);

        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        $this->assertEquals('tenant_from_header_request', $tenantId);
    }
}
