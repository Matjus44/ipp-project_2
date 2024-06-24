<?php

/**
 * File: Interpret.php
 * Author: Matúš Janek
 * Description: Main file for parsing instruction and evaluating the input code.
 * Date: April 11, 2024
 */

namespace IPP\Student;

use IPP\Core\AbstractInterpreter;
use IPP\Core\Exception\NotImplementedException;

class Interpreter extends AbstractInterpreter
{   
    // Declaration of frames and arrays

    /** 
     * @var array<mixed>
     */
    private array $instructions = []; // Array of instructions

    private ?Frame $temporary_frame = null;

    private ?Frame $global_frame;

    private ?Stack $local_frame = null;

    private Data_stack $data_stack;

    /** @var array<array{name: mixed, order: mixed}> */
    private array $labels_array = [];

    private ?Data_stack $call_stack = null;

    // Main function
    public function execute(): int
    {

        $dom = $this->source->getDOMDocument();

        $this->parseInstructions($dom);

        $this->createFrame();

        $this->parse_opcode();

        throw new NotImplementedException;
    }

    // Creates all necessary frames
    private function createFrame() : void
    {
        $this->global_frame = new Frame();
        // $this->local_frame = new Stack();
        $this->data_stack = new Data_stack();
        $this->call_stack = new Data_stack();
    }

    // Add label  into his array
    private function addLabelToLabelsArray(mixed $labelName,mixed $order) : void
    {
        # Exists ?
        foreach ($this->labels_array as $label) {
            if ($label['name'] === $labelName) {
                # Yes => error.
                exit(52);
            }
        }

        # Add into array.
        $this->labels_array[] = [
            'name' => $labelName,
            'order' => $order,
        ];
    }

    // Function for cleaning instruction
    private function getCleanNodeValue(mixed $node) : mixed {
        $nodeValue = $node->nodeValue;
        // Delete space
        $cleanValue = trim($nodeValue);
        return $cleanValue;
    }

    // Convert escape sequences into character
    private function convertEscapeSequences(mixed $input) : mixed
    {
        $output = preg_replace_callback(
            '/\\\\([0-9]{3})/', 
            function($matches) {
                $decimalCode = intval($matches[1]);
                return chr($decimalCode);
            },
            $input
        );

        return $output;
    }

    // Updating labels order
    private function updateLabelsOrder() : void {
        foreach ($this->labels_array as &$label) {
            $label['order'] = array_search($label['order'], array_column($this->instructions, 'order')) + 1;
        }
    }   

    // Check if the argument is valid
    private function check_arg_count(mixed $arg_count,mixed $opcode) : void
    {
        $argument_1 = ['DEFVAR', 'CALL', 'PUSHS', 'POPS', 'WRITE', 'LABEL', 'JUMP', 'EXIT', 'DPRINT','JUMPIFEQS','JUMPIFNEQS'];
        $argument_0 = ['CREATEFRAME', 'PUSHFRAME', 'POPFRAME', 'RETURN', 'BREAK','CLEARS','ADDS','SUBS','MULS','IDIVS','LTS','GTS','EQS','ANDS','ORS','NOTS','INT2CHARS','STRI2INTS'];
        $argument_2 = ['MOVE', 'INT2CHAR', 'READ', 'STRLEN', 'TYPE', 'NOT'];
        $argument_3 = ['ADD', 'SUB', 'MUL', 'IDIV', 'LT', 'GT', 'EQ', 'AND', 'OR', 'STRI2INT', 'CONCAT', 'GETCHAR', 'SETCHAR', 'JUMPIFEQ', 'JUMPIFNEQ'];

        // Check if the opcode is present in the respective argument arrays
        if ($arg_count == 1 && !in_array(strtoupper($opcode), $argument_1)) {
            exit(32);
        }
        if ($arg_count == 0 && !in_array(strtoupper($opcode), $argument_0)) {
            exit(32);
        }
        if ($arg_count == 2 && !in_array(strtoupper($opcode), $argument_2)) {
            exit(32);
        }
        if ($arg_count == 3 && !in_array(strtoupper($opcode), $argument_3)) {
            exit(32);
        }
    }

