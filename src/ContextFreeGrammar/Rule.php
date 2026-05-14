<?php

namespace olcaytaner\SyntacticParser\ContextFreeGrammar;

use olcaytaner\ParseTree\Symbol;

class Rule
{
    protected Symbol $leftHandSide;
    protected array $rightHandSide = [];
    protected RuleType $type;

    /**
     * Empty constructor for the rule class.
     */
    public function constructor1(): void
    {
    }

    public function constructor2(Symbol $leftHandSide, Symbol $rightHandSideSymbol): void
    {
        $this->leftHandSide = $leftHandSide;
        $this->rightHandSide[] = $rightHandSideSymbol;
    }

    /**
     * Constructor for the rule X -&gt;  YZ.
     * @param Symbol $leftHandSide Non-terminal symbol X.
     * @param Symbol $rightHandSideSymbol1 Symbol Y (non-terminal).
     * @param Symbol $rightHandSideSymbol2 Symbol Z (non-terminal).
     */
    public function constructor3(Symbol $leftHandSide, Symbol $rightHandSideSymbol1, Symbol $rightHandSideSymbol2): void
    {
        $this->leftHandSide = $leftHandSide;
        $this->rightHandSide[] = $rightHandSideSymbol1;
        $this->rightHandSide[] = $rightHandSideSymbol2;
    }

    /**
     * Constructor for the rule X -&gt;  beta. beta is a string of symbols from symbols (non-terminal)
     * @param Symbol $leftHandSide Non-terminal symbol X.
     * @param array $rightHandSide beta. beta is a string of symbols from symbols (non-terminal)
     */
    public function constructor4(Symbol $leftHandSide, array $rightHandSide): void
    {
        $this->leftHandSide = $leftHandSide;
        $this->rightHandSide = $rightHandSide;
    }

    /**
     * Constructor for the rule X -&gt;  beta. beta is a string of symbols from symbols (non-terminal)
     * @param Symbol $leftHandSide Non-terminal symbol X.
     * @param array $rightHandSide beta. beta is a string of symbols from symbols (non-terminal)
     * @param RuleType $type Type of the rule. TERMINAL if the rule is like X -&gt;  a, SINGLE_NON_TERMINAL if the rule is like X -&gt;  Y,
     *             TWO_NON_TERMINAL if the rule is like X -&gt;  YZ, MULTIPLE_NON_TERMINAL if the rule is like X -&gt;  YZT..
     */
    public function constructor5(Symbol $leftHandSide, array $rightHandSide, RuleType $type): void
    {
        $this->constructor4($leftHandSide, $rightHandSide);
        $this->type = $type;
    }

    /**
     * Constructor for any rule from a string. The string is of the form X -&gt;  .... The method constructs left hand
     * side symbol and right hand side symbol(s) from the input string.
     * @param string $rule String containing the rule. The string is of the form X -&gt;  ....
     */
    public function constructor6(string $rule): void
    {
        $left = trim(mb_substr($rule, 0, mb_strpos($rule, '-> ')));
        $right = trim(mb_substr($rule, mb_strpos($rule, '-> ') + 2));
        $this->leftHandSide = new Symbol($left);
        $rightHandSide = explode(" ", $right);
        foreach ($rightHandSide as $symbol) {
            $this->rightHandSide[] = new Symbol($symbol);
        }
    }

    public function __construct(Symbol|string|null $item1 = null, array|Symbol|null $item2 = null, Symbol|RuleType|null $item3 = null)
    {
        if ($item1 === null) {
            $this->constructor1();
        } else {
            if ($item1 instanceof Symbol) {
                if ($item3 === null) {
                    if (is_array($item2)) {
                        $this->constructor4($item1, $item2);
                    } else {
                        $this->constructor2($item1, $item2);
                    }
                } else {
                    if ($item3 instanceof Symbol) {
                        $this->constructor3($item1, $item2, $item3);
                    } else {
                        if ($item3 instanceof RuleType) {
                            $this->constructor5($item1, $item2, $item3);
                        }
                    }
                }
            } else {
                $this->constructor6($item1);
            }
        }
    }

    /**
     * Checks if the rule is left recursive or not. A rule is left recursive if it is of the form X -&gt;  X..., so its
     * first symbol of the right side is the symbol on the left side.
     * @return bool True, if the rule is left recursive; false otherwise.
     */
    public function leftRecursive(): bool
    {
        return $this->rightHandSide[0] == $this->leftHandSide && $this->type == RuleType::SINGLE_NON_TERMINAL;
    }

