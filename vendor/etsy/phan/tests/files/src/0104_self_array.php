<?php
class A {
    /** @return self[] */
    function f() {
        return [self, self];
    }

    function g() {
        foreach ($this->f() as $c) {
            $c->g();
        }
    }
}