    // Load xml file, parse his instruction and store them in array of Instruction objects
    private function parseInstructions(mixed $dom) : void
    { 
        $arg_count = 0;

        $instructionElements = $dom->getElementsByTagName('instruction');
        
        // Array to store instructions indexed by their order
        $indexedInstructions = [];
        
        // Variable to store new order values
        $newOrder = 1;

        // Loop trough xml file
        foreach ($instructionElements as $instructionElement) 
        {
            $order = $instructionElement->getAttribute('order');

            // Check for duplicate order
            if (isset($indexedInstructions[$order])) {
                exit(32); // Duplicate order error
            }

            if (!ctype_digit($order) || $order <= 0) {
                exit(32); // Invalid order attribute
            }

            $opcode = strtoupper($instructionElement->getAttribute('opcode'));
            $args = [];

            // Get instruction arguments
            $arg1Element = $instructionElement->getElementsByTagName("arg1")->item(0);
            $arg2Element = $instructionElement->getElementsByTagName("arg2")->item(0);
            $arg3Element = $instructionElement->getElementsByTagName("arg3")->item(0);

            // Check argument order
            if ($arg2Element !== null && $arg1Element === null) {
                exit(32); // Missing arg1 error
            }

            if ($arg3Element !== null && ($arg1Element === null || $arg2Element === null)) {
                exit(32); // Missing arg1 or arg2 error
            }

            // Check for validation of arguments
            $otherArgs = $instructionElement->getElementsByTagName("*");
            $hasInvalidArg = false;
            foreach ($otherArgs as $arg) {
                $argName = $arg->tagName;
                if ($argName !== "arg1" && $argName !== "arg2" && $argName !== "arg3") {
                    $hasInvalidArg = true;
                    break;
                }
            }

            if ($hasInvalidArg) {
                exit(32); // Invalid argument format error
            }

            // Loop trough arguments
            for ($i = 1; $i <= 3; $i++) 
            {
                 // Check if the instruction element has both opcode and order attributes
                if (!$instructionElement->hasAttribute('opcode') || !$instructionElement->hasAttribute('order')) {
                    exit(32); // Missing opcode or order attribute
                }

                $argElement = $instructionElement->getElementsByTagName("arg$i")->item(0);
                if ($argElement !== null) 
                {
                    $type = $argElement->getAttribute('type');
                    $value = $this->getCleanNodeValue($argElement); // Ignore whitespace
                    $arg_count = $arg_count + 1;
                    if ($type === 'string') {
                        $value = $this->convertEscapeSequences($value);
                    }

                    if ($type === 'int' && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
                        exit(32); // Invalid operand value
                    }

                    // Store instruction value and type
                    $args[] = [
                        'type' => $type,
                        'value' => $value,
                    ];

                    if ($opcode === 'LABEL') {
                        // Ignore whitespace in label name
                        $labelName = $this->getCleanNodeValue($argElement);
                        $this->addLabelToLabelsArray($labelName, $newOrder); // Update label order
                    }
                }
            }
            // Store instruction in array indexed by order
            $this->check_arg_count($arg_count,$opcode);
            $arg_count = 0;
            $indexedInstructions[$order] = new Instruction($newOrder, $opcode, $args);
            $newOrder++; // Increment new order value
        }

        // Sort instructions by order in ascending order
        ksort($indexedInstructions);

        // Convert indexed array to sequential array
        $this->instructions = array_values($indexedInstructions);

        $this->updateLabelsOrder();
    }

    // Get labels order
    private function findLabelOrder(mixed $labelName) : mixed
    {
        foreach ($this->labels_array as $label) {
            if ($label['name'] === $labelName) {
                return $label['order'];
            }
        }
        return null; // Label not found
    }

    // Switch case for all instructions
    private function parse_opcode() : mixed
    {
        $numInstructions = count($this->instructions);
        $currentIndex = 0;
        $num_of_instrictions = 0;

        // While there is instruction left, then we continue
        while ($currentIndex < $numInstructions) {
            $instruction = $this->instructions[$currentIndex];

            // Case for each instruction
            switch ($instruction->opcode) {
                case 'CREATEFRAME':
                    $this->create_temporary_frame();
                    break;
                
                case 'DEFVAR':
                    $this->create_var($instruction->args);
                    break;

                case 'PUSHFRAME':
                    $this->push_frame();
                    break;
                
                case 'POPFRAME':
                    $this->pop_frame();
                    break;
                
                case 'MOVE':
                    $this->move($instruction);
                    break;

                case 'PUSHS':
                    $this->push($instruction);
                    break;

                case 'POPS':
                    $this->pop($instruction);
                    break;

                case 'ADD':
                case 'SUB':
                case 'MUL':
                case 'IDIV':
                case 'LT':
                case 'GT':
                case 'EQ':
                case 'AND':
                case 'OR':
                case 'ADDS':
                case 'SUBS':
                case 'MULS':
                case 'IDIVS':
                case 'LTS':
                case 'GTS':
                case 'EQS':
                case 'ANDS':
                case 'ORS':
                    $this->add($instruction, $instruction->opcode);
                    break;
                case 'NOTS':
                case 'NOT':
                    $this->not($instruction, $instruction->opcode);
                    break;

                case 'INT2CHAR':
                case 'INT2CHARS':
                    $this->int2char($instruction, $instruction->opcode);
                    break;

                case 'STRI2INT':
                case 'STRI2INTS':
                    $this->stri2int($instruction, $instruction->opcode);
                    break;

                case 'READ':
                    $this->read($instruction);
                    break;

                case 'WRITE':
                    $this->write($instruction);
                    break;
                
                case 'SETCHAR':
                case 'GETCHAR':
                case 'STRLEN':
                case 'CONCAT':
                    $this->concat($instruction, $instruction->opcode);
                    break;

                case 'LABEL':
                    // No action needed for labels during execution
                    break;
                
                case 'JUMPIFEQ':
                case 'JUMPIFEQS':
                case 'JUMPIFNEQ':
                case 'JUMPIFNEQS':
                case 'JUMP':
                    $store = $this->jumps($instruction,$instruction->opcode);
                    if($store != -1)
                    {
                        $currentIndex = $store;
                    }
                    break;

                case 'EXIT':
                    $this->exit_function($instruction);
                    break;

                case 'TYPE';
                    $this->type_function($instruction);
                    break;

                case 'DPRINT':
                    $this->dprint($instruction);
                    break;

                case 'BREAK':
                    $this->break_function($num_of_instrictions,$currentIndex);
                    break;

                case 'CALL':
                    $jumping_to = $this->call_function($instruction);
                    $currentIndex = $jumping_to;
                    break;

                case 'RETURN':
                    $where = $this->call_stack->pop();
                    $currentIndex = intval($where['value']);
                    break;

                case 'CLEARS':
                    $this->data_stack->clear();
                    break;
                    
                default:
                    $this->stderr->writeString("Unknown opcode: {$instruction->opcode}\n");
                    exit(32);
            }
            // Update number of instruction
            $num_of_instrictions++;
            $currentIndex++; // Move to the next instruction
        }

        exit(0);
    }

    // Find element in frame using objects method findElement
    private function find_element(mixed $frame,mixed $name) : mixed
    {
        switch($frame)
        {
            case 'GF':
                return $this->global_frame->findElement($name);
            case 'LF':
                if($this->local_frame == null)
                {
                    exit(55);
                }
                $storing = $this->local_frame->top();
                return $storing->findElement($name);
            case 'TF':
                if($this->temporary_frame == null)
                {
                    exit(55);
                }
                return $this->temporary_frame->findElement($name);
            default:
                $this->stderr->writeString("Invalid frame type: $frame\n");
                exit(55); 
        }
    }

