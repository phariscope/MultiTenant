<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Tools;

use Phariscope\MultiTenant\Doctrine\Tools\TenantManager;
use Phariscope\MultiTenant\Infrastructure\TenantShortname\TenantShortnameRegistry;
use Phariscope\MultiTenant\Share\TenantException;
use Phariscope\MultiTenant\Share\TenantExistenceChecker;
use Phariscope\MultiTenant\Tests\Share\EnsuresTenantDirectoryTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;

use function Safe\json_encode;

class TenantManagerTest extends TestCase
{
    use EnsuresTenantDirectoryTrait;

    private TenantManager $tenantManager;

    /** @var array<string,mixed> */
    private array $server;

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        $this->tenantManager = new TenantManager();
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_COOKIE = [];
        $this->server = self::stringKeyedServerArray($_SERVER);
        $_SERVER = [];
    }

    /**
     * @param array<mixed> $server
     * @return array<string, mixed>
     */
    private static function stringKeyedServerArray(array $server): array
    {
        $result = [];
        foreach ($server as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function tearDown(): void
    {
        $_REQUEST = [];
        $_GET = [];
        $_POST = [];
        $_SESSION = [];
        $_COOKIE = [];
        $_SERVER = $this->server;

        foreach ($this->tempDirs as $dir) {
            (new Filesystem())->remove($dir);
        }
        $this->tempDirs = [];
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');
    }

    public function testGetTenantIdFromRequestRequest(): void
    {
        // Arrange
        $this->arrangeDataPathWithTenant('tenant_from_request');
        $_REQUEST['tenant_id'] = 'tenant_from_request';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_request', $tenantId);
    }

    public function testGetTenantIdFromGetRequest(): void
    {
        // Arrange
        $this->arrangeDataPathWithTenant('tenant_from_get');
        $_GET['tenant_id'] = 'tenant_from_get';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_get', $tenantId);
    }

    public function testGetTenantIdFromPostRequest(): void
    {
        // Arrange
        $this->arrangeDataPathWithTenant('tenant_from_post');
        $_POST['tenant_id'] = 'tenant_from_post';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_post', $tenantId);
    }

    public function testGetTenantIdFromSession(): void
    {
        // Arrange
        $this->arrangeDataPathWithTenant('tenant_from_session');
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
        $this->arrangeDataPathWithTenant('tenant_from_header');
        $_SERVER['HTTP_X_TENANT_ID'] = 'tenant_from_header';

        // Act
        $tenantId = $this->tenantManager->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_header', $tenantId);
    }

    public function testGetTenantIdFromCookie(): void
    {
        // Arrange
        $this->arrangeDataPathWithTenant('tenant_from_cookie');
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
        $this->arrangeDataPathWithTenant('tenant_from_symfony_request');
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
        $this->arrangeDataPathWithTenant('tenant_from_post_request');
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
        $this->arrangeDataPathWithTenant('tenant_from_cookie_request');
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
        $this->arrangeDataPathWithTenant('tenant_from_header_request');
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
        $this->arrangeDataPathWithTenant('tenant_from_active_session');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['noise'] = 'ignored';
        $_SESSION['tenant_id'] = 'tenant_from_active_session';

        // Act
        $sut = new TenantManager();
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertEquals('tenant_from_active_session', $tenantId);

        // Clean up
        unset($_SESSION['noise'], $_SESSION['tenant_id']);
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
        $this->arrangeDataPathWithTenant('tenant_from_json_request');
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
            $this->ensureTenantDirectory($tmp, 'resolved-tid');

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
            $this->ensureTenantDirectory($tmp, 'direct-id');

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
            $this->ensureTenantDirectory($tmpRight, 'tid-json');

            $payload = json_encode(['tenant_shortname' => 'slug-json']);
            $request = new Request([], [], [], [], [], [], $payload);
            $checker = new TenantExistenceChecker($tmpRight, $registryRight);
            $sut = new TenantManager($request, null, $registryRight, $checker);

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
            $this->ensureTenantDirectory($tmp, 'tid-q');

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
            $this->ensureTenantDirectory($tmp, 'tid-req');
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
            $this->ensureTenantDirectory($tmp, 'tid-get-sn');
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
            $this->ensureTenantDirectory($tmp, 'tid-post-sn');
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
            $this->ensureTenantDirectory($tmp, 'tid-sess-sn');
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
            $this->ensureTenantDirectory($tmp, 'tid-cookie-sn');
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

    public function testThrowsWhenShortnameCannotBeResolvedWithoutRegistry(): void
    {
        // Arrange
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;
        unset($_ENV['DATA_PATH']);
        putenv('DATA_PATH');
        $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'unknown-slug';
        $sut = new TenantManager();

        // Assert
        $this->expectException(TenantException::class);
        $this->expectExceptionMessage('Cannot resolve tenant shortname "unknown-slug"');

        try {
            // Act
            $sut->getCurrentTenantId();
        } finally {
            unset($_SERVER['HTTP_X_TENANT_SHORTNAME']);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testThrowsWhenUnknownShortnameWithRegistry(): void
    {
        $tmp = sys_get_temp_dir() . '/mt-tm-bad-sn-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'slug-inconnu';
            $sut = new TenantManager();

            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Unknown tenant: slug-inconnu');
            $sut->getCurrentTenantId();
        } finally {
            unset($_SERVER['HTTP_X_TENANT_SHORTNAME']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testThrowsWhenTenantIdFolderIsMissing(): void
    {
        $tmp = sys_get_temp_dir() . '/mt-tm-missing-' . uniqid('', true);
        mkdir($tmp, 0775, true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            $_SERVER['HTTP_X_TENANT_ID'] = 'id-inexistant';
            $sut = new TenantManager();

            $this->expectException(TenantException::class);
            $this->expectExceptionMessage('Tenant not found: id-inexistant');
            $sut->getCurrentTenantId();
        } finally {
            unset($_SERVER['HTTP_X_TENANT_ID']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesShortnameFromRegistryWithoutTenantFolder(): void
    {
        $tmp = sys_get_temp_dir() . '/mt-tm-sn-no-dir-' . uniqid('', true);
        mkdir($tmp, 0775, true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-no-dir', 'slug-no-dir');
            $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'slug-no-dir';
            $sut = new TenantManager();

            $this->assertSame('tid-no-dir', $sut->getCurrentTenantId());
        } finally {
            unset($_SERVER['HTTP_X_TENANT_SHORTNAME']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
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
            $this->ensureTenantDirectory($tmp, 'tid-sf-post');
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
            $this->ensureTenantDirectory($tmp, 'tid-sf-cookie');
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

    public function testWhitespaceOnlyTenantIdFromHeaderIsIgnored(): void
    {
        // Arrange
        $request = new Request([], [], [], [], [], ['HTTP_X_TENANT_ID' => '   ']);
        $sut = new TenantManager($request);

        // Act
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertNull($tenantId);
    }

    public function testResolvesShortnameFromSymfonyHeader(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-sf-header-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-sf-header', 'slug-sf-header');
            $this->ensureTenantDirectory($tmp, 'tid-sf-header');
            $request = new Request([], [], [], [], [], ['HTTP_X_TENANT_SHORTNAME' => 'slug-sf-header']);
            $sut = new TenantManager($request);

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-sf-header', $tenantId);
        } finally {
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testUsesInjectedExistenceChecker(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-checker-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            $registry = TenantShortnameRegistry::fromApplicationDataPath($tmp);
            $registry->register('tid-custom', 'slug-custom');
            $this->ensureTenantDirectory($tmp, 'tid-custom');

            $checker = $this->createMock(TenantExistenceChecker::class);
            $checker->expects($this->once())
                ->method('assertResolvableForHttp')
                ->with(null, 'slug-custom')
                ->willReturn('tid-custom');

            $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'slug-custom';
            $sut = new TenantManager(null, null, $registry, $checker);

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-custom', $tenantId);
        } finally {
            unset($_SERVER['HTTP_X_TENANT_SHORTNAME']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromGetWithoutSymfonyRequestFallback(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-get-only-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-get-only', 'slug-get-only');
            $this->ensureTenantDirectory($tmp, 'tid-get-only');
            $_GET['tenant_shortname'] = 'slug-get-only';
            $sut = new TenantManager(new Request());

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-get-only', $tenantId);
        } finally {
            unset($_GET['tenant_shortname']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromPostWithoutSymfonyRequestFallback(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-post-only-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-post-only', 'slug-post-only');
            $this->ensureTenantDirectory($tmp, 'tid-post-only');
            $_POST['tenant_shortname'] = 'slug-post-only';
            $sut = new TenantManager(new Request());

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-post-only', $tenantId);
        } finally {
            unset($_POST['tenant_shortname']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromServerHeaderWithoutSymfonyRequestFallback(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-hdr-only-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-hdr-only', 'slug-hdr-only');
            $this->ensureTenantDirectory($tmp, 'tid-hdr-only');
            $_SERVER['HTTP_X_TENANT_SHORTNAME'] = 'slug-hdr-only';
            $sut = new TenantManager(new Request());

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-hdr-only', $tenantId);
        } finally {
            unset($_SERVER['HTTP_X_TENANT_SHORTNAME']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testResolvesTenantShortnameFromCookieWithoutSymfonyRequestFallback(): void
    {
        // Arrange
        $tmp = sys_get_temp_dir() . '/mt-tm-cookie-only-' . uniqid('', true);
        $hadKey = array_key_exists('DATA_PATH', $_ENV);
        $previous = $hadKey ? $_ENV['DATA_PATH'] : null;

        try {
            $_ENV['DATA_PATH'] = $tmp;
            putenv('DATA_PATH=' . $tmp);
            TenantShortnameRegistry::fromApplicationDataPath($tmp)->register('tid-cookie-only', 'slug-cookie-only');
            $this->ensureTenantDirectory($tmp, 'tid-cookie-only');
            $_COOKIE['tenant_shortname'] = 'slug-cookie-only';
            $sut = new TenantManager(new Request());

            // Act
            $tenantId = $sut->getCurrentTenantId();

            // Assert
            $this->assertSame('tid-cookie-only', $tenantId);
        } finally {
            unset($_COOKIE['tenant_shortname']);
            (new Filesystem())->remove($tmp);
            $this->restoreDataPath($hadKey, $previous);
        }
    }

    public function testSessionUsesTenantIdEvenWhenOtherStringKeysExist(): void
    {
        // Arrange
        $session = [
            'noise' => 'ignored',
            'tenant_id' => 'tenant_from_session',
        ];
        $this->arrangeDataPathWithTenant('tenant_from_session');
        $sut = new TenantManager(null, $session);

        // Act
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertSame('tenant_from_session', $tenantId);
    }

    public function testSessionIgnoresNonStringKeys(): void
    {
        // Arrange
        $session = [
            0 => 'ignored-numeric-key',
            'tenant_id' => 'tenant_from_session',
        ];
        $this->arrangeDataPathWithTenant('tenant_from_session');
        $sut = new TenantManager(null, $session);

        // Act
        $tenantId = $sut->getCurrentTenantId();

        // Assert
        $this->assertSame('tenant_from_session', $tenantId);
    }

    private function arrangeDataPathWithTenant(string $tenantId): void
    {
        $tmp = sys_get_temp_dir() . '/mt-tm-' . uniqid('', true);
        $this->ensureTenantDirectory($tmp, $tenantId);
        $_ENV['DATA_PATH'] = $tmp;
        putenv('DATA_PATH=' . $tmp);
        $this->tempDirs[] = $tmp;
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
