<?php

namespace Phabalicious\Utilities\Questions;

use Symfony\Component\Console\Question\Question as SymfonyQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class Question extends QuestionBase implements QuestionInterface
{

    public static function getName()
    {
        return 'question';
    }

    public function ask(SymfonyStyle $io)
    {
        $question = new SymfonyQuestion(
            $this->data['question'],
            $this->data['default'] ?? null
        );
        if (!empty($this->data['validation'])) {
            $question->setValidator(function ($answer) {
                return $this->validate($answer);
            });
        }
        if (!empty($this->data['hidden'])) {
            $question->setHidden(true);
        }
        if (isset($this->data['autocomplete'])) {
            $question->setAutocompleterValues($this->data['autocomplete']);
        }

        return $io->askQuestion($question);
    }
}