    // Create variable
    private function create_var(mixed $var_info) : void
    {
        $varInfo = explode('@', $var_info[0]['value'], 2);
        
        $frameType = $varInfo[0]; 
        $varName = $varInfo[1]; 

        // Switch trough frames and search for element, if element exists then we return error, otherwise we add element into frame
        switch ($frameType)
        {
            case 'GF':
                if ($this->find_element($frameType,$varName) !== -1) 
                {
                    $this->stderr->writeString("variable found in Global Frame.\n");
                    exit(52);
                }
                $this->global_frame->addElement($varName,null,'');
                break;

            case 'LF':
                if($this->local_frame == null)
                {
                    exit(55);
                }
                if ($this->find_element($frameType,$varName) !== -1) 
                {
                    $this->stderr->writeString("variable found in temporary Frame.\n");
                    exit(52);
                }
                $store_top_array = $this->local_frame->pop();
                $store_top_array->addElement($varName,null,'');
                $this->local_frame->push($store_top_array);
                break;

            case "TF":
                if($this->temporary_frame == null)
                {
                    exit(55);
                }
                if ($this->find_element($frameType,$varName) !== -1) 
                {
                    $this->stderr->writeString("variable found in temporary Frame.\n");
                    exit(52);
                }
                $this->temporary_frame->addElement($varName,null,'');
                break;
        }
    }

    // Create temporary frame
    private function create_temporary_frame() : void
    {
        $this->temporary_frame = new Frame();
    }

    // Push temporary frame into local frame, if local frame is not initialized then we create it
    private function push_frame() : void
    {
        if ($this->temporary_frame === null) {
            $this->stderr->writeString("Temporary frame is undefined.\n");
            exit(55); // Error code for accessing an undefined frame
        }
        if($this->local_frame == null)
        {
            $this->local_frame = new Stack();
        }
        $this->local_frame->push($this->temporary_frame);
        $this->temporary_frame = null;
    }

    // Remove frame local frame
    private function pop_frame() : void
    {
        if( $this->local_frame == null) 
        {
            exit(55); // Error code for accessing an empty frame stack
        }

        $this->temporary_frame = $this->local_frame->pop();

        if (empty($this->local_frame) )
        {
            $this->local_frame = null;
        }
    }

    // Insert element into frame according to his frame type
    private function insertIntoFrame(mixed $frame,mixed $destination_name,mixed $source_value,mixed $source_type) : void
    {
        switch ($frame) {
            case $this->global_frame:
                $frame->initialize($destination_name, $source_value, $source_type);
                break;
            case $this->local_frame:
                $store_array = $frame->pop();
                $store_array->initialize($destination_name, $source_value, $source_type);
                $frame->push($store_array);
                break;
            case $this->temporary_frame:
                $frame->initialize($destination_name, $source_value, $source_type);
                break;
            default:
                exit(55); // Error code for an invalid frame
        }
    }

    // Switch case for possible frames
    private function switch_for_frames(mixed $destination_frame,mixed $destination_name,mixed $source_value, mixed $source_type) : void
    {
        // Insert the source value into the destination frame.
        switch ($destination_frame) {
            case 'GF':
                $this->insertIntoFrame($this->global_frame, $destination_name, $source_value, $source_type);
                break;
            case 'LF':
                $this->insertIntoFrame($this->local_frame, $destination_name, $source_value, $source_type);
                break;
            case 'TF':
                $this->insertIntoFrame($this->temporary_frame, $destination_name, $source_value, $source_type);
                break;
            default:
                exit(55); // Error code for an invalid frame type
        } 
    }

    // Parse move instruction, extracting his type value and frame. Then calling switch_for_frames and initialize to add element into frame
    private function move(mixed $instruction) : void
    {     
        # Get each argument.
        $destination = $instruction->args[0];
        $source = $instruction->args[1];
    
        $destination_parts = explode('@', $destination['value']);
        $destination_frame = $destination_parts[0];
        $destination_name = $destination_parts[1];

        if($this->find_element($destination_frame,$destination_name) == -1)
        {
            exit(54);
        }

        // Get the source type and value.
        $source_type = $source['type'];
        $source_value = $source['value'];
    
        // If the source argument is a variable, retrieve its value and frame.
        if ($source_type === 'var') {
            $source_parts = explode('@', $source_value);
            $source_frame = $source_parts[0];
            $source_name = $source_parts[1];
    
            if($this->find_element($source_frame,$source_name) == -1)
            {
                exit(54);
            }
            
            switch($source_frame){
                case 'GF':
                    $source_array = $this->global_frame->getValueByName($source_name);
                    $source_value = $source_array['value'];
                    $source_type = $source_array['type'];
                    break;
                case 'LF':
                    $source_bla = $this->local_frame->pop();
                    $source_array = $source_bla->getValueByName($source_name);
                    $source_value = $source_array['value'];
                    $source_type = $source_array['type'];
                    break;
                case 'TF':
                    $source_array = $this->temporary_frame->getValueByName($source_name);
                    $source_value = $source_array['value'];
                    $source_type = $source_array['type'];
                    break;
            }
        }

        $this->switch_for_frames($destination_frame, $destination_name, $source_value, $source_type);
    }

