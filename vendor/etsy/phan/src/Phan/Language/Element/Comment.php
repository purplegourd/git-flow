<?php
declare(strict_types=1);
namespace Phan\Language\Element;

use Phan\Config;
use Phan\Language\Context;
use Phan\Language\Element\Comment\Parameter as CommentParameter;
use Phan\Language\Type;
use Phan\Language\Type\TemplateType;
use Phan\Language\UnionType;
use Phan\Library\None;
use Phan\Library\Option;
use Phan\Library\Some;

/**
 */
class Comment
{

    /**
     * @var bool
     * Set to true if the comment contains a 'deprecated'
     * directive.
     */
    private $is_deprecated = false;

    /**
     * @var CommentParameter[]
     * A list of CommentParameters from var declarations
     */
    private $variable_list = [];

    /**
     * @var CommentParameter[]
     * A list of CommentParameters from param declarations
     */
    private $parameter_list = [];

    /**
     * @var CommentParameter[]
     * A map from variable name to CommentParameters from
     * param declarations
     */
    private $parameter_map = [];

    /**
     * @var string[]
     * A list of template types parameterizing a generic class
     */
    private $template_type_list = [];

    /**
     * @var Option<Type>
     * Classes may specify their inherited type explicitly
     * via `@inherits Type`.
     */
    private $inherited_type = null;

    /**
     * @var UnionType
     * A UnionType defined by a @return directive
     */
    private $return_union_type = null;

    /**
     * @var string[]
     * A list of issue types to be suppressed
     */
    private $suppress_issue_list = [];

    /**
     * A private constructor meant to ingest a parsed comment
     * docblock.
     *
     * @param bool $is_deprecated
     * Set to true if the comment contains a 'deprecated'
     * directive.
     *
     * @param Variable[] $variable_list
     *
     * @param CommentParameter[] $parameter_list
     *
     * @param string[] $template_type_list
     * A list of template types parameterizing a generic class
     *
     * @param Option<Type> $inherited_type
     * An override on the type of the extended class
     *
     * @param UnionType $return
     *
     * @param string[] $suppress_issue_list
     * A list of tags for error type to be suppressed
     */
    private function __construct(
        bool $is_deprecated,
        array $variable_list,
        array $parameter_list,
        array $template_type_list,
        Option $inherited_type,
        UnionType $return_union_type,
        array $suppress_issue_list
    ) {
        $this->is_deprecated = $is_deprecated;
        $this->variable_list = $variable_list;
        $this->parameter_list = $parameter_list;
        $this->template_type_list = $template_type_list;
        $this->inherited_type = $inherited_type;
        $this->return_union_type = $return_union_type;
        $this->suppress_issue_list = $suppress_issue_list;

        foreach ($this->parameter_list as $i => $parameter) {
            $name = $parameter->getName();
            if (!empty($name)) {
                // Add it to the named map
                $this->parameter_map[$name] = $parameter;

                // Remove it from the offset map
                unset($this->parameter_list[$i]);
            }
        }
    }

    /**
     * @return Comment
     * A comment built by parsing the given doc block
     * string.
     */
    public static function fromStringInContext(
        string $comment,
        Context $context
    ) : Comment {

        if (!Config::get()->read_type_annotations) {
            return new Comment(
                false, [], [], [], new None, new UnionType(), []
            );
        }

        $is_deprecated = false;
        $variable_list = [];
        $parameter_list = [];
        $template_type_list = [];
        $inherited_type = new None;
        $return_union_type = new UnionType();
        $suppress_issue_list = [];

        $lines = explode("\n", $comment);

        foreach ($lines as $line) {

            if (strpos($line, '@param') !== false) {
                $parameter_list[] =
                    self::parameterFromCommentLine($context, $line);
            } elseif (stripos($line, '@var') !== false) {
                $variable_list[] =
                    self::parameterFromCommentLine($context, $line);
            } elseif (stripos($line, '@template') !== false) {

                // Make sure support for generic types is enabled
                if (Config::get()->generic_types_enabled) {
                    if (($template_type =
                        self::templateTypeFromCommentLine($context, $line))
                    ) {
                        $template_type_list[] = $template_type;
                    }
                }
            } elseif (stripos($line, '@inherits') !== false) {
                // Make sure support for generic types is enabled
                if (Config::get()->generic_types_enabled) {
                    $inherited_type =
                        self::inheritsFromCommentLine($context, $line);
                }
            } elseif (stripos($line, '@return') !== false) {
                $return_union_type =
                    self::returnTypeFromCommentLine($context, $line);
            } elseif (stripos($line, '@suppress') !== false) {
                $suppress_issue_list[] =
                    self::suppressIssueFromCommentLine($line);
            }

            if (($pos=stripos($line, '@deprecated')) !== false) {
                if (preg_match('/@deprecated\b/', $line, $match)) {
                    $is_deprecated = true;
                }
            }
        }

        return new Comment(
            $is_deprecated,
            $variable_list,
            $parameter_list,
            $template_type_list,
            $inherited_type,
            $return_union_type,
            $suppress_issue_list
        );
    }

    /**
     * @param Context $context
     * The context in which the comment line appears
     *
     * @param string $line
     * An individual line of a comment
     *
     * @return UnionType
     * The declared return type
     */
    private static function returnTypeFromCommentLine(
        Context $context,
        string $line
    ) {
        $return_union_type_string = '';

        if (preg_match('/@return\s+(' . UnionType::union_type_regex . '+)/', $line, $match)) {
            $return_union_type_string = $match[1];
        }

        $return_union_type = UnionType::fromStringInContext(
            $return_union_type_string,
            $context
        );

        return $return_union_type;
    }

