<?php declare(strict_types=1);
namespace Phan\Analysis;

use Phan\CodeBase;
use Phan\Exception\IssueException;
use Phan\Issue;
use Phan\Language\Element\Clazz;
use Phan\Language\FQSEN;
use Phan\Language\Type\TemplateType;

class PropertyTypesAnalyzer
{

    /**
     * Check to see if the given Clazz is a duplicate
     *
     * @return null
     */
    public static function analyzePropertyTypes(CodeBase $code_base, Clazz $clazz)
    {
        foreach ($clazz->getPropertyList($code_base) as $property) {
            try {
                $union_type = $property->getUnionType();
            } catch (IssueException $exception) {
                Issue::maybeEmitInstance(
                    $code_base,
                    $property->getContext(),
                    $exception->getIssueInstance()
                );
                continue;
            }

            // Look at each type in the parameter's Union Type
            foreach ($union_type->getTypeSet() as $type) {

                // If its a native type or a reference to
                // self, its OK
                if ($type->isNativeType() || $type->isSelfType()) {
                    continue;
                }

                if ($type instanceof TemplateType) {
                    if ($property->isStatic()) {
                        Issue::maybeEmit(
                            $code_base,
                            $property->getContext(),
                            Issue::TemplateTypeStaticProperty,
                            $property->getFileRef()->getLineNumberStart(),
                            (string)$property->getFQSEN()
                        );
                    }
                } else {

                    // Make sure the class exists
                    $type_fqsen = $type->asFQSEN();

                    if (!$code_base->hasClassWithFQSEN($type_fqsen)
                        && !($type instanceof TemplateType)
                        && (
                            !$property->hasDefiningFQSEN()
                            || $property->getDefiningFQSEN() == $property->getFQSEN()
                        )
                    ) {
                        Issue::maybeEmit(
                            $code_base,
                            $property->getContext(),
                            Issue::UndeclaredTypeProperty,
                            $property->getFileRef()->getLineNumberStart(),
                            (string)$property->getFQSEN(),
                            (string)$type_fqsen
                        );
                    }
                }
            }
        }
    }
}