    // Push element into data_stack
    private function push(mixed $instruction) : void
    {
        $type = $instruction->args[0]['type'];
        $value = $instruction->args[0]['value'];

        // If element is var, then we push only his value into stack
        if ($type === 'var') {
            
            $varInfo = explode('@', $value, 2);
            $frameType = $varInfo[0];
            $varName = $varInfo[1];
    
            if($this->find_element($frameType,$varName) == -1)
            {
                exit(54);
            }

            switch ($frameType) {
                case 'GF':
                    $var_array = $this->global_frame->getValueByName($varName);
                    $varValue = $var_array['value'];
                    $varType = $var_array['type'];
                    break;
                case 'LF':
                    $top_array = $this->local_frame->top();
                    $var_array = $top_array->getValueByName($varName);
                    $varValue = $var_array['value'];
                    $varType = $var_array['type'];
                    break;
                case 'TF':
                    $var_array = $this->temporary_frame->getValueByName($varName);
                    $varValue = $var_array['value'];
                    $varType = $var_array['type'];
                    break;
                default:
                    exit(55); 
            }
    
            $this->data_stack->push($varValue,$varType);
        } 
        // Element is not variable, pushing his value into stack
        else 
        {
            $this->data_stack->push($value,$type);
        }
    }

    // Parse pop instruction
    private function pop(mixed $instruction) : void
    {
        // Extract arguments properties
        $name = $instruction->args[0]['value'];
        $type = $instruction->args[0]['type'];

        $store_element_array = $this->data_stack->pop();
        $element_type = $store_element_array['type'];
        $element_value = $store_element_array['value'];

        // If storing element is variable then we remove first value from stack and store it into variable, otherwise exit with an error.
        if ($type === 'var') 
        {       
            $varInfo = explode('@', $name, 2);
            $frameType = $varInfo[0];
            $varName = $varInfo[1];

            if($this->find_element($frameType,$varName) == -1)
            {
                exit(54);
            }
    
            switch ($frameType) 
            {
                case 'GF':
                    $this->global_frame->updateValueByName($varName, $element_value,$element_type);
                    break;
                case 'LF':
                    
                    $store_array = $this->local_frame->pop();
                    $store_array->updateValueByName($varName, $element_value,$element_type);
                    $this->local_frame->push($store_array);
                    break;
                case 'TF':
                    $this->temporary_frame->updateValueByName($varName, $element_value,$element_type);
                    break;
                default:
                    exit(55); 
            }
        } 
        else 
        {
            exit(55);
        }  
    }

    // Get value of an element that is corresponding to his name
    private function get_value(mixed $operand) : mixed
    {
        $varInfo = explode('@', $operand['value'], 2);
        $frameType = $varInfo[0];
        $varName = $varInfo[1];

        if($this->find_element($frameType,$varName) == -1)
        {
            exit(54);
        }
        // Check all frames
        switch ($frameType) 
        {
            case 'GF':
                $store_value = $this->global_frame->getValueByName($varName);
                break;
            case 'LF':
                $store_array = $this->local_frame->top();
                $store_value = $store_array->getValueByName($varName);
                break;
            case 'TF':
                $store_value = $this->temporary_frame->getValueByName($varName);
                break;
            default:
                exit(55); 
        }

        return $store_value;
    }

    // Get type of an element
    private function get_type(mixed $operand) : mixed
    {
        $varInfo = explode('@', $operand['value'], 2);
        $frameType = $varInfo[0];
        $varName = $varInfo[1];

        if($this->find_element($frameType,$varName) == -1)
        {
            exit(54);
        }

        // Check all frames and use instance method getTypeByName to get type.
        switch ($frameType) 
        {
            case 'GF':
                $store_value = $this->global_frame->getTypeByName($varName);
                break;
            case 'LF':
                $store_array = $this->local_frame->top();
                $store_value = $store_array->getTypeByName($varName);
                break;
            case 'TF':
                $store_value = $this->temporary_frame->getTypeByName($varName);
                break;
            default:
                exit(55); 
        }

        return $store_value;
    }
    
