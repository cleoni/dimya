<?php
declare(strict_types=1);

namespace Dimx;

/**
 * ManageableObject
 *
 * Base class for all scriptable entities and data structures in the DimensioneX engine.
 * Provides dynamic property get/set and dynamic method invocation without heavy reflection.
 */
class ManageableObject
{
    /** @var array<string, mixed> Dynamic properties dictionary */
    protected array $properties = [];

    /** @var array<string, callable> Dynamic methods registry */
    protected array $methods = [];

    public function __construct()
    {
    }

    /**
     * Get dynamic or declared property.
     */
    public function getProperty(string $name): mixed
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        }
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        return null;
    }

    /**
     * Set dynamic or declared property.
     */
    public function setProperty(string $name, mixed $value): mixed
    {
        if (property_exists($this, $name)) {
            $this->{$name} = $value;
        } else {
            $this->properties[$name] = $value;
        }
        return $value;
    }

    /**
     * Check if a property exists.
     */
    public function varExists(string $name): bool
    {
        return array_key_exists($name, $this->properties) || property_exists($this, $name);
    }

    /**
     * Magic getter for properties.
     */
    public function __get(string $name): mixed
    {
        return $this->getProperty($name);
    }

    /**
     * Magic setter for properties.
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setProperty($name, $value);
    }

    /**
     * Magic isset for properties.
     */
    public function __isset(string $name): bool
    {
        return $this->varExists($name);
    }

    /**
     * Magic unset for properties.
     */
    public function __unset(string $name): void
    {
        unset($this->properties[$name]);
    }

    /**
     * Register a dynamic callable method.
     */
    public function registerMethod(string $name, callable $handler): void
    {
        $this->methods[strtolower($name)] = $handler;
    }

    /**
     * Check if a method is available on this object.
     */
    public function hasMethod(string $name): bool
    {
        return method_exists($this, $name) || isset($this->methods[strtolower($name)]);
    }

    /**
     * Execute a method dynamically with parameters.
     */
    public function execMethod(string $name, array $params = []): mixed
    {
        $lower = strtolower($name);
        if (isset($this->methods[$lower])) {
            return ($this->methods[$lower])($this, ...$params);
        }
        if (method_exists($this, $name)) {
            return $this->{$name}(...$params);
        }
        throw new \BadMethodCallException("The game engine cannot handle method: {$name} on " . static::class);
    }

    /**
     * Magic call for dynamic methods.
     */
    public function __call(string $name, array $arguments): mixed
    {
        return $this->execMethod($name, $arguments);
    }

    /**
     * Return all dynamic properties as associative array.
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Set multiple properties from a comma-separated string or array (e.g. "open=0,locked,Health=10")
     */
    public function setAttrList(string|array $attrList): void
    {
        if (is_array($attrList)) {
            foreach ($attrList as $k => $v) {
                $this->setProperty((string)$k, $v);
            }
            return;
        }

        $parts = explode(',', $attrList);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (strpos($part, '=') !== false) {
                [$k, $v] = explode('=', $part, 2);
                $k = trim($k);
                $v = trim($v);
                if (is_numeric($v)) {
                    $v = strpos($v, '.') !== false ? (float)$v : (int)$v;
                } elseif (strtolower($v) === 'true') {
                    $v = true;
                } elseif (strtolower($v) === 'false') {
                    $v = false;
                }
                $this->setProperty($k, $v);
            } else {
                $this->setProperty($part, true);
            }
        }
    }
}
