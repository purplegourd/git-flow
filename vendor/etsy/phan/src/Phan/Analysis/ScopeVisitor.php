<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\AnalysisVisitor;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\FQSEN;
use Phan\Language\FQSEN\FullyQualifiedClassName;
use Phan\Language\FQSEN\FullyQualifiedGlobalConstantName;
use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use ast\Node;

abstract class ScopeVisitor extends AnalysisVisitor {

    /**
     * @param CodeBase $code_base
     * The global code base holding all state
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     */
    public function __construct(
        CodeBase $code_base,
        Context $context
    ) {
        parent::__construct($code_base, $context);
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
    public function visit(Node $node) : Context {
        // Many nodes don't change the context and we
        // don't need to read them.
        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_DECLARE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitDeclare(Node $node) : Context
    {
        $declares = $node->children['declares'];
        $name = $declares->children[0]->children['name'];
        $value = $declares->children[0]->children['value'];
        if ('strict_types' === $name) {
            return $this->context->withStrictTypes($value);
        }

        return $this->context;
    }

    /**
     * Visit a node with kind `\ast\AST_NAMESPACE`
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitNamespace(Node $node) : Context {
        $namespace = '\\' . (string)$node->children['name'];
        return $this->context->withNamespace($namespace);
    }

    /**
     * Visit a node with kind `\ast\AST_GROUP_USE`
     * such as `use \ast\Node;`.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitGroupUse(Node $node) : Context {
        $children = $node->children ?? [];

        $prefix = array_shift($children);

        $context = $this->context;

        foreach ($this->aliasTargetMapFromUseNode(
                $children['uses'],
                $prefix
            ) as $alias => $map
        ) {
            list($flags, $target) = $map;
            $context = $context->withNamespaceMap(
                $flags, $alias, $target
            );
        }

        return $context;
    }

    /**
     * Visit a node with kind `\ast\AST_USE`
     * such as `use \ast\Node;`.
     *
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitUse(Node $node) : Context {
        $context = $this->context;

        foreach ($this->aliasTargetMapFromUseNode($node)
            as $alias => $map
        ) {
            list($flags, $target) = $map;
            $context = $context->withNamespaceMap(
                $node->flags ?? 0, $alias, $target
            );
        }

        return $context;
    }

    /**
     * @return array
     * A map from alias to target
     */
    private function aliasTargetMapFromUseNode(
        Node $node,
        string $prefix = ''
    ) : array {
        assert($node->kind == \ast\AST_USE,
            'Method takes AST_USE nodes');

        $map = [];
        foreach($node->children ?? [] as $child_node) {
            $target = $child_node->children['name'];

            if(empty($child_node->children['alias'])) {
                if(($pos = strrpos($target, '\\'))!==false) {
                    $alias = substr($target, $pos + 1);
                } else {
                    $alias = $target;
                }
            } else {
                $alias = $child_node->children['alias'];
            }

            // if AST_USE does not have any flags set, then its AST_USE_ELEM
            // children will (this will be for AST_GROUP_USE)
            if ($node->flags !== 0) {
                $target_node = $node;
            } else {
                $target_node = $child_node;
            }

            if ($target_node->flags == \ast\flags\USE_FUNCTION) {
                $parts = explode('\\', $target);
                $function_name = array_pop($parts);
                $target = FullyQualifiedFunctionName::make(
                    $prefix . '\\' . implode('\\', $parts),
                    $function_name
                );
            } else if ($target_node->flags == \ast\flags\USE_CONST) {
                $parts = explode('\\', $target);
                $name = array_pop($parts);
                $target = FullyQualifiedGlobalConstantName::make(
                    $prefix . '\\' . implode('\\', $parts),
                    $name
                );
            } else {
                assert($target_node->flags == \ast\flags\USE_NORMAL,
                    'Unknown type for a use statement');
                $target = FullyQualifiedClassName::fromFullyQualifiedString(
                    $prefix . '\\' . $target
                );
            }

            $map[$alias] = [$target_node->flags, $target];
        }

        return $map;
    }
}