    // Parse ADD, MUL, SUB IDIV, LT, GT, EQ, AND OR
    private function add(mixed $instruction,mixed $operand) : void
    {
        // Get arguments properties
        $operand_1 = '';
        $operand_2 = '';
        $destination = '';
        $destination_parts = '';
        $destination_frame = '';
        $destination_name = '';
        // Initialize for temporary storing
        $store_type_1 = '';
        $store_type_2 = '';
        $value_1 = '';
        $value_2 = '';
        $result = 0;

        if($operand === 'ADD' || $operand === 'MUL' || $operand === 'SUB' || $operand === 'IDIV' || $operand === 'LT' || $operand === 'GT' ||
        $operand === 'EQ' || $operand === 'AND' || $operand === 'OR')
        {
            $destination = $instruction->args[0];
            $destination_parts = explode('@', $destination['value']);
            $destination_frame = $destination_parts[0];
            $destination_name = $destination_parts[1];
        
            if($this->find_element($destination_frame,$destination_name) == -1)
            {
                exit(54);
            }

            $operand_1 = $instruction->args[1];
            $operand_2 = $instruction->args[2];
        }
        else
        {
            $operand_2 = $this->data_stack->pop();
            $operand_1 = $this->data_stack->pop();
        }
        
        // If argument is var then extract his value
        if ($operand_1['type'] == 'var') 
        {
            $store_array_1 = $this->get_value($operand_1);
            $value_1 = $store_array_1['value'];
            $store_type_1 = $store_array_1['type'];
        }
        else 
        {
            $value_1 = $operand_1['value'];
            $store_type_1 = $operand_1['type'];
        }
    
        if ($operand_2['type'] == 'var') 
        {
            $store_array_2 = $this->get_value($operand_2);
            $value_2 = $store_array_2['value'];
            $store_type_2 = $store_array_2['type'];
        } 
        else 
        {
            $value_2 = $operand_2['value'];
            $store_type_2 = $operand_2['type'];
        }

        // Check for validation of type
        $this->is_valid_type($store_type_1);
        $this->is_valid_type($store_type_2);
        
        // Process ADD, SUB, MUL, IDIV, AND, OR, LT ,GT ,EQ and also stack instructions of same type
        if($operand === 'ADD' || $operand === 'SUB' || $operand === 'MUL' || $operand === 'IDIV' || $operand === 'ADDS' || $operand === 'SUBS' || $operand === 'MULS' || $operand === 'IDIVS')
        {
            // Type can be only int
            if($store_type_1 === 'int' && $store_type_2 === 'int')
            {
                switch ($operand) 
                {
                    case 'ADDS':
                    case 'ADD':
                        $result = $value_1 + $value_2;
                        break;
                    case 'SUBS':
                    case 'SUB':
                        $result = $value_1 - $value_2;
                        break;
                    case 'MULS':
                    case 'MUL':
                        $result = $value_1 * $value_2;
                        break;
                    case 'IDIV':
                    case 'IDIVS':
                        if ($value_2 == 0) {
                            exit(57);
                        }
                        $result = $value_1 / $value_2;
                        break;
                    default:
                        exit(53);
                }
                // Store updated value into variable
                if($operand === 'ADD' || $operand === 'SUB' || $operand === 'IDIV' || $operand === 'MUL')
                {
                    $this->switch_for_frames($destination_frame, $destination_name, $result, 'int');
                }
                else
                {
                    $this->data_stack->push($result,'int');
                }
            }
            else
            {
                exit(53);
            }
        }
        // Process LT and GT
        if ($operand === 'LT' || $operand === 'GT' || $operand === 'LTS' || $operand === 'GTS') 
        {  
            // Arguments types have to be same and different from nil
            if ($store_type_1 === $store_type_2 && $store_type_1 !== 'nil' && $store_type_2 !== 'nil') 
            {
                switch ($operand) 
                {
                    case 'LT':
                    case 'LTS':
                        $result = $value_1 < $value_2;
                        break;
                    case 'GT':
                    case 'GTS':
                        $result = $value_1 > $value_2;
                        break;
                    default:
                        exit(53);
                }
                // Store result into variable
                if($operand === 'LT' || $operand === 'GT' )
                {
                    if($result === false)
                    {
                        $this->switch_for_frames($destination_frame, $destination_name, 'false', 'bool');
                    }
                    else
                    {
                        $this->switch_for_frames($destination_frame, $destination_name, 'true', 'bool');
                    }
                }
                else
                {
                    if($result === false)
                    {
                        $this->data_stack->push('false','bool');
                    }
                    else
                    {
                        $this->data_stack->push('true','bool');
                    }
                }
            } 
            else 
            {
                exit(53);
            }
        }
        // Process EQ
        if ($operand === 'EQ' || $operand === 'EQS') 
        {   
            // Theirs type has to be same or one of theirs types is nil
            if ($store_type_1 === $store_type_2 || ($store_type_1 === 'nil' && $store_type_2 === 'nil') || $store_type_1 === 'nil' || $store_type_2 === 'nil') 
            {
                $result = $value_1 === $value_2;
                if($operand === 'EQ')
                {
                    if($result === false)
                    {
                        $this->switch_for_frames($destination_frame, $destination_name, 'false', 'bool');
                    }
                    else
                    {
                        $this->switch_for_frames($destination_frame, $destination_name, 'true', 'bool');
                    }
                }
                else
                {
                    if($result === false)
                    {
                        $this->data_stack->push('false','bool');
                    }
                    else
                    {
                        $this->data_stack->push('true','bool');
                    }
                }
            } 
            else 
            {
                exit(53);
            }
        }
        // Process AND and OR
        if($operand == 'AND' || $operand == 'OR' || $operand == 'ANDS' || $operand == 'ORS')
        {
            // Both types have to be bool
            if($store_type_1 == 'bool' && $store_type_2 == 'bool')
            {
                $value_1_bool = filter_var($value_1, FILTER_VALIDATE_BOOLEAN);
                $value_2_bool = filter_var($value_2, FILTER_VALIDATE_BOOLEAN);
                switch ($operand) 
                {
                    case 'AND':
                    case 'ANDS':
                        $result = $value_1_bool && $value_2_bool;
                        break;
                    case 'OR':
                    case 'ORS':
                        $result = $value_1_bool || $value_2_bool;
                        break;
                    default:
                        exit(53); 
                }
                // Store result into variable
                if($operand === 'AND' || $operand === 'OR')
                {
                    if($result === false)
                    {
                        $this->switch_for_frames($destination_frame, $destination_name, 'false', 'bool');
                    }
                    else
                    {
                        $this->switch_for_frames($destination_frame, $destination_name, 'true', 'bool');
                    }
                    $this->switch_for_frames($destination_frame, $destination_name, $result ? 'true' : 'false', 'bool');
                }
                else
                {    
                    $this->data_stack->push($result ? 'true' : 'false','bool');  
                }
            }
            else
            {
                exit(53);
            }
        }
    }

    // Parse NOT instruction
    private function not(mixed $instruction,mixed $opcode) : void
    {
        // Extract arguments properties
        $destination = '';
        $destination_frame = '';
        $destination_parts = '';
        $destination_name = '';
        $operand_1 = '';

        if($opcode === 'NOT')
        {
            $destination = $instruction->args[0];
            $destination_parts = explode('@', $destination['value']);
            $destination_frame = $destination_parts[0];
            $destination_name = $destination_parts[1];
            // Check for existence
            if($this->find_element($destination_frame,$destination_name) == -1)
            {
                exit(54);
            }
            $operand_1 = $instruction->args[1];
        }
        else
        {
            $operand_1 = $this->data_stack->pop();
        }

        // Temporary variables for storing
        $result = '';
        $store_array = '';
        $store_value_1 = '';

        // If arguments type is var, then extract his value
        if($operand_1['type'] === 'var')
        {
            $store_array = $this->get_value($operand_1);
            if($store_array['type'] !== 'bool')
            {
                exit(53);
            }
            if($store_array['value'] === '')
            {
                exit(56);
            }
            $store_value_1 = $store_array['value'];
            if($store_array['type'] === 'bool')
            {
                // Store negated value back into variable
                $value_1_bool = filter_var($store_value_1, FILTER_VALIDATE_BOOLEAN);
                $result = !$value_1_bool;
            }
        } 
        else
        {
            // If type is bool then store negated value into variable
            if($operand_1['type'] == 'bool')
            {
                $store_value_1 = $operand_1['value'];
                $value_1_bool = filter_var($store_value_1, FILTER_VALIDATE_BOOLEAN);
                $result = !$value_1_bool;
            }
            else
            {
                exit(53);
            }
        }
        if($opcode === 'NOT')
        {
            $this->switch_for_frames($destination_frame, $destination_name, $result ? 'true' : 'false', 'bool');
        }
        else
        {
            $this->data_stack->push($result ? 'true' : 'false', 'bool');
        }       
    }

