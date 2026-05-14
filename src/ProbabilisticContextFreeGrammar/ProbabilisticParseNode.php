<?php

namespace olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar;

use olcaytaner\ParseTree\ParseNode;
use olcaytaner\ParseTree\Symbol;

class ProbabilisticParseNode extends ParseNode
{
    private float $logProbability;

    /**
     * Constructor for the ProbabilisticParseNode class. Extends the parse node with a probability.
     * @param ParseNode $left Left child of this node.
     * @param ParseNode $right Right child of this node.
     * @param Symbol $data Data for this node.
     * @param float $logProbability Logarithm of the probability of the node.
     */
    private function constructor1(ParseNode $left, ParseNode $right, Symbol $data, float $logProbability): void
    {
        parent::__construct($data, $left, $right);
        $this->logProbability = $logProbability;
    }

    /**
     * Another constructor for the ProbabilisticParseNode class.
     * @param ParseNode $left Left child of this node.
     * @param Symbol $data Data for this node.
     * @param float $probability logProbability Logarithm of the probability of the node.
     */
    private function constructor2(ParseNode $left, Symbol $data, float $probability): void
    {
        parent::__construct($data, $left);
        $this->logProbability = $probability;
    }

    /**
     * Another constructor for the ProbabilisticParseNode class.
     * @param Symbol $data Data for this node.
     * @param float $logProbability Logarithm of the probability of the node.
     */
    private function constructor3(Symbol $data, float $logProbability): void
    {
        parent::__construct($data);
        $this->logProbability = $logProbability;
    }

    public function __construct(
        ParseNode|Symbol $item1,
        ParseNode|Symbol|float $item2,
        Symbol|float|null $item3 = null,
        float|null $item4 = null
    ) {
        if ($item4 === null) {
            if ($item3 === null) {
                $this->constructor3($item1, $item2);
            } else {
                $this->constructor2($item1, $item2, $item3);
            }
        } else {
            $this->constructor1($item1, $item2, $item3, $item4);
        }
    }

    /**
     * Accessor for the logProbability attribute.
     * @return float logProbability attribute.
     */
    public function getLogProbability(): float
    {
        return $this->logProbability;
    }

}