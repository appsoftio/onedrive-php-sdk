<?php

namespace Test\Unit\Krizalys\Onedrive;

use Krizalys\Onedrive\Folder;
use Krizalys\Onedrive\NameConflictBehavior;
use Mockery\Adapter\Phpunit\MockeryTestCase;
use Mockery as m;
use Psr\Log\LogLevel;

class FolderTest extends MockeryTestCase
{
    private $folder;

    private $client;

    protected function setUp()
    {
        parent::setUp();
        $client       = $this->mockClient();
        $this->folder = new Folder($client);
        $this->client = $client;
    }

    private function mockClient(array $expectations = [])
    {
        $names  = implode(',', array_keys($expectations));
        $client = m::mock("Krizalys\Onedrive\Client[$names]");

        foreach ($expectations as $name => $callback) {
            $expectation = $client->shouldReceive($name);

            if (is_callable($callback)) {
                $callback($expectation);
            }
        }

        return $client;
    }

    /**
     * @param mixed $fileId
     */
    private function mockFile($fileId)
    {
        $file = m::mock('Krizalys\Onedrive\File', [
            'isFolder' => false,
        ]);

        $file->id = $fileId;
        return $file;
    }

    public function testIsFolderShouldReturnExpectedValue()
    {
        $actual = $this
            ->folder
            ->isFolder();

        $this->assertEquals(true, $actual);
    }

    public function testFetchObjectsShouldCallOnceClientLogWithExpectedLevel()
    {
        $client = $this->mockClient([
            'log' => function ($expectation) {
                $expectation
                    ->once()
                    ->withArgs(function ($level, $message, $context = []) {
                        return LogLevel::WARNING == $level;
                    });
            },

            'fetchObjects' => null,
        ]);

        $folder = new Folder($client);
        $folder->fetchObjects();
    }

    public function testFetchObjectsShouldCallOnceClientFetchObjects()
    {
        $client = $this->mockClient([
            'log' => null,

            'fetchObjects' => function ($expectation) {
                $expectation->once();
            },
        ]);

        $folder = new Folder($client);
        $folder->fetchObjects();
    }

    public function testFetchChildObjectsShouldCallOnceClientFetchObjects()
    {
        $client = $this->mockClient([
            'fetchObjects' => function ($expectation) {
                $expectation->once();
            },
        ]);

        $folder = new Folder($client);
        $folder->fetchChildObjects();
    }

    public function testFetchDescendantObjectsShouldReturnExpectedValue()
    {
        $file1 = $this->mockFile('file1');
        $file2 = $this->mockFile('file2');
        $file3 = $this->mockFile('file3');
        $file4 = $this->mockFile('file4');

        $folder = new Folder($this->mockClient([
            'fetchObjects' => function ($expectation) use ($file1, $file2, $file3, $file4) {
                $expectation->andReturn([
                    $file1,
                    new Folder($this->mockClient([
                        'fetchObjects' => function ($expectation) use ($file2, $file3) {
                            $expectation->andReturn([
                                $file2,
                                $file3,
                            ]);
                        },
                    ])),
                    $file4,
                ]);
            },
        ]));

        $expected = [$file2, $file3, $file1, $file4];
        $actual   = $folder->fetchDescendantObjects();
        $this->assertEquals($expected, $actual);
    }

    public function testCreateFolderShouldCallOnceClientCreateFolder()
    {
        $client = $this->mockClient([
            'createFolder' => function ($expectation) {
                $expectation->once();
            },
        ]);

        $folder = new Folder($client);
        $folder->createFolder('test-folder', 'Some test description');
    }

    public function testCreateFileShouldCallOnceClientCreateFile()
    {
        $client = $this->mockClient([
            'createFile' => function ($expectation) {
                $expectation->once();
            },
        ]);

        $folder = new Folder($client);

        $folder->createFile(
            'test-file',
            'Some test content',
            ['name_conflict_behavior', NameConflictBehavior::REPLACE]
        );
    }
}
