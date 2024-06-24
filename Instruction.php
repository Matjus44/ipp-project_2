<?php

/**
 * File: Instruction.php
 * Author: Matúš Janek
 * Description: Class for storing instructions arguments.
 * Date: April 11, 2024
 */

namespace IPP\Student;

class Instruction {
    // Store instruction properties
    public int $order;
    public string $opcode;
    /** @var array<array{value: mixed, type: mixed}> */
    public array $args;

    public function __construct(int $order, string $opcode, mixed $args) {
        $this->order = $order;
        $this->opcode = $opcode;
        $this->args = $args;
    }
}