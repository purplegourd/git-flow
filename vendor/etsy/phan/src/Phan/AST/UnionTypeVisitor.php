<?php declare(strict_types=1);
namespace Phan\AST;

use Phan\Analysis\BinaryOperatorFlagVisitor;
use Phan\CodeBase;
use Phan\Config;
use Phan\Debug;
use Phan\Exception\CodeBaseException;
use Phan\Exception\IssueException;
use Phan\Exception\NodeException;
use Phan\Exception\TypeException;
use Phan\Exception\UnanalyzableException;
use Phan\Issue;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Variable;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\Type;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\BoolType;
use Phan\Language\Type\CallableType;
use Phan\Language\Type\FloatType;
use Phan\Language\Type\IntType;
use Phan\Language\Type\MixedType;
use Phan\Language\Type\NullType;
use Phan\Language\Type\ObjectType;
use Phan\Language\Type\StaticType;
use Phan\Language\Type\StringType;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use ast\Node;
use ast\Node\Decl;

/**
 * Determine the UnionType associated with a
 * given node
 */
class UnionTypeVisitor extends AnalysisVisitor
{
    /**
     * @var bool
     * Set to true to cause loggable issues to be thrown
     * instead of emitted as issues to the log.
     */
    private $should_catch_issue_exception = false;

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param bool $should_catch_issue_exception
     * Set to true to cause loggable issues to be thrown
     * instead of emitted as issues to the log.
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        bool $should_catch_issue_exception = true
    ) {
        parent::__construct($code_base, $context);

        $this->should_catch_issue_exception =
            $should_catch_issue_exception;
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param $context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|mixed $node
     * The node for which we'd like to determine its type
     *
     * @param bool $should_catch_issue_exception
     * Set to true to cause loggable issues to be thrown
     * instead
     *
     * @return UnionType
     * The UnionType associated with the given node
     * in the given Context within the given CodeBase
     *
     * @throws IssueException
     * If $should_catch_issue_exception is false an IssueException may
     * be thrown for optional issues.
     */
    public static function unionTypeFromNode(
        CodeBase $code_base,
        Context $context,
        $node,
        bool $should_catch_issue_exception = true
    ) : UnionType {
        if (!($node instanceof Node)) {
            if ($node === null || $node === 'null') {
                return new UnionType();
            }

            return Type::fromObject($node)->asUnionType();
        }

        if ($should_catch_issue_exception) {
            try {
                return (new self(
                    $code_base,
                    $context,
                    $should_catch_issue_exception
                ))($node);
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $context,
                    $exception->getIssueInstance()
                );
                return new UnionType();
            }
        }

        return (new self(
            $code_base,
            $context,
            $should_catch_issue_exception
        ))($node);
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * An AST node we'd like to determine the UnionType
     * for
     *
     * @return UnionType
     * The set of types associated with the given node
     */
    public function visit(Node $node) : UnionType
    {
        /*
        throw new NodeException($node,
            'Visitor not implemented for node of type '
            . Debug::nodeName($node)
        );
        */
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_POST_INC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPostInc(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_POST_DEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPostDec(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_PRE_DEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPreDec(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_PRE_INC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitPreInc(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['var']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_CLONE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClone(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_COALESCE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCoalesce(Node $node) : UnionType
    {
        $union_type = new UnionType();

        $left_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['left']
        );
        $union_type->addUnionType($left_type);

        $right_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['right']
        );

        $union_type->addUnionType(
            $right_type
        );

        return $union_type;
    }

    /**
     * Visit a node with kind `\ast\AST_EMPTY`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitEmpty(Node $node) : UnionType
    {
        return BoolType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ISSET`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitIsset(Node $node) : UnionType
    {
        return BoolType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_INCLUDE_OR_EVAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitIncludeOrEval(Node $node) : UnionType
    {
        // require() can return arbitrary objects. Lets just
        // say that we don't know what it is and move on
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_MAGIC_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitMagicConst(Node $node) : UnionType
    {
        // This is for things like __METHOD__
        return StringType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN_REF`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitAssignRef(Node $node) : UnionType
    {
        // TODO
        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_SHELL_EXEC`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitShellExec(Node $node) : UnionType
    {
        return StringType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_NAME`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitName(Node $node) : UnionType
    {
        if ($node->flags & \ast\flags\NAME_NOT_FQ) {
            if ('parent' === $node->children['name']) {
                $class = $this->context->getClassInScope($this->code_base);

                if ($class->hasParentType()) {
                    return Type::fromFullyQualifiedString(
                        (string)$class->getParentClassFQSEN()
                    )->asUnionType();
                } else {
                    if (!$class->isTrait()) {
                        $this->emitIssue(
                            Issue::ParentlessClass,
                            $node->lineno ?? 0,
                            (string)$class->getFQSEN()
                        );
                    }

                    return new UnionType();
                }
            }

            if ('self' === $node->children['name']
                && $this->context->getClassInScope($this->code_base)->isTrait()
            ) {
                return new UnionType();
            }

            return Type::fromStringInContext(
                $node->children['name'],
                $this->context
            )->asUnionType();
        }

        return Type::fromFullyQualifiedString(
            '\\' . $node->children['name']
        )->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_TYPE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitType(Node $node) : UnionType
    {
        switch ($node->flags) {
            case \ast\flags\TYPE_ARRAY:
                return ArrayType::instance()->asUnionType();
            case \ast\flags\TYPE_BOOL:
                return BoolType::instance()->asUnionType();
            case \ast\flags\TYPE_CALLABLE:
                return CallableType::instance()->asUnionType();
            case \ast\flags\TYPE_DOUBLE:
                return FloatType::instance()->asUnionType();
            case \ast\flags\TYPE_LONG:
                return IntType::instance()->asUnionType();
            case \ast\flags\TYPE_NULL:
                return NullType::instance()->asUnionType();
            case \ast\flags\TYPE_OBJECT:
                return ObjectType::instance()->asUnionType();
            case \ast\flags\TYPE_STRING:
                return StringType::instance()->asUnionType();
            default:
                assert(
                    false,
                    "All flags must match. Found "
                    . Debug::astFlagDescription($node->flags ?? 0)
                );
                break;
        }
    }

    /**
     * Visit a node with kind `\ast\AST_CONDITIONAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitConditional(Node $node) : UnionType
    {

        $true_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['trueExpr'] ??
                $node->children['true'] ?? ''
        );

        $false_type = UnionType::fromNode(
            $this->context,
            $this->code_base,
            $node->children['falseExpr'] ??
                $node->children['false'] ?? ''
        );

        $union_type = new UnionType();

        // Add the type for the 'true' side
        $union_type->addUnionType($true_type);

        // Add the type for the 'false' side
        $union_type->addUnionType($false_type);

        // If one side has an unknown type but the other doesn't
        // we can't let the unseen type get erased. Unfortunately,
        // we need to add 'mixed' in so that we know it could be
        // anything at all.
        //
        // See Issue #104
        if ($true_type->isEmpty() xor $false_type->isEmpty()) {
            $union_type->addUnionType(
                MixedType::instance()->asUnionType()
            );
        }

        return $union_type;
    }

    /**
     * Visit a node with kind `\ast\AST_ARRAY`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitArray(Node $node) : UnionType
    {
        if (!empty($node->children)
            && $node->children[0] instanceof Node
            && $node->children[0]->kind == \ast\AST_ARRAY_ELEM
        ) {
            $element_types = [];

            // Check the first 5 (completely arbitrary) elements
            // and assume the rest are the same type
            for ($i=0; $i<5; $i++) {
                // Check to see if we're out of elements
                if (empty($node->children[$i])) {
                    break;
                }

                if ($node->children[$i]->children['value'] instanceof Node) {
                    $element_types[] = UnionType::fromNode(
                        $this->context,
                        $this->code_base,
                        $node->children[$i]->children['value'],
                        $this->should_catch_issue_exception
                    );
                } else {
                    $element_types[] = Type::fromObject(
                        $node->children[$i]->children['value']
                    )->asUnionType();
                }
            }

            $element_types =
                array_values(array_unique($element_types));

            if (count($element_types) == 1) {
                return $element_types[0]->asGenericArrayTypes();
            }
        }

        return ArrayType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_BINARY_OP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitBinaryOp(Node $node) : UnionType
    {
        return (new BinaryOperatorFlagVisitor(
            $this->code_base,
            $this->context
        ))($node);
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitGreater(Node $node) : UnionType
    {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_GREATER_EQUAL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitGreaterEqual(Node $node) : UnionType
    {
        return $this->visitBinaryOp($node);
    }

    /**
     * Visit a node with kind `\ast\AST_CAST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCast(Node $node) : UnionType
    {
        // TODO: Check if the cast is allowed based on the right side type
        UnionType::fromNode($this->context, $this->code_base, $node->children['expr']);
        switch ($node->flags) {
            case \ast\flags\TYPE_NULL:
                return NullType::instance()->asUnionType();
            case \ast\flags\TYPE_BOOL:
                return BoolType::instance()->asUnionType();
            case \ast\flags\TYPE_LONG:
                return IntType::instance()->asUnionType();
            case \ast\flags\TYPE_DOUBLE:
                return FloatType::instance()->asUnionType();
            case \ast\flags\TYPE_STRING:
                return StringType::instance()->asUnionType();
            case \ast\flags\TYPE_ARRAY:
                return ArrayType::instance()->asUnionType();
            case \ast\flags\TYPE_OBJECT:
                return ObjectType::instance()->asUnionType();
            default:
                throw new NodeException(
                    $node,
                    'Unknown type (' . $node->flags . ') in cast'
                );
        }
    }

    /**
     * Visit a node with kind `\ast\AST_NEW`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitNew(Node $node) : UnionType
    {
        $union_type = $this->visitClassNode($node->children['class']);

        // For any types that are templates, map them to concrete
        // types based on the parameters passed in.
        return new UnionType(array_map(function (Type $type) use ($node) {

            // Get a fully qualified name for the type
            $fqsen = $type->asFQSEN();

            // If this isn't a class, its fine as is
            if (!($fqsen instanceof FullyQualifiedClassName)) {
                return $type;
            }

            assert($fqsen instanceof FullyQualifiedClassName);

            // If we don't have the class, we'll catch that problem
            // elsewhere
            if (!$this->code_base->hasClassWithFQSEN($fqsen)) {
                return $type;
            }


            $class = $this->code_base->getClassByFQSEN($fqsen);

            // If this class doesn't have any generics on it, we're
            // fine as we are with this Type
            if (!$class->isGeneric()) {
                return $type;
            }

            // Now things are interesting. We need to map the
            // arguments to the generic types and return a special
            // kind of type.

            // Get the constructor so that we can figure out what
            // template types we're going to be mapping
            $constructor_method =
                $class->getMethodByName($this->code_base, '__construct');

            // Map each argument to its type
            $arg_type_list = array_map(function($arg_node) {
                return UnionType::fromNode(
                    $this->context,
                    $this->code_base,
                    $arg_node
                );
            }, $node->children['args']->children ?? []);

            // Map each template type o the argument's concrete type
            $template_type_list = [];
            foreach ($constructor_method->getParameterList() as $i => $parameter) {
                if (isset($arg_type_list[$i])) {
                    $template_type_list[] = $arg_type_list[$i];
                }
            }

            // Create a new type that assigns concrete
            // types to template type identifiers.
            return Type::fromType($type, $template_type_list);
        }, $union_type->getTypeSet()->toArray()));
    }

    /**
     * Visit a node with kind `\ast\AST_INSTANCEOF`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitInstanceOf(Node $node) : UnionType
    {
        // Check to make sure the left side is valid
        UnionType::fromNode($this->context, $this->code_base, $node->children['expr']);
        try {
            // Confirm that the right-side exists
            $union_type = $this->visitClassNode(
                $node->children['class']
            );
        } catch (TypeException $exception) {
            // TODO: log it?
        }

        return BoolType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_DIM`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitDim(Node $node) : UnionType
    {
        $union_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr'],
            $this->should_catch_issue_exception
        );

        if ($union_type->isEmpty()) {
            return $union_type;
        }

        // Figure out what the types of accessed array
        // elements would be
        $generic_types =
            $union_type->genericArrayElementTypes();

        // If we have generics, we're all set
        if (!$generic_types->isEmpty()) {
            return $generic_types;
        }

        // If the only type is null, we don't know what
        // accessed items will be
        if ($union_type->isType(NullType::instance())) {
            return new UnionType();
        }

        $element_types = new UnionType();

        // You can access string characters via array index,
        // so we'll add the string type to the result if we're
        // indexing something that could be a string
        if ($union_type->isType(StringType::instance())
            || $union_type->canCastToUnionType(StringType::instance()->asUnionType())
        ) {
            $element_types->addType(StringType::instance());
        }

        // array offsets work on strings, unfortunately
        // Double check that any classes in the type don't
        // have ArrayAccess
        $array_access_type =
            Type::fromNamespaceAndName('\\', 'ArrayAccess');


        // Hunt for any types that are viable class names and
        // see if they inherit from ArrayAccess
        try {
            foreach ($union_type->asClassList($this->code_base, $this->context) as $class) {
                if ($class->getUnionType()->asExpandedTypes($this->code_base)->hasType($array_access_type)) {
                    return $element_types;
                }
            }
        } catch (CodeBaseException $exception) {}

        if ($element_types->isEmpty()) {
            $this->emitIssue(
                Issue::TypeArraySuspicious,
                $node->lineno ?? 0,
                (string)$union_type
            );
        }

        return $element_types;
    }

    /**
     * Visit a node with kind `\ast\AST_CLOSURE`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitClosure(Decl $node) : UnionType
    {
        // The type of a closure is the fqsen pointing
        // at its definition
        $closure_fqsen =
            FullyQualifiedFunctionName::fromClosureInContext(
                $this->context
            );

        $type = CallableType::instanceWithClosureFQSEN(
            $closure_fqsen
        )->asUnionType();

        return $type;
    }

    /**
     * Visit a node with kind `\ast\AST_VAR`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitVar(Node $node) : UnionType
    {
        // $$var or ${...} (whose idea was that anyway?)
        if (($node->children['name'] instanceof Node)
            && ($node->children['name']->kind == \ast\AST_VAR
            || $node->children['name']->kind == \ast\AST_BINARY_OP)
        ) {
            return MixedType::instance()->asUnionType();
        }

        // This is nonsense. Give up.
        if ($node->children['name'] instanceof Node) {
            return new UnionType();
        }

        $variable_name =
            $node->children['name'];

        if (!$this->context->getScope()->hasVariableWithName($variable_name)) {
            if (Variable::isSuperglobalVariableWithName($variable_name)) {
                return Variable::getUnionTypeOfHardcodedGlobalVariableWithName($variable_name, $this->context);
            }
            if (!Config::get()->ignore_undeclared_variables_in_global_scope
                || !$this->context->isInGlobalScope()
            ) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredVariable)(
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        [$variable_name]
                    )
                );
            }
        } else {
            $variable = $this->context->getScope()->getVariableByName(
                $variable_name
            );

            return $variable->getUnionType();
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ENCAPS_LIST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitEncapsList(Node $node) : UnionType
    {
        return StringType::instance()->asUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitConst(Node $node) : UnionType
    {
        if ($node->children['name']->kind == \ast\AST_NAME) {
            if (defined($node->children['name']->children['name'])) {
                return Type::fromObject(
                    constant($node->children['name']->children['name'])
                )->asUnionType();
            } else {
                // Figure out the name of the constant if its
                // a string.
                $constant_name =
                    isset($node->children['name']->children['name'])
                    ? $node->children['name']->children['name']
                    : '';

                // If the constant is referring to the current
                // class, return that as a type
                if (Type::isSelfTypeString($constant_name)) {
                    return $this->visitClassNode($node);
                }

                $constant = (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getConst();

                return $constant->getUnionType();
            }
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_CLASS_CONST`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     *
     * @throws IssueException
     * An exception is thrown if we can't find the constant
     */
    public function visitClassConst(Node $node) : UnionType
    {

        $constant_name = $node->children['const'];

        try {
            $constant = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getClassConst();

            return $constant->getUnionType();
        } catch (NodeException $exception) {
            $this->emitIssue(
                Issue::Unanalyzable,
                $node->lineno ?? 0
            );
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitProp(Node $node) : UnionType
    {
        try {
            $property = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getProperty($node->children['prop']);

            // Map template types to concrete types
            if ($property->getUnionType()->hasTemplateType()) {

                // Get the type of the object calling the property
                $expression_type = UnionType::fromNode(
                    $this->context,
                    $this->code_base,
                    $node->children['expr']
                );

                $union_type = $property->getUnionType()->withTemplateParameterTypeMap(
                    $expression_type->getTemplateParameterTypeMap($this->code_base)
                );

                return $union_type;
            }

            return $property->getUnionType();
        } catch (IssueException $exception) {
            Issue::maybeEmitInstance(
                $this->code_base,
                $this->context,
                $exception->getIssueInstance()
            );
        } catch (CodeBaseException $exception) {
            $property_name = $node->children['prop'];
            $this->emitIssue(
                Issue::UndeclaredProperty,
                $node->lineno ?? 0,
                "{$exception->getFQSEN()}->{$property_name}"
            );
        } catch (UnanalyzableException $exception) {
            // Swallow it. There are some constructs that we
            // just can't figure out.
        } catch (NodeException $exception) {
            // Swallow it. There are some constructs that we
            // just can't figure out.
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_PROP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitStaticProp(Node $node) : UnionType
    {
        return $this->visitProp($node);
    }


    /**
     * Visit a node with kind `\ast\AST_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitCall(Node $node) : UnionType
    {
        // Things like `$func()`. We don't understand these.
        if ($node->children['expr']->kind !== \ast\AST_NAME) {
            return new UnionType();
        }

        $function_name =
            $node->children['expr']->children['name'];

        try {
            $function = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getFunction($function_name);
        } catch (CodeBaseException $exception) {
            // If the function wasn't declared, it'll be caught
            // and reported elsewhere
            return new UnionType();
        }

        $function_fqsen = $function->getFQSEN();

        // TODO: I don't believe we need this any more
        // If this is an internal function, see if we can get
        // its types from the static dataset.
        if ($function->isInternal()
            && $function->getUnionType()->isEmpty()
        ) {
            $map = UnionType::internalFunctionSignatureMapForFQSEN(
                $function->getFQSEN()
            );

            return $map[$function_name] ?? new UnionType();
        }

        return $function->getUnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_STATIC_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitStaticCall(Node $node) : UnionType
    {
        return $this->visitMethodCall($node);
    }

    /**
     * Visit a node with kind `\ast\AST_METHOD_CALL`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitMethodCall(Node $node) : UnionType
    {
        $method_name = $node->children['method'] ?? '';

        // Give up on any complicated nonsense where the
        // method name is a variable such as in
        // `$variable->$function_name()`.
        if ($method_name instanceof Node) {
            return new UnionType();
        }

        // Method names can some times turn up being
        // other method calls.
        assert(
            is_string($method_name),
            "Method name must be a string. Something else given."
        );

        try {
            $class_fqsen = null;
            foreach ($this->classListFromNode(
                    $node->children['class'] ?? $node->children['expr']
                ) as $i => $class
            ) {
                $class_fqsen = $class->getFQSEN();

                if (!$class->hasMethodWithName(
                    $this->code_base,
                    $method_name
                )) {
                    continue;
                }

                try {
                    $method = $class->getMethodByNameInContext(
                        $this->code_base,
                        $method_name,
                        $this->context
                    );

                    $union_type = $method->getUnionType();

                    // Map template types to concrete types
                    if ($union_type->hasTemplateType()) {

                        // Get the type of the object calling the property
                        $expression_type = UnionType::fromNode(
                            $this->context,
                            $this->code_base,
                            $node->children['expr']
                        );

                        // Map template types to concrete types
                        $union_type = $union_type->withTemplateParameterTypeMap(
                            $expression_type->getTemplateParameterTypeMap($this->code_base)
                        );
                    }

                    // Remove any references to \static or \static[]
                    // once we're talking about the method's return
                    // type outside of its class
                    if ($union_type->hasStaticType()) {
                        $union_type = clone($union_type);
                        $union_type->removeType(\Phan\Language\Type\StaticType::instance());
                    }

                    if ($union_type->genericArrayElementTypes()->hasStaticType()) {

                        $union_type = clone($union_type);

                        // Find the static type on the list
                        $static_type = $union_type->getTypeSet()->find(function (Type $type) : bool {
                            return (
                                $type->isGenericArray()
                                && $type->genericArrayElementType()->isStaticType()
                            );
                        });

                        // Remove it from the list
                        $union_type->removeType($static_type);
                    }

                    return $union_type;
                } catch (IssueException $exception) {
                    return new UnionType();
                }
            }
        } catch (IssueException $exception) {
            // Swallow it
        } catch (CodeBaseException $exception) {
            $this->emitIssue(
                Issue::UndeclaredClassMethod,
                $node->lineno ?? 0,
                $method_name,
                (string)$exception->getFQSEN()
            );
        }

        return new UnionType();
    }

    /**
     * Visit a node with kind `\ast\AST_ASSIGN`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitAssign(Node $node) : UnionType
    {
        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_UNARY_OP`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitUnaryOp(Node $node) : UnionType
    {
        // Shortcut some easy operators
        switch ($node->flags) {
            case \ast\flags\UNARY_BOOL_NOT:
                return BoolType::instance()->asUnionType();
        }

        return self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node->children['expr']
        );
    }

    /**
     * Visit a node with kind `\ast\AST_UNARY_MINUS`
     *
     * @param Node $node
     * A node of the type indicated by the method name that we'd
     * like to figure out the type that it produces.
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     */
    public function visitUnaryMinus(Node $node) : UnionType
    {
        return Type::fromObject($node->children['expr'])->asUnionType();
    }

    /*
     * @param Node $node
     * A node holding a class
     *
     * @return UnionType
     * The set of types that are possibly produced by the
     * given node
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    private function visitClassNode(Node $node) : UnionType
    {
        // Things of the form `new $class_name();`
        if ($node->kind == \ast\AST_VAR) {
            return new UnionType();
        }

        // Anonymous class of form `new class { ... }`
        if ($node->kind == \ast\AST_CLASS
            && $node->flags & \ast\flags\CLASS_ANONYMOUS
        ) {
            // Generate a stable name for the anonymous class
            $anonymous_class_name =
                (new ContextNode(
                    $this->code_base,
                    $this->context,
                    $node
                ))->getUnqualifiedNameForAnonymousClass();

            // Turn that into a fully qualified name
            $fqsen = FullyQualifiedClassName::fromStringInContext(
                $anonymous_class_name,
                $this->context
            );

            // Turn that into a union type
            return Type::fromFullyQualifiedString((string)$fqsen)->asUnionType();
        }

        // Things of the form `new $method->name()`
        if ($node->kind !== \ast\AST_NAME) {
            return new UnionType();
        }

        // Get the name of the class
        $class_name = $node->children['name'];

        // If this is a straight-forward class name, recurse into the
        // class node and get its type
        if (!Type::isSelfTypeString($class_name)) {
            // TODO: does anyone else call this method?
            return self::unionTypeFromClassNode(
                $this->code_base,
                $this->context,
                $node
            );
        }

        // This is a self-referential node
        if (!$this->context->isInClassScope()) {
            $this->emitIssue(
                Issue::ContextNotObject,
                $node->lineno ?? 0,
                $class_name
            );

            return new UnionType();
        }

        // Reference to a parent class
        if ($class_name === 'parent') {
            $class = $this->context->getClassInScope(
                $this->code_base
            );

            if (!$class->hasParentType()) {
                $this->emitIssue(
                    Issue::ParentlessClass,
                    $node->lineno ?? 0,
                    (string)$class->getFQSEN()
                );

                return new UnionType();
            }

            return Type::fromFullyQualifiedString(
                (string)$class->getParentClassFQSEN()
            )->asUnionType();
        }

        return Type::fromFullyQualifiedString(
            (string)$this->context->getClassFQSEN()
        )->asUnionType();
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param $context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Node|mixed $node
     * The node for which we'd like to determine its type
     *
     * @return UnionType
     * The UnionType associated with the given node
     * in the given Context within the given CodeBase
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    public static function unionTypeFromClassNode(
        CodeBase $code_base,
        Context $context,
        $node
    ) : UnionType {
        // For simple nodes or very complicated nodes,
        // recurse
        if (!($node instanceof \ast\Node)
            || $node->kind != \ast\AST_NAME
        ) {
            return self::unionTypeFromNode(
                $code_base,
                $context,
                $node
            );
        }

        $class_name = $node->children['name'];

        if ('parent' === $class_name) {
            if (!$context->isInClassScope()) {
                throw new IssueException(
                    Issue::fromType(Issue::ContextNotObject)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [$class_name]
                    )
                );
            }

            $class = $context->getClassInScope($code_base);

            if ($class->isTrait()) {
                throw new IssueException(
                    Issue::fromType(Issue::TraitParentReference)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [(string)$context->getClassFQSEN() ]
                    )
                );
            }

            if (!$class->hasParentType()) {
                throw new IssueException(
                    Issue::fromType(Issue::ParentlessClass)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [ (string)$context->getClassFQSEN() ]
                    )
                );
            }

            $parent_class_fqsen = $class->getParentClassFQSEN();

            if (!$code_base->hasClassWithFQSEN($parent_class_fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredClass)(
                        $context->getFile(),
                        $node->lineno ?? 0,
                        [ (string)$parent_class_fqsen ]
                    )
                );
            } else {
                $parent_class = $code_base->getClassByFQSEN(
                    $parent_class_fqsen
                );

                return $parent_class->getUnionType();
            }
        }

        // We're going to convert the class reference to a type
        $type = null;

        // Check to see if the name is fully qualified
        if (!($node->flags & \ast\flags\NAME_NOT_FQ)) {
            if (0 !== strpos($class_name, '\\')) {
                $class_name = '\\' . $class_name;
            }

            $type = Type::fromFullyQualifiedString(
                $class_name
            );
        } else {
            $type = Type::fromStringInContext(
                $class_name,
                $context
            );
        }

        return $type->asUnionType();
    }

    /**
     * @return \Generator|Clazz[]
     * A list of classes associated with the given node
     *
     * @throws IssueException
     * An exception is thrown if we can't find a class for
     * the given type
     */
    private function classListFromNode(Node $node)
    {
        // Get the types associated with the node
        $union_type = self::unionTypeFromNode(
            $this->code_base,
            $this->context,
            $node
        );

        // Iterate over each viable class type to see if any
        // have the constant we're looking for
        foreach ($union_type->nonNativeTypes()->getTypeSet()
        as $class_type) {
            // Get the class FQSEN
            $class_fqsen = $class_type->asFQSEN();

            // See if the class exists
            if (!$this->code_base->hasClassWithFQSEN($class_fqsen)) {
                throw new IssueException(
                    Issue::fromType(Issue::UndeclaredClassReference)(
                        $this->context->getFile(),
                        $node->lineno ?? 0,
                        [ (string)$class_fqsen ]
                    )
                );
            }

            yield $this->code_base->getClassByFQSEN($class_fqsen);
        }
    }
}
