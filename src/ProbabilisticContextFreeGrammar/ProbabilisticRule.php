<?php

namespace olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar;

use olcaytaner\ParseTree\Symbol;
use olcaytaner\SyntacticParser\ContextFreeGrammar\Rule;
use olcaytaner\SyntacticParser\ContextFreeGrammar\RuleType;

class ProbabilisticRule extends Rule
{
    private float $probability;

    private int $count = 0;

    /**
     * Constructor for the probabilistic rule X -&gt;  beta. beta is a string of symbols from symbols (non-terminal)
     * @param Symbol $leftHandSide Non-terminal symbol X.
     * @param array $rightHandSide beta. beta is a string of symbols from symbols (non-terminal)
     * @param RuleType $type Type of the rule. TERMINAL if the rule is like X -&gt;  a, SINGLE_NON_TERMINAL if the rule is like X -&gt;  Y,
     *             TWO_NON_TERMINAL if the rule is like X -&gt;  YZ, MULTIPLE_NON_TERMINAL if the rule is like X -&gt;  YZT..
     * @param float $probability Probability of the rule
     */
    public function constructor7(Symbol $leftHandSide, array $rightHandSide, RuleType $type, float $probability): void
    {
        parent::constructor5($leftHandSide, $rightHandSide, $type);
        $this->probability = $probability;
    }

    /**
     * Constructor for the rule X -&gt;  beta. beta is a string of symbols from symbols (non-terminal)
     * @param Symbol $leftHandSide Non-terminal symbol X.
     * @param array $rightHandSide beta. beta is a string of symbols from symbols (non-terminal)
     */
    public function constructor4(Symbol $leftHandSide, array $rightHandSide): void
    {
        parent::constructor4($leftHandSide, $rightHandSide);
    }

    /**
     * Constructor for any probabilistic rule from a string. The string is of the form X -&gt;  .... [probability] The
     * method constructs left hand side symbol and right hand side symbol(s) from the input string.
     * @param string $rule String containing the rule. The string is of the form X -&gt;  .... [probability]
     */
    public function constructor6(string $rule): void
    {
        $this->probability = floatval(trim(mb_substr($rule, mb_strpos($rule, '[') + 1, mb_strpos($rule, ']') - mb_strpos($rule, '[') - 1)));
        $left = trim(mb_substr($rule, 0, mb_strpos($rule, '-> ')));
        $right = trim(mb_substr($rule, mb_strpos($rule, '-> ') + 2, mb_strpos($rule, '[') - mb_strpos($rule, '-> ') - 2));
        $this->leftHandSide = new Symbol($left);
        $rightHandSide = explode(" ", $right);
        foreach ($rightHandSide as $symbol) {
            $this->rightHandSide[] = new Symbol($symbol);
        }
    }

    public function __construct(string|Symbol $item1, array|null $item2 = null, RuleType|null $type = null, float|null $probability = null)
    {
        if ($type !== null) {
            $this->constructor7($item1, $item2, $type, $probability);
        } else {
            if ($item2 !== null) {
                $this->constructor4($item1, $item2);
            } else {
                $this->constructor6($item1);
            }
        }
    }

    /**
     * Accessor for the probability attribute.
     * @return float Probability attribute.
     */
    public function getProbability(): float
    {
        return $this->probability;
    }

    /**
     * Accessor for the count attribute
     * @return int Count attribute
     */
    public function getCount(): int
    {
        return $this->count;
    }

    /**
     * Increments the count attribute.
     */
    public function increment(): void{
        $this->count++;
    }

    /**
     * Calculates the probability from count and the given total value.
     * @param int $total Value used for calculating the probability.
     */
    public function normalizeProbability(int $total): void{
        $this->probability = $this->count / ($total + 0.0);
    }

    /**
     * Converts the rule to the form X -&gt;  ... [probability]
     * @return string String form of the rule in the form of X -&gt;  ... [probability]
     */
    public function __toString(): string
    {
        return parent::__toString() . "[" . $this->probability . "]";
    }


}