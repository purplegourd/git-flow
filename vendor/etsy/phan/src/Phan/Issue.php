<?php declare(strict_types=1);
namespace Phan;

use Phan\CodeBase;
use Phan\Language\Context;

/**
 * An issue emitted during the course of analysis
 */
class Issue
{
    const SyntaxError               = 'PhanSyntaxError';

    // Issue::CATEGORY_UNDEFINED
    const EmptyFile                 = 'PhanEmptyFile';
    const ParentlessClass           = 'PhanParentlessClass';
    const TraitParentReference      = 'PhanTraitParentReference';
    const UndeclaredClass           = 'PhanUndeclaredClass';
    const UndeclaredClassCatch      = 'PhanUndeclaredClassCatch';
    const UndeclaredClassConstant   = 'PhanUndeclaredClassConstant';
    const UndeclaredClassInstanceof = 'PhanUndeclaredClassInstanceof';
    const UndeclaredClassMethod     = 'PhanUndeclaredClassMethod';
    const UndeclaredClassReference  = 'PhanUndeclaredClassReference';
    const UndeclaredConstant        = 'PhanUndeclaredConstant';
    const UndeclaredExtendedClass   = 'PhanUndeclaredExtendedClass';
    const UndeclaredFunction        = 'PhanUndeclaredFunction';
    const UndeclaredInterface       = 'PhanUndeclaredInterface';
    const UndeclaredMethod          = 'PhanUndeclaredMethod';
    const UndeclaredProperty        = 'PhanUndeclaredProperty';
    const UndeclaredStaticMethod    = 'PhanUndeclaredStaticMethod';
    const UndeclaredStaticProperty  = 'PhanUndeclaredStaticProperty';
    const UndeclaredTrait           = 'PhanUndeclaredTrait';
    const UndeclaredTypeParameter   = 'PhanUndeclaredTypeParameter';
    const UndeclaredTypeProperty    = 'PhanUndeclaredTypeProperty';
    const UndeclaredVariable        = 'PhanUndeclaredVariable';

    // Issue::CATEGORY_TYPE
    const NonClassMethodCall        = 'PhanNonClassMethodCall';
    const TypeArrayOperator         = 'PhanTypeArrayOperator';
    const TypeArraySuspicious       = 'PhanTypeArraySuspicious';
    const TypeComparisonFromArray   = 'PhanTypeComparisonFromArray';
    const TypeComparisonToArray     = 'PhanTypeComparisonToArray';
    const TypeConversionFromArray   = 'PhanTypeConversionFromArray';
    const TypeInstantiateAbstract   = 'PhanTypeInstantiateAbstract';
    const TypeInstantiateInterface  = 'PhanTypeInstantiateInterface';
    const TypeInvalidLeftOperand    = 'PhanTypeInvalidLeftOperand';
    const TypeInvalidRightOperand   = 'PhanTypeInvalidRightOperand';
    const TypeMismatchArgument      = 'PhanTypeMismatchArgument';
    const TypeMismatchArgumentInternal = 'PhanTypeMismatchArgumentInternal';
    const TypeMismatchDefault       = 'PhanTypeMismatchDefault';
    const TypeMismatchForeach       = 'PhanTypeMismatchForeach';
    const TypeMismatchProperty      = 'PhanTypeMismatchProperty';
    const TypeMismatchReturn        = 'PhanTypeMismatchReturn';
    const TypeMissingReturn         = 'PhanTypeMissingReturn';
    const TypeNonVarPassByRef       = 'PhanTypeNonVarPassByRef';
    const TypeParentConstructorCalled = 'PhanTypeParentConstructorCalled';
    const TypeVoidAssignment        = 'PhanTypeVoidAssignment';

    // Issue::CATEGORY_ANALYSIS
    const Unanalyzable              = 'PhanUnanalyzable';

    // Issue::CATEGORY_VARIABLE
    const VariableUseClause         = 'PhanVariableUseClause';

    // Issue::CATEGORY_STATIC
    const StaticCallToNonStatic     = 'PhanStaticCallToNonStatic';

    // Issue::CATEGORY_CONTEXT
    const ContextNotObject          = 'PhanContextNotObject';

    // Issue::CATEGORY_DEPRECATED
    const DeprecatedClass           = 'PhanDeprecatedClass';
    const DeprecatedFunction        = 'PhanDeprecatedFunction';
    const DeprecatedProperty        = 'PhanDeprecatedProperty';