    // Parse INT2CHAR instruction
    private function int2char(mixed $instruction,mixed $opcode) : void
    {
        // Extract arguments properties

        $destination = '';
        $source = '';
        $destination_frame = '';
        $destination_name = '';

        if($opcode === 'INT2CHAR')
        {
            $destination = $instruction->args[0];
            $source = $instruction->args[1];
    
            $destination_frame = explode('@', $destination['value'])[0];
            $destination_name = explode('@', $destination['value'])[1];
    
            // Check for existence
            if($this->find_element($destination_frame,$destination_name) == -1)
            {
                exit(54);
            }
        }
        else
        {
            $source = $this->data_stack->pop();
        }

        // If variables type is var then we extract his value
        if ($source['type'] == 'var') 
        {
            $store_element = $this->get_value($source);
            if ($store_element['type'] != 'int') 
            {
                exit(53);
            }
            $int_value = intval($store_element['value']);
            // Check if the integer value is within the ASCII range
            if ($int_value < 0 || $int_value > 255) 
            {
                exit(58); // Invalid ASCII value error
            }
            $char = chr($int_value); // Convert ASCII value to character
        } 
        elseif ($source['type'] == 'int') 
        {
            $int_value = intval($source['value']);
            // Check if the integer value is within the ASCII range
            if ($int_value < 0 || $int_value > 255) 
            {
                exit(58); // Invalid ASCII value error
            }
            $char = chr($int_value); // Convert ASCII value to character
        } 
        else 
        {
            exit(53); // Invalid type error
        }

        if($opcode === 'INT2CHAR')
        {
            $this->switch_for_frames($destination_frame, $destination_name, $char, 'string');
        }
        else
        {
            $this->data_stack->push($char,'string');
        }
    }

    // Parse STRI2INT, this instruction will convert string into int
    private function stri2int(mixed $instruction,mixed $opcode) : void
    {   
        // Extract arguments properties
        $destination = '';
        $string_symbol = '';
        $index_symbol = '';
        $destination_frame = '';
        $destination_name = '';

        if($opcode === 'STRI2INT')
        {
            $destination = $instruction->args[0];
            $string_symbol = $instruction->args[1];
            $index_symbol = $instruction->args[2];
    
            $destination_frame = explode('@', $destination['value'])[0];
            $destination_name = explode('@', $destination['value'])[1];

            if($this->find_element($destination_frame,$destination_name) == -1)
            {
                exit(54);
            }
        }
        else
        {
            $index_symbol = $this->data_stack->pop();
            $string_symbol = $this->data_stack->pop();
        }

        $string_value = '';
        $index_value = '';

        // Check for existence

        if ($string_symbol['type'] == 'var') 
        {
            $string_array = $this->get_value($string_symbol);
            if($string_array['type'] !== 'string')
            {
                exit(53); // Invalid string
            }
            $string_value = $string_array['value'];
        } 
        elseif ($string_symbol['type'] == 'string') 
        {
            $string_value = $string_symbol['value'];
        } 
        else 
        {
            exit(53); // Invalid operand type
        }

        if ($index_symbol['type'] == 'var') 
        {
            $index_value_array = $this->get_value($index_symbol);
            if($index_value_array['type'] !== 'int')
            {
                exit(53);
            }
            $index_value = $index_value_array['value'];
        } 
        elseif ($index_symbol['type'] == 'int') 
        {
            $index_value = $index_symbol['value'];
        } 
        else 
        {
            exit(53); // Invalid operand type
        }

        $index_value = intval($index_value); // Convert index to integer

        if ($index_value < 0 || $index_value >= mb_strlen($string_value, 'UTF-8')) {
            exit(58); // Index out of range
        }

        $char = mb_substr($string_value, $index_value, 1, 'UTF-8');
        $ord_value = mb_ord($char, 'UTF-8');

        if ($ord_value === 1) {
            exit(58); // Error getting ordinal value
        }
        // Store value into variable
        if($opcode === 'STRI2INT')
        {
            $this->switch_for_frames($destination_frame, $destination_name, $ord_value, 'int');
        }
        else
        {
            $this->data_stack->push($ord_value, 'int');
        }
    }

