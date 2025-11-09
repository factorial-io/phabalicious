<?php

namespace Phabalicious\Utilities\Questions;

use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class Choices extends QuestionBase implements QuestionInterface
{
    public static function getName()
    {
        return 'choices';
    }

    public function setData($question_data): ValidationService
    {
        $validation = parent::setData($question_data);
        $validation->hasKey('choices', 'Please provide an array with possible choices');

        return $validation;
    }

    public function ask(SymfonyStyle $io)
    {
        $question = new ChoiceQuestion(
            $this->data['question'],
            $this->data['choices'],
            $this->data['default'] ?? null
        );

        if (isset($this->data['multiselect'])) {
            $question->setMultiselect($this->data['multiselect']);
        }

        return $io->askQuestion($question);
    }
}