    // Issue::CATEGORY_PARAMETER
    const ParamReqAfterOpt          = 'PhanParamReqAfterOpt';
    const ParamSpecial1             = 'PhanParamSpecial1';
    const ParamSpecial2             = 'PhanParamSpecial2';
    const ParamSpecial3             = 'PhanParamSpecial3';
    const ParamSpecial4             = 'PhanParamSpecial4';
    const ParamTooFew               = 'PhanParamTooFew';
    const ParamTooFewInternal       = 'PhanParamTooFewInternal';
    const ParamTooMany              = 'PhanParamTooMany';
    const ParamTooManyInternal      = 'PhanParamTooManyInternal';
    const ParamTypeMismatch         = 'PhanParamTypeMismatch';
    const ParamSignatureMismatch    = 'PhanParamSignatureMismatch';
    const ParamSignatureMismatchInternal = 'PhanParamSignatureMismatchInternal';
    const ParamRedefined            = 'PhanParamRedefined';

    // Issue::CATEGORY_NOOP
    const NoopArray                 = 'PhanNoopArray';
    const NoopClosure               = 'PhanNoopClosure';
    const NoopConstant              = 'PhanNoopConstant';
    const NoopProperty              = 'PhanNoopProperty';
    const NoopVariable              = 'PhanNoopVariable';
    const UnreferencedClass         = 'PhanUnreferencedClass';
    const UnreferencedMethod        = 'PhanUnreferencedMethod';
    const UnreferencedProperty      = 'PhanUnreferencedProperty';
    const UnreferencedConstant      = 'PhanUnreferencedConstant';

    // Issue::CATEGORY_REDEFINE
    const RedefineClass             = 'PhanRedefineClass';
    const RedefineClassInternal     = 'PhanRedefineClassInternal';
    const RedefineFunction          = 'PhanRedefineFunction';
    const RedefineFunctionInternal  = 'PhanRedefineFunctionInternal';
    const IncompatibleCompositionProp = 'PhanIncompatibleCompositionProp';
    const IncompatibleCompositionMethod = 'PhanIncompatibleCompositionMethod';

    // Issue::CATEGORY_ACCESS
    const AccessPropertyPrivate     = 'PhanAccessPropertyPrivate';
    const AccessPropertyProtected   = 'PhanAccessPropertyProtected';
    const AccessMethodPrivate       = 'PhanAccessMethodPrivate';
    const AccessMethodProtected     = 'PhanAccessMethodProtected';
    const AccessSignatureMismatch   = 'PhanAccessSignatureMismatch';
    const AccessSignatureMismatchInternal = 'PhanAccessSignatureMismatchInternal';
    const AccessStaticToNonStatic   = 'PhanAccessStaticToNonStatic';
    const AccessNonStaticToStatic   = 'PhanAccessNonStaticToStatic';

    // Issue::CATEGORY_COMPATIBLE
    const CompatibleExpressionPHP7  = 'PhanCompatibleExpressionPHP7';
    const CompatiblePHP7            = 'PhanCompatiblePHP7';

    // Issue::CATEGORY_GENERIC
    const TemplateTypeConstant      = 'PhanTemplateTypeConstant';
    const TemplateTypeStaticMethod  = 'PhanTemplateTypeStaticMethod';
    const TemplateTypeStaticProperty = 'PhanTemplateTypeStaticProperty';
    const GenericGlobalVariable     = 'PhanGenericGlobalVariable';
    const GenericConstructorTypes   = 'PhanGenericConstructorTypes';

    const CATEGORY_ACCESS            = 1 << 1;
    const CATEGORY_ANALYSIS          = 1 << 2;
    const CATEGORY_COMPATIBLE        = 1 << 3;
    const CATEGORY_CONTEXT           = 1 << 4;
    const CATEGORY_DEPRECATED        = 1 << 5;
    const CATEGORY_NOOP              = 1 << 6;
    const CATEGORY_PARAMETER         = 1 << 7;
    const CATEGORY_REDEFINE          = 1 << 8;
    const CATEGORY_STATIC            = 1 << 9;
    const CATEGORY_TYPE              = 1 << 10;
    const CATEGORY_UNDEFINED         = 1 << 11;
    const CATEGORY_VARIABLE          = 1 << 12;
    const CATEGORY_PLUGIN            = 1 << 13;
    const CATEGORY_GENERIC           = 1 << 14;

