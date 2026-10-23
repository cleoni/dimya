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

    /** @var array<string, array<string, array{name: string, type: ?string, nullable: bool, isClass: bool}>> */
    private static array $classPropMetadata = [];

    public function __construct()
    {
    }

    /**
     * Get or build cached reflection metadata for class properties.
     * Maps lowercase property names to their declared name and typing rules.
     *
     * @return array<string, array{name: string, type: ?string, nullable: bool, isClass: bool}>
     */
    private static function getClassPropertyMetaMap(string $className): array
    {
        if (isset(self::$classPropMetadata[$className])) {
            return self::$classPropMetadata[$className];
        }

        $map = [];
        $ref = new \ReflectionClass($className);
        foreach ($ref->getProperties() as $prop) {
            if ($prop->isStatic()) {
                continue;
            }
            $type = $prop->getType();
            $typeName = null;
            $nullable = true;
            $isClass = false;

            if ($type instanceof \ReflectionNamedType) {
                $typeName = $type->getName();
                $nullable = $type->allowsNull();
                $isClass = class_exists($typeName) || interface_exists($typeName);
            }

            $map[strtolower($prop->getName())] = [
                'name'     => $prop->getName(),
                'type'     => $typeName,
                'nullable' => $nullable,
                'isClass'  => $isClass,
            ];
        }

        self::$classPropMetadata[$className] = $map;
        return $map;
    }

    /**
     * Get dynamic or declared property.
     */
    public function getProperty(string $name): mixed
    {
        if (array_key_exists($name, $this->properties)) {
            return $this->properties[$name];
        }

        $metaMap = self::getClassPropertyMetaMap(static::class);
        $lower = strtolower($name);
        if (isset($metaMap[$lower])) {
            $propName = $metaMap[$lower]['name'];
            return $this->{$propName} ?? null;
        }

        // Case-insensitive lookup in dynamic properties
        foreach ($this->properties as $k => $v) {
            if (strcasecmp((string)$k, $name) === 0) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Set dynamic or declared property with type coercion for typed declared properties.
     */
    public function setProperty(string $name, mixed $value): mixed
    {
        $metaMap = self::getClassPropertyMetaMap(static::class);
        $lower = strtolower($name);

        if (isset($metaMap[$lower])) {
            $meta = $metaMap[$lower];
            $propName = $meta['name'];
            $typeName = $meta['type'];

            if ($typeName !== null) {
                if ($value === null) {
                    if ($meta['nullable']) {
                        $this->{$propName} = null;
                        return null;
                    }
                } elseif ($typeName === 'bool') {
                    $coerced = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $this->{$propName} = $coerced ?? (bool)$value;
                    $this->properties[$name] = $this->{$propName};
                    return $this->{$propName};
                } elseif ($typeName === 'int') {
                    $this->{$propName} = (int)$value;
                    $this->properties[$name] = $this->{$propName};
                    return $this->{$propName};
                } elseif ($typeName === 'float') {
                    $this->{$propName} = (float)$value;
                    $this->properties[$name] = $this->{$propName};
                    return $this->{$propName};
                } elseif ($typeName === 'string') {
                    $this->{$propName} = (string)$value;
                    $this->properties[$name] = $this->{$propName};
                    return $this->{$propName};
                } elseif ($meta['isClass']) {
                    if ($value instanceof $typeName) {
                        $this->{$propName} = $value;
                        $this->properties[$name] = $value;
                        return $value;
                    }
                    // Non-compatible object/scalar assigned to typed object property; store dynamically
                    $this->properties[$name] = $value;
                    return $value;
                }
            }

            $this->{$propName} = $value;
            $this->properties[$name] = $value;
            return $value;
        }

        $this->properties[$name] = $value;
        return $value;
    }

    /**
     * Check if a property exists.
     */
    public function varExists(string $name): bool
    {
        if (array_key_exists($name, $this->properties)) {
            return true;
        }

        $metaMap = self::getClassPropertyMetaMap(static::class);
        if (isset($metaMap[strtolower($name)])) {
            return true;
        }

        foreach ($this->properties as $k => $v) {
            if (strcasecmp((string)$k, $name) === 0) {
                return true;
            }
        }

        return false;
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
