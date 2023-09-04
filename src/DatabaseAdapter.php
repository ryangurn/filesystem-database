<?php

namespace Ryangurnick\FilesystemDatabase;

use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Ryangurnick\FilesystemDatabase\Models\Binary;

class DatabaseAdapter implements FilesystemAdapter
{
    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        $this->validatePath($path);

        return Binary::where('name', $path)->count() == 1;
    }

    /**
     * @inheritDoc
     */
    public function directoryExists(string $path): bool
    {
        // since we are storing files in the database there is no directory structure
        return false;
    }

    /**
     * @inheritDoc
     */
    public function write(string $path, string $contents, Config $config): void
    {
        $this->validatePath($path);

        $writeStream = fopen('php://temp', 'w+b');
        fwrite($writeStream, $contents);
        rewind($writeStream);
        $this->writeStream($path, $writeStream, $config);
    }

    /**
     * @inheritDoc
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->validatePath($path);

        $content = stream_get_contents($contents);
        if (!$content)
            throw new UnableToWriteFile("Appears to be an empty file or unable to read the stream");

        // check if it already exists
        if (Binary::where('name', $path)
                ->count() > 0)
            throw new UnableToWriteFile("There is already a file at that path: {$path}");

        // create the new item
        $binary = new Binary();
        $binary->hash = Str::orderedUuid();
        $binary->name = $path;
        $binary->content = base64_encode($content);
        $binary->size = strlen($content);
        $binary->mime_type = mime_content_type($contents) ?? null;
        $binary->save();
    }

    /**
     * @inheritDoc
     */
    public function read(string $path): string
    {
        $this->validatePath($path);

        $binary = Binary::where('name', $path);

        if (($binary->count()) != 1)
            throw new UnableToReadFile("Cannot find the file {$path}");

        return base64_decode($binary->first()->content);
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
        $this->validatePath($path);
        $contents = $this->read($path);
        $writeStream = fopen('php://temp', 'w+b');
        fwrite($writeStream, $contents);
        rewind($writeStream);
        return $writeStream;
    }

    /**
     * @inheritDoc
     */
    public function delete(string $path): void
    {
        $this->validatePath($path);

        Binary::where('name', $path)
            ->delete();
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        $this->validatePath($path);

        Binary::where('name', $path)
            ->delete();
    }

    /**
     * @inheritDoc
     */
    public function createDirectory(string $path, Config $config): void
    {
        throw UnableToCreateDirectory::atLocation($path, 'Adapter does not support directories outside a file path, add a file instead.');
    }

    /**
     * @inheritDoc
     */
    public function setVisibility(string $path, string $visibility): void
    {
        throw UnableToSetVisibility::atLocation($path, 'Adapter does not support visibility controls.');
    }

    /**
     * @inheritDoc
     */
    public function visibility(string $path): FileAttributes
    {
        throw UnableToSetVisibility::atLocation($path, 'Adapter does not support visibility controls.');
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        $this->validatePath($path);

        $binary = Binary::where('name', $path);

        if (($binary->count()) != 1)
            throw new UnableToReadFile("Cannot find the file {$path}");

        return new FileAttributes(
            $path,
            null,
            null,
            null,
            $binary->mime_type
        );
    }

    /**
     * @inheritDoc
     */
    public function lastModified(string $path): FileAttributes
    {
        $this->validatePath($path);

        $binary = Binary::where('name', $path);

        if (($binary->count()) != 1)
            throw new UnableToReadFile("Cannot find the file {$path}");

        return new FileAttributes(
            $path,
            null,
            null,
            $binary->updated_at
        );
    }

    /**
     * @inheritDoc
     */
    public function fileSize(string $path): FileAttributes
    {
        $this->validatePath($path);

        $binary = Binary::where('name', $path);

        if (($binary->count()) != 1)
            throw new UnableToReadFile("Cannot find the file {$path}");

        return new FileAttributes(
            $path,
            $binary->size);
    }

    /**
     * @inheritDoc
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $this->validatePath($path);

        $binary = Binary::where('name', $path)
            ->get();

        if ($binary->count() <= 0)
            throw new UnableToListContents("Cannot find the path {$path}");

        $retArr = [];
        foreach ($binary as $b) {
            $retArr[] = (new FileAttributes(
                $b->name,
                $b->size ?? null,
                null,
                $b->updated_at->timestamp,
                $b->mime_type
            ));
        }

        return $retArr;
    }

    /**
     * @inheritDoc
     */
    public function move(string $source, string $destination, Config $config): void
    {
        // validate
        $this->validatePath($source);
        $this->validatePath($destination);

        // find the record(s)
        $srcBinary = Binary::where('name', $source);
        $dstBinary = Binary::where('name', $destination);

        // are there collisions
        if ($dstBinary->count() != 0)
            throw UnableToMoveFile::fromLocationTo($source, $destination);

        // are there any things to move...
        if ($srcBinary->count() == 0)
            throw UnableToMoveFile::fromLocationTo($source, $destination);

        // update name
        $srcBinary->update(['name' => $destination]);
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        // validate
        $this->validatePath($source);
        $this->validatePath($destination);

        // find the record(s)
        $srcBinary = Binary::where('name', $source);
        $dstBinary = Binary::where('name', $destination);

        // are there collisions
        if ($dstBinary->count() != 0)
            throw UnableToCopyFile::fromLocationTo($source, $destination);

        // are there any things to move...
        if ($srcBinary->count() == 0)
            throw UnableToCopyFile::fromLocationTo($source, $destination);

        $copy = $srcBinary->first()->replicate();
        $copy->name = $destination;
        $copy->save();
    }

    protected function validatePath(string $path)
    {
        preg_match('/^[a-zA-Z0-9]*[.][a-zA-Z0-9]*$/', $path, $output_array);
        if (count($output_array) == 0) {
            if (!str_contains($path, '.') && !str_contains($path, '/'))
            {
                throw new \InvalidArgumentException("This adapter requires an extension for the file: {$path}");
            }
            throw new \InvalidArgumentException("This adapter does not support folders in the path: {$path}");
        }
    }
}