    /**
     * @param Context $context
     * The context in which the comment line appears
     *
     * @param string $line
     * An individual line of a comment
     *
     * @return CommentParameter
     * A CommentParameter associated with a line that has a var
     * or param reference.
     */
    private static function parameterFromCommentLine(
        Context $context,
        string $line
    ) {
        $match = [];
        if (preg_match('/@(param|var)\s+(' . UnionType::union_type_regex . ')(\s+(\\$\S+))?/', $line, $match)) {
            $type = $match[2];

            $variable_name =
                empty($match[23]) ? '' : trim($match[23], '$');

            // If the type looks like a variable name, make it an
            // empty type so that other stuff can match it. We can't
            // just skip it or we'd mess up the parameter order.
            $union_type = null;
            if (0 !== strpos($type, '$')) {
                $union_type =
                    UnionType::fromStringInContext(
                        $type,
                        $context
                    );
            } else {
                $union_type = new UnionType();
            }

            return new CommentParameter(
                $variable_name,
                $union_type
            );
        }

        return  new CommentParameter('', new UnionType());
    }

    /**
     * @param Context $context
     * The context in which the comment line appears
     *
     * @param string $line
     * An individual line of a comment
     *
     * @return TemplateType|null
     * A generic type identifier or null if a valid type identifier
     * wasn't found.
     */
    private static function templateTypeFromCommentLine(
        Context $context,
        string $line
    ) {
        $match = [];
        if (preg_match('/@template\s+(' . Type::simple_type_regex. ')/', $line, $match)) {
            $template_type_identifier = $match[1];
            return new TemplateType($template_type_identifier);
        }

        return null;
    }

    /**
     * @param Context $context
     * The context in which the comment line appears
     *
     * @param string $line
     * An individual line of a comment
     *
     * @return Option<Type>
     * An optional type overriding the extended type of the class
     */
    private static function inheritsFromCommentLine(
        Context $context,
        string $line
    ) {
        $match = [];
        if (preg_match('/@inherits\s+(' . Type::type_regex . ')/', $line, $match)) {
            $type_string = $match[1];

            $type = new Some(Type::fromStringInContext(
                $type_string,
                $context
            ));

            return $type;
        }

        return new None();
    }

    /**
     * @param string $line
     * An individual line of a comment
     *
     * @return string
     * An issue name to suppress
     */
    private static function suppressIssueFromCommentLine(
        string $line
    ) : string {
        if (preg_match('/@suppress\s+([^\s]+)/', $line, $match)) {
            return $match[1];
        }

        return '';
    }

    /**
     * @return bool
     * Set to true if the comment contains a 'deprecated'
     * directive.
     */
    public function isDeprecated() : bool
    {
        return $this->is_deprecated;
    }

    /**
     * @return UnionType
     * A UnionType defined by a (at)return directive
     */
    public function getReturnType() : UnionType
    {
        return $this->return_union_type;
    }

    /**
     * @return bool
     * True if this doc block contains a (at)return
     * directive specifying a type.
     */
    public function hasReturnUnionType() : bool
    {
        return !empty($this->return_union_type) && !$this->return_union_type->isEmpty();
    }

    /**
     * @return CommentParameter[]
     *
     * @suppress PhanUnreferencedMethod
     */
    public function getParameterList() : array
    {
        return $this->parameter_list;
    }

    /**
     * @return TemplateType[]
     * A list of template types parameterizing a generic class
     */
    public function getTemplateTypeList() : array
    {
        return $this->template_type_list;
    }

    /**
     * @return Option<Type>
     * An optional type declaring what a class extends.
     */
    public function getInheritedTypeOption()
    {
        return $this->inherited_type;
    }

    /**
     * @return string[]
     * A set of issie names like 'PhanUnreferencedMethod' to suppress
     */
    public function getSuppressIssueList() : array
    {
        return $this->suppress_issue_list;
    }

    /**
     * @return bool
     * True if we have a parameter at the given offset
     */
    public function hasParameterWithNameOrOffset(
        string $name,
        int $offset
    ) : bool {
        if (!empty($this->parameter_map[$name])) {
            return true;
        }

        return !empty($this->parameter_list[$offset]);
    }

    /**
     * @return CommentParameter
     * The paramter at the given offset
     */
    public function getParameterWithNameOrOffset(
        string $name,
        int $offset
    ) : CommentParameter {
        if (!empty($this->parameter_map[$name])) {
            return $this->parameter_map[$name];
        }

        return $this->parameter_list[$offset];
    }

    /**
     * @return CommentParameter[]
     */
    public function getVariableList() : array
    {
        return $this->variable_list;
    }

    public function __toString() : string
    {
        $string = "/**\n";

        if ($this->is_deprecated) {
            $string  .= " * @deprecated\n";
        }

        foreach ($this->variable_list as $variable) {
            $string  .= " * @var $variable\n";
        }

        foreach ($this->parameter_list as $parameter) {
            $string  .= " * @var $parameter\n";
        }

        if ($this->return_union_type) {
            $string .= " * @return {$this->return_union_type}\n";
        }

        $string .= " */\n";

        return $string;
    }
}
