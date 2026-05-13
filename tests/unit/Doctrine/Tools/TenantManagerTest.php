<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools;

use Phariscope\MultiTenant\Doctrine\Tools\TenantManager;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

use function Safe\json_encode;

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
        // Arrange
        $_REQUEST['tenant_id'] = 'tenant_from_request';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_request', $tenantId);
    }

    public function testGetTenantIdFromGetRequest(): void
    {
        // Arrange
        $_GET['tenant_id'] = 'tenant_from_get';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_get', $tenantId);
    }

    public function testGetTenantIdFromPostRequest(): void
    {
        // Arrange
        $_POST['tenant_id'] = 'tenant_from_post';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_post', $tenantId);
    }

    public function testGetTenantIdFromSession(): void
    {
        // Arrange
        $MOCK_SESSION = [];
        $MOCK_SESSION['tenant_id'] = 'tenant_from_session';

        $this->tenantManager = new TenantManager(null, $MOCK_SESSION);

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_session', $tenantId);
    }

    public function testGetTenantIdFromHttpHeader(): void
    {
        // Arrange
        $_SERVER['HTTP_X_TENANT_ID'] = 'tenant_from_header';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_header', $tenantId);
    }

    public function testGetTenantIdFromCookie(): void
    {
        // Arrange
        $_COOKIE['tenant_id'] = 'tenant_from_cookie';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_cookie', $tenantId);
    }

    public function testGetTenantIdReturnsNullWhenNoTenantIdIsSet(): void
    {
        // Arrange
        // (defaults from setUp: no tenant in request)

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertNull($tenantId);
    }

    public function testGetTenantIdFromSymfonyQuery(): void
    {
        // Arrange
        $request = new Request(['tenant_id' => 'tenant_from_symfony_request']);

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_symfony_request', $tenantId);
    }

    public function testGetTenantIdFromSymfonyRequestPost(): void
    {
        // Arrange
        $request = new Request([], ['tenant_id' => 'tenant_from_post_request']);

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_post_request', $tenantId);
    }

    public function testGetTenantIdFromSymfonyRequestCookie(): void
    {
        // Arrange
        $request = new Request([], [], [], ['tenant_id' => 'tenant_from_cookie_request']);

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_cookie_request', $tenantId);
    }

    public function testGetTenantIdFromSymfonyRequestHeader(): void
    {
        // Arrange
        $request = new Request([], [], [], [], [], ['HTTP_X_TENANT_ID' => 'tenant_from_header_request']);

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_header_request', $tenantId);
    }

    public function testConstructorWithActiveSession(): void
    {
        // Arrange
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['tenant_id'] = 'tenant_from_active_session';

        // Act
        $sut = new TenantManager();
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_active_session', $tenantId);

        // Clean up
        session_destroy();
    }

    public function testGetTenantIdFromEmptySymfonyRequest(): void
    {
        // Arrange
        $request = new Request();

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertNull($tenantId);
    }

    public function testGetTenantIdFromJsonRequest(): void
    {
        // Arrange
        $request = new Request([], [], [], [], [], [], json_encode(['tenant_id' => 'tenant_from_json_request']));

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_json_request', $tenantId);
    }

    public function testShouldNotThrowEceptionWhenContentIsNotJson(): void
    {
        // Arrange
        $request = new Request([], [], [], [], [], [], 'not_json');

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertNull($tenantId);
    }

    public function testResolvesTenantShortnameFromHeader(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-sn-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            $registry = TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $registry->register('resolved-tid', 'slug-one');

            $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'slug-one';
            $sut = new TenantManager();

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('resolved-tid', $tenantId);
        } finally {
            unset($_SERVER['HTTP_X_TENANT_SHORTNAME']);
            (new Filesystem())->remove($tmp);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }

    public function testTenantIdTakesPrecedenceOverTenantShortname(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-prec-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            $registry = TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $registry->register('from-shortname', 'slug-one');

            $_SERVER['HTTP_X_TENANT_ID'] = 'direct-id';
            $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'slug-one';
            $sut = new TenantManager();

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('direct-id', $tenantId);
        } finally {
            unset($_SERVER['HTTP_X_TENANT_ID'], $_SERVER['HTTP_X_TENANT_SHORTNAME']);
            (new Filesystem())->remove($tmp);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }

    public function testJsonBodyWithoutTenantIdDoesNotYieldTenantId(): void
    {
        // Arrange
        $request = new Request([], [], [], [], [], [], json_encode(['other' => 'value']));

        // Act
        $sut = new TenantManager($request);
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertNull($tenantId);
    }

    public function testInjectedShortnameRegistryIsPreferredOverEnvBasedRegistry(): void
    {
        // Arrange
        $tmpWrong = sys_get_temp_dir() . '/mt-tm-wrong-' . uniqid('', true);
        $tmpRight = sys_get_temp_dir() . '/mt-tm-right-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmpWrong;
            putenv('DATA_PATH=' . $tmpWrong);

            $registryRight = TenantShortnameRegistry::fromApplicationDataPath($tmpRight);
            $registryRight->register('tid-json', 'slug-json');

            $payload = json_encode(['tenant_shortname' => 'slug-json']);
            $request = new Request([], [], [], [], [], [], $payload);
            $sut = new TenantManager($request, null, $registryRight);

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-json', $tenantId);
        } finally {
            (new Filesystem())->remove($tmpWrong);
            (new Filesystem())->remove($tmpRight);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }

    public function testResolvesShortnameFromSymfonyQuery(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-q-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            $registry = TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $registry->register('tid-q', 'slug-q');

            $request = new Request(['tenant_shortname' => 'slug-q']);

            // Act
            $sut = new TenantManager($request);
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-q', $tenantId);
        } finally {
            (new Filesystem())->remove($tmp);
            if ($hadKey && is_string($previous)) {
                $_ENV['DATA_PATH'] = $previous;
                putenv('DATA_PATH=' . $previous);
            } else {
                unset($_ENV['DATA_PATH']);
                putenv('DATA_PATH');
            }
        }
    }
}
