<?php declare(strict_types=1);
namespace Phan;

use Phan\CodeBase;
use Phan\Language\Context;
use Phan\Language\Element\Clazz;
use Phan\Language\Element\Method;
use Phan\Language\Element\Func;
use ast\Node;

/**
 * Plugins can be defined in the config and will have
 * their hooks called at appropriate times during analysis
 * of each file, class, method and function.
 *
 * Plugins must extends this class and return an instance
 * of themselves.
 */
abstract class Plugin {

    /**
     * @param CodeBase $code_base
     * The code base in which the node exists
     *
     * @param Context $context
     * The context in which the node exits. This is
     * the context inside the given node rather than
     * the context outside of the given node
     *
     * @param Node $node
     * The php-ast Node being analyzed.
     *
     * @param Node $node
     * The parent node of the given node (if one exists).
     *
     * @return void
     */
    abstract public function analyzeNode(
        CodeBase $code_base,
        Context $context,
        Node $node,
        Node $parent_node = null
    );

    /**
     * @param CodeBase $code_base
     * The code base in which the class exists
     *
     * @param Clazz $class
     * A class being analyzed
     *
     * @return void
     */
    abstract public function analyzeClass(
        CodeBase $code_base,
        Clazz $class
    );

    /**
     * @param CodeBase $code_base
     * The code base in which the method exists
     *
     * @param Method $method
     * A method being analyzed
     *
     * @return void
     */
    abstract public function analyzeMethod(
        CodeBase $code_base,
        Method $method
    );

    /**
     * @param CodeBase $code_base
     * The code base in which the function exists
     *
     * @param Func $function
     * A function being analyzed
     *
     * @return void
     */
    abstract public function analyzeFunction(
        CodeBase $code_base,
        Func $function
    );

    /**
     * Emit an issue if it is not suppressed
     *
     * @param CodeBase $code_base
     * The code base in which the issue was found
     *
     * @param Context $context
     * The context in which the issue was found
     *
     * @param string $issue_type
     * A name for the type of issue such as 'PhanPluginMyIssue'
     *
     * @param string $issue_message
     * The complete issue message to emit such as 'class with
     * fqsen \NS\Name is broken in some fashion'.
     *
     * @param int $severity
     * A value from the set {Issue::SEVERITY_LOW,
     * Issue::SEVERITY_NORMAL, Issue::SEVERITY_HIGH}.
     *
     * @param int $remediation_difficulty
     * A guess at how hard the issue will be to fix from the
     * set {Issue:REMEDIATION_A, Issue:REMEDIATION_B, ...
     * Issue::REMEDIATION_F} with F being the hardest.
     */
    public function emitIssue(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        string $issue_message,
        int $severity = Issue::SEVERITY_NORMAL,
        int $remediation_difficulty = Issue::REMEDIATION_B,
        int $issue_type_id = Issue::TYPE_ID_UNKNOWN
    ) {
        $issue = new Issue(
            $issue_type,
            Issue::CATEGORY_PLUGIN,
            $severity,
            $issue_message,
            $remediation_difficulty,
            $issue_type_id
        );

        $issue_instance = new IssueInstance(
            $issue,
            $context->getFile(),
            $context->getLineNumberStart(),
            []
        );

        Issue::maybeEmitInstance(
            $code_base,
            $context,
            $issue_instance
        );
    }

}
