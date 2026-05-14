<?php

namespace olcaytaner\SyntacticParser\SyntacticParser;

use olcaytaner\Corpus\Sentence;
use olcaytaner\Dictionary\Dictionary\Word;
use olcaytaner\ParseTree\ParseNode;
use olcaytaner\ParseTree\ParseTree;
use olcaytaner\ParseTree\Symbol;
use olcaytaner\SyntacticParser\ContextFreeGrammar\ContextFreeGrammar;

class CYKParser implements SyntacticParser
{

    /**
     * Constructs an array of possible parse trees for a given sentence according to the given grammar. CYK parser
     * is based on a dynamic programming algorithm.
     * @param ContextFreeGrammar $cfg Context free grammar used in parsing.
     * @param Sentence $sentence Sentence to be parsed.
     * @return array Array list of possible parse trees for the given sentence.
     */
    public function parse(ContextFreeGrammar $cfg, Sentence $sentence): array
    {
        $parseTrees = [];
        $backup = new Sentence();
        for ($i = 0; $i < $sentence->wordCount(); $i++) {
            $backup->addWord(new Word($sentence->getWord($i)->getName()));
        }
        $cfg->updateExceptionalWordsInSentence($sentence);
        $table = [];
        for ($i = 0; $i < $sentence->wordCount(); $i++) {
            $subTable = [];
            for ($j = 0; $j < $sentence->wordCount(); $j++) {
                $subTable[] = new PartialParseList();
            }
            $table[] = $subTable;
        }
        for ($i = 0; $i < $sentence->wordCount(); $i++) {
            $candidates = $cfg->getTerminalRulesWithRightSideX(new Symbol($sentence->getWord($i)->getName()));
            foreach ($candidates as $candidate) {
                if ($table[$i][$i] instanceof PartialParseList) {
                    $table[$i][$i]->addPartialParse(new ParseNode($candidate->getLeftHandSide(), new ParseNode(new Symbol($sentence->getWord($i)->getName()))));
                }
            }
        }
        for ($j = 1; $j < $sentence->wordCount(); $j++) {
            for ($i = $j - 1; $i >= 0; $i--) {
                for ($k = $i; $k < $j; $k++) {
                    for ($x = 0; $x < $table[$i][$k]->size(); $x++) {
                        for ($y = 0; $y < $table[$k + 1][$j]->size(); $y++) {
                            $leftNode = $table[$i][$k]->getPartialParse($x);
                            $rightNode = $table[$k + 1][$j]->getPartialParse($y);
                            $candidates = $cfg->getRulesWithTwoNonTerminalsOnRightSide($leftNode->getData(), $rightNode->getData());
                            foreach ($candidates as $candidate) {
                                $table[$i][$j]->addPartialParse(new ParseNode($candidate->getLeftHandSide(), $leftNode, $rightNode));
                            }
                        }
                    }
                }
            }
        }
        for ($i = 0; $i < $table[0][$sentence->wordCount() - 1]->size(); $i++) {
            if ($table[0][$sentence->wordCount() - 1]->getPartialParse($i)->getData()->getName() == "S") {
                $parseTree = new ParseTree($table[0][$sentence->wordCount() - 1]->getPartialParse($i));
                $parseTree->correctParents();
                $parseTree->removeXNodes();
                $parseTrees[] = $parseTree;
            }
        }
        foreach ($parseTrees as $parseTree) {
            $cfg->reinsertExceptionalWordsFromSentence($parseTree, $backup);
        }
        return $parseTrees;
    }
}