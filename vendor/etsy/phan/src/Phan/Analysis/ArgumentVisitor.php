<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\AST\ContextNode;
use Phan\AST\Visitor\KindVisitorImplementation;
use Phan\CodeBase;
use Phan\Language\Context;
use ast\Node;
use ast\Node\Decl;

class ArgumentVisitor extends KindVisitorImplementation
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
     * @return void
     */
    public function visit(Node $node)
    {
        // Nothing to do
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return void
     */
    public function visitVar(Node $node)
    {
        try {
            $variable = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getOrCreateVariable();
        } catch (\Exception $exception) {
            // Swallow it
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return void
     */
    public function visitStaticProp(Node $node)
    {
        $this->visitProp($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return void
     */
    public function visitProp(Node $node)
    {
        try {

            // Only look at properties with names that aren't
            // variables or whatever
            if (!is_string($node->children['prop'])) {
                return;
            }

            $property = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getOrCreateProperty($node->children['prop']);

            $property->addReference($this->context);
        } catch (\Exception $exception) {
            // Swallow it
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return void
     */
    public function visitClosure(Decl $node)
    {
        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context->withLineNumberStart($node->lineno ?? 0),
                $node
            ))->getClosure();

            $method->addReference($this->context);
        } catch (\Exception $exception) {
            // Swallow it
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return void
     */
    public function visitCall(Node $node)
    {

        $method_name = '';
        if (isset($node->children['method'])) {
            $method_name = $node->children['method'];
        } elseif (isset($node->children['expr'])) {
            if ($node->children['expr']->kind == \ast\AST_NAME) {
                $method_name = $node->children['expr']->children['name'];
            }
        } else {
            return;
        }

        if (!is_string($method_name)) {
            return;
        }

        try {
            $method = (new ContextNode(
                $this->code_base,
                $this->context,
                $node
            ))->getMethod(
                $method_name,
                false
            );

            $method->addReference($this->context);
        } catch (\Exception $exception) {
            // Swallow it
        }
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return void
     */
    public function visitMethodCall(Node $node)
    {
        return $this->visitCall($node);
    }

    /**
     * @param Node $node
     * A node to parse
     *
     * @return void
     */
    public function visitStaticCall(Node $node)
    {
        return $this->visitCall($node);
    }
}
