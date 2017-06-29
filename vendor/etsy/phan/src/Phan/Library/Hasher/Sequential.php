<?php declare(strict_types=1);
namespace Phan\Library\Hasher;

use Phan\Library\Hasher;

/**
 * Hasher implementation mapping keys to sequential groups (first key to 0, second key to 1, looping back to 0)
 * getGroup() is called exactly once on each string to be hashed.
 */
class Sequential implements Hasher {
    /** @var int */
    protected $_i;
    /** @var int */
    protected $_groupCount;

    public function __construct(int $groupCount) {
        $this->_i = 1;
        $this->_groupCount = $groupCount;
    }

    /**
     * @return int - an integer between 0 and $this->_groupCount - 1, inclusive
     */
    public function getGroup(string $key) : int {
        return ($this->_i++) % $this->_groupCount;
    }

    /**
     * Resets counter
     * @return void
     */
    public function reset() {
        $this->_i = 1;
    }
}
