<?php

namespace Phabalicious\Utilities\Questions;

use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;

abstract class QuestionBase implements QuestionInterface
{
    protected $data = [];

    public function setData($question_data): ValidationService
    {
        $this->data = $question_data;
        $validation = new ValidationService($question_data, new ValidationErrorBag(), "question data");

        $validation->hasKey('question', 'A question needs a question');
        if (!empty($question_data['validation'])) {
            $validation->hasKey('validation', 'Please provide a regex for validation');
            $validation->hasKey('error', 'Please provide an error message when a validation fails');
        }
        return $validation;
    }

    public function validate($value)
    {
        if (!empty($this->data['validation'])) {
            if (!preg_match($this->data['validation'], $value)) {
                throw new \InvalidArgumentException($this->data['error'] . ': ' . $value);
            }
        }
        return $value;
    }

    public function getHelp()
    {
        return $this->data['help'] ?? false;
    }
}
