<?php

namespace Hanafalah\LaravelSupport\Concerns\Support;

use Hanafalah\LaravelSupport\Exceptions;

trait HasRepository
{
    use HasJson, HasLocalDir;
    use HasClassModifier;

    protected string $__asset_path = '../assets/';

    /** @var FileRepositoryInterface */
    protected $__file_repository;

    protected $__loader;

    public static function new()
    {
        return new static();
    }

    /**
     * Set the file repository implementation.
     *
     * @param  \Hanafalah\LaravelSupport\Contracts\FileRepositoryInterface  $repository
     * @return $this
     */
    public function setRepository($repository): self
    {
        $this->__file_repository = app($repository);
        return $this;
    }

    /**
     * Get the file repository instance.
     *
     * @return \Hanafalah\LaravelSupport\Contracts\FileRepositoryInterface
     */
    public function repo()
    {
        return $this->__file_repository;
    }

    /**
     * Checks if the schema class is set and throws an exception if it is not.
     *
     * @throws SchemaClassNotSetException if the schema class is not set
     * @return bool true if the schema class is set, false otherwise
     */
    public function isSetSchemaThrow(): bool
    {
        $isset = $this->isSetSchema();
        if (!$isset) throw new Exceptions\SchemaClassNotSet('Schema class not set !');
        return $isset;
    }

    /**
     * Checks if the given string is not empty.
     *
     * @param string $value The string to check.
     * @return bool True if the string is not empty, false otherwise.
     */
    public function isNotEmptyString(string $value): bool
    {
        return $value !== '';
    }

    /**
     * Checks if the schema class is set.
     *
     * @return bool Returns true if the schema class is set, false otherwise.
     */
    public function isSetSchema(): bool
    {
        // if (\method_exists($this,'booting')){
        //     $this->booting();
        // }
        return isset(static::$__class);
    }

    /**
     * Checks if a method exists in the current class.
     *
     * @param string $method The name of the method to check.
     * @return bool Returns true if the method exists, false otherwise.
     */
    private function isMethodExists(string $method, $class = null): bool
    {
        return \method_exists($class ?? self::$__class, $method);
    }

    /**
     * Creates a directory recursively.
     *
     * @param string $path The path of the directory to create.
     * @return bool True if the directory has been created successfully, false otherwise.
     */
    protected function makeDir(string $relative_path): string{
        if (!$this->isDir($relative_path)) mkdir($relative_path, 0777, true);
        return $relative_path;
    }

    /**
     * Checks if the given path is a directory.
     *
     * @param string $path The path to check.
     * @return bool True if the path is a directory, false otherwise.
     */
    protected function isDir(string $path): bool
    {
        return is_dir($path);
    }

    /**
     * Checks if the given path is a file.
     *
     * @param string $path The path to check.
     * @return bool True if the path is a file, false otherwise.
     */
    protected function isFile(string $path): bool
    {
        return \is_file($path);
    }

    /**
     * Checks if the given path exists.
     *
     * @param string $path The path to check.
     * @return bool True if the path exists, false otherwise.
     */
    protected function isFileExists(string $path): bool
    {
        return \file_exists($path);
    }

    /**
     * A description of the entire PHP function.
     *
     * @param datatype $value description
     * @return Some_Return_Value
     */
    public function toObject($value)
    {
        return is_object($value) ? $value : (object) $value;
    }

    /**
     * Retrieves the path to the asset directory.
     *
     * @return string The path to the asset directory.
     */
    protected function getAssetPath(string $path = ''): string
    {
        return $this->dir() . $this->__asset_path . $path;
    }
}
