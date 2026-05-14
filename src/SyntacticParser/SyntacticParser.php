<?php

namespace olcaytaner\SyntacticParser\SyntacticParser;

use olcaytaner\Corpus\Sentence;
use olcaytaner\SyntacticParser\ContextFreeGrammar\ContextFreeGrammar;

interface SyntacticParser
{
    public function parse(ContextFreeGrammar $cfg, Sentence $sentence): array;
}