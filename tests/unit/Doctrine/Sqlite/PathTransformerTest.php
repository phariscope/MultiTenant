<?php

namespace Phariscope\MultiTenant\Tests\Doctrine\Sqlite;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Phariscope\MultiTenant\Doctrine\Sqlite\PathTransformer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Util\Filesystem;

class PathTransformerTest extends TestCase
{
    private vfsStreamDirectory $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = vfsStream::setup();
    }

    public function testTransform(): void
    {
        $initialPath = $this->root->url() . '/data/database.sqlite';
        $expectedPath = $this->root->url() . '/data/databases/tenant123/database.sqlite';
        $sut = new PathTransformer();
        $result = $sut->transform($initialPath, 'tenant123');
        $this->assertEquals($expectedPath, $result);
    }
}
