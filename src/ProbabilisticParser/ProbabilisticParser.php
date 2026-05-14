<?php

namespace olcaytaner\SyntacticParser\ProbabilisticParser;

use olcaytaner\Corpus\Sentence;
use olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar\ProbabilisticContextFreeGrammar;

interface ProbabilisticParser
{
    public function parse(ProbabilisticContextFreeGrammar $pCfg, Sentence $sentence): array;
}