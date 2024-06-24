<?php

/**
 * File: Frame.php
 * Author: Matúš Janek
 * Description: Class for frame which is used to store elements into frame and methods for operations with frames.
 * Date: April 11, 2024
 */

namespace IPP\Student;

class Frame {

    /** @var array<array{name: string, value: mixed, type: mixed}> */
    private array $array;

    // Create Frame
    public function __construct() {
        $this->array = [];
    }

    // Add element into frame
    public function addElement(string $name, mixed $value, mixed $type): void {
        $this->array[] = ['name' => $name, 'value' => $value, 'type' => $type];
    }   

    // Find element in frame.
    public function findElement(string $name): int {
        foreach ($this->array as $index => $element) {
            if ($element['name'] === $name) {
                return $index;
            }
        }
        return -1;
    }

    // Remove element from frame
    public function removeElement(string $name): void {
        $index = $this->findElement($name);
        if ($index !== -1) {
            unset($this->array[$index]);
            $this->array = array_values($this->array);
        }
    }

    // Initialize element in frame
    public function initialize(string $name, mixed $newValue, mixed $newType): void {
        $index = $this->findElement($name);
        if ($index !== -1) {
            $this->array[$index]['value'] = $newValue;
            $this->array[$index]['type'] = $newType;
        }
    }
    
    /**
     * @return array<array{name: string, value: mixed, type: mixed}>
     */
    public function getAllElements(): array {
        return $this->array;
    }

    // Get size of frame.
    public function getSize(): int {
        return count($this->array);
    }   

    // Get value of an element in frame by its name
    public function getValueByName(string $name): mixed {
        foreach ($this->array as $element) {
            if ($element['name'] === $name) {
                if ($element['value'] === null) {
                    exit(56);
                }
                return ['value' => $element['value'], 'type' => $element['type']];
            }
        }
        exit(56);
    }

    // Update value of an element by his name
    public function updateValueByName(string $name, mixed $newValue, mixed $newType = null): void {
        foreach ($this->array as &$element) {
            if ($element['name'] === $name) {
                $element['value'] = $newValue;
                if ($newType !== null) {
                    $element['type'] = $newType;
                }
                return;
            }
        }
    }

    // Get type of an element by his name
    public function getTypeByName(string $name): mixed {
        foreach ($this->array as $element) {
            if ($element['name'] === $name) {
                return $element['type'];
            }
        }
        // If the element with the given name is not found, exit with error code 56
        exit(56);
    }
}