    const CATEGORY_NAME = [
        self::CATEGORY_ACCESS            => 'AccessError',
        self::CATEGORY_ANALYSIS          => 'Analysis',
        self::CATEGORY_COMPATIBLE        => 'CompatError',
        self::CATEGORY_CONTEXT           => 'Context',
        self::CATEGORY_DEPRECATED        => 'DeprecatedError',
        self::CATEGORY_NOOP              => 'NOOPError',
        self::CATEGORY_PARAMETER         => 'ParamError',
        self::CATEGORY_REDEFINE          => 'RedefineError',
        self::CATEGORY_STATIC            => 'StaticCallError',
        self::CATEGORY_TYPE              => 'TypeError',
        self::CATEGORY_UNDEFINED         => 'UndefError',
        self::CATEGORY_VARIABLE          => 'VarError',
        self::CATEGORY_PLUGIN            => 'Plugin',
        self::CATEGORY_GENERIC           => 'Generic',
    ];

    const SEVERITY_LOW      = 0;
    const SEVERITY_NORMAL   = 5;
    const SEVERITY_CRITICAL = 10;

    // See https://docs.codeclimate.com/v1.0/docs/remediation
    const REMEDIATION_A = 1000000;
    const REMEDIATION_B = 3000000;
    const REMEDIATION_C = 6000000;
    const REMEDIATION_D = 12000000;
    const REMEDIATION_E = 16000000;
    const REMEDIATION_F = 18000000;

    // type id constants.
    const TYPE_ID_UNKNOWN = 999;

    /** @var string */
    private $type;

    /** @var int */
    private $type_id;

    /** @var int */
    private $category;

    /** @var int */
    private $severity;

    /** @var string */
    private $template;

    /** @var int */
    private $remediation_difficulty;

    /**
     * @param string $type
     * @param int $category
     * @param int $severity
     * @param string $template
     * @param int $remediation_difficulty
     * @param int $type_id (unique integer id for $type)
     */
    public function __construct(
        string $type,
        int $category,
        int $severity,
        string $template,
        int $remediation_difficulty,
        int $type_id
    ) {
        $this->type = $type;
        $this->category = $category;
        $this->severity = $severity;
        $this->template = $template;
        $this->remediation_difficulty = $remediation_difficulty;
        $this->type_id = $type_id;
    }

