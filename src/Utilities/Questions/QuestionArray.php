<?php

namespace Phabalicious\Utilities\Questions;

use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Style\SymfonyStyle;

class QuestionArray extends Question implements QuestionInterface
{
    public static function getName()
    {
        return 'array';
    }

    public function setData($question_data): ValidationService
    {
        $question_data['question'] .= ' (Keep the answer empty to continue)';

        return parent::setData($question_data);
    }

    public function ask(SymfonyStyle $io)
    {
        $result = [];

        do {
            $value = parent::ask($io);
            if (!empty($value)) {
                $result[] = trim($value);
            }
        } while (!empty($value));

        return $result;
    }
}
