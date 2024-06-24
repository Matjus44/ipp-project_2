<?php

/**
 * File: Data_stack.php
 * Author: Matúš Janek
 * Description: Class for stack which will be used for storing data.
 * Date: April 11, 2024
 */

namespace IPP\Student;

class Data_stack
{
    /** @var array<array{value: mixed, type: mixed}> */
    private array $elements;

    public function __construct()
    {
        $this->elements = [];
    }

    // Add element with value and type
    public function push(mixed $value, mixed $type): void
    {
        $this->elements[] = ['value' => $value, 'type' => $type];
    }

    // Delete and return top element's value
    public function pop(): mixed
    {
        if ($this->isEmpty()) {
            exit(56);
        }
        $topElement = array_pop($this->elements);
        return $topElement;
    }

    // Check if stack is empty
    public function isEmpty(): bool
    {
        return empty($this->elements);
    }

    // Returns top element's value only
    public function top(): mixed
    {
        if ($this->isEmpty()) {
            exit(56);
        }
        $topElement = end($this->elements);
        return $topElement['value'];
    }

    // Returns top element's type only
    public function topType(): mixed
    {
        if ($this->isEmpty()) {
            exit(56);
        }
        $topElement = end($this->elements);
        return $topElement['type'];
    }

    // Count of elements in stack
    public function size(): int
    {
        return count($this->elements);
    }

    // Clear all elements from the stack
    public function clear(): void
    {
        $this->elements = [];
    }
}