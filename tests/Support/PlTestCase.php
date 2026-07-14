<?php

abstract class PlTestCase extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        FakeDb::reset();
        $_SESSION = ['TourId' => 1];
    }
}
