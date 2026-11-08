<?php
declare(strict_types = 1);

namespace Slothsoft\FinalFantasy\I;

use InvalidArgumentException;
use RuntimeException;

/** Private persistent saves; the random URL identifier grants access to its save. */
final class SaveRepository {
    public function __construct(private string $directory) {
    }
    
    public static function userId(string $user): string {
        if ($user === '') {
            return bin2hex(random_bytes(16));
        }
        if (! preg_match('/^[a-f0-9]{32}$/D', $user)) {
            throw new InvalidArgumentException('Invalid save user ID.');
        }
        return $user;
    }
    
    public function load(string $user): ?string {
        $path = $this->path($user);
        if (! is_file($path)) {
            return null;
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Could not load the saved game.');
        }
        new Save($contents);
        return $contents;
    }
    
    public function store(string $user, string $contents): void {
        new Save($contents);
        $path = $this->path($user);
        if (! is_dir($this->directory) && ! mkdir($this->directory, 0700, true) && ! is_dir($this->directory)) {
            throw new RuntimeException('Could not create the save directory.');
        }
        $temporary = tempnam($this->directory, 'save-');
        if ($temporary === false) {
            throw new RuntimeException('Could not prepare the saved game.');
        }
        try {
            if (file_put_contents($temporary, $contents) !== strlen($contents) || ! rename($temporary, $path)) {
                throw new RuntimeException('Could not store the saved game.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }
    
    private function path(string $user): string {
        if ($user === '') {
            throw new InvalidArgumentException('A save user ID is required.');
        }
        return $this->directory . '/' . self::userId($user) . '.sav';
    }
}
