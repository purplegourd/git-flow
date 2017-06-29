<?php declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Analysis\CompositionAnalyzer;
use Phan\Analysis\DuplicateClassAnalyzer;
use Phan\Analysis\ParentClassExistsAnalyzer;
use Phan\Analysis\ParentConstructorCalledAnalyzer;
use Phan\Analysis\PropertyTypesAnalyzer;
use Phan\CodeBase;
use Phan\Config;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassConstantName;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;
use Phan\Language\FQSEN\FullyQualifiedPropertyName;
use Phan\Language\Scope\ClassScope;
use Phan\Language\Scope\GlobalScope;
use Phan\Language\Type;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\Library\None;
use Phan\Library\Option;
use Phan\Library\Some;
use Phan\Plugin\ConfigPluginSet;

class Clazz extends AddressableElement
{
    use \Phan\Memoize;
    use ClosedScopeElement;

    /**
     * @var Type|null
     * The type of the parent of this class if it extends
     * anything, else null.
     */
    private $parent_type = null;

    /**
     * @var \Phan\Language\FQSEN[]
     * A possibly empty list of interfaces implemented
     * by this class
     */
    private $interface_fqsen_list = [];

    /**
     * @var \Phan\Language\FQSEN[]
     * A possibly empty list of traits used by this class
     */
    private $trait_fqsen_list = [];

    /**
     * @param Context $context
     * The context in which the structural element lives
     *
     * @param string $name,
     * The name of the typed structural element
     *
     * @param UnionType $type,
     * A '|' delimited set of types satisfied by this
     * typed structural element.
     *
     * @param int $flags,
     * The flags property contains node specific flags. It is
     * always defined, but for most nodes it is always zero.
     * ast\kind_uses_flags() can be used to determine whether
     * a certain kind has a meaningful flags value.
     *
     * @param FullyQualifiedClassName $fqsen
     * A fully qualified name for this class
     *
     * @param Type|null $parent_type
     * @param FullyQualifiedClassName[]|null $interface_fqsen_list
     * @param FullyQualifiedClassName[]|null $trait_fqsen_list
     */
    public function __construct(
        Context $context,
        string $name,
        UnionType $type,
        int $flags,
        FullyQualifiedClassName $fqsen,
        Type $parent_type = null,
        array $interface_fqsen_list = [],
        array $trait_fqsen_list = []
    ) {
        parent::__construct(
            $context,
            $name,
            $type,
            $flags,
            $fqsen
        );

        $this->parent_type = $parent_type;
        $this->interface_fqsen_list = $interface_fqsen_list;
        $this->trait_fqsen_list = $trait_fqsen_list;

        $this->setInternalScope(new ClassScope(
            $context->getScope(),
            $fqsen
        ));
    }

    /**
     * @param CodeBase $code_base
     * A reference to the entire code base in which this
     * context exists
     *
     * @param string $class_name
     * The name of a builtin class to build a new Class structural
     * element from.
     *
     * @return Clazz
     * A Class structural element representing the given named
     * builtin.
     */
    public static function fromClassName(
        CodeBase $code_base,
        string $class_name
    ) : Clazz {
        return self::fromReflectionClass(
            $code_base,
            new \ReflectionClass($class_name)
        );
    }

