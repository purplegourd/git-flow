<?php declare(strict_types=1);
namespace Phan\Language\FQSEN;

use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\Type;

/**
 * A Fully-Qualified Global Structural Element
 */
abstract class FullyQualifiedGlobalStructuralElement extends AbstractFQSEN
{
    use \Phan\Language\FQSEN\Alternatives;
    use \Phan\Memoize;

    /**
     * @var string
     * The namespace in this elements scope
     */
    private $namespace = '\\';

    /**
     * @param string $namespace
     * The namespace in this element's scope
     *
     * @param string $name
     * The name of this structural element
     *
     * @param int $alternate_id
     * An alternate ID for the elemnet for use when
     * there are multiple definitions of the element
     */
    protected function __construct(
        string $namespace,
        string $name,
        int $alternate_id = 0
    ) {
        assert(!empty($name), "The name cannot be empty");

        assert(!empty($namespace), "The namespace cannot be empty");

        assert(
            $namespace[0] === '\\',
            "The first character of a namespace must be \\"
        );

        parent::__construct($name);
        $this->namespace = $namespace;
        $this->alternate_id = $alternate_id;
    }

    /**
     * @param string $namespace
     * The namespace in this element's scope
     *
     * @param string $name
     * The name of this structural element
     *
     * @param int $alternate_id
     * An alternate ID for the elemnet for use when
     * there are multiple definitions of the element
     *
     * @return static
     */
    public static function make(
        string $namespace,
        string $name,
        int $alternate_id = 0
    ) {

        // Transfer any relative namespace stuff from the
        // name to the namespace.
        $name_parts= explode('\\', $name);
        $name = array_pop($name_parts);
        $namespace = implode('\\', array_merge([$namespace], $name_parts));
        $namespace = self::cleanNamespace($namespace);

        $key = strtolower(implode('|', [
            get_called_class(),
            static::toString($namespace, $name, $alternate_id)
        ]));

        $fqsen = self::memoizeStatic($key, function () use ($namespace, $name, $alternate_id) {
            return new static(
                $namespace,
                $name,
                $alternate_id
            );
        });

        return $fqsen;
    }

    /**
     * @param $fully_qualified_string
     * An fully qualified string like '\Namespace\Class'
     *
     * @return static
     */
    public static function fromFullyQualifiedString(
        string $fully_qualified_string
    ) {

        $key = get_called_class() . '|' . $fully_qualified_string;

        return self::memoizeStatic($key, function () use ($fully_qualified_string) {

            // Split off the alternate_id
            $parts = explode(',', $fully_qualified_string);
            $fqsen_string = $parts[0];
            $alternate_id = (int)($parts[1] ?? 0);

            assert(
                is_int($alternate_id),
                "Alternate must be an integer"
            );

            $parts = explode('\\', $fqsen_string);
            $name = array_pop($parts);

            assert(!empty($name), "The name cannot be empty");

            $namespace = '\\' . implode('\\', array_filter($parts));

            assert(!empty($namespace), "The namespace cannot be empty");

            assert(
                $namespace[0] === '\\',
                "The first character of the namespace must be \\"
            );

            return static::make(
                $namespace,
                $name,
                $alternate_id
            );
        });
    }

    /**
     * @param Context $context
     * The context in which the FQSEN string was found
     *
     * @param $fqsen_string
     * An FQSEN string like '\Namespace\Class'
     *
     * @return static
     */
    public static function fromStringInContext(
        string $fqsen_string,
        Context $context
    ) {
        // Check to see if we're fully qualified
        if (0 === strpos($fqsen_string, '\\')) {
            return static::fromFullyQualifiedString($fqsen_string);
        }

        // Split off the alternate ID
        $parts = explode(',', $fqsen_string);
        $fqsen_string = $parts[0];
        $alternate_id = (int)($parts[1] ?? 0);

        assert(
            is_int($alternate_id),
            "Alternate must be an integer"
        );

        $parts = explode('\\', $fqsen_string);
        $name = array_pop($parts);

        assert(!empty($name), "The name cannot be empty");

        // Check for a name map
        if ($context->hasNamespaceMapFor(static::getNamespaceMapType(), $name)) {
            return $context->getNamespaceMapFor(
                static::getNamespaceMapType(),
                $name
            );
        }

        $namespace = implode('\\', array_filter($parts));

        // n.b.: Functions must override this method because
        //       they don't prefix the namespace for naked
        //       calls
        if (empty($namespace)) {
            $namespace = $context->getNamespace();
        }

        return static::make(
            $namespace,
            $name,
            $alternate_id
        );
    }

    /**
     * @return int
     * The namespace map type such as \ast\flags\USE_NORMAL or \ast\flags\USE_FUNCTION
     */
    abstract protected static function getNamespaceMapType() : int;

    /**
     * @return string
     * The namespace associated with this FQSEN
     * or null if not defined
     */
    public function getNamespace() : string
    {
        return $this->namespace;
    }

    /**
     * @return static
     */
    public function withNamespace(
        string $namespace
    ) {
        return static::make(
            self::cleanNamespace($namespace),
            $this->getName(),
            $this->getAlternateId()
        );
    }

    /**
     * @return static
     * A FQSEN with the given alternate_id set
     */
    public function withAlternateId(
        int $alternate_id
    ) {
        if ($this->getAlternateId() === $alternate_id) {
            return $this;
        }

        assert(
            $alternate_id < 1000,
            "Your alternate IDs have run away"
        );

        return static::make(
            $this->getNamespace(),
            $this->getName(),
            $alternate_id
        );
    }

    /**
     * @param string|null $namespace
     *
     * @return string
     * A cleaned version of the given namespace such that
     * its always prefixed with a '\' and never ends in a
     * '\', and is the string "\" if there is no namespace.
     */
    protected static function cleanNamespace(string $namespace) : string
    {
        if (!$namespace
            || empty($namespace)
            || $namespace === '\\'
        ) {
            return '\\';
        }

        // Ensure that the first character of the namespace
        // is always a '\'
        if (0 !== strpos($namespace, '\\')) {
            $namespace = '\\' . $namespace;
        }

        // Ensure that we don't have a trailing '\' on the
        // namespace
        if ('\\' === substr($namespace, -1)) {
            $namespace = substr($namespace, 0, -1);
        }

        return $namespace;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public static function toString(
        string $namespace,
        string $name,
        int $alternate_id
    ) : string {
        $fqsen_string = $namespace;

        if ($fqsen_string && $fqsen_string !== '\\') {
            $fqsen_string .= '\\';
        }

        $fqsen_string .= static::canonicalName($name);

        // Append an alternate ID if we need to disambiguate
        // multiple definitions
        if ($alternate_id) {
            $fqsen_string .= ',' . $alternate_id;
        }

        return $fqsen_string;
    }

    /**
     * @return string
     * A string representation of this fully-qualified
     * structural element name.
     */
    public function __toString() : string
    {
        return $this->memoize(__METHOD__, function () {
            return static::toString(
                $this->getNamespace(),
                $this->getName(),
                $this->getAlternateId()
            );
        });
    }
}
