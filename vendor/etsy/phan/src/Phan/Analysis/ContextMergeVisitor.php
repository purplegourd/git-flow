<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Variable;
use Phan\Language\Scope;
use Phan\Language\Type\NullType;
use Phan\Language\UnionType;
use Phan\Library\Set;
use ast\Node;

class ContextMergeVisitor extends KindVisitorImplementation
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
     * @var Context[]
     * A list of the contexts returned after depth-first
     * parsing of all first-level children of this node
     */
    private $child_context_list;

    /**
     * @param CodeBase $code_base
     * A code base needs to be passed in because we require
     * it to be initialized before any classes or files are
     * loaded.
     *
     * @param Context $context
     * The context of the parser at the node for which we'd
     * like to determine a type
     *
     * @param Context[] $child_context_list
     * A list of the contexts returned after depth-first
     * parsing of all first-level children of this node
     */
    public function __construct(
        CodeBase $code_base,
        Context $context,
        array $child_context_list
    ) {
        $this->code_base = $code_base;
        $this->context = $context;
        $this->child_context_list = $child_context_list;
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

        // TODO: if ($this->context->isInGlobalScope()) {
        //            copy local to global
        //       }

        return end($this->child_context_list) ?: $this->context;
    }

    public function visitTry(Node $node) : Context
    {
        // Get the list of scopes for each branch of the
        // conditional
        $scope_list = array_map(function (Context $context) {
            return $context->getScope();
        }, $this->child_context_list);

        // The 0th scope is the scope from Try
        $try_scope = $scope_list[0];

        $catch_scope_list = [];
        foreach ($node->children['catches'] ?? [] as $i => $catch_node) {
            $catch_scope_list[] = $scope_list[((int)$i)+1];
        }

        // Merge in the types for any variables found in a catch.
        foreach ($try_scope->getVariableMap() as $variable_name => $variable) {
            foreach ($catch_scope_list as $catch_scope) {

                // Merge types if try and catch have a variable in common
                if ($catch_scope->hasVariableWithName($variable_name)) {
                    $catch_variable = $catch_scope->getVariableByName(
                        $variable_name
                    );

                    $variable->getUnionType()->addUnionType(
                        $catch_variable->getUnionType()
                    );
                }
            }
        }

        // Look for variables that exist in catch, but not try
        foreach ($catch_scope_list as $catch_scope) {
            foreach ($catch_scope->getVariableMap() as $variable_name => $variable) {
                if (!$try_scope->hasVariableWithName($variable_name)) {

                    // Note that it can be null
                    $variable->getUnionType()->addType(
                        NullType::instance()
                    );

                    // Add it to the try scope
                    $try_scope->addVariable($variable);
                }
            }
        }

        // If we have a finally, overwite types for each
        // element
        if (!empty($node->children['finallyStmts'])
            || !empty($node->children['finally'])
        ) {
            $finally_scope = $scope_list[count($scope_list)-1];

            foreach ($try_scope->getVariableMap() as $variable_name => $variable) {
                if ($finally_scope->hasVariableWithName($variable_name)) {
                    $finally_variable =
                        $finally_scope->getVariableByName($variable_name);

                    // Overwrite the variable with the type from the
                    // finally
                    if (!$finally_variable->getUnionType()->isEmpty()) {
                        $variable->setUnionType(
                            $finally_variable->getUnionType()
                        );
                    }
                }
            }

            // Look for variables that exist in finally, but not try
            foreach ($finally_scope->getVariableMap() as $variable_name => $variable) {
                if (!$try_scope->hasVariableWithName($variable_name)) {
                    $try_scope->addVariable($variable);
                }
            }
        }

        // Return the context of the try with the types of
        // variables within its scope limited appropriately
        return $this->child_context_list[0];
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return Context
     * A new or an unchanged context resulting from
     * parsing the node
     */
    public function visitIf(Node $node) : Context
    {
        // Get the list of scopes for each branch of the
        // conditional
        $scope_list = array_map(function (Context $context) {
            return $context->getScope();
        }, $this->child_context_list);

        $has_else = array_reduce(
            $node->children ?? [],
            function (bool $carry, $child_node) {
                return $carry || (
                    $child_node instanceof Node
                    && empty($child_node->children['cond'])
                );
            },
            false
        );

        // If we're not guaranteed to hit at least one
        // branch, mark the incoming scope as a possibility
        if (!$has_else) {
            $scope_list[] = $this->context->getScope();
        }

        // If there weren't multiple branches, continue on
        // as if the conditional never happened
        if (count($scope_list) < 2) {
            return array_values($this->child_context_list)[0];
        }

        // Get a list of all variables in all scopes
        $variable_map = [];
        foreach ($scope_list as $i => $scope) {
            foreach ($scope->getVariableMap() as $name => $variable) {
                $variable_map[$name] = $variable;
            }
        }

        // A function that determins if a variable is defined on
        // every branch
        $is_defined_on_all_branches =
            function (string $variable_name) use ($scope_list) {
                return array_reduce(
                    $scope_list,
                    function (bool $has_variable, Scope $scope)
                    use ($variable_name) {

                        return (
                            $has_variable &&
                            $scope->hasVariableWithName($variable_name)
                        );
                    },
                    true
                );
            };

        // Get the intersection of all types for all versions of
        // the variable from every side of the branch
        $union_type =
            function (string $variable_name) use ($scope_list) {

                // Get a list of all variables with the given name from
                // each scope
                $variable_list = array_filter(array_map(
                    function (Scope $scope) use ($variable_name) {
                        if (!$scope->hasVariableWithName($variable_name)) {
                            return null;
                        }

                        return $scope->getVariableByName($variable_name);
                    },
                    $scope_list
                ));

                // Get the list of types for each version of the variable
                $type_set_list = array_map(function (Variable $variable) : Set {
                    return $variable->getUnionType()->getTypeSet();
                }, $variable_list);

                if (count($type_set_list) < 2) {
                    return new UnionType($type_set_list[0] ?? []);
                }

                return new UnionType(
                    Set::unionAll($type_set_list)
                );
            };

        // Clone the incoming scope so we can modify it
        // with the outgoing merged scope
        $scope = clone($this->context->getScope());

        foreach ($variable_map as $name => $variable) {
            // Skip variables that are only partially defined
            if (!$is_defined_on_all_branches($name)) {
                if ($this->context->getIsStrictTypes()) {
                    continue;
                } else {
                    $variable->getUnionType()->addType(
                        NullType::instance()
                    );
                }
            }

            // Limit the type of the variable to the subset
            // of types that are common to all branches
            $variable = clone($variable);

            $variable->setUnionType(
                $union_type($name)
            );

            // Add the variable to the outgoing scope
            $scope->addVariable($variable);
        }

        // Set the new scope with only the variables and types
        // that are common to all branches
        return $this->context->withScope($scope);
    }
}
