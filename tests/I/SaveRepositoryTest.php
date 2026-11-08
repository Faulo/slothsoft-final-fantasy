<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** @covers \Slothsoft\FinalFantasy\I\SaveRepository */
final class SaveRepositoryTest extends TestCase {
    public function testPersistenceIsolationAndRejectedUpload(): void {
        $directory = temp_dir('ff-repository');
        $repository = new SaveRepository($directory);
        $user = SaveRepository::userId('');
        $other = SaveRepository::userId('');
        self::assertNotSame($user, $other);
        $source = str_repeat("\0", 8192);
        $source[0x4FD] = "\xFF";
        $source[0x4FE] = "\x55";
        $source[0x4FF] = "\xAA";
        $repository->store($user, $source);
        self::assertSame($source, $repository->load($user));
        self::assertNull($repository->load($other));
        self::assertNull($repository->getModifiedTime($other));
        self::assertTrue(touch($directory . '/' . $user . '.sav', 946684800));
        self::assertSame(946684800, $repository->getModifiedTime($user));
        self::assertSame($source, $repository->load($user));
        self::assertSame(946684800, $repository->getModifiedTime($user), 'Reading must not change the file timestamp.');
        try {
            $repository->store($user, 'invalid');
            self::fail('Invalid saves must be rejected.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame($source, $repository->load($user));
            self::assertSame(946684800, $repository->getModifiedTime($user));
        }
        $repository->store($user, $source);
        self::assertGreaterThan(946684800, $repository->getModifiedTime($user));
        $this->expectException(InvalidArgumentException::class);
        $repository->load('../escape');
    }
}
