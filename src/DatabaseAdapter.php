<?php

namespace Ryangurnick\FilesystemDatabase;

use Illuminate\Support\Str;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToListContents;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use Ryangurnick\FilesystemDatabase\Models\Binary;

class DatabaseAdapter implements FilesystemAdapter
{

    protected string $dir;

    protected string $name;

    /**
     * @inheritDoc
     */
    public function fileExists(string $path): bool
    {
        return Binary::where('path', $path)->count() == 1;
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
        $this->splitPath($path);
        $content = stream_get_contents($contents);
        if (!$content)
            throw new UnableToWriteFile("Appears to be an empty file or unable to read the stream");

        // check if it already exists
        if (Binary::where('path', $this->dir)
                ->where('name', $this->name)
                ->count() > 0)
            throw new UnableToWriteFile("There is already a file with that name {$this->name} at the path {$this->dir}");

        // create the new item
        $binary = new Binary();
        $binary->hash = Str::orderedUuid();
        $binary->path = $this->dir;
        $binary->name = $this->name;
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
        $this->splitPath($path);

        $binary = Binary::where('path', $this->dir)
            ->where('name', $this->name);

        if (($binary->count()) != 1)
            throw new UnableToReadFile("Cannot find the file {$path}");

        return base64_decode($binary->first()->content);
    }

    /**
     * @inheritDoc
     */
    public function readStream(string $path)
    {
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
        $this->splitPath($path);

        Binary::where('path', $this->dir)
            ->where('name', $this->name)
            ->delete();
    }

    /**
     * @inheritDoc
     */
    public function deleteDirectory(string $path): void
    {
        $this->splitPath($path);

        Binary::where('path', $this->dir)
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
        return new FileAttributes($path);
    }

    /**
     * @inheritDoc
     */
    public function mimeType(string $path): FileAttributes
    {
        $this->splitPath($path);

        $binary = Binary::where('path', $this->dir)
            ->where('name', $name);

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
        $this->splitPath($path);

        $binary = Binary::where('path', $this->dir)
            ->where('name', $name);

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
        $this->splitPath($path);

        $binary = Binary::where('path', $this->dir)
            ->where('name', $name);

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
        $this->splitPath($path);

        if ($this->dir === '.') {
            $binary = Binary::where('path', $this->name)
                ->get();
        } else {
            $binary = Binary::where('path', $this->dir)
                ->get();
        }

        if ($binary->count() <= 0)
            throw new UnableToListContents("Cannot find the path {$path}");

        $retArr = [];
        foreach ($binary as $b) {
            $retArr[] = (new FileAttributes(
                $b->path,
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
        $src = $this->splitPath($source);
        $dst = $this->splitPath($destination);

        dd($src, $dst);

        // verify both files or both folders
//        if (($src['dir'] != '.' && $dst['dir'] != '.') || ($src['dir'] != '.' && $src['name'] != ))

        // find the record(s)
        if ($this->dir === '.') {
            $binary = Binary::where('path', $this->name)
                ->get();
        } else {
            $binary = Binary::where('path', $this->dir)
                ->where('name', $this->name)
                ->get();
        }

        dd($this->dir, $this->name, $binary);
    }

    /**
     * @inheritDoc
     */
    public function copy(string $source, string $destination, Config $config): void
    {
        // TODO: Implement copy() method.
    }

    protected function splitPath(string $path)
    {
        $this->dir = dirname($path);
        $this->name = basename($path);
        return ['dir' => $this->dir, 'name' => $this->name];
    }
}
