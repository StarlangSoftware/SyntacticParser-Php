<?php

namespace olcaytaner\SyntacticParser\ContextFreeGrammar;

enum RuleType
{
case TERMINAL;
case SINGLE_NON_TERMINAL;
case TWO_NON_TERMINAL;
case MULTIPLE_NON_TERMINAL;
}