    /**
     * @return Issue[]
     */
    public static function issueMap()
    {
        static $error_map;

        if (!empty($error_map)) {
            return $error_map;
        }

        /**
         * @var Issue[]
         * Note: All type ids should be unique, and be grouped by the category.
         * (E.g. If the category is (1 << x), then the type_id should be x*1000 + y
         * If new type ids are added, existing ones should not be changed.
         */
        $error_list = [
            new Issue(
                self::SyntaxError,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "%s",
                self::REMEDIATION_A,
                1
            ),

            // Issue::CATEGORY_UNDEFINED
            new Issue(
                self::EmptyFile,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_LOW,
                "Empty file %s",
                self::REMEDIATION_B,
                1000
            ),
            new Issue(
                self::ParentlessClass,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Reference to parent of class %s that does not extend anything",
                self::REMEDIATION_B,
                1001
            ),
            new Issue(
                self::UndeclaredClass,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Reference to undeclared class %s",
                self::REMEDIATION_B,
                1002
            ),
            new Issue(
                self::UndeclaredExtendedClass,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Class extends undeclared class %s",
                self::REMEDIATION_B,
                1003
            ),
            new Issue(
                self::UndeclaredInterface,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Class implements undeclared interface %s",
                self::REMEDIATION_B,
                1004
            ),
            new Issue(
                self::UndeclaredTrait,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Class uses undeclared trait %s",
                self::REMEDIATION_B,
                1005
            ),
            new Issue(
                self::UndeclaredClassCatch,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Catching undeclared class %s",
                self::REMEDIATION_B,
                1006
            ),
            new Issue(
                self::UndeclaredClassConstant,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Reference to constant %s from undeclared class %s",
                self::REMEDIATION_B,
                1007
            ),
            new Issue(
                self::UndeclaredClassInstanceof,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Checking instanceof against undeclared class %s",
                self::REMEDIATION_B,
                1008
            ),
            new Issue(
                self::UndeclaredClassMethod,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Call to method %s from undeclared class %s",
                self::REMEDIATION_B,
                1009
            ),
            new Issue(
                self::UndeclaredClassReference,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Reference to undeclared class %s",
                self::REMEDIATION_B,
                1010
            ),
            new Issue(
                self::UndeclaredConstant,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Reference to undeclared constant %s",
                self::REMEDIATION_B,
                1011
            ),
            new Issue(
                self::UndeclaredFunction,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_CRITICAL,
                "Call to undeclared function %s",
                self::REMEDIATION_B,
                1012
            ),
            new Issue(
                self::UndeclaredMethod,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Call to undeclared method %s",
                self::REMEDIATION_B,
                1013
            ),
            new Issue(
                self::UndeclaredStaticMethod,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Static call to undeclared method %s",
                self::REMEDIATION_B,
                1014
            ),
            new Issue(
                self::UndeclaredProperty,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Reference to undeclared property %s",
                self::REMEDIATION_B,
                1015
            ),
            new Issue(
                self::UndeclaredStaticProperty,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Static property '%s' on %s is undeclared",
                self::REMEDIATION_B,
                1016
            ),
            new Issue(
                self::TraitParentReference,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_LOW,
                "Reference to parent from trait %s",
                self::REMEDIATION_B,
                1017
            ),
            new Issue(
                self::UndeclaredVariable,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Variable \$%s is undeclared",
                self::REMEDIATION_B,
                1018
            ),
            new Issue(
                self::UndeclaredTypeParameter,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Parameter of undeclared type %s",
                self::REMEDIATION_B,
                1019
            ),
            new Issue(
                self::UndeclaredTypeProperty,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_NORMAL,
                "Property %s has undeclared type %s",
                self::REMEDIATION_B,
                1020
            ),

            // Issue::CATEGORY_ANALYSIS
            new Issue(
                self::Unanalyzable,
                self::CATEGORY_UNDEFINED,
                self::SEVERITY_LOW,
                "Expression is unanalyzable or feature is unimplemented. Please create an issue at https://github.com/etsy/phan/issues/new.",
                self::REMEDIATION_B,
                2000
            ),

            // Issue::CATEGORY_TYPE
            new Issue(
                self::TypeMismatchProperty,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Assigning %s to property but %s is %s",
                self::REMEDIATION_B,
                10001
            ),
            new Issue(
                self::TypeMismatchDefault,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Default value for %s \$%s can't be %s",
                self::REMEDIATION_B,
                10002
            ),
            new Issue(
                self::TypeMismatchArgument,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s defined at %s:%d",
                self::REMEDIATION_B,
                10003
            ),
            new Issue(
                self::TypeMismatchArgumentInternal,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s",
                self::REMEDIATION_B,
                10004
            ),
            new Issue(
                self::TypeMismatchReturn,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Returning type %s but %s() is declared to return %s",
                self::REMEDIATION_B,
                10005
            ),
            new Issue(
                self::TypeMissingReturn,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Method %s is declared to return %s but has no return value",
                self::REMEDIATION_B,
                10006
            ),
            new Issue(
                self::TypeMismatchForeach,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "%s passed to foreach instead of array",
                self::REMEDIATION_B,
                10007
            ),
            new Issue(
                self::TypeArrayOperator,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Invalid array operator between types %s and %s",
                self::REMEDIATION_B,
                10008
            ),
            new Issue(
                self::TypeArraySuspicious,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Suspicious array access to %s",
                self::REMEDIATION_B,
                10009
            ),
            new Issue(
                self::TypeComparisonToArray,
                self::CATEGORY_TYPE,
                self::SEVERITY_LOW,
                "%s to array comparison",
                self::REMEDIATION_B,
                10010
            ),
            new Issue(
                self::TypeComparisonFromArray,
                self::CATEGORY_TYPE,
                self::SEVERITY_LOW,
                "array to %s comparison",
                self::REMEDIATION_B,
                10011
            ),
            new Issue(
                self::TypeConversionFromArray,
                self::CATEGORY_TYPE,
                self::SEVERITY_LOW,
                "array to %s conversion",
                self::REMEDIATION_B,
                10012
            ),
            new Issue(
                self::TypeInstantiateAbstract,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Instantiation of abstract class %s",
                self::REMEDIATION_B,
                10013
            ),
            new Issue(
                self::TypeInstantiateInterface,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Instantiation of interface %s",
                self::REMEDIATION_B,
                10014
            ),
            new Issue(
                self::TypeInvalidRightOperand,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Invalid operator: left operand is array and right is not",
                self::REMEDIATION_B,
                10015
            ),
            new Issue(
                self::TypeInvalidLeftOperand,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Invalid operator: right operand is array and left is not",
                self::REMEDIATION_B,
                10016
            ),
            new Issue(
                self::TypeParentConstructorCalled,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Must call parent::__construct() from %s which extends %s",
                self::REMEDIATION_B,
                10017
            ),
            new Issue(
                self::TypeNonVarPassByRef,
                self::CATEGORY_TYPE,
                self::SEVERITY_NORMAL,
                "Only variables can be passed by reference at argument %d of %s()",
                self::REMEDIATION_B,
                10018
            ),
            new Issue(
                self::NonClassMethodCall,
                self::CATEGORY_TYPE,
                self::SEVERITY_CRITICAL,
                "Call to method %s on non-class type %s",
                self::REMEDIATION_B,
                10019
            ),
            new Issue(
                self::TypeVoidAssignment,
                self::CATEGORY_TYPE,
                self::SEVERITY_LOW,
                "Cannot assign void return value",
                self::REMEDIATION_B,
                10000
            ),

            // Issue::CATEGORY_VARIABLE
            new Issue(
                self::VariableUseClause,
                self::CATEGORY_VARIABLE,
                self::SEVERITY_NORMAL,
                "Non-variables not allowed within use clause",
                self::REMEDIATION_B,
                12000
            ),

            // Issue::CATEGORY_STATIC
            new Issue(
                self::StaticCallToNonStatic,
                self::CATEGORY_STATIC,
                self::SEVERITY_NORMAL,
                "Static call to non-static method %s defined at %s:%d",
                self::REMEDIATION_B,
                9000
            ),

            // Issue::CATEGORY_CONTEXT
            new Issue(
                self::ContextNotObject,
                self::CATEGORY_CONTEXT,
                self::SEVERITY_CRITICAL,
                "Cannot access %s when not in object context",
                self::REMEDIATION_B,
                4000
            ),

            // Issue::CATEGORY_DEPRECATED
            new Issue(
                self::DeprecatedFunction,
                self::CATEGORY_DEPRECATED,
                self::SEVERITY_NORMAL,
                "Call to deprecated function %s() defined at %s:%d",
                self::REMEDIATION_B,
                5000
            ),
            new Issue(
                self::DeprecatedClass,
                self::CATEGORY_DEPRECATED,
                self::SEVERITY_NORMAL,
                "Call to deprecated class %s defined at %s:%d",
                self::REMEDIATION_B,
                5001
            ),
            new Issue(
                self::DeprecatedProperty,
                self::CATEGORY_DEPRECATED,
                self::SEVERITY_NORMAL,
                "Reference to deprecated property %s defined at %s:%d",
                self::REMEDIATION_B,
                5002
            ),

            // Issue::CATEGORY_PARAMETER
            new Issue(
                self::ParamReqAfterOpt,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_LOW,
                "Required argument follows optional",
                self::REMEDIATION_B,
                7000
            ),
            new Issue(
                self::ParamTooMany,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_LOW,
                "Call with %d arg(s) to %s() which only takes %d arg(s) defined at %s:%d",
                self::REMEDIATION_B,
                7001
            ),
            new Issue(
                self::ParamTooManyInternal,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_LOW,
                "Call with %d arg(s) to %s() which only takes %d arg(s)",
                self::REMEDIATION_B,
                7002
            ),
            new Issue(
                self::ParamTooFew,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Call with %d arg(s) to %s() which requires %d arg(s) defined at %s:%d",
                self::REMEDIATION_B,
                7003
            ),
            new Issue(
                self::ParamTooFewInternal,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Call with %d arg(s) to %s() which requires %d arg(s)",
                self::REMEDIATION_B,
                7004
            ),
            new Issue(
                self::ParamSpecial1,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s when argument %d is %s",
                self::REMEDIATION_B,
                7005
            ),
            new Issue(
                self::ParamSpecial2,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Argument %d (%s) is %s but %s() takes %s when passed only one argument",
                self::REMEDIATION_B,
                7006
            ),
            new Issue(
                self::ParamSpecial3,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "The last argument to %s must be of type %s",
                self::REMEDIATION_B,
                7007
            ),
            new Issue(
                self::ParamSpecial4,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "The second to last argument to %s must be of type %s",
                self::REMEDIATION_B,
                7008
            ),
            new Issue(
                self::ParamTypeMismatch,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Argument %d is %s but %s() takes %s",
                self::REMEDIATION_B,
                7009
            ),
            new Issue(
                self::ParamSignatureMismatch,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Declaration of %s should be compatible with %s defined in %s:%d",
                self::REMEDIATION_B,
                7010
            ),
            new Issue(
                self::ParamSignatureMismatchInternal,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Declaration of %s should be compatible with internal %s",
                self::REMEDIATION_B,
                7011
            ),
            new Issue(
                self::ParamRedefined,
                self::CATEGORY_PARAMETER,
                self::SEVERITY_NORMAL,
                "Redefinition of parameter %s",
                self::REMEDIATION_B,
                7012
            ),

            // Issue::CATEGORY_NOOP
            new Issue(
                self::NoopProperty,
                self::CATEGORY_NOOP,
                self::SEVERITY_LOW,
                "Unused property",
                self::REMEDIATION_B,
                6000
            ),
            new Issue(
                self::NoopArray,
                self::CATEGORY_NOOP,
                self::SEVERITY_LOW,
                "Unused array",
                self::REMEDIATION_B,
                6001
            ),
            new Issue(
                self::NoopConstant,
                self::CATEGORY_NOOP,
                self::SEVERITY_LOW,
                "Unused constant",
                self::REMEDIATION_B,
                6002
            ),
            new Issue(
                self::NoopClosure,
                self::CATEGORY_NOOP,
                self::SEVERITY_LOW,
                "Unused closure",
                self::REMEDIATION_B,
                6003
            ),
            new Issue(
                self::NoopVariable,
                self::CATEGORY_NOOP,
                self::SEVERITY_LOW,
                "Unused variable",
                self::REMEDIATION_B,
                6004
            ),
            new Issue(
                self::UnreferencedClass,
                self::CATEGORY_NOOP,
                self::SEVERITY_NORMAL,
                "Possibly zero references to class %s",
                self::REMEDIATION_B,
                6005
            ),
            new Issue(
                self::UnreferencedMethod,
                self::CATEGORY_NOOP,
                self::SEVERITY_NORMAL,
                "Possibly zero references to method %s",
                self::REMEDIATION_B,
                6006
            ),
            new Issue(
                self::UnreferencedProperty,
                self::CATEGORY_NOOP,
                self::SEVERITY_NORMAL,
                "Possibly zero references to property %s",
                self::REMEDIATION_B,
                6007
            ),
            new Issue(
                self::UnreferencedConstant,
                self::CATEGORY_NOOP,
                self::SEVERITY_NORMAL,
                "Possibly zero references to constant %s",
                self::REMEDIATION_B,
                6008
            ),

            // Issue::CATEGORY_REDEFINE
            new Issue(
                self::RedefineClass,
                self::CATEGORY_REDEFINE,
                self::SEVERITY_NORMAL,
                "%s defined at %s:%d was previously defined as %s at %s:%d",
                self::REMEDIATION_B,
                8000
            ),
            new Issue(
                self::RedefineClassInternal,
                self::CATEGORY_REDEFINE,
                self::SEVERITY_NORMAL,
                "%s defined at %s:%d was previously defined as %s internally",
                self::REMEDIATION_B,
                8001
            ),
            new Issue(
                self::RedefineFunction,
                self::CATEGORY_REDEFINE,
                self::SEVERITY_NORMAL,
                "Function %s defined at %s:%d was previously defined at %s:%d",
                self::REMEDIATION_B,
                8002
            ),
            new Issue(
                self::RedefineFunctionInternal,
                self::CATEGORY_REDEFINE,
                self::SEVERITY_NORMAL,
                "Function %s defined at %s:%d was previously defined internally",
                self::REMEDIATION_B,
                8003
            ),
            new Issue(
                self::IncompatibleCompositionProp,
                self::CATEGORY_REDEFINE,
                self::SEVERITY_NORMAL,
                "%s and %s define the same property (%s) in the composition of %s. However, the definition differs and is considered incompatible. Class was composed in %s on line %d",
                self::REMEDIATION_B,
                8004
            ),
            new Issue(
                self::IncompatibleCompositionMethod,
                self::CATEGORY_REDEFINE,
                self::SEVERITY_NORMAL,
                "Declaration of %s must be compatible with %s in %s on line %d",
                self::REMEDIATION_B,
                8005
            ),

            // Issue::CATEGORY_ACCESS
            new Issue(
                self::AccessPropertyProtected,
                self::CATEGORY_ACCESS,
                self::SEVERITY_CRITICAL,
                "Cannot access protected property %s",
                self::REMEDIATION_B,
                1000
            ),
            new Issue(
                self::AccessPropertyPrivate,
                self::CATEGORY_ACCESS,
                self::SEVERITY_CRITICAL,
                "Cannot access private property %s",
                self::REMEDIATION_B,
                1001
            ),
            new Issue(
                self::AccessMethodProtected,
                self::CATEGORY_ACCESS,
                self::SEVERITY_CRITICAL,
                "Cannot access protected method %s defined at %s:%d",
                self::REMEDIATION_B,
                1002
            ),
            new Issue(
                self::AccessMethodPrivate,
                self::CATEGORY_ACCESS,
                self::SEVERITY_CRITICAL,
                "Cannot access private method %s defined at %s:%d",
                self::REMEDIATION_B,
                1003
            ),
            new Issue(
                self::AccessSignatureMismatch,
                self::CATEGORY_ACCESS,
                self::SEVERITY_NORMAL,
                "Access level to %s must be compatible with %s defined in %s:%d",
                self::REMEDIATION_B,
                1004
            ),
            new Issue(
                self::AccessSignatureMismatchInternal,
                self::CATEGORY_ACCESS,
                self::SEVERITY_NORMAL,
                "Access level to %s must be compatible with internal %s",
                self::REMEDIATION_B,
                1005
            ),
            new Issue(
                self::AccessStaticToNonStatic,
                self::CATEGORY_ACCESS,
                self::SEVERITY_CRITICAL,
                "Cannot make static method %s() non static",
                self::REMEDIATION_B,
                1006
            ),
            new Issue(
                self::AccessNonStaticToStatic,
                self::CATEGORY_ACCESS,
                self::SEVERITY_CRITICAL,
                "Cannot make non static method %s() static",
                self::REMEDIATION_B,
                1007
            ),

            // Issue::CATEGORY_COMPATIBLE
            new Issue(
                self::CompatiblePHP7,
                self::CATEGORY_COMPATIBLE,
                self::SEVERITY_NORMAL,
                "Expression may not be PHP 7 compatible",
                self::REMEDIATION_B,
                3000
            ),
            new Issue(
                self::CompatibleExpressionPHP7,
                self::CATEGORY_COMPATIBLE,
                self::SEVERITY_NORMAL,
                "%s expression may not be PHP 7 compatible",
                self::REMEDIATION_B,
                3001
            ),

            // Issue::CATEGORY_GENERIC
            new Issue(
                self::TemplateTypeConstant,
                self::CATEGORY_GENERIC,
                self::SEVERITY_NORMAL,
                "constant %s may not have a template type",
                self::REMEDIATION_B,
                14000
            ),
            new Issue(
                self::TemplateTypeStaticMethod,
                self::CATEGORY_GENERIC,
                self::SEVERITY_NORMAL,
                "static method %s may not use template types",
                self::REMEDIATION_B,
                14001
            ),
            new Issue(
                self::TemplateTypeStaticProperty,
                self::CATEGORY_GENERIC,
                self::SEVERITY_NORMAL,
                "static property %s may not have a template type",
                self::REMEDIATION_B,
                14002
            ),
            new Issue(
                self::GenericGlobalVariable,
                self::CATEGORY_GENERIC,
                self::SEVERITY_NORMAL,
                "Global variable %s may not be assigned an instance of a generic class",
                self::REMEDIATION_B,
                14003
            ),
            new Issue(
                self::GenericConstructorTypes,
                self::CATEGORY_GENERIC,
                self::SEVERITY_NORMAL,
                "Missing template parameters %s on constructor for generic class %s",
                self::REMEDIATION_B,
                14004
            ),
        ];

        $error_map = [];
        foreach ($error_list as $i => $error) {
            $error_map[$error->getType()] = $error;
        }

        return $error_map;
    }