    /**
     * @param CodeBase $code_base
     * A reference to the entire code base in which this
     * context exists
     *
     * @param ReflectionClass $class
     * A reflection class representing a builtin class.
     *
     * @return Clazz
     * A Class structural element representing the given named
     * builtin.
     */
    public static function fromReflectionClass(
        CodeBase $code_base,
        \ReflectionClass $class
    ) : Clazz {
        // Build a set of flags based on the constitution
        // of the built-in class
        $flags = 0;
        if ($class->isFinal()) {
            $flags = \ast\flags\CLASS_FINAL;
        } elseif ($class->isInterface()) {
            $flags = \ast\flags\CLASS_INTERFACE;
        } elseif ($class->isTrait()) {
            $flags = \ast\flags\CLASS_TRAIT;
        }
        if ($class->isAbstract()) {
            $flags |= \ast\flags\CLASS_ABSTRACT;
        }

        $context = new Context;

        $class_fqsen = FullyQualifiedClassName::fromStringInContext(
            $class->getName(),
            $context
        );

        // Build a base class element
        $clazz = new Clazz(
            $context,
            $class->getName(),
            UnionType::fromStringInContext($class->getName(), $context),
            $flags,
            $class_fqsen
        );

        // If this class has a parent class, add it to the
        // class info
        if (($parent_class = $class->getParentClass())) {

            $parent_class_fqsen =
                FullyQualifiedClassName::fromFullyQualifiedString(
                    '\\' . $parent_class->getName()
                );

            $parent_type = $parent_class_fqsen->asType();

            $clazz->setParentType($parent_type);
        }

        // n.b.: public properties on internal classes don't get
        //       listed via reflection until they're set unless
        //       they have a default value. Therefore, we don't
        //       bother iterating over `$class->getProperties()`
        //       `$class->getStaticProperties()`.

        foreach ($class->getDefaultProperties() as $name => $value) {
            $property_context = $context->withScope(
                new ClassScope(new GlobalScope, $clazz->getFQSEN())
            );

            $property_fqsen = FullyQualifiedPropertyName::make(
                $clazz->getFQSEN(),
                $name
            );

            $property = new Property(
                $property_context,
                $name,
                Type::fromObject($value)->asUnionType(),
                0,
                $property_fqsen
            );

            $clazz->addProperty($code_base, $property, new None);
        }

        foreach (UnionType::internalPropertyMapForClassName(
            $clazz->getName()
        ) as $property_name => $property_type_string) {
            $property_context = $context->withScope(
                new ClassScope(new GlobalScope, $clazz->getFQSEN())
            );

            $property_type =
                UnionType::fromStringInContext(
                    $property_type_string,
                    new Context
                );

            $property_fqsen = FullyQualifiedPropertyName::make(
                $clazz->getFQSEN(),
                $property_name
            );

            $property = new Property(
                $property_context,
                $property_name,
                $property_type,
                0,
                $property_fqsen
            );

            $clazz->addProperty($code_base, $property, new None);
        }

        foreach ($class->getInterfaceNames() as $name) {
            $clazz->addInterfaceClassFQSEN(
                FullyQualifiedClassName::fromFullyQualifiedString(
                    '\\' . $name
                )
            );
        }

        foreach ($class->getTraitNames() as $name) {
            $clazz->addTraitFQSEN(
                FullyQualifiedClassName::fromFullyQualifiedString(
                    '\\' . $name
                )
            );
        }

        foreach ($class->getConstants() as $name => $value) {
            $constant_fqsen = FullyQualifiedClassConstantName::make(
                $clazz->getFQSEN(),
                $name
            );

            $constant = new ClassConstant(
                $context,
                $name,
                Type::fromObject($value)->asUnionType(),
                0,
                $constant_fqsen
            );

            $clazz->addConstant($code_base, $constant);
        }

        foreach ($class->getMethods() as $reflection_method) {

            $method_context = $context->withScope(
                new ClassScope(new GlobalScope, $clazz->getFQSEN())
            );

            $method_list =
                FunctionFactory::methodListFromReflectionClassAndMethod(
                    $method_context,
                    $code_base,
                    $class,
                    $reflection_method
                );

            foreach ($method_list as $method) {
                $clazz->addMethod($code_base, $method, new None);
            }
        }

        return $clazz;
    }

    /**
     * @param Type|null $parent_type
     * The type of the parent (extended) class of this class.
     *
     * @return void
     */
    public function setParentType(Type $parent_type = null)
    {
        if ($this->getInternalScope()->hasAnyTemplateType()) {

            // Get a reference to the local list of templated
            // types. We'll use this to map templated types on the
            // parent to locally templated types.
            $template_type_map =
                $this->getInternalScope()->getTemplateTypeMap();

            // Figure out if the given parent type contains any template
            // types.
            $contains_templated_type = false;
            foreach ($parent_type->getTemplateParameterTypeList() as $i => $union_type) {
                foreach ($union_type->getTypeSet() as $type) {
                    if (isset($template_type_map[$type->getName()])) {
                        $contains_templated_type = true;
                        break 2;
                    }
                }
            }

            // If necessary, map the template parameter type list through the
            // local list of templated types.
            if ($contains_templated_type) {
                $parent_type = Type::fromType(
                    $parent_type,
                    array_map(function (UnionType $union_type) use ($template_type_map) : UnionType {
                        return new UnionType(
                            array_map(function (Type $type) use ($template_type_map) : Type {
                                return $template_type_map[$type->getName()] ?? $type;
                            }, $union_type->getTypeSet()->toArray())
                        );
                    }, $parent_type->getTemplateParameterTypeList())
                );
            }
        }

        $this->parent_type = $parent_type;

        // Add the parent to the union type of this
        // class
        $this->getUnionType()->addUnionType(
            $parent_type->asUnionType()
        );
    }

