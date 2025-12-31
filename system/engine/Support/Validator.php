<?php

namespace Engine\Support;

class Validator
{
    protected array $data;
    protected array $rules;
    protected array $errors = [];
    protected bool $validated = false;

    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    public static function make(array $data, array $rules): self
    {
        return new self($data, $rules);
    }

    public function fails(): bool
    {
        $this->check();
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    public function errors(): array
    {
        $this->check();
        return $this->errors;
    }

    public function validated(): array
    {
        $this->check();
        // Return only the fields that were present in rules
        // But we need to preserve the values from data
        $result = [];
        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $result[$field] = $this->data[$field];
            } else {
                $result[$field] = null;
            }
        }
        return $result;
    }

    protected function check()
    {
        if ($this->validated) {
            return;
        }

        foreach ($this->rules as $field => $ruleString) {
            $value = $this->data[$field] ?? null;
            
            $rulesList = is_string($ruleString) ? explode('|', $ruleString) : $ruleString;
            
            foreach ($rulesList as $rule) {
                $rule = trim($rule);
                
                if ($rule === 'required') {
                    if (is_null($value) || $value === '' || (is_array($value) && empty($value))) {
                        $this->addError($field, "The $field field is required.");
                    }
                } elseif ($rule === 'email') {
                    if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                        $this->addError($field, "The $field field must be a valid email.");
                    }
                } elseif (str_starts_with($rule, 'min:')) {
                    $min = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) < $min) {
                        $this->addError($field, "The $field field must be at least $min characters.");
                    }
                } elseif (str_starts_with($rule, 'max:')) {
                    $max = (int) substr($rule, 4);
                    if (is_string($value) && strlen($value) > $max) {
                        $this->addError($field, "The $field field must not exceed $max characters.");
                    }
                }
            }
        }

        $this->validated = true;
    }

    protected function addError(string $field, string $message)
    {
        // Only add the first error for a field to avoid clutter
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }
}