    /**
     * @return string
     */
    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @return int (Unique integer code corresponding to getType())
     */
    public function getTypeId() : int
    {
        return $this->type_id;
    }

    /**
     * @return int
     */
    public function getCategory() : int
    {
        return $this->category;
    }

    /**
     * @return string
     * The name of the category
     */
    public static function getNameForCategory(int $category) : string
    {
        return self::CATEGORY_NAME[$category] ?? '';
    }

    /**
     * @return int
     */
    public function getSeverity() : int
    {
        return $this->severity;
    }

    /**
     * @return string
     * A descriptive name of the severity of hte issue
     */
    public function getSeverityName() : string
    {
        switch ($this->getSeverity()) {
        case self::SEVERITY_LOW:
            return 'low';
        case self::SEVERITY_NORMAL:
            return 'normal';
        case self::SEVERITY_CRITICAL:
            return 'critical';
        }
    }

    /**
     * @return int
     */
    public function getRemediationDifficulty() : int
    {
        return $this->remediation_difficulty;
    }


    /**
     * @return string
     */
    public function getTemplate() : string
    {
        return $this->template;
    }

    /**
     * @return IssueInstance
     */
    public function __invoke(
        string $file,
        int $line,
        array $template_parameters = []
    ) : IssueInstance {
        return new IssueInstance(
            $this,
            $file,
            $line,
            $template_parameters
        );
    }