    /**
     * @return bool
     * True if this class has a parent class
     */
    public function hasParentType() : bool
    {
        return !empty($this->parent_type);
    }

    /**
     * @return Option<Type>
     * If a parent type is defined, get Some<Type>, else None.
     */
    public function getParentTypeOption()
    {
        if ($this->hasParentType()) {
            return new Some($this->parent_type);
        }

        return new None;
    }

    /**
     * @return FQSEN
     * The parent class of this class if one exists
     *
     * @throws \Exception
     * An exception is thrown if this class has no parent
     */
    public function getParentClassFQSEN() : FullyQualifiedClassName
    {
        $parent_type_option = $this->getParentTypeOption();

        if (!$parent_type_option->isDefined()) {
            throw new \Exception("Class $this has no parent");
        }

        return $parent_type_option->get()->asFQSEN();
    }

    /**
     * @return Clazz
     * The parent class of this class if defined
     *
     * @throws \Exception
     * An exception is thrown if this class has no parent
     */
    public function getParentClass(CodeBase $code_base) : Clazz
    {
        $parent_type_option = $this->getParentTypeOption();

        if (!$parent_type_option->isDefined()) {
            throw new \Exception("Class $this has no parent");
        }

        $parent_fqsen = $parent_type_option->get()->asFQSEN();
        assert($parent_fqsen instanceof FullyQualifiedClassName);

        return $code_base->getClassByFQSEN(
            $parent_fqsen
        );
    }

    public function isSubclassOf(CodeBase $code_base, Clazz $other) : bool
    {
        if (!$this->hasParentType()) {
            return false;
        }

        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            // Let this emit an issue elsewhere for the
            // parent not existing
            return false;
        }

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        if ($parent === $other) {
            return true;
        }

        return $parent->isSubclassOf($code_base, $other);
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return int
     * This class's depth in the class hierarchy
     */
    public function getHierarchyDepth(CodeBase $code_base) : int
    {
        if (!$this->hasParentType()) {
            return 0;
        }

        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            // Let this emit an issue elsewhere for the
            // parent not existing
            return 0;
        }

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        // Prevent infinite loops
        if ($parent == $this) {
            return 0;
        }

        return (1 + $parent->getHierarchyDepth($code_base));
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return FullyQualifiedClassName
     * The FQSEN of the root class on this class's hiearchy
     */
    public function getHierarchyRootFQSEN(
        CodeBase $code_base
    ) : FullyQualifiedClassName {
        if (!$this->hasParentType()) {
            return $this->getFQSEN();
        }

        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            // Let this emit an issue elsewhere for the
            // parent not existing
            return $this->getFQSEN();
        }

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        // Prevent infinite loops
        if ($parent == $this) {
            return $this->getFQSEN();
        }

