<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Langauge\Type;
use Phan\Language\Context;
use Phan\Language\Type\ArrayType;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use ast\Node;

class ConditionVisitor extends KindVisitorImplementation
{

    /**
     * @var CodeBase
     */
    private $code_base;

    /**
     * @var Context
     * The context in which the node we're going to be looking
     * at exits.
     */
    private $context;

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
    }

    /**
     * Default visitor for node kinds that do not have
     * an overriding method
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visit(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitBinaryOp(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUnaryOp(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCoalesce(Node $node) : Context
    {
        return $this->context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIsset(Node $node) : Context
    {
        return $this->context;

        /*
        // Only look at things of the form
        // `isset($variable)`
        if ($node->children['var']->kind !== \ast\AST_VAR) {
            return $this->context;
        }

        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['var']
            ))->getVariable();

            $v0 = $variable;

            // Make a copy of the variable
            $variable = clone($variable);

            // Remove null from the list of possible types
            // given that we know that the variable is
            // set
            $variable->getUnionType()->removeType(
                NullType::instance()
            );

            // Overwrite the variable with its new type
            $this->context->addScopeVariable(
                $variable
            );
        } catch (\Exception $exception) {
            // Swallow it
        }

        return $this->context;
        */
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitInstanceof(Node $node) : Context
    {
        // Only look at things of the form
        // `$variable instanceof ClassName`
        if ($node->children['expr']->kind !== \ast\AST_VAR) {
            return $this->context;
        }

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['expr']
            ))->getVariable();

            // Get the type that we're checking it against
            $type = UnionType::fromNode(
                $this->context,
                $this->code_base,
                $node->children['class']
            );

            // Make a copy of the variable
            $variable = clone($variable);

            // Add the type to the variable
            $variable->getUnionType()->addUnionType($type);

            // Overwrite the variable with its new type
            $context = $context->withScopeVariable(
                $variable
            );

        } catch (\Exception $exception) {
            // Swallow it
        }

        return $context;
    }

    /**
     * Look at elements of the form `is_array($v)` and modify
     * the type of the variable.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitCall(Node $node) : Context
    {
        // Only look at things of the form
        // `is_string($variable)`
        if (count($node->children['args']->children) !== 1
            || !$node->children['args']->children[0] instanceof Node
            || $node->children['args']->children[0]->kind !== \ast\AST_VAR
            || !($node->children['expr'] instanceof Node)
            || empty($node->children['expr']->children['name'] ?? null)
            || !is_string($node->children['expr']->children['name'])
        ) {
            return $this->context;
        }

        // Translate the function name into the UnionType it asserts
        $map = array(
            'is_array' => 'array',
            'is_bool' => 'bool',
            'is_callable' => 'callable',
            'is_double' => 'float',
            'is_float' => 'float',
            'is_int' => 'int',
            'is_integer' => 'int',
            'is_long' => 'int',
            'is_null' => 'null',
            'is_numeric' => 'string|int|float',
            'is_object' => 'object',
            'is_real' => 'float',
            'is_resource' => 'resource',
            'is_scalar' => 'int|float|bool|string|null',
            'is_string' => 'string',
            'empty' => 'null',
        );

        $functionName = $node->children['expr']->children['name'];
        if (!isset($map[$functionName])) {
            return $this->context;
        }

        $type = UnionType::fromFullyQualifiedString(
            $map[$functionName]
        );

        $context = $this->context;

        try {
            // Get the variable we're operating on
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node->children['args']->children[0]
            ))->getVariable();

            if ($variable->getUnionType()->isEmpty()) {
                $variable->getUnionType()->addType(
                    NullType::instance()
                );
            }

            // Make a copy of the variable
            $variable = clone($variable);

            $variable->setUnionType(
                clone($variable->getUnionType())
            );

            // Change the type to match the is_a relationship
            if ($type->isType(ArrayType::instance())
                && $variable->getUnionType()->hasGenericArray()
            ) {
                // If the variable is already a generic array,
                // note that it can be an arbitrary array without
                // erasing the existing generic type.
                $variable->getUnionType()->addUnionType($type);
            } else {
                // Otherwise, overwrite the type for any simple
                // primitive types.
                $variable->setUnionType($type);
            }

            // Overwrite the variable with its new type in this
            // scope without overwriting other scopes
            $context = $context->withScopeVariable(
                $variable
            );
        } catch (\Exception $exception) {
            // Swallow it
        }

        return $context;
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitEmpty(Node $node) : Context
    {
        return $this->context;
    }
}
