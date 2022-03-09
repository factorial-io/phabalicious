<?php

namespace Phabalicious\Utilities\Questions;

use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class Confirm extends QuestionBase implements QuestionInterface
{

    public static function getName()
    {
        return 'confirm';
    }

    public function ask(SymfonyStyle $io)
    {
        $question = new ConfirmationQuestion(
            $this->data['question'],
            $this->data['default'] ?? false
        );

        return $io->askQuestion($question) ? 1 : 0;
    }
}