    /**
     * return Issue
     */
    public static function fromType(string $type) : Issue
    {
        $error_map = self::issueMap();

        assert(
            !empty($error_map[$type]),
            "Undefined error type $type"
        );

        return $error_map[$type];
    }

    /**
     * @param string $type
     * The type of the issue
     *
     * @param string $file
     * The name of the file where the issue was found
     *
     * @param int $line
     * The line number (start) where the issue was found
     *
     * @param mixed $template_parameters
     * Any template parameters required for the issue
     * message
     *
     * @return void
     */
    public static function emit(
        string $type,
        string $file,
        int $line,
        ...$template_parameters
    ) {
        self::emitWithParameters(
            $type,
            $file,
            $line,
            $template_parameters
        );
    }

    /**
     * @param string $type
     * The type of the issue
     *
     * @param string $file
     * The name of the file where the issue was found
     *
     * @param int $line
     * The line number (start) where the issue was found
     *
     * @param array $template_parameters
     * Any template parameters required for the issue
     * message
     *
     * @return void
     */
    public static function emitWithParameters(
        string $type,
        string $file,
        int $line,
        array $template_parameters
    ) {
        $issue = self::fromType($type);

        self::emitInstance(
            $issue($file, $line, $template_parameters)
        );
    }

    /**
     * @param IssueInstance $issue_instance
     * An issue instance to emit
     *
     * @return void
     */
    public static function emitInstance(
        IssueInstance $issue_instance
    ) {
        Phan::getIssueCollector()->collectIssue($issue_instance);
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context in which the instance was found
     *
     * @param IssueInstance $issue_instance
     * An issue instance to emit
     *
     * @return void
     */
    public static function maybeEmitInstance(
        CodeBase $code_base,
        Context $context,
        IssueInstance $issue_instance
    ) {
        // If this issue type has been suppressed in
        // the config, ignore it
        if (!Config::get()->disable_suppression
            && in_array($issue_instance->getIssue()->getType(),
                Config::get()->suppress_issue_types ?? [])
        ) {
            return;
        }

        // If a white-list of allowed issue types is defined,
        // only emit issues on the white-list
        if (!Config::get()->disable_suppression
            && count(Config::get()->whitelist_issue_types) > 0
            && !in_array($issue_instance->getIssue()->getType(),
                Config::get()->whitelist_issue_types ?? [])
        ) {
            return;
        }

        // If this issue type has been suppressed in
        // this scope from a doc block, ignore it.
        if (!Config::get()->disable_suppression
            && $context->hasSuppressIssue(
                $code_base,
                $issue_instance->getIssue()->getType()
        )) {
            return;
        }

        self::emitInstance($issue_instance);
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context in which the node we're going to be looking
     * at exits.
     *
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno
     * The line number where the issue was found
     *
     * @param mixed parameters
     * Template parameters for the issue's error message
     *
     * @return void
     */
    public static function maybeEmit(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        int $lineno,
        ...$parameters
    ) {
        self::maybeEmitWithParameters(
            $code_base, $context, $issue_type, $lineno, $parameters
        );
    }

    /**
     * @param CodeBase $code_base
     * The code base within which we're operating
     *
     * @param Context $context
     * The context in which the node we're going to be looking
     * at exits.
     *
     * @param string $issue_type
     * The type of issue to emit such as Issue::ParentlessClass
     *
     * @param int $lineno
     * The line number where the issue was found
     *
     * @param array parameters
     * Template parameters for the issue's error message
     *
     * @return void
     */
    public static function maybeEmitWithParameters(
        CodeBase $code_base,
        Context $context,
        string $issue_type,
        int $lineno,
        array $parameters
    ) {
        // If this issue type has been suppressed in
        // the config, ignore it
        if (!Config::get()->disable_suppression
            && in_array($issue_type,
            Config::get()->suppress_issue_types ?? [])
        ) {
            return;
        }
        // If a white-list of allowed issue types is defined,
        // only emit issues on the white-list
        if (!Config::get()->disable_suppression
            && count(Config::get()->whitelist_issue_types) > 0
            && !in_array($issue_type,
                Config::get()->whitelist_issue_types ?? [])
        ) {
            return;
        }

        if (!Config::get()->disable_suppression
            && $context->hasSuppressIssue($code_base, $issue_type)
        ) {
            return;
        }

        Issue::emitWithParameters(
            $issue_type,
            $context->getFile(),
            $lineno,
            $parameters
        );

    }
}
