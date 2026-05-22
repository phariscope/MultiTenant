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

    public function testResolvesTenantShortnameFromRequestSuperglobal(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-req-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-req', 'slug-req');
            $_REQUEST['tenant_shortname'] = 'slug-req';
            $sut = new TenantManager();

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-req', $tenantId);
        } finally {
            unset($_REQUEST['tenant_shortname']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromGet(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-get-sn-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-get-sn', 'slug-get');
            $_GET['tenant_shortname'] = 'slug-get';
            $sut = new TenantManager();

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-get-sn', $tenantId);
        } finally {
            unset($_GET['tenant_shortname']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromPost(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-post-sn-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-post-sn', 'slug-post');
            $_POST['tenant_shortname'] = 'slug-post';
            $sut = new TenantManager();

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-post-sn', $tenantId);
        } finally {
            unset($_POST['tenant_shortname']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromSession(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-sess-sn-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-sess-sn', 'slug-sess');
            $session = ['tenant_shortname' => 'slug-sess'];
            $sut = new TenantManager(null, $session);

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-sess-sn', $tenantId);
        } finally {
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromCookie(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-cookie-sn-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-cookie-sn', 'slug-cookie');
            $_COOKIE['tenant_shortname'] = 'slug-cookie';
            $sut = new TenantManager();

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-cookie-sn', $tenantId);
        } finally {
            unset($_COOKIE['tenant_shortname']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testReturnsNullWhenShortnameCannotBeResolvedWithoutRegistry(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');
        $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'unknown-slug';
        $sut = new TenantManager();

        // Act
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertNull($tenantId);

        unset($_SERVER['HTTP_X_TENANT_SHORTNAME']);
        $this->restoreDataPath($hadKey, $previous);
    }

    public function testResolvesShortnameFromSymfonyPost(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-sf-post-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-sf-post', 'slug-sf-post');
            $request = new Request([], ['tenant_shortname' => 'slug-sf-post']);
            $sut = new TenantManager($request);

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-sf-post', $tenantId);
        } finally {
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesShortnameFromSymfonyCookie(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-sf-cookie-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-sf-cookie', 'slug-sf-cookie');
            $request = new Request([], [], [], ['tenant_shortname' => 'slug-sf-cookie']);
            $sut = new TenantManager($request);

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-sf-cookie', $tenantId);
        } finally {
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testEmptySymfonyShortnameHeaderReturnsNull(): void
    {
        // Arrange
        $request = new Request([], [], [], [], [], ['HTTP_X_TENANT_SHORTNAME' => '']);
        $sut = new TenantManager($request);

        // Act
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertNull($tenantId);
    }

    /**
     * @param mixed $previous
     */
    private function restoreDataPath(bool $hadKey, mixed $previous): void
    {
        if ($hadKey && is_string($previous)) {
            $_ENV['DATA_PATH'] = $previous;
            putenv('DATA_PATH=' . $previous);
        } else {
            unset($_ENV['DATA_PATH']);
            putenv('DATA_PATH');
        }
    }
}