    // Parse READ instruction, this instruction will read from input.
    private function read(mixed $instruction) : void
    {   
        // Extract argument properties
        $destination = $instruction->args[0];
        $second = $instruction->args[1];
        if($second['type'] !== 'type')
        {
            exit(32);
        }
        $type = $second['value'];

        $destination_frame = explode('@', $destination['value'])[0];
        $destination_name = explode('@', $destination['value'])[1];

        // Check for existence
        if($this->find_element($destination_frame,$destination_name) == -1)
        {
            exit(54);
        }

        // Read
        $val = $this->input->readString(); 

        // No input ? => nil
        if ($val === null) {
            $type = 'nil';
        }
        switch ($type) {
            case 'int':
                if (!is_numeric($val)) 
                {
                    $this->switch_for_frames($destination_frame, $destination_name, 'nil', 'nil');
                    return;
                }
                $val = (int)$val; 
                break;
            case 'string':
                break;
            case 'bool':
                // Convert input value to lowercase for case-insensitive comparison
                $val = strtolower($val);
                if ($val !== 'true' && $val !== 'false') 
                {
                    $this->switch_for_frames($destination_frame, $destination_name, 'nil', 'nil');
                    return;
                }
                // $val = ($val === 'true');
                if($val === 'true')
                {
                    $this->switch_for_frames($destination_frame, $destination_name,'true', $type);
                }
                else
                {
                    $this->switch_for_frames($destination_frame, $destination_name, 'false', $type);
                }
                return;
            default:
                $this->switch_for_frames($destination_frame, $destination_name, 'nil', 'nil');
                return;
        }
        // Store into variable
        $this->switch_for_frames($destination_frame, $destination_name, $val, $type);
    }

    // Parse WRITE instruction, print value to output
    private function write(mixed $instruction) : void
    {
        $value = $instruction->args[0];

        // If it is var, then extract his type
        if ($value['type'] === 'var') {
            $value = $this->get_value($value);
        }
        // Type nil ? print empty line
        if ($value['type'] === 'nil') {
            $this->stdout->writeString('');
            return;
        }
        else
        {
            switch ($value['type']) 
            {
                case 'int':
                    $this->stdout->writeInt(intval($value['value']));
                    break;
                case 'string':
                    $this->stdout->writeString($value['value']);
                    break;
                case 'bool':
                    $value_1_bool = filter_var($value['value'], FILTER_VALIDATE_BOOLEAN);
                    if($value['value'] === 'true')
                    {
                        $this->stdout->writeBool($value_1_bool);
                    }
                    else
                    {
                        $this->stdout->writeBool($value_1_bool);
                    }
                    break;
                default:
                    exit(53); // Invalid operand value
            }
        }
    }

    // Parse CONCAT instruction 
    private function concat(mixed $instruction,mixed $opcode) : void
    {
        // Extract arguments properties
        $destination = $instruction->args[0];
        $symbol_1 = $instruction->args[1];
        $symbol_2 = '';
        $store = '';

        // If opcode is strlen then it has one more argument
        if($opcode != 'STRLEN')
        {
            $symbol_2 = $instruction->args[2];
        }
        
        $destination_frame = explode('@', $destination['value'])[0];
        $destination_name = explode('@', $destination['value'])[1];

        $to_concat_1 = '';
        $to_concat_2 = '';
        $store_destination_value = '';

        // Search for element
        if($this->find_element($destination_frame,$destination_name) == -1)
        {
            exit(54);
        }

        // According to opcode get value and type
        if($opcode == 'SETCHAR')
        {
            $store = $this->get_value($destination);
            if($store['type'] != 'string')
            {
                exit(53);
            }
            $store_destination_value = $store['value']; 
        }
        if($opcode != 'SETCHAR')
        {
            if($symbol_1['type'] == 'var')
            {
                $store_1 = $this->get_value($symbol_1);
                if($store_1['type'] != 'string')
                {
                    exit(53);
                }
                $to_concat_1 = $store_1['value'];
            }
            else
            {
                if($symbol_1['type'] != 'string')
                {
                    exit(53);
                }
                $to_concat_1 = $symbol_1['value'];
            }
        }
        if($opcode != 'STRLEN' && $opcode != 'GETCHAR')
        {
            if($symbol_2['type'] == 'var')
            {
                $store_2 = $this->get_value($symbol_2);
                if($store_2['type'] != 'string')
                {
                    exit(53);
                }
                $to_concat_2 = $store_2['value'];
            }
            else
            {
                if($symbol_2['type'] != 'string')
                {
                    exit(53);
                }
                $to_concat_2 = $symbol_2['value'];
            }
        }
        // Store element into variable in frame
        if($opcode == 'CONCAT')
        {
            $concatenated_string = $to_concat_1 . $to_concat_2;
            $this->switch_for_frames($destination_frame, $destination_name, $concatenated_string, 'string');
        }
        elseif($opcode == 'STRLEN')
        {
            $len = strlen($to_concat_1);
            $this->switch_for_frames($destination_frame, $destination_name, $len, 'int');
        }
        // Process getchar and store result into variable into frame
        elseif($opcode == 'GETCHAR')
        {
            $index = 0;
            if($symbol_2['type'] == 'var')
            {
                $store_index = $this->get_value($symbol_2);
                if($store_index['type'] != 'int')
                {
                    exit(53);
                }
                $index = intval($store_index['value']);
            }
            else
            {
                if($symbol_2['type'] != 'int')
                {
                    exit(53);
                }
                $index = intval($symbol_2['value']);
            }
            
            if($index < 0 || $index >= strlen($to_concat_1))
            {
                exit(58);
            }

            $char = $to_concat_1[$index];
            $this->switch_for_frames($destination_frame, $destination_name, $char, 'string');
        }
        // Process setchar and store it into variable into frame
        elseif($opcode == 'SETCHAR')
        {
            if($symbol_1['type'] == 'var')
            {
                $store_1 = $this->get_value($symbol_1);
                if($store_1['type'] != 'int')
                {
                    exit(53);
                }
                $to_concat_1 = intval($store_1['value']);
            }
            else
            {
                if($symbol_1['type'] != 'int')
                {
                    exit(53);
                }
                $to_concat_1 = intval($symbol_1['value']);
            }

            if($to_concat_1 < 0 || $to_concat_1 >= strlen($store_destination_value))
            {
                exit(58);
            }
            $to_concat_2 = $instruction->args[2];
            if($to_concat_2['type'] == 'var')
            {
                $to_concat_2 = $this->get_value($to_concat_2);
            }
            if($to_concat_2['value'] == '')
            {
                exit(53);
            }
            if($to_concat_2['type'] != 'string')
            {
                exit(53);
            }
            $char_to_set = substr($to_concat_2['value'], 0, 1);
            $new_string = substr_replace($store_destination_value, $char_to_set, $to_concat_1, 1);
            $this->switch_for_frames($destination_frame, $destination_name, $new_string, 'string');
        }
    }

