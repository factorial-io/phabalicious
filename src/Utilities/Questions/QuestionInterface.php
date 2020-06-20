<?php

namespace Phabalicious\Utilities\Questions;

use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Style\SymfonyStyle;

interface QuestionInterface
{
    public static function getName();

    public function setData($question_data): ValidationService;

    public function ask(SymfonyStyle $io);
    
    public function validate($value);
}
