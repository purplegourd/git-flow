<?php declare(strict_types=1);
namespace Phan\Language\Scope;

use Phan\Language\FQSEN\FullyQualifiedFunctionName;
use Phan\Language\FQSEN\FullyQualifiedMethodName;

class FunctionLikeScope extends ClosedScope {

    /**
     * @return bool
     * True if we're in a function scope
     */
    public function isInFunctionLikeScope() : bool {
        return true;
    }

    /**
     * @return FullyQualifiedMethodName|FullyQualifiedFunctionName
     * Get the FQSEN for the closure, method or function we're in
     */
    public function getFunctionLikeFQSEN()
    {
        $fqsen = $this->getFQSEN();

        if ($fqsen instanceof FullyQualifiedMethodName) {
            return $fqsen;
        }

        if ($fqsen instanceof FullyQualifiedFunctionName) {
            return $fqsen;
        }

        assert(false, "FQSEN must be a function-like FQSEN");
    }

}
