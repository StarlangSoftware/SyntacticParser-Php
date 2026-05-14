<?php

namespace olcaytaner\SyntacticParser\SyntacticParser;

use olcaytaner\ParseTree\ParseNode;
use olcaytaner\SyntacticParser\ProbabilisticContextFreeGrammar\ProbabilisticParseNode;

class PartialParseList
{
    private array $partialParses;

    /**
     * Constructor for the PartialParseList class. Initializes partial parses array list.
     */
    public function __construct()
    {
        $this->partialParses = [];
    }

    /**
     * Adds a new partial parse (actually a parse node representing the root of the subtree of the partial parse)
     * @param ParseNode $parseNode Root of the subtree showing the partial parse.
     */
    public function addPartialParse(ParseNode $parseNode): void
    {
        $this->partialParses[] = $parseNode;
    }

    /**
     * Updates the partial parse by removing less probable nodes with the given parse node.
     * @param ProbabilisticParseNode $parseNode Parse node to be added to the partial parse.
     */
    public function updatePartialParse(ProbabilisticParseNode $parseNode): void
    {
        $found = false;
        $i = 0;
        while ($i < count($this->partialParses)) {
            $partialParse = $this->partialParses[$i];
            if ($partialParse instanceof ProbabilisticParseNode && $partialParse->getData()->getName() == $parseNode->getData()->getName()) {
                if ($partialParse->getLogProbability() < $parseNode->getLogProbability()) {
                    array_splice($this->partialParses, $i, 1, $parseNode);
                }
                $found = true;
                break;
            }
            $i++;
        }
        if (!$found) {
            $this->partialParses[] = $parseNode;
        }
    }

    /**
     * Accessor for the partialParses array list.
     * @param int $index Position of the parse node.
     * @return ParseNode Parse node at the given position.
     */
    public function getPartialParse(int $index): ParseNode
    {
        return $this->partialParses[$index];
    }

    /**
     * Returns size of the partial parse.
     * @return int Size of the partial parse.
     */
    public function size(): int{
        return count($this->partialParses);
    }
}