        return $parent->getHierarchyRootFQSEN($code_base);
    }

    /**
     * @param FQSEN $fqsen
     * Add the given FQSEN to the list of implemented
     * interfaces for this class
     *
     * @return null
     */
    public function addInterfaceClassFQSEN(FQSEN $fqsen)
    {
        $this->interface_fqsen_list[] = $fqsen;

        // Add the interface to the union type of this
        // class
        $this->getUnionType()->addUnionType(
            UnionType::fromFullyQualifiedString((string)$fqsen)
        );
    }

    /**
     * @return FQSEN[]
     * Get the list of interfaces implemented by this class
     */
    public function getInterfaceFQSENList() : array
    {
        return $this->interface_fqsen_list;
    }

    /**
     * Add a property to this class
     *
     * @param CodeBase $code_base
     * A reference to the code base in which the ancestor exists
     *
     * @param Property $property
     * The property to copy onto this class
     *
     * @param Option<Type>|None $type_option
     * A possibly defined type used to define template
     * parameter types when importing the property
     *
     * @return void
     */
    public function addProperty(
        CodeBase $code_base,
        Property $property,
        $type_option
    ) {
        // Ignore properties we already have
        if ($this->hasPropertyWithName($code_base, $property->getName())) {
            return;
        }

        $property_fqsen = FullyQualifiedPropertyName::make(
            $this->getFQSEN(),
            $property->getName()
        );

        if ($property->getFQSEN() !== $property_fqsen) {
            $property = clone($property);
            $property->setDefiningFQSEN($property->getFQSEN());
            $property->setFQSEN($property_fqsen);

            try {
                // If we have a parent type defined, map the property's
                // type through it
                if ($type_option->isDefined()
                    && $property->getUnionType()->hasTemplateType()
                ) {
                    $property->setUnionType(
                        $property->getUnionType()->withTemplateParameterTypeMap(
                            $type_option->get()->getTemplateParameterTypeMap(
                                $code_base
                            )
                        )
                    );
                }
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $property->getContext(),
                    $exception->getIssueInstance()
                );
            }

        }

        $code_base->addProperty($property);
    }

    /**
     * @return bool
     */
    public function hasPropertyWithName(
        CodeBase $code_base,
        string $name
    ) : bool {
        return $code_base->hasPropertyWithFQSEN(
            FullyQualifiedPropertyName::make(
                $this->getFQSEN(),
                $name
            )
        );
    }

    /**
     * @return Property[]
     * The list of properties defined on this class
     */
    public function getPropertyList(
        CodeBase $code_base
    ) {
        return $code_base->getPropertyMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * @param string $name
     * The name of the property
     *
     * @param Context $context
     * The context of the caller requesting the property
     *
     * @return Property
     * A property with the given name
     *
     * @throws IssueException
     * An exception may be thrown if the caller does not
     * have access to the given property from the given
     * context
     */
    public function getPropertyByNameInContext(
        CodeBase $code_base,
        string $name,
        Context $context
    ) : Property {

        // Get the FQSEN of the property we're looking for
        $property_fqsen = FullyQualifiedPropertyName::make(
            $this->getFQSEN(), $name
        );

        $property = null;

        // Figure out if we have the property
        $has_property =
            $code_base->hasPropertyWithFQSEN($property_fqsen);

        // Figure out if the property is accessible
        $is_property_accessible = false;
        if ($has_property) {
            $property = $code_base->getPropertyByFQSEN(
                $property_fqsen
            );

            $is_remote_access = (
                !$context->isInClassScope()
                || !$context->getClassInScope($code_base)
                    ->getUnionType()->canCastToExpandedUnionType(
                        $this->getUnionType(),
                        $code_base
                    )
            );

            $is_property_accessible = (
                !$is_remote_access
                || $property->isPublic()
            );
        }

        // If the property exists and is accessible, return it
        if ($is_property_accessible) {
            return $property;
        }

        // Check to see if we can use a __get magic method
        if ($this->hasMethodWithName($code_base, '__get')) {
            $method = $this->getMethodByName($code_base, '__get');

            // Make sure the magic method is accessible
            if ($method->isPrivate()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyPrivate)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ (string)$property_fqsen ]
                    )
                );
            } else if ($method->isProtected()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyProtected)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ (string)$property_fqsen ]
                    )
                );
            }

            $property = new Property(
                $context,
                $name,
                $method->getUnionType(),
                0,
                $property_fqsen
            );

            $this->addProperty($code_base, $property, new None);

            return $property;

        } else if ($has_property) {

            // If we have a property, but its inaccessible, emit
            // an issue
            if ($property->isPrivate()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyPrivate)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ "{$this->getFQSEN()}::\${$property->getName()}" ]
                    )
                );
            }
            if ($property->isProtected()) {
                throw new IssueException(
                    Issue::fromType(Issue::AccessPropertyProtected)(
                        $context->getFile(),
                        $context->getLineNumberStart(),
                        [ "{$this->getFQSEN()}::\${$property->getName()}" ]
                    )
                );
            }
        }

        // Check to see if missing properties are allowed
        // or we're stdclass
        if (Config::get()->allow_missing_properties
            || $this->getFQSEN() == FullyQualifiedClassName::getStdClassFQSEN()
        ) {
            $property = new Property(
                $context,
                $name,
                new UnionType(),
                0,
                $property_fqsen
            );

            $this->addProperty($code_base, $property, new None);

            return $property;
        }

        throw new IssueException(
            Issue::fromType(Issue::UndeclaredProperty)(
                $context->getFile(),
                $context->getLineNumberStart(),
                [ "{$this->getFQSEN()}::\$$name}" ]
            )
        );
    }

    /**
     * @return Property[]
     * The list of properties on this class
     */
    public function getPropertyMap(CodeBase $code_base) : array
    {
        return $code_base->getPropertyMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * Add a class constant
     *
     * @return null;
     */
    public function addConstant(
        CodeBase $code_base,
        ClassConstant $constant
    ) {
        $constant_fqsen = FullyQualifiedClassConstantName::make(
            $this->getFQSEN(),
            $constant->getName()
        );

        // Update the FQSEN if its not associated with this
        // class yet
        if ($constant->getFQSEN() !== $constant_fqsen) {
            $constant = clone($constant);
            $constant->setFQSEN($constant_fqsen);
        }

        $code_base->addClassConstant($constant);
    }

    /**
     * @return bool
     * True if a constant with the given name is defined
     * on this class.
     */
    public function hasConstantWithName(
        CodeBase $code_base,
        string $name
    ) : bool {
        return $code_base->hasClassConstantWithFQSEN(
            FullyQualifiedClassConstantName::make(
                $this->getFQSEN(),
                $name
            )
        );
    }

    /**
     * @return ClassConstant
     * The class constant with the given name.
     */
    public function getConstantWithName(
        CodeBase $code_base,
        string $name
    ) : ClassConstant {
        return $code_base->getClassConstantByFQSEN(
            FullyQualifiedClassConstantName::make(
                $this->getFQSEN(),
                $name
            )
        );
    }

    /**
     * @return ClassConstant[]
     * The constants associated with this class
     */
    public function getConstantMap(CodeBase $code_base) : array
    {
        return $code_base->getClassConstantMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * Add a method to this class
     *
     * @param CodeBase $code_base
     * A reference to the code base in which the ancestor exists
     *
     * @param Method $method
     * The method to copy onto this class
     *
     * @param Option<Type>|None $type_option
     * A possibly defined type used to define template
     * parameter types when importing the method
     *
     * @return null
     */
    public function addMethod(
        CodeBase $code_base,
        Method $method,
        $type_option
    ) {
        $method_fqsen = FullyQualifiedMethodName::make(
            $this->getFQSEN(),
            $method->getName(),
            $method->getFQSEN()->getAlternateId()
        );

        // Don't overwrite overridden methods with
        // parent methods
        if ($code_base->hasMethodWithFQSEN($method_fqsen)) {

            // Note that we're overriding something
            $existing_method =
                $code_base->getMethodByFQSEN($method_fqsen);
            $existing_method->setIsOverride(true);

            // Don't add the method
            return;
        }

        if ($method->getFQSEN() !== $method_fqsen) {
            $method = clone($method);
            $method->setDefiningFQSEN($method->getFQSEN());
            $method->setFQSEN($method_fqsen);

            // If we have a parent type defined, map the method's
            // return type and parameter types through it
            if ($type_option->isDefined()) {

                // Map the method's return type
                if ($method->getUnionType()->hasTemplateType()) {
                    $method->setUnionType(
                        $method->getUnionType()->withTemplateParameterTypeMap(
                            $type_option->get()->getTemplateParameterTypeMap(
                                $code_base
                            )
                        )
                    );
                }

                // Map each method parameter
                $method->setParameterList(
                    array_map(function (Parameter $parameter) use ($type_option, $code_base) : Parameter {

                        if (!$parameter->getUnionType()->hasTemplateType()) {
                            return $parameter;
                        }

                        $mapped_parameter = clone($parameter);

                        $mapped_parameter->setUnionType(
                            $mapped_parameter->getUnionType()->withTemplateParameterTypeMap(
                                $type_option->get()->getTemplateParameterTypeMap(
                                    $code_base
                                )
                            )
                        );

                        return $mapped_parameter;
                    }, $method->getParameterList())
                );
            }
        }
        if ($method->getHasYield()) {
            // There's no phpdoc standard for template types of Generators at the moment.
            $newType = UnionType::fromFullyQualifiedString('\\Generator');
            $oldType = $method->getUnionType();
            if (!$newType->canCastToUnionType($method->getUnionType())) {
                $method->setUnionType($newType);
            }
        }

        $code_base->addMethod($method);
    }

    /**
     * @return bool
     * True if this class has a method with the given name
     */
    public function hasMethodWithName(
        CodeBase $code_base,
        string $name
    ) : bool {
        // All classes have a constructor even if it hasn't
        // been declared yet
        if ('__construct' === strtolower($name)) {
            return true;
        }

        $method_fqsen = FullyQualifiedMethodName::make(
            $this->getFQSEN(),
            $name
        );

        return $code_base->hasMethodWithFQSEN($method_fqsen);
    }

    /**
     * @return Method
     * The method with the given name
     */
    public function getMethodByName(
        CodeBase $code_base,
        string $name
    ) : Method {
        return $this->getMethodByNameInContext(
            $code_base,
            $name,
            $this->getContext()
        );
    }

    /**
     * @return Method
     * The method with the given name
     */
    public function getMethodByNameInContext(
        CodeBase $code_base,
        string $name,
        Context $context
    ) : Method {

        $method_fqsen = FullyQualifiedMethodName::make(
            $this->getFQSEN(),
            $name
        );

        if (!$code_base->hasMethodWithFQSEN($method_fqsen)) {
            if ('__construct' === $name) {
                // Create a default constructor if its requested
                // but doesn't exist yet
                $default_constructor =
                    Method::defaultConstructorForClassInContext(
                        $this, $context, $code_base
                    );

                $this->addMethod($code_base, $default_constructor, $this->getParentTypeOption());

                return $default_constructor;
            }

            throw new CodeBaseException(
                $method_fqsen,
                "Method with name $name does not exist for class {$this->getFQSEN()}."
            );
        }

        return $code_base->getMethodByFQSEN($method_fqsen);
    }

    /**
     * @return Method[]
     * A list of methods on this class
     */
    public function getMethodMap(CodeBase $code_base) : array
    {
        return $code_base->getMethodMapByFullyQualifiedClassName(
            $this->getFQSEN()
        );
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__call' method
     */
    public function hasCallMethod(CodeBase $code_base)
    {
        return $this->hasMethodWithName($code_base, '__call');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return Method
     * The magic `__call` method
     */
    public function getCallMethod(CodeBase $code_base) {
        return $this->getMethodByName($code_base, '__call');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__callStatic' method
     */
    public function hasCallStaticMethod(CodeBase $code_base)
    {
        return $this->hasMethodWithName($code_base, '__callStatic');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return Method
     * The magic `__callStatic` method
     */
    public function getCallStaticMethod(CodeBase $code_base) {
        return $this->getMethodByName($code_base, '__callStatic');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__call' or '__callStatic'
     * method
     */
    public function hasCallOrCallStaticMethod(CodeBase $code_base)
    {
        return (
            $this->hasCallMethod($code_base)
            || $this->hasCallStaticMethod($code_base)
        );
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__get' method
     */
    public function hasGetMethod(CodeBase $code_base)
    {
        return $this->hasMethodWithName($code_base, '__get');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__set' method
     */
    public function hasSetMethod(CodeBase $code_base)
    {
        return $this->hasMethodWithName($code_base, '__set');
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return bool
     * True if this class has a magic '__get' or '__set'
     * method
     */
    public function hasGetOrSetMethod(CodeBase $code_base)
    {
        return (
            $this->hasGetMethod($code_base)
            || $this->hasSetMethod($code_base)
        );
    }

    /**
     * @return null
     */
    public function addTraitFQSEN(FQSEN $fqsen)
    {
        $this->trait_fqsen_list[] = $fqsen;

        // Add the trait to the union type of this class
        $this->getUnionType()->addUnionType(
            UnionType::fromFullyQualifiedString((string)$fqsen)
        );
    }

    /**
     * @return FQSEN[]
     * A list of FQSEN's for included traits
     */
    public function getTraitFQSENList() : array
    {
        return $this->trait_fqsen_list;
    }

    /**
     * @return bool
     * True if this class calls its parent constructor
     */
    public function getIsParentConstructorCalled() : bool
    {
        return Flags::bitVectorHasState(
            $this->getPhanFlags(),
            Flags::IS_PARENT_CONSTRUCTOR_CALLED
        );
    }

    /**
     * @return void
     */
    public function setIsParentConstructorCalled(
        bool $is_parent_constructor_called
    ) {
        $this->setPhanFlags(Flags::bitVectorWithState(
            $this->getPhanFlags(),
            Flags::IS_PARENT_CONSTRUCTOR_CALLED,
            $is_parent_constructor_called
        ));
    }

    /**
     * @return bool
     * True if this is a final class
     */
    public function isFinal() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_FINAL
        );
    }

    /**
     * @return bool
     * True if this is an abstract class
     */
    public function isAbstract() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_ABSTRACT
        );
    }

    /**
     * @return bool
     * True if this is an interface
     */
    public function isInterface() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_INTERFACE
        );
    }

    /**
     * @return bool
     * True if this class is a trait
     */
    public function isTrait() : bool
    {
        return Flags::bitVectorHasState(
            $this->getFlags(),
            \ast\flags\CLASS_TRAIT
        );
    }

    /**
     * @return FullyQualifiedClassName
     */
    public function getFQSEN() : FullyQualifiedClassName
    {
        return $this->fqsen;
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return FullyQualifiedClassName[]
     */
    public function getNonParentAncestorFQSENList(CodeBase $code_base)
    {
        return array_merge(
            $this->getInterfaceFQSENList(),
            $this->getTraitFQSENList()
        );
    }

    /**
     * @return FullyQualifiedClassName[]
     */
    public function getAncestorFQSENList(CodeBase $code_base)
    {
        $ancestor_list = $this->getNonParentAncestorFQSENList($code_base);

        if ($this->hasParentType()) {
            $ancestor_list[] = $this->getParentClassFQSEN();
        }

        return $ancestor_list;
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @param FullyQualifiedClassName[]
     * A list of class FQSENs to turn into a list of
     * Clazz objects
     *
     * @return Clazz[]
     */
    private function getClassListFromFQSENList(
        CodeBase $code_base,
        array $fqsen_list
    ) : array {
        $class_list = [];
        foreach ($fqsen_list as $fqsen) {
            if ($code_base->hasClassWithFQSEN($fqsen)) {
                $class_list[] = $code_base->getClassByFQSEN($fqsen);
            }
        }
        return $class_list;
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return Clazz[]
     */
    public function getAncestorClassList(CodeBase $code_base)
    {
        return $this->getClassListFromFQSENList(
            $code_base,
            $this->getAncestorFQSENList($code_base)
        );
    }

    /**
     * @return FullyQualifiedClassName[]
     * The set of FQSENs representing extended classes and traits
     * for which this class could have overriding methods and
     * properties.
     */
    public function getOverridableAncestorFQSENList(CodeBase $code_base)
    {
        $ancestor_list = $this->getTraitFQSENList();

        if ($this->hasParentType()) {
            $ancestor_list[] = $this->getParentClassFQSEN();
        }

        return $ancestor_list;
    }

    /**
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return Clazz[]
     */
    public function getOverridableAncestorClassList(CodeBase $code_base)
    {
        return $this->getClassListFromFQSENList(
            $code_base,
            $this->getOverridableAncestorFQSENList($code_base)
        );
    }

    /**
     * Add properties, constants and methods from all
     * ancestors (parents, traits, ...) to this class
     *
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return null
     */
    public function importAncestorClasses(CodeBase $code_base)
    {
        if (!$this->isFirstExecution(__METHOD__)) {
            return;
        }

        foreach ($this->getNonParentAncestorFQSENList($code_base) as $fqsen) {
            if (!$code_base->hasClassWithFQSEN($fqsen)) {
                continue;
            }

            $ancestor = $code_base->getClassByFQSEN($fqsen);

            $this->importAncestorClass(
                $code_base, $ancestor, new None
            );
        }

        // Copy information from the parent(s)
        $this->importParentClass($code_base);
    }

    /*
     * Add properties, constants and methods from the
     * parent of this class
     *
     * @param CodeBase $code_base
     * The entire code base from which we'll find ancestor
     * details
     *
     * @return null
     */
    public function importParentClass(CodeBase $code_base)
    {
        if (!$this->isFirstExecution(__METHOD__)) {
            return;
        }

        if (!$this->hasParentType()) {
            return;
        }

        if ($this->getParentClassFQSEN() == $this->getFQSEN()) {
            return;
        }

        // Let the parent class finder worry about this
        if (!$code_base->hasClassWithFQSEN(
            $this->getParentClassFQSEN()
        )) {
            return;
        }

        assert(
            $code_base->hasClassWithFQSEN($this->getParentClassFQSEN()),
            "Clazz should already have been proven to exist."
        );

        // Get the parent class
        $parent = $this->getParentClass($code_base);

        $parent->addReference($this->getContext());

        // Tell the parent to import its own parents first

        // Import elements from the parent
        $this->importAncestorClass(
            $code_base,
            $parent,
            $this->getParentTypeOption()
        );
    }

    /**
     * Add properties, constants and methods from the given
     * class to this.
     *
     * @param CodeBase $code_base
     * A reference to the code base in which the ancestor exists
     *
     * @param Clazz $class
     * A class to import from
     *
     * @param Option<Type>|None $type_option
     * A possibly defined ancestor type used to define template
     * parameter types when importing ancestor properties and
     * methods
     *
     * @return void
     */
    public function importAncestorClass(
        CodeBase $code_base,
        Clazz $class,
        $type_option
    ) {
        if (!$this->isFirstExecution(
            __METHOD__ . ':' . (string)$class->getFQSEN()
        )) {
            return;
        }

        $class->addReference($this->getContext());

        // Make sure that the class imports its parents first
        $class->hydrate($code_base);

        // Copy properties
        foreach ($class->getPropertyMap($code_base) as $property) {
            $this->addProperty(
                $code_base,
                $property,
                $type_option
            );
        }

        // Copy constants
        foreach ($class->getConstantMap($code_base) as $constant) {
            $this->addConstant($code_base, $constant);
        }

        // Copy methods
        foreach ($class->getMethodMap($code_base) as $method) {
            $this->addMethod(
                $code_base,
                $method,
                $type_option
            );
        }
    }

    /**
     * @return int
     * The number of references to this typed structural element
     */
    public function getReferenceCount(
        CodeBase $code_base
    ) : int {
        $count = parent::getReferenceCount($code_base);

        // A function that maps a list of elements to the
        // total reference count for all elements
        $list_count = function (array $list) use ($code_base) {
            return array_reduce($list, function (
                int $count,
                AddressableElement $element
            ) use ($code_base) {
                return (
                    $count
                    + $element->getReferenceCount($code_base)
                );
            }, 0);
        };

        // Sum up counts for all dependent elements
        $count += $list_count($this->getPropertyList($code_base));
        $count += $list_count($this->getMethodMap($code_base));
        $count += $list_count($this->getConstantMap($code_base));

        return $count;
    }

    /**
     * @return bool
     * True if this class contains generic types
     */
    public function isGeneric() : bool
    {
        return $this->getInternalScope()->hasAnyTemplateType();
    }

    /**
     * @return TemplateType[]
     * The set of all template types parameterizing this generic
     * class
     */
    public function getTemplateTypeMap() : array
    {
        return $this->getInternalScope()->getTemplateTypeMap();
    }

    /**
     * @return string
     * A string describing this class
     */
    public function __toString() : string
    {
        $string = '';

        if ($this->isFinal()) {
            $string .= 'final ';
        }

        if ($this->isAbstract()) {
            $string .= 'abstract ';
        }

        if ($this->isInterface()) {
            $string .= 'Interface ';
        } elseif ($this->isTrait()) {
            $string .= 'Trait ';
        } else {
            $string .= 'Class ';
        }

        $string .= (string)$this->getFQSEN()->getCanonicalFQSEN();

        return $string;
    }

    /**
     * This method must be called before analysis
     * begins.
     *
     * @return void
     */
    protected function hydrateOnce(CodeBase $code_base)
    {
        foreach ($this->getAncestorFQSENList($code_base) as $fqsen) {
            if ($code_base->hasClassWithFQSEN($fqsen)) {
                $code_base->getClassByFQSEN(
                    $fqsen
                )->hydrate($code_base);
            }
        }

        // Create the 'class' constant
        $this->addConstant($code_base,
            new ClassConstant(
                $this->getContext(),
                'class',
                StringType::instance()->asUnionType(),
                0,
                FullyQualifiedClassConstantName::make(
                    $this->getFQSEN(),
                    'class'
                )
            )
        );

        // Add variable '$this' to the scope
        $this->getInternalScope()->addVariable(
            new Variable(
                $this->getContext(),
                'this',
                $this->getUnionType(),
                0
            )
        );

        // Load parent methods, properties, constants
        $this->importAncestorClasses($code_base);
    }

    /**
     * This method should be called after hydration
     *
     * @return void
     */
    public final function analyze(CodeBase $code_base)
    {
        if ($this->isInternal()) {
            return;
        }

        // Make sure the parent classes exist
        ParentClassExistsAnalyzer::analyzeParentClassExists(
            $code_base, $this
        );

        DuplicateClassAnalyzer::analyzeDuplicateClass(
            $code_base, $this
        );

        ParentConstructorCalledAnalyzer::analyzeParentConstructorCalled(
            $code_base, $this
        );

        PropertyTypesAnalyzer::analyzePropertyTypes(
            $code_base, $this
        );

        // Analyze this class to make sure that we don't have conflicting
        // types between similar inherited methods.
        CompositionAnalyzer::analyzeComposition(
            $code_base, $this
        );

        // Let any configured plugins analyze the class
        ConfigPluginSet::instance()->analyzeClass(
            $code_base, $this
        );

    }
}
