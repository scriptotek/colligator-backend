<?php

namespace Tests;

class ExampleTest extends BrowserKitTestCase
{
    /**
     * A basic functional test example.
     */
    public function testBasicExample()
    {
        $this->visit('/')
             ->see('colligator');
    }
}
