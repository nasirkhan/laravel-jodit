<?php

namespace Nasirkhan\LaravelJodit\Events;

class FileUploaded
{
    public function __construct(
        public readonly string $path,
        public readonly string $disk,
    ) {}
}