    // Function for checking if type is valid
    private function is_valid_type(mixed $type) : void
    {
        if (!in_array($type, ['bool', 'nil', 'string', 'int'])) {
            $this->stderr->writeString("Error is_valid_type");
            exit(53); // Invalid type error
        }
    }

    // Function for comparation of types 
    private function check_symbol_types(mixed $type1,mixed $type2) : void
    {
        if ($type1 !== $type2 && $type1 !== 'nil' && $type2 !== 'nil') {
            $this->stderr->writeString("Error is_valid_type");
            exit(53); // Incompatible types error
        }
    }

    // Process jumps
    private function jumps(mixed $instruction,mixed $opcode) : mixed
    {
        // Find the corresponding label and update the index
        $labelName = $instruction->args[0]['value'];
        $order = $this->findLabelOrder($labelName);
        if ($order === null) {
            exit(52); // Label not found error
        }

        // Extract arguments properties
        if($opcode === 'JUMPIFEQ' || $opcode === 'JUMPIFNEQ' || $opcode === 'JUMPIFEQS' || $opcode === 'JUMPIFNEQS')
        {
            if($opcode === 'JUMPIFEQ' || $opcode === 'JUMPIFNEQ')
            {
                $symbol_1 = $instruction->args[1];
                $symbol_2 = $instruction->args[2];
            }
            else
            {
                $symbol_2 = $this->data_stack->pop(); 
                $symbol_1 = $this->data_stack->pop(); 
            }
            $compare_1 = '';
            $compare_2 = '';

            $type_1 = '';
            $type_2 = '';


            if($symbol_1['type'] == 'var')
            {
                $store_1 = $this->get_value($symbol_1);
                $this->is_valid_type($store_1['type']);
                $type_1 = $store_1['type'];
                $compare_1 = $store_1['value'];
            }
            else
            {
                $this->is_valid_type($symbol_1['type']);
                $type_1 = $symbol_1['type'];
                $compare_1 = $symbol_1['value'];
            }
            if($symbol_2['type'] == 'var')
            {
                $store_2 = $this->get_value($symbol_2);
                $this->is_valid_type($store_2['type']);
                $type_2 = $store_2['type'];
                $compare_2 = $store_2['value'];
            }
            else
            {
                $this->is_valid_type($symbol_2['type']);
                $type_2 = $symbol_2['type'];
                $compare_2 = $symbol_2['value'];
            }

            $this->check_symbol_types($type_1,$type_2);
            // Evaluate condition and return order
            if (($opcode === 'JUMPIFEQ' || $opcode === 'JUMPIFEQS') && strcmp($compare_1,$compare_2) !== 0) {
                return -1;
            }
    
            if (($opcode === 'JUMPIFNEQ'|| $opcode === 'JUMPIFNEQS') && strcmp($compare_1,$compare_2) === 0) {
                return -1;
            }
        }
        return $order - 1; // Update the index to the label position
    }

    // Process exit opcode
    private function exit_function(mixed $instruction) : void
    {   
        // Extract variables properties
        $symbol = $instruction->args[0];
        if($symbol['type'] === 'var')
        {
            $store = $this->get_value($symbol);
            if($store['type'] !== 'int')
            {
                exit(53);
            }
            $exit_code = intval($store['value']);

        }
        else
        {
            if($symbol['type'] !== 'int')
            {
                exit(53);
            }
            $exit_code = intval($symbol['value']);
        }

        if ($exit_code < 0 || $exit_code > 9) {
            exit(57); // Invalid exit code error
        }
        exit($exit_code);
    }

    // Process type opcode
    private function type_function(mixed $instruction) : void
    {   
        // Extract arguments properties
        $destination = $instruction->args[0];
        $symbol = $instruction->args[1];

        $destination_frame = explode('@', $destination['value'])[0];
        $destination_name = explode('@', $destination['value'])[1];

        $value = '';
        // Search for element
        if($this->find_element($destination_frame,$destination_name) === -1)
        {
            exit(54);
        }
        if($symbol['type'] === 'var')
        {
            $store = $this->get_type($symbol);
            if($store !== '')
            {
                $this->is_valid_type($store);
            }
            $value = $store;
        }
        else
        {
            $this->is_valid_type($symbol['type']);
            $value = $symbol['type'];
        }
        // Store element
        $this->switch_for_frames($destination_frame,$destination_name,$value,'string');
    }

    // Process dprint opcode
    private function dprint(mixed $instruction) : void
    {   
        // Extract arguments properties
        $symbol = $instruction->args[0];
        $value = '';

        if($symbol['type'] === 'var')
        {
            $store = $this->get_value($symbol);
            $value = $store['value'];
        }
        else
        {
            $value = $symbol['value'];
        }
        // Print to stderr
        $this->stderr->writeString($value);
    }

    // Process break opcode
    private function break_function(mixed $num_of_instrictions,mixed $position) : void
    {
        $this->stderr->writeString($num_of_instrictions + 1 . "\n");
        $this->stderr->writeString($position + 1 . "\n");
    }

    // Process call opcode
    private function call_function(mixed $instruction) : mixed
    {
        // Check for labels existence and return his order
        $labelName = $instruction->args[0]['value'];
        $label_order = intval($instruction->order);
        $new_order = $this->findLabelOrder($labelName);
        if($new_order === null)
        {
            exit(52);
        }
        $this->call_stack->push($label_order-1,$labelName);
        return $new_order - 1;
    }
}

    