    /**
     * In conversion to Chomsky Normal Form, rules like A -&gt;  BC... are replaced with A -&gt;  X1... and X1 -&gt;  BC. This
     * method replaces B and C non-terminals on the right hand side with X1.
     * @param Symbol $first Non-terminal symbol B.
     * @param Symbol $second Non-terminal symbol C.
     * @param Symbol $with Non-terminal symbol X1.
     * @return bool True, if any replacements has been made; false otherwise.
     */
    public function updateMultipleNonTerminal(Symbol $first, Symbol $second, Symbol $with): bool
    {
        for ($i = 0; $i < count($this->rightHandSide) - 1; $i++) {
            if ($this->rightHandSide[$i] == $first && $this->rightHandSide[$i + 1] == $second) {
                array_splice($this->rightHandSide, $i + 1, 1);
                array_splice($this->rightHandSide, $i, 1, $with);
                if (count($this->rightHandSide) == 2) {
                    $this->type = RuleType::TWO_NON_TERMINAL;
                }
                return true;
            }
        }
        return false;
    }

    /**
     * Accessor for the left hand side.
     * @return Symbol Left hand side.
     */
    public function getLeftHandSide(): Symbol
    {
        return $this->leftHandSide;
    }

    /**
     * Accessor for the right hand side.
     * @return array Right hand side.
     */
    public function getRightHandSide(): array
    {
        return $this->rightHandSide;
    }

    /**
     * Accessor for the rule type.
     * @return RuleType Rule type.
     */
    public function getType(): RuleType
    {
        return $this->type;
    }

    /**
     * Returns number of symbols on the right hand side.
     * @return int Number of symbols on the right hand side.
     */
    public function getRightHandSize(): int
    {
        return count($this->rightHandSide);
    }

    /**
     * Returns symbol at position index on the right hand side.
     * @param int $index Position of the symbol
     * @return Symbol Symbol at position index on the right hand side.
     */
    public function getRightHandSideAt(int $index): Symbol
    {
        return $this->rightHandSide[$index];
    }

    /**
     * Converts the rule to the form X -&gt;  ...
     * @return string String form of the rule in the form of X -&gt;  ...
     */
    public function __toString(): string
    {
        $result = $this->leftHandSide->getName() . " ->";
        foreach ($this->rightHandSide as $symbol) {
            $result .= " " . $symbol;
        }
        return $result;
    }

    /**
     * Compares two rules based on first their left hand side and their right hand side lexicographically.
     * @param Rule $ruleA the first rule to be compared.
     * @param Rule $ruleB the second rule to be compared.
     * @return int -1 if the first rule is less than the second rule lexicographically, 1 if the first rule is larger than
     * the second rule lexicographically, 0 if they are the same rule.
     */
    static function ruleCompare(Rule $ruleA, Rule $ruleB): int
    {
        if ($ruleA->getLeftHandSide() == $ruleB->getLeftHandSide()){
            return Rule::ruleRightSideCompare($ruleA, $ruleB);
        } else {
            return Rule::ruleLeftSideCompare($ruleA, $ruleB);
        }
    }

    /**
     * Compares two rules based on their left sides lexicographically.
     * @param Rule $ruleA the first rule to be compared.
     * @param Rule $ruleB the second rule to be compared.
     * @return int -1 if the first rule is less than the second rule lexicographically, 1 if the first rule is larger than
     *          the second rule lexicographically, 0 if they are the same rule.
     */
    static function ruleLeftSideCompare(Rule $ruleA, Rule $ruleB): int
    {
        return strcmp($ruleA->getLeftHandSide()->getName(), $ruleB->getLeftHandSide()->getName());
    }

    /**
     * Compares two rules based on their right sides lexicographically.
     * @param Rule $ruleA the first rule to be compared.
     * @param Rule $ruleB the second rule to be compared.
     * @return int -1 if the first rule is less than the second rule lexicographically, 1 if the first rule is larger than
     *          the second rule lexicographically, 0 if they are the same rule.
     */
    static function ruleRightSideCompare(Rule $ruleA, Rule $ruleB): int{
        $i = 0;
        while ($i < $ruleA->getRightHandSize() && $i < $ruleB->getRightHandSize()){
            if ($ruleA->getRightHandSideAt($i)->getName() == $ruleB->getRightHandSideAt($i)->getName()){
                $i++;
            } else {
                return strcmp($ruleA->getRightHandSideAt($i)->getName(), $ruleB->getRightHandSideAt($i)->getName());
            }
        }
        if ($ruleA->getRightHandSize() == $ruleB->getRightHandSize()){
            return 0;
        } else {
            if ($ruleA->getRightHandSize() < $ruleB->getRightHandSize()){
                return -1;
            } else {
                return 1;
            }
        }
    }

    public function setType(RuleType $type): void
    {
        $this->type = $type;
    }

}