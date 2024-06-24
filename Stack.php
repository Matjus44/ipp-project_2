<?php

/**
 * File: Stack.php
 * Author: Matúš Janek
 * Description: Class for stack of temporary frames, used for local frame.
 * Date: April 11, 2024
 */

namespace IPP\Student;

class Stack
{
    /** @var array<mixed> */
    private array $elements;

    public function __construct()
    {
        $this->elements = [];
    }

    // Add element
    public function push(mixed $element): void
    {
        $this->elements[] = $element;
    }

    // Delete and return top element
    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            exit(56);
        }
        return array_pop($this->elements);
    }

    // Check if stack is empty
    public function isEmpty(): bool
    {
        return empty($this->elements);
    }

    // Returns top element only
    public function top(): mixed
    {
        if ($this->isEmpty()) {
            exit(56);
        }
        return end($this->elements);
    }

    // Count of elements in stack
    public function size(): int
    {
        return count($this->elements);
